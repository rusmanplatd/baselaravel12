<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FileShare extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'shared_by',
        'shared_with_type',
        'shared_with_id',
        'share_type',
        'share_token',
        'permissions',
        'password',
        'expires_at',
        'max_downloads',
        'download_count',
        'notify_on_access',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
        'notify_on_access' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    // Polymorphic relationships
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sharedWith(): MorphTo
    {
        return $this->morphTo('shared_with');
    }

    // User relationships
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('share_token', $token);
    }

    public function scopePublicLinks($query)
    {
        return $query->where('share_type', 'link');
    }

    public function scopeDirectShares($query)
    {
        return $query->where('share_type', 'direct');
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasDownloadsRemaining(): bool
    {
        return !$this->max_downloads || $this->download_count < $this->max_downloads;
    }

    public function canBeAccessed(): bool
    {
        return $this->is_active && !$this->isExpired() && $this->hasDownloadsRemaining();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function validatePassword(string $password): bool
    {
        return !$this->password || Hash::check($password, $this->password);
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function generateShareToken(): string
    {
        $token = Str::random(32);
        
        // Ensure uniqueness
        while (static::where('share_token', $token)->exists()) {
            $token = Str::random(32);
        }
        
        $this->share_token = $token;
        $this->save();
        
        return $token;
    }

    public function setPassword(string $password): void
    {
        $this->password = Hash::make($password);
        $this->save();
    }

    public function getShareUrl(): string
    {
        return route('files.shared', ['token' => $this->share_token]);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($share) {
            if ($share->share_type === 'link' && !$share->share_token) {
                $share->generateShareToken();
            }
        });
    }
}