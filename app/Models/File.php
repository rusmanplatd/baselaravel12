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
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class File extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity, HasScopedRoles;

    protected $fillable = [
        'name',
        'original_name',
        'description',
        'owner_type',
        'owner_id',
        'folder_id',
        'mime_type',
        'extension',
        'size',
        'hash',
        'disk',
        'path',
        'is_encrypted',
        'encryption_metadata',
        'thumbnail_path',
        'preview_path',
        'has_preview',
        'visibility',
        'is_shared',
        'share_settings',
        'metadata',
        'download_count',
        'last_accessed_at',
        'version',
        'parent_file_id',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_encrypted' => 'boolean',
        'encryption_metadata' => 'array',
        'has_preview' => 'boolean',
        'is_shared' => 'boolean',
        'share_settings' => 'array',
        'metadata' => 'array',
        'download_count' => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    protected $appends = [
        'url',
        'thumbnail_url',
        'preview_url',
        'human_size',
        'is_image',
        'is_video',
        'is_audio',
        'is_document',
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

    // Versioning relationships
    public function parentFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'parent_file_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(File::class, 'parent_file_id');
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

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function scopeAudios($query)
    {
        return $query->where('mime_type', 'like', 'audio/%');
    }

    public function scopeDocuments($query)
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ]);
    }

    public function scopeByMimeType($query, string $mimeType)
    {
        return $query->where('mime_type', $mimeType);
    }

    public function scopeByExtension($query, string $extension)
    {
        return $query->where('extension', $extension);
    }

    // Accessors
    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::disk($this->disk)->url($this->thumbnail_path) : null;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return $this->preview_path ? Storage::disk($this->disk)->url($this->preview_path) : null;
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->formatBytes($this->size);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function getIsAudioAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function getIsDocumentAttribute(): bool
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ];

        return in_array($this->mime_type, $documentTypes);
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

    public function isVersion(): bool
    {
        return !is_null($this->parent_file_id);
    }

    public function hasVersions(): bool
    {
        return $this->versions()->count() > 0;
    }

    public function getLatestVersion(): ?File
    {
        return $this->versions()->latest()->first();
    }

    public function getAllVersions()
    {
        $versions = collect([$this]);
        return $versions->merge($this->versions);
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);
    }

    public function logAccess(string $action, $user = null, ?string $ipAddress = null, ?string $userAgent = null, array $metadata = []): void
    {
        FileAccessLog::create([
            'file_type' => 'file',
            'file_id' => $this->id,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'action' => $action,
            'metadata' => $metadata,
            'accessed_at' => now(),
        ]);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function delete(): bool
    {
        // Delete physical files
        if ($this->path && Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }

        if ($this->thumbnail_path && Storage::disk($this->disk)->exists($this->thumbnail_path)) {
            Storage::disk($this->disk)->delete($this->thumbnail_path);
        }

        if ($this->preview_path && Storage::disk($this->disk)->exists($this->preview_path)) {
            Storage::disk($this->disk)->delete($this->preview_path);
        }

        return parent::delete();
    }

    public function copy(Folder $targetFolder = null, string $newName = null): File
    {
        $copy = $this->replicate();
        $copy->name = $newName ?? ($this->name . ' (Copy)');
        $copy->folder_id = $targetFolder?->id ?? $this->folder_id;
        
        // Copy the physical file
        $newPath = $this->generatePath($copy->name);
        Storage::disk($this->disk)->copy($this->path, $newPath);
        $copy->path = $newPath;
        
        $copy->save();
        
        return $copy;
    }

    public function move(Folder $targetFolder): bool
    {
        $this->folder_id = $targetFolder->id;
        return $this->save();
    }

    protected function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    protected function generatePath(string $filename): string
    {
        $date = now()->format('Y/m/d');
        $hash = substr(md5($filename . time()), 0, 8);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        return "{$date}/{$hash}.{$extension}";
    }

    // Activity logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'visibility', 'is_shared', 'folder_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        static::created(function ($file) {
            if ($file->folder) {
                $file->folder->updateCounts();
            }
        });

        static::updated(function ($file) {
            if ($file->isDirty(['folder_id'])) {
                // Update counts for old and new folders
                if ($file->getOriginal('folder_id')) {
                    $oldFolder = Folder::find($file->getOriginal('folder_id'));
                    $oldFolder?->updateCounts();
                }
                
                if ($file->folder) {
                    $file->folder->updateCounts();
                }
            }
        });

        static::deleted(function ($file) {
            if ($file->folder) {
                $file->folder->updateCounts();
            }
        });
    }
}