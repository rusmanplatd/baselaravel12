<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumFileShare extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_file_shares';

    protected $fillable = [
        'share_id',
        'file_id',
        'conversation_id',
        'sharer_id',
        'shared_with',
        'permissions',
        'expires_at',
        'max_downloads',
        'download_count',
        'quantum_key_share',
        'access_signature',
        'share_message',
        'status',
        'revoked_at',
        'revocation_reason'
    ];

    protected $casts = [
        'shared_with' => 'array',
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
        'revoked_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'active',
        'download_count' => 0
    ];

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sharer_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(QuantumEncryptedFile::class, 'file_id', 'file_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_file_shares')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum file share {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at', 'download_count']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function isExhausted(): bool
    {
        return $this->max_downloads && $this->download_count >= $this->max_downloads;
    }

    public function canDownload(): bool
    {
        return !$this->isExpired() && !$this->isRevoked() && !$this->isExhausted();
    }

    public function incrementDownload(): void
    {
        $this->increment('download_count');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'revoked')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sharer_id', $userId)
              ->orWhereJsonContains('shared_with', $userId);
        });
    }
}