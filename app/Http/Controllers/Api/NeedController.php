<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Need;
use App\Models\Lead;
use App\Models\SalesTeam;

class NeedController extends Controller
{
    // ✅ List all needs
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Need::with(['place', 'lead'])->latest();

        // Sales can view only their assigned leads
        if ($user instanceof SalesTeam) {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->sales_person_id);
            });
        }

        return response()->json($query->paginate(10));
    }

    // ✅ Store need
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads_master,lead_id',
            'place_id' => 'required|exists:places,id',
            'property_type' => 'nullable|string|max:100',
            'min_area' => 'nullable|numeric',
            'max_area' => 'nullable|numeric',
            'area_unit' => 'nullable|string|max:50',
            'min_budget' => 'nullable|numeric',
            'max_budget' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $need = Need::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Need created successfully',
            'data' => $need->load('place')
        ], 201);
    }

    // ✅ Show need
    public function show($id)
    {
        $need = Need::with(['place', 'lead'])->findOrFail($id);
        return response()->json($need);
    }

    // ✅ Update need
    public function update(Request $request, $id)
    {
        $need = Need::findOrFail($id);

        $validated = $request->validate([
            'place_id' => 'required|exists:places,id',
            'property_type' => 'nullable|string|max:100',
            'min_area' => 'nullable|numeric',
            'max_area' => 'nullable|numeric',
            'area_unit' => 'nullable|string|max:50',
            'min_budget' => 'nullable|numeric',
            'max_budget' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $need->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Need updated successfully',
            'data' => $need->load('place')
        ]);
    }

    // ✅ Delete need
    public function destroy($id)
    {
        $need = Need::findOrFail($id);
        $need->delete();

        return response()->json([
            'status' => true,
            'message' => 'Need deleted successfully'
        ]);
    }
}