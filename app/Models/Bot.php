<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bot extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $table = 'bots';

    protected $fillable = [
        'name',
        'description',
        'avatar',
        'api_token',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'capabilities',
        'configuration',
        'rate_limit_per_minute',
        'organization_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capabilities' => 'array',
        'configuration' => 'array',
        'rate_limit_per_minute' => 'integer',
    ];

    protected $hidden = [
        'api_token',
        'webhook_secret',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(\App\Models\Chat\BotConversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(\App\Models\Chat\BotMessage::class);
    }

    public function encryptionKeys(): HasMany
    {
        return $this->hasMany(\App\Models\Chat\BotEncryptionKey::class);
    }

    public function generateApiToken(): string
    {
        $this->api_token = 'bot_' . Str::random(64);
        $this->save();
        return $this->api_token;
    }

    public function generateWebhookSecret(): string
    {
        $this->webhook_secret = 'whsec_' . Str::random(32);
        $this->save();
        return $this->webhook_secret;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? []);
    }

    public function getCapabilities(): array
    {
        return $this->capabilities ?? [];
    }

    public function isQuantumCapable(): bool
    {
        return $this->hasCapability('quantum_encryption');
    }

    public function canReceiveMessages(): bool
    {
        return $this->hasCapability('receive_messages');
    }

    public function canSendMessages(): bool
    {
        return $this->hasCapability('send_messages');
    }

    public function canProcessFiles(): bool
    {
        return $this->hasCapability('process_files');
    }

    public function canAccessHistory(): bool
    {
        return $this->hasCapability('access_history');
    }

    public function getRateLimitPerMinute(): int
    {
        return $this->rate_limit_per_minute ?? 60;
    }

    public function getConfiguration(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->configuration ?? [];
        }
        
        return data_get($this->configuration, $key, $default);
    }

    public function setConfiguration(string $key, $value): void
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;
        $this->save();
    }

    public function getDisplayName(): string
    {
        return $this->name . ' (Bot)';
    }

    public function getUserAgent(): string
    {
        return "Bot/{$this->id} ({$this->name})";
    }
}