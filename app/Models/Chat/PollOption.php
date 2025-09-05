<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollOption extends Model
{
    use HasUlids;

    protected $fillable = [
        'poll_id',
        'option_order',
        'encrypted_option_text',
        'option_hash',
        'option_type',
        'encrypted_metadata',
    ];

    protected $casts = [
        'option_order' => 'integer',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function getVoteCount(): int
    {
        return $this->poll->getVoteCount($this->option_order);
    }

    public function getVotePercentage(): float
    {
        $totalVotes = $this->poll->getTotalVotes();
        if ($totalVotes === 0) {
            return 0;
        }

        $optionVotes = $this->getVoteCount();

        return ($optionVotes / $totalVotes) * 100;
    }

    public function isTextOption(): bool
    {
        return $this->option_type === 'text';
    }

    public function isImageOption(): bool
    {
        return $this->option_type === 'image';
    }

    public function isEmojiOption(): bool
    {
        return $this->option_type === 'emoji';
    }
}
