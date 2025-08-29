<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceCode extends Model
{
    protected $table = 'oauth_device_codes';
    
    protected $primaryKey = 'device_code';
    
    protected $keyType = 'string';
    
    public $incrementing = false;

    protected $fillable = [
        'device_code',
        'user_code',
        'client_id',
        'user_id',
        'scopes',
        'expires_at',
        'last_polled_at',
        'poll_count',
        'status',
        'verification_uri',
        'verification_uri_complete',
        'interval',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function isDenied(): bool
    {
        return $this->status === 'denied';
    }

    public function markAsAuthorized(User $user): void
    {
        $this->update([
            'user_id' => $user->id,
            'status' => 'authorized',
        ]);
    }

    public function markAsDenied(): void
    {
        $this->update(['status' => 'denied']);
    }

    public function recordPoll(): void
    {
        $this->increment('poll_count');
        $this->update(['last_polled_at' => now()]);
    }

    public static function generateUserCode(): string
    {
        // Generate a user-friendly code like Google (6-8 chars, uppercase)
        do {
            $code = strtoupper(Str::random(6));
            // Make it more readable by avoiding confusing characters
            $code = strtr($code, '0O1I', 'ABCD');
        } while (static::where('user_code', $code)->exists());

        return $code;
    }

    public static function generateDeviceCode(): string
    {
        return Str::random(40);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }
}
