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
    ];

    protected $casts = [
        'field_values' => 'array',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
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
        return $this->project->canEdit($user);
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