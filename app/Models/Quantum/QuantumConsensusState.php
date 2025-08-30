<?php

namespace App\Models\Quantum;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumConsensusState extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_consensus_states';

    protected $fillable = [
        'session_id',
        'participant_id',
        'quantum_state',
        'measurement_basis',
        'entanglement_proof',
        'contribution_round',
        'measured_at'
    ];

    protected $casts = [
        'contribution_round' => 'integer',
        'measured_at' => 'datetime'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuantumSMPCSession::class, 'session_id', 'session_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(QuantumSMPCParticipant::class, 'participant_id', 'participant_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_consensus_states')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum consensus state {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByParticipant($query, string $participantId)
    {
        return $query->where('participant_id', $participantId);
    }

    public function scopeByRound($query, int $round)
    {
        return $query->where('contribution_round', $round);
    }
}