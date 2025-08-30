<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_device_id',
        'session_id',
        'conversation_keys',
        'last_key_sync_at',
        'is_active',
        'sync_metadata',
    ];

    protected $casts = [
        'conversation_keys' => 'json',
        'sync_metadata' => 'json',
        'is_active' => 'boolean',
        'last_key_sync_at' => 'datetime',
    ];

    /**
     * Get the device that owns this session.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }

    /**
     * Update conversation key for this session.
     */
    public function updateConversationKey(string $conversationId, array $keyData): void
    {
        $keys = $this->conversation_keys ?? [];
        $keys[$conversationId] = $keyData;

        $this->update([
            'conversation_keys' => $keys,
            'last_key_sync_at' => now(),
        ]);
    }

    /**
     * Remove conversation key from this session.
     */
    public function removeConversationKey(string $conversationId): void
    {
        $keys = $this->conversation_keys ?? [];
        unset($keys[$conversationId]);

        $this->update([
            'conversation_keys' => $keys,
            'last_key_sync_at' => now(),
        ]);
    }

    /**
     * Clear all conversation keys.
     */
    public function clearConversationKeys(): void
    {
        $this->update([
            'conversation_keys' => [],
            'last_key_sync_at' => now(),
        ]);
    }

    /**
     * Check if session needs key sync.
     */
    public function needsKeySync(): bool
    {
        if (!$this->last_key_sync_at) {
            return true;
        }

        return $this->last_key_sync_at->lt(now()->subHour());
    }

    /**
     * Deactivate session.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activate session.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Scope for active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sessions needing sync.
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_key_sync_at')
              ->orWhere('last_key_sync_at', '<', now()->subHour());
        });
    }
}