<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class ProjectScopedPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create project-specific permissions
        $this->createProjectPermissions();
        
        // Create project-specific roles
        $this->createProjectRoles();
    }

    private function createProjectPermissions(): void
    {
        $permissions = [
            // Core project permissions
            'projects:read' => 'View project and its items',
            'projects:write' => 'Edit project details and settings',
            'projects:delete' => 'Delete project',
            'projects:admin' => 'Archive/unarchive project and manage settings',
            
            // Item permissions
            'project-items:read' => 'View project items (issues, drafts, etc.)',
            'project-items:write' => 'Create and edit project items',
            'project-items:delete' => 'Delete project items',
            'project-items:archive' => 'Archive project items',
            'project-items:assign' => 'Assign items to users',
            'project-items:triage' => 'Change item status and convert types',
            
            // Field permissions
            'project-fields:read' => 'View custom fields',
            'project-fields:write' => 'Create and edit custom fields',
            'project-fields:delete' => 'Delete custom fields',
            'project-fields:admin' => 'Manage field configurations',
            
            // View permissions
            'project-views:read' => 'View project views',
            'project-views:write' => 'Create and edit project views',
            'project-views:delete' => 'Delete project views',
            'project-views:share' => 'Share views with others',
            
            // Member permissions
            'project-members:read' => 'View project members',
            'project-members:write' => 'Invite and manage members',
            'project-members:admin' => 'Edit member permissions and remove members',
            
            // Workflow permissions
            'project-workflows:read' => 'View workflows',
            'project-workflows:write' => 'Create and edit workflows',
            'project-workflows:delete' => 'Delete workflows',
            'project-workflows:trigger' => 'Manually trigger workflows',
            
            // Advanced permissions
            'projects:export' => 'Export project data',
            'projects:webhooks' => 'Manage project webhooks',
        ];

        $systemUser = User::first();
        
        if (!$systemUser) {
            throw new \Exception('No users found. Please ensure users are seeded first.');
        }

        foreach ($permissions as $name => $description) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            
            if (!$permission) {
                $permission = new Permission();
                $permission->id = \Illuminate\Support\Str::ulid();
                $permission->name = $name;
                $permission->guard_name = 'web';
                $permission->created_by = $systemUser->id;
                $permission->updated_by = $systemUser->id;
                $permission->save();
            }
        }
    }

    private function createProjectRoles(): void
    {
        $systemUser = User::first();
        
        if (!$systemUser) {
            throw new \Exception('No users found. Please ensure users are seeded first.');
        }

        $roles = [
            'project-admin' => [
                'permissions' => [
                    'projects:read', 'projects:write', 'projects:delete', 'projects:admin',
                    'project-items:read', 'project-items:write', 'project-items:delete', 'project-items:archive',
                    'project-items:assign', 'project-items:triage',
                    'project-fields:read', 'project-fields:write', 'project-fields:delete', 'project-fields:admin',
                    'project-views:read', 'project-views:write', 'project-views:delete', 'project-views:share',
                    'project-members:read', 'project-members:write', 'project-members:admin',
                    'project-workflows:read', 'project-workflows:write', 'project-workflows:delete', 'project-workflows:trigger',
                    'projects:export', 'projects:webhooks',
                ],
            ],
            'project-maintainer' => [
                'permissions' => [
                    'projects:read', 'projects:write',
                    'project-items:read', 'project-items:write', 'project-items:delete', 'project-items:archive',
                    'project-items:assign', 'project-items:triage',
                    'project-fields:read', 'project-fields:write', 'project-fields:admin',
                    'project-views:read', 'project-views:write', 'project-views:delete', 'project-views:share',
                    'project-members:read', 'project-members:write',
                    'project-workflows:read', 'project-workflows:write', 'project-workflows:trigger',
                    'projects:export',
                ],
            ],
            'project-contributor' => [
                'permissions' => [
                    'projects:read',
                    'project-items:read', 'project-items:write', 'project-items:assign', 'project-items:triage',
                    'project-fields:read', 'project-fields:admin',
                    'project-views:read', 'project-views:write', 'project-views:share',
                    'project-members:read',
                    'project-workflows:read', 'project-workflows:trigger',
                ],
            ],
            'project-viewer' => [
                'permissions' => [
                    'projects:read',
                    'project-items:read',
                    'project-fields:read',
                    'project-views:read',
                    'project-members:read',
                    'project-workflows:read',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            
            if (!$role) {
                $role = new Role();
                $role->id = \Illuminate\Support\Str::ulid();
                $role->name = $roleName;
                $role->guard_name = 'web';
                $role->created_by = $systemUser->id;
                $role->updated_by = $systemUser->id;
                $role->save();
            }

            // Assign permissions to role
            $permissions = Permission::where(function($query) use ($roleData) {
                foreach ($roleData['permissions'] as $permission) {
                    $query->orWhere('name', $permission);
                }
            })->get();
            $role->syncPermissions($permissions);
        }
    }
}