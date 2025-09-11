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
            'project.view' => 'View project and its items',
            'project.edit' => 'Edit project details and settings',
            'project.delete' => 'Delete project',
            'project.archive' => 'Archive/unarchive project',
            
            // Item permissions
            'project.item.create' => 'Create project items (issues, drafts, etc.)',
            'project.item.edit' => 'Edit project items',
            'project.item.delete' => 'Delete project items',
            'project.item.archive' => 'Archive project items',
            'project.item.assign' => 'Assign items to users',
            'project.item.status' => 'Change item status',
            'project.item.convert' => 'Convert drafts to issues',
            
            // Field permissions
            'project.field.create' => 'Create custom fields',
            'project.field.edit' => 'Edit custom fields',
            'project.field.delete' => 'Delete custom fields',
            'project.field.values' => 'Edit field values on items',
            
            // View permissions
            'project.view.create' => 'Create project views',
            'project.view.edit' => 'Edit project views',
            'project.view.delete' => 'Delete project views',
            'project.view.share' => 'Share views with others',
            
            // Member permissions
            'project.member.invite' => 'Invite new members',
            'project.member.edit' => 'Edit member permissions',
            'project.member.remove' => 'Remove members',
            'project.member.view' => 'View project members',
            
            // Workflow permissions
            'project.workflow.create' => 'Create workflows',
            'project.workflow.edit' => 'Edit workflows',
            'project.workflow.delete' => 'Delete workflows',
            'project.workflow.trigger' => 'Manually trigger workflows',
            
            // Advanced permissions
            'project.settings' => 'Manage project settings',
            'project.export' => 'Export project data',
            'project.webhook.manage' => 'Manage project webhooks',
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
            'project.admin' => [
                'permissions' => [
                    'project.view', 'project.edit', 'project.delete', 'project.archive', 'project.settings',
                    'project.item.create', 'project.item.edit', 'project.item.delete', 'project.item.archive',
                    'project.item.assign', 'project.item.status', 'project.item.convert',
                    'project.field.create', 'project.field.edit', 'project.field.delete', 'project.field.values',
                    'project.view.create', 'project.view.edit', 'project.view.delete', 'project.view.share',
                    'project.member.invite', 'project.member.edit', 'project.member.remove', 'project.member.view',
                    'project.workflow.create', 'project.workflow.edit', 'project.workflow.delete', 'project.workflow.trigger',
                    'project.export', 'project.webhook.manage',
                ],
            ],
            'project.maintainer' => [
                'permissions' => [
                    'project.view', 'project.edit',
                    'project.item.create', 'project.item.edit', 'project.item.delete', 'project.item.archive',
                    'project.item.assign', 'project.item.status', 'project.item.convert',
                    'project.field.create', 'project.field.edit', 'project.field.values',
                    'project.view.create', 'project.view.edit', 'project.view.delete', 'project.view.share',
                    'project.member.invite', 'project.member.view',
                    'project.workflow.create', 'project.workflow.edit', 'project.workflow.trigger',
                    'project.export',
                ],
            ],
            'project.editor' => [
                'permissions' => [
                    'project.view',
                    'project.item.create', 'project.item.edit', 'project.item.assign', 'project.item.status', 'project.item.convert',
                    'project.field.values',
                    'project.view.create', 'project.view.edit', 'project.view.share',
                    'project.member.view',
                    'project.workflow.trigger',
                ],
            ],
            'project.contributor' => [
                'permissions' => [
                    'project.view',
                    'project.item.create', 'project.item.edit', 'project.item.status',
                    'project.field.values',
                    'project.view.create',
                    'project.member.view',
                ],
            ],
            'project.viewer' => [
                'permissions' => [
                    'project.view',
                    'project.member.view',
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