<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatBackup extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'backup_type',
        'export_format',
        'backup_scope',
        'date_range',
        'encryption_settings',
        'status',
        'status_message',
        'progress_percentage',
        'total_items',
        'processed_items',
        'backup_file_path',
        'backup_file_hash',
        'backup_file_size',
        'include_attachments',
        'include_metadata',
        'preserve_encryption',
        'started_at',
        'completed_at',
        'expires_at',
        'error_log',
    ];

    protected $casts = [
        'backup_scope' => 'array',
        'date_range' => 'array',
        'encryption_settings' => 'array',
        'progress_percentage' => 'integer',
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'backup_file_size' => 'integer',
        'include_attachments' => 'boolean',
        'include_metadata' => 'boolean',
        'preserve_encryption' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'error_log' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(BackupVerification::class, 'backup_id');
    }

    public function restorations(): HasMany
    {
        return $this->hasMany(BackupRestoration::class, 'backup_id');
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

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('backup_type', $type);
    }

    public function scopeByFormat($query, string $format)
    {
        return $query->where('export_format', $format);
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

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isFullAccount(): bool
    {
        return $this->backup_type === 'full_account';
    }

    public function isConversationBackup(): bool
    {
        return $this->backup_type === 'conversation';
    }

    public function isDateRangeBackup(): bool
    {
        return $this->backup_type === 'date_range';
    }

    public function getProgressPercentage(): int
    {
        return $this->progress_percentage;
    }

    public function getProcessedItems(): int
    {
        return $this->processed_items;
    }

    public function getTotalItems(): int
    {
        return $this->total_items ?? 0;
    }

    public function getRemainingItems(): int
    {
        return max(0, $this->getTotalItems() - $this->getProcessedItems());
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

    public function getFileSizeFormatted(): ?string
    {
        if (! $this->backup_file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->backup_file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2).' '.$units[$unitIndex];
    }

    public function hasBackupFile(): bool
    {
        return ! empty($this->backup_file_path) && file_exists($this->backup_file_path);
    }

    public function includesMessages(): bool
    {
        return in_array('messages', $this->backup_scope);
    }

    public function includesFiles(): bool
    {
        return in_array('files', $this->backup_scope);
    }

    public function includesPolls(): bool
    {
        return in_array('polls', $this->backup_scope);
    }

    public function includesSurveys(): bool
    {
        return in_array('surveys', $this->backup_scope);
    }

    public function includesReactions(): bool
    {
        return in_array('reactions', $this->backup_scope);
    }

    public function preservesEncryption(): bool
    {
        return $this->preserve_encryption;
    }

    public function includesAttachments(): bool
    {
        return $this->include_attachments;
    }

    public function includesMetadata(): bool
    {
        return $this->include_metadata;
    }

    public function getDateRangeStart(): ?string
    {
        return $this->date_range['start'] ?? null;
    }

    public function getDateRangeEnd(): ?string
    {
        return $this->date_range['end'] ?? null;
    }

    public function getEncryptionAlgorithm(): string
    {
        return $this->encryption_settings['algorithm'] ?? 'aes-256-gcm';
    }

    public function isVerified(): bool
    {
        return $this->verification && $this->verification->is_verified;
    }

    public function canBeDownloaded(): bool
    {
        return $this->isCompleted() &&
               ! $this->isExpired() &&
               $this->hasBackupFile();
    }

    public function canBeRestored(): bool
    {
        return $this->isCompleted() &&
               $this->hasBackupFile() &&
               $this->isVerified();
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress_percentage' => 0,
        ]);
    }

    public function markAsCompleted(string $filePath, string $fileHash, int $fileSize): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
            'backup_file_path' => $filePath,
            'backup_file_hash' => $fileHash,
            'backup_file_size' => $fileSize,
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

    public function updateProgress(int $processedItems, ?string $statusMessage = null): void
    {
        $percentage = $this->total_items > 0
            ? round(($processedItems / $this->total_items) * 100)
            : 0;

        $this->update([
            'processed_items' => $processedItems,
            'progress_percentage' => min(100, $percentage),
            'status_message' => $statusMessage,
        ]);
    }
}
