<?php

use App\Http\Controllers\Api\MfaApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\UserSecurityController;
use App\Http\Controllers\Api\WebAuthnApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth']);

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
});

// Authenticated API routes
Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
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
        // Conversations
        Route::apiResource('conversations', \App\Http\Controllers\Api\Chat\ConversationController::class)
            ->middleware('throttle:60,1');
        Route::post('conversations/{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'addParticipant'])
            ->name('conversations.participants.add')
            ->middleware(['throttle:20,1', \App\Http\Middleware\ChatRateLimit::class.':conversation']);
        Route::delete('conversations/{conversation}/participants', [\App\Http\Controllers\Api\Chat\ConversationController::class, 'removeParticipant'])
            ->name('conversations.participants.remove')
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
        Route::post('encryption/verify', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'verifyMessage'])->name('encryption.verify');
        Route::post('encryption/test', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'testEncryption'])->name('encryption.test');

        // Enhanced E2EE endpoints
        Route::post('encryption/backup/create', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'createBackup'])->name('encryption.backup.create');
        Route::post('encryption/backup/restore', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'restoreBackup'])->name('encryption.backup.restore');
        Route::get('encryption/health', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'getEncryptionHealth'])->name('encryption.health');
        Route::post('encryption/bulk-decrypt', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'bulkDecryptMessages'])->name('encryption.bulk-decrypt');

        // Multi-Device E2EE endpoints
        Route::post('conversations/{conversation}/setup-encryption-multidevice', [\App\Http\Controllers\Api\Chat\EncryptionController::class, 'setupConversationEncryptionMultiDevice'])->name('conversations.setup-encryption-multidevice');
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
