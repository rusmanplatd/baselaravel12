<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncryptionKey extends Model
{
    use HasUlids;

    protected $table = 'chat_encryption_keys';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'device_id',
        'device_fingerprint',
        'encrypted_key',
        'public_key',
        'key_version',
        'created_by_device_id',
        'expires_at',
        'is_active',
        'algorithm',
        'key_strength',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'key_version' => 'integer',
    ];

    protected $hidden = [
        'encrypted_key',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function createdByDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'created_by_device_id');
    }

    public function decryptSymmetricKey(string $privateKey): string
    {
        $encryptionService = app(ChatEncryptionService::class);

        return $encryptionService->decryptSymmetricKey(
            $this->encrypted_key,
            $privateKey
        );
    }

    public static function createForUser(
        string $conversationId,
        string $userId,
        string $symmetricKey,
        string $publicKey,
        int $keyVersion = 1
    ): self {
        // Get or create a default device for the user
        $device = UserDevice::where('user_id', $userId)->first();
        
        if (!$device) {
            // Create a default device if none exists
            $device = UserDevice::create([
                'user_id' => $userId,
                'device_name' => 'Default Device',
                'device_type' => 'web',
                'platform' => 'web',
                'public_key' => $publicKey, // Use the provided public key
                'device_fingerprint' => 'default-' . $userId . '-' . time(),
                'last_used_at' => now(),
                'is_trusted' => true,
            ]);
        }

        $encryptionService = app(ChatEncryptionService::class);

        $encryptedKey = $encryptionService->encryptSymmetricKey(
            $symmetricKey,
            $publicKey
        );

        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'encrypted_key' => $encryptedKey,
            'public_key' => $publicKey,
            'key_version' => $keyVersion,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
        ]);
    }

    public static function createForDevice(
        string $conversationId,
        string $userId,
        string $deviceId,
        string $symmetricKey,
        string $publicKey,
        ?string $deviceFingerprint = null,
        int $keyVersion = 1,
        ?string $createdByDeviceId = null
    ): self {
        if (empty($deviceId)) {
            throw new \InvalidArgumentException('Device ID is required for multi-device encryption');
        }

        // If no fingerprint provided, get it from the device
        if (empty($deviceFingerprint)) {
            $device = \App\Models\UserDevice::find($deviceId);
            if (! $device) {
                throw new \InvalidArgumentException('Device not found');
            }
            $deviceFingerprint = $device->device_fingerprint;
        }

        if (empty($deviceFingerprint)) {
            throw new \InvalidArgumentException('Device fingerprint is required for multi-device encryption');
        }

        $encryptionService = app(ChatEncryptionService::class);

        $encryptedKey = $encryptionService->encryptSymmetricKey(
            $symmetricKey,
            $publicKey
        );

        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'device_id' => $deviceId,
            'device_fingerprint' => $deviceFingerprint,
            'encrypted_key' => $encryptedKey,
            'public_key' => $publicKey,
            'key_version' => $keyVersion,
            'created_by_device_id' => $createdByDeviceId,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeByFingerprint($query, string $fingerprint)
    {
        return $query->where('device_fingerprint', $fingerprint);
    }

    public function scopeLatestVersion($query)
    {
        return $query->orderBy('key_version', 'desc');
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
