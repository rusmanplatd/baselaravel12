<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// File Manager route
Route::middleware(['auth', 'verified'])->get('/file-manager', function () {
    return Inertia::render('file-manager');
})->name('file-manager');

// Public API documentation
Route::get('developer/api-reference', [\App\Http\Controllers\DeveloperController::class, 'apiReference'])->name('developer.api-reference');

// Custom broadcasting auth route that fully supports API tokens
Route::post('broadcasting/auth', function (\Illuminate\Http\Request $request) {
    \Log::info('Broadcasting auth route called', [
        'user' => Auth::user() ? Auth::user()->only(['id', 'name']) : null,
        'socket_id' => $request->input('socket_id'),
        'channel_name' => $request->input('channel_name'),
        'has_auth_header' => $request->hasHeader('Authorization'),
        'auth_header_prefix' => $request->header('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : null,
    ]);

    $user = Auth::user();

    // If no session user, try API token authentication
    if (! $user && $request->hasHeader('Authorization')) {
        $token = $request->header('Authorization');

        // Remove 'Bearer ' prefix if present
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            // Create a temporary request with the auth header for API guard
            $originalRequest = app('request');
            $testRequest = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [], '');
            $testRequest->headers->set('Authorization', 'Bearer '.$token);

            // Temporarily swap the request
            app()->instance('request', $testRequest);

            // Use the API guard to authenticate
            $guard = auth('api');
            $guard->forgetUser(); // Clear any cached user
            $user = $guard->user();

            // Restore original request
            app()->instance('request', $originalRequest);
            $guard->forgetUser(); // Clear cache again after restoring

            \Log::info('API token authentication result', [
                'authenticated' => $user !== null,
                'user_id' => $user ? $user->id : null,
            ]);
        } catch (Exception $e) {
            \Log::error('API token authentication error', [
                'error' => $e->getMessage(),
                'token_prefix' => substr($token, 0, 10).'...',
            ]);
        }
    }

    if (! $user) {
        \Log::warning('No authenticated user for broadcasting auth');

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');

    if (! $socketId || ! $channelName) {
        \Log::warning('Missing required parameters', [
            'socket_id' => $socketId,
            'channel_name' => $channelName,
        ]);

        return response()->json(['message' => 'Missing required parameters'], 400);
    }

    \Log::info('Checking channel authorization', [
        'channel' => $channelName,
        'user_id' => $user->id,
    ]);

    // For user.{id} channels, check if user ID matches
    if (preg_match('/^user\.(\w+)$/', $channelName, $matches)) {
        $requestedUserId = $matches[1];

        if ($user->id !== $requestedUserId) {
            \Log::warning('User ID mismatch for user channel', [
                'authenticated_user' => $user->id,
                'requested_user' => $requestedUserId,
            ]);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Generate auth signature for Reverb
        $stringToSign = $socketId.':'.$channelName;
        $secret = env('REVERB_APP_SECRET');
        $appId = env('REVERB_APP_ID');
        $signature = hash_hmac('sha256', $stringToSign, $secret);
        $auth = $appId.':'.$signature;

        \Log::info('Broadcasting auth successful', [
            'channel' => $channelName,
            'user_id' => $user->id,
            'auth_prefix' => substr($auth, 0, 20).'...',
        ]);

        return response()->json(['auth' => $auth]);
    }

    // For conversation.{id} channels (with or without private- prefix), check if user is participant
    if (preg_match('/^(?:private-)?conversation\.(\w+)$/', $channelName, $matches)) {
        $conversationId = $matches[1];

        \Log::info('Conversation channel auth attempt', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);

        try {
            $conversation = \App\Models\Chat\Conversation::where('id', $conversationId)->first();
            if (!$conversation) {
                \Log::warning('Conversation not found', [
                    'conversation_id' => $conversationId,
                ]);
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $participant = $conversation->participants()->where('user_id', $user->id)->first();
            if (!$participant) {
                \Log::warning('User not a participant', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversationId,
                ]);
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($participant->left_at !== null) {
                \Log::warning('User has left the conversation', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversationId,
                    'left_at' => $participant->left_at,
                ]);
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // Generate auth signature for Reverb
            $stringToSign = $socketId.':'.$channelName;
            $secret = env('REVERB_APP_SECRET');
            $appId = env('REVERB_APP_ID');
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            $auth = $appId.':'.$signature;

            \Log::info('Conversation broadcasting auth successful', [
                'channel' => $channelName,
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'participant_role' => $participant->role,
                'auth_prefix' => substr($auth, 0, 20).'...',
            ]);

            return response()->json(['auth' => $auth]);

        } catch (\Exception $e) {
            \Log::error('Conversation channel auth error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['message' => 'Forbidden'], 403);
        }
    }

    // For other channels, use Laravel's channel authorization
    try {
        \Log::info('Attempting channel authorization', [
            'channel' => $channelName,
            'user_id' => $user->id,
            'socket_id' => $socketId,
        ]);

        $broadcastManager = app(\Illuminate\Broadcasting\BroadcastManager::class);
        $result = $broadcastManager->auth($request, $channelName);

        \Log::info('Channel authorization result', [
            'channel' => $channelName,
            'user_id' => $user->id,
            'result' => $result,
            'result_type' => gettype($result),
        ]);

        if ($result === false || $result === null) {
            \Log::warning('Channel authorization failed', [
                'channel' => $channelName,
                'user_id' => $user->id,
                'result' => $result,
            ]);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Generate auth signature for Reverb
        $stringToSign = $socketId.':'.$channelName;
        $secret = env('REVERB_APP_SECRET');
        $appId = env('REVERB_APP_ID');
        $signature = hash_hmac('sha256', $stringToSign, $secret);
        $auth = $appId.':'.$signature;

        \Log::info('Broadcasting auth signature generated', [
            'channel' => $channelName,
            'user_id' => $user->id,
            'auth_prefix' => substr($auth, 0, 20).'...',
        ]);

        return response()->json(['auth' => $auth]);

    } catch (\Exception $e) {
        \Log::error('Broadcasting auth error', [
            'error' => $e->getMessage(),
            'channel' => $channelName,
            'user_id' => $user->id,
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json(['message' => 'Authorization failed'], 403);
    }
})->middleware(['broadcasting.auth']);

Route::middleware(['auth', 'verified', 'mfa.verified'])->group(function () {
    // Tenant management routes (before tenant middleware)
    Route::get('tenant/select', [\App\Http\Controllers\TenantController::class, 'select'])->name('tenant.select');
    Route::post('tenant/switch', [\App\Http\Controllers\TenantController::class, 'switch'])->name('tenant.switch');
    Route::get('tenant/current', [\App\Http\Controllers\TenantController::class, 'current'])->name('tenant.current');
    Route::get('tenant/available', [\App\Http\Controllers\TenantController::class, 'available'])->name('tenant.available');
    Route::post('tenant/clear', [\App\Http\Controllers\TenantController::class, 'clear'])->name('tenant.clear');

    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('projects', function () {
        return Inertia::render('projects');
    })->name('projects');

    Route::get('projects/{project}', function (Request $request, $project) {
        return Inertia::render('project-detail', [
            'projectId' => $project
        ]);
    })->name('projects.show');

    Route::get('chat', function () {
        return Inertia::render('chat');
    })->name('chat');

    Route::get('chat/{conversationId}', function ($conversationId) {
        $user = auth()->user();

        // Check if user has access to this conversation
        $conversation = \App\Models\Chat\Conversation::where('id', $conversationId)->first();

        if (!$conversation) {
            abort(404, 'Conversation not found');
        }

        $participant = $conversation->participants()->where('user_id', $user->id)->first();

        if (!$participant || $participant->left_at !== null) {
            abort(403, 'You do not have access to this conversation');
        }

        return Inertia::render('chat', [
            'initialConversationId' => $conversationId,
        ]);
    })->name('chat.conversation');

    Route::get('calendar', function () {
        return Inertia::render('calendar');
    })->name('calendar');

    // Meeting routes
    Route::get('meetings/{meeting}/join', [\App\Http\Controllers\MeetingController::class, 'join'])->name('meetings.join');
    Route::get('meetings/{meeting}/host', [\App\Http\Controllers\MeetingController::class, 'host'])->name('meetings.host');

    // Generate personal access token for API usage
    Route::post('api/generate-token', function () {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required to generate API token',
            ], 401);
        }

        // Check if user already has an active token
        $existingTokens = $user->tokens()->where('revoked', false)->where('name', 'API Access Token')->get();

        // Revoke existing tokens to prevent token accumulation
        foreach ($existingTokens as $existingToken) {
            $existingToken->revoke();
        }

        $token = $user->createToken('API Access Token')->accessToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    })->name('api.generate-token');

    // Organizations
    Route::resource('organizations', \App\Http\Controllers\OrganizationController::class);
    Route::get('organizations-hierarchy', [\App\Http\Controllers\OrganizationController::class, 'hierarchy'])->name('organizations.hierarchy');

    // Organization member management
    Route::middleware('organization.context')->group(function () {
        Route::get('organizations/{organization}/members', [\App\Http\Controllers\OrganizationController::class, 'members'])->name('organizations.members');
        Route::post('organizations/{organization}/members', [\App\Http\Controllers\OrganizationController::class, 'addMember'])->name('organizations.members.add');
        Route::put('organizations/{organization}/members/{membership}', [\App\Http\Controllers\OrganizationController::class, 'updateMember'])->name('organizations.members.update');
        Route::delete('organizations/{organization}/members/{membership}', [\App\Http\Controllers\OrganizationController::class, 'removeMember'])->name('organizations.members.remove');

        // Organization role management
        Route::get('organizations/{organization}/roles', [\App\Http\Controllers\OrganizationController::class, 'roles'])->name('organizations.roles');
        Route::post('organizations/{organization}/roles', [\App\Http\Controllers\OrganizationController::class, 'createRole'])->name('organizations.roles.create');
    });

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

    // User Management
    Route::resource('users', \App\Http\Controllers\UserController::class)->except(['create', 'store']);
    Route::post('users/{user}/assign-roles', [\App\Http\Controllers\UserController::class, 'assignRoles'])->name('users.assignRoles');

    // Roles and Permissions
    Route::resource('roles', \App\Http\Controllers\RoleController::class);
    Route::delete('roles/bulk-delete', [\App\Http\Controllers\RoleController::class, 'bulkDelete'])->name('roles.bulkDelete');
    Route::post('roles/bulk-assign-permissions', [\App\Http\Controllers\RoleController::class, 'bulkAssignPermissions'])->name('roles.bulkAssignPermissions');

    Route::resource('permissions', \App\Http\Controllers\PermissionController::class);
    Route::delete('permissions/bulk-delete', [\App\Http\Controllers\PermissionController::class, 'bulkDelete'])->name('permissions.bulkDelete');
    Route::post('permissions/bulk-create-by-pattern', [\App\Http\Controllers\PermissionController::class, 'bulkCreateByPattern'])->name('permissions.bulkCreateByPattern');

    // Activity Log
    Route::get('activity-log', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('activity-log/{activity}', [\App\Http\Controllers\ActivityLogController::class, 'show'])->name('activity-log.show');

    // Activity Log Export
    Route::post('activity-log/export/all', [\App\Http\Controllers\ActivityLogController::class, 'exportAll'])->name('activity-log.export.all');
    Route::post('activity-log/export/filtered', [\App\Http\Controllers\ActivityLogController::class, 'exportFiltered'])->name('activity-log.export.filtered');
    Route::post('activity-log/export/validate', [\App\Http\Controllers\ActivityLogController::class, 'validateExport'])->name('activity-log.export.validate');
    Route::get('activity-log/export/columns', [\App\Http\Controllers\ActivityLogController::class, 'getExportColumns'])->name('activity-log.export.columns');

    // Geography Management
    Route::prefix('geography')->name('geography.')->group(function () {
        Route::get('countries', [\App\Http\Controllers\Geography\CountryController::class, 'index'])->name('countries');
        Route::get('countries/create', [\App\Http\Controllers\Geography\CountryController::class, 'create'])->name('countries.create');
        Route::post('countries', [\App\Http\Controllers\Geography\CountryController::class, 'store'])->name('countries.store');
        Route::get('countries/{country}', [\App\Http\Controllers\Geography\CountryController::class, 'show'])->name('countries.show');
        Route::get('countries/{country}/edit', [\App\Http\Controllers\Geography\CountryController::class, 'edit'])->name('countries.edit');
        Route::put('countries/{country}', [\App\Http\Controllers\Geography\CountryController::class, 'update'])->name('countries.update');
        Route::delete('countries/{country}', [\App\Http\Controllers\Geography\CountryController::class, 'destroy'])->name('countries.destroy');

        Route::get('provinces', [\App\Http\Controllers\Geography\ProvinceController::class, 'index'])->name('provinces');
        Route::get('provinces/create', [\App\Http\Controllers\Geography\ProvinceController::class, 'create'])->name('provinces.create');
        Route::post('provinces', [\App\Http\Controllers\Geography\ProvinceController::class, 'store'])->name('provinces.store');
        Route::get('provinces/{province}', [\App\Http\Controllers\Geography\ProvinceController::class, 'show'])->name('provinces.show');
        Route::get('provinces/{province}/edit', [\App\Http\Controllers\Geography\ProvinceController::class, 'edit'])->name('provinces.edit');
        Route::put('provinces/{province}', [\App\Http\Controllers\Geography\ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('provinces/{province}', [\App\Http\Controllers\Geography\ProvinceController::class, 'destroy'])->name('provinces.destroy');

        Route::get('cities', [\App\Http\Controllers\Geography\CityController::class, 'index'])->name('cities');
        Route::get('cities/create', [\App\Http\Controllers\Geography\CityController::class, 'create'])->name('cities.create');
        Route::post('cities', [\App\Http\Controllers\Geography\CityController::class, 'store'])->name('cities.store');
        Route::get('cities/{city}', [\App\Http\Controllers\Geography\CityController::class, 'show'])->name('cities.show');
        Route::get('cities/{city}/edit', [\App\Http\Controllers\Geography\CityController::class, 'edit'])->name('cities.edit');
        Route::put('cities/{city}', [\App\Http\Controllers\Geography\CityController::class, 'update'])->name('cities.update');
        Route::delete('cities/{city}', [\App\Http\Controllers\Geography\CityController::class, 'destroy'])->name('cities.destroy');

        Route::get('districts', [\App\Http\Controllers\Geography\DistrictController::class, 'index'])->name('districts');
        Route::get('districts/create', [\App\Http\Controllers\Geography\DistrictController::class, 'create'])->name('districts.create');
        Route::post('districts', [\App\Http\Controllers\Geography\DistrictController::class, 'store'])->name('districts.store');
        Route::get('districts/{district}', [\App\Http\Controllers\Geography\DistrictController::class, 'show'])->name('districts.show');
        Route::get('districts/{district}/edit', [\App\Http\Controllers\Geography\DistrictController::class, 'edit'])->name('districts.edit');
        Route::put('districts/{district}', [\App\Http\Controllers\Geography\DistrictController::class, 'update'])->name('districts.update');
        Route::delete('districts/{district}', [\App\Http\Controllers\Geography\DistrictController::class, 'destroy'])->name('districts.destroy');

        Route::get('villages', [\App\Http\Controllers\Geography\VillageController::class, 'index'])->name('villages');
        Route::get('villages/create', [\App\Http\Controllers\Geography\VillageController::class, 'create'])->name('villages.create');
        Route::post('villages', [\App\Http\Controllers\Geography\VillageController::class, 'store'])->name('villages.store');
        Route::get('villages/{village}', [\App\Http\Controllers\Geography\VillageController::class, 'show'])->name('villages.show');
        Route::get('villages/{village}/edit', [\App\Http\Controllers\Geography\VillageController::class, 'edit'])->name('villages.edit');
        Route::put('villages/{village}', [\App\Http\Controllers\Geography\VillageController::class, 'update'])->name('villages.update');
        Route::delete('villages/{village}', [\App\Http\Controllers\Geography\VillageController::class, 'destroy'])->name('villages.destroy');
    });

});

require __DIR__.'/webs/settings.php';
require __DIR__.'/webs/oauth.php';
require __DIR__.'/webs/auth.php';
