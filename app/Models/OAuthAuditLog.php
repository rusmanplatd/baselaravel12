<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Tenant;

class OAuthAuditLog extends Model
{
    protected $table = 'oauth_audit_logs';
    
    protected $fillable = [
        'event_type',
        'client_id',
        'user_id',
        'organization_id',
        'tenant_id',
        'tenant_domain',
        'ip_address',
        'user_agent',
        'scopes',
        'grant_type',
        'success',
        'error_code',
        'error_description',
        'metadata',
        'organization_context',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'organization_context' => 'array',
        'success' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\Laravel\Passport\Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function logEvent(string $eventType, array $data = []): void
    {
        $contextData = static::getContextData($data);

        static::create(array_merge([
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => true,
        ], $data, $contextData));
    }

    public static function logError(string $eventType, string $errorCode, ?string $errorDescription = null, array $data = []): void
    {
        $contextData = static::getContextData($data);

        static::create(array_merge([
            'event_type' => $eventType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => false,
            'error_code' => $errorCode,
            'error_description' => $errorDescription,
        ], $data, $contextData));
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    private static function getContextData(array $data = []): array
    {
        $contextData = [];

        if (isset($data['client_id'])) {
            $client = \Laravel\Passport\Client::find($data['client_id']);
            if ($client && $client->organization_id) {
                $organization = \App\Models\Organization::with('tenant')->find($client->organization_id);
                if ($organization) {
                    $contextData['organization_id'] = $organization->id;
                    $contextData['organization_context'] = [
                        'name' => $organization->name,
                        'code' => $organization->organization_code,
                        'type' => $organization->organization_type,
                        'level' => $organization->level,
                        'path' => $organization->path,
                    ];

                    if ($organization->tenant) {
                        $contextData['tenant_id'] = $organization->tenant->id;
                        $contextData['tenant_domain'] = $organization->tenant->domain;
                    }
                }
            }
        }

        return $contextData;
    }
}
