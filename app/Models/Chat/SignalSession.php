<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\Chat\Conversation;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SignalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'conversation_id',
        'local_user_id',
        'remote_user_id',
        'local_device_id',
        'remote_device_id',
        'local_registration_id',
        'remote_registration_id',
        'remote_identity_key',
        'session_state_encrypted',
        'current_sending_chain',
        'current_receiving_chain',
        'is_active',
        'verification_status',
        'protocol_version',
        'messages_sent',
        'messages_received',
        'key_rotations',
        'last_activity_at',
    ];

    protected $casts = [
        'local_registration_id' => 'integer',
        'remote_registration_id' => 'integer',
        'current_sending_chain' => 'integer',
        'current_receiving_chain' => 'integer',
        'is_active' => 'boolean',
        'messages_sent' => 'integer',
        'messages_received' => 'integer',
        'key_rotations' => 'integer',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the conversation this session belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the local user.
     */
    public function localUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'local_user_id');
    }

    /**
     * Get the remote user.
     */
    public function remoteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remote_user_id');
    }

    /**
     * Get the local device.
     */
    public function localDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'local_device_id');
    }

    /**
     * Get the remote device.
     */
    public function remoteDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'remote_device_id');
    }

    /**
     * Get the messages for this session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SignalMessage::class);
    }

    /**
     * Get the key rotation logs for this session.
     */
    public function keyRotations(): HasMany
    {
        return $this->hasMany(SignalKeyRotation::class);
    }

    /**
     * Get active session for conversation and users.
     */
    public static function getActiveSession(
        int $conversationId,
        int $localUserId,
        int $remoteUserId
    ): ?self {
        return self::where('conversation_id', $conversationId)
            ->where('local_user_id', $localUserId)
            ->where('remote_user_id', $remoteUserId)
            ->where('is_active', true)
            ->latest('last_activity_at')
            ->first();
    }

    /**
     * Update activity timestamp.
     */
    public function updateActivity(): bool
    {
        return $this->update(['last_activity_at' => now()]);
    }

    /**
     * Increment message counters.
     */
    public function incrementMessagesSent(): bool
    {
        return $this->increment('messages_sent') && $this->updateActivity();
    }

    public function incrementMessagesReceived(): bool
    {
        return $this->increment('messages_received') && $this->updateActivity();
    }

    /**
     * Increment key rotation counter.
     */
    public function incrementKeyRotations(): bool
    {
        return $this->increment('key_rotations') && $this->updateActivity();
    }

    /**
     * Check if session needs maintenance (inactive for too long).
     */
    public function needsMaintenance(int $inactiveDays = 30): bool
    {
        return $this->last_activity_at->addDays($inactiveDays)->isPast();
    }

    /**
     * Get session age in days.
     */
    public function getAgeInDays(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Calculate identity fingerprint from remote identity key.
     */
    public function getRemoteIdentityFingerprint(): string
    {
        return hash('sha256', base64_decode($this->remote_identity_key));
    }

    /**
     * Update verification status.
     */
    public function updateVerificationStatus(string $status): bool
    {
        return $this->update(['verification_status' => $status]);
    }

    /**
     * Check if session is verified.
     */
    public function isVerified(): bool
    {
        return in_array($this->verification_status, ['verified', 'trusted']);
    }

    /**
     * Clean up inactive sessions.
     */
    public static function cleanupInactiveSessions(int $inactiveDays = 30): int
    {
        return self::where('last_activity_at', '<', now()->subDays($inactiveDays))
            ->update(['is_active' => false]);
    }
}