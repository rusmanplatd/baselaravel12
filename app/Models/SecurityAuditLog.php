<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'security_audit_logs';

    protected $fillable = [
        'event_type',
        'severity',
        'user_id',
        'device_id',
        'conversation_id',
        'ip_address',
        'user_agent',
        'location',
        'metadata',
        'risk_score',
        'status',
        'resolved_at',
        'resolved_by',
        'organization_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'location' => 'array',
        'resolved_at' => 'datetime',
        'risk_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Chat\Conversation::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isHighRisk(): bool
    {
        return $this->risk_score >= 8;
    }

    public function isMediumRisk(): bool
    {
        return $this->risk_score >= 5 && $this->risk_score < 8;
    }

    public function isLowRisk(): bool
    {
        return $this->risk_score < 5;
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 8);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('status', '!=', 'resolved');
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}