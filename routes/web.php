<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified', 'mfa.verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Organizations
    Route::resource('organizations', \App\Http\Controllers\OrganizationController::class);
    Route::get('organizations-hierarchy', [\App\Http\Controllers\OrganizationController::class, 'hierarchy'])->name('organizations.hierarchy');
    
    // Organization Units  
    Route::resource('organization-units', \App\Http\Controllers\OrganizationUnitController::class);
    Route::get('organization-units-governance', [\App\Http\Controllers\OrganizationUnitController::class, 'governance'])->name('organization-units.governance');
    Route::get('organization-units-operational', [\App\Http\Controllers\OrganizationUnitController::class, 'operational'])->name('organization-units.operational');
    
    // Organization Position Levels
    Route::resource('organization-position-levels', \App\Http\Controllers\OrganizationPositionLevelController::class);
    Route::get('api/organization-position-levels', [\App\Http\Controllers\OrganizationPositionLevelController::class, 'api'])->name('organization-position-levels.api');
    
    // Organization Positions
    Route::resource('organization-positions', \App\Http\Controllers\OrganizationPositionController::class);
    
    // Organization Memberships
    Route::resource('organization-memberships', \App\Http\Controllers\OrganizationMembershipController::class);
    Route::post('organization-memberships/{organizationMembership}/activate', [\App\Http\Controllers\OrganizationMembershipController::class, 'activate'])->name('organization-memberships.activate');
    Route::post('organization-memberships/{organizationMembership}/deactivate', [\App\Http\Controllers\OrganizationMembershipController::class, 'deactivate'])->name('organization-memberships.deactivate');
    Route::post('organization-memberships/{organizationMembership}/terminate', [\App\Http\Controllers\OrganizationMembershipController::class, 'terminate'])->name('organization-memberships.terminate');
    Route::get('board-members', [\App\Http\Controllers\OrganizationMembershipController::class, 'boardMembers'])->name('board-members.index');
    Route::get('executives', [\App\Http\Controllers\OrganizationMembershipController::class, 'executives'])->name('executives.index');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
