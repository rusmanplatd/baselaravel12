<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestoration extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'backup_id',
        'restoration_type',
        'source_file_path',
        'source_file_hash',
        'restoration_scope',
        'status',
        'status_message',
        'progress_percentage',
        'total_items',
        'restored_items',
        'started_at',
        'completed_at',
        'restoration_log',
        'error_log',
    ];

    protected $casts = [
        'restoration_scope' => 'array',
        'progress_percentage' => 'integer',
        'total_items' => 'integer',
        'restored_items' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'restoration_log' => 'array',
        'error_log' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(ChatBackup::class, 'backup_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('restoration_type', $type);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isFullRestoration(): bool
    {
        return $this->restoration_type === 'full';
    }

    public function isSelectiveRestoration(): bool
    {
        return $this->restoration_type === 'selective';
    }

    public function isConversationRestoration(): bool
    {
        return $this->restoration_type === 'conversation';
    }

    public function getProgressPercentage(): int
    {
        return $this->progress_percentage;
    }

    public function getRestoredItems(): int
    {
        return $this->restored_items;
    }

    public function getTotalItems(): int
    {
        return $this->total_items ?? 0;
    }

    public function getRemainingItems(): int
    {
        return max(0, $this->getTotalItems() - $this->getRestoredItems());
    }

    public function getDurationInSeconds(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function getDurationFormatted(): ?string
    {
        $seconds = $this->getDurationInSeconds();
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes.'m '.$remainingSeconds.'s';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours.'h '.$remainingMinutes.'m';
    }

    public function includesMessages(): bool
    {
        return in_array('messages', $this->restoration_scope);
    }

    public function includesFiles(): bool
    {
        return in_array('files', $this->restoration_scope);
    }

    public function includesPolls(): bool
    {
        return in_array('polls', $this->restoration_scope);
    }

    public function includesSurveys(): bool
    {
        return in_array('surveys', $this->restoration_scope);
    }

    public function includesReactions(): bool
    {
        return in_array('reactions', $this->restoration_scope);
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress_percentage' => 0,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);
    }

    public function markAsFailed(string $errorMessage, array $errorLog = []): void
    {
        $this->update([
            'status' => 'failed',
            'status_message' => $errorMessage,
            'error_log' => array_merge($this->error_log ?? [], $errorLog),
        ]);
    }

    public function updateProgress(int $restoredItems, ?string $statusMessage = null): void
    {
        $percentage = $this->total_items > 0
            ? round(($restoredItems / $this->total_items) * 100)
            : 0;

        $updates = [
            'restored_items' => $restoredItems,
            'progress_percentage' => min(100, $percentage),
        ];

        if ($statusMessage) {
            $updates['status_message'] = $statusMessage;
        }

        $this->update($updates);
    }

    public function addToLog(string $action, array $data = []): void
    {
        $logEntry = [
            'timestamp' => now()->toISOString(),
            'action' => $action,
            'data' => $data,
        ];

        $currentLog = $this->restoration_log ?? [];
        $currentLog[] = $logEntry;

        $this->update(['restoration_log' => $currentLog]);
    }
}
