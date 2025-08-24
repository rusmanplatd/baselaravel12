<?php

namespace App\Http\Middleware;

use App\Exceptions\ChatException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatSpamProtection
{
    private const DUPLICATE_MESSAGE_WINDOW = 60; // seconds

    private const MAX_MESSAGE_LENGTH = 10000;

    private const MAX_CONSECUTIVE_MESSAGES = 20;

    private const SUSPICIOUS_PATTERNS = [
        '/(.)\1{50,}/',           // 50+ repeated characters
        '/https?:\/\/[^\s]+/',    // URLs (basic detection)
        '/\b(\w+\s+){20,}/',     // 20+ repeated words
    ];

    public function handle(Request $request, Closure $next)
    {
        $userId = auth()->id();
        if (! $userId) {
            return $next($request);
        }

        // Check for suspicious content
        if ($request->has('content') && $request->input('content') !== null) {
            $this->checkMessageContent($request->input('content'), $userId);
        }

        // Check for duplicate messages
        $this->checkDuplicateMessages($request, $userId);

        // Check for rapid consecutive messages
        $this->checkConsecutiveMessages($userId);

        return $next($request);
    }

    private function checkMessageContent(string $content, string $userId): void
    {
        // Check message length
        if (strlen($content) > self::MAX_MESSAGE_LENGTH) {
            $this->logSuspiciousActivity($userId, 'message_too_long', ['length' => strlen($content)]);
            throw new ChatException('Message too long', 'MESSAGE_TOO_LONG');
        }

        // Check for suspicious patterns
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logSuspiciousActivity($userId, 'suspicious_pattern', [
                    'pattern' => $pattern,
                    'content_sample' => substr($content, 0, 100),
                ]);

                throw new ChatException(
                    'Message contains suspicious content',
                    'SUSPICIOUS_CONTENT'
                );
            }
        }

        // Check for excessive caps
        if (strlen($content) > 10) {
            $capsCount = preg_match_all('/[A-Z]/', $content);
            $capsPercentage = ($capsCount / strlen($content)) * 100;

            if ($capsPercentage > 70) {
                $this->logSuspiciousActivity($userId, 'excessive_caps', [
                    'caps_percentage' => $capsPercentage,
                ]);

                throw new ChatException(
                    'Please avoid excessive use of capital letters',
                    'EXCESSIVE_CAPS'
                );
            }
        }
    }

    private function checkDuplicateMessages(Request $request, string $userId): void
    {
        if (! $request->has('content') || $request->input('content') === null) {
            return;
        }

        $content = $request->input('content');
        $conversationId = $request->route('conversation')?->id ?? 'unknown';

        $messageHash = hash('sha256', $content.$conversationId);
        $cacheKey = "chat_message_hash_{$userId}_{$messageHash}";

        if (Cache::has($cacheKey)) {
            $this->logSuspiciousActivity($userId, 'duplicate_message', [
                'conversation_id' => $conversationId,
                'message_hash' => $messageHash,
            ]);

            throw new ChatException(
                'Duplicate message detected',
                'DUPLICATE_MESSAGE'
            );
        }

        Cache::put($cacheKey, true, now()->addSeconds(self::DUPLICATE_MESSAGE_WINDOW));
    }

    private function checkConsecutiveMessages(string $userId): void
    {
        $cacheKey = "chat_consecutive_messages_{$userId}";
        $consecutiveCount = Cache::get($cacheKey, 0);

        if ($consecutiveCount >= self::MAX_CONSECUTIVE_MESSAGES) {
            $this->logSuspiciousActivity($userId, 'too_many_consecutive', [
                'count' => $consecutiveCount,
            ]);

            throw new ChatException(
                'Too many messages sent in quick succession. Please wait a moment.',
                'TOO_MANY_CONSECUTIVE_MESSAGES'
            );
        }

        // Increment and set to expire after 1 minute of inactivity
        Cache::put($cacheKey, $consecutiveCount + 1, now()->addMinute());
    }

    private function logSuspiciousActivity(string $userId, string $type, array $data = []): void
    {
        Log::warning('Suspicious chat activity detected', [
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Track suspicious activity count
        $cacheKey = "chat_suspicious_activity_{$userId}";
        $count = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $count + 1, now()->addHours(24));

        // If too many suspicious activities, temporarily block
        if ($count >= 10) {
            Cache::put("chat_user_blocked_{$userId}", true, now()->addMinutes(30));

            Log::alert('User temporarily blocked due to suspicious activity', [
                'user_id' => $userId,
                'suspicious_count' => $count + 1,
            ]);
        }
    }
}
