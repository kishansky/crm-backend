<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\StatusHistoryController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\NeedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 🔓 Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/public/lead-form', [LeadController::class, 'storeFromForm']);

// 🔐 Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 🔐 Admin Only Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('isAdmin')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/leads-import-excel', [LeadController::class, 'importExcel']);
        Route::post('/leads-bulk-assign', [LeadController::class, 'bulkAssign']);
        Route::post('/leads-export', [LeadController::class, 'export']);
        Route::post('/leads-bulk-delete', [LeadController::class, 'bulkDelete']);

        // Sales Team Management
        Route::apiResource('sales-team', SalesTeamController::class);

        // Performance Management
        Route::apiResource('performance', PerformanceController::class);

        // Status Management (Admin Only)
        Route::apiResource('statuses', StatusController::class)->except(['index']);

        // 📍 Places Management (Admin Only)
        Route::apiResource('places', PlaceController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | 👥 Shared Routes (Admin + Sales)
    |--------------------------------------------------------------------------
    */

    // Leads & Status
    Route::apiResource('leads', LeadController::class);
    Route::apiResource('status-history', StatusHistoryController::class);

    // Needs Management (Admin + Sales)
    Route::apiResource('needs', NeedController::class);

    // Reports & Dashboard
    Route::get('/team-status-report', [LeadController::class, 'teamStatusReport']);
    Route::get('/dashboard-stats', [LeadController::class, 'dashboardStats']);
    Route::get('/follow-ups', [LeadController::class, 'followUps']);

    // Status List for Dropdowns
    Route::get('/statuses', [StatusController::class, 'index']);

    // Active Places for Dropdowns
    Route::get('/places-list', [PlaceController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | 🔓 Logout
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    });
});