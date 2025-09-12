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
            'files.view' => 'View files and folders',
            'files.create' => 'Create and upload files',
            'files.edit' => 'Edit file properties and content',
            'files.delete' => 'Delete files and folders',
            'files.download' => 'Download files',
            'files.move' => 'Move files and folders',
            'files.copy' => 'Copy files and folders',
            'files.restore' => 'Restore deleted files',
            
            // Folder operations
            'folders.create' => 'Create folders',
            'folders.manage' => 'Manage folder structure and properties',
            
            // Sharing operations
            'files.share' => 'Share files and folders',
            'files.share.public' => 'Create public share links',
            'files.share.password' => 'Create password protected shares',
            'files.share.expiring' => 'Create expiring share links',
            'files.share.manage' => 'Manage all shared files',
            
            // Permission management
            'files.permissions.view' => 'View file permissions',
            'files.permissions.manage' => 'Manage file permissions',
            'files.permissions.inherit' => 'Set inheritance rules',
            
            // Comments and collaboration
            'files.comment' => 'Add comments to files',
            'files.comment.manage' => 'Manage all comments',
            
            // Tags and organization
            'files.tags.create' => 'Create and assign tags',
            'files.tags.manage' => 'Manage tag system',
            
            // Version management
            'files.versions.view' => 'View file versions',
            'files.versions.manage' => 'Manage file versions',
            
            // Analytics and logs
            'files.analytics.view' => 'View file analytics',
            'files.logs.view' => 'View access logs',
            'files.logs.export' => 'Export access logs',
            
            // Administration
            'files.admin' => 'Full file system administration',
            'files.storage.manage' => 'Manage storage settings',
            'files.cleanup' => 'Perform cleanup operations',
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
            'File Manager' => [
                'description' => 'Can manage files and folders with full permissions',
                'permissions' => [
                    'files.view', 'files.create', 'files.edit', 'files.delete',
                    'files.download', 'files.move', 'files.copy', 'files.restore',
                    'folders.create', 'folders.manage',
                    'files.share', 'files.share.public', 'files.share.password',
                    'files.permissions.view', 'files.permissions.manage',
                    'files.comment', 'files.tags.create', 'files.tags.manage',
                    'files.versions.view', 'files.versions.manage',
                ]
            ],
            
            'File Editor' => [
                'description' => 'Can edit and manage files but not system settings',
                'permissions' => [
                    'files.view', 'files.create', 'files.edit',
                    'files.download', 'files.move', 'files.copy',
                    'folders.create', 'files.share',
                    'files.comment', 'files.tags.create',
                    'files.versions.view',
                ]
            ],
            
            'File Viewer' => [
                'description' => 'Can only view and download files',
                'permissions' => [
                    'files.view', 'files.download', 'files.comment',
                    'files.versions.view',
                ]
            ],
            
            'File Administrator' => [
                'description' => 'Full administrative access to file system',
                'permissions' => array_keys($permissions)
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