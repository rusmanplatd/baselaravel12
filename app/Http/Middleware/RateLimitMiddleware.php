<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class RateLimitMiddleware
{
    protected RateLimitService $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    public function handle(Request $request, Closure $next, string $action = 'general', string $scope = 'per_user'): ResponseAlias
    {
        $user = Auth::user();
        $ip = $request->ip();

        // Generate rate limit key based on scope
        $key = $this->generateRateLimitKey($scope, $user?->id, $ip, $request);

        // Check rate limit
        $result = $this->rateLimitService->checkRateLimit($action, $key, $user, $ip);

        if (! $result['allowed']) {
            return $this->createRateLimitResponse($result);
        }

        // Process request
        $response = $next($request);

        // Record the successful hit after request completion
        $this->rateLimitService->recordHit($action, $key, $user, $ip);

        // Add rate limit headers
        $this->addRateLimitHeaders($response, $result);

        return $response;
    }

    protected function generateRateLimitKey(string $scope, ?string $userId, string $ip, Request $request): string
    {
        switch ($scope) {
            case 'per_user':
                return $userId ? "user:{$userId}" : "ip:{$ip}";

            case 'per_ip':
                return "ip:{$ip}";

            case 'per_conversation':
                $conversationId = $request->route('conversation') ?? $request->input('conversation_id');

                return $conversationId ? "conversation:{$conversationId}:user:{$userId}" : "user:{$userId}";

            case 'global':
                return 'global';

            default:
                return $userId ? "user:{$userId}" : "ip:{$ip}";
        }
    }

    protected function createRateLimitResponse(array $result): Response
    {
        $status = match ($result['reason']) {
            'user_penalty' => 403,
            'ip_restriction' => 403,
            'rate_limit_exceeded' => 429,
            default => 429
        };

        $message = match ($result['reason']) {
            'user_penalty' => 'Account temporarily restricted due to policy violations',
            'ip_restriction' => 'IP address temporarily restricted',
            'rate_limit_exceeded' => 'Rate limit exceeded. Please slow down.',
            default => 'Request blocked'
        };

        $data = [
            'error' => $message,
            'reason' => $result['reason'],
            'retry_after' => $result['retry_after'] ?? null,
            'limits' => [
                'max_attempts' => $result['max_attempts'] ?? null,
                'window_seconds' => $result['window_seconds'] ?? null,
                'remaining' => $result['remaining'] ?? 0,
            ],
        ];

        $response = response()->json($data, $status);

        // Add rate limit headers
        if (isset($result['retry_after'])) {
            $response->header('Retry-After', $result['retry_after']);
        }

        if (isset($result['max_attempts'])) {
            $response->header('X-RateLimit-Limit', $result['max_attempts']);
            $response->header('X-RateLimit-Remaining', $result['remaining'] ?? 0);
        }

        return $response;
    }

    protected function addRateLimitHeaders(ResponseAlias $response, array $result): void
    {
        if (isset($result['max_attempts'])) {
            $response->headers->set('X-RateLimit-Limit', $result['max_attempts']);
            $response->headers->set('X-RateLimit-Remaining', max(0, ($result['max_attempts'] - ($result['current_hits'] ?? 0))));
        }

        if (isset($result['reset_at'])) {
            $response->headers->set('X-RateLimit-Reset', $result['reset_at']);
        }

        if (isset($result['window_seconds'])) {
            $response->headers->set('X-RateLimit-Window', $result['window_seconds']);
        }
    }
}
