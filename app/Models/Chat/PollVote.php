<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    use HasUlids;

    protected $fillable = [
        'poll_id',
        'voter_id',
        'device_id',
        'encrypted_vote_data',
        'vote_hash',
        'vote_encryption_keys',
        'is_anonymous',
        'encrypted_reasoning',
        'voted_at',
    ];

    protected $casts = [
        'encrypted_vote_data' => 'array',
        'vote_encryption_keys' => 'array',
        'is_anonymous' => 'boolean',
        'voted_at' => 'datetime',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function getSelectedOptions(): array
    {
        return $this->encrypted_vote_data['choices'] ?? [];
    }

    public function hasSelectedOption(int $optionIndex): bool
    {
        return in_array($optionIndex, $this->getSelectedOptions());
    }

    public function isAnonymous(): bool
    {
        return $this->is_anonymous;
    }

    public function hasReasoning(): bool
    {
        return ! empty($this->encrypted_reasoning);
    }

    public function getVotingDuration(): int
    {
        // Calculate time taken to vote (if we track start time)
        return 0; // Placeholder
    }
}
