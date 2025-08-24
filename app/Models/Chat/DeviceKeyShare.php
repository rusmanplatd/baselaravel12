<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceKeyShare extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'from_device_id',
        'to_device_id',
        'conversation_id',
        'user_id',
        'encrypted_symmetric_key',
        'from_device_public_key',
        'to_device_public_key',
        'key_version',
        'share_method',
        'is_accepted',
        'is_active',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'is_accepted' => 'boolean',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_symmetric_key',
        'from_device_public_key',
        'to_device_public_key',
    ];

    public function fromDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'from_device_id');
    }

    public function toDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'to_device_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accept(): void
    {
        $this->update([
            'is_accepted' => true,
            'accepted_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('is_accepted', false)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeAccepted($query)
    {
        return $query->where('is_accepted', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('to_device_id', $deviceId);
    }

    public function scopeFromDevice($query, string $deviceId)
    {
        return $query->where('from_device_id', $deviceId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($keyShare) {
            if (! $keyShare->expires_at) {
                $keyShare->expires_at = now()->addDays(7); // Default 7-day expiration
            }
        });
    }
}
