<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'trusted_device_id',
        'browser',
        'platform',
        'device_type',
        'location',
        'login_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'last_activity' => 'integer',
        'login_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trustedDevice(): BelongsTo
    {
        return $this->belongsTo(TrustedDevice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('last_activity', 'desc');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function terminate(): void
    {
        $this->update(['is_active' => false]);
    }
}