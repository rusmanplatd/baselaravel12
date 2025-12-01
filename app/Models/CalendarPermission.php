<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarPermission extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'calendar_id',
        'user_id',
        'permission',
        'granted_by',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function canRead(): bool
    {
        return in_array($this->permission, ['read', 'write', 'admin']);
    }

    public function canWrite(): bool
    {
        return in_array($this->permission, ['write', 'admin']);
    }

    public function canAdmin(): bool
    {
        return $this->permission === 'admin';
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeWithPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    public function scopeCanWrite($query)
    {
        return $query->whereIn('permission', ['write', 'admin']);
    }

    public function scopeCanAdmin($query)
    {
        return $query->where('permission', 'admin');
    }
}
