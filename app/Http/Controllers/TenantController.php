<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function select()
    {
        $tenants = TenantService::getUserTenants();
        $currentTenant = TenantService::getCurrentTenant();

        return Inertia::render('Tenant/Select', [
            'tenants' => $tenants->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'organization_code' => $tenant->organization_code,
                'organization_type' => $tenant->organization_type,
                'level' => $tenant->level,
                'path' => $tenant->path,
            ]),
            'currentTenant' => $currentTenant ? [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'organization_code' => $currentTenant->organization_code,
                'organization_type' => $currentTenant->organization_type,
                'level' => $currentTenant->level,
            ] : null,
        ]);
    }

    public function switch(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|string|exists:organizations,id',
        ]);

        $currentTenant = TenantService::getCurrentTenant();
        $targetTenant = \App\Models\Organization::find($request->organization_id);

        $success = TenantService::switchTenant($request->organization_id);

        if (! $success) {
            return back()->with('error', 'You do not have access to this organization.');
        }

        // Log the tenant switch activity
        ActivityLogService::logTenantSwitch($currentTenant, $targetTenant, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => 'manual_switch',
        ]);

        $redirectUrl = $request->input('redirect_to', route('dashboard'));

        return redirect()->to($redirectUrl)->with('success', 'Organization switched successfully.');
    }

    public function current()
    {
        $tenant = TenantService::getCurrentTenant();

        return response()->json([
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'organization_code' => $tenant->organization_code,
                'organization_type' => $tenant->organization_type,
                'level' => $tenant->level,
                'path' => $tenant->path,
            ] : null,
        ]);
    }

    public function available()
    {
        $tenants = TenantService::getUserTenants();

        return response()->json([
            'tenants' => $tenants->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'organization_code' => $tenant->organization_code,
                'organization_type' => $tenant->organization_type,
                'level' => $tenant->level,
                'path' => $tenant->path,
            ]),
        ]);
    }

    public function clear()
    {
        TenantService::clearTenant();

        return redirect()->route('tenant.select')
            ->with('message', 'Organization context cleared.');
    }
}
