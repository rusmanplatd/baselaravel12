<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run()
    {
        // Create system user first and set auth context
        $systemUser = User::firstOrCreate([
            'email' => 'system@system.local',
        ], [
            'name' => 'System User',
            'username' => 'system',
            'email' => 'system@system.local',
            'password' => Hash::make('system-user-password-'.bin2hex(random_bytes(16))),
            'email_verified_at' => now(),
        ]);

        // Set auth context for model boot methods
        Auth::login($systemUser);

        // Create permissions and roles first
        $this->createPermissionsAndRoles();

        // Create additional system/service users
        $systemUsers = [
            [
                'name' => 'OAuth Service',
                'username' => 'oauth',
                'email' => 'oauth@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Audit Service',
                'username' => 'audit',
                'email' => 'audit@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Backup Service',
                'username' => 'backup',
                'email' => 'backup@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Monitoring Service',
                'username' => 'monitor',
                'email' => 'monitor@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Notification Service',
                'username' => 'notifications',
                'email' => 'notifications@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($systemUsers as $userData) {
            User::firstOrCreate([
                'email' => $userData['email'],
            ], $userData);
        }

        // Create main test user
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        // Create additional demo users
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $managerUser = User::factory()->create([
            'name' => 'Manager User',
            'username' => 'manager',
            'email' => 'manager@example.com',
        ]);

        $regularUser = User::factory()->create([
            'name' => 'Regular User',
            'username' => 'user',
            'email' => 'user@example.com',
        ]);

        // Create additional staff users
        User::factory(50)->create();

        // Create some department-specific users
        User::factory(5)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        User::factory(15)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        User::factory(8)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        User::factory(6)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        User::factory(4)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        // Create organization-specific users
        $organizationUsers = [
            [
                'name' => 'John Smith',
                'username' => 'john.smith',
                'email' => 'john.smith@techcorp.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Jane Doe',
                'username' => 'jane.doe',
                'email' => 'jane.doe@techcorp.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Mike Johnson',
                'username' => 'mike.johnson',
                'email' => 'mike.johnson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Sarah Wilson',
                'username' => 'sarah.wilson',
                'email' => 'sarah.wilson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'David Brown',
                'username' => 'david.brown',
                'email' => 'david.brown@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Emily Davis',
                'username' => 'emily.davis',
                'email' => 'emily.davis@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Robert Taylor',
                'username' => 'robert.taylor',
                'email' => 'robert.taylor@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Lisa Anderson',
                'username' => 'lisa.anderson',
                'email' => 'lisa.anderson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Michael Chen',
                'username' => 'michael.chen',
                'email' => 'michael.chen@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Jennifer Martinez',
                'username' => 'jennifer.martinez',
                'email' => 'jennifer.martinez@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Alex Thompson',
                'username' => 'alex.thompson',
                'email' => 'alex.thompson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Maria Rodriguez',
                'username' => 'maria.rodriguez',
                'email' => 'maria.rodriguez@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($organizationUsers as $userData) {
            $userData['created_by'] = $testUser->id;
            $userData['updated_by'] = $testUser->id;
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }

        // Wait for other seeders to create the DEFAULT organization
        // For now, don't assign roles to demo users as it will be handled later
        // after the DEFAULT organization is created by other seeders
        
        // Store user IDs for later role assignment by other seeders
        config([
            'seeder.test_user_id' => $testUser->id,
            'seeder.admin_user_id' => $adminUser->id,
            'seeder.manager_user_id' => $managerUser->id,
            'seeder.regular_user_id' => $regularUser->id,
        ]);

        // Ensure all users have usernames
        $this->ensureAllUsersHaveUsernames();

        // Assign basic chat permissions to all users
        $this->assignChatPermissionsToAllUsers();

        // Logout system user after seeding
        Auth::logout();
    }

    /**
     * Create permissions and roles (merged from PermissionSeeder)
     */
    private function createPermissionsAndRoles(): void
    {
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

            // Geography management permissions (GitHub-style)
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

            // Chat permissions (GitHub-style) - global access
            'chat:read' => 'View chat conversations and messages',
            'chat:write' => 'Send messages and participate in chat',
            'chat:files' => 'Upload and download files in chat',
            'chat:calls' => 'Participate in audio/video calls',
            'chat:manage' => 'Manage chat settings and conversations',
            'chat:moderate' => 'Moderate chat content and users',
            'chat:admin' => 'Full administrative access to chat system',
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
                    'geo_country:read',
                    'geo_country:write',
                    'geo_country:delete',
                    'geo_country:admin',
                    'geo_province:read',
                    'geo_province:write',
                    'geo_province:delete',
                    'geo_province:admin',
                    'geo_city:read',
                    'geo_city:write',
                    'geo_city:delete',
                    'geo_city:admin',
                    'geo_district:read',
                    'geo_district:write',
                    'geo_district:delete',
                    'geo_district:admin',
                    'geo_village:read',
                    'geo_village:write',
                    'geo_village:delete',
                    'geo_village:admin',
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
                    'chat:manage',
                    'chat:moderate',
                    'chat:admin',
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
                    'geo_country:read',
                    'geo_province:read',
                    'geo_city:read',
                    'geo_district:read',
                    'geo_village:read',
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
                    'chat:manage',
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
                    'geo_country:read',
                    'geo_province:read',
                    'geo_city:read',
                    'geo_district:read',
                    'geo_village:read',
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
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
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
                    'chat:manage',
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
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
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
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
                    'chat:manage',
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
                    'chat:read',
                    'chat:write',
                    'chat:files',
                    'chat:calls',
                    'chat:manage',
                    'chat:moderate',
                    'chat:admin',
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

            $this->command->info("Created role: {$roleName} with ".count($permissions).' permissions');
        }

        // If teams are enabled, also create team-specific roles for the DEFAULT organization
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();

            if ($defaultOrg) {
                setPermissionsTeamId($defaultOrg->id);

                foreach ($roles as $roleName => $roleData) {
                    // Create team-specific role for the DEFAULT organization
                    $teamRole = Role::firstOrCreate([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'team_id' => $defaultOrg->id,
                    ]);

                    // Sync permissions with team context
                    $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
                    $teamRole->syncPermissions($permissions);

                    $this->command->info("Created team role: {$roleName} for DEFAULT org with ".count($permissions).' permissions');
                }

                setPermissionsTeamId(null);
            }
        }

        // Display summary
        $this->displaySummary($roles);
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
            $activityPerms = array_filter($roleData['permissions'], function ($perm) {
                return strpos($perm, 'audit_log:') === 0;
            });

            if (! empty($activityPerms)) {
                $this->command->info("• {$roleName}: ".implode(', ', $activityPerms));
            }
        }

        $this->command->info("\n=== ROLE HIERARCHY FOR AUDIT LOGS (GitHub-style) ===");
        $this->command->info('• Employees: Basic audit log read access (own activities)');
        $this->command->info('• Managers/Board Members: Organization-scoped audit log read access');
        $this->command->info('• Organization Admins: Full audit log administration within organization');
        $this->command->info('• Auditors: Read-only access to all audit logs with export capabilities');
        $this->command->info('• Security Admins: Full audit log administration including deletion and purging');
        $this->command->info('• Super Admins: Complete access to all audit log features');

        $totalPermissions = Permission::count();
        $totalRoles = Role::count();
        $this->command->info("\nSeeding completed: {$totalPermissions} permissions, {$totalRoles} roles created/updated.");
    }

    /**
     * Ensure all users have usernames
     */
    private function ensureAllUsersHaveUsernames(): void
    {
        $this->command->info("Ensuring all users have usernames...");
        
        $usersWithoutUsernames = User::whereNull('username')->orWhere('username', '')->get();
        
        foreach ($usersWithoutUsernames as $user) {
            // Generate a base username from email or name
            $baseUsername = null;
            
            if ($user->email) {
                $baseUsername = strtolower(explode('@', $user->email)[0]);
            } elseif ($user->name) {
                $baseUsername = strtolower(str_replace(' ', '.', $user->name));
            } else {
                $baseUsername = 'user' . $user->id;
            }
            
            // Clean the username (remove special characters, keep only alphanumeric, dots, dashes, underscores)
            $baseUsername = preg_replace('/[^a-z0-9._-]/', '', $baseUsername);
            
            // Ensure uniqueness
            $username = $baseUsername;
            $counter = 1;
            
            while (User::where('username', $username)->where('id', '!=', $user->id)->exists()) {
                $username = $baseUsername . '.' . $counter;
                $counter++;
            }
            
            $user->username = $username;
            $user->save();
            
            $this->command->info("Generated username '{$username}' for user: {$user->name} ({$user->email})");
        }
        
        $this->command->info("Processed " . $usersWithoutUsernames->count() . " users for username generation.");
    }

    /**
     * Assign basic chat permissions to all users
     */
    private function assignChatPermissionsToAllUsers(): void
    {
        // Define basic chat permissions that all users should have
        $basicChatPermissions = [
            'chat:read',
            'chat:write',
            'chat:files', 
            'chat:calls',
        ];

        // Get all users
        $users = User::all();
        
        // Get the basic chat permissions
        $permissions = Permission::whereIn('name', $basicChatPermissions)->get();

        $this->command->info("Assigning basic chat permissions to all users...");

        foreach ($users as $user) {
            // Set permissions team context to null for global permissions
            setPermissionsTeamId(null);
            
            // Give the user these permissions directly
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }

        $this->command->info("Assigned basic chat permissions to " . $users->count() . " users.");
    }
}
