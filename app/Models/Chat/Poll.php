<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poll extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'message_id',
        'creator_id',
        'poll_type',
        'encrypted_question',
        'question_hash',
        'encrypted_options',
        'option_hashes',
        'anonymous',
        'allow_multiple_votes',
        'show_results_immediately',
        'expires_at',
        'is_closed',
        'closed_at',
        'settings',
    ];

    protected $casts = [
        'encrypted_options' => 'array',
        'option_hashes' => 'array',
        'anonymous' => 'boolean',
        'allow_multiple_votes' => 'boolean',
        'show_results_immediately' => 'boolean',
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

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PollAnalytics::class);
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

    public function scopeByType($query, string $type)
    {
        return $query->where('poll_type', $type);
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

    public function canVote(): bool
    {
        return $this->isActive();
    }

    public function hasVoted(string $userId): bool
    {
        return $this->votes()->where('voter_id', $userId)->exists();
    }

    public function getUserVote(string $userId): ?PollVote
    {
        return $this->votes()->where('voter_id', $userId)->first();
    }

    public function getTotalVotes(): int
    {
        return $this->votes()->count();
    }

    public function getVoteCount(int $optionIndex): int
    {
        return $this->votes()
            ->whereJsonContains('encrypted_vote_data->choices', $optionIndex)
            ->count();
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

    public function getParticipationRate(): float
    {
        $totalParticipants = $this->message->conversation->participants()->count();
        $totalVotes = $this->getTotalVotes();

        return $totalParticipants > 0 ? ($totalVotes / $totalParticipants) * 100 : 0;
    }

    public function canViewResults(User $user): bool
    {
        // Creator can always see results
        if ($this->creator_id === $user->id) {
            return true;
        }

        // If show results immediately is enabled
        if ($this->show_results_immediately) {
            return true;
        }

        // If poll is closed, everyone can see results
        if ($this->isClosed() || $this->isExpired()) {
            return true;
        }

        // If user has voted, they can see current results
        return $this->hasVoted($user->id);
    }

    public function isPollType(string $type): bool
    {
        return $this->poll_type === $type;
    }

    public function isSingleChoice(): bool
    {
        return $this->poll_type === 'single_choice';
    }

    public function isMultipleChoice(): bool
    {
        return $this->poll_type === 'multiple_choice';
    }

    public function isRating(): bool
    {
        return $this->poll_type === 'rating';
    }
}
