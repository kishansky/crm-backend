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
        // ✅ Validation
        $request->validate([
            'sales_person_id' => 'required|unique:sales_team,sales_person_id',
            'name' => 'required|string',
            'email' => 'required|email|unique:sales_team,email',
            'password' => 'required|min:6',
            'is_active' => 'nullable|boolean'
        ]);

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

        // ✅ Validation (ignore current record)
        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:sales_team,email,' . $id . ',sales_person_id',
            'password' => 'nullable|min:6',
            'is_active' => 'nullable|boolean'
        ]);

        $input = $request->all();

        // ✅ Fix boolean
        if (isset($input['is_active'])) {
            $input['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        // ❗ Remove password if empty (avoid hashing null)
        if (empty($input['password'])) {
            unset($input['password']);
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