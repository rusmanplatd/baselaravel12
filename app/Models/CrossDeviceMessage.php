<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrossDeviceMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'conversation_id',
        'sender_id',
        'sender_device_id',
        'target_devices',
        'encrypted_for_devices',
        'quantum_safe',
        'encryption_metadata',
        'expires_at',
    ];

    protected $casts = [
        'target_devices' => 'json',
        'encrypted_for_devices' => 'json',
        'encryption_metadata' => 'json',
        'quantum_safe' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (empty($message->message_id)) {
                $message->message_id = self::generateMessageId();
            }
        });
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Chat\Conversation::class, 'conversation_id');
    }

    /**
     * Get the sender of this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the sender device.
     */
    public function senderDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'sender_device_id');
    }

    /**
     * Generate a unique message ID.
     */
    public static function generateMessageId(): string
    {
        return 'msg_' . time() . '_' . Str::random(16);
    }

    /**
     * Check if message is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->lt(now());
    }

    /**
     * Get encrypted content for a specific device.
     */
    public function getEncryptedContentForDevice(string $deviceId): ?array
    {
        $encryptedData = $this->encrypted_for_devices ?? [];
        return $encryptedData[$deviceId] ?? null;
    }

    /**
     * Add encrypted content for a device.
     */
    public function addEncryptedContentForDevice(string $deviceId, array $encryptedContent): void
    {
        $encryptedData = $this->encrypted_for_devices ?? [];
        $encryptedData[$deviceId] = $encryptedContent;

        $targetDevices = $this->target_devices ?? [];
        if (!in_array($deviceId, $targetDevices)) {
            $targetDevices[] = $deviceId;
        }

        $this->update([
            'encrypted_for_devices' => $encryptedData,
            'target_devices' => $targetDevices,
        ]);
    }

    /**
     * Remove encrypted content for a device.
     */
    public function removeEncryptedContentForDevice(string $deviceId): void
    {
        $encryptedData = $this->encrypted_for_devices ?? [];
        unset($encryptedData[$deviceId]);

        $targetDevices = $this->target_devices ?? [];
        $targetDevices = array_filter($targetDevices, fn($id) => $id !== $deviceId);

        $this->update([
            'encrypted_for_devices' => $encryptedData,
            'target_devices' => array_values($targetDevices),
        ]);
    }

    /**
     * Check if message is encrypted for a device.
     */
    public function isEncryptedForDevice(string $deviceId): bool
    {
        return isset($this->encrypted_for_devices[$deviceId]);
    }

    /**
     * Get target device count.
     */
    public function getTargetDeviceCount(): int
    {
        return count($this->target_devices ?? []);
    }

    /**
     * Set expiration time.
     */
    public function setExpiration(\DateTimeInterface $expiresAt): void
    {
        $this->update(['expires_at' => $expiresAt]);
    }

    /**
     * Scope for quantum-safe messages.
     */
    public function scopeQuantumSafe($query)
    {
        return $query->where('quantum_safe', true);
    }

    /**
     * Scope for non-expired messages.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for messages targeting a specific device.
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->whereJsonContains('target_devices', $deviceId);
    }

    /**
     * Scope for messages from a specific conversation.
     */
    public function scopeForConversation($query, int $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope for messages from a specific sender.
     */
    public function scopeFromSender($query, int $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    /**
     * Clean up expired messages.
     */
    public static function cleanupExpiredMessages(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    /**
     * Get message statistics.
     */
    public function getStatistics(): array
    {
        return [
            'message_id' => $this->message_id,
            'target_device_count' => $this->getTargetDeviceCount(),
            'is_quantum_safe' => $this->quantum_safe,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}