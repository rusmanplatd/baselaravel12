<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAccessLog extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'file_type',
        'file_id',
        'user_id',
        'ip_address',
        'user_agent',
        'action',
        'share_token',
        'metadata',
        'accessed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'accessed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFileAttribute()
    {
        $model = $this->file_type === 'file' ? File::class : Folder::class;
        return $model::find($this->file_id);
    }

    // Scopes
    public function scopeForFile($query, string $fileId)
    {
        return $query->where('file_type', 'file')->where('file_id', $fileId);
    }

    public function scopeForFolder($query, string $folderId)
    {
        return $query->where('file_type', 'folder')->where('file_id', $folderId);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('accessed_at', '>=', now()->subHours($hours));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('accessed_at', today());
    }

    // Helper methods
    public function isAnonymous(): bool
    {
        return is_null($this->user_id);
    }

    public function wasAccessedViaShare(): bool
    {
        return !is_null($this->share_token);
    }

    public function getBrowser(): ?string
    {
        return $this->metadata['browser'] ?? null;
    }

    public function getOperatingSystem(): ?string
    {
        return $this->metadata['os'] ?? null;
    }
}