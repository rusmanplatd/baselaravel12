<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class FileTag extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'owner_type',
        'owner_id',
    ];

    // Relationships
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FileTagAssignment::class);
    }

    // Scopes
    public function scopeForOwner($query, $ownerType, $ownerId)
    {
        return $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return is_null($this->owner_type) && is_null($this->owner_id);
    }

    public function getUsageCount(): int
    {
        return $this->assignments()->count();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (!$tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name') && !$tag->isDirty('slug')) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}