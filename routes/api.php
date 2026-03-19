<?php 
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\StatusHistoryController;
use App\Http\Controllers\Api\PerformanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login',[AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function(){

    Route::apiResource('sales-team', SalesTeamController::class);
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('status-history', StatusHistoryController::class);
    Route::apiResource('performance', PerformanceController::class);

});

Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Logged out'
    ]);
})->middleware('auth:sanctum');