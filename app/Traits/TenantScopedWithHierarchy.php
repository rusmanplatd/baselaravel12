<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait TenantScopedWithHierarchy
{
    protected static function bootTenantScopedWithHierarchy(): void
    {
        static::addGlobalScope('tenant_hierarchy', function (Builder $builder) {
            if (static::shouldApplyTenantScope()) {
                $scope = TenantService::getTenantScope();

                if (! empty($scope)) {
                    $column = static::getTenantColumn();
                    $builder->whereIn($column, $scope);
                }
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
        return $builder->withoutGlobalScope('tenant_hierarchy');
    }

    public function scopeForTenant(Builder $builder, string $tenantId): Builder
    {
        return $builder->withoutGlobalScope('tenant_hierarchy')->where(static::getTenantColumn(), $tenantId);
    }

    public function scopeForTenantHierarchy(Builder $builder, array $tenantIds): Builder
    {
        return $builder->withoutGlobalScope('tenant_hierarchy')->whereIn(static::getTenantColumn(), $tenantIds);
    }

    public function scopeForAllTenants(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('tenant_hierarchy');
    }
}
