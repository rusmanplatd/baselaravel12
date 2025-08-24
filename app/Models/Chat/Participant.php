<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'chat_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'last_read_at',
        'permissions',
        'notification_settings',
        'is_muted',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'last_read_at' => 'datetime',
        'permissions' => 'array',
        'notification_settings' => 'array',
        'is_muted' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return is_null($this->left_at);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'owner']);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function leave(): void
    {
        $this->update(['left_at' => now()]);
    }

    public function markAsRead(): void
    {
        $this->update(['last_read_at' => now()]);
    }
}
