<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectItem extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'type',
        'status',
        'sort_order',
        'field_values',
        'created_by',
        'updated_by',
        'completed_at',
        'archived_at',
        'iteration_id',
        'labels',
        'estimate',
        'progress',
    ];

    protected $casts = [
        'field_values' => 'array',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'labels' => 'array',
        'progress' => 'decimal:2',
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

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_item_assignees')
            ->withTimestamps();
    }

    public function iteration(): BelongsTo
    {
        return $this->belongsTo(ProjectIteration::class, 'iteration_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'done')->whereNotNull('completed_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function assignTo(User $user): void
    {
        $this->assignees()->syncWithoutDetaching([$user->id]);
        $this->updateFieldValue('assignees', $this->assignees->pluck('id')->toArray());
    }

    public function unassignFrom(User $user): void
    {
        $this->assignees()->detach($user->id);
        $this->updateFieldValue('assignees', $this->assignees->pluck('id')->toArray());
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
        $this->updateFieldValue('status', 'done');
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'todo',
            'completed_at' => null,
        ]);
        $this->updateFieldValue('status', 'todo');
    }

    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }

    public function unarchive(): void
    {
        $this->update([
            'status' => 'todo',
            'archived_at' => null,
        ]);
    }

    public function updateFieldValue(string $fieldName, $value): void
    {
        $fieldValues = $this->field_values ?? [];
        $fieldValues[$fieldName] = $value;
        $this->update(['field_values' => $fieldValues]);
    }

    public function getFieldValue(string $fieldName, $default = null)
    {
        return $this->field_values[$fieldName] ?? $default;
    }

    public function validateFieldValues(): array
    {
        $errors = [];
        $fields = $this->project->fields;

        foreach ($fields as $field) {
            $value = $this->getFieldValue($field->name);
            
            if (!$field->validateValue($value)) {
                $errors[$field->name] = "Invalid value for {$field->getDisplayName()}";
            }
        }

        return $errors;
    }

    public function canEdit(User $user): bool
    {
        return $user->hasPermissionTo('project.item.edit', $this->project);
    }

    public function canDelete(User $user): bool
    {
        return $user->hasPermissionTo('project.item.delete', $this->project);
    }

    public function canAssign(User $user): bool
    {
        return $user->hasPermissionTo('project.item.assign', $this->project);
    }

    public function canChangeStatus(User $user): bool
    {
        return $user->hasPermissionTo('project.item.status', $this->project);
    }

    public function canArchive(User $user): bool
    {
        return $user->hasPermissionTo('project.item.archive', $this->project);
    }

    public function canConvert(User $user): bool
    {
        return $user->hasPermissionTo('project.item.convert', $this->project);
    }

    public function convertToIssue(): void
    {
        if ($this->isDraft()) {
            $this->update(['type' => 'issue']);
        }
    }

    public function addLabel(string $label): void
    {
        $labels = $this->labels ?? [];
        if (!in_array($label, $labels)) {
            $labels[] = $label;
            $this->update(['labels' => $labels]);
        }
    }

    public function removeLabel(string $label): void
    {
        $labels = $this->labels ?? [];
        $labels = array_values(array_filter($labels, fn($l) => $l !== $label));
        $this->update(['labels' => $labels]);
    }

    public function hasLabel(string $label): bool
    {
        return in_array($label, $this->labels ?? []);
    }

    public function updateProgress(float $progress): void
    {
        $this->update(['progress' => max(0, min(100, $progress))]);
    }

    public function setEstimate(int $estimate): void
    {
        $this->update(['estimate' => $estimate]);
    }

    public function assignToIteration(ProjectIteration $iteration): void
    {
        $this->update(['iteration_id' => $iteration->id]);
    }

    public function removeFromIteration(): void
    {
        $this->update(['iteration_id' => null]);
    }

    public function scopeInIteration($query, $iterationId)
    {
        return $query->where('iteration_id', $iterationId);
    }

    public function scopeWithLabel($query, string $label)
    {
        return $query->where('labels', 'like', '%"' . $label . '"%');
    }

    public function scopeWithEstimate($query)
    {
        return $query->whereNotNull('estimate');
    }

    public function scopeByProgress($query, $operator = '>=', $value = 0)
    {
        return $query->where('progress', $operator, $value);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->where('user_id', $user->id)->exists();
    }

    public function isDraft(): bool
    {
        return $this->type === 'draft_issue';
    }

    public function isPullRequest(): bool
    {
        return $this->type === 'pull_request';
    }

    public function isIssue(): bool
    {
        return $this->type === 'issue';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'status', 'type', 'field_values'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Project item {$eventName}")
            ->useLogName('project_item');
    }
}