<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get admin user ID for created_by/updated_by
        $adminUserId = config('seeder.admin_user_id', 1);

        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Organization management
            'view organizations',
            'create organizations',
            'edit organizations',
            'delete organizations',

            // Organization structure
            'view organization units',
            'create organization units',
            'edit organization units',
            'delete organization units',

            'view organization positions',
            'create organization positions',
            'edit organization positions',
            'delete organization positions',

            'view organization memberships',
            'create organization memberships',
            'edit organization memberships',
            'delete organization memberships',

            // System administration
            'view system settings',
            'edit system settings',
            'view audit logs',
            'manage roles',
            'manage permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
            ]);
        }

        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $superAdminRole->givePermissionTo(Permission::all());

        $adminRole = Role::create([
            'name' => 'Admin',
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $adminRole->givePermissionTo([
            'view users',
            'create users',
            'edit users',
            'view organizations',
            'create organizations',
            'edit organizations',
            'view organization units',
            'create organization units',
            'edit organization units',
            'view organization positions',
            'create organization positions',
            'edit organization positions',
            'view organization memberships',
            'create organization memberships',
            'edit organization memberships',
            'view system settings',
        ]);

        $managerRole = Role::create([
            'name' => 'Manager',
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $managerRole->givePermissionTo([
            'view users',
            'view organizations',
            'view organization units',
            'view organization positions',
            'view organization memberships',
            'create organization memberships',
            'edit organization memberships',
        ]);

        $userRole = Role::create([
            'name' => 'User',
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $userRole->givePermissionTo([
            'view organizations',
            'view organization units',
            'view organization positions',
            'view organization memberships',
        ]);
    }
}
