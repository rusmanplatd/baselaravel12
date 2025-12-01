<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RateLimitConfig extends Model
{
    use HasUlids;

    protected $fillable = [
        'action_name',
        'scope',
        'max_attempts',
        'window_seconds',
        'penalty_duration_seconds',
        'escalation_rules',
        'is_active',
        'description',
    ];

    protected $casts = [
        'max_attempts' => 'integer',
        'window_seconds' => 'integer',
        'penalty_duration_seconds' => 'integer',
        'escalation_rules' => 'array',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action_name', $action);
    }

    public function scopeByScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isPerUser(): bool
    {
        return $this->scope === 'per_user';
    }

    public function isPerIp(): bool
    {
        return $this->scope === 'per_ip';
    }

    public function isPerConversation(): bool
    {
        return $this->scope === 'per_conversation';
    }

    public function isGlobal(): bool
    {
        return $this->scope === 'global';
    }

    public function hasPenaltyDuration(): bool
    {
        return $this->penalty_duration_seconds > 0;
    }

    public function hasEscalationRules(): bool
    {
        return ! empty($this->escalation_rules);
    }

    public function getWindowInMinutes(): float
    {
        return $this->window_seconds / 60;
    }

    public function getPenaltyInMinutes(): float
    {
        return $this->penalty_duration_seconds ? $this->penalty_duration_seconds / 60 : 0;
    }

    public function getRatePerSecond(): float
    {
        return $this->window_seconds > 0 ? $this->max_attempts / $this->window_seconds : 0;
    }

    public function getRatePerMinute(): float
    {
        return $this->getRatePerSecond() * 60;
    }
}
