<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'headers',
        'status',
        'http_status',
        'response_body',
        'attempt',
        'delivered_at',
        'next_retry_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && 
               $this->attempt < ($this->webhook->retry_attempts ?? 3) &&
               (!$this->next_retry_at || $this->next_retry_at->isPast());
    }
}