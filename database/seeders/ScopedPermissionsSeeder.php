<?php

namespace Database\Seeders;

use App\Facades\ScopedPermission;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScopedPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create global permissions (system-wide)
        $this->createGlobalPermissions();
        
        // Create global roles
        $this->createGlobalRoles();
        
        // Create organization-level permissions and roles
        $this->createOrganizationPermissions();
        
        // Create project-level permissions and roles
        $this->createProjectPermissions();
        
        // Create chat-level permissions and roles
        $this->createChatPermissions();
        
        // Set up sample scoped permissions for existing data
        $this->setupSampleScopedPermissions();
    }

    private function createGlobalPermissions(): void
    {
        $globalPermissions = [
            // System administration
            'manage_system',
            'view_system_logs',
            'manage_global_settings',
            
            // User management
            'create_users',
            'manage_all_users',
            'delete_users',
            
            // Organization management
            'create_organizations',
            'manage_all_organizations',
            'view_all_organizations',
            
            // Global reporting
            'view_global_reports',
            'export_data',
        ];

        foreach ($globalPermissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
                'is_global' => true,
            ]);
        }
    }

    private function createGlobalRoles(): void
    {
        // Super Admin - has all global permissions
        $superAdmin = Role::create([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'is_global' => true,
            'type' => 2, // system role
        ]);

        $superAdmin->givePermissionTo([
            'manage_system',
            'view_system_logs',
            'manage_global_settings',
            'create_users',
            'manage_all_users',
            'delete_users',
            'create_organizations',
            'manage_all_organizations',
            'view_all_organizations',
            'view_global_reports',
            'export_data',
        ]);

        // System Moderator - limited system access
        $systemModerator = Role::create([
            'name' => 'system_moderator',
            'guard_name' => 'web',
            'is_global' => true,
            'type' => 2,
        ]);

        $systemModerator->givePermissionTo([
            'view_system_logs',
            'view_all_organizations',
            'view_global_reports',
        ]);
    }

    private function createOrganizationPermissions(): void
    {
        $organizationPermissions = [
            // Basic organization access
            'view_organization',
            'edit_organization',
            'manage_organization',
            'delete_organization',
            
            // Member management
            'view_organization_members',
            'invite_members',
            'remove_members',
            'manage_member_roles',
            
            // Project management within organization
            'create_projects',
            'view_all_projects',
            'manage_all_projects',
            
            // Chat management within organization
            'create_chats',
            'manage_all_chats',
            'moderate_chats',
            
            // Reporting within organization
            'view_organization_reports',
            'export_organization_data',
        ];

        foreach ($organizationPermissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
                'is_global' => false,
                'scope_type' => null, // Will be set when assigned to specific organizations
                'scope_id' => null,
            ]);
        }

        // Organization roles (these will be created per organization)
        $this->createOrganizationRoleTemplates();
    }

    private function createOrganizationRoleTemplates(): void
    {
        // These are template roles that will be instantiated per organization
        $roleTemplates = [
            'organization_admin' => [
                'view_organization',
                'edit_organization',
                'manage_organization',
                'view_organization_members',
                'invite_members',
                'remove_members',
                'manage_member_roles',
                'create_projects',
                'view_all_projects',
                'manage_all_projects',
                'create_chats',
                'manage_all_chats',
                'moderate_chats',
                'view_organization_reports',
                'export_organization_data',
            ],
            'organization_manager' => [
                'view_organization',
                'edit_organization',
                'view_organization_members',
                'invite_members',
                'create_projects',
                'view_all_projects',
                'manage_all_projects',
                'create_chats',
                'view_organization_reports',
            ],
            'organization_member' => [
                'view_organization',
                'view_organization_members',
                'create_projects',
                'create_chats',
            ],
        ];

        // Store templates for later instantiation
        cache()->put('organization_role_templates', $roleTemplates, now()->addHours(24));
    }

    private function createProjectPermissions(): void
    {
        $projectPermissions = [
            // Basic project access
            'view_project',
            'edit_project',
            'manage_project',
            'delete_project',
            
            // Project member management
            'view_project_members',
            'add_project_members',
            'remove_project_members',
            'manage_project_roles',
            
            // Project content management
            'create_tasks',
            'edit_tasks',
            'delete_tasks',
            'assign_tasks',
            
            // Project files
            'upload_files',
            'download_files',
            'delete_files',
            
            // Project reporting
            'view_project_reports',
            'export_project_data',
        ];

        foreach ($projectPermissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
                'is_global' => false,
            ]);
        }
    }

    private function createChatPermissions(): void
    {
        $chatPermissions = [
            // Basic chat access
            'view_conversation',
            'join_conversation',
            'leave_conversation',
            
            // Messaging
            'send_message',
            'edit_own_message',
            'delete_own_message',
            
            // Moderation
            'moderate_chat',
            'delete_any_message',
            'mute_users',
            'ban_users',
            
            // Management
            'manage_chat',
            'add_participants',
            'remove_participants',
            'change_chat_settings',
            'delete_chat',
            
            // File sharing
            'share_files',
            'share_media',
        ];

        foreach ($chatPermissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
                'is_global' => false,
            ]);
        }

        // Create chat role templates
        $chatRoleTemplates = [
            'chat_admin' => [
                'view_conversation',
                'join_conversation',
                'send_message',
                'edit_own_message',
                'delete_own_message',
                'moderate_chat',
                'delete_any_message',
                'mute_users',
                'ban_users',
                'manage_chat',
                'add_participants',
                'remove_participants',
                'change_chat_settings',
                'share_files',
                'share_media',
            ],
            'chat_moderator' => [
                'view_conversation',
                'join_conversation',
                'send_message',
                'edit_own_message',
                'delete_own_message',
                'moderate_chat',
                'delete_any_message',
                'mute_users',
                'share_files',
                'share_media',
            ],
            'chat_member' => [
                'view_conversation',
                'send_message',
                'edit_own_message',
                'delete_own_message',
                'share_files',
                'share_media',
            ],
        ];

        cache()->put('chat_role_templates', $chatRoleTemplates, now()->addHours(24));
    }

    private function setupSampleScopedPermissions(): void
    {
        // Get sample data
        $organizations = Organization::limit(3)->get();
        $users = User::limit(10)->get();

        foreach ($organizations as $organization) {
            // Set up organization permission scope
            ScopedPermission::setupScopeHierarchy($organization);
            
            // Create organization-specific roles
            $this->createOrganizationRoles($organization);
            
            // Assign some users to organization roles
            $this->assignUsersToOrganization($organization, $users->random(5));
        }
    }

    private function createOrganizationRoles(Organization $organization): void
    {
        $roleTemplates = cache()->get('organization_role_templates', []);

        foreach ($roleTemplates as $roleName => $permissions) {
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'scope_type' => 'organization',
                'scope_id' => $organization->id,
                'team_id' => $organization->id,
                'is_global' => false,
                'type' => 1, // standard role
            ]);

            // Give permissions to role
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    private function assignUsersToOrganization(Organization $organization, $users): void
    {
        $roles = ['organization_admin', 'organization_manager', 'organization_member'];
        
        foreach ($users as $index => $user) {
            // First user becomes admin, second becomes manager, rest become members
            $roleName = match ($index) {
                0 => 'organization_admin',
                1 => 'organization_manager',
                default => 'organization_member',
            };

            ScopedPermission::assignRoleToUser(
                $user,
                $roleName,
                'organization',
                $organization->id
            );
        }
    }
}