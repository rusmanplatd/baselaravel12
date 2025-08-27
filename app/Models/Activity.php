<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    use HasUlids, HasFactory, TenantScoped;

    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'event',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
        'organization_id',
        'tenant_id',
    ];

    protected $casts = [
        'id' => 'string',
        'properties' => 'collection',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('causer_id', $userId)->where('causer_type', User::class);
    }

    public function scopeAuth($query)
    {
        return $query->where('log_name', 'auth');
    }

    public function scopeOrganizationManagement($query)
    {
        return $query->where('log_name', 'organization');
    }

    public function scopeOauth($query)
    {
        return $query->where('log_name', 'oauth');
    }

    public function scopeSystem($query)
    {
        return $query->where('log_name', 'system');
    }

    public function getOrganizationContextAttribute()
    {
        return $this->organization_id ? $this->organization : null;
    }

    public function getTenantContextAttribute()
    {
        return $this->tenant_id;
    }

    public function getUserContextAttribute()
    {
        return $this->causer;
    }

    public function getFormattedPropertiesAttribute()
    {
        return $this->properties->map(function ($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }

            return $value;
        });
    }
}
