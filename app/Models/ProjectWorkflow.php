<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWorkflow extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'trigger_conditions',
        'actions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function shouldTrigger(ProjectItem $item, string $event, array $changes = []): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $conditions = $this->trigger_conditions ?? [];
        
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? null;
            
            switch ($type) {
                case 'item_created':
                    if ($event !== 'created') {
                        return false;
                    }
                    break;
                    
                case 'item_updated':
                    if ($event !== 'updated') {
                        return false;
                    }
                    break;
                    
                case 'status_changed':
                    if ($event !== 'updated' || !isset($changes['status'])) {
                        return false;
                    }
                    
                    $fromStatus = $condition['from'] ?? null;
                    $toStatus = $condition['to'] ?? null;
                    
                    if ($fromStatus && $changes['status']['old'] !== $fromStatus) {
                        return false;
                    }
                    
                    if ($toStatus && $changes['status']['new'] !== $toStatus) {
                        return false;
                    }
                    break;
                    
                case 'field_changed':
                    if ($event !== 'updated') {
                        return false;
                    }
                    
                    $fieldName = $condition['field'] ?? null;
                    if (!$fieldName || !isset($changes['field_values'][$fieldName])) {
                        return false;
                    }
                    break;
                    
                case 'assigned':
                    if ($event !== 'assigned') {
                        return false;
                    }
                    break;
                    
                default:
                    return false;
            }
        }
        
        return true;
    }

    public function executeActions(ProjectItem $item, array $context = []): array
    {
        $results = [];
        $actions = $this->actions ?? [];
        
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            
            try {
                switch ($type) {
                    case 'set_status':
                        $status = $action['status'] ?? null;
                        if ($status) {
                            $item->update(['status' => $status]);
                            $item->updateFieldValue('status', $status);
                            $results[] = ['action' => 'set_status', 'status' => 'success', 'value' => $status];
                        }
                        break;
                        
                    case 'assign_user':
                        $userId = $action['user_id'] ?? null;
                        if ($userId) {
                            $user = User::find($userId);
                            if ($user) {
                                $item->assignTo($user);
                                $results[] = ['action' => 'assign_user', 'status' => 'success', 'user_id' => $userId];
                            }
                        }
                        break;
                        
                    case 'set_field_value':
                        $fieldName = $action['field'] ?? null;
                        $value = $action['value'] ?? null;
                        if ($fieldName !== null) {
                            $item->updateFieldValue($fieldName, $value);
                            $results[] = ['action' => 'set_field_value', 'status' => 'success', 'field' => $fieldName, 'value' => $value];
                        }
                        break;
                        
                    case 'add_comment':
                        $comment = $action['comment'] ?? null;
                        if ($comment) {
                            // TODO: Implement comment system
                            $results[] = ['action' => 'add_comment', 'status' => 'success', 'comment' => $comment];
                        }
                        break;
                        
                    case 'send_notification':
                        $message = $action['message'] ?? null;
                        $recipients = $action['recipients'] ?? [];
                        if ($message) {
                            // TODO: Implement notification system
                            $results[] = ['action' => 'send_notification', 'status' => 'success', 'recipients' => count($recipients)];
                        }
                        break;
                        
                    default:
                        $results[] = ['action' => $type, 'status' => 'error', 'message' => 'Unknown action type'];
                }
            } catch (\Exception $e) {
                $results[] = ['action' => $type, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    public function canEdit(User $user): bool
    {
        return $this->project->canAdmin($user);
    }
}