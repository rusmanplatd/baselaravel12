<?php

namespace App\Models\Channel;

use App\Models\Chat\Conversation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelStatistic extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'channel_id',
        'date',
        'views',
        'unique_views',
        'new_subscribers',
        'unsubscribes',
        'shares',
        'messages_sent',
        'hourly_views',
        'demographic_data',
    ];

    protected $casts = [
        'date' => 'date',
        'views' => 'integer',
        'unique_views' => 'integer',
        'new_subscribers' => 'integer',
        'unsubscribes' => 'integer',
        'shares' => 'integer',
        'messages_sent' => 'integer',
        'hourly_views' => 'array',
        'demographic_data' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'channel_id');
    }

    // Scopes
    public function scopeForChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForPeriod($query, $period = 'week')
    {
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        return $query->where('date', '>=', $startDate);
    }

    // Helper methods
    public function incrementViews(int $count = 1): void
    {
        $this->increment('views', $count);
    }

    public function incrementUniqueViews(int $count = 1): void
    {
        $this->increment('unique_views', $count);
    }

    public function incrementNewSubscribers(int $count = 1): void
    {
        $this->increment('new_subscribers', $count);
    }

    public function incrementUnsubscribes(int $count = 1): void
    {
        $this->increment('unsubscribes', $count);
    }

    public function incrementShares(int $count = 1): void
    {
        $this->increment('shares', $count);
    }

    public function incrementMessagesSent(int $count = 1): void
    {
        $this->increment('messages_sent', $count);
    }

    public function addHourlyView(int $hour, int $count = 1): void
    {
        $hourlyViews = $this->hourly_views ?? array_fill(0, 24, 0);
        $hourlyViews[$hour] = ($hourlyViews[$hour] ?? 0) + $count;
        $this->update(['hourly_views' => $hourlyViews]);
    }

    public function getNetSubscribers(): int
    {
        return $this->new_subscribers - $this->unsubscribes;
    }

    public function getEngagementRate(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        $engagementActions = $this->shares;
        return round(($engagementActions / $this->views) * 100, 2);
    }

    public function getUniqueViewRate(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        return round(($this->unique_views / $this->views) * 100, 2);
    }

    public function getPeakHour(): ?int
    {
        if (!$this->hourly_views || empty($this->hourly_views)) {
            return null;
        }

        return array_search(max($this->hourly_views), $this->hourly_views);
    }

    // Static helper methods
    public static function getOrCreateForDate(string $channelId, $date = null): self
    {
        $date = $date ? (is_string($date) ? $date : $date->format('Y-m-d')) : now()->format('Y-m-d');
        
        return static::firstOrCreate(
            ['channel_id' => $channelId, 'date' => $date],
            [
                'views' => 0,
                'unique_views' => 0,
                'new_subscribers' => 0,
                'unsubscribes' => 0,
                'shares' => 0,
                'messages_sent' => 0,
                'hourly_views' => array_fill(0, 24, 0),
            ]
        );
    }

    public static function recordView(string $channelId, bool $isUnique = false, $date = null): void
    {
        $stat = static::getOrCreateForDate($channelId, $date);
        $stat->incrementViews();
        
        if ($isUnique) {
            $stat->incrementUniqueViews();
        }

        $stat->addHourlyView(now()->hour);
    }

    public static function recordSubscription(string $channelId, $date = null): void
    {
        $stat = static::getOrCreateForDate($channelId, $date);
        $stat->incrementNewSubscribers();
    }

    public static function recordUnsubscription(string $channelId, $date = null): void
    {
        $stat = static::getOrCreateForDate($channelId, $date);
        $stat->incrementUnsubscribes();
    }

    public static function recordShare(string $channelId, $date = null): void
    {
        $stat = static::getOrCreateForDate($channelId, $date);
        $stat->incrementShares();
    }

    public static function recordMessage(string $channelId, $date = null): void
    {
        $stat = static::getOrCreateForDate($channelId, $date);
        $stat->incrementMessagesSent();
    }
}