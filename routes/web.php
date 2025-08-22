<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('organizations', \App\Http\Controllers\OrganizationController::class);
    Route::resource('departments', \App\Http\Controllers\DepartmentController::class);
    Route::resource('job-levels', \App\Http\Controllers\JobLevelController::class);
    Route::resource('job-positions', \App\Http\Controllers\JobPositionController::class);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
