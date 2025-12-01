<?php

namespace App\Models;

use App\Models\Chat\Conversation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GroupInviteLink extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'group_invite_links';

    protected $fillable = [
        'conversation_id',
        'created_by_user_id',
        'link_token',
        'name',
        'role',
        'permissions',
        'usage_limit',
        'usage_count',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $attributes = [
        'role' => 'member',
        'usage_count' => 0,
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->link_token)) {
                $model->link_token = Str::random(16);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'role', 'permissions', 'usage_limit', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Group invite link {$eventName}")
            ->useLogName('group_invite_links');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(GroupInviteLinkUsage::class, 'invite_link_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeExhausted($query)
    {
        return $query->whereNotNull('usage_limit')
            ->whereColumn('usage_count', '>=', 'usage_limit');
    }

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeByToken($query, $token)
    {
        return $query->where('link_token', $token);
    }

    // Helper methods
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit && $this->usage_count >= $this->usage_limit;
    }

    public function canBeUsed(): bool
    {
        return $this->isActive();
    }

    public function getRemainingUses(): ?int
    {
        if (!$this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }

    public function getRemainingTime(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        $remaining = $this->expires_at->diffInSeconds(now());

        return $remaining > 0 ? $remaining : 0;
    }

    public function getInviteUrl(): string
    {
        return route('group-invite-links.join', ['token' => $this->link_token]);
    }

    public function use(User $user, array $metadata = []): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        // Record usage
        $this->usages()->create([
            'user_id' => $user->id,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'metadata' => $metadata,
            'used_at' => now(),
        ]);

        // Increment usage count
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);

        return true;
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function revoke(): bool
    {
        return $this->deactivate();
    }
}