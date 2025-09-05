<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            \App\Http\Middleware\SetPermissionTeamContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            // Ensure API routes don't get the default auth middleware
        ]);

        // Use Laravel's standard authentication middleware

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'mfa.verified' => \App\Http\Middleware\EnsureMfaVerified::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'permission.check' => \App\Http\Middleware\CheckPermissionMiddleware::class,
            'permission.dynamic' => \App\Http\Middleware\DynamicPermissionMiddleware::class,
            'chat.permission' => \App\Http\Middleware\ChatPermissionMiddleware::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'organization.access' => \App\Http\Middleware\CheckOrganizationAccess::class,
            'organization.context' => \App\Http\Middleware\OrganizationContext::class,
            'rate_limit' => \App\Http\Middleware\RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            // Enhanced error handling with activity logging
            if ($request->is('api/*')) {
                return null; // Let default API error handling work
            }

            // Log the exception for monitoring
            if (app()->bound('ActivityLogService')) {
                try {
                    \App\Services\ActivityLogService::logSystem('exception_occurred', 'Application exception: '.get_class($e), [
                        'exception_class' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'url' => $request->url(),
                        'method' => $request->method(),
                        'user_agent' => $request->userAgent(),
                    ]);
                } catch (\Exception $logException) {
                    // Silently fail if logging fails to avoid infinite loops
                }
            }

            return null; // Continue with default error handling
        });
    })->create();
