<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Carbon\Carbon;

class LeadController extends Controller
{
    // ✅ LIST WITH PAGINATION
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $leads = Lead::with(['salesPerson','statusHistory'])
            ->latest()
            ->paginate($perPage);

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

    // ✅ SHOW
    public function show($id)
    {
        return response()->json(
            Lead::with(['salesPerson','statusHistory'])
                ->findOrFail($id)
        );
    }

    // ✅ UPDATE (FIXED)
    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->all();

        // ✅ FIX datetime here also
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

    // ✅ DELETE (SOFT DELETE)
    public function destroy($id)
    {
        Lead::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}