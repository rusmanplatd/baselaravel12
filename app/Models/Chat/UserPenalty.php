<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPenalty extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'penalty_type',
        'reason',
        'description',
        'restrictions',
        'severity_level',
        'starts_at',
        'expires_at',
        'is_active',
        'applied_by',
        'admin_notes',
    ];

    protected $casts = [
        'restrictions' => 'array',
        'severity_level' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
        return $query->where('penalty_type', $type);
    }

    public function scopeBySeverity($query, int $level)
    {
        return $query->where('severity_level', $level);
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

    public function getSecondsUntilExpiry(): ?int
    {
        if (is_null($this->expires_at)) {
            return null; // Permanent penalty
        }

        return max(0, $this->expires_at->diffInSeconds(now()));
    }

    public function getHoursUntilExpiry(): ?float
    {
        $seconds = $this->getSecondsUntilExpiry();

        return $seconds ? $seconds / 3600 : null;
    }

    public function isRateLimitRestriction(): bool
    {
        return $this->penalty_type === 'rate_limit';
    }

    public function isMessageLimitRestriction(): bool
    {
        return $this->penalty_type === 'message_limit';
    }

    public function isFileLimitRestriction(): bool
    {
        return $this->penalty_type === 'file_limit';
    }

    public function isTemporaryBan(): bool
    {
        return $this->penalty_type === 'temporary_ban';
    }

    public function hasRestriction(string $restrictionType): bool
    {
        return isset($this->restrictions[$restrictionType]);
    }

    public function getRestrictionValue(string $restrictionType, $default = null)
    {
        return $this->restrictions[$restrictionType] ?? $default;
    }
}
