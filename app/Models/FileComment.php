<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FileComment extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'content',
        'metadata',
        'parent_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FileComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(FileComment::class, 'parent_id');
    }

    // Scopes
    public function scopeForFile($query, string $fileId)
    {
        return $query->where('commentable_type', File::class)->where('commentable_id', $fileId);
    }

    public function scopeForFolder($query, string $folderId)
    {
        return $query->where('commentable_type', Folder::class)->where('commentable_id', $folderId);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function hasReplies(): bool
    {
        return $this->replies()->count() > 0;
    }

    public function getAllReplies()
    {
        $replies = collect();
        
        foreach ($this->replies as $reply) {
            $replies->push($reply);
            $replies = $replies->merge($reply->getAllReplies());
        }
        
        return $replies;
    }

    public function getMentionedUsers(): array
    {
        return $this->metadata['mentions'] ?? [];
    }

    public function getAttachments(): array
    {
        return $this->metadata['attachments'] ?? [];
    }

    // Activity logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['content', 'commentable_type', 'commentable_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}