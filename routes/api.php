<?php

use App\Http\Controllers\Api\MfaApiController;
use App\Http\Controllers\Api\UserSecurityController;
use App\Http\Controllers\Api\WebAuthnApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public API routes (no authentication required for demo)
Route::prefix('v1')->group(function () {
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

    // Global roles API
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);
    Route::get('permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);

    Route::apiResource('organization-units', \App\Http\Controllers\Api\OrganizationUnitController::class);
    Route::get('organization-units/hierarchy/tree', [\App\Http\Controllers\Api\OrganizationUnitController::class, 'getHierarchy']);
    Route::get('organization-units/type/{type}', [\App\Http\Controllers\Api\OrganizationUnitController::class, 'getByType']);

    Route::apiResource('organization-position-levels', \App\Http\Controllers\Api\OrganizationPositionLevelController::class);
    Route::get('organization-position-levels/active', [\App\Http\Controllers\Api\OrganizationPositionLevelController::class, 'getActive']);
    Route::get('organization-position-levels/hierarchy', [\App\Http\Controllers\Api\OrganizationPositionLevelController::class, 'getByHierarchy']);

    Route::apiResource('organization-positions', \App\Http\Controllers\Api\OrganizationPositionController::class);
    Route::get('organization-positions/available', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getAvailablePositions']);
    Route::get('organization-positions/level/{level}', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getByLevel']);
    Route::get('organization-positions/{organizationPosition}/incumbents', [\App\Http\Controllers\Api\OrganizationPositionController::class, 'getIncumbents']);

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
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
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
});
