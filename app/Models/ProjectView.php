<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectView extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'layout',
        'filters',
        'sort',
        'group_by',
        'visible_fields',
        'settings',
        'is_default',
        'is_public',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'sort' => 'array',
        'group_by' => 'array',
        'visible_fields' => 'array',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('is_public', true)
                ->orWhere('created_by', $user->id);
        });
    }

    public function isTableLayout(): bool
    {
        return $this->layout === 'table';
    }

    public function isBoardLayout(): bool
    {
        return $this->layout === 'board';
    }

    public function isTimelineLayout(): bool
    {
        return $this->layout === 'timeline';
    }

    public function isRoadmapLayout(): bool
    {
        return $this->layout === 'roadmap';
    }

    public function canEdit(User $user): bool
    {
        return $this->created_by === $user->id || $this->project->canAdmin($user);
    }

    public function makeDefault(): void
    {
        // Remove default from other views
        $this->project->views()->where('id', '!=', $this->id)->update(['is_default' => false]);
        
        // Set this as default
        $this->update(['is_default' => true]);
    }

    public function applyFilters($query)
    {
        if (!$this->filters) {
            return $query;
        }

        foreach ($this->filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) {
                continue;
            }

            switch ($operator) {
                case 'equals':
                    if ($field === 'status' || $field === 'type') {
                        $query->where($field, $value);
                    } else {
                        $query->whereJsonContains("field_values->{$field}", $value);
                    }
                    break;
                case 'not_equals':
                    if ($field === 'status' || $field === 'type') {
                        $query->where($field, '!=', $value);
                    } else {
                        $query->whereJsonDoesntContain("field_values->{$field}", $value);
                    }
                    break;
                case 'contains':
                    if ($field === 'title' || $field === 'description') {
                        $query->where($field, 'like', "%{$value}%");
                    }
                    break;
                case 'assigned_to':
                    $query->whereHas('assignees', function ($q) use ($value) {
                        $q->where('user_id', $value);
                    });
                    break;
            }
        }

        return $query;
    }

    public function applySorting($query)
    {
        if (!$this->sort) {
            return $query->orderBy('sort_order');
        }

        foreach ($this->sort as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';

            if (!$field) {
                continue;
            }

            if (in_array($field, ['title', 'status', 'type', 'created_at', 'updated_at', 'sort_order'])) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy("field_values->{$field}", $direction);
            }
        }

        return $query;
    }

    public function getFilteredItems()
    {
        $query = $this->project->items()->active();
        
        $query = $this->applyFilters($query);
        $query = $this->applySorting($query);

        return $query;
    }
}