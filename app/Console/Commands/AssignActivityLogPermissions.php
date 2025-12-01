<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignActivityLogPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity-log:assign-permissions 
                            {--user= : The user email to assign permissions to}
                            {--role= : The role to assign (employee, manager, organization-admin, auditor, security-admin, super-admin)}
                            {--list : List all available roles and their activity log permissions}
                            {--show-users : Show all users and their current activity log permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign activity log permissions to users based on their roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('list')) {
            $this->listRolesAndPermissions();

            return 0;
        }

        if ($this->option('show-users')) {
            $this->showUsersWithPermissions();

            return 0;
        }

        $userEmail = $this->option('user');
        $roleName = $this->option('role');

        if (! $userEmail) {
            $userEmail = $this->ask('Enter the user email');
        }

        if (! $roleName) {
            $this->listAvailableRoles();
            $roleName = $this->choice('Choose a role', [
                'employee',
                'manager',
                'organization-admin',
                'auditor',
                'security-admin',
                'super-admin',
            ]);
        }

        $user = User::where('email', $userEmail)->first();

        if (! $user) {
            $this->error("User with email {$userEmail} not found.");

            return 1;
        }

        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            $this->error("Role {$roleName} not found.");

            return 1;
        }

        // Set permissions team context to null for global permissions
        setPermissionsTeamId(null);

        $user->assignRole($role);

        $activityPermissions = $role->permissions()
            ->where('name', 'LIKE', 'activity.%')
            ->pluck('name')
            ->toArray();

        $this->info("Successfully assigned role '{$roleName}' to user {$user->name} ({$user->email})");

        if (! empty($activityPermissions)) {
            $this->info('Activity log permissions granted:');
            foreach ($activityPermissions as $permission) {
                $this->line("  • {$permission}");
            }
        } else {
            $this->warn('No activity log permissions found for this role.');
        }

        // Show what the user can now do
        $this->showUserCapabilities($user, $activityPermissions);

        return 0;
    }

    /**
     * List all roles and their activity log permissions
     */
    private function listRolesAndPermissions(): void
    {
        $this->info("=== ACTIVITY LOG ROLES AND PERMISSIONS ===\n");

        $roles = [
            'super-admin' => ['audit_log:read', 'audit_log:write', 'audit_log:delete', 'audit_log:admin'],
            'organization-admin' => ['audit_log:read', 'audit_log:delete', 'audit_log:admin'],
            'security-admin' => ['audit_log:read', 'audit_log:delete', 'audit_log:admin'],
            'auditor' => ['audit_log:read', 'audit_log:admin'],
            'manager' => ['audit_log:read'],
            'board-member' => ['audit_log:read'],
            'employee' => ['audit_log:read'],
            'consultant' => ['audit_log:read'],
        ];

        foreach ($roles as $roleName => $permissions) {
            $this->info("🔹 {$roleName}:");
            foreach ($permissions as $permission) {
                $description = $this->getPermissionDescription($permission);
                $this->line("  • {$permission} - {$description}");
            }
            $this->line('');
        }
    }

    /**
     * Show all users and their current activity log permissions
     */
    private function showUsersWithPermissions(): void
    {
        $this->info("=== USERS WITH ACTIVITY LOG PERMISSIONS ===\n");

        $users = User::with('roles.permissions')
            ->whereHas('permissions', function ($query) {
                $query->where('name', 'LIKE', 'activity.%');
            })
            ->orWhereHas('roles.permissions', function ($query) {
                $query->where('name', 'LIKE', 'activity.%');
            })
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No users found with activity log permissions.');

            return;
        }

        foreach ($users as $user) {
            $this->info("👤 {$user->name} ({$user->email})");

            // Get roles
            $roles = $user->roles->pluck('name')->toArray();
            if (! empty($roles)) {
                $this->line('  Roles: '.implode(', ', $roles));
            }

            // Get activity permissions
            $activityPermissions = $user->getAllPermissions()
                ->filter(function ($permission) {
                    return strpos($permission->name, 'activity.') === 0;
                })
                ->pluck('name')
                ->toArray();

            if (! empty($activityPermissions)) {
                $this->line('  Activity Permissions:');
                foreach ($activityPermissions as $permission) {
                    $this->line("    • {$permission}");
                }
            }

            $this->line('');
        }
    }

    /**
     * List available roles for selection
     */
    private function listAvailableRoles(): void
    {
        $this->info('Available roles:');
        $this->line('• employee - Can view own activities only');
        $this->line('• manager - Can view organization activities');
        $this->line('• organization-admin - Can view, delete organization + all activities');
        $this->line('• auditor - Can view and export all activities (read-only)');
        $this->line('• security-admin - Can manage all activity logs');
        $this->line('• super-admin - Full system access');
        $this->line('');
    }

    /**
     * Get permission description
     */
    private function getPermissionDescription(string $permission): string
    {
        $descriptions = [
            'audit_log:read' => 'View audit logs (scoped to user permissions)',
            'audit_log:write' => 'Create audit log entries',
            'audit_log:delete' => 'Delete audit log entries',
            'audit_log:admin' => 'Full administrative access to audit logs including export and purge',
        ];

        return $descriptions[$permission] ?? 'Unknown permission';
    }

    /**
     * Show what the user can now do with their permissions
     */
    private function showUserCapabilities(User $user, array $activityPermissions): void
    {
        $this->info("\n=== USER CAPABILITIES ===");

        if (in_array('audit_log:admin', $activityPermissions)) {
            $this->line('✅ Can access the Activity Log page and view ALL system activities');
        } elseif (in_array('audit_log:read', $activityPermissions)) {
            $this->line('✅ Can access the Activity Log page and view activities within their organization scope');
        } else {
            $this->line('❌ Cannot access the Activity Log page');
        }

        if (in_array('audit_log:admin', $activityPermissions)) {
            $this->line('✅ Can export activity logs');
        }

        if (in_array('audit_log:delete', $activityPermissions) || in_array('audit_log:admin', $activityPermissions)) {
            $this->line('✅ Can delete activity logs');
        }

        if (in_array('audit_log:admin', $activityPermissions)) {
            $this->line('✅ Can purge old audit logs');
        }

        $this->info("\nThe user can now access the Activity Log at: /activity-log");
    }
}
