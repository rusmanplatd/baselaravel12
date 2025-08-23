<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $tenant = TenantService::getCurrentTenant();

        if (! $tenant) {
            return $this->redirectToTenantSelection($request);
        }

        if (! TenantService::canAccessTenant($tenant)) {
            return $this->handleUnauthorizedAccess($request);
        }

        return $next($request);
    }

    protected function redirectToTenantSelection(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'No tenant selected',
                'code' => 'NO_TENANT',
            ], 400);
        }

        return redirect()->route('tenant.select')
            ->with('message', 'Please select an organization to continue.');
    }

    protected function handleUnauthorizedAccess(Request $request): Response
    {
        TenantService::clearTenant();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized access to this organization',
                'code' => 'TENANT_ACCESS_DENIED',
            ], 403);
        }

        return redirect()->route('tenant.select')
            ->with('error', 'You do not have access to this organization.');
    }
}
