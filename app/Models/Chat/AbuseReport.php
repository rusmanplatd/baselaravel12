<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbuseReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'reported_user_id',
        'reporter_user_id',
        'conversation_id',
        'message_id',
        'abuse_type',
        'description',
        'evidence',
        'status',
        'resolution_notes',
        'reviewed_by',
        'reviewed_at',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('abuse_type', $type);
    }

    public function scopeByReporter($query, string $reporterId)
    {
        return $query->where('reporter_user_id', $reporterId);
    }

    public function scopeByReported($query, string $reportedUserId)
    {
        return $query->where('reported_user_id', $reportedUserId);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isDismissed(): bool
    {
        return $this->status === 'dismissed';
    }

    public function isSpamReport(): bool
    {
        return $this->abuse_type === 'spam';
    }

    public function isHarassmentReport(): bool
    {
        return $this->abuse_type === 'harassment';
    }

    public function isInappropriateReport(): bool
    {
        return $this->abuse_type === 'inappropriate';
    }

    public function isMalwareReport(): bool
    {
        return $this->abuse_type === 'malware';
    }

    public function hasEvidence(): bool
    {
        return ! empty($this->evidence);
    }

    public function getEvidenceCount(): int
    {
        return is_array($this->evidence) ? count($this->evidence) : 0;
    }

    public function hasScreenshots(): bool
    {
        if (! is_array($this->evidence)) {
            return false;
        }

        return collect($this->evidence)->contains(function ($item) {
            return isset($item['type']) && $item['type'] === 'screenshot';
        });
    }

    public function getAgeInHours(): float
    {
        return $this->created_at->diffInHours(now());
    }

    public function requiresUrgentReview(): bool
    {
        return $this->abuse_type === 'malware' ||
               $this->abuse_type === 'harassment' ||
               $this->getAgeInHours() > 24;
    }
}
