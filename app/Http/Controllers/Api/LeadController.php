<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Carbon\Carbon;
use App\Models\User;
use App\Models\SalesTeam;
use App\Models\StatusHistory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\Cache;

class LeadController extends Controller
{
    // ✅ LIST WITH ROLE FILTER
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $user = $request->user();

        // ✅ DEFINE QUERY WITH RELATIONSHIPS
        $query = Lead::with([
            'salesPerson',
            'latestStatus.addedBy:sales_person_id,name',
            'needs.place' // 🔥 Include needs with places
        ])->latest();

        // ✅ ROLE FILTER (Sales can view only their leads)
        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        // ✅ SEARCH FILTER
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {

                // 🔍 Normal text search
                $q->where('company_name', 'like', "%$search%")
                    ->orWhere('contact_person', 'like', "%$search%")
                    ->orWhere('phone_number', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%");

                // 📅 Date search (created_at)
                try {
                    // Convert input to Y-m-d
                    $date = Carbon::parse($search)->toDateString();

                    $q->orWhereDate('created_at', $date);
                } catch (\Exception $e) {
                    // Ignore if not a valid date
                }
            });
        }

        // ✅ SOURCE FILTER
        if ($request->source) {
            $query->where('source', $request->source);
        }

        // ✅ SALES FILTER (Admin Only)
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

        // ✅ PLACE FILTER THROUGH NEEDS
        if ($request->place_id) {
            $query->whereHas('needs', function ($q) use ($request) {
                $q->where('place_id', $request->place_id);
            });
        }

        // ✅ PROPERTY TYPE FILTER (Optional)
        if ($request->property_type) {
            $query->whereHas('needs', function ($q) use ($request) {
                $q->where('property_type', $request->property_type);
            });
        }

        // ✅ PAGINATE RESULTS
        $leads = $query->paginate($perPage);

        return response()->json($leads);
    }
    // ✅ STORE
    public function store(Request $request)
    {
        // ✅ Validate request
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        // 🔹 Clean numbers ONLY for checking
        $numbers = collect(explode(',', $request->phone_number))
            ->map(fn($n) => trim($n))
            ->filter(fn($n) => $n !== '')
            ->values()
            ->toArray();

        // 🔍 Check if ANY number already exists (exact match)
        foreach ($numbers as $number) {
            if (Lead::where('phone_number', 'LIKE', "%$number%")->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => "Phone number $number already registered."
                ], 409);
            }
        }

        $data = $request->all();

        // ❗ IMPORTANT: save ORIGINAL input (with spaces if any)
        $data['phone_number'] = $request->phone_number;

        // ✅ Fix datetime format
        if ($request->timestamp) {
            $data['timestamp'] = Carbon::parse($request->timestamp)
                ->format('Y-m-d H:i:s');
        }

        // ✅ Create lead
        $lead = Lead::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Lead created successfully.',
            'data' => $lead
        ], 201);
    }

    public function storeFromForm(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:35',
            'email' => 'nullable|email',
            'enquiry_description' => 'nullable|string',
            // ✅ allow source but restrict values if needed
            'source' => 'nullable|string|max:50',
            'is_form' => 'nullable|boolean',
        ]);

        // 🔍 Check if phone number already exists
        $existingLead = Lead::where('phone_number', $validated['phone_number'])->first();

        if ($existingLead) {
            return response()->json([
                'status' => false,
                'message' => 'Phone number already registered.',
                'data' => $existingLead
            ], 409); // HTTP 409 Conflict
        }

        // ✅ System-generated fields
        $validated['lead_id'] = 'L' . Str::upper(Str::random(10));
        $validated['timestamp'] = Carbon::now()->format('Y-m-d H:i:s');

        // 🔒 Enforce form flag (do not trust frontend)
        $validated['is_form'] = true;

        // ✅ Create the lead
        $lead = Lead::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Lead submitted successfully 🚀',
            'data' => $lead
        ], 201);
    }

    // ✅ SHOW (ROLE SAFE)
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $lead = Lead::with([
            'salesPerson',
            'statusHistory.addedBy:sales_person_id,name',
            'needs.place' // ✅ Load needs with their respective places
        ])->findOrFail($id);

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
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $data = $request->all();

        // ✅ Prevent SalesTeam from changing assigned_to and phone_number
        if ($user instanceof SalesTeam) {
            unset($data['assigned_to']);
            unset($data['phone_number']);
        }

        // 🔍 Check if phone number already exists (for Admin updates)
        if (
            isset($data['phone_number']) &&
            $data['phone_number'] !== $lead->phone_number
        ) {
            $exists = Lead::where('phone_number', $data['phone_number'])
                ->where('lead_id', '!=', $lead->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Phone number already registered.'
                ], 409); // HTTP 409 Conflict
            }
        }

        // ✅ Fix datetime format
        if ($request->timestamp) {
            $data['timestamp'] = Carbon::parse($request->timestamp)
                ->format('Y-m-d H:i:s');
        }

        // ✅ Update lead
        $lead->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Lead updated successfully.',
            'data' => $lead
        ], 200);
    }

    // ✅ DELETE (ADMIN ONLY recommended)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // ❌ Sales cannot delete
        if ($user instanceof SalesTeam) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        // 🔍 Find the lead (including soft-deleted ones)
        $lead = Lead::withTrashed()->findOrFail($id);

        // 🗑️ Permanently delete the record
        $lead->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Lead deleted'
        ], 200);
    }


    public function importExcel(Request $request)
    {
        $user = $request->user();

        // ❌ Only Admin allowed
        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls',
            'assigned_to' => 'nullable|string'
        ]);

        // ✅ Assigned user (may be null)
        $assignedTo = $request->input('assigned_to', null);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // 📂 Read file
        if (in_array(strtolower($extension), ['csv', 'txt'])) {
            $rows = array_map('str_getcsv', file($file->getPathname()));
        } else {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        }

        $insertData = [];
        $skipped = 0;
        $duplicates = 0;

        // 🔍 Fetch existing phone numbers once
        $existingPhones = DB::table('leads_master')
            ->pluck('phone_number')
            ->map(fn($phone) => trim($phone))
            ->toArray();

        $existingPhones = array_flip($existingPhones);

        foreach ($rows as $index => $row) {

            // Skip header
            if ($index === 0) continue;

            $contact = trim($row[0] ?? '');
            $phone = trim($row[1] ?? '');

            // ❌ Skip empty
            if ($phone === '') {
                $skipped++;
                continue;
            }

            // ✅ FIX: Handle scientific notation from Excel
            if (is_numeric($phone)) {
                $phone = number_format($phone, 0, '', '');
            }

            // ✅ Remove Excel weird formatting
            $phone = preg_replace('/^="(.*)"$/', '$1', $phone);
            $phone = ltrim($phone, "'");

            // ✅ Keep only digits (remove +, spaces, etc.)
            $phone = preg_replace('/\D/', '', $phone);

            // ❌ Skip invalid (too short)
            if (strlen($phone) < 8) {
                $skipped++;
                continue;
            }

            // 🔁 Skip duplicates (DB)
            if (isset($existingPhones[$phone])) {
                $duplicates++;
                $skipped++;
                continue;
            }

            // Prevent duplicates inside file
            $existingPhones[$phone] = true;

            $insertData[] = [
                'lead_id' => 'L' . Str::upper(Str::random(10)),
                'contact_person' => $contact ?: null,
                'phone_number' => $phone,
                'email' => $row[2] ?? null,
                'source' => $row[3] ?? null,
                'company_name' => $row[4] ?? null,
                'assigned_to' => $assignedTo,
                'enquiry_description' => $row[5] ?? null,
                'timestamp' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // 🚀 Bulk insert
        if (!empty($insertData)) {
            DB::table('leads_master')->insert($insertData);
        }

        return response()->json([
            'message' => $skipped > 0
                ? "Imported successfully ($skipped rows skipped, $duplicates duplicates)"
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
        $columns = $request->input('columns', []);
        $leadIds = $request->input('lead_ids', []);
        $assignedTo = $request->input('assigned_to');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $limit = $request->input('limit');

        // ✅ DEFAULT COLUMNS
        if (empty($columns)) {
            $columns = ['contact_person', 'phone', 'email', 'source', 'company_name', 'latest_status'];
        }

        // ✅ COLUMN LABELS
        $columnLabels = [
            'contact_person' => 'Contact Person',
            'phone' => 'Phone',
            'email' => 'Email',
            'source' => 'Source',
            'company_name' => 'Address',
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

        // ✅ STATUS FILTER
        if ($request->status) {

            $query->where(function ($q) use ($request) {

                // latest status
                $q->whereHas('latestStatus', function ($sub) use ($request) {
                    $sub->where('status_type', $request->status);
                })

                    // any old status history
                    ->orWhereHas('statusHistory', function ($sub) use ($request) {
                        $sub->where('status_type', $request->status);
                    });
            });
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

                            case 'company_name':
                                $row[] = $lead->company_name ?? '';
                                break;

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

                    case 'company_name':
                        $value = $lead->company_name ?? '';
                        break;
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

        // ❌ Only admin allowed
        if ($user instanceof SalesTeam) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        // ✅ Validate request
        $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'required|string|exists:leads_master,lead_id',
        ]);

        // 🔍 Fetch leads including soft-deleted ones
        $leads = Lead::withTrashed()
            ->whereIn('lead_id', $request->lead_ids)
            ->get();

        // 🗑️ Perform hard delete
        $deletedCount = $leads->count();
        foreach ($leads as $lead) {
            $lead->forceDelete();
        }

        return response()->json([
            'status' => true,
            'message' => "$deletedCount Leads deleted",
            'deleted_count' => $deletedCount
        ], 200);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();

        // 📅 Date references
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        /*
    |--------------------------------------------------------------------------
    | 🔹 LEADS QUERY
    |--------------------------------------------------------------------------
    */

        $leadQuery = Lead::query();

        // Restrict sales users to their own leads
        if ($user instanceof SalesTeam) {
            $leadQuery->where('assigned_to', $user->sales_person_id);
        }

        /*
    |--------------------------------------------------------------------------
    | 🔹 LEAD STATISTICS
    |--------------------------------------------------------------------------
    */

        $totalLeads = (clone $leadQuery)->count();

        $todayLeads = (clone $leadQuery)
            ->whereDate('created_at', $today)
            ->count();

        $weeklyLeads = (clone $leadQuery)
            ->whereDate('created_at', '>=', $startOfWeek)
            ->count();

        $monthlyLeads = (clone $leadQuery)
            ->whereDate('created_at', '>=', $startOfMonth)
            ->count();

        /*
    |--------------------------------------------------------------------------
    | 🔹 TODAY STATUS QUERY
    |--------------------------------------------------------------------------
    */

        $statusQuery = DB::table('status_history')
            ->join('statuses', 'status_history.status_id', '=', 'statuses.id')
            ->join('leads_master', 'status_history.lead_id', '=', 'leads_master.lead_id')
            ->whereNull('status_history.deleted_at')
            ->whereDate('status_history.updated_at', $today);

        // Restrict sales users to their own activities
        if ($user instanceof SalesTeam) {

            $statusQuery->where(function ($q) use ($user) {

                $q->where('leads_master.assigned_to', $user->sales_person_id)
                    ->orWhere('status_history.added_by', $user->sales_person_id);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | 🔹 TODAY STATUS COUNTS
    |--------------------------------------------------------------------------
    */

        $todayStatusCounts = $statusQuery
            ->select(
                'statuses.id',
                'statuses.name',
                'statuses.color',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('statuses.id', 'statuses.name', 'statuses.color')
            ->get()
            ->keyBy('id');

        /*
    |--------------------------------------------------------------------------
    | 🔥 ALL TIME STATUS COUNTS (CACHED)
    |--------------------------------------------------------------------------
    */

        $allTimeStatusCounts = Cache::remember(

            $user instanceof SalesTeam
                ? 'all_time_status_counts_' . $user->sales_person_id
                : 'all_time_status_counts_admin',

            now()->addHours(6),

            function () use ($user) {

                $query = DB::table('leads_master as lm')

                    ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                    SELECT sh2.history_id
                    FROM status_history sh2
                    WHERE sh2.lead_id = lm.lead_id
                    AND sh2.deleted_at IS NULL
                    ORDER BY sh2.history_id DESC
                    LIMIT 1
                )'))

                    ->join('statuses', 'sh.status_id', '=', 'statuses.id');

                if ($user instanceof SalesTeam) {

                    $query->where(
                        'lm.assigned_to',
                        $user->sales_person_id
                    );
                }

                return $query
                    ->select(
                        'statuses.id',
                        DB::raw('COUNT(*) as total_count')
                    )
                    ->groupBy('statuses.id')
                    ->pluck('total_count', 'statuses.id');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | 🔥 ALL TIME PLACE COUNTS (CACHED)
    |--------------------------------------------------------------------------
    */

        $allTimePlaceCounts = Cache::remember(

            $user instanceof SalesTeam
                ? 'all_time_place_counts_' . $user->sales_person_id
                : 'all_time_place_counts_admin',

            now()->addHours(6),

            function () use ($user) {

                $query = DB::table('needs')
                    ->join('places', 'needs.place_id', '=', 'places.id')
                    ->join('leads_master', 'needs.lead_id', '=', 'leads_master.lead_id');

                if ($user instanceof SalesTeam) {

                    $query->where(
                        'leads_master.assigned_to',
                        $user->sales_person_id
                    );
                }

                return $query
                    ->select(
                        'places.id',
                        'places.name',
                        DB::raw('COUNT(*) as total_count')
                    )
                    ->groupBy('places.id', 'places.name')
                    ->get();
            }
        );

        /*
|--------------------------------------------------------------------------
| 🔥 ALL TIME LATEST STATUS COUNTS (CACHED)
|--------------------------------------------------------------------------
*/

        $allTimeLatestStatusCounts = Cache::remember(

            $user instanceof SalesTeam
                ? 'all_time_latest_status_counts_' . $user->sales_person_id
                : 'all_time_latest_status_counts_admin',

            now()->addHours(6),

            function () use ($user) {

                $query = DB::table('leads_master as lm')

                    ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                SELECT sh2.history_id
                FROM status_history sh2
                WHERE sh2.lead_id = lm.lead_id
                AND sh2.deleted_at IS NULL
                ORDER BY sh2.history_id DESC
                LIMIT 1
            )'))

                    ->join('statuses', 'sh.status_id', '=', 'statuses.id');

                if ($user instanceof SalesTeam) {

                    $query->where(
                        'lm.assigned_to',
                        $user->sales_person_id
                    );
                }

                return $query
                    ->select(
                        'statuses.id',
                        'statuses.name',
                        'statuses.color',
                        DB::raw('COUNT(*) as total_count')
                    )
                    ->groupBy(
                        'statuses.id',
                        'statuses.name',
                        'statuses.color'
                    )
                    ->orderBy('statuses.orders')
                    ->get();
            }
        );

        /*
    |--------------------------------------------------------------------------
    | 🔹 FETCH ALL ACTIVE STATUSES
    |--------------------------------------------------------------------------
    */

        $allStatuses = DB::table('statuses')
            ->where('is_active', 1)
            ->orderBy('orders')
            ->select('id', 'name', 'color')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | 🔹 MERGE TODAY + ALL TIME COUNTS
    |--------------------------------------------------------------------------
    */

        $statusCounts = $allStatuses->map(function ($status) use (
            $todayStatusCounts,
            $allTimeStatusCounts
        ) {

            return [

                'status_id' => $status->id,

                'status_name' => $status->name,

                'status_color' => $status->color,

                // ✅ TODAY COUNT
                'count' => isset($todayStatusCounts[$status->id])
                    ? (int) $todayStatusCounts[$status->id]->count
                    : 0,
            ];
        })->values();

        /*
    |--------------------------------------------------------------------------
    | 🔹 RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'total_leads' => $totalLeads,

            'today_leads' => $todayLeads,

            'weekly_leads' => $weeklyLeads,

            'monthly_leads' => $monthlyLeads,

            // today status counts
            'status_counts' => $statusCounts,

            // all time latest status counts
            'all_time_latest_status_counts' => $allTimeLatestStatusCounts,

            // all time place counts
            'place_counts' => $allTimePlaceCounts,
        ]);
    }

    public function followUps(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $user = $request->user();

        $query = Lead::with([
            'salesPerson',
            'latestStatus',
            'needs.place'
        ]);

        /*
    |--------------------------------------------------------------------------
    | ONLY FOLLOW-UPS (LATEST STATUS MUST HAVE RESCHEDULE TIME)
    |--------------------------------------------------------------------------
    */

        $query->whereIn('lead_id', function ($sub) {

            $sub->select('lm.lead_id')
                ->from('leads_master as lm')

                ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                SELECT sh2.history_id
                FROM status_history sh2
                WHERE sh2.lead_id = lm.lead_id
                AND sh2.deleted_at IS NULL
                ORDER BY sh2.history_id DESC
                LIMIT 1
            )'))

                ->whereNotNull('sh.reschedule_time');
        });

        /*
    |--------------------------------------------------------------------------
    | ROLE FILTER
    |--------------------------------------------------------------------------
    */

        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

        if ($request->filled('search') && strlen($request->search) >= 2) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        /*
    |--------------------------------------------------------------------------
    | SOURCE
    |--------------------------------------------------------------------------
    */

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        /*
    |--------------------------------------------------------------------------
    | SALES PERSON
    |--------------------------------------------------------------------------
    */

        if (
            $request->filled('assigned_to') &&
            !($user instanceof SalesTeam)
        ) {
            $query->where('assigned_to', $request->assigned_to);
        }

        /*
    |--------------------------------------------------------------------------
    | PLACE
    |--------------------------------------------------------------------------
    */

        if ($request->filled('place_id')) {

            $query->whereHas('needs', function ($q) use ($request) {

                $q->where('place_id', $request->place_id);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | STATUS TYPE
    |--------------------------------------------------------------------------
    */

        if ($request->filled('status')) {

            $query->whereIn('lead_id', function ($sub) use ($request) {

                $sub->select('lm.lead_id')
                    ->from('leads_master as lm')

                    ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                    SELECT sh2.history_id
                    FROM status_history sh2
                    WHERE sh2.lead_id = lm.lead_id
                    AND sh2.deleted_at IS NULL
                    ORDER BY sh2.history_id DESC
                    LIMIT 1
                )'))

                    ->where('sh.status_type', $request->status);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | CALL STATUS
    |--------------------------------------------------------------------------
    */

        if (
            isset($request->call_status) &&
            trim($request->call_status) !== ''
        ) {

            $callStatus = (int) $request->call_status;

            $query->whereIn('lead_id', function ($sub) use ($callStatus) {

                $sub->select('lm.lead_id')
                    ->from('leads_master as lm')

                    ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                    SELECT sh2.history_id
                    FROM status_history sh2
                    WHERE sh2.lead_id = lm.lead_id
                    AND sh2.deleted_at IS NULL
                    ORDER BY sh2.history_id DESC
                    LIMIT 1
                )'))

                    ->where('sh.status_id', $callStatus);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | DATE FILTERS
    |--------------------------------------------------------------------------
    */

        if ($request->filter) {

            switch ($request->filter) {

                case 'today':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->whereDate('sh.reschedule_time', now());
                    });

                    break;

                case 'yesterday':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->whereDate('sh.reschedule_time', now()->subDay());
                    });

                    break;

                case 'tomorrow':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->whereDate('sh.reschedule_time', now()->addDay());
                    });

                    break;

                case 'missed':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->where('sh.reschedule_time', '<', now());
                    });

                    break;

                case 'week':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->whereBetween('sh.reschedule_time', [
                                now(),
                                now()->endOfWeek()
                            ]);
                    });

                    break;

                case 'upcoming':

                    $query->whereIn('lead_id', function ($sub) {

                        $sub->select('lm.lead_id')
                            ->from('leads_master as lm')

                            ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                            SELECT sh2.history_id
                            FROM status_history sh2
                            WHERE sh2.lead_id = lm.lead_id
                            AND sh2.deleted_at IS NULL
                            ORDER BY sh2.history_id DESC
                            LIMIT 1
                        )'))

                            ->where('sh.reschedule_time', '>', now());
                    });

                    break;

                case 'all':
                    break;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | CUSTOM DATE
    |--------------------------------------------------------------------------
    */

        if ($request->filled('date')) {

            $query->whereIn('lead_id', function ($sub) use ($request) {

                $sub->select('lm.lead_id')
                    ->from('leads_master as lm')

                    ->join('status_history as sh', 'sh.history_id', '=', DB::raw('(
                    SELECT sh2.history_id
                    FROM status_history sh2
                    WHERE sh2.lead_id = lm.lead_id
                    AND sh2.deleted_at IS NULL
                    ORDER BY sh2.history_id DESC
                    LIMIT 1
                )'))

                    ->whereDate('sh.reschedule_time', $request->date);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | ORDERING
    |--------------------------------------------------------------------------
    */

        $query->orderByRaw("
        (
            SELECT reschedule_time
            FROM status_history
            WHERE status_history.lead_id = leads_master.lead_id
            AND status_history.deleted_at IS NULL
            ORDER BY history_id DESC
            LIMIT 1
        ) ASC
    ");

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function newLeads(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $user = $request->user();

        $query = Lead::with([
            'salesPerson',
            'latestStatus',
            'needs.place'
        ]);

        // ✅ ONLY NEW LEADS (NO FOLLOW-UP)
        $query->whereDoesntHave('latestStatus');

        // ✅ ROLE FILTER
        if ($user instanceof SalesTeam) {
            $query->where('assigned_to', $user->sales_person_id);
        }

        /*
    |--------------------------------------------------------------------------
    | OPTIONAL FILTERS
    |--------------------------------------------------------------------------
    */

        // 🔍 SEARCH
        if ($request->filled('search') && strlen($request->search) >= 2) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 🌐 SOURCE
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // 👤 SALES (Admin only)
        if ($request->filled('assigned_to') && !($user instanceof SalesTeam)) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // 📌 PLACE
        if ($request->filled('place_id')) {
            $query->whereHas('needs', function ($q) use ($request) {
                $q->where('place_id', $request->place_id);
            });
        }

        /*
    |--------------------------------------------------------------------------
    | ORDERING
    |--------------------------------------------------------------------------
    */

        // 🔹 Latest leads first
        $query->latest('created_at');

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function teamStatusReport(Request $request)
    {
        $user = $request->user();

        // 📅 Determine date filter
        $filter = $request->input('filter', 'today');

        $fromDate = null;
        $toDate = null;

        switch ($filter) {
            case 'yesterday':
                $fromDate = Carbon::yesterday()->startOfDay();
                $toDate = Carbon::yesterday()->endOfDay();
                break;

            case 'date':
                $fromDate = Carbon::parse($request->input('date'))->startOfDay();
                $toDate = Carbon::parse($request->input('date'))->endOfDay();
                break;

            case 'range': // 🔥 NEW RANGE SUPPORT
                $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
                $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
                break;

            case 'today':
            default:
                $fromDate = Carbon::today()->startOfDay();
                $toDate = Carbon::today()->endOfDay();
                break;
        }

        // 📊 STATUS DATA
        $query = DB::table('status_history')
            ->leftJoin('sales_team', 'status_history.added_by', '=', 'sales_team.sales_person_id')
            ->leftJoin('statuses', 'status_history.status_id', '=', 'statuses.id')
            ->select(
                'status_history.added_by',
                DB::raw('COALESCE(sales_team.name, "Admin") as team_member'),
                'statuses.name as status_name',
                'statuses.color as status_color',
                DB::raw('COUNT(*) as total')
            )
            ->whereBetween('status_history.updated_at', [$fromDate, $toDate])
            ->whereNull('status_history.deleted_at');

        // 🔒 Sales Team restriction
        if ($user instanceof SalesTeam) {
            $query->where('status_history.added_by', $user->sales_person_id);
        }

        $rows = $query->groupBy(
            'status_history.added_by',
            'team_member',
            'statuses.name',
            'statuses.color'
        )
            ->orderBy('team_member')
            ->get();

        // 📊 ASSIGNED LEADS COUNT
        $assignedLeads = DB::table('leads_master')
            ->select(
                'assigned_to',
                DB::raw('COUNT(*) as total_assigned')
            )
            ->whereBetween('updated_at', [$fromDate, $toDate])
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->pluck('total_assigned', 'assigned_to');

        // 📌 Fetch teams
        if ($user instanceof SalesTeam) {
            $teams = [
                [
                    'sales_person_id' => $user->sales_person_id,
                    'name' => $user->name
                ]
            ];
        } else {
            $teams = SalesTeam::select('sales_person_id', 'name')
                ->whereNull('deleted_at')
                ->get()
                ->toArray();

            // Include Admin
            array_unshift($teams, [
                'sales_person_id' => '1',
                'name' => 'Admin'
            ]);
        }

        // 🔄 Initialize structure
        $grouped = [];
        foreach ($teams as $team) {
            $teamId = $team['sales_person_id'];
            $teamName = $team['name'];

            $grouped[$teamName] = [
                'team_member' => $teamName,
                'total' => 0,
                'assigned_leads' => isset($assignedLeads[$teamId])
                    ? (int) $assignedLeads[$teamId]
                    : 0,
                'statuses' => []
            ];
        }

        // 🔄 Populate status data
        foreach ($rows as $row) {
            $team = $row->team_member;

            if (!isset($grouped[$team])) {
                $grouped[$team] = [
                    'team_member' => $team,
                    'total' => 0,
                    'assigned_leads' => 0,
                    'statuses' => []
                ];
            }

            $grouped[$team]['statuses'][] = [
                'status_name' => $row->status_name,
                'status_color' => $row->status_color,
                'count' => (int) $row->total
            ];

            $grouped[$team]['total'] += (int) $row->total;
        }

        return response()->json([
            'status' => true,
            'filter' => $filter,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'data' => array_values($grouped)
        ]);
    }
}
