<?php

namespace App\Models;

use App\Models\Chat\Conversation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GroupInvitation extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'group_invitations';

    protected $fillable = [
        'conversation_id',
        'invited_by_user_id',
        'invited_user_id',
        'email',
        'phone_number',
        'invitation_type',
        'status',
        'invitation_token',
        'invitation_message',
        'permissions',
        'role',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'revoked_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $attributes = [
        'invitation_type' => 'direct',
        'status' => 'pending',
        'role' => 'member',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->invitation_token)) {
                $model->invitation_token = Str::random(32);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'role', 'permissions'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Group invitation {$eventName}")
            ->useLogName('group_invitations');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->where('expires_at', '<=', now())
                    ->where('status', 'pending');
            });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('invitation_type', $type);
    }

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('invited_user_id', $userId);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canBeRevoked(): bool
    {
        return $this->isPending();
    }

    public function accept(): bool
    {
        if (!$this->canBeAccepted()) {
            return false;
        }

        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return true;
    }

    public function reject(): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return true;
    }

    public function revoke(): bool
    {
        if (!$this->canBeRevoked()) {
            return false;
        }

        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return true;
    }

    public function markAsExpired(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'expired']);

        return true;
    }

    public function getInviteUrl(): string
    {
        return route('group-invitations.accept', ['token' => $this->invitation_token]);
    }

    public function getRemainingTime(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        $remaining = $this->expires_at->diffInSeconds(now());

        return $remaining > 0 ? $remaining : 0;
    }
}