<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Storage;

class MeetingRecording extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'breakout_room_id',
        'initiated_by',
        'recording_id',
        'recording_name',
        'description',
        'recording_type',
        'status',
        'started_at',
        'stopped_at',
        'processing_completed_at',
        'duration_seconds',
        'file_path',
        'file_url',
        'file_size',
        'file_format',
        'video_metadata',
        'video_resolution',
        'video_bitrate',
        'audio_bitrate',
        'video_codec',
        'audio_codec',
        'layout_type',
        'layout_settings',
        'participant_layout',
        'is_public',
        'access_permissions',
        'share_token',
        'share_token_expires_at',
        'processing_details',
        'processing_error',
        'retry_count',
        'last_retry_at',
        'view_count',
        'download_count',
        'last_accessed_at',
        'analytics_data',
        'auto_delete_at',
        'retention_policy',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'processing_completed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'share_token_expires_at' => 'datetime',
        'auto_delete_at' => 'datetime',
        'archived_at' => 'datetime',
        'video_metadata' => 'array',
        'layout_settings' => 'array',
        'participant_layout' => 'array',
        'access_permissions' => 'array',
        'processing_details' => 'array',
        'analytics_data' => 'array',
        'duration_seconds' => 'integer',
        'file_size' => 'integer',
        'video_bitrate' => 'integer',
        'audio_bitrate' => 'integer',
        'retry_count' => 'integer',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'is_public' => 'boolean',
        'is_archived' => 'boolean',
    ];

    // Relationships
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingCalendarIntegration::class, 'meeting_id');
    }

    public function breakoutRoom(): BelongsTo
    {
        return $this->belongsTo(MeetingBreakoutRoom::class, 'breakout_room_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingRecordingParticipant::class, 'recording_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(MeetingRecordingSegment::class, 'recording_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(MeetingRecordingAccessLog::class, 'recording_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeProcessing($query)
    {
        return $query->whereIn('status', ['starting', 'recording', 'processing']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByMeeting($query, $meetingId)
    {
        return $query->where('meeting_id', $meetingId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('recording_type', $type);
    }

    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('is_public', true)
              ->orWhere('initiated_by', $user->id)
              ->orWhereJsonContains('access_permissions->users', $user->id);
        });
    }

    public function scopeScheduledForDeletion($query)
    {
        return $query->whereNotNull('auto_delete_at')
            ->where('auto_delete_at', '<=', now());
    }

    // Helper Methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['starting', 'recording', 'processing']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeAccessed(): bool
    {
        return $this->isCompleted() && !$this->is_archived;
    }

    public function startRecording(): void
    {
        $this->update([
            'status' => 'recording',
            'started_at' => now(),
        ]);
    }

    public function stopRecording(): void
    {
        $duration = null;
        if ($this->started_at) {
            $duration = $this->started_at->diffInSeconds(now());
        }

        $this->update([
            'status' => 'processing',
            'stopped_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    public function markCompleted(array $fileInfo = []): void
    {
        $updateData = [
            'status' => 'completed',
            'processing_completed_at' => now(),
        ];

        if (!empty($fileInfo)) {
            $updateData = array_merge($updateData, $fileInfo);
        }

        // Set auto-delete date based on retention policy
        if (!$this->auto_delete_at) {
            $updateData['auto_delete_at'] = $this->calculateAutoDeleteDate();
        }

        $this->update($updateData);
    }

    public function markFailed(string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'processing_error' => $error,
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function retry(): void
    {
        if (!$this->canRetry()) {
            throw new \Exception('Recording cannot be retried');
        }

        $this->update([
            'status' => 'processing',
            'processing_error' => null,
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    public function generateShareToken(int $expiresInHours = 24): string
    {
        $token = Str::random(64);
        
        $this->update([
            'share_token' => $token,
            'share_token_expires_at' => now()->addHours($expiresInHours),
        ]);

        return $token;
    }

    public function revokeShareToken(): void
    {
        $this->update([
            'share_token' => null,
            'share_token_expires_at' => null,
        ]);
    }

    public function isShareTokenValid(string $token): bool
    {
        return $this->share_token === $token &&
               $this->share_token_expires_at &&
               $this->share_token_expires_at->isFuture();
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
        $this->touch('last_accessed_at');
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->touch('last_accessed_at');
    }

    public function logAccess(User $user = null, string $accessType = 'view', array $metadata = []): void
    {
        $this->accessLogs()->create([
            'user_id' => $user?->id,
            'access_type' => $accessType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'accessed_at' => now(),
            'access_method' => 'web',
            'access_metadata' => $metadata,
            'access_granted' => true,
        ]);

        if ($accessType === 'view') {
            $this->incrementViewCount();
        } elseif ($accessType === 'download') {
            $this->incrementDownloadCount();
        }
    }

    public function canBeAccessedBy(User $user = null): bool
    {
        // Public recordings are accessible to anyone
        if ($this->is_public && $this->canBeAccessed()) {
            return true;
        }

        // No user provided
        if (!$user) {
            return false;
        }

        // Recording initiator can always access
        if ($this->initiated_by === $user->id) {
            return true;
        }

        // Check access permissions
        $permissions = $this->access_permissions ?? [];
        if (in_array($user->id, $permissions['users'] ?? [])) {
            return true;
        }

        // Check if user was a meeting attendee
        if ($this->meeting->attendees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function getFileUrl(bool $forceRefresh = false): ?string
    {
        if (!$this->file_path || !$this->canBeAccessed()) {
            return null;
        }

        // Return cached URL if still valid
        if (!$forceRefresh && $this->file_url) {
            return $this->file_url;
        }

        // Generate new signed URL (valid for 1 hour)
        try {
            $url = Storage::disk('minio')->temporaryUrl(
                $this->file_path,
                now()->addHour()
            );

            // Cache the URL
            $this->update(['file_url' => $url]);

            return $url;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getRecordingInfo(): array
    {
        return [
            'id' => $this->id,
            'recording_id' => $this->recording_id,
            'name' => $this->recording_name,
            'description' => $this->description,
            'type' => $this->recording_type,
            'status' => $this->status,
            'duration' => $this->getDurationFormatted(),
            'file_size' => $this->getFileSizeFormatted(),
            'file_format' => $this->file_format,
            'video_resolution' => $this->video_resolution,
            'layout_type' => $this->layout_type,
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->processing_completed_at,
            'is_public' => $this->is_public,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
            'last_accessed' => $this->last_accessed_at,
            'auto_delete_at' => $this->auto_delete_at,
            'retention_policy' => $this->retention_policy,
            'participant_count' => $this->participants()->count(),
            'segment_count' => $this->segments()->count(),
        ];
    }

    public function getDurationFormatted(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $hours = intval($this->duration_seconds / 3600);
        $minutes = intval(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFileSizeFormatted(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function archive(): void
    {
        $this->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }

    public function unarchive(): void
    {
        $this->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);
    }

    public function deleteRecording(): bool
    {
        try {
            // Delete file from storage
            if ($this->file_path && Storage::disk('minio')->exists($this->file_path)) {
                Storage::disk('minio')->delete($this->file_path);
            }

            // Delete segments
            foreach ($this->segments as $segment) {
                if ($segment->segment_file_path && Storage::disk('minio')->exists($segment->segment_file_path)) {
                    Storage::disk('minio')->delete($segment->segment_file_path);
                }
            }

            // Delete the record
            $this->delete();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function calculateAutoDeleteDate(): ?Carbon
    {
        return match ($this->retention_policy) {
            'keep_forever' => null,
            'delete_after_30_days' => now()->addDays(30),
            'delete_after_90_days' => now()->addDays(90),
            'delete_after_1_year' => now()->addYear(),
            default => now()->addDays(90), // Default fallback
        };
    }

    public static function cleanupExpiredRecordings(): int
    {
        $expiredRecordings = self::scheduledForDeletion()->get();
        $deletedCount = 0;

        foreach ($expiredRecordings as $recording) {
            if ($recording->deleteRecording()) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}