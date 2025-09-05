<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuspiciousActivity extends Model
{
    use HasUlids;

    protected $table = 'suspicious_activities';

    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_description',
        'activity_data',
        'severity_score',
        'detection_method',
        'status',
        'investigation_notes',
        'investigated_by',
        'investigated_at',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'activity_data' => 'array',
        'severity_score' => 'integer',
        'investigated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInvestigated($query)
    {
        return $query->where('status', 'investigated');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeFalsePositive($query)
    {
        return $query->where('status', 'false_positive');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeBySeverity($query, int $minSeverity)
    {
        return $query->where('severity_score', '>=', $minSeverity);
    }

    public function scopeHighSeverity($query)
    {
        return $query->where('severity_score', '>=', 7);
    }

    public function scopeByDetectionMethod($query, string $method)
    {
        return $query->where('detection_method', $method);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInvestigated(): bool
    {
        return $this->status === 'investigated';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isFalsePositive(): bool
    {
        return $this->status === 'false_positive';
    }

    public function isHighSeverity(): bool
    {
        return $this->severity_score >= 7;
    }

    public function isMediumSeverity(): bool
    {
        return $this->severity_score >= 4 && $this->severity_score < 7;
    }

    public function isLowSeverity(): bool
    {
        return $this->severity_score < 4;
    }

    public function isRapidMessaging(): bool
    {
        return $this->activity_type === 'rapid_messaging';
    }

    public function isMassFileUpload(): bool
    {
        return $this->activity_type === 'mass_file_upload';
    }

    public function isUnusualLogin(): bool
    {
        return $this->activity_type === 'unusual_login';
    }

    public function wasAutomaticallyDetected(): bool
    {
        return $this->detection_method === 'automated';
    }

    public function wasManuallyReported(): bool
    {
        return $this->detection_method === 'reported';
    }

    public function getAgeInHours(): float
    {
        return $this->created_at->diffInHours(now());
    }

    public function requiresUrgentInvestigation(): bool
    {
        return $this->isHighSeverity() && $this->isActive();
    }

    public function getSeverityLabel(): string
    {
        if ($this->isHighSeverity()) {
            return 'High';
        } elseif ($this->isMediumSeverity()) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    public function getActivityDataValue(string $key, $default = null)
    {
        return $this->activity_data[$key] ?? $default;
    }
}
