<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectField extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'options',
        'settings',
        'sort_order',
        'is_required',
        'is_system',
    ];

    protected $casts = [
        'options' => 'array',
        'settings' => 'array',
        'is_required' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function isSelectType(): bool
    {
        return in_array($this->type, ['single_select', 'multi_select']);
    }

    public function validateValue($value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        switch ($this->type) {
            case 'number':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'single_select':
                return !$this->options || in_array($value, $this->options);
            case 'multi_select':
                if (!is_array($value)) {
                    return false;
                }
                return !$this->options || empty(array_diff($value, $this->options));
            default:
                return true;
        }
    }

    public function getDefaultValue()
    {
        return $this->settings['default_value'] ?? null;
    }

    public function getDisplayName(): string
    {
        return $this->settings['display_name'] ?? $this->name;
    }
}