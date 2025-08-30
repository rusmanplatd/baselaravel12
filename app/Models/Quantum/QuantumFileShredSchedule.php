<?php

namespace App\Models\Quantum;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuantumFileShredSchedule extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'quantum_file_shred_schedules';

    protected $fillable = [
        'schedule_id',
        'file_id',
        'conversation_id',
        'scheduler_id',
        'shred_time',
        'shred_method',
        'notification_time',
        'status',
        'executed_at',
        'execution_result'
    ];

    protected $casts = [
        'shred_time' => 'datetime',
        'notification_time' => 'datetime',
        'executed_at' => 'datetime',
        'execution_result' => 'array'
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'shred_method' => 'quantum_secure'
    ];

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduler_id');
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
            ->useLogName('quantum_file_shreds')
            ->setDescriptionForEvent(fn (string $eventName) => "Quantum file shred schedule {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function isDue(): bool
    {
        return $this->status === 'scheduled' && $this->shred_time <= now();
    }

    public function isNotificationDue(): bool
    {
        return $this->status === 'scheduled' && 
               $this->notification_time && 
               $this->notification_time <= now();
    }

    public function markExecuted(array $result = []): void
    {
        $this->update([
            'status' => 'executed',
            'executed_at' => now(),
            'execution_result' => $result
        ]);
    }

    public function markFailed(array $result = []): void
    {
        $this->update([
            'status' => 'failed',
            'executed_at' => now(),
            'execution_result' => $result
        ]);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('shred_time', '<=', now());
    }

    public function scopeNotificationDue($query)
    {
        return $query->where('status', 'scheduled')
                    ->whereNotNull('notification_time')
                    ->where('notification_time', '<=', now());
    }
}