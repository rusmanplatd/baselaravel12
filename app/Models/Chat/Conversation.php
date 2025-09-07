<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Conversation extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'name',
        'type',
        'privacy',
        'username',
        'description',
        'welcome_message',
        'avatar_url',
        'settings',
        'group_settings',
        'member_limit',
        'can_members_add_others',
        'require_approval_to_join',
        'show_member_count',
        'allow_anonymous_viewing',
        'created_by_user_id',
        'created_by_device_id',
        'organization_id',
        'is_active',
        'last_activity_at',
        'last_message_at',
        'last_message_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'group_settings' => 'array',
        'is_active' => 'boolean',
        'can_members_add_others' => 'boolean',
        'require_approval_to_join' => 'boolean',
        'show_member_count' => 'boolean',
        'allow_anonymous_viewing' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'direct',
        'privacy' => 'private',
        'is_active' => true,
        'can_members_add_others' => false,
        'require_approval_to_join' => true,
        'show_member_count' => true,
        'allow_anonymous_viewing' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'description', 'is_active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Conversation {$eventName}")
            ->useLogName('chat');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id')->active();
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(EncryptionKey::class, 'conversation_id');
    }

    public function videoCalls(): HasMany
    {
        return $this->hasMany(VideoCall::class, 'conversation_id');
    }

    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(\App\Models\GroupInvitation::class, 'conversation_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(\App\Models\GroupJoinRequest::class, 'conversation_id');
    }

    public function inviteLinks(): HasMany
    {
        return $this->hasMany(\App\Models\GroupInviteLink::class, 'conversation_id');
    }

    public function adminLogs(): HasMany
    {
        return $this->hasMany(\App\Models\GroupAdminLog::class, 'conversation_id');
    }

    public function pinnedMessages(): HasMany
    {
        return $this->hasMany(\App\Models\GroupPinnedMessage::class, 'conversation_id');
    }

    public function memberRestrictions(): HasMany
    {
        return $this->hasMany(\App\Models\GroupMemberRestriction::class, 'conversation_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(\App\Models\GroupTopic::class, 'conversation_id');
    }

    public function scheduledMessages(): HasMany
    {
        return $this->hasMany(\App\Models\GroupScheduledMessage::class, 'conversation_id');
    }

    public function autoModerationRules(): HasMany
    {
        return $this->hasMany(\App\Models\GroupAutoModerationRule::class, 'conversation_id');
    }

    public function bannedUsers(): HasMany
    {
        return $this->hasMany(\App\Models\GroupBannedUser::class, 'conversation_id');
    }

    public function memberHistory(): HasMany
    {
        return $this->hasMany(\App\Models\GroupMemberHistory::class, 'conversation_id');
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(\App\Models\GroupStatistic::class, 'conversation_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)->active();
        });
    }

    public function scopeByPrivacy($query, $privacy)
    {
        return $query->where('privacy', $privacy);
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('privacy', 'private');
    }

    public function scopeInviteOnly($query)
    {
        return $query->where('privacy', 'invite_only');
    }

    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeWithMemberCount($query)
    {
        return $query->withCount(['activeParticipants as member_count']);
    }

    public function scopeGroups($query)
    {
        return $query->where('type', 'group');
    }

    public function scopeChannels($query)
    {
        return $query->where('type', 'channel');
    }

    // Helper methods
    public function isDirectMessage(): bool
    {
        return $this->type === 'direct';
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isChannel(): bool
    {
        return $this->type === 'channel';
    }

    public function isEncrypted(): bool
    {
        return ! empty($this->settings['encryption_algorithm'] ?? null);
    }

    public function getParticipantCount(): int
    {
        return $this->activeParticipants()->count();
    }

    public function hasUser(string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->active()->exists();
    }

    public function addParticipant(string $userId, array $options = []): Participant
    {
        return $this->participants()->create([
            'user_id' => $userId,
            'role' => $options['role'] ?? 'member',
            'joined_at' => now(),
            'permissions' => $options['permissions'] ?? null,
        ]);
    }

    public function removeParticipant(string $userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        if ($participant) {
            $participant->update(['left_at' => now()]);

            return true;
        }

        return false;
    }

    // Group-specific helper methods
    public function isPublic(): bool
    {
        return $this->privacy === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->privacy === 'private';
    }

    public function isInviteOnly(): bool
    {
        return $this->privacy === 'invite_only';
    }

    public function canMembersAddOthers(): bool
    {
        return $this->can_members_add_others;
    }

    public function requiresApprovalToJoin(): bool
    {
        return $this->require_approval_to_join;
    }

    public function showsMemberCount(): bool
    {
        return $this->show_member_count;
    }

    public function allowsAnonymousViewing(): bool
    {
        return $this->allow_anonymous_viewing;
    }

    public function hasUsername(): bool
    {
        return !empty($this->username);
    }

    public function getPublicUrl(): ?string
    {
        if (!$this->hasUsername() || !$this->isPublic()) {
            return null;
        }

        return route('public-groups.show', ['username' => $this->username]);
    }

    public function hasReachedMemberLimit(): bool
    {
        if (!$this->member_limit) {
            return false;
        }

        return $this->getParticipantCount() >= $this->member_limit;
    }

    public function canUserJoin(string $userId): bool
    {
        // Check if user is already a member
        if ($this->hasUser($userId)) {
            return false;
        }

        // Check if user is banned
        if ($this->isUserBanned($userId)) {
            return false;
        }

        // Check member limit
        if ($this->hasReachedMemberLimit()) {
            return false;
        }

        // For public groups, allow joining
        if ($this->isPublic()) {
            return !$this->requiresApprovalToJoin();
        }

        return false;
    }

    public function isUserBanned(string $userId): bool
    {
        return $this->bannedUsers()
            ->where('user_id', $userId)
            ->whereNull('unbanned_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function getUserRole(string $userId): ?string
    {
        $participant = $this->participants()
            ->where('user_id', $userId)
            ->active()
            ->first();

        return $participant?->role;
    }

    public function isUserAdmin(string $userId): bool
    {
        return $this->getUserRole($userId) === 'admin';
    }

    public function isUserModerator(string $userId): bool
    {
        $role = $this->getUserRole($userId);
        return in_array($role, ['admin', 'moderator']);
    }

    public function getAdminCount(): int
    {
        return $this->activeParticipants()->admins()->count();
    }

    public function getModeratorCount(): int
    {
        return $this->activeParticipants()->moderators()->count();
    }

    public function createInviteLink(string $createdByUserId, array $options = []): \App\Models\GroupInviteLink
    {
        return $this->inviteLinks()->create([
            'created_by_user_id' => $createdByUserId,
            'name' => $options['name'] ?? null,
            'role' => $options['role'] ?? 'member',
            'permissions' => $options['permissions'] ?? null,
            'usage_limit' => $options['usage_limit'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    public function inviteUser(string $invitedByUserId, string $invitedUserId, array $options = []): \App\Models\GroupInvitation
    {
        return $this->invitations()->create([
            'invited_by_user_id' => $invitedByUserId,
            'invited_user_id' => $invitedUserId,
            'invitation_type' => 'direct',
            'role' => $options['role'] ?? 'member',
            'permissions' => $options['permissions'] ?? null,
            'invitation_message' => $options['message'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    public function banUser(string $userId, string $bannedByUserId, array $options = []): \App\Models\GroupBannedUser
    {
        // First remove the user from participants if they're a member
        $this->removeParticipant($userId);

        return $this->bannedUsers()->create([
            'user_id' => $userId,
            'banned_by_user_id' => $bannedByUserId,
            'reason' => $options['reason'] ?? null,
            'ban_settings' => $options['ban_settings'] ?? null,
            'is_permanent' => $options['is_permanent'] ?? false,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    public function unbanUser(string $userId, string $unbannedByUserId): bool
    {
        $ban = $this->bannedUsers()
            ->where('user_id', $userId)
            ->whereNull('unbanned_at')
            ->first();

        if ($ban) {
            $ban->update([
                'unbanned_at' => now(),
                'unbanned_by_user_id' => $unbannedByUserId,
            ]);

            return true;
        }

        return false;
    }

    public function logAdminAction(string $adminUserId, string $action, array $data = []): \App\Models\GroupAdminLog
    {
        return $this->adminLogs()->create([
            'admin_user_id' => $adminUserId,
            'target_user_id' => $data['target_user_id'] ?? null,
            'target_message_id' => $data['target_message_id'] ?? null,
            'action' => $action,
            'description' => $data['description'] ?? '',
            'action_data' => $data['action_data'] ?? null,
            'previous_values' => $data['previous_values'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
