<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\WebAuthnController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // WebAuthn authentication routes
    Route::get('webauthn/options', [WebAuthnController::class, 'authenticateOptions'])
        ->name('webauthn.authenticate.options');

    Route::post('webauthn/authenticate', [WebAuthnController::class, 'authenticate'])
        ->name('webauthn.authenticate');

    // Google OAuth routes
    Route::get('google', [GoogleController::class, 'redirect'])
        ->name('google');

    Route::get('google/callback', [GoogleController::class, 'callback'])
        ->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // MFA routes
    Route::prefix('mfa')->name('mfa.')->group(function () {
        Route::get('setup', [MfaController::class, 'show'])->name('setup');
        Route::post('enable', [MfaController::class, 'enable'])->name('enable');
        Route::post('confirm', [MfaController::class, 'confirm'])->name('confirm');
        Route::post('disable', [MfaController::class, 'disable'])->name('disable');
        Route::post('verify', [MfaController::class, 'verify'])->name('verify');
        Route::post('backup-codes/regenerate', [MfaController::class, 'regenerateBackupCodes'])->name('backup-codes.regenerate');
    });

    // WebAuthn management routes
    Route::prefix('webauthn')->name('webauthn.')->group(function () {
        Route::get('register/options', [WebAuthnController::class, 'registerOptions'])->name('register.options');
        Route::post('register', [WebAuthnController::class, 'register'])->name('register');
        Route::get('passkeys', [WebAuthnController::class, 'list'])->name('list');
        Route::delete('passkeys/{passkey}', [WebAuthnController::class, 'delete'])->name('delete');
    });
});
