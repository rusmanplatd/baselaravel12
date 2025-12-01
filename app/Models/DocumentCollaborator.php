<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCollaborator extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'document_id',
        'user_id',
        'role',
        'permissions',
        'last_seen',
        'cursor_position',
        'selection_range',
        'is_anonymous',
        'anonymous_name',
        'anonymous_color',
        'metadata',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_seen' => 'datetime',
        'cursor_position' => 'array',
        'selection_range' => 'array',
        'is_anonymous' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'is_online',
        'display_name',
        'avatar_color',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeOnline($query, $minutes = 5)
    {
        return $query->where('last_seen', '>=', now()->subMinutes($minutes));
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeAnonymous($query)
    {
        return $query->where('is_anonymous', true);
    }

    public function scopeAuthenticated($query)
    {
        return $query->where('is_anonymous', false);
    }

    // Helper methods
    public function isOnline(int $minutes = 5): bool
    {
        return $this->last_seen && $this->last_seen >= now()->subMinutes($minutes);
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return $this->anonymous_name ?? 'Anonymous User';
        }

        return $this->user ? $this->user->name : 'Unknown User';
    }

    public function getAvatarColorAttribute(): string
    {
        if ($this->is_anonymous && $this->anonymous_color) {
            return $this->anonymous_color;
        }

        // Generate a consistent color based on user ID or anonymous name
        $seed = $this->user_id ?? $this->anonymous_name ?? $this->id;
        $colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
        ];
        
        return $colors[crc32($seed) % count($colors)];
    }

    public function canRead(): bool
    {
        return in_array($this->role, ['viewer', 'commenter', 'editor', 'owner']);
    }

    public function canComment(): bool
    {
        return in_array($this->role, ['commenter', 'editor', 'owner']);
    }

    public function canEdit(): bool
    {
        return in_array($this->role, ['editor', 'owner']);
    }

    public function canManageCollaborators(): bool
    {
        return $this->role === 'owner';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function updatePresence(array $cursorPosition = null, array $selectionRange = null): void
    {
        $this->update([
            'last_seen' => now(),
            'cursor_position' => $cursorPosition,
            'selection_range' => $selectionRange,
        ]);
    }

    public function setRole(string $role): void
    {
        $allowedRoles = ['viewer', 'commenter', 'editor', 'owner'];
        
        if (!in_array($role, $allowedRoles)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $this->role = $role;
        $this->save();
    }

    public static function getAvailableRoles(): array
    {
        return [
            'viewer' => 'Can view the document',
            'commenter' => 'Can view and comment on the document',
            'editor' => 'Can view, comment, and edit the document',
            'owner' => 'Full control over the document and collaborators',
        ];
    }

    public static function getDefaultPermissions(): array
    {
        return ['read', 'comment', 'edit'];
    }
}