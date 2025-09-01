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

// Activity logs API (with authentication)
Route::middleware(['auth:api'])->group(function () {
    Route::get('activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);
    Route::get('activity-logs/{activity}', [\App\Http\Controllers\Api\ActivityLogController::class, 'show']);
});

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

    // Geo API endpoints (public reference data)
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
        Route::get('districts/{district}/villages', [\App\Http\Controllers\Api\Geo\VillageController::class, 'byDistrict'])->name('villages.by-district');

        // Resource routes
        Route::apiResource('countries', \App\Http\Controllers\Api\Geo\CountryController::class);
        Route::apiResource('provinces', \App\Http\Controllers\Api\Geo\ProvinceController::class);
        Route::apiResource('cities', \App\Http\Controllers\Api\Geo\CityController::class);
        Route::apiResource('districts', \App\Http\Controllers\Api\Geo\DistrictController::class);
        Route::apiResource('villages', \App\Http\Controllers\Api\Geo\VillageController::class);
    });
});

// Authenticated API routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    // OAuth Client Management API
    Route::prefix('oauth')->name('api.oauth.')->group(function () {
        Route::apiResource('clients', \App\Http\Controllers\OAuth\ClientController::class);
        Route::post('clients/{client}/regenerate-secret', [\App\Http\Controllers\OAuth\ClientController::class, 'regenerateSecret'])
            ->name('clients.regenerate-secret');
    });
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

    // Chat API endpoints
    Route::prefix('chat')->name('api.chat.')->group(function () {
        // Bulk operations (must come before resource routes to avoid conflicts)
        Route::post('conversations/bulk-export', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'bulkExport'])->name('conversations.bulk-export');
        Route::patch('conversations/bulk-update-encryption', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'bulkUpdateEncryption'])->name('conversations.bulk-update-encryption');

        // Conversations
        Route::apiResource('conversations', \App\Http\Controllers\Api\Chat\ConversationController::class)
            ->middleware('throttle:60,1');
        Route::post('conversations/{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'addParticipant'])
            ->name('conversations.participants.add')
            ->middleware(['throttle:20,1', \App\Http\Middleware\ChatRateLimit::class.':conversation']);
        Route::delete('conversations/{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'removeParticipant'])
            ->name('conversations.participants.remove')
            ->middleware('throttle:20,1');
        Route::delete('conversations/{conversation}/participants/{user}', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'removeParticipantById'])
            ->name('conversations.participants.remove.by-id')
            ->middleware('throttle:20,1');

        // Group management routes
        Route::get('conversations/{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'getParticipants'])
            ->name('conversations.participants.list')
            ->middleware('throttle:60,1');
        Route::put('conversations/{conversation}/participants/role', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'updateParticipantRole'])
            ->name('conversations.participants.role')
            ->middleware('throttle:20,1');
        Route::put('conversations/{conversation}/settings', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'updateGroupSettings'])
            ->name('conversations.settings')
            ->middleware('throttle:10,1');
        Route::post('conversations/{conversation}/invite-link', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'generateInviteLink'])
            ->name('conversations.invite-link')
            ->middleware('throttle:5,1');
        Route::post('join/{inviteCode}', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'joinByInvite'])
            ->name('conversations.join-invite')
            ->middleware('throttle:10,1');

        // Messages
        Route::get('conversations/{conversation}/messages', [\App\Http\Controllers\Api\Chat\MessageController::class, 'index'])
            ->name('conversations.messages.index')
            ->middleware('throttle:100,1');
        Route::post('conversations/{conversation}/messages', [\App\Http\Controllers\Api\Chat\MessageController::class, 'store'])
            ->name('conversations.messages.store')
            ->middleware([\App\Http\Middleware\ChatRateLimit::class.':message', \App\Http\Middleware\ChatSpamProtection::class]);
        Route::post('messages', [\App\Http\Controllers\Api\Chat\MessageController::class, 'createMessage'])
            ->name('messages.create')
            ->middleware([\App\Http\Middleware\ChatRateLimit::class.':message', \App\Http\Middleware\ChatSpamProtection::class]);
        Route::get('messages/{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'show'])
            ->name('messages.show')
            ->middleware('throttle:100,1');
        Route::put('messages/{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'update'])
            ->name('messages.update')
            ->middleware([\App\Http\Middleware\ChatRateLimit::class.':message', \App\Http\Middleware\ChatSpamProtection::class]);
        Route::delete('messages/{message}', [\App\Http\Controllers\Api\Chat\MessageController::class, 'destroy'])
            ->name('messages.destroy')
            ->middleware('throttle:30,1');
        Route::post('conversations/{conversation}/mark-read', [\App\Http\Controllers\Api\Chat\MessageController::class, 'markAsRead'])
            ->name('conversations.mark-read')
            ->middleware('throttle:60,1');

        // Thread routes
        Route::get('conversations/{conversation}/messages/{message}/thread', [\App\Http\Controllers\Api\Chat\MessageController::class, 'getThread'])
            ->name('messages.thread')
            ->middleware('throttle:100,1');
        Route::get('conversations/{conversation}/messages/threads', [\App\Http\Controllers\Api\Chat\MessageController::class, 'indexWithThreads'])
            ->name('conversations.messages.threads')
            ->middleware('throttle:100,1');

        // Files
        Route::post('conversations/{conversation}/upload', [\App\Http\Controllers\Api\Chat\FileController::class, 'upload'])
            ->name('conversations.upload')
            ->middleware(\App\Http\Middleware\ChatRateLimit::class.':file');
        Route::get('files/{encodedPath}/download', [\App\Http\Controllers\Api\Chat\FileController::class, 'download'])
            ->name('files.download')
            ->middleware('throttle:100,1');
        Route::delete('messages/{message}/file', [\App\Http\Controllers\Api\Chat\FileController::class, 'delete'])
            ->name('messages.file.delete')
            ->middleware('throttle:30,1');

        // Presence and typing
        Route::post('conversations/{conversation}/typing', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'typing'])
            ->name('conversations.typing')
            ->middleware(\App\Http\Middleware\ChatRateLimit::class.':typing');
        Route::get('conversations/{conversation}/typing', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'getTyping'])
            ->name('conversations.typing.get')
            ->middleware('throttle:30,1');
        Route::post('presence/status', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'updateStatus'])
            ->name('presence.status.update')
            ->middleware('throttle:10,1');
        Route::get('presence/status', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'getStatus'])
            ->name('presence.status.get')
            ->middleware('throttle:60,1');
        Route::post('presence/heartbeat', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'heartbeat'])
            ->name('presence.heartbeat')
            ->middleware('throttle:120,1');

        // Encryption
        Route::post('encryption/generate-keypair', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'generateKeyPair'])->name('encryption.generate-keypair');
        Route::post('encryption/register-key', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'registerKey'])->name('encryption.register-key');
        Route::get('conversations/{conversation}/encryption-key', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getConversationKey'])->name('conversations.encryption-key');
        Route::post('conversations/{conversation}/rotate-key', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'rotateConversationKey'])->name('conversations.rotate-key');
        Route::post('conversations/{conversation}/setup-encryption', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'setupConversationEncryption'])->name('conversations.setup-encryption');

        // Conversation-level encryption management (all conversations are always encrypted in E2EE)
        Route::get('conversations/{conversation}/encryption/status', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'getEncryptionStatus'])
            ->name('conversations.encryption.status')
            ->middleware('throttle:60,1');
        Route::post('encryption/verify', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'verifyMessage'])->name('encryption.verify');
        Route::post('encryption/test', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'testEncryption'])->name('encryption.test');

        // Encryption key management
        Route::post('encryption/keys', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'generateUserKeys'])->name('encryption.keys.generate');
        Route::get('encryption/keys', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getUserKeys'])->name('encryption.keys.list');
        Route::put('encryption/keys/{encryptionKey}', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'updateUserKey'])->name('encryption.keys.update');
        Route::post('encryption-keys/{encryptionKey}/validate', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'validateKey'])->name('encryption.keys.validate');
        Route::post('encryption-keys/{encryptionKey}/recover', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'recoverKey'])->name('encryption.keys.recover');

        // Enhanced E2EE endpoints
        Route::post('encryption/backup/create', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'createBackup'])->name('encryption.backup.create');
        Route::post('encryption/backup/restore', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'restoreBackup'])->name('encryption.backup.restore');
        Route::get('encryption/health', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getEncryptionHealth'])->name('encryption.health');
        Route::post('encryption/bulk-decrypt', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'bulkDecryptMessages'])->name('encryption.bulk-decrypt');
        Route::get('encryption/key-usage-stats', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getKeyUsageStats'])->name('encryption.key-usage-stats');
        Route::get('encryption/detect-anomalies', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'detectAnomalies'])->name('encryption.detect-anomalies');
        Route::get('conversations/{conversation}/encryption/audit-log', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getAuditLog'])->name('conversations.encryption.audit-log');

        // Multi-Device E2EE endpoints
        Route::post('conversations/{conversation}/setup-encryption-multidevice', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'setupConversationEncryptionMultiDevice'])->name('conversations.setup-encryption-multidevice');
        Route::post('conversations/setup-encryption', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'setupConversationEncryptionGeneral'])->name('conversations.setup-encryption');
        Route::post('conversations/{conversation}/rotate-key-multidevice', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'rotateConversationKeyMultiDevice'])->name('conversations.rotate-key-multidevice');
        Route::post('conversations/{conversation}/device-key', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getDeviceConversationKey'])->name('conversations.device-key');
        Route::post('key-shares/{keyShare}/accept', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'acceptKeyShare'])->name('key-shares.accept');
        Route::delete('devices/{device}/revoke-access', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'revokeDeviceAccess'])->name('devices.revoke-access');
        Route::get('encryption/multidevice-health', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getMultiDeviceHealth'])->name('encryption.multidevice-health');

        // Device Management
        Route::apiResource('devices', \App\Http\Controllers\Api\DeviceController::class);
        Route::post('devices/{device}/trust', [\App\Http\Controllers\Api\DeviceController::class, 'trust'])->name('devices.trust');
        Route::post('devices/{device}/share-keys', [\App\Http\Controllers\Api\DeviceController::class, 'shareKeys'])->name('devices.share-keys');
        Route::get('devices/{device}/key-shares', [\App\Http\Controllers\Api\DeviceController::class, 'getKeyShares'])->name('devices.key-shares');
        Route::post('devices/{device}/verify', [\App\Http\Controllers\Api\DeviceController::class, 'verifyDevice'])->name('devices.verify');
        Route::post('devices/{device}/verification/initiate', [\App\Http\Controllers\Api\DeviceController::class, 'initiateVerification'])->name('devices.verification.initiate');
        Route::post('devices/{device}/verification/complete', [\App\Http\Controllers\Api\DeviceController::class, 'completeVerification'])->name('devices.verification.complete');
        Route::post('devices/{device}/verification/qr', [\App\Http\Controllers\Api\DeviceController::class, 'generateVerificationQRCode'])->name('devices.verification.qr');
        Route::get('devices/{device}/security-report', [\App\Http\Controllers\Api\DeviceController::class, 'getSecurityReport'])->name('devices.security-report');
        Route::post('devices/{device}/rotate-keys', [\App\Http\Controllers\Api\DeviceController::class, 'rotateKeys'])->name('devices.rotate-keys');
        Route::post('devices/{device}/sync-message', [\App\Http\Controllers\Api\DeviceController::class, 'syncMessage'])->name('devices.sync-message');
        Route::post('devices/{device}/request-messages', [\App\Http\Controllers\Api\DeviceController::class, 'requestMessages'])->name('devices.request-messages');
        Route::post('devices/{device}/unlock', [\App\Http\Controllers\Api\DeviceController::class, 'unlockDevice'])->name('devices.unlock');

        // Message Reactions
        Route::get('messages/{message}/reactions', [\App\Http\Controllers\Api\Chat\MessageReactionController::class, 'index'])->name('messages.reactions.index');
        Route::post('messages/{message}/reactions', [\App\Http\Controllers\Api\Chat\MessageReactionController::class, 'store'])->name('messages.reactions.store');
        Route::post('messages/{message}/reactions/toggle', [\App\Http\Controllers\Api\Chat\MessageReactionController::class, 'toggle'])->name('messages.reactions.toggle');
        Route::delete('messages/{message}/reactions/{emoji}', [\App\Http\Controllers\Api\Chat\MessageReactionController::class, 'destroy'])->name('messages.reactions.destroy');

        // Read Receipts
        Route::get('messages/{message}/read-receipts', [\App\Http\Controllers\Api\Chat\MessageReadReceiptController::class, 'index'])->name('messages.read-receipts.index');
        Route::post('messages/{message}/read-receipts', [\App\Http\Controllers\Api\Chat\MessageReadReceiptController::class, 'store'])->name('messages.read-receipts.store');
        Route::post('read-receipts/mark-multiple', [\App\Http\Controllers\Api\Chat\MessageReadReceiptController::class, 'markMultipleAsRead'])->name('read-receipts.mark-multiple');
        Route::post('conversations/{conversation}/mark-as-read', [\App\Http\Controllers\Api\Chat\MessageReadReceiptController::class, 'markConversationAsRead'])->name('conversations.mark-as-read');

        // Typing Indicators
        Route::post('conversations/{conversation}/typing', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'setTyping'])->name('conversations.typing');
        Route::delete('conversations/{conversation}/typing', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'stopTyping'])->name('conversations.typing.stop');
        Route::get('conversations/{conversation}/typing', [\App\Http\Controllers\Api\Chat\PresenceController::class, 'getTypingUsers'])->name('conversations.typing.get');

        // Channels (search route must come before resource route to avoid conflicts)
        Route::get('channels/search', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'searchChannels'])
            ->name('channels.search')
            ->middleware('throttle:30,1');

        Route::apiResource('channels', \App\Http\Controllers\Api\Chat\ChannelController::class)
            ->middleware('throttle:60,1');
        Route::post('channels/{channel}/join', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'join'])
            ->name('channels.join')
            ->middleware('throttle:20,1');
        Route::post('channels/{channel}/leave', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'leave'])
            ->name('channels.leave')
            ->middleware('throttle:20,1');
        Route::post('channels/{channel}/invite', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'inviteUser'])
            ->name('channels.invite')
            ->middleware('throttle:10,1');
        Route::get('channels/{channel}/members', [\App\Http\Controllers\Api\Chat\ChannelController::class, 'getMembers'])
            ->name('channels.members')
            ->middleware('throttle:60,1');
    });

    // Key Recovery and Backup Management
    Route::prefix('key-recovery')->name('api.key-recovery.')->middleware(['auth:api', 'throttle:30,1'])->group(function () {
        Route::post('backup', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'createBackup'])->name('backup.create');
        Route::post('backup/incremental', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'createIncrementalBackup'])->name('backup.incremental');
        Route::post('backup/restore', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'restoreFromBackup'])->name('backup.restore');
        Route::post('backup/load', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'loadBackup'])->name('backup.load');
        Route::post('backup/validate', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'validateBackup'])->name('backup.validate');
        Route::get('status', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'getRecoveryStatus'])->name('status');
        Route::post('emergency-recovery', [\App\Http\Controllers\Api\KeyRecoveryController::class, 'performEmergencyRecovery'])
            ->name('emergency-recovery')
            ->middleware('throttle:5,1'); // Stricter rate limiting for emergency recovery
    });

    // Quantum cryptography API endpoints
    Route::prefix('quantum')->name('api.quantum.')->group(function () {
        Route::get('health', [\App\Http\Controllers\Api\QuantumController::class, 'healthCheck'])
            ->name('health');
        Route::post('generate-keypair', [\App\Http\Controllers\Api\QuantumController::class, 'generateKeyPair'])
            ->name('generate-keypair')
            ->middleware('throttle:10,1'); // Rate limit key generation
        Route::post('devices/register', [\App\Http\Controllers\Api\QuantumController::class, 'registerQuantumDevice'])
            ->name('devices.register')
            ->middleware('throttle:5,1'); // Rate limit device registration
        Route::get('devices/capabilities', [\App\Http\Controllers\Api\QuantumController::class, 'getDeviceCapabilities'])
            ->name('devices.capabilities');
        Route::put('devices/{deviceId}/capabilities', [\App\Http\Controllers\Api\QuantumController::class, 'updateDeviceCapabilities'])
            ->name('devices.update-capabilities')
            ->middleware('throttle:10,1');
        Route::post('conversations/{conversation}/negotiate-algorithm', [\App\Http\Controllers\Api\QuantumController::class, 'negotiateAlgorithm'])
            ->name('conversations.negotiate-algorithm')
            ->middleware('throttle:20,1');
        Route::post('performance-test', [\App\Http\Controllers\Api\QuantumController::class, 'performanceTest'])
            ->name('performance-test')
            ->middleware('throttle:10,1');
        Route::post('cross-platform-compatibility', [\App\Http\Controllers\Api\QuantumController::class, 'crossPlatformCompatibility'])
            ->name('cross-platform-compatibility')
            ->middleware('throttle:10,1');

        // Device management endpoints
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::post('bulk-upgrade', [\App\Http\Controllers\Api\QuantumController::class, 'bulkDeviceUpgrade'])
                ->name('bulk-upgrade')
                ->middleware('throttle:5,1');
            Route::get('readiness-assessment', [\App\Http\Controllers\Api\QuantumController::class, 'deviceReadinessAssessment'])
                ->name('readiness-assessment');
            Route::put('{device}/security-level', [\App\Http\Controllers\Api\QuantumController::class, 'updateDeviceSecurityLevel'])
                ->name('security-level')
                ->middleware('throttle:10,1');
            Route::post('{device}/compatibility-check', [\App\Http\Controllers\Api\QuantumController::class, 'checkDeviceCompatibility'])
                ->name('compatibility-check');
            Route::post('{device}/migrate', [\App\Http\Controllers\Api\QuantumController::class, 'migrateDevice'])
                ->name('migrate')
                ->middleware('throttle:5,1');
            Route::post('validate-capabilities', [\App\Http\Controllers\Api\QuantumController::class, 'validateCapabilities'])
                ->name('validate-capabilities');
            Route::get('{device}/performance', [\App\Http\Controllers\Api\QuantumController::class, 'getDevicePerformance'])
                ->name('performance');
            Route::post('{device}/verify-capabilities', [\App\Http\Controllers\Api\QuantumController::class, 'verifyDeviceCapabilities'])
                ->name('verify-capabilities');
            Route::post('{device}/health-check', [\App\Http\Controllers\Api\QuantumController::class, 'deviceHealthCheck'])
                ->name('health-check');
        });

        // Quantum migration endpoints
        Route::prefix('migration')->name('migration.')->group(function () {
            Route::post('assess', [\App\Http\Controllers\Api\QuantumController::class, 'assessMigration'])
                ->name('assess')
                ->middleware('throttle:10,1');
            Route::post('start', [\App\Http\Controllers\Api\QuantumController::class, 'startMigration'])
                ->name('start')
                ->middleware('throttle:5,1');
            Route::get('{migrationId}/status', [\App\Http\Controllers\Api\QuantumController::class, 'getMigrationStatus'])
                ->name('status')
                ->middleware('throttle:30,1');
            Route::post('{migrationId}/cancel', [\App\Http\Controllers\Api\QuantumController::class, 'cancelMigration'])
                ->name('cancel')
                ->middleware('throttle:10,1');
            Route::post('check-compatibility', [\App\Http\Controllers\Api\QuantumController::class, 'checkCompatibility'])
                ->name('check-compatibility')
                ->middleware('throttle:15,1');
        });
    });

    // User API endpoints
    Route::prefix('users')->name('api.users.')->group(function () {
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
});
