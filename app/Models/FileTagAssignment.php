<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FileTagAssignment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'taggable_type',
        'taggable_id',
        'file_tag_id',
        'assigned_by',
    ];

    // Relationships
    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(FileTag::class, 'file_tag_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeForFile($query, string $fileId)
    {
        return $query->where('taggable_type', File::class)->where('taggable_id', $fileId);
    }

    public function scopeForFolder($query, string $folderId)
    {
        return $query->where('taggable_type', Folder::class)->where('taggable_id', $folderId);
    }

    public function scopeByTag($query, string $tagId)
    {
        return $query->where('file_tag_id', $tagId);
    }
}