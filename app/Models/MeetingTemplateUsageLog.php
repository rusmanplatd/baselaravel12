<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingTemplateUsageLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'template_id',
        'meeting_id',
        'used_by',
        'applied_settings',
        'modified_settings',
    ];

    protected $casts = [
        'applied_settings' => 'array',
        'modified_settings' => 'array',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(MeetingTemplate::class, 'template_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingCalendarIntegration::class, 'meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    // Scopes
    public function scopeByTemplate($query, $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('used_by', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper Methods
    public function getModificationsSummary(): array
    {
        $modifications = $this->modified_settings ?? [];
        $applied = $this->applied_settings ?? [];

        $summary = [
            'total_modifications' => count($modifications),
            'modification_rate' => 0,
            'changed_settings' => array_keys($modifications),
        ];

        if (count($applied) > 0) {
            $summary['modification_rate'] = round((count($modifications) / count($applied)) * 100, 2);
        }

        return $summary;
    }
}