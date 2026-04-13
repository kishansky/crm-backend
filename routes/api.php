<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\StatusHistoryController;
use App\Http\Controllers\Api\PerformanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StatusController;

// 🔓 Public
Route::post('/login', [AuthController::class, 'login']);
Route::post('/public/lead-form', [LeadController::class, 'storeFromForm']);

// 🔐 Protected
Route::middleware('auth:sanctum')->group(function () {

    // ✅ Admin Only Routes
    Route::middleware('isAdmin')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/leads-import-excel', [LeadController::class, 'importExcel']);
        Route::post('/leads-bulk-assign', [LeadController::class, 'bulkAssign']);
        Route::post('/leads-export', [LeadController::class, 'export']);
        Route::post('/leads-bulk-delete', [LeadController::class, 'bulkDelete']);

        Route::apiResource('sales-team', SalesTeamController::class);
        Route::apiResource('performance', PerformanceController::class);
        // 🔥 ADD THIS
        Route::apiResource('statuses', StatusController::class)->except(['index']);
    });

    // ✅ Shared (Admin + Sales)
    Route::get('/team-status-report', [LeadController::class, 'teamStatusReport']);
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('status-history', StatusHistoryController::class);
    Route::get('/dashboard-stats', [LeadController::class, 'dashboardStats']);

    Route::get('/follow-ups', [LeadController::class, 'followUps']);

    // 🔥 ADD THIS
    Route::get('/statuses', [StatusController::class, 'index']);

    // ✅ Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    });
});
