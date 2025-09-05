<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Participant extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'conversation_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'device_id',
        'role',
        'permissions',
        'is_muted',
        'has_notifications',
        'joined_at',
        'left_at',
        'last_read_at',
        'last_read_message_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_muted' => 'boolean',
        'has_notifications' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];

    protected $attributes = [
        'role' => 'member',
        'is_muted' => false,
        'has_notifications' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['role', 'status', 'permissions', 'notification_settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Participant {$eventName}")
            ->useLogName('chat');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeModerators($query)
    {
        return $query->whereIn('role', ['admin', 'moderator']);
    }

    public function scopeMembers($query)
    {
        return $query->where('role', 'member');
    }

    public function scopeMuted($query)
    {
        return $query->where('is_muted', true);
    }

    public function scopeNotMuted($query)
    {
        return $query->where('is_muted', false);
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function isActive(): bool
    {
        return ! $this->left_at;
    }

    public function isMuted(): bool
    {
        return $this->is_muted;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true; // Admins have all permissions
        }

        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions);
    }

    public function canSendMessages(): bool
    {
        return $this->isActive() && $this->hasPermission('send_messages');
    }

    public function canDeleteMessages(): bool
    {
        return $this->isModerator() || $this->hasPermission('delete_messages');
    }

    public function canAddMembers(): bool
    {
        return $this->isModerator() || $this->hasPermission('add_members');
    }

    public function canRemoveMembers(): bool
    {
        return $this->isModerator() || $this->hasPermission('remove_members');
    }

    public function canManageRoles(): bool
    {
        return $this->isAdmin() || $this->hasPermission('manage_roles');
    }

    public function leave(): void
    {
        $this->update([
            'left_at' => now(),
        ]);
    }

    public function mute(): void
    {
        $this->update(['is_muted' => true]);
    }

    public function unmute(): void
    {
        $this->update(['is_muted' => false]);
    }

    public function updateLastRead(?string $messageId = null): void
    {
        $this->update([
            'last_read_message_id' => $messageId,
            'last_read_at' => now(),
        ]);
    }

    public function getUnreadCount(): int
    {
        if (! $this->last_read_message_id) {
            return $this->conversation->messages()->count();
        }

        $lastReadMessage = Message::find($this->last_read_message_id);
        if (! $lastReadMessage) {
            return 0;
        }

        return $this->conversation->messages()
            ->where('created_at', '>', $lastReadMessage->created_at)
            ->count();
    }
}
