<?php

namespace App\Http\Controllers;

use App\Models\Auth\Permission;
use App\Models\OAuthScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeveloperController extends Controller
{
    public function apiReference(Request $request)
    {
        // Get all OAuth scopes
        $scopes = OAuthScope::all()->map(function ($scope) {
            return [
                'identifier' => $scope->identifier,
                'name' => $scope->name,
                'description' => $scope->description,
                'is_default' => $scope->is_default,
                'category' => $this->categorizeScope($scope->identifier),
            ];
        })->groupBy('category');

        // Get all permissions grouped by module
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'module' => $this->getPermissionModule($permission->name),
                'action' => $this->getPermissionAction($permission->name),
                'description' => $this->getPermissionDescription($permission->name),
            ];
        })->groupBy('module');

        return Inertia::render('developer/api-reference', [
            'scopes' => $scopes,
            'permissions' => $permissions,
        ]);
    }

    private function categorizeScope(string $identifier): string
    {
        if (in_array($identifier, ['openid', 'profile', 'email', 'offline_access'])) {
            return 'Authentication';
        }

        if (str_contains($identifier, 'organization')) {
            return 'Organization Management';
        }

        if (str_contains($identifier, 'userinfo') || str_contains($identifier, 'user.modify')) {
            return 'User Management';
        }

        if (str_contains($identifier, 'analytics') || str_contains($identifier, 'reports')) {
            return 'Analytics & Reporting';
        }

        if (str_contains($identifier, 'webhooks') || str_contains($identifier, 'integrations')) {
            return 'Integrations';
        }

        if (str_contains($identifier, 'finance') || str_contains($identifier, 'audit')) {
            return 'Financial & Security';
        }

        if (str_contains($identifier, 'platform') || str_contains($identifier, 'mobile')) {
            return 'Platform Access';
        }

        return 'Other';
    }

    private function getPermissionModule(string $name): string
    {
        $parts = explode(':', $name);

        return $parts[0] ?? 'general';
    }

    private function getPermissionAction(string $name): string
    {
        $parts = explode(':', $name);

        return $parts[1] ?? 'unknown';
    }

    private function getPermissionDescription(string $name): string
    {
        $descriptions = [
            // User management
            'user:read' => 'View and list users',
            'user:write' => 'Create and update users',
            'user:delete' => 'Delete users',
            'user:impersonate' => 'Impersonate users for troubleshooting',

            // Organization management
            'org:read' => 'View organization information',
            'org:write' => 'Create and update organizations',
            'org:delete' => 'Delete organizations',
            'org:admin' => 'Full administrative access to organization',

            // Organization membership
            'org_member:read' => 'View organization memberships',
            'org_member:write' => 'Create and update organization memberships',
            'org_member:delete' => 'Remove organization memberships',
            'org_member:admin' => 'Full administrative access to organization memberships',

            // Organization units
            'org_unit:read' => 'View organization units',
            'org_unit:write' => 'Create and update organization units',
            'org_unit:delete' => 'Delete organization units',
            'org_unit:admin' => 'Full administrative access to organization units',

            // Organization positions
            'org_position:read' => 'View organization positions',
            'org_position:write' => 'Create and update organization positions',
            'org_position:delete' => 'Delete organization positions',
            'org_position:admin' => 'Full administrative access to organization positions',

            // OAuth applications
            'oauth_app:read' => 'View OAuth applications',
            'oauth_app:write' => 'Create and update OAuth applications',
            'oauth_app:delete' => 'Delete OAuth applications',
            'oauth_app:admin' => 'Full administrative access to OAuth applications',

            // OAuth tokens
            'oauth_token:read' => 'View OAuth tokens and analytics',
            'oauth_token:write' => 'Manage OAuth tokens',
            'oauth_token:delete' => 'Revoke OAuth tokens',

            // Audit logs
            'audit_log:read' => 'View audit logs',
            'audit_log:write' => 'Create audit log entries',
            'audit_log:delete' => 'Delete audit log entries',
            'audit_log:admin' => 'Full administrative access to audit logs including export and purge',

            // Roles and permissions
            'role:read' => 'View roles and their permissions',
            'role:write' => 'Create and update roles',
            'role:delete' => 'Delete roles',
            'role:admin' => 'Full administrative access to roles',

            'permission:read' => 'View permissions',
            'permission:write' => 'Create and update permissions',
            'permission:delete' => 'Delete permissions',
            'permission:admin' => 'Full administrative access to permissions',

            // System administration
            'admin:org' => 'Organization administration',
            'admin:enterprise' => 'Enterprise administration',
            'site_admin' => 'Site administration access',
            'system:read' => 'View system settings and logs',
            'system:write' => 'Modify system settings',
            'system:admin' => 'Full system administrative access',

            // Profile and security
            'profile:read' => 'View own profile information',
            'profile:write' => 'Update own profile information',
            'security:read' => 'View security settings',
            'security:write' => 'Modify security settings including MFA and sessions',
            'security:admin' => 'Full administrative access to security features',

            // Geography management
            'geo_country:read' => 'View countries',
            'geo_country:write' => 'Create and update countries',
            'geo_country:delete' => 'Delete countries',
            'geo_country:admin' => 'Full administrative access to countries',

            'geo_province:read' => 'View provinces',
            'geo_province:write' => 'Create and update provinces',
            'geo_province:delete' => 'Delete provinces',
            'geo_province:admin' => 'Full administrative access to provinces',

            'geo_city:read' => 'View cities',
            'geo_city:write' => 'Create and update cities',
            'geo_city:delete' => 'Delete cities',
            'geo_city:admin' => 'Full administrative access to cities',

            'geo_district:read' => 'View districts',
            'geo_district:write' => 'Create and update districts',
            'geo_district:delete' => 'Delete districts',
            'geo_district:admin' => 'Full administrative access to districts',

            'geo_village:read' => 'View villages',
            'geo_village:write' => 'Create and update villages',
            'geo_village:delete' => 'Delete villages',
            'geo_village:admin' => 'Full administrative access to villages',
        ];

        return $descriptions[$name] ?? 'No description available';
    }
}
