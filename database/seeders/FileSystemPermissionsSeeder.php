<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FileSystemPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create system user if it doesn't exist
        $systemUser = \DB::table('sys_users')->where('email', 'system@localhost')->first();
        if (!$systemUser) {
            $systemUserId = \Illuminate\Support\Str::ulid()->toBase32();
            \DB::table('sys_users')->insert([
                'id' => $systemUserId,
                'name' => 'System',
                'email' => 'system@localhost',
                'email_verified_at' => now(),
                'password' => bcrypt('password'), // Won't be used
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $systemUserId = $systemUser->id;
        }
        // Create file management permissions
        $permissions = [
            // File operations
            'files:read' => 'View files and folders',
            'files:write' => 'Create, upload, and edit files',
            'files:delete' => 'Delete files and folders',
            'files:download' => 'Download files',
            'files:move' => 'Move files and folders',
            'files:copy' => 'Copy files and folders',
            'files:restore' => 'Restore deleted files',
            
            // Folder operations
            'folders:read' => 'View folders',
            'folders:write' => 'Create and manage folder structure',
            'folders:admin' => 'Manage folder properties and permissions',
            
            // Sharing operations
            'file-sharing:read' => 'View shared files',
            'file-sharing:write' => 'Share files and folders',
            'file-sharing:admin' => 'Manage all shared files and sharing settings',
            'file-sharing:public' => 'Create public share links',
            'file-sharing:password' => 'Create password protected shares',
            'file-sharing:expiring' => 'Create expiring share links',
            
            // Permission management
            'file-permissions:read' => 'View file permissions',
            'file-permissions:write' => 'Manage file permissions',
            'file-permissions:admin' => 'Set inheritance rules and advanced permissions',
            
            // Comments and collaboration
            'file-comments:read' => 'View comments on files',
            'file-comments:write' => 'Add comments to files',
            'file-comments:admin' => 'Manage all comments',
            
            // Tags and organization
            'file-tags:read' => 'View tags on files',
            'file-tags:write' => 'Create and assign tags',
            'file-tags:admin' => 'Manage tag system',
            
            // Version management
            'file-versions:read' => 'View file versions',
            'file-versions:write' => 'Create file versions',
            'file-versions:admin' => 'Manage file version history',
            
            // Analytics and logs
            'file-analytics:read' => 'View file analytics',
            'file-logs:read' => 'View access logs',
            'file-logs:export' => 'Export access logs',
            
            // Administration
            'files:admin' => 'Full file system administration',
            'file-storage:admin' => 'Manage storage settings',
            'file-cleanup:admin' => 'Perform cleanup operations',
        ];

        foreach ($permissions as $name => $description) {
            $existingPermission = \DB::table('sys_permissions')->where('name', $name)->first();
            if (!$existingPermission) {
                \DB::table('sys_permissions')->insert([
                    'id' => \Illuminate\Support\Str::ulid()->toBase32(),
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            }
        }

        // Create file management roles
        $roles = [
            'file-admin' => [
                'description' => 'Full administrative access to file system',
                'permissions' => array_keys($permissions)
            ],
            
            'file-manager' => [
                'description' => 'Can manage files and folders with full permissions',
                'permissions' => [
                    'files:read', 'files:write', 'files:delete',
                    'files:download', 'files:move', 'files:copy', 'files:restore',
                    'folders:read', 'folders:write', 'folders:admin',
                    'file-sharing:read', 'file-sharing:write', 'file-sharing:public', 'file-sharing:password',
                    'file-permissions:read', 'file-permissions:write',
                    'file-comments:read', 'file-comments:write', 'file-tags:read', 'file-tags:write', 'file-tags:admin',
                    'file-versions:read', 'file-versions:write', 'file-versions:admin',
                ]
            ],
            
            'file-contributor' => [
                'description' => 'Can edit and manage files but not system settings',
                'permissions' => [
                    'files:read', 'files:write',
                    'files:download', 'files:move', 'files:copy',
                    'folders:read', 'folders:write', 'file-sharing:read', 'file-sharing:write',
                    'file-comments:read', 'file-comments:write', 'file-tags:read', 'file-tags:write',
                    'file-versions:read', 'file-versions:write',
                ]
            ],
            
            'file-viewer' => [
                'description' => 'Can only view and download files',
                'permissions' => [
                    'files:read', 'files:download', 'folders:read',
                    'file-sharing:read', 'file-comments:read', 'file-tags:read',
                    'file-versions:read',
                ]
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            // Create role using raw insert
            $existingRole = \DB::table('sys_roles')->where('name', $roleName)->first();
            if (!$existingRole) {
                $roleId = \Illuminate\Support\Str::ulid()->toBase32();
                \DB::table('sys_roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            } else {
                $roleId = $existingRole->id;
            }

            // Assign permissions to role
            foreach ($roleData['permissions'] as $permissionName) {
                $permission = \DB::table('sys_permissions')->where('name', $permissionName)->first();
                if ($permission) {
                    // Check if relationship already exists
                    $exists = \DB::table('sys_role_has_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $permission->id)
                        ->exists();
                    
                    if (!$exists) {
                        \DB::table('sys_role_has_permissions')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permission->id,
                        ]);
                    }
                }
            }
        }

        $this->command->info('File system permissions and roles created successfully.');
    }
}