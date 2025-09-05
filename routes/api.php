<?php

use App\Http\Controllers\Api\MfaApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\UserSecurityController;
use App\Http\Controllers\Api\WebAuthnApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// Activity logs API (moved to authenticated API section)
// Note: Moved to v1 section below for proper API token authentication

// Public API routes (no authentication required for demo)
Route::prefix('v1')->group(function () {
    // Geo API endpoints (public for reference data)
    Route::prefix('geo')->name('api.geo.')->group(function () {
        // List routes must come before resource routes to avoid conflicts
        Route::get('countries/list', [\App\Http\Controllers\Api\Geo\CountryController::class, 'list'])->name('countries.list');
        Route::get('provinces/list', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'list'])->name('provinces.list');
        Route::get('cities/list', [\App\Http\Controllers\Api\Geo\CityController::class, 'list'])->name('cities.list');
        Route::get('districts/list', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'list'])->name('districts.list');
        Route::get('villages/list', [\App\Http\Controllers\Api\Geo\VillageController::class, 'list'])->name('villages.list');

        // Nested routes must also come before resource routes
        Route::get('countries/{country}/provinces', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'byCountry'])->name('provinces.by-country');
        Route::get('provinces/{province}/cities', [\App\Http\Controllers\Api\Geo\CityController::class, 'byProvince'])->name('cities.by-province');
        Route::get('cities/{city}/districts', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'byCity'])->name('districts.by-city');
        Route::get('districts/{district}/villages', [\App\Http\Controllers\Api\Geo\VillageController::class, 'byDistrict'])->name('villages.by-districts');

        // Resource routes (read-only operations public)
        Route::get('countries', [\App\Http\Controllers\Api\Geo\CountryController::class, 'index'])->name('countries.index');
        Route::get('provinces', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'index'])->name('provinces.index');
        Route::get('cities', [\App\Http\Controllers\Api\Geo\CityController::class, 'index'])->name('cities.index');
        Route::get('districts', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'index'])->name('districts.index');
        Route::get('villages', [\App\Http\Controllers\Api\Geo\VillageController::class, 'index'])->name('villages.index');
    });

    Route::apiResource('organizations', \App\Http\Controllers\Api\OrganizationController::class)->names([
        'index' => 'api.organizations.index',
        'store' => 'api.organizations.store',
        'show' => 'api.organizations.show',
        'update' => 'api.organizations.update',
        'destroy' => 'api.organizations.destroy',
    ]);
    Route::get('organizations/hierarchy/tree', [\App\Http\Controllers\Api\OrganizationController::class, 'getHierarchy']);
    Route::get('organizations/type/{type}', [\App\Http\Controllers\Api\OrganizationController::class, 'getByType']);

    // Organization member management
    Route::get('organizations/{organization}/members', [\App\Http\Controllers\Api\OrganizationController::class, 'members']);
    Route::post('organizations/{organization}/members', [\App\Http\Controllers\Api\OrganizationController::class, 'addMember']);
    Route::put('organizations/{organization}/members/{membership}', [\App\Http\Controllers\Api\OrganizationController::class, 'updateMember']);
    Route::delete('organizations/{organization}/members/{membership}', [\App\Http\Controllers\Api\OrganizationController::class, 'removeMember']);

    // Organization role management
    Route::get('organizations/{organization}/roles', [\App\Http\Controllers\Api\OrganizationController::class, 'roles']);
    Route::post('organizations/{organization}/roles', [\App\Http\Controllers\Api\OrganizationController::class, 'createRole']);

    // Global roles and permissions API
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
    Route::apiResource('permissions', \App\Http\Controllers\Api\PermissionController::class);
    Route::get('roles/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);

    Route::apiResource('organization-units', \App\Http\Controllers\Api\OrganizationUnitController::class);
    Route::get('organization-units/hierarchy/tree', [\App\Http\Controllers\Api\OrganizationUnitController::class, 'getHierarchy']);
    Route::get('organization-units/type/{type}', [\App\Http\Controllers\Api\OrganizationUnitController::class, 'getByType']);

    Route::apiResource('organization-position-levels', \App\Http\Controllers\Api\OrganizationPositionLevelController::class);
    Route::get('organization-position-levels/active', [\App\Http\Controllers\Api\OrganizationPositionLevelController::class, 'getActive']);
    Route::get('organization-position-levels/hierarchy', [\App\Http\Controllers\Api\OrganizationPositionLevelController::class, 'getByHierarchy']);

    // Organization positions list endpoint (must come before resource routes)
    Route::get('organization-positions/list', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'list']);
    Route::get('organization-positions/available', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getAvailablePositions']);
    Route::get('organization-positions/level/{level}', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getByLevel']);
    Route::get('organization-positions/{organizationPosition}/incumbents', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getIncumbents']);
    
    // Organization positions resource routes
    Route::apiResource('organization-positions', \App\Http\Controllers\Api\OrganizationPositionController::class);

    Route::apiResource('organization-memberships', \App\Http\Controllers\Api\OrganizationMembershipController::class);
    Route::post('organization-memberships/{organizationMembership}/activate', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'activate']);
    Route::post('organization-memberships/{organizationMembership}/deactivate', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'deactivate']);
    Route::post('organization-memberships/{organizationMembership}/terminate', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'terminate']);
    Route::get('users/{user}/memberships', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'getUserMemberships']);
    Route::get('organizations/{organization}/memberships', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'getOrganizationMemberships']);
    Route::get('board-members', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'getBoardMembers']);
    Route::get('executives', [\App\Http\Controllers\Api\OrganizationMembershipController::class, 'getExecutives']);

    // Public WebAuthn endpoints (for authentication)
    Route::prefix('auth/webauthn')->name('api.webauthn.')->group(function () {
        Route::get('options', [WebAuthnApiController::class, 'getAuthenticationOptions'])
            ->name('options');
        Route::post('authenticate', [WebAuthnApiController::class, 'authenticate'])
            ->name('authenticate');
        Route::get('capabilities', [WebAuthnApiController::class, 'capabilities'])
            ->name('capabilities');
        Route::get('health', [WebAuthnApiController::class, 'health'])
            ->name('health');
    });

});

// Authenticated API routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    // Geo API endpoints (write operations require authentication)
    Route::prefix('geo')->name('api.geo.')->group(function () {
        // Write operations require authentication
        Route::post('countries', [\App\Http\Controllers\Api\Geo\CountryController::class, 'store'])->name('countries.store');
        Route::get('countries/{country}', [\App\Http\Controllers\Api\Geo\CountryController::class, 'show'])->name('countries.show');
        Route::put('countries/{country}', [\App\Http\Controllers\Api\Geo\CountryController::class, 'update'])->name('countries.update');
        Route::delete('countries/{country}', [\App\Http\Controllers\Api\Geo\CountryController::class, 'destroy'])->name('countries.destroy');
        
        Route::post('provinces', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'store'])->name('provinces.store');
        Route::get('provinces/{province}', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'show'])->name('provinces.show');
        Route::put('provinces/{province}', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('provinces/{province}', [\App\Http\Controllers\Api\Geo\ProvinceController::class, 'destroy'])->name('provinces.destroy');
        
        Route::post('cities', [\App\Http\Controllers\Api\Geo\CityController::class, 'store'])->name('cities.store');
        Route::get('cities/{city}', [\App\Http\Controllers\Api\Geo\CityController::class, 'show'])->name('cities.show');
        Route::put('cities/{city}', [\App\Http\Controllers\Api\Geo\CityController::class, 'update'])->name('cities.update');
        Route::delete('cities/{city}', [\App\Http\Controllers\Api\Geo\CityController::class, 'destroy'])->name('cities.destroy');
        
        Route::post('districts', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'store'])->name('districts.store');
        Route::get('districts/{district}', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'show'])->name('districts.show');
        Route::put('districts/{district}', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'update'])->name('districts.update');
        Route::delete('districts/{district}', [\App\Http\Controllers\Api\Geo\DistrictController::class, 'destroy'])->name('districts.destroy');
        
        Route::post('villages', [\App\Http\Controllers\Api\Geo\VillageController::class, 'store'])->name('villages.store');
        Route::get('villages/{village}', [\App\Http\Controllers\Api\Geo\VillageController::class, 'show'])->name('villages.show');
        Route::put('villages/{village}', [\App\Http\Controllers\Api\Geo\VillageController::class, 'update'])->name('villages.update');
        Route::delete('villages/{village}', [\App\Http\Controllers\Api\Geo\VillageController::class, 'destroy'])->name('villages.destroy');
    });

    // OAuth Client Management API
    Route::prefix('oauth')->name('api.oauth.')->group(function () {
        Route::apiResource('clients', \App\Http\Controllers\OAuth\ClientController::class);
        Route::post('clients/{client}/regenerate-secret', [\App\Http\Controllers\OAuth\ClientController::class, 'regenerateSecret'])
            ->name('clients.regenerate-secret');
    });

    // Activity logs API
    Route::get('activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index'])->name('api.activity-logs.index');
    Route::get('activity-logs/{activity}', [\App\Http\Controllers\Api\ActivityLogController::class, 'show'])->name('api.activity-logs.show');

    // User Security Profile
    Route::prefix('security')->name('api.security.')->group(function () {
        Route::get('/', [UserSecurityController::class, 'index'])->name('profile');
        Route::get('activity', [UserSecurityController::class, 'activity'])->name('activity');
        Route::get('recommendations', [UserSecurityController::class, 'recommendations'])->name('recommendations');
        Route::get('settings', [UserSecurityController::class, 'settings'])->name('settings');
        Route::put('settings', [UserSecurityController::class, 'updateSettings'])->name('settings.update');
        Route::put('password', [UserSecurityController::class, 'updatePassword'])->name('password.update');
        Route::get('sessions', [UserSecurityController::class, 'sessions'])->name('sessions');
        Route::delete('sessions', [UserSecurityController::class, 'revokeSessions'])->name('sessions.revoke');
    });

    // MFA API endpoints
    Route::prefix('mfa')->name('api.mfa.')->group(function () {
        Route::get('/', [MfaApiController::class, 'index'])->name('status');
        Route::post('/', [MfaApiController::class, 'store'])->name('enable');
        Route::put('/', [MfaApiController::class, 'update'])->name('confirm');
        Route::delete('/', [MfaApiController::class, 'destroy'])->name('disable');
        Route::post('verify', [MfaApiController::class, 'verify'])->name('verify');
        Route::get('backup-codes/status', [MfaApiController::class, 'backupCodesStatus'])->name('backup-codes.status');
        Route::post('backup-codes/regenerate', [MfaApiController::class, 'regenerateBackupCodes'])->name('backup-codes.regenerate');
    });

    // WebAuthn/Passkey management endpoints
    Route::prefix('webauthn')->name('api.webauthn.')->group(function () {
        Route::get('/', [WebAuthnApiController::class, 'index'])->name('list');
        Route::get('register/options', [WebAuthnApiController::class, 'getRegistrationOptions'])->name('register.options');
        Route::post('register', [WebAuthnApiController::class, 'store'])->name('register');
        Route::put('{passkey}', [WebAuthnApiController::class, 'update'])->name('update');
        Route::delete('{passkey}', [WebAuthnApiController::class, 'destroy'])->name('delete');
        Route::get('statistics', [WebAuthnApiController::class, 'statistics'])->name('statistics');
    });


    // User API endpoints
    Route::prefix('users')->name('api.users.')->group(function () {
        Route::get('/', [UserApiController::class, 'index'])
            ->name('index')
            ->middleware('throttle:60,1');
        Route::get('search', [UserApiController::class, 'search'])
            ->name('search')
            ->middleware('throttle:30,1');
        Route::get('suggestions', [UserApiController::class, 'suggestions'])
            ->name('suggestions')
            ->middleware('throttle:60,1');
        Route::get('{id}', [UserApiController::class, 'show'])
            ->name('show')
            ->middleware('throttle:60,1');
    });

    // Notification API endpoints
    Route::prefix('notifications')->name('api.notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index'])
            ->name('index')
            ->middleware('throttle:60,1');
        Route::post('/', [\App\Http\Controllers\Api\NotificationController::class, 'store'])
            ->name('store')
            ->middleware('throttle:10,1');
        Route::patch('{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])
            ->name('read')
            ->middleware('throttle:60,1');
        Route::patch('read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])
            ->name('read-all')
            ->middleware('throttle:10,1');
        Route::delete('{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])
            ->name('destroy')
            ->middleware('throttle:60,1');
    });
});
