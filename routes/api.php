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

    // Public Group Invitation Endpoints (no authentication required)
    Route::prefix('invitations')->name('api.invitations.')->group(function () {
        Route::post('accept/{token}', [\App\Http\Controllers\Api\GroupInviteController::class, 'acceptInvitation'])
            ->name('accept')
            ->middleware('auth:api');
        Route::post('reject/{token}', [\App\Http\Controllers\Api\GroupInviteController::class, 'rejectInvitation'])
            ->name('reject')
            ->middleware('auth:api');
    });

    // Public Group Invite Link Endpoints
    Route::prefix('invite-links')->name('api.invite-links.')->group(function () {
        Route::post('join/{token}', [\App\Http\Controllers\Api\GroupInviteController::class, 'useInviteLink'])
            ->name('join')
            ->middleware('auth:api')
            ->middleware('rate_limit:use_invite_link,per_user');
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

    // E2EE Chat System API
    Route::prefix('chat')->name('api.chat.')->group(function () {
        // Device Management
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'register'])
                ->name('register')
                ->middleware('rate_limit:device_registration,per_user');
            Route::get('{device}', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'show'])
                ->name('show');
            Route::patch('{device}', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'update'])
                ->name('update');
            Route::post('{device}/trust', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'trust'])
                ->name('trust');
            Route::delete('{device}/trust', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'untrust'])
                ->name('untrust');
            Route::delete('{device}', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'revoke'])
                ->name('revoke');
            Route::get('{device}/verification-code', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'getVerificationCode'])
                ->name('verification-code');
            Route::post('{device}/rotate-keys', [\App\Http\Controllers\Api\Chat\DeviceController::class, 'rotateKeys'])
                ->name('rotate-keys');
        });

        // Conversations
        Route::prefix('conversations')->name('conversations.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'store'])
                ->name('store')
                ->middleware('rate_limit:create_conversation,per_user');
            Route::get('{conversation}', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'show'])
                ->name('show');
            Route::patch('{conversation}', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'update'])
                ->name('update');
            Route::delete('{conversation}/leave', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'leave'])
                ->name('leave');
            Route::post('{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'addParticipant'])
                ->name('add-participant');
            Route::delete('{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'removeParticipant'])
                ->name('remove-participant');
            Route::post('{conversation}/rotate-keys', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'rotateKeys'])
                ->name('rotate-keys');

            // Messages within conversations
            Route::prefix('{conversation}/messages')->name('messages.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Chat\MessageController::class, 'index'])
                    ->name('index');
                Route::post('/', [\App\Http\Controllers\Api\Chat\MessageController::class, 'store'])
                    ->name('store')
                    ->middleware('rate_limit:messages,per_user');
                Route::get('{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'show'])
                    ->name('show');
                Route::patch('{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'update'])
                    ->name('update');
                Route::delete('{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'destroy'])
                    ->name('destroy');
                Route::post('{message}/reactions', [\App\Http\Controllers\Api\Chat\MessageController::class, 'addReaction'])
                    ->name('add-reaction');
                Route::delete('{message}/reactions', [\App\Http\Controllers\Api\Chat\MessageController::class, 'removeReaction'])
                    ->name('remove-reaction');
                Route::post('{message}/forward', [\App\Http\Controllers\Api\Chat\MessageController::class, 'forward'])
                    ->name('forward');

                // Message actions
                Route::post('{message}/pin', [\App\Http\Controllers\Api\Chat\MessageController::class, 'togglePin'])
                    ->name('toggle-pin');
                Route::post('{message}/bookmark', [\App\Http\Controllers\Api\Chat\MessageController::class, 'toggleBookmark'])
                    ->name('toggle-bookmark');
                Route::post('{message}/flag', [\App\Http\Controllers\Api\Chat\MessageController::class, 'toggleFlag'])
                    ->name('toggle-flag');
                Route::get('{message}/read-receipts', [\App\Http\Controllers\Api\Chat\MessageController::class, 'getReadReceipts'])
                    ->name('read-receipts');
                Route::get('{message}/download', [\App\Http\Controllers\Api\Chat\MessageController::class, 'downloadAttachment'])
                    ->name('download-attachment');

                // Migration endpoint for existing quantum-encrypted messages
                Route::post('{message}/migrate', [\App\Http\Controllers\Api\Chat\MessageController::class, 'migrateMessage'])
                    ->name('migrate');
            });

            // File attachments
            Route::post('{conversation}/attachments', [\App\Http\Controllers\Api\Chat\MessageController::class, 'uploadAttachment'])
                ->name('upload-attachment');

            // File management
            Route::prefix('{conversation}/files')->name('files.')->group(function () {
                Route::post('/', [\App\Http\Controllers\Api\Chat\FileController::class, 'upload'])
                    ->name('upload');
                Route::post('bulk', [\App\Http\Controllers\Api\Chat\FileController::class, 'bulkUpload'])
                    ->name('bulk-upload');
                Route::get('{file}', [\App\Http\Controllers\Api\Chat\FileController::class, 'download'])
                    ->name('download');
                Route::get('{file}/info', [\App\Http\Controllers\Api\Chat\FileController::class, 'getFileInfo'])
                    ->name('info');
                Route::get('{file}/thumbnail', [\App\Http\Controllers\Api\Chat\FileController::class, 'thumbnail'])
                    ->name('thumbnail');
            });

            // Polls
            Route::prefix('{conversation}/polls')->name('polls.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Chat\PollController::class, 'list'])
                    ->name('list');
                Route::post('/', [\App\Http\Controllers\Api\Chat\PollController::class, 'create'])
                    ->name('create')
                    ->middleware('rate_limit:poll_creation,per_user');
                Route::get('{poll}', [\App\Http\Controllers\Api\Chat\PollController::class, 'show'])
                    ->name('show');
                Route::post('{poll}/vote', [\App\Http\Controllers\Api\Chat\PollController::class, 'vote'])
                    ->name('vote')
                    ->middleware('rate_limit:poll_votes,per_user');
                Route::get('{poll}/results', [\App\Http\Controllers\Api\Chat\PollController::class, 'results'])
                    ->name('results');
                Route::post('{poll}/close', [\App\Http\Controllers\Api\Chat\PollController::class, 'close'])
                    ->name('close');
                Route::get('{poll}/analytics', [\App\Http\Controllers\Api\Chat\PollController::class, 'analytics'])
                    ->name('analytics');
            });

            // Surveys
            Route::prefix('{conversation}/surveys')->name('surveys.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'list'])
                    ->name('list');
                Route::post('/', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'create'])
                    ->name('create');
                Route::get('{survey}', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'show'])
                    ->name('show');
                Route::post('{survey}/respond', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'respond'])
                    ->name('respond');
                Route::get('{survey}/results', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'results'])
                    ->name('results');
                Route::post('{survey}/close', [\App\Http\Controllers\Api\Chat\SurveyController::class, 'close'])
                    ->name('close');
            });
        });

        // Backup and Export
        Route::prefix('backups')->name('backups.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Chat\BackupController::class, 'list'])
                ->name('list');
            Route::post('/', [\App\Http\Controllers\Api\Chat\BackupController::class, 'create'])
                ->name('create');
            Route::get('{backup}', [\App\Http\Controllers\Api\Chat\BackupController::class, 'show'])
                ->name('show');
            Route::get('{backup}/download', [\App\Http\Controllers\Api\Chat\BackupController::class, 'download'])
                ->name('download');
            Route::get('{backup}/progress', [\App\Http\Controllers\Api\Chat\BackupController::class, 'progress'])
                ->name('progress');
            Route::post('{backup}/verify', [\App\Http\Controllers\Api\Chat\BackupController::class, 'verify'])
                ->name('verify');
            Route::post('{backup}/cancel', [\App\Http\Controllers\Api\Chat\BackupController::class, 'cancel'])
                ->name('cancel');
            Route::delete('{backup}', [\App\Http\Controllers\Api\Chat\BackupController::class, 'delete'])
                ->name('delete');
        });

        // Abuse Reporting
        Route::prefix('abuse-reports')->name('abuse-reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Chat\AbuseReportController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Api\Chat\AbuseReportController::class, 'create'])
                ->name('create')
                ->middleware('rate_limit:abuse_reports,per_user');
            Route::get('stats', [\App\Http\Controllers\Api\Chat\AbuseReportController::class, 'getStats'])
                ->name('stats');
            Route::get('{report}', [\App\Http\Controllers\Api\Chat\AbuseReportController::class, 'show'])
                ->name('show');
            Route::patch('{report}/review', [\App\Http\Controllers\Api\Chat\AbuseReportController::class, 'review'])
                ->name('review');
        });

        // Rate Limiting Management
        Route::prefix('rate-limits')->name('rate-limits.')->group(function () {
            Route::get('status', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'getRateLimitStatus'])
                ->name('status');
            Route::get('configs', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'getConfigs'])
                ->name('configs');
            Route::post('configs', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'createConfig'])
                ->name('configs.create');
            Route::patch('configs/{config}', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'updateConfig'])
                ->name('configs.update');
            Route::delete('configs/{config}', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'deleteConfig'])
                ->name('configs.delete');
            Route::get('penalties', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'getUserPenalties'])
                ->name('penalties');
            Route::post('penalties', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'applyUserPenalty'])
                ->name('penalties.apply');
            Route::get('ip-restrictions', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'getIpRestrictions'])
                ->name('ip-restrictions');
            Route::get('stats', [\App\Http\Controllers\Api\Chat\RateLimitController::class, 'getSystemStats'])
                ->name('stats');
        });

        // Video/Audio Calls
        Route::prefix('conversations/{conversation}/calls')->name('calls.')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'initiate'])
                ->name('initiate')
                ->middleware('rate_limit:video_calls,per_user');
            Route::get('history', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'history'])
                ->name('history');
        });

        Route::prefix('calls')->name('calls.')->group(function () {
            Route::get('{call}', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'show'])
                ->name('show');
            Route::post('{call}/join', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'join'])
                ->name('join');
            Route::post('{call}/leave', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'leave'])
                ->name('leave');
            Route::post('{call}/reject', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'reject'])
                ->name('reject');
            Route::post('{call}/end', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'end'])
                ->name('end');
            Route::post('{call}/quality-metrics', [\App\Http\Controllers\Api\Chat\VideoCallController::class, 'updateQualityMetrics'])
                ->name('quality-metrics');
        });

        // Group Management
        Route::prefix('groups')->name('groups.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\GroupController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Api\GroupController::class, 'store'])
                ->name('store')
                ->middleware('rate_limit:create_group,per_user');
            Route::get('{group}', [\App\Http\Controllers\Api\GroupController::class, 'show'])
                ->name('show');
            Route::patch('{group}', [\App\Http\Controllers\Api\GroupController::class, 'update'])
                ->name('update');
            Route::delete('{group}', [\App\Http\Controllers\Api\GroupController::class, 'destroy'])
                ->name('destroy');
            Route::post('{group}/join', [\App\Http\Controllers\Api\GroupController::class, 'join'])
                ->name('join')
                ->middleware('rate_limit:join_group,per_user');
            Route::post('{group}/leave', [\App\Http\Controllers\Api\GroupController::class, 'leave'])
                ->name('leave');

            // Group Member Management
            Route::prefix('{group}/members')->name('members.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\GroupMemberController::class, 'index'])
                    ->name('index');
                Route::get('{member}', [\App\Http\Controllers\Api\GroupMemberController::class, 'show'])
                    ->name('show');
                Route::post('/', [\App\Http\Controllers\Api\GroupMemberController::class, 'addMember'])
                    ->name('add')
                    ->middleware('rate_limit:add_group_member,per_user');
                Route::delete('{member}', [\App\Http\Controllers\Api\GroupMemberController::class, 'removeMember'])
                    ->name('remove');
                Route::patch('{member}/role', [\App\Http\Controllers\Api\GroupMemberController::class, 'updateMemberRole'])
                    ->name('update-role');
                Route::post('{member}/ban', [\App\Http\Controllers\Api\GroupMemberController::class, 'banMember'])
                    ->name('ban')
                    ->middleware('rate_limit:ban_group_member,per_user');
                Route::delete('banned/{userId}', [\App\Http\Controllers\Api\GroupMemberController::class, 'unbanMember'])
                    ->name('unban');
                Route::post('mute', [\App\Http\Controllers\Api\GroupMemberController::class, 'muteMembers'])
                    ->name('mute')
                    ->middleware('rate_limit:mute_group_members,per_user');
            });

            // Group Invitations
            Route::prefix('{group}/invitations')->name('invitations.')->group(function () {
                Route::post('/', [\App\Http\Controllers\Api\GroupInviteController::class, 'createInvitation'])
                    ->name('create')
                    ->middleware('rate_limit:create_group_invitation,per_user');
                
                // Invite Links
                Route::prefix('links')->name('links.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\GroupInviteController::class, 'getInviteLinks'])
                        ->name('index');
                    Route::post('/', [\App\Http\Controllers\Api\GroupInviteController::class, 'createInviteLink'])
                        ->name('create')
                        ->middleware('rate_limit:create_invite_link,per_user');
                    Route::delete('{link}', [\App\Http\Controllers\Api\GroupInviteController::class, 'revokeInviteLink'])
                        ->name('revoke');
                });

                // Join Requests
                Route::prefix('requests')->name('requests.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\GroupInviteController::class, 'getJoinRequests'])
                        ->name('index');
                    Route::post('{request}/approve', [\App\Http\Controllers\Api\GroupInviteController::class, 'approveJoinRequest'])
                        ->name('approve');
                    Route::post('{request}/reject', [\App\Http\Controllers\Api\GroupInviteController::class, 'rejectJoinRequest'])
                        ->name('reject');
                });
            });
        });
    });

    // LiveKit Webhooks (no authentication required)
    Route::post('livekit/webhook', [\App\Http\Controllers\Api\Chat\LiveKitWebhookController::class, 'handle'])
        ->name('livekit.webhook');

    // Webhook Management
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WebhookController::class, 'index'])
            ->name('index');
        Route::post('/', [\App\Http\Controllers\Api\WebhookController::class, 'store'])
            ->name('store')
            ->middleware('throttle:10,1');
        Route::get('events', [\App\Http\Controllers\Api\WebhookController::class, 'events'])
            ->name('events');
        Route::get('{webhook}', [\App\Http\Controllers\Api\WebhookController::class, 'show'])
            ->name('show');
        Route::patch('{webhook}', [\App\Http\Controllers\Api\WebhookController::class, 'update'])
            ->name('update');
        Route::delete('{webhook}', [\App\Http\Controllers\Api\WebhookController::class, 'destroy'])
            ->name('destroy');
        Route::post('{webhook}/regenerate-secret', [\App\Http\Controllers\Api\WebhookController::class, 'regenerateSecret'])
            ->name('regenerate-secret');
        Route::post('{webhook}/test', [\App\Http\Controllers\Api\WebhookController::class, 'test'])
            ->name('test')
            ->middleware('throttle:5,1');
        Route::get('{webhook}/deliveries', [\App\Http\Controllers\Api\WebhookController::class, 'deliveries'])
            ->name('deliveries');
        Route::post('{webhook}/deliveries/{delivery}/retry', [\App\Http\Controllers\Api\WebhookController::class, 'retryDelivery'])
            ->name('deliveries.retry')
            ->middleware('throttle:10,1');
    });

    // Security Audit Management
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Api\SecurityAuditController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('audit-logs', [\App\Http\Controllers\Api\SecurityAuditController::class, 'index'])
            ->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\SecurityAuditController::class, 'show'])
            ->name('audit-logs.show');
        Route::patch('audit-logs/{auditLog}/investigate', [\App\Http\Controllers\Api\SecurityAuditController::class, 'investigate'])
            ->name('audit-logs.investigate');
        Route::patch('audit-logs/{auditLog}/resolve', [\App\Http\Controllers\Api\SecurityAuditController::class, 'resolve'])
            ->name('audit-logs.resolve');
        Route::get('report', [\App\Http\Controllers\Api\SecurityAuditController::class, 'report'])
            ->name('report')
            ->middleware('throttle:5,1');
        Route::get('event-types', [\App\Http\Controllers\Api\SecurityAuditController::class, 'eventTypes'])
            ->name('event-types');
    });

    // Bot Management
    Route::prefix('bots')->name('bots.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BotController::class, 'index'])
            ->name('index');
        Route::post('/', [\App\Http\Controllers\Api\BotController::class, 'store'])
            ->name('store')
            ->middleware('throttle:10,1');
        Route::get('capabilities', [\App\Http\Controllers\Api\BotController::class, 'capabilities'])
            ->name('capabilities');
        Route::get('{bot}', [\App\Http\Controllers\Api\BotController::class, 'show'])
            ->name('show');
        Route::patch('{bot}', [\App\Http\Controllers\Api\BotController::class, 'update'])
            ->name('update');
        Route::delete('{bot}', [\App\Http\Controllers\Api\BotController::class, 'destroy'])
            ->name('destroy');
        Route::post('{bot}/regenerate-token', [\App\Http\Controllers\Api\BotController::class, 'regenerateToken'])
            ->name('regenerate-token');
        Route::post('{bot}/regenerate-webhook-secret', [\App\Http\Controllers\Api\BotController::class, 'regenerateWebhookSecret'])
            ->name('regenerate-webhook-secret');
        Route::post('{bot}/add-to-conversation', [\App\Http\Controllers\Api\BotController::class, 'addToConversation'])
            ->name('add-to-conversation')
            ->middleware('throttle:20,1');
        Route::delete('{bot}/conversations/{conversation}', [\App\Http\Controllers\Api\BotController::class, 'removeFromConversation'])
            ->name('remove-from-conversation');

        // Bot API endpoints (authenticated with bot tokens)
        Route::post('{bot}/send-message', [\App\Http\Controllers\Api\BotController::class, 'sendMessage'])
            ->name('send-message')
            ->middleware('throttle:bot-api:100,1');
    });

    // Voice Transcription Management
    Route::prefix('voice-transcriptions')->name('voice-transcriptions.')->group(function () {
        Route::post('transcribe', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'transcribe'])
            ->name('transcribe')
            ->middleware('throttle:20,1');
        Route::get('status/{message}', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'status'])
            ->name('status');
        Route::get('{transcription}', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'show'])
            ->name('show');
        Route::post('{transcription}/retry', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'retry'])
            ->name('retry')
            ->middleware('throttle:10,1');
        Route::delete('{transcription}', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'destroy'])
            ->name('destroy');
        Route::post('bulk-transcribe', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'bulkTranscribe'])
            ->name('bulk-transcribe')
            ->middleware('throttle:5,1');
        Route::get('statistics', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'statistics'])
            ->name('statistics');
        Route::get('search', [\App\Http\Controllers\Api\VoiceTranscriptionController::class, 'search'])
            ->name('search');
    });

    // Message Scheduling Management
    Route::prefix('scheduled-messages')->name('scheduled-messages.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'index'])
            ->name('index');
        Route::post('/', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'store'])
            ->name('store')
            ->middleware('throttle:30,1');
        Route::get('{scheduledMessage}', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'show'])
            ->name('show');
        Route::patch('{scheduledMessage}', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'update'])
            ->name('update')
            ->middleware('throttle:20,1');
        Route::post('{scheduledMessage}/cancel', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'cancel'])
            ->name('cancel')
            ->middleware('throttle:20,1');
        Route::post('{scheduledMessage}/retry', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'retry'])
            ->name('retry')
            ->middleware('throttle:10,1');
        Route::delete('{scheduledMessage}', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'destroy'])
            ->name('destroy');
        Route::get('statistics', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'statistics'])
            ->name('statistics');
        Route::post('bulk-action', [\App\Http\Controllers\Api\ScheduledMessageController::class, 'bulkAction'])
            ->name('bulk-action')
            ->middleware('throttle:10,1');
    });

    // Channel Management API
    Route::prefix('channels')->name('channels.')->group(function () {
        // Public channel discovery (no authentication required)
        Route::get('discover', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'discover'])
            ->name('discover');
        Route::get('categories', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'categories'])
            ->name('categories');
        
        // Authenticated channel routes
        Route::middleware('auth:api')->group(function () {
            // Channel CRUD
            Route::get('/', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'index'])
                ->name('index');
            Route::post('/', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'store'])
                ->name('store')
                ->middleware('rate_limit:create_channel,per_user');
            Route::get('{channel}', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'show'])
                ->name('show');
            Route::patch('{channel}', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'update'])
                ->name('update');
            
            // Channel subscription management
            Route::post('{channel}/subscribe', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'subscribe'])
                ->name('subscribe')
                ->middleware('rate_limit:channel_subscribe,per_user');
            Route::delete('{channel}/unsubscribe', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'unsubscribe'])
                ->name('unsubscribe');
            
            // Channel admin routes
            Route::middleware('throttle:30,1')->group(function () {
                Route::get('{channel}/subscribers', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'subscribers'])
                    ->name('subscribers');
                Route::get('{channel}/statistics', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'statistics'])
                    ->name('statistics');
                Route::post('{channel}/verify', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'verify'])
                    ->name('verify');
                
                // Channel broadcasts
                Route::prefix('{channel}/broadcasts')->name('broadcasts.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'index'])
                        ->name('index');
                    Route::post('/', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'store'])
                        ->name('store');
                    Route::get('{broadcast}', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'show'])
                        ->name('show');
                    Route::patch('{broadcast}', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'update'])
                        ->name('update');
                    Route::delete('{broadcast}', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'destroy'])
                        ->name('destroy');
                    Route::post('{broadcast}/send', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'send'])
                        ->name('send');
                    Route::post('{broadcast}/duplicate', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'duplicate'])
                        ->name('duplicate');
                    Route::get('{broadcast}/analytics', [\App\Http\Controllers\Api\Chat\ChannelBroadcastController::class, 'analytics'])
                        ->name('analytics');
                });
                
                // Channel management (admin functions)
                Route::prefix('{channel}/manage')->name('manage.')->group(function () {
                    Route::post('add-admin', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'addAdmin'])
                        ->name('add-admin');
                    Route::delete('remove-admin/{user}', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'removeAdmin'])
                        ->name('remove-admin');
                    Route::post('ban-user', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'banUser'])
                        ->name('ban-user');
                    Route::delete('unban-user/{user}', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'unbanUser'])
                        ->name('unban-user');
                    Route::delete('delete', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'deleteChannel'])
                        ->name('delete');
                    Route::post('transfer-ownership', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'transferOwnership'])
                        ->name('transfer-ownership');
                    Route::get('admins', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'getAdmins'])
                        ->name('admins');
                    Route::get('banned-users', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'getBannedUsers'])
                        ->name('banned-users');
                    Route::patch('settings', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'updateChannelSettings'])
                        ->name('settings');
                    Route::get('export', [\App\Http\Controllers\Api\Chat\ChannelManagementController::class, 'exportData'])
                        ->name('export');
                });
            });
        });
    });
});
