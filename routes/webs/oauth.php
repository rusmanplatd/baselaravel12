<?php

use App\Http\Controllers\OAuth\ClientController;
use App\Http\Controllers\OAuth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('oauth')->group(function () {
    Route::get('authorize', [OAuthController::class, 'handleAuthorize'])
        ->middleware(['throttle:oauth_authorize'])
        ->name('oauth.authorize');
    Route::post('authorize', [OAuthController::class, 'approve'])
        ->middleware(['throttle:oauth_authorize'])
        ->name('oauth.approve');
    Route::delete('authorize', [OAuthController::class, 'deny'])
        ->middleware(['throttle:oauth_authorize'])
        ->name('oauth.deny');

    Route::get('userinfo', [OAuthController::class, 'userinfo'])
        ->middleware(['auth:api', 'throttle:oauth_userinfo'])
        ->name('oauth.userinfo');

    Route::get('introspect', [OAuthController::class, 'introspect'])
        ->middleware(['throttle:oauth_token'])
        ->name('oauth.introspect');
    Route::get('jwks', [OAuthController::class, 'jwks'])->name('oauth.jwks');
    Route::post('revoke', [OAuthController::class, 'revoke'])
        ->middleware(['throttle:oauth_token'])
        ->name('oauth.revoke');

    Route::middleware('auth:web')->group(function () {
        Route::resource('clients', ClientController::class);
        Route::post('clients/{client}/regenerate-secret', [ClientController::class, 'regenerateSecret'])
            ->name('clients.regenerate-secret');
        Route::get('analytics', [\App\Http\Controllers\OAuth\AnalyticsController::class, 'dashboard'])
            ->name('oauth.analytics');
        Route::get('analytics/chart-data', [\App\Http\Controllers\OAuth\AnalyticsController::class, 'chartData'])
            ->name('oauth.analytics.chart-data');
        Route::get('analytics/{client}', [\App\Http\Controllers\OAuth\AnalyticsController::class, 'client'])
            ->name('oauth.analytics.client');
    });

    // Public client registration removed - all clients must be organization-associated
});

Route::get('.well-known/oauth-authorization-server', [OAuthController::class, 'discovery'])
    ->name('oauth.discovery');

Route::prefix('oidc')->group(function () {
    Route::post('token', [\App\Http\Controllers\OAuth\OidcController::class, 'token'])
        ->middleware(['throttle:oauth_token'])
        ->name('oidc.token');
    Route::get('userinfo', [\App\Http\Controllers\OAuth\OidcController::class, 'userinfo'])
        ->middleware(['throttle:oauth_userinfo'])
        ->name('oidc.userinfo');
    Route::get('jwks', [\App\Http\Controllers\OAuth\OidcController::class, 'jwks'])->name('oidc.jwks');
});

Route::get('.well-known/openid_configuration', [\App\Http\Controllers\OAuth\OidcController::class, 'discovery'])
    ->name('oidc.discovery');

Route::prefix('oauth/test')->group(function () {
    Route::get('/', [\App\Http\Controllers\OAuth\TestController::class, 'client'])->name('oauth.test.client');
    Route::get('callback', [\App\Http\Controllers\OAuth\TestController::class, 'callback'])->name('oauth.test.callback');
});
