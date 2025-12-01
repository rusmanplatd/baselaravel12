<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpamDetection extends Model
{
    use HasUlids;

    protected $fillable = [
        'message_id',
        'user_id',
        'conversation_id',
        'detection_type',
        'confidence_score',
        'detection_details',
        'action_taken',
        'is_false_positive',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'detection_details' => 'array',
        'is_false_positive' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('detection_type', $type);
    }

    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    public function scopeMediumConfidence($query)
    {
        return $query->whereBetween('confidence_score', [0.5, 0.79]);
    }

    public function scopeLowConfidence($query)
    {
        return $query->where('confidence_score', '<', 0.5);
    }

    public function scopeFlagged($query)
    {
        return $query->where('action_taken', 'flagged');
    }

    public function scopeHidden($query)
    {
        return $query->where('action_taken', 'hidden');
    }

    public function scopeDeleted($query)
    {
        return $query->where('action_taken', 'deleted');
    }

    public function scopeUserWarned($query)
    {
        return $query->where('action_taken', 'user_warned');
    }

    public function scopeFalsePositive($query)
    {
        return $query->where('is_false_positive', true);
    }

    public function scopePendingReview($query)
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeReviewed($query)
    {
        return $query->whereNotNull('reviewed_at');
    }

    // Helper methods
    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= 0.8;
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidence_score >= 0.5 && $this->confidence_score < 0.8;
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence_score < 0.5;
    }

    public function isDuplicateContent(): bool
    {
        return $this->detection_type === 'duplicate_content';
    }

    public function hasExcessiveLinks(): bool
    {
        return $this->detection_type === 'excessive_links';
    }

    public function isKeywordMatch(): bool
    {
        return $this->detection_type === 'keyword_match';
    }

    public function isMlClassified(): bool
    {
        return $this->detection_type === 'ml_classifier';
    }

    public function wasFlagged(): bool
    {
        return $this->action_taken === 'flagged';
    }

    public function wasHidden(): bool
    {
        return $this->action_taken === 'hidden';
    }

    public function wasDeleted(): bool
    {
        return $this->action_taken === 'deleted';
    }

    public function wasUserWarned(): bool
    {
        return $this->action_taken === 'user_warned';
    }

    public function isFalsePositive(): bool
    {
        return $this->is_false_positive;
    }

    public function isPendingReview(): bool
    {
        return is_null($this->reviewed_at);
    }

    public function isReviewed(): bool
    {
        return ! is_null($this->reviewed_at);
    }

    public function getConfidencePercentage(): int
    {
        return (int) round($this->confidence_score * 100);
    }

    public function getDetectionDetail(string $key, $default = null)
    {
        return $this->detection_details[$key] ?? $default;
    }

    public function requiresHumanReview(): bool
    {
        return $this->isMediumConfidence() ||
               $this->isHighConfidence() && $this->wasFlagged();
    }

    public function getAgeInHours(): float
    {
        return $this->created_at->diffInHours(now());
    }

    public function isOldDetection(int $hoursThreshold = 24): bool
    {
        return $this->getAgeInHours() > $hoursThreshold;
    }
}
