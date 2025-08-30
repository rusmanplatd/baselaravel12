<?php

namespace App\Models\Quantum;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumThresholdSignature extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_threshold_signatures';

    protected $fillable = [
        'session_id',
        'message_hash',
        'required_signers',
        'algorithm',
        'distributed_key_data',
        'partial_signatures',
        'final_signature',
        'status',
        'completed_at'
    ];

    protected $casts = [
        'required_signers' => 'integer',
        'distributed_key_data' => 'array',
        'partial_signatures' => 'array',
        'completed_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'awaiting_signatures'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuantumSMPCSession::class, 'session_id', 'session_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_threshold_signatures')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum threshold signature {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function getSignatureCount(): int
    {
        return count($this->partial_signatures ?? []);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasSignatureFromUser(int $userId): bool
    {
        return isset($this->partial_signatures[$userId]);
    }

    public function addPartialSignature(int $userId, array $signatureData): void
    {
        $signatures = $this->partial_signatures ?? [];
        $signatures[$userId] = $signatureData;
        
        $this->update(['partial_signatures' => $signatures]);
    }

    public function complete(string $finalSignature): void
    {
        $this->update([
            'final_signature' => $finalSignature,
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function progressPercentage(): float
    {
        if ($this->required_signers <= 0) {
            return 0.0;
        }
        
        return min(100.0, ($this->getSignatureCount() / $this->required_signers) * 100);
    }

    public function scopeAwaitingSignatures($query)
    {
        return $query->where('status', 'awaiting_signatures');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}