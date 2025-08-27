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
        // GitHub-style permissions for different modules
        $permissions = [
            // User management permissions (GitHub-style)
            'user:read' => 'View and list users',
            'user:write' => 'Create and update users',
            'user:delete' => 'Delete users',
            'user:impersonate' => 'Impersonate users for troubleshooting',

            // Organization management permissions (GitHub-style)
            'org:read' => 'View organization information',
            'org:write' => 'Create and update organizations',
            'org:delete' => 'Delete organizations',
            'org:admin' => 'Full administrative access to organization',

            // Organization membership permissions (GitHub-style)
            'org_member:read' => 'View organization memberships',
            'org_member:write' => 'Create and update organization memberships',
            'org_member:delete' => 'Remove organization memberships',
            'org_member:admin' => 'Full administrative access to organization memberships',

            // Repository-style organization unit permissions
            'org_unit:read' => 'View organization units',
            'org_unit:write' => 'Create and update organization units',
            'org_unit:delete' => 'Delete organization units',
            'org_unit:admin' => 'Full administrative access to organization units',

            // Position management permissions (GitHub-style)
            'org_position:read' => 'View organization positions',
            'org_position:write' => 'Create and update organization positions',
            'org_position:delete' => 'Delete organization positions',
            'org_position:admin' => 'Full administrative access to organization positions',


            // OAuth client permissions (GitHub-style)
            'oauth_app:read' => 'View OAuth applications',
            'oauth_app:write' => 'Create and update OAuth applications',
            'oauth_app:delete' => 'Delete OAuth applications',
            'oauth_app:admin' => 'Full administrative access to OAuth applications',

            // OAuth token and analytics permissions
            'oauth_token:read' => 'View OAuth tokens and analytics',
            'oauth_token:write' => 'Manage OAuth tokens',
            'oauth_token:delete' => 'Revoke OAuth tokens',

            // Activity log permissions (GitHub-style)
            'audit_log:read' => 'View audit logs',
            'audit_log:write' => 'Create audit log entries',
            'audit_log:delete' => 'Delete audit log entries',
            'audit_log:admin' => 'Full administrative access to audit logs including export and purge',

            // Role and permission management (GitHub-style)
            'role:read' => 'View roles and their permissions',
            'role:write' => 'Create and update roles',
            'role:delete' => 'Delete roles',
            'role:admin' => 'Full administrative access to roles',
            
            'permission:read' => 'View permissions',
            'permission:write' => 'Create and update permissions',
            'permission:delete' => 'Delete permissions',
            'permission:admin' => 'Full administrative access to permissions',


            // System administration (GitHub-style)
            'admin:org' => 'Organization administration',
            'admin:enterprise' => 'Enterprise administration',
            'site_admin' => 'Site administration access',
            'system:read' => 'View system settings and logs',
            'system:write' => 'Modify system settings',
            'system:admin' => 'Full system administrative access',

            // Profile and security permissions (GitHub-style)
            'profile:read' => 'View own profile information',
            'profile:write' => 'Update own profile information',
            'security:read' => 'View security settings',
            'security:write' => 'Modify security settings including MFA and sessions',
            'security:admin' => 'Full administrative access to security features',
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

        // Create default roles with GitHub-style permissions
        $roles = [
            'super-admin' => [
                'description' => 'Full system access',
                'permissions' => array_keys($permissions),
            ],
            'organization-admin' => [
                'description' => 'Organization administrator',
                'permissions' => [
                    'org:read',
                    'org:write',
                    'org:admin',
                    'org_member:read',
                    'org_member:write',
                    'org_member:delete',
                    'org_member:admin',
                    'org_unit:read',
                    'org_unit:write',
                    'org_unit:delete',
                    'org_unit:admin',
                    'org_position:read',
                    'org_position:write',
                    'org_position:delete',
                    'org_position:admin',
                    'oauth_app:read',
                    'oauth_app:write',
                    'oauth_app:delete',
                    'oauth_app:admin',
                    'oauth_token:read',
                    'audit_log:read',
                    'audit_log:admin',
                    'user:read',
                    'role:read',
                    'role:write',
                    'role:delete',
                    'permission:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                    'admin:org',
                ],
            ],
            'manager' => [
                'description' => 'Department/unit manager',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_member:write',
                    'org_unit:read',
                    'org_unit:write',
                    'org_position:read',
                    'org_position:write',
                    'user:read',
                    'audit_log:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                ],
            ],
            'employee' => [
                'description' => 'Regular employee',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_unit:read',
                    'org_position:read',
                    'user:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                ],
            ],
            'board-member' => [
                'description' => 'Board member',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_unit:read',
                    'org_position:read',
                    'user:read',
                    'oauth_token:read',
                    'audit_log:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                ],
            ],
            'consultant' => [
                'description' => 'External consultant',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_unit:read',
                    'org_position:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                ],
            ],
            'auditor' => [
                'description' => 'System auditor with read-only access to activity logs',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_unit:read',
                    'org_position:read',
                    'user:read',
                    'audit_log:read',
                    'audit_log:admin',
                    'oauth_token:read',
                    'system:read',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                ],
            ],
            'security-admin' => [
                'description' => 'Security administrator with full access to security features',
                'permissions' => [
                    'org:read',
                    'org_member:read',
                    'org_unit:read',
                    'org_position:read',
                    'user:read',
                    'user:impersonate',
                    'audit_log:read',
                    'audit_log:delete',
                    'audit_log:admin',
                    'oauth_token:read',
                    'oauth_token:write',
                    'oauth_token:delete',
                    'system:read',
                    'system:admin',
                    'profile:read',
                    'profile:write',
                    'security:read',
                    'security:write',
                    'security:admin',
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
            'audit_log:read' => 'Can view audit logs (scoped to user permissions)',
            'audit_log:write' => 'Can create audit log entries',
            'audit_log:delete' => 'Can delete audit log entries',
            'audit_log:admin' => 'Full administrative access to audit logs including export and purge',
        ];

        foreach ($activityPermissions as $permission => $description) {
            $this->command->info("• {$permission}: {$description}");
        }

        $this->command->info("\n=== ROLES WITH ACTIVITY LOG ACCESS ===");

        foreach ($roles as $roleName => $roleData) {
            $activityPerms = array_filter($roleData['permissions'], function($perm) {
                return strpos($perm, 'audit_log:') === 0;
            });

            if (!empty($activityPerms)) {
                $this->command->info("• {$roleName}: " . implode(', ', $activityPerms));
            }
        }

        $this->command->info("\n=== ROLE HIERARCHY FOR AUDIT LOGS (GitHub-style) ===");
        $this->command->info("• Employees: Basic audit log read access (own activities)");
        $this->command->info("• Managers/Board Members: Organization-scoped audit log read access");
        $this->command->info("• Organization Admins: Full audit log administration within organization");
        $this->command->info("• Auditors: Read-only access to all audit logs with export capabilities");
        $this->command->info("• Security Admins: Full audit log administration including deletion and purging");
        $this->command->info("• Super Admins: Complete access to all audit log features");
        
        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $this->command->info("\nSeeding completed: {$totalPermissions} permissions, {$totalRoles} roles created/updated.");
    }
}
