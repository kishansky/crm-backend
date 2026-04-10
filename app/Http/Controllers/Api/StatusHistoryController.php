<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StatusHistory;
use App\Models\Lead;
use App\Models\SalesTeam;

class StatusHistoryController extends Controller
{
    // ✅ LIST (ROLE BASED)
    public function index(Request $request)
    {
        $user = $request->user();

        $query = StatusHistory::with(['lead', 'addedBy', 'status'])
            ->latest('updated_at');

        // ✅ Sales → only their leads' status
        if ($user instanceof SalesTeam) {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->sales_person_id);
            });
        }

        return response()->json($query->get());
    }

    // ✅ STORE (ROLE SAFE)
    public function store(Request $request)
    {
        $user = $request->user();

        $lead = Lead::findOrFail($request->lead_id);

        // ❌ Sales cannot add status to others' leads
        if ($user instanceof SalesTeam && $lead->assigned_to !== $user->sales_person_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // ✅ VALIDATION (IMPORTANT)
        $request->validate([
            'lead_id' => 'required',
            'status_id' => 'required|exists:statuses,id',
            'remark' => 'nullable|string',
            'reschedule_time' => 'nullable|date',
            'shift' => 'nullable|in:morning,noon,evening',
        ]);

        $data = $request->only([
            'lead_id',
            'status_id',
            'status_type', // keep if you want
            'remark',
            'reschedule_time',
            'shift'
        ]);

        // ✅ added_by
        $data['added_by'] = $user instanceof SalesTeam
            ? $user->sales_person_id
            : $user->id;

            
        // ✅ manual timestamp (IMPORTANT)
        $data['updated_at'] = now()->setTimezone('Asia/Kolkata');

        $status = StatusHistory::create($data);

        return response()->json([
            'message' => 'Status Added',
            'data' => $status
        ]);
    }

    // ✅ SHOW (ROLE SAFE)
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $status = StatusHistory::with('lead')->findOrFail($id);

        if ($user instanceof SalesTeam && $status->lead->assigned_to !== $user->sales_person_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json($status);
    }

    // ✅ UPDATE (ROLE SAFE)
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $status = StatusHistory::with('lead')->findOrFail($id);

        if ($user instanceof SalesTeam && $status->lead->assigned_to !== $user->sales_person_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $status->update([
            ...$request->only([
                'status_id',
                'status_type',
                'remark',
                'reschedule_time',
                'shift'
            ]),
            'updated_at' => now()
        ]);

        return response()->json([
            'message' => 'Updated',
            'data' => $status
        ]);
    }

    // ✅ DELETE (ADMIN ONLY)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        StatusHistory::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}
