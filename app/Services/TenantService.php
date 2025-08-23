<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class TenantService
{
    protected static ?Organization $currentTenant = null;

    protected static ?User $currentUser = null;

    public static function setTenant(?Organization $organization): void
    {
        static::$currentTenant = $organization;

        if ($organization) {
            Session::put('tenant_id', $organization->id);
            Cache::put("tenant:{$organization->id}", $organization, now()->addHours(1));
        } else {
            Session::forget('tenant_id');
        }
    }

    public static function getCurrentTenant(): ?Organization
    {
        if (static::$currentTenant) {
            return static::$currentTenant;
        }

        $tenantId = Session::get('tenant_id');
        if (! $tenantId) {
            return null;
        }

        static::$currentTenant = Cache::remember(
            "tenant:{$tenantId}",
            now()->addHours(1),
            fn () => Organization::find($tenantId)
        );

        return static::$currentTenant;
    }

    public static function getTenantId(): ?string
    {
        return static::getCurrentTenant()?->id;
    }

    public static function hasTenant(): bool
    {
        return static::getCurrentTenant() !== null;
    }

    public static function clearTenant(): void
    {
        static::$currentTenant = null;
        Session::forget('tenant_id');
    }

    public static function getUserTenants(?User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return collect();
        }

        return $user->organizations()
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->with(['parentOrganization', 'childOrganizations'])
            ->get()
            ->unique('id');
    }

    public static function canAccessTenant(Organization $organization, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        return static::getUserTenants($user)->contains('id', $organization->id);
    }

    public static function getDefaultTenant(?User $user = null): ?Organization
    {
        $tenants = static::getUserTenants($user);

        if ($tenants->isEmpty()) {
            return null;
        }

        return $tenants->sortBy('level')->first();
    }

    public static function switchTenant(string $organizationId): bool
    {
        $organization = Organization::find($organizationId);

        if (! $organization || ! static::canAccessTenant($organization)) {
            return false;
        }

        static::setTenant($organization);

        return true;
    }

    public static function getTenantAncestors(?Organization $tenant = null): \Illuminate\Database\Eloquent\Collection
    {
        $tenant = $tenant ?? static::getCurrentTenant();

        if (! $tenant) {
            return collect();
        }

        return $tenant->getAncestors();
    }

    public static function getTenantDescendants(?Organization $tenant = null): \Illuminate\Database\Eloquent\Collection
    {
        $tenant = $tenant ?? static::getCurrentTenant();

        if (! $tenant) {
            return collect();
        }

        return $tenant->getDescendants();
    }

    public static function isTenantAncestor(Organization $organization, ?Organization $tenant = null): bool
    {
        $tenant = $tenant ?? static::getCurrentTenant();

        if (! $tenant) {
            return false;
        }

        return $tenant->isDescendantOf($organization);
    }

    public static function isTenantDescendant(Organization $organization, ?Organization $tenant = null): bool
    {
        $tenant = $tenant ?? static::getCurrentTenant();

        if (! $tenant) {
            return false;
        }

        return $tenant->isAncestorOf($organization);
    }

    public static function getTenantScope(): array
    {
        $tenant = static::getCurrentTenant();

        if (! $tenant) {
            return [];
        }

        $scope = [$tenant->id];

        $descendants = static::getTenantDescendants($tenant);
        if ($descendants->isNotEmpty()) {
            $scope = array_merge($scope, $descendants->pluck('id')->toArray());
        }

        return $scope;
    }

    public static function makeTenantAware(string $column = 'organization_id'): \Closure
    {
        return function ($builder) use ($column) {
            $tenantId = static::getTenantId();

            if ($tenantId) {
                $builder->where($column, $tenantId);
            }
        };
    }

    public static function makeTenantAwareWithHierarchy(string $column = 'organization_id'): \Closure
    {
        return function ($builder) use ($column) {
            $scope = static::getTenantScope();

            if (! empty($scope)) {
                $builder->whereIn($column, $scope);
            }
        };
    }

    public static function boot(): void
    {
        $tenantId = Session::get('tenant_id');
        if ($tenantId) {
            static::$currentTenant = Cache::remember(
                "tenant:{$tenantId}",
                now()->addHours(1),
                fn () => Organization::find($tenantId)
            );
        }
    }
}
