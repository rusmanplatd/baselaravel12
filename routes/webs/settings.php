<?php

use App\Http\Controllers\Security\SessionController;
use App\Http\Controllers\Security\TrustedDeviceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Avatar upload routes
    Route::post('settings/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('settings/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');

    Route::get('settings/security', function () {
        $user = auth()->user();
        $mfaSettings = $user->mfaSettings;
        $passkeys = $user->passkeys()->select(['id', 'name', 'created_at'])->get();

        return Inertia::render('settings/security', [
            'mfaEnabled' => $mfaSettings?->hasMfaEnabled() ?? false,
            'hasBackupCodes' => $mfaSettings?->hasBackupCodes() ?? false,
            'passkeys' => $passkeys,
        ]);
    })->name('security');

    // Trusted Devices
    Route::prefix('security')->name('security.')->group(function () {
        // Trust device page (for new device verification)
        Route::get('trust-device', [TrustedDeviceController::class, 'show'])->name('trust-device');

        // Trusted devices management
        Route::get('trusted-devices', [TrustedDeviceController::class, 'index'])->name('trusted-devices');
        Route::post('trusted-devices', [TrustedDeviceController::class, 'store'])->name('trusted-devices.store');
        Route::put('trusted-devices/{device}', [TrustedDeviceController::class, 'update'])->name('trusted-devices.update');
        Route::delete('trusted-devices/{device}', [TrustedDeviceController::class, 'destroy'])->name('trusted-devices.destroy');
        Route::post('trusted-devices/revoke-all', [TrustedDeviceController::class, 'revokeAll'])->name('trusted-devices.revoke-all');
        Route::post('trusted-devices/cleanup', [TrustedDeviceController::class, 'cleanup'])->name('trusted-devices.cleanup');

        // Session management
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions');
        Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
        Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        Route::post('sessions/terminate-all', [SessionController::class, 'terminateAll'])->name('sessions.terminate-all');
        Route::post('sessions/terminate-others', [SessionController::class, 'terminateAllOthers'])->name('sessions.terminate-others');
        Route::get('sessions-stats', [SessionController::class, 'stats'])->name('sessions.stats');
        Route::get('sessions-alerts', [SessionController::class, 'alerts'])->name('sessions.alerts');
        Route::post('sessions/cleanup', [SessionController::class, 'cleanup'])->name('sessions.cleanup');
    });
});
