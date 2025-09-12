<?php

namespace App\Models;

use App\Traits\HasScopedRoles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity, HasScopedRoles;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'yjs_state',
        'owner_type',
        'owner_id',
        'folder_id',
        'visibility',
        'is_shared',
        'share_settings',
        'last_edited_by',
        'last_edited_at',
        'version',
        'is_template',
        'template_data',
        'metadata',
        'collaboration_settings',
        'is_collaborative',
        'lock_version',
        'status',
    ];

    protected $casts = [
        'yjs_state' => 'binary',
        'is_shared' => 'boolean',
        'share_settings' => 'array',
        'last_edited_at' => 'datetime',
        'version' => 'integer',
        'is_template' => 'boolean',
        'template_data' => 'array',
        'metadata' => 'array',
        'collaboration_settings' => 'array',
        'is_collaborative' => 'boolean',
        'lock_version' => 'integer',
    ];

    protected $appends = [
        'can_edit',
        'can_comment',
        'is_online',
        'active_collaborators_count',
    ];

    // Polymorphic owner relationship
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    // Collaboration relationships
    public function collaborationSessions(): HasMany
    {
        return $this->hasMany(DocumentCollaborationSession::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class);
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(DocumentCollaborator::class);
    }

    public function activeCollaborators(): HasMany
    {
        return $this->collaborators()->where('last_seen', '>=', now()->subMinutes(5));
    }

    // Polymorphic relationships
    public function shares(): MorphMany
    {
        return $this->morphMany(FileShare::class, 'shareable');
    }

    public function accessLogs(): MorphMany
    {
        return $this->morphMany(FileAccessLog::class, 'file', 'file_type', 'file_id');
    }

    public function tags(): MorphMany
    {
        return $this->morphMany(FileTagAssignment::class, 'taggable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(FileComment::class, 'commentable');
    }

    public function permissions(): MorphMany
    {
        return $this->morphMany(FilePermission::class, 'permissionable');
    }

    // Scopes
    public function scopeForOwner($query, $ownerType, $ownerId)
    {
        return $query->where('owner_type', $ownerType)
                     ->where('owner_id', $ownerId);
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeCollaborative($query)
    {
        return $query->where('is_collaborative', true);
    }

    public function scopeTemplate($query)
    {
        return $query->where('is_template', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecentlyEdited($query, $minutes = 30)
    {
        return $query->where('last_edited_at', '>=', now()->subMinutes($minutes));
    }

    // Helper methods
    public function isOwnedBy(Model $owner): bool
    {
        return $this->owner_type === get_class($owner) && $this->owner_id === $owner->id;
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isShared(): bool
    {
        return $this->is_shared;
    }

    public function isCollaborative(): bool
    {
        return $this->is_collaborative;
    }

    public function isTemplate(): bool
    {
        return $this->is_template;
    }

    public function canBeEditedBy(User $user): bool
    {
        // Owner can always edit
        if ($this->isOwnedBy($user)) {
            return true;
        }

        // Check explicit permissions
        $permission = $this->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canWrite()) {
            return true;
        }

        // Check if user has editor role in collaboration
        return $this->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['editor', 'owner'])
            ->exists();
    }

    public function canBeCommentedBy(User $user): bool
    {
        // If user can edit, they can comment
        if ($this->canBeEditedBy($user)) {
            return true;
        }

        // Check comment-specific permissions
        $permission = $this->permissions()
            ->forSubject(User::class, $user->id)
            ->first();

        if ($permission && $permission->canComment()) {
            return true;
        }

        // Check collaboration role
        return $this->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['editor', 'commenter', 'owner'])
            ->exists();
    }

    public function getCanEditAttribute(): bool
    {
        $user = auth()->user();
        return $user ? $this->canBeEditedBy($user) : false;
    }

    public function getCanCommentAttribute(): bool
    {
        $user = auth()->user();
        return $user ? $this->canBeCommentedBy($user) : false;
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->activeCollaborators()->count() > 0;
    }

    public function getActiveCollaboratorsCountAttribute(): int
    {
        return $this->activeCollaborators()->count();
    }

    public function addCollaborator(User $user, string $role = 'editor'): DocumentCollaborator
    {
        return $this->collaborators()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $role, 'last_seen' => now()]
        );
    }

    public function removeCollaborator(User $user): bool
    {
        return $this->collaborators()->where('user_id', $user->id)->delete();
    }

    public function updateCollaboratorPresence(User $user): void
    {
        $this->collaborators()->where('user_id', $user->id)->update([
            'last_seen' => now(),
        ]);
    }

    public function createRevision(User $user, array $changes = []): DocumentRevision
    {
        return $this->revisions()->create([
            'content' => $this->content,
            'yjs_state' => $this->yjs_state,
            'created_by' => $user->id,
            'version' => $this->version,
            'changes' => $changes,
            'metadata' => [
                'content_length' => strlen($this->content),
                'word_count' => str_word_count(strip_tags($this->content)),
            ],
        ]);
    }

    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->content));
    }

    public function getCharacterCount(): int
    {
        return strlen(strip_tags($this->content));
    }

    public function incrementVersion(): void
    {
        $this->increment('version');
        $this->increment('lock_version');
    }

    public function logAccess(string $action, $user = null, ?string $ipAddress = null, ?string $userAgent = null, array $metadata = []): void
    {
        FileAccessLog::create([
            'file_type' => 'document',
            'file_id' => $this->id,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'action' => $action,
            'metadata' => array_merge($metadata, [
                'document_title' => $this->title,
                'collaboration_status' => $this->is_collaborative,
            ]),
            'accessed_at' => now(),
        ]);
    }

    public function duplicate(string $title = null, Folder $folder = null): Document
    {
        $copy = $this->replicate();
        $copy->title = $title ?? ($this->title . ' (Copy)');
        $copy->slug = null; // Will be generated
        $copy->folder_id = $folder?->id ?? $this->folder_id;
        $copy->yjs_state = null; // Reset Yjs state for new document
        $copy->version = 1;
        $copy->lock_version = 0;
        $copy->last_edited_at = now();
        $copy->status = 'draft';
        
        $copy->save();
        
        return $copy;
    }

    // Activity logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'content', 'visibility', 'is_shared', 'is_collaborative', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($document) {
            if (!$document->slug) {
                $document->slug = \Illuminate\Support\Str::slug($document->title);
            }
            $document->version = 1;
            $document->lock_version = 0;
            $document->last_edited_at = now();
            $document->status = $document->status ?? 'draft';
        });

        static::created(function ($document) {
            if ($document->folder) {
                $document->folder->updateCounts();
            }
        });

        static::updated(function ($document) {
            if ($document->isDirty(['title', 'slug', 'folder_id'])) {
                // Update folder counts if moved
                if ($document->isDirty(['folder_id'])) {
                    if ($document->getOriginal('folder_id')) {
                        $oldFolder = Folder::find($document->getOriginal('folder_id'));
                        $oldFolder?->updateCounts();
                    }
                    
                    if ($document->folder) {
                        $document->folder->updateCounts();
                    }
                }
            }
        });

        static::deleted(function ($document) {
            if ($document->folder) {
                $document->folder->updateCounts();
            }
        });
    }
}