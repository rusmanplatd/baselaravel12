<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionResponse extends Model
{
    use HasUlids;

    protected $fillable = [
        'survey_response_id',
        'question_id',
        'encrypted_answer',
        'answer_hash',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function surveyResponse(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'question_id');
    }

    public function hasAnswer(): bool
    {
        return ! empty($this->encrypted_answer);
    }

    public function getAnswerLength(): int
    {
        // This would be the length of the decrypted answer
        // For now, return a placeholder
        return strlen($this->encrypted_answer);
    }

    public function isAnsweredRecently(): bool
    {
        return $this->answered_at && $this->answered_at->isAfter(now()->subHour());
    }

    public function getTimeSinceAnswered(): string
    {
        if (! $this->answered_at) {
            return 'Never';
        }

        return $this->answered_at->diffForHumans();
    }
}
