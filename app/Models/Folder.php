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

class Folder extends Model
{
    use HasFactory, HasUlids, SoftDeletes, LogsActivity, HasScopedRoles;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'owner_type',
        'owner_id',
        'parent_id',
        'path',
        'level',
        'visibility',
        'is_shared',
        'share_settings',
        'total_size',
        'file_count',
        'folder_count',
    ];

    protected $casts = [
        'share_settings' => 'array',
        'is_shared' => 'boolean',
        'total_size' => 'integer',
        'file_count' => 'integer',
        'folder_count' => 'integer',
        'level' => 'integer',
    ];

    // Polymorphic owner relationship
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    // Self-referencing relationships for folder hierarchy
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
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

    public function scopeRootFolders($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByPath($query, string $path)
    {
        return $query->where('path', $path);
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

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;
        
        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }
        
        return $ancestors;
    }

    public function getDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    public function updatePath()
    {
        if ($this->parent) {
            $this->path = $this->parent->path ? $this->parent->path . '/' . $this->slug : $this->slug;
            $this->level = $this->parent->level + 1;
        } else {
            $this->path = $this->slug;
            $this->level = 0;
        }
        
        $this->save();
        
        // Update children paths
        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }

    public function updateCounts()
    {
        $this->file_count = $this->files()->count();
        $this->folder_count = $this->children()->count();
        $this->total_size = $this->files()->sum('size') + 
                           $this->children()->sum('total_size');
        $this->save();
        
        // Update parent counts
        if ($this->parent) {
            $this->parent->updateCounts();
        }
    }

    // Activity logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description', 'visibility', 'is_shared'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($folder) {
            if (!$folder->slug) {
                $folder->slug = \Illuminate\Support\Str::slug($folder->name);
            }
        });

        static::created(function ($folder) {
            $folder->updatePath();
            if ($folder->parent) {
                $folder->parent->updateCounts();
            }
        });

        static::updated(function ($folder) {
            if ($folder->isDirty(['name', 'slug', 'parent_id'])) {
                $folder->updatePath();
            }
        });

        static::deleted(function ($folder) {
            if ($folder->parent) {
                $folder->parent->updateCounts();
            }
        });
    }
}