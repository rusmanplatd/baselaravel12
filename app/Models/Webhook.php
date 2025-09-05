<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'webhooks';

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'status',
        'retry_attempts',
        'timeout',
        'headers',
        'organization_id',
        'created_by',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'status' => 'string',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function generateSecret(): string
    {
        $this->secret = 'whsec_' . Str::random(32);
        $this->save();
        return $this->secret;
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    public function getFailedDeliveries()
    {
        return $this->deliveries()->where('status', 'failed')->orderByDesc('created_at');
    }

    public function getSuccessRate(): float
    {
        $total = $this->deliveries()->count();
        if ($total === 0) {
            return 100.0;
        }

        $successful = $this->deliveries()->where('status', 'success')->count();
        return ($successful / $total) * 100;
    }
}