<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'survey_id',
        'question_order',
        'question_type',
        'encrypted_question_text',
        'question_hash',
        'required',
        'encrypted_options',
        'option_hashes',
        'validation_rules',
        'settings',
    ];

    protected $casts = [
        'question_order' => 'integer',
        'required' => 'boolean',
        'encrypted_options' => 'array',
        'option_hashes' => 'array',
        'validation_rules' => 'array',
        'settings' => 'array',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class, 'question_id');
    }

    // Question type checks
    public function isTextQuestion(): bool
    {
        return $this->question_type === 'text';
    }

    public function isMultipleChoiceQuestion(): bool
    {
        return $this->question_type === 'multiple_choice';
    }

    public function isSingleChoiceQuestion(): bool
    {
        return $this->question_type === 'single_choice';
    }

    public function isRatingQuestion(): bool
    {
        return $this->question_type === 'rating';
    }

    public function isDateQuestion(): bool
    {
        return $this->question_type === 'date';
    }

    public function isEmailQuestion(): bool
    {
        return $this->question_type === 'email';
    }

    public function isNumberQuestion(): bool
    {
        return $this->question_type === 'number';
    }

    public function isFileQuestion(): bool
    {
        return $this->question_type === 'file';
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function hasOptions(): bool
    {
        return in_array($this->question_type, [
            'multiple_choice',
            'single_choice',
            'rating',
        ]);
    }

    public function getOptions(): array
    {
        return $this->encrypted_options ?? [];
    }

    public function getTotalResponses(): int
    {
        return $this->responses()->count();
    }

    public function getResponseRate(): float
    {
        $totalSurveyResponses = $this->survey->getTotalResponses();
        if ($totalSurveyResponses === 0) {
            return 0;
        }

        $questionResponses = $this->getTotalResponses();

        return ($questionResponses / $totalSurveyResponses) * 100;
    }

    public function getValidationRules(): array
    {
        return $this->validation_rules ?? [];
    }

    public function hasValidationRule(string $rule): bool
    {
        return isset($this->validation_rules[$rule]);
    }

    public function getMinLength(): ?int
    {
        return $this->validation_rules['min_length'] ?? null;
    }

    public function getMaxLength(): ?int
    {
        return $this->validation_rules['max_length'] ?? null;
    }

    public function getMinValue(): ?int
    {
        return $this->validation_rules['min_value'] ?? null;
    }

    public function getMaxValue(): ?int
    {
        return $this->validation_rules['max_value'] ?? null;
    }

    public function getRegexPattern(): ?string
    {
        return $this->validation_rules['regex'] ?? null;
    }

    public function getAllowedFileTypes(): array
    {
        return $this->validation_rules['allowed_file_types'] ?? [];
    }

    public function getMaxFileSize(): ?int
    {
        return $this->validation_rules['max_file_size'] ?? null;
    }
}
