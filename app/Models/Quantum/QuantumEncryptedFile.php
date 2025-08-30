<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumEncryptedFile extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_encrypted_files';

    protected $fillable = [
        'file_id',
        'conversation_id',
        'uploader_id',
        'original_name',
        'mime_type',
        'original_size',
        'encrypted_size',
        'storage_path',
        'master_key_id',
        'content_hash',
        'content_signature',
        'backup_signature',
        'quantum_resistance_proof',
        'homomorphic_proof',
        'security_level',
        'cipher_suite',
        'access_control_list',
        'compression_level',
        'watermark_enabled',
        'auto_shred_enabled',
        'expires_at',
        'max_shares',
        'status',
        'shredded_at',
        'shred_method',
        'destruction_certificate'
    ];

    protected $casts = [
        'original_size' => 'integer',
        'encrypted_size' => 'integer',
        'access_control_list' => 'array',
        'compression_level' => 'integer',
        'watermark_enabled' => 'boolean',
        'auto_shred_enabled' => 'boolean',
        'expires_at' => 'datetime',
        'max_shares' => 'integer',
        'shredded_at' => 'datetime',
        'destruction_certificate' => 'array'
    ];

    protected $attributes = [
        'status' => 'active',
        'security_level' => 'level_5',
        'cipher_suite' => 'hybrid_xcha20_aes256',
        'compression_level' => 0,
        'watermark_enabled' => false,
        'auto_shred_enabled' => false
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_files')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum encrypted file {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isShredded(): bool
    {
        return $this->status === 'shredded';
    }

    public function canAccess(int $userId): bool
    {
        if ($this->uploader_id === $userId) {
            return true;
        }

        if (in_array($userId, $this->access_control_list ?? [])) {
            return true;
        }

        return false;
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'shredded')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('uploader_id', $userId)
              ->orWhereJsonContains('access_control_list', $userId);
        });
    }
}