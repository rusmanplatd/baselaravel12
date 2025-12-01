<?php

namespace App\Models\Channel;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelMessageView extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'channel_id',
        'message_id',
        'user_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'channel_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeForMessage($query, $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAnonymous($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeAuthenticated($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeInTimeRange($query, $start, $end)
    {
        return $query->whereBetween('viewed_at', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('viewed_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('viewed_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('viewed_at', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    // Helper methods
    public function isAnonymous(): bool
    {
        return is_null($this->user_id);
    }

    public function isAuthenticated(): bool
    {
        return !is_null($this->user_id);
    }

    // Static helper methods
    public static function recordView(
        string $channelId,
        string $messageId,
        ?string $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return static::create([
            'channel_id' => $channelId,
            'message_id' => $messageId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'viewed_at' => now(),
        ]);
    }

    public static function getViewCountForMessage(string $messageId): int
    {
        return static::where('message_id', $messageId)->count();
    }

    public static function getUniqueViewCountForMessage(string $messageId): int
    {
        return static::where('message_id', $messageId)
            ->distinct('user_id')
            ->whereNotNull('user_id')
            ->count('user_id');
    }

    public static function getViewCountForChannel(string $channelId): int
    {
        return static::where('channel_id', $channelId)->count();
    }

    public static function getUniqueViewCountForChannel(string $channelId): int
    {
        return static::where('channel_id', $channelId)
            ->distinct('user_id')
            ->whereNotNull('user_id')
            ->count('user_id');
    }

    public static function hasUserViewedMessage(string $userId, string $messageId): bool
    {
        return static::where('user_id', $userId)
            ->where('message_id', $messageId)
            ->exists();
    }

    public static function getUserViewHistory(string $userId, ?string $channelId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('user_id', $userId)
            ->with(['message', 'channel'])
            ->orderBy('viewed_at', 'desc');

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        return $query->get();
    }

    public static function getChannelViewStats(string $channelId, ?string $period = 'week'): array
    {
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        $views = static::forChannel($channelId)
            ->where('viewed_at', '>=', $startDate)
            ->get();

        return [
            'total_views' => $views->count(),
            'unique_views' => $views->whereNotNull('user_id')->unique('user_id')->count(),
            'anonymous_views' => $views->whereNull('user_id')->count(),
            'authenticated_views' => $views->whereNotNull('user_id')->count(),
            'daily_breakdown' => $views->groupBy(fn($view) => $view->viewed_at->format('Y-m-d'))
                ->map(fn($dayViews) => [
                    'total' => $dayViews->count(),
                    'unique' => $dayViews->whereNotNull('user_id')->unique('user_id')->count(),
                ]),
        ];
    }

    public static function getPopularMessages(string $channelId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::forChannel($channelId)
            ->selectRaw('message_id, COUNT(*) as view_count')
            ->groupBy('message_id')
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->with('message')
            ->get();
    }
}