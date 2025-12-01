<?php

namespace App\Models\Chat;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyResponse extends Model
{
    use HasUlids;

    protected $fillable = [
        'survey_id',
        'respondent_id',
        'device_id',
        'is_complete',
        'is_anonymous',
        'started_at',
        'completed_at',
        'response_encryption_keys',
    ];

    protected $casts = [
        'is_complete' => 'boolean',
        'is_anonymous' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'response_encryption_keys' => 'array',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function questionResponses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class, 'survey_response_id');
    }

    public function isComplete(): bool
    {
        return $this->is_complete;
    }

    public function isPartial(): bool
    {
        return ! $this->is_complete;
    }

    public function isAnonymous(): bool
    {
        return $this->is_anonymous;
    }

    public function getDurationInSeconds(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function getDurationFormatted(): ?string
    {
        $seconds = $this->getDurationInSeconds();
        if ($seconds === null) {
            return null;
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d:%02d', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    public function getAnsweredQuestions(): int
    {
        return $this->questionResponses()->count();
    }

    public function getTotalQuestions(): int
    {
        return $this->survey->questions()->count();
    }

    public function getCompletionPercentage(): float
    {
        $total = $this->getTotalQuestions();
        if ($total === 0) {
            return 0;
        }

        $answered = $this->getAnsweredQuestions();

        return ($answered / $total) * 100;
    }

    public function hasAnsweredQuestion(string $questionId): bool
    {
        return $this->questionResponses()->where('question_id', $questionId)->exists();
    }

    public function getQuestionResponse(string $questionId): ?SurveyQuestionResponse
    {
        return $this->questionResponses()->where('question_id', $questionId)->first();
    }

    public function markAsComplete(): void
    {
        $this->update([
            'is_complete' => true,
            'completed_at' => now(),
        ]);
    }

    public function markAsPartial(): void
    {
        $this->update([
            'is_complete' => false,
            'completed_at' => null,
        ]);
    }

    public function getProgress(): array
    {
        return [
            'answered' => $this->getAnsweredQuestions(),
            'total' => $this->getTotalQuestions(),
            'percentage' => $this->getCompletionPercentage(),
            'is_complete' => $this->isComplete(),
        ];
    }
}
