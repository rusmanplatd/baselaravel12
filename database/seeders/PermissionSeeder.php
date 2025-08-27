<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Create system user if it doesn't exist and set auth context
        $systemUser = User::firstOrCreate([
            'email' => 'system@system.local',
        ], [
            'name' => 'System User',
            'email' => 'system@system.local',
            'password' => bcrypt('system-user-password-'.bin2hex(random_bytes(16))),
            'email_verified_at' => now(),
        ]);

        // Set auth context for model boot methods
        Auth::login($systemUser);
        // Create permissions for different modules
        $permissions = [
            // User management permissions
            'user.view' => 'View users',
            'user.create' => 'Create users',
            'user.edit' => 'Edit users',
            'user.delete' => 'Delete users',
            'user.impersonate' => 'Impersonate users',

            // Organization management permissions
            'organization.view' => 'View organizations',
            'organization.create' => 'Create organizations',
            'organization.edit' => 'Edit organizations',
            'organization.delete' => 'Delete organizations',
            'organization.hierarchy.view' => 'View organization hierarchy',
            'organization.hierarchy.manage' => 'Manage organization hierarchy',

            // Organization membership permissions
            'membership.view' => 'View organization memberships',
            'membership.create' => 'Create organization memberships',
            'membership.edit' => 'Edit organization memberships',
            'membership.delete' => 'Delete organization memberships',
            'membership.activate' => 'Activate memberships',
            'membership.deactivate' => 'Deactivate memberships',
            'membership.terminate' => 'Terminate memberships',

            // Organization unit permissions
            'unit.view' => 'View organization units',
            'unit.create' => 'Create organization units',
            'unit.edit' => 'Edit organization units',
            'unit.delete' => 'Delete organization units',

            // Organization position permissions
            'position.view' => 'View organization positions',
            'position.create' => 'Create organization positions',
            'position.edit' => 'Edit organization positions',
            'position.delete' => 'Delete organization positions',

            // Organization position level permissions
            'position.level.view' => 'View organization position levels',
            'position.level.create' => 'Create organization position levels',
            'position.level.edit' => 'Edit organization position levels',
            'position.level.delete' => 'Delete organization position levels',

            // Alternative permission names for organization position levels (used by form requests)
            'create organization position levels' => 'Create organization position levels',
            'edit organization position levels' => 'Edit organization position levels',
            'view organization position levels' => 'View organization position levels',
            'delete organization position levels' => 'Delete organization position levels',

            // OAuth client permissions
            'oauth.client.view' => 'View OAuth clients',
            'oauth.client.create' => 'Create OAuth clients',
            'oauth.client.edit' => 'Edit OAuth clients',
            'oauth.client.delete' => 'Delete OAuth clients',
            'oauth.client.regenerate' => 'Regenerate OAuth client secrets',

            // OAuth analytics and monitoring
            'oauth.analytics.view' => 'View OAuth analytics',
            'oauth.tokens.view' => 'View OAuth tokens',
            'oauth.tokens.revoke' => 'Revoke OAuth tokens',

            // Activity log permissions
            'activity.view.own' => 'View own activity logs',
            'activity.view.organization' => 'View organization activity logs',
            'activity.view.all' => 'View all activity logs across organizations',
            'activity.delete' => 'Delete activity logs',
            'activity.export' => 'Export activity logs',
            'activity.purge' => 'Purge old activity logs',

            // Role and permission management
            'view roles' => 'View roles',
            'create roles' => 'Create roles',
            'edit roles' => 'Edit roles',
            'delete roles' => 'Delete roles',
            'manage roles' => 'Manage roles (full access)',
            'view permissions' => 'View permissions',
            'create permissions' => 'Create permissions',
            'edit permissions' => 'Edit permissions',
            'delete permissions' => 'Delete permissions',
            'manage permissions' => 'Manage permissions (full access)',
            'assign permissions' => 'Assign permissions to roles',
            'revoke permissions' => 'Revoke permissions from roles',

            // Legacy role and permission names (keeping for backward compatibility)
            'role.view' => 'View roles',
            'role.create' => 'Create roles',
            'role.edit' => 'Edit roles',
            'role.delete' => 'Delete roles',
            'permission.assign' => 'Assign permissions to roles',
            'permission.revoke' => 'Revoke permissions from roles',

            // System administration
            'system.settings.view' => 'View system settings',
            'system.settings.edit' => 'Edit system settings',
            'system.maintenance' => 'Access system maintenance',
            'system.logs.view' => 'View system logs',

            // Profile and security permissions
            'profile.view' => 'View own profile',
            'profile.edit' => 'Edit own profile',
            'security.mfa.manage' => 'Manage multi-factor authentication',
            'security.password.change' => 'Change password',
            'security.sessions.manage' => 'Manage active sessions',
        ];

        // Set permissions team context to null for global permissions
        setPermissionsTeamId(null);
        
        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Create default roles
        $roles = [
            'super-admin' => [
                'description' => 'Full system access',
                'permissions' => array_keys($permissions),
            ],
            'organization-admin' => [
                'description' => 'Organization administrator',
                'permissions' => [
                    'organization.view',
                    'organization.edit',
                    'organization.hierarchy.view',
                    'membership.view',
                    'membership.create',
                    'membership.edit',
                    'membership.delete',
                    'membership.activate',
                    'membership.deactivate',
                    'membership.terminate',
                    'unit.view',
                    'unit.create',
                    'unit.edit',
                    'unit.delete',
                    'position.view',
                    'position.create',
                    'position.edit',
                    'position.delete',
                    'position.level.view',
                    'position.level.create',
                    'position.level.edit',
                    'position.level.delete',
                    'view organization position levels',
                    'create organization position levels',
                    'edit organization position levels',
                    'delete organization position levels',
                    'oauth.client.view',
                    'oauth.client.create',
                    'oauth.client.edit',
                    'oauth.client.delete',
                    'oauth.client.regenerate',
                    'oauth.analytics.view',
                    'activity.view.organization',
                    'activity.view.all',
                    'activity.delete',
                    'activity.export',
                    'activity.purge',
                    'user.view',
                    'view roles',
                    'create roles',
                    'edit roles',
                    'delete roles',
                    'view permissions',
                    'assign permissions',
                    'revoke permissions',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'manager' => [
                'description' => 'Department/unit manager',
                'permissions' => [
                    'organization.view',
                    'membership.view',
                    'membership.create',
                    'membership.edit',
                    'unit.view',
                    'unit.edit',
                    'position.view',
                    'position.edit',
                    'position.level.view',
                    'view organization position levels',
                    'user.view',
                    'activity.view.organization',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'employee' => [
                'description' => 'Regular employee',
                'permissions' => [
                    'organization.view',
                    'membership.view',
                    'unit.view',
                    'position.view',
                    'position.level.view',
                    'view organization position levels',
                    'user.view',
                    'activity.view.own',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'board-member' => [
                'description' => 'Board member',
                'permissions' => [
                    'organization.view',
                    'organization.hierarchy.view',
                    'membership.view',
                    'unit.view',
                    'position.view',
                    'position.level.view',
                    'view organization position levels',
                    'user.view',
                    'oauth.analytics.view',
                    'activity.view.organization',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'consultant' => [
                'description' => 'External consultant',
                'permissions' => [
                    'organization.view',
                    'membership.view',
                    'unit.view',
                    'position.view',
                    'position.level.view',
                    'view organization position levels',
                    'activity.view.own',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'auditor' => [
                'description' => 'System auditor with read-only access to activity logs',
                'permissions' => [
                    'organization.view',
                    'organization.hierarchy.view',
                    'membership.view',
                    'unit.view',
                    'position.view',
                    'position.level.view',
                    'view organization position levels',
                    'user.view',
                    'activity.view.all',
                    'activity.export',
                    'oauth.analytics.view',
                    'system.logs.view',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
            'security-admin' => [
                'description' => 'Security administrator with full access to security features',
                'permissions' => [
                    'organization.view',
                    'organization.hierarchy.view',
                    'membership.view',
                    'unit.view',
                    'position.view',
                    'position.level.view',
                    'view organization position levels',
                    'user.view',
                    'user.impersonate',
                    'activity.view.all',
                    'activity.delete',
                    'activity.export',
                    'activity.purge',
                    'oauth.analytics.view',
                    'oauth.tokens.view',
                    'oauth.tokens.revoke',
                    'system.logs.view',
                    'system.maintenance',
                    'profile.view',
                    'profile.edit',
                    'security.mfa.manage',
                    'security.password.change',
                    'security.sessions.manage',
                ],
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $roleData) {
            // Set permissions team context to null for global roles
            setPermissionsTeamId(null);
            
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Sync permissions
            $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
            $role->syncPermissions($permissions);

            $this->command->info("Created role: {$roleName} with " . count($permissions) . " permissions");
        }

        // Display summary
        $this->displaySummary($roles);

        // Logout system user after seeding
        Auth::logout();
    }

    /**
     * Display a summary of roles and activity log permissions
     */
    private function displaySummary(array $roles): void
    {
        $this->command->info("\n=== ACTIVITY LOG PERMISSIONS SUMMARY ===");
        
        $activityPermissions = [
            'activity.view.own' => 'Can view their own activity logs',
            'activity.view.organization' => 'Can view activity logs within their organizations',
            'activity.view.all' => 'Can view all activity logs across all organizations',
            'activity.delete' => 'Can delete activity logs',
            'activity.export' => 'Can export activity logs',
            'activity.purge' => 'Can purge old activity logs',
        ];

        foreach ($activityPermissions as $permission => $description) {
            $this->command->info("• {$permission}: {$description}");
        }

        $this->command->info("\n=== ROLES WITH ACTIVITY LOG ACCESS ===");

        foreach ($roles as $roleName => $roleData) {
            $activityPerms = array_filter($roleData['permissions'], function($perm) {
                return strpos($perm, 'activity.') === 0;
            });

            if (!empty($activityPerms)) {
                $this->command->info("• {$roleName}: " . implode(', ', $activityPerms));
            }
        }

        $this->command->info("\n=== ROLE HIERARCHY FOR ACTIVITY LOGS ===");
        $this->command->info("• Employees: Can only view their own activities");
        $this->command->info("• Managers/Board Members: Can view organization activities");
        $this->command->info("• Organization Admins: Can view and delete organization activities + all activities");
        $this->command->info("• Auditors: Can view and export all activities (read-only)");
        $this->command->info("• Security Admins: Can view, delete, export, and purge all activities");
        $this->command->info("• Super Admins: Full access to all activity log features");
        
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $this->command->info("\nSeeding completed: {$totalPermissions} permissions, {$totalRoles} roles created/updated.");
    }
}
