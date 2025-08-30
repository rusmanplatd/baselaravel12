<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumSecureVote extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_secure_votes';

    protected $fillable = [
        'session_id',
        'voter_id',
        'encrypted_vote',
        'vote_commitment',
        'zk_proof',
        'vote_receipt',
        'cast_at'
    ];

    protected $casts = [
        'voter_id' => 'integer',
        'cast_at' => 'datetime'
    ];

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuantumSMPCSession::class, 'session_id', 'session_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_secure_votes')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum secure vote {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByVoter($query, int $voterId)
    {
        return $query->where('voter_id', $voterId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('cast_at', '>=', now()->subHours($hours));
    }
}
