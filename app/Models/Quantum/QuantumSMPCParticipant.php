<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumSMPCParticipant extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_smpc_participants';

    protected $fillable = [
        'participant_id',
        'user_id',
        'device_fingerprint',
        'public_key',
        'quantum_proof',
        'quantum_capabilities',
        'trust_level',
        'key_handle',
        'last_activity',
        'contribution_count',
        'status'
    ];

    protected $casts = [
        'quantum_capabilities' => 'array',
        'trust_level' => 'float',
        'last_activity' => 'datetime',
        'contribution_count' => 'integer'
    ];

    protected $attributes = [
        'status' => 'active',
        'contribution_count' => 0,
        'trust_level' => 0.5
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('quantum_smpc_participants')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum SMPC participant {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at', 'last_activity', 'contribution_count']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrusted(): bool
    {
        return $this->trust_level >= 0.7; // 70% trust threshold
    }

    public function hasCapability(string $capability): bool
    {
        return isset($this->quantum_capabilities[$capability]) && 
               $this->quantum_capabilities[$capability] === true;
    }

    public function incrementContribution(): void
    {
        $this->increment('contribution_count');
        $this->update(['last_activity' => now()]);
    }

    public function updateTrustLevel(float $newLevel): void
    {
        $this->update([
            'trust_level' => max(0.0, min(1.0, $newLevel)),
            'last_activity' => now()
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrusted($query)
    {
        return $query->where('trust_level', '>=', 0.7);
    }

    public function scopeWithCapability($query, string $capability)
    {
        return $query->whereJsonContains('quantum_capabilities->' . $capability, true);
    }

    public function scopeRecentlyActive($query, int $hours = 24)
    {
        return $query->where('last_activity', '>=', now()->subHours($hours));
    }
}