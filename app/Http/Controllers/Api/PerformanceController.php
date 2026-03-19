<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerformanceMetric;

class PerformanceController extends Controller
{
   public function index()
{
    return PerformanceMetric::with('salesPerson')->latest()->get();
}

public function store(Request $request)
{
    $data = PerformanceMetric::create($request->all());

    return response()->json($data);
}

public function show($id)
{
    return PerformanceMetric::findOrFail($id);
}

public function update(Request $request, $id)
{
    $data = PerformanceMetric::findOrFail($id);
    $data->update($request->all());

    return response()->json($data);
}

public function destroy($id)
{
    PerformanceMetric::findOrFail($id)->delete();

    return response()->json(['message' => 'Deleted']);
}
}