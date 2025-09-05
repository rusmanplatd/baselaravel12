<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollAnalytics extends Model
{
    use HasUlids;

    protected $fillable = [
        'poll_id',
        'encrypted_results_summary',
        'participation_stats',
        'generated_at',
    ];

    protected $casts = [
        'encrypted_results_summary' => 'array',
        'participation_stats' => 'array',
        'generated_at' => 'datetime',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function getTotalVotes(): int
    {
        return $this->participation_stats['total_votes'] ?? 0;
    }

    public function getParticipationRate(): float
    {
        return $this->participation_stats['participation_rate'] ?? 0.0;
    }

    public function getCompletionRate(): float
    {
        return $this->participation_stats['completion_rate'] ?? 0.0;
    }

    public function getAverageVotingTime(): float
    {
        return $this->participation_stats['average_voting_time'] ?? 0.0;
    }

    public function getVotingPattern(): array
    {
        return $this->participation_stats['voting_pattern'] ?? [];
    }

    public function hasResults(): bool
    {
        return ! empty($this->encrypted_results_summary);
    }
}
