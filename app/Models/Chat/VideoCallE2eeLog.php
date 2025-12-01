<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCallE2eeLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'video_call_id',
        'participant_id',
        'key_operation',
        'encryption_algorithm',
        'key_id',
        'operation_timestamp',
        'operation_success',
        'error_details',
        'operation_metadata',
    ];

    protected $casts = [
        'operation_timestamp' => 'datetime',
        'operation_success' => 'boolean',
        'operation_metadata' => 'array',
    ];

    // Relationships
    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(VideoCallParticipant::class, 'participant_id');
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('operation_success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('operation_success', false);
    }

    public function scopeByOperation($query, string $operation)
    {
        return $query->where('key_operation', $operation);
    }

    public function scopeKeyGeneration($query)
    {
        return $query->where('key_operation', 'generate');
    }

    public function scopeKeyRotation($query)
    {
        return $query->where('key_operation', 'rotate');
    }

    // Helper methods
    public function wasSuccessful(): bool
    {
        return $this->operation_success;
    }

    public function failed(): bool
    {
        return ! $this->operation_success;
    }

    public function isKeyGeneration(): bool
    {
        return $this->key_operation === 'generate';
    }

    public function isKeyDistribution(): bool
    {
        return $this->key_operation === 'distribute';
    }

    public function isKeyRotation(): bool
    {
        return $this->key_operation === 'rotate';
    }

    public function isKeyRevocation(): bool
    {
        return $this->key_operation === 'revoke';
    }

    public function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return $this->operation_metadata;
        }

        return $this->operation_metadata[$key] ?? null;
    }

    public function getLogSummary(): array
    {
        return [
            'id' => $this->id,
            'operation' => $this->key_operation,
            'algorithm' => $this->encryption_algorithm,
            'success' => $this->operation_success,
            'timestamp' => $this->operation_timestamp->toISOString(),
            'error' => $this->error_details,
        ];
    }
}
