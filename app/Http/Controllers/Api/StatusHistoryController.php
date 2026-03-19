<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StatusHistory;

class StatusHistoryController extends Controller
{
    public function index()
    {
        return response()->json(StatusHistory::latest()->get());
    }

    public function store(Request $request)
    {
        $data = StatusHistory::create($request->all());

        return response()->json([
            'message' => 'Status Added',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        return response()->json(StatusHistory::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $data = StatusHistory::findOrFail($id);
        $data->update($request->all());

        return response()->json([
            'message' => 'Updated',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        StatusHistory::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}