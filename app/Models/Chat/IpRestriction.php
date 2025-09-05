<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpRestriction extends Model
{
    use HasUlids;

    protected $fillable = [
        'ip_address',
        'restriction_type',
        'reason',
        'description',
        'violation_count',
        'restriction_settings',
        'first_violation_at',
        'last_violation_at',
        'expires_at',
        'is_active',
        'applied_by',
        'admin_notes',
    ];

    protected $casts = [
        'violation_count' => 'integer',
        'restriction_settings' => 'array',
        'first_violation_at' => 'datetime',
        'last_violation_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('restriction_type', $type);
    }

    public function scopeByIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRateLimited($query)
    {
        return $query->where('restriction_type', 'rate_limit');
    }

    public function scopeTemporarilyBanned($query)
    {
        return $query->where('restriction_type', 'temporary_ban');
    }

    public function scopePermanentlyBanned($query)
    {
        return $query->where('restriction_type', 'permanent_ban');
    }

    public function scopeSuspicious($query)
    {
        return $query->where('restriction_type', 'suspicious');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active &&
               (is_null($this->expires_at) || $this->expires_at > now());
    }

    public function isExpired(): bool
    {
        return ! is_null($this->expires_at) && $this->expires_at <= now();
    }

    public function isRateLimited(): bool
    {
        return $this->restriction_type === 'rate_limit';
    }

    public function isTemporarilyBanned(): bool
    {
        return $this->restriction_type === 'temporary_ban';
    }

    public function isPermanentlyBanned(): bool
    {
        return $this->restriction_type === 'permanent_ban';
    }

    public function isSuspicious(): bool
    {
        return $this->restriction_type === 'suspicious';
    }

    public function incrementViolationCount(): self
    {
        $this->increment('violation_count');
        $this->update(['last_violation_at' => now()]);

        return $this->fresh();
    }

    public function getSecondsUntilExpiry(): ?int
    {
        if (is_null($this->expires_at)) {
            return null; // Permanent restriction
        }

        return max(0, $this->expires_at->diffInSeconds(now()));
    }

    public function getHoursUntilExpiry(): ?float
    {
        $seconds = $this->getSecondsUntilExpiry();

        return $seconds ? $seconds / 3600 : null;
    }

    public function getViolationFrequency(): float
    {
        if ($this->violation_count <= 1) {
            return 0;
        }

        $hoursSpan = $this->first_violation_at->diffInHours($this->last_violation_at);

        return $hoursSpan > 0 ? $this->violation_count / $hoursSpan : $this->violation_count;
    }

    public function hasCustomRateLimit(): bool
    {
        return $this->isRateLimited() &&
               isset($this->restriction_settings['rate_limit']);
    }

    public function getCustomRateLimit(): ?array
    {
        if (! $this->hasCustomRateLimit()) {
            return null;
        }

        return $this->restriction_settings['rate_limit'];
    }

    public function getRestrictionSetting(string $key, $default = null)
    {
        return $this->restriction_settings[$key] ?? $default;
    }
}
