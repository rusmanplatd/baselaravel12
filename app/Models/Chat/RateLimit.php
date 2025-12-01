<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'action',
        'hits',
        'max_attempts',
        'window_start',
        'window_end',
        'reset_at',
        'is_blocked',
        'metadata',
    ];

    protected $casts = [
        'hits' => 'integer',
        'max_attempts' => 'integer',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'reset_at' => 'datetime',
        'is_blocked' => 'boolean',
        'metadata' => 'array',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('window_end', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('window_end', '<=', now());
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->window_end > now();
    }

    public function isExpired(): bool
    {
        return $this->window_end <= now();
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    public function isLimitExceeded(): bool
    {
        return $this->hits >= $this->max_attempts;
    }

    public function getRemainingAttempts(): int
    {
        return max(0, $this->max_attempts - $this->hits);
    }

    public function getSecondsUntilReset(): int
    {
        return max(0, $this->reset_at->diffInSeconds(now()));
    }

    public function getUsagePercentage(): float
    {
        return $this->max_attempts > 0 ? ($this->hits / $this->max_attempts) * 100 : 0;
    }
}
