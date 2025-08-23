<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantService::getTenantId();

            if ($tenantId && static::shouldApplyTenantScope()) {
                $column = static::getTenantColumn();
                $builder->where($column, $tenantId);
            }
        });

        static::creating(function (Model $model) {
            $tenantId = TenantService::getTenantId();

            if ($tenantId && ! $model->{static::getTenantColumn()}) {
                $model->{static::getTenantColumn()} = $tenantId;
            }
        });
    }

    protected static function getTenantColumn(): string
    {
        return 'organization_id';
    }

    protected static function shouldApplyTenantScope(): bool
    {
        return true;
    }

    public function scopeWithoutTenantScope(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('tenant');
    }

    public function scopeForTenant(Builder $builder, string $tenantId): Builder
    {
        return $builder->withoutGlobalScope('tenant')->where(static::getTenantColumn(), $tenantId);
    }

    public function scopeForAllTenants(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('tenant');
    }
}
