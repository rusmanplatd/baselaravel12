<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->setTenantFromRequest($request);

        return $next($request);
    }

    protected function setTenantFromRequest(Request $request): void
    {
        if (! Auth::check()) {
            return;
        }

        $tenantId = $this->resolveTenantId($request);

        if ($tenantId) {
            $organization = Organization::find($tenantId);

            if ($organization && TenantService::canAccessTenant($organization)) {
                TenantService::setTenant($organization);

                return;
            }
        }

        $defaultTenant = TenantService::getDefaultTenant();
        if ($defaultTenant) {
            TenantService::setTenant($defaultTenant);
        }
    }

    protected function resolveTenantId(Request $request): ?string
    {
        return $request->route('organization')
            ?? $request->header('X-Tenant-ID')
            ?? $request->get('tenant_id')
            ?? session('tenant_id');
    }
}
