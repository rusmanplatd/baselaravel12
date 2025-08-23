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

        // Create a default organization if teams are enabled and no organizations exist
        $defaultOrgId = null;
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::firstOrCreate([
                'organization_code' => 'DEFAULT',
                'name' => 'Default Organization',
            ], [
                'organization_type' => 'holding_company',
                'parent_organization_id' => null,
                'description' => 'Default organization for system roles',
                'address' => 'System Default',
                'phone' => '+1-000-0000',
                'email' => 'system@default.com',
                'website' => 'https://default.com',
                'registration_number' => 'DEFAULT001',
                'tax_number' => 'TAX_DEFAULT',
                'establishment_date' => now()->format('Y-m-d'),
                'legal_status' => 'System Default',
                'business_activities' => 'System administration',
                'is_active' => true,
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
            ]);
            $defaultOrgId = $defaultOrg->id;
        }

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
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ], [
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
            ]);
        }

        $superAdminRoleData = [
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ];
        $superAdminRoleAttributes = [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ];

        if ($defaultOrgId) {
            $superAdminRoleData['team_id'] = $defaultOrgId;
            $superAdminRoleAttributes['team_id'] = $defaultOrgId;
        }

        $superAdminRole = Role::firstOrCreate($superAdminRoleData, $superAdminRoleAttributes);
        $superAdminRole->syncPermissions(Permission::all());

        $adminRoleData = [
            'name' => 'Admin',
            'guard_name' => 'web',
        ];
        $adminRoleAttributes = [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ];

        if ($defaultOrgId) {
            $adminRoleData['team_id'] = $defaultOrgId;
            $adminRoleAttributes['team_id'] = $defaultOrgId;
        }

        $adminRole = Role::firstOrCreate($adminRoleData, $adminRoleAttributes);
        $adminRole->syncPermissions([
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

        $managerRoleData = [
            'name' => 'Manager',
            'guard_name' => 'web',
        ];
        $managerRoleAttributes = [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ];

        if ($defaultOrgId) {
            $managerRoleData['team_id'] = $defaultOrgId;
            $managerRoleAttributes['team_id'] = $defaultOrgId;
        }

        $managerRole = Role::firstOrCreate($managerRoleData, $managerRoleAttributes);
        $managerRole->syncPermissions([
            'view users',
            'view organizations',
            'view organization units',
            'view organization positions',
            'view organization memberships',
            'create organization memberships',
            'edit organization memberships',
        ]);

        $userRoleData = [
            'name' => 'User',
            'guard_name' => 'web',
        ];
        $userRoleAttributes = [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ];

        if ($defaultOrgId) {
            $userRoleData['team_id'] = $defaultOrgId;
            $userRoleAttributes['team_id'] = $defaultOrgId;
        }

        $userRole = Role::firstOrCreate($userRoleData, $userRoleAttributes);
        $userRole->syncPermissions([
            'view organizations',
            'view organization units',
            'view organization positions',
            'view organization memberships',
        ]);
    }
}
