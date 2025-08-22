<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class OAuthRateLimiter
{
    public function handle(Request $request, Closure $next, string $limiter = 'oauth')
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts($limiter))) {
            return $this->buildTooManyAttemptsResponse($key, $limiter);
        }

        RateLimiter::hit($key, $this->decayMinutes($limiter) * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $this->maxAttempts($limiter),
            $this->calculateRemainingAttempts($key, $this->maxAttempts($limiter))
        );
    }

    protected function resolveRequestSignature(Request $request): string
    {
        if ($clientId = $request->input('client_id')) {
            return sha1('oauth_client:'.$clientId.'|'.$request->ip());
        }

        return sha1('oauth_ip:'.$request->ip());
    }

    protected function maxAttempts(string $limiter): int
    {
        return match ($limiter) {
            'oauth_token' => 10, // Token endpoint: 10 per minute
            'oauth_authorize' => 30, // Authorization endpoint: 30 per minute
            'oauth_userinfo' => 60, // UserInfo endpoint: 60 per minute
            default => 20, // Default: 20 per minute
        };
    }

    protected function decayMinutes(string $limiter): int
    {
        return match ($limiter) {
            'oauth_token' => 1,
            'oauth_authorize' => 1,
            'oauth_userinfo' => 1,
            default => 1,
        };
    }

    protected function buildTooManyAttemptsResponse(string $key, string $limiter): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'error' => 'rate_limit_exceeded',
            'error_description' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $this->maxAttempts($limiter),
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - RateLimiter::attempts($key));
    }

    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }
}
