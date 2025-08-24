<?php

namespace App\Http\Middleware;

use App\Exceptions\ChatException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatRateLimit
{
    // Rate limits per user per minute
    private const MESSAGE_LIMIT = 60;        // 60 messages per minute

    private const CONVERSATION_LIMIT = 10;   // 10 new conversations per minute

    private const FILE_UPLOAD_LIMIT = 20;    // 20 file uploads per minute

    private const TYPING_LIMIT = 120;        // 120 typing indicators per minute

    public function handle(Request $request, Closure $next, string $type = 'message')
    {
        $userId = auth()->id();
        if (! $userId) {
            return $next($request);
        }

        $limit = $this->getLimit($type);
        $cacheKey = "chat_rate_limit_{$type}_{$userId}_".now()->format('Y-m-d_H:i');

        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $limit) {
            Log::warning('Chat rate limit exceeded', [
                'user_id' => $userId,
                'type' => $type,
                'attempts' => $attempts,
                'limit' => $limit,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            throw new ChatException(
                'Rate limit exceeded. Please slow down.',
                'MESSAGE_RATE_LIMITED',
                ['limit' => $limit, 'reset_in' => 60 - now()->second]
            );
        }

        // Increment counter
        Cache::put($cacheKey, $attempts + 1, now()->addMinutes(1));

        // Add rate limit headers
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $limit);
            $response->header('X-RateLimit-Remaining', max(0, $limit - $attempts - 1));
            $response->header('X-RateLimit-Reset', now()->addMinutes(1)->timestamp);
        }

        return $response;
    }

    private function getLimit(string $type): int
    {
        return match ($type) {
            'message' => self::MESSAGE_LIMIT,
            'conversation' => self::CONVERSATION_LIMIT,
            'file' => self::FILE_UPLOAD_LIMIT,
            'typing' => self::TYPING_LIMIT,
            default => self::MESSAGE_LIMIT,
        };
    }
}
