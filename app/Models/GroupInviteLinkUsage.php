<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupInviteLinkUsage extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'group_invite_link_usages';

    protected $fillable = [
        'invite_link_id',
        'user_id',
        'user_agent',
        'ip_address',
        'metadata',
        'used_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'used_at' => 'datetime',
    ];

    public function inviteLink(): BelongsTo
    {
        return $this->belongsTo(GroupInviteLink::class, 'invite_link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('used_at', '>=', now()->subDays($days));
    }

    public function scopeForLink($query, $inviteLinkId)
    {
        return $query->where('invite_link_id', $inviteLinkId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}