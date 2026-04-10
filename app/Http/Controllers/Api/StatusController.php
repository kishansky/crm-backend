<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use App\Models\SalesTeam;

class StatusController extends Controller
{
    // ✅ Anyone can view
    public function index(Request $request)
    {
        return Status::where('is_active', true)->get();
    }

    // ❌ Admin only
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $status = Status::create($request->only('name', 'color'));

        return response()->json($status);
    }

    // ❌ Admin only
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        $status = Status::findOrFail($id);
        $status->update($request->only('name', 'color'));

        return response()->json($status);
    }

    // ❌ Admin only
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if ($user instanceof SalesTeam) {
            return response()->json([
                'message' => 'Unauthorized (Admin only)'
            ], 403);
        }

        Status::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}