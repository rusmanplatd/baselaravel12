<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FilePermission extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'permissionable_type',
        'permissionable_id',
        'subject_type',
        'subject_id',
        'permissions',
        'inherited',
        'granted_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'inherited' => 'boolean',
    ];

    // Relationships
    public function permissionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    // Scopes
    public function scopeForFile($query, string $fileId)
    {
        return $query->where('permissionable_type', File::class)->where('permissionable_id', $fileId);
    }

    public function scopeForFolder($query, string $folderId)
    {
        return $query->where('permissionable_type', Folder::class)->where('permissionable_id', $folderId);
    }

    public function scopeForSubject($query, string $subjectType, string $subjectId)
    {
        return $query->where('subject_type', $subjectType)->where('subject_id', $subjectId);
    }

    public function scopeInherited($query)
    {
        return $query->where('inherited', true);
    }

    public function scopeDirect($query)
    {
        return $query->where('inherited', false);
    }

    // Helper methods
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function canRead(): bool
    {
        return $this->hasPermission('read');
    }

    public function canWrite(): bool
    {
        return $this->hasPermission('write');
    }

    public function canDelete(): bool
    {
        return $this->hasPermission('delete');
    }

    public function canShare(): bool
    {
        return $this->hasPermission('share');
    }

    public function canComment(): bool
    {
        return $this->hasPermission('comment');
    }

    public function isInherited(): bool
    {
        return $this->inherited;
    }

    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
        $this->save();
    }

    public static function getAvailablePermissions(): array
    {
        return ['read', 'write', 'delete', 'share', 'comment'];
    }
}