<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumSMPCSession extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_smpc_sessions';

    protected $fillable = [
        'session_id',
        'protocol_type',
        'initiator_id',
        'participant_ids',
        'threshold',
        'current_round',
        'total_rounds',
        'status',
        'algorithm',
        'key_usage',
        'proposed_value',
        'fault_tolerance',
        'byzantine_threshold',
        'security_parameters',
        'voting_question',
        'voting_options',
        'voting_deadline',
        'anonymous_voting',
        'verifiable_results',
        'consensus_reached',
        'final_result',
        'expires_at',
        'completed_at',
        'abort_reason',
        'aborted_at',
        'message_hash'
    ];

    protected $casts = [
        'initiator_id' => 'integer',
        'participant_ids' => 'array',
        'threshold' => 'integer',
        'current_round' => 'integer',
        'total_rounds' => 'integer',
        'fault_tolerance' => 'integer',
        'byzantine_threshold' => 'integer',
        'security_parameters' => 'array',
        'voting_options' => 'array',
        'voting_deadline' => 'datetime',
        'anonymous_voting' => 'boolean',
        'verifiable_results' => 'boolean',
        'consensus_reached' => 'boolean',
        'final_result' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'aborted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'initializing',
        'current_round' => 1,
        'total_rounds' => 3,
        'consensus_reached' => false,
        'anonymous_voting' => true,
        'verifiable_results' => true
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_smpc')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum SMPC session {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at', 'current_round']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['initializing', 'running']) && !$this->isExpired();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isAborted(): bool
    {
        return $this->status === 'aborted';
    }

    public function progressPercentage(): float
    {
        if ($this->total_rounds <= 0) {
            return 0.0;
        }
        
        return min(100.0, ($this->current_round / $this->total_rounds) * 100);
    }

    public function nextRound(): void
    {
        if ($this->current_round < $this->total_rounds) {
            $this->increment('current_round');
        }
    }

    public function complete(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'final_result' => $result,
            'completed_at' => now()
        ]);
    }

    public function fail(string $reason = ''): void
    {
        $this->update([
            'status' => 'failed',
            'abort_reason' => $reason,
            'aborted_at' => now()
        ]);
    }

    public function abort(string $reason): void
    {
        $this->update([
            'status' => 'aborted',
            'abort_reason' => $reason,
            'aborted_at' => now()
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['initializing', 'running'])
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('initiator_id', $userId)
              ->orWhereJsonContains('participant_ids', $userId);
        });
    }

    public function scopeByType($query, string $protocolType)
    {
        return $query->where('protocol_type', $protocolType);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}