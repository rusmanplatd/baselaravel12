<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TrustedDevice extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'user_id',
        'device_token',
        'device_name',
        'device_type',
        'browser',
        'platform',
        'ip_address',
        'user_agent',
        'location',
        'last_used_at',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            if (empty($device->device_token)) {
                $device->device_token = Str::random(64);
            }

            if (empty($device->expires_at)) {
                $device->expires_at = now()->addDays(30); // Default 30 days
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'trusted_device_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    public function extend(int $days = 30): void
    {
        $this->update([
            'expires_at' => now()->addDays($days),
        ]);
    }

    public function getDeviceFingerprint(): string
    {
        return hash('sha256', $this->user_agent.$this->ip_address.$this->device_type);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'device_name',
                'device_type',
                'browser',
                'platform',
                'ip_address',
                'last_used_at',
                'expires_at',
                'is_active'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Trusted device {$eventName}")
            ->useLogName('security');
    }
}
