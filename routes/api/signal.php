<?php

use App\Http\Controllers\Api\Chat\SignalProtocolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Signal Protocol API Routes
|--------------------------------------------------------------------------
|
| Routes for Signal Protocol E2EE functionality including:
| - X3DH key agreement (prekey bundle management)
| - Double Ratchet session management
| - Message encryption/decryption
| - Identity verification
| - Key rotation and maintenance
|
*/

Route::middleware(['auth:sanctum', 'verified'])->prefix('v1/signal')->group(function () {
    
    // X3DH Prekey Bundle Management
    Route::post('/upload-bundle', [SignalProtocolController::class, 'uploadPreKeyBundle'])
        ->name('signal.upload-bundle');
    
    Route::get('/prekey-bundle/{userId}', [SignalProtocolController::class, 'getPreKeyBundle'])
        ->name('signal.get-prekey-bundle')
        ->where('userId', '[0-9]+');

    // Message Sending
    Route::post('/messages/send', [SignalProtocolController::class, 'sendSignalMessage'])
        ->name('signal.send-message');

    // Session Management
    Route::get('/sessions/info', [SignalProtocolController::class, 'getSessionInfo'])
        ->name('signal.session-info');
    
    Route::post('/sessions/rotate-keys', [SignalProtocolController::class, 'rotateSessionKeys'])
        ->name('signal.rotate-session-keys');

    // Identity Verification
    Route::post('/identity/verify', [SignalProtocolController::class, 'verifyUserIdentity'])
        ->name('signal.verify-identity');

    // Statistics and Monitoring
    Route::get('/statistics', [SignalProtocolController::class, 'getStatistics'])
        ->name('signal.statistics');

    // Health Check
    Route::get('/health', [SignalProtocolController::class, 'getHealth'])
        ->name('signal.health');

    // Maintenance (Admin only)
    Route::middleware('can:admin-signal-protocol')->group(function () {
        Route::post('/maintenance/cleanup', [SignalProtocolController::class, 'performMaintenance'])
            ->name('signal.maintenance.cleanup');
        
        Route::get('/admin/stats', [SignalProtocolController::class, 'getAdminStats'])
            ->name('signal.admin.stats');
        
        Route::get('/admin/users-needing-maintenance', [SignalProtocolController::class, 'getUsersNeedingMaintenance'])
            ->name('signal.admin.users-maintenance');
    });
});