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
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class LeadController extends Controller
{
    // ✅ LIST WITH ROLE FILTER
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $user = $request->user();

        // ✅ ONLY DEFINE QUERY ONCE
        $query = Lead::with(['salesPerson', 'latestStatus'])->latest();

        // ✅ ROLE FILTER
        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        // ✅ SEARCH
        if ($request->search && strlen($request->search) >= 3) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%$search%")
                    ->orWhere('contact_person', 'like', "%$search%")
                    ->orWhere('phone_number', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        // ✅ SOURCE FILTER
        if ($request->source) {
            $query->where('source', $request->source);
        }

        // ✅ SALES FILTER (admin only)
        if ($request->assigned_to && !($user instanceof SalesTeam)) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // ✅ DATE FILTER
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // ✅ STATUS FILTER (LATEST ONLY)
        if ($request->status) {
            $query->whereHas('latestStatus', function ($q) use ($request) {
                $q->where('status_type', $request->status);
            });
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

        // ✅ Prevent SalesTeam from changing assigned_to
        if ($user instanceof SalesTeam) {
            unset($data['assigned_to']);
        }

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
            'file' => 'required|mimes:csv,txt,xlsx,xls',
            'assigned_to' => 'nullable|string'
        ]);

        // ✅ may be null
        $assignedTo = $request->input('assigned_to', null);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        if (in_array($extension, ['csv', 'txt'])) {

            // ✅ CSV
            $rows = array_map('str_getcsv', file($file->getPathname()));
        } else {

            // ✅ Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        }

        $skipped = 0;

        foreach ($rows as $index => $row) {

            if ($index === 0) continue;

            $contact = trim($row[0] ?? '');
            $phone   = trim($row[1] ?? '');
            $phone = trim($row[1] ?? '');

            if ($phone === '') {
                $skipped++;
                continue;
            }
            // ✅ REMOVE =""
            $phone = preg_replace('/^="(.*)"$/', '$1', $phone);

            // ✅ REMOVE starting '
            $phone = ltrim($phone, "'");

            // optional: remove spaces
            $phone = trim($phone);


            DB::table('leads_master')->insert([
                'lead_id' => 'L' . Str::random(10),
                'contact_person' => $contact ?? null,
                'phone_number' => $phone,
                'email' => $row[2] ?? null,
                'source' => $row[3] ?? null,
                'assigned_to' => $assignedTo,
                'enquiry_description' => $row[4] ?? null,
                // 'company_name' => $row[3] ?? null,
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => $skipped > 0
                ? "Imported successfully (Skipped: $skipped rows)"
                : "Imported successfully"
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

    public function export(Request $request)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $format = $request->input('format', 'xlsx');

        // ✅ INPUTS
        $columns     = $request->input('columns', []);
        $leadIds     = $request->input('lead_ids', []);
        $assignedTo  = $request->input('assigned_to');
        $startDate   = $request->input('start_date');
        $endDate     = $request->input('end_date');
        $limit       = $request->input('limit');

        // ✅ DEFAULT COLUMNS
        if (empty($columns)) {
            $columns = ['contact_person', 'phone', 'email', 'source', 'latest_status'];
        }

        // ✅ COLUMN LABELS
        $columnLabels = [
            'contact_person' => 'Contact Person',
            'phone' => 'Phone',
            'email' => 'Email',
            'source' => 'Source',
            'company' => 'Company',
            'latest_status' => "Latest Status"
        ];

        // ================= QUERY =================
        $query = Lead::query();

        if (!empty($leadIds)) {
            $query->whereIn('lead_id', $leadIds);
        }

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $query->latest();

        // ✅ TOTAL BEFORE LIMIT
        $totalRecords = (clone $query)->count();

        // ✅ APPLY LIMIT
        if (!empty($limit) && is_numeric($limit) && $limit > 0) {
            $query->limit((int) $limit);
        }

        $leads = $query->get();
        $exportedCount = $leads->count();

        // ================= CSV =================
        if ($format === 'csv') {

            $fileName = 'leads_' . time() . '.csv';

            $headers = [
                "Content-Type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
            ];

            $callback = function () use ($leads, $columns, $columnLabels) {

                $file = fopen('php://output', 'w');

                // HEADER
                fputcsv($file, array_map(fn($col) => $columnLabels[$col], $columns));

                foreach ($leads as $lead) {

                    $latestStatus = $lead->latestStatus ?? null;
                    $row = [];

                    foreach ($columns as $col) {

                        switch ($col) {
                            case 'contact_person':
                                $row[] = $lead->contact_person ?? '';
                                break;

                            case 'phone':
                                $row[] = '="' . ($lead->phone_number ?? '') . '"'; // ✅ clean CSV fix
                                break;

                            case 'email':
                                $row[] = $lead->email ?? '';
                                break;

                            case 'source':
                                $row[] = $lead->source ?? '';
                                break;

                            // case 'company':
                            //     $row[] = $lead->company_name ?? '';
                            //     break;

                            case 'latest_status':
                                $row[] = optional($latestStatus)->status_type ?? '';
                                break;

                            default:
                                $row[] = '';
                        }
                    }

                    fputcsv($file, $row);
                }

                fclose($file);
            };

            $response = response()->stream($callback, 200, $headers);

            $response->headers->set('X-Total-Count', $totalRecords);
            $response->headers->set('X-Exported-Count', $exportedCount);

            // ✅ IMPORTANT (this fixes your issue)
            $response->headers->set('Access-Control-Expose-Headers', 'X-Total-Count, X-Exported-Count');

            return $response;
        }

        // ================= EXCEL =================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // HEADER
        $sheet->fromArray(
            array_map(fn($col) => $columnLabels[$col], $columns),
            null,
            'A1'
        );

        $rowNumber = 2;

        foreach ($leads as $lead) {

            $colIndex = 0;

            foreach ($columns as $col) {

                $latestStatus = $lead->latestStatus ?? null;
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);

                $value = '';

                switch ($col) {
                    case 'contact_person':
                        $value = $lead->contact_person ?? '';
                        break;

                    case 'phone':
                        $value = $lead->phone_number ?? '';
                        break;

                    case 'email':
                        $value = $lead->email ?? '';
                        break;

                    case 'source':
                        $value = $lead->source ?? '';
                        break;

                    // case 'company':
                    //     $value = $lead->company_name ?? '';
                    //     break;
                    case 'latest_status':
                        $value = optional($latestStatus)->status_type ?? '';
                        break;
                }

                // ✅ FORCE STRING (fix phone issue)
                $sheet->setCellValueExplicit(
                    $columnLetter . $rowNumber,
                    (string) $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );

                $colIndex++;
            }

            $rowNumber++;
        }

        // Auto width
        foreach (range(1, count($columns)) as $i) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'leads_' . time() . '.xlsx';
        $filePath = storage_path("app/public/$fileName");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $response = response()->download($filePath)->deleteFileAfterSend(true);

        $response->headers->set('X-Total-Count', $totalRecords);
        $response->headers->set('X-Exported-Count', $exportedCount);

        // ✅ IMPORTANT (this fixes your issue)
        $response->headers->set('Access-Control-Expose-Headers', 'X-Total-Count, X-Exported-Count');

        return $response;
    }

    public function bulkDelete(Request $request)
    {
        $user = $request->user();

        // ❌ Only admin
        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $request->validate([
            'lead_ids' => 'required|array'
        ]);

        Lead::whereIn('lead_id', $request->lead_ids)->delete();

        return response()->json([
            'message' => 'Leads deleted successfully 🗑️'
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();

        $query = Lead::query();

        // ✅ Sales: only own leads
        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        // ✅ Total Leads
        $totalLeads = (clone $query)->count();

        // ✅ Today Leads
        $todayLeads = (clone $query)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        // ✅ Interested Leads (latest status)
        $interested = (clone $query)
            ->whereHas('latestStatus', function ($q) {
                $q->where('status_type', 'Interested');
            })
            ->count();

        // ✅ Closed Ordered
        $closed = (clone $query)
            ->whereHas('latestStatus', function ($q) {
                $q->where('status_type', 'Closed-Ordered');
            })
            ->count();

        return response()->json([
            'total_leads' => $totalLeads,
            'today_leads' => $todayLeads,
            'interested' => $interested,
            'closed' => $closed,
        ]);
    }
}
