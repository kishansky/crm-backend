<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalesTeam;

class SalesTeamController extends Controller
{
    public function index()
    {
        return response()->json(SalesTeam::latest()->get());
    }

    public function store(Request $request)
    {
        $data = SalesTeam::create($request->all());

        return response()->json([
            'message' => 'Created',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        return response()->json(SalesTeam::findOrFail($id));
    }

   public function update(Request $request, $id)
{
    $data = SalesTeam::findOrFail($id);

    $input = $request->all();

    // ✅ FIX BOOLEAN
    if (isset($input['is_active'])) {
        $input['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);
    }

    $data->update($input);

    return response()->json([
        'message' => 'Updated',
        'data' => $data
    ]);
}

    public function destroy($id)
    {
        SalesTeam::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}