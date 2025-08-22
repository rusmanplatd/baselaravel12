<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// Public API routes (no authentication required for demo)
Route::prefix('v1')->group(function () {
    Route::apiResource('organizations', \App\Http\Controllers\Api\OrganizationController::class);
    Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class);
    Route::apiResource('job-levels', \App\Http\Controllers\Api\JobLevelController::class);
    Route::apiResource('job-positions', \App\Http\Controllers\Api\JobPositionController::class);
});

// Authenticated API routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    // You can add authenticated routes here if needed
});
