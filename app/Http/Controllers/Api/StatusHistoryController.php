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

        $query = StatusHistory::with('lead')->latest();

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

        $data = StatusHistory::create($request->all());

        return response()->json([
            'message' => 'Status Added',
            'data' => $data
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

        $status->update($request->all());

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