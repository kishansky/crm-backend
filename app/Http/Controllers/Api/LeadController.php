<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Carbon\Carbon;
use App\Models\User;
use App\Models\SalesTeam;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class LeadController extends Controller
{
    // ✅ LIST WITH ROLE FILTER
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $user = $request->user(); // 🔑 current logged in user

        $query = Lead::with(['salesPerson', 'statusHistory'])->latest();

        // ✅ If Sales Team → only their leads
        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        $leads = $query->paginate($perPage);

        return response()->json($leads);
    }

    // ✅ STORE
    public function store(Request $request)
    {
        $data = $request->all();

        // ✅ FIX datetime
        if ($request->timestamp) {
            $data['timestamp'] = Carbon::parse($request->timestamp)
                ->format('Y-m-d H:i:s');
        }

        $lead = Lead::create($data);

        return response()->json($lead);
    }

    // ✅ SHOW (ROLE SAFE)
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $lead = Lead::with(['salesPerson', 'statusHistory'])
            ->findOrFail($id);

        // ✅ Sales can only see their own lead
        if ($user instanceof SalesTeam && $lead->assigned_to !== $user->sales_person_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json($lead);
    }

    // ✅ UPDATE (ROLE SAFE)
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $lead = Lead::findOrFail($id);

        // ❌ Block sales from updating others' leads
        if ($user instanceof SalesTeam && $lead->assigned_to !== $user->sales_person_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $data = $request->all();

        // ✅ FIX datetime
        if ($request->timestamp) {
            $data['timestamp'] = Carbon::parse($request->timestamp)
                ->format('Y-m-d H:i:s');
        }

        $lead->update($data);

        return response()->json([
            'message' => 'Updated',
            'data' => $lead
        ]);
    }

    // ✅ DELETE (ADMIN ONLY recommended)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // ❌ Sales cannot delete
        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        Lead::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }



    public function importExcel(Request $request)
    {
        $user = $request->user();
        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
            'assigned_to' => 'nullable|string'
        ]);

        // ✅ may be null
        $assignedTo = $request->input('assigned_to', null);

        $file = $request->file('file');

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {

            if ($index === 0) continue;

            // ✅ skip empty rows
            if (empty($row[0])) continue;

            DB::table('leads_master')->insert([
                'lead_id' => 'L' . time() . rand(100, 999),
                'company_name' => $row[0] ?? null,
                'contact_person' => $row[1] ?? null,
                'phone_number' => $row[2] ?? null,
                'email' => $row[3] ?? null,
                'source' => $row[4] ?? null,

                // ✅ NULL SAFE
                'assigned_to' => $assignedTo,

                'enquiry_description' => $row[5] ?? null,
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Excel imported successfully 🚀'
        ]);
    }

    public function bulkAssign(Request $request)
    {
        $user = $request->user();

        // ❌ Only admin
        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $request->validate([
            'lead_ids' => 'required|array',
            'assigned_to' => 'required|string'
        ]);

        Lead::whereIn('lead_id', $request->lead_ids)
            ->update(['assigned_to' => $request->assigned_to]);

        return response()->json([
            'message' => 'Leads assigned successfully 🚀'
        ]);
    }

public function exportExcel(Request $request)
{
    $user = $request->user();

    if ($user instanceof \App\Models\SalesTeam) {
        return response()->json([
            'message' => 'Unauthorized (Admin only)'
        ], 403);
    }

    $columns = $request->input('columns', []);
    $leadIds = $request->input('lead_ids', []);
    $assignedTo = $request->input('assigned_to');
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');

    if (empty($columns)) {
        $columns = [
            'company_name',
            'contact_person',
            'phone_number',
            'email',
            'source',
            'assigned_to',
            'latest_status'
        ];
    }

    $query = Lead::with(['salesPerson', 'statusHistory']);

    if (!empty($leadIds)) {
        $query->whereIn('lead_id', $leadIds);
    }

    if ($assignedTo) {
        $query->where('assigned_to', $assignedTo);
    }

    if ($startDate && $endDate) {
        $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    $leads = $query->get();
    $count = $leads->count();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ✅ Header
    $sheet->fromArray($columns, null, 'A1');

    $rowNumber = 2;

    foreach ($leads as $lead) {

        $latestStatus = $lead->statusHistory
            ->sortByDesc('created_at')
            ->first();

        $colIndex = 0;

        foreach ($columns as $col) {

            // 🔤 Convert index → column letter
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);

            switch ($col) {

                case 'assigned_to':
                    $value = $lead->salesPerson->name ?? '';
                    break;

                case 'latest_status':
                    $value = $latestStatus->status_type ?? '';
                    break;

                case 'phone_number':
                    $value = (string) $lead->phone_number;
                    break;

                default:
                    $value = $lead->$col ?? '';
            }

            // ✅ Force TEXT (fix scientific notation)
            $sheet->setCellValueExplicit(
                $columnLetter . $rowNumber,
                (string)$value,
                DataType::TYPE_STRING
            );

            $colIndex++;
        }

        $rowNumber++;
    }

    $fileName = 'leads_export_' . time() . '.xlsx';
    $filePath = storage_path("app/public/$fileName");

    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    $response = response()->download($filePath)->deleteFileAfterSend(true);
    $response->headers->set('X-Total-Count', $count);

    return $response;
}
}
