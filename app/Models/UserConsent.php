<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    protected $table = 'user_consents';

    protected $fillable = [
        'user_id',
        'client_id',
        'scopes',
        'scope_details',
        'last_used_at',
        'expires_at',
        'status',
        'granted_by_ip',
        'granted_user_agent',
        'usage_stats',
    ];

    protected $casts = [
        'scopes' => 'array',
        'scope_details' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_stats' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function recordUsage(?string $ip = null, ?string $userAgent = null): void
    {
        $stats = $this->usage_stats ?? [];
        $stats['last_access'] = now()->toISOString();
        $stats['access_count'] = ($stats['access_count'] ?? 0) + 1;

        if ($ip) {
            $stats['last_ip'] = $ip;
        }

        if ($userAgent) {
            $stats['last_user_agent'] = $userAgent;
        }

        $this->update([
            'last_used_at' => now(),
            'usage_stats' => $stats,
        ]);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function addScope(string $scope): void
    {
        $scopes = $this->scopes ?? [];
        if (! in_array($scope, $scopes)) {
            $scopes[] = $scope;
            $this->update(['scopes' => $scopes]);
        }
    }

    public function removeScope(string $scope): void
    {
        $scopes = array_diff($this->scopes ?? [], [$scope]);
        $this->update(['scopes' => array_values($scopes)]);
    }
}
