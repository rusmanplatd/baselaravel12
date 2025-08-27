<?php

use App\Http\Controllers\OAuthClientController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// OAuth Client Routes
Route::prefix('oauth')->group(function () {
    // OAuth dashboard and flow initiation
    Route::get('/', [OAuthClientController::class, 'index'])->name('oauth.index');
    Route::post('authorize', [OAuthClientController::class, 'startAuthorization'])->name('oauth.authorize');
    
    // OAuth callback handler
    Route::get('callback', [OAuthClientController::class, 'callback'])->name('oauth.callback');
    
    // Token operations
    Route::post('refresh', [OAuthClientController::class, 'refresh'])->name('oauth.refresh');
    Route::post('revoke', [OAuthClientController::class, 'revoke'])->name('oauth.revoke');
    
    // Discovery endpoints
    Route::get('discovery', [OAuthClientController::class, 'discovery'])->name('oauth.discovery');
});

// Legacy routes for backward compatibility
Route::get('/oauth/test', function () {
    return redirect()->route('oauth.index');
});
