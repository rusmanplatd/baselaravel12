<?php

namespace App\Http\Middleware;

use App\Models\Bot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BotAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token || !str_starts_with($token, 'bot_')) {
            return response()->json(['error' => 'Bot authentication required'], 401);
        }

        $bot = Bot::where('api_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$bot) {
            return response()->json(['error' => 'Invalid bot token'], 401);
        }

        // Check rate limiting
        $rateLimitKey = "bot_rate_limit:{$bot->id}";
        $currentCount = cache()->get($rateLimitKey, 0);
        
        if ($currentCount >= $bot->getRateLimitPerMinute()) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Increment rate limit counter
        cache()->put($rateLimitKey, $currentCount + 1, 60); // 60 seconds

        // Set bot in request
        $request->attributes->set('bot', $bot);
        $request->merge(['bot' => $bot]);

        return $next($request);
    }
}