<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Place;
use App\Models\SalesTeam;

class PlaceController extends Controller
{
    // ✅ List all places
    public function index()
    {
        return response()->json(
            Place::where('is_active', true)->latest()->get()
        );
    }

    // ✅ Store new place (Admin only)
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:places,name',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $place = Place::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Place created successfully',
            'data' => $place
        ], 201);
    }

    // ✅ Show single place
    public function show($id)
    {
        $place = Place::findOrFail($id);
        return response()->json($place);
    }

    // ✅ Update place (Admin only)
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $place = Place::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:places,name,' . $id,
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $place->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Place updated successfully',
            'data' => $place
        ]);
    }

    // ✅ Delete place (Admin only)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $place = Place::findOrFail($id);
        $place->delete();

        return response()->json([
            'status' => true,
            'message' => 'Place deleted successfully'
        ]);
    }
}