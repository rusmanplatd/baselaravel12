<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        // Debug logging
        \Log::info('Custom Authenticate middleware called', [
            'path' => $request->path(),
            'expects_json' => $request->expectsJson(),
            'is_api' => $request->is('api/*'),
            'guards' => $guards
        ]);

        // For API routes or JSON requests, always throw authentication exception
        // which will be converted to 401 JSON response
        if ($request->expectsJson() || $request->is('api/*')) {
            throw new AuthenticationException(
                'Unauthenticated.', $guards, null
            );
        }

        throw new AuthenticationException(
            'Unauthenticated.', $guards, $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return route('login');
    }
}