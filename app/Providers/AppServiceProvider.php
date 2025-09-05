<?php

namespace App\Providers;

use App\Models\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\RateLimitService::class);
        $this->app->singleton(\App\Services\LiveKitService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Use our custom Client model
        Passport::useClientModel(Client::class);

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::tokensCan([
            'openid' => 'OpenID Connect',
            'profile' => 'Profile Information',
            'email' => 'Email Address',
            'https://api.yourcompany.com/auth/organization.readonly' => 'Organization Read Access',
            'https://api.yourcompany.com/auth/organization' => 'Organization Management',
            'https://api.yourcompany.com/auth/organization.members' => 'Organization Members',
            'https://api.yourcompany.com/auth/organization.admin' => 'Organization Administration',
            'https://api.yourcompany.com/auth/userinfo.profile' => 'User Profile Access',
            'https://api.yourcompany.com/auth/userinfo.email' => 'User Email Access',
            'https://api.yourcompany.com/auth/user.modify' => 'User Profile Management',
            'https://api.yourcompany.com/auth/analytics.readonly' => 'Analytics Read Access',
            'https://api.yourcompany.com/auth/webhooks' => 'Webhooks Management',
            'https://api.yourcompany.com/auth/platform.full' => 'Full Platform Access',
            'https://api.yourcompany.com/auth/mobile' => 'Mobile Application Access',
            'offline_access' => 'Offline Access',
        ]);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('oauth_token', function (Request $request) {
            $clientId = $request->input('client_id');
            $key = $clientId ? "oauth_client:$clientId" : 'oauth_ip:'.$request->ip();

            return Limit::perMinute(10)->by($key)->response(function () {
                return response()->json([
                    'error' => 'rate_limit_exceeded',
                    'error_description' => 'Too many token requests. Please try again later.',
                ], 429);
            });
        });

        RateLimiter::for('oauth_authorize', function (Request $request) {
            $clientId = $request->input('client_id');
            $key = $clientId ? "oauth_client:$clientId" : 'oauth_ip:'.$request->ip();

            return Limit::perMinute(30)->by($key)->response(function () {
                return response()->json([
                    'error' => 'rate_limit_exceeded',
                    'error_description' => 'Too many authorization requests. Please try again later.',
                ], 429);
            });
        });

        RateLimiter::for('oauth_userinfo', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())->response(function () {
                return response()->json([
                    'error' => 'rate_limit_exceeded',
                    'error_description' => 'Too many userinfo requests. Please try again later.',
                ], 429);
            });
        });
    }
}
