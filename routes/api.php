<?php 

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\StatusHistoryController;
use App\Http\Controllers\Api\PerformanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 🔓 Public
Route::post('/login', [AuthController::class, 'login']);

// 🔐 Protected
Route::middleware('auth:sanctum')->group(function(){

    // ✅ Admin Only Routes
    Route::middleware('isAdmin')->group(function(){
        Route::post('/leads-import-excel', [LeadController::class, 'importExcel']);
        Route::post('/leads-bulk-assign', [LeadController::class, 'bulkAssign']);
        Route::post('/leads-export-excel', [LeadController::class, 'exportExcel']);
        Route::post('/leads-bulk-delete', [LeadController::class, 'bulkDelete']);
        
        Route::apiResource('sales-team', SalesTeamController::class);
        Route::apiResource('performance', PerformanceController::class);
    });

    // ✅ Shared (Admin + Sales)
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('status-history', StatusHistoryController::class);
    Route::get('/dashboard-stats', [LeadController::class, 'dashboardStats']);

    // ✅ Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    });

});