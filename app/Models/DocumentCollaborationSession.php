<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCollaborationSession extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'document_id',
        'user_id',
        'session_id',
        'socket_id',
        'ip_address',
        'user_agent',
        'started_at',
        'ended_at',
        'is_active',
        'last_activity',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'duration',
        'is_current_session',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForDocument($query, string $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySocketId($query, string $socketId)
    {
        return $query->where('socket_id', $socketId);
    }

    public function scopeRecent($query, int $minutes = 30)
    {
        return $query->where('last_activity', '>=', now()->subMinutes($minutes));
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active && $this->last_activity >= now()->subMinutes(5);
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    public function getIsCurrentSessionAttribute(): bool
    {
        return $this->session_id === session()->getId();
    }

    public function updateActivity(): void
    {
        $this->update([
            'last_activity' => now(),
        ]);
    }

    public function end(): void
    {
        $this->update([
            'ended_at' => now(),
            'is_active' => false,
        ]);
    }

    public function getDurationInMinutes(): int
    {
        return (int) ceil($this->duration / 60);
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration) {
            return '0 seconds';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        $parts = [];
        
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours !== 1 ? 's' : '');
        }
        
        if ($minutes > 0) {
            $parts[] = $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }
        
        if ($seconds > 0 && $hours === 0) {
            $parts[] = $seconds . ' second' . ($seconds !== 1 ? 's' : '');
        }

        return implode(', ', $parts) ?: '0 seconds';
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            $session->started_at = $session->started_at ?? now();
            $session->last_activity = $session->last_activity ?? now();
            $session->is_active = true;
            $session->session_id = $session->session_id ?? session()->getId();
        });
    }
}