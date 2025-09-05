<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'message_id',
        'creator_id',
        'encrypted_title',
        'title_hash',
        'encrypted_description',
        'description_hash',
        'anonymous',
        'allow_partial_responses',
        'randomize_questions',
        'expires_at',
        'is_closed',
        'closed_at',
        'settings',
    ];

    protected $casts = [
        'anonymous' => 'boolean',
        'allow_partial_responses' => 'boolean',
        'randomize_questions' => 'boolean',
        'expires_at' => 'datetime',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'settings' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('question_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_closed', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    public function scopeAnonymous($query)
    {
        return $query->where('anonymous', true);
    }

    // Helper methods
    public function isActive(): bool
    {
        return ! $this->is_closed &&
               (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->is_closed;
    }

    public function canRespond(): bool
    {
        return $this->isActive();
    }

    public function hasResponded(string $userId): bool
    {
        return $this->responses()->where('respondent_id', $userId)->exists();
    }

    public function getUserResponse(string $userId): ?SurveyResponse
    {
        return $this->responses()->where('respondent_id', $userId)->first();
    }

    public function getTotalResponses(): int
    {
        return $this->responses()->count();
    }

    public function getCompleteResponses(): int
    {
        return $this->responses()->where('is_complete', true)->count();
    }

    public function getPartialResponses(): int
    {
        return $this->responses()->where('is_complete', false)->count();
    }

    public function getCompletionRate(): float
    {
        $total = $this->getTotalResponses();
        if ($total === 0) {
            return 0;
        }

        $complete = $this->getCompleteResponses();

        return ($complete / $total) * 100;
    }

    public function getParticipationRate(): float
    {
        $totalParticipants = $this->message->conversation->participants()->count();
        $totalResponses = $this->getTotalResponses();

        return $totalParticipants > 0 ? ($totalResponses / $totalParticipants) * 100 : 0;
    }

    public function getTotalQuestions(): int
    {
        return $this->questions()->count();
    }

    public function getRequiredQuestions(): int
    {
        return $this->questions()->where('required', true)->count();
    }

    public function close(?User $user = null): void
    {
        $this->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);
    }

    public function reopen(?User $user = null): void
    {
        $this->update([
            'is_closed' => false,
            'closed_at' => null,
        ]);
    }

    public function canViewResults(User $user): bool
    {
        // Creator can always see results
        if ($this->creator_id === $user->id) {
            return true;
        }

        // If survey is closed, everyone can see results
        if ($this->isClosed() || $this->isExpired()) {
            return true;
        }

        // If user has responded, they can see current results
        return $this->hasResponded($user->id);
    }

    public function allowsPartialResponses(): bool
    {
        return $this->allow_partial_responses;
    }

    public function shouldRandomizeQuestions(): bool
    {
        return $this->randomize_questions;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }
}
