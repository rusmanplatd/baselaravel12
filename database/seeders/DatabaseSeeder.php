<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create main test user
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create additional demo users
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $managerUser = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
        ]);

        $regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
        ]);

        // Create additional staff users
        User::factory(50)->create();

        // Create some department-specific users
        $hrUsers = User::factory(5)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        $engineeringUsers = User::factory(15)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        $salesUsers = User::factory(8)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        $marketingUsers = User::factory(6)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        $financeUsers = User::factory(4)->create([
            'name' => fn () => fake()->name(),
            'email' => fn () => fake()->unique()->safeEmail(),
        ]);

        // Store the admin user ID for use in other seeders
        config(['seeder.admin_user_id' => $testUser->id]);

        // System users and permissions
        $this->call([
            SystemUserSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // Organizational structure seeders
        $this->call([
            OrganizationPositionLevelSeeder::class,
            OrganizationSeeder::class,
            OrganizationUnitSeeder::class,
            OrganizationPositionSeeder::class,
            OrganizationMembershipSeeder::class,
            OrganizationalStructureSeeder::class,
        ]);

        // OAuth and scopes
        $this->call([
            OAuthScopesSeeder::class,
        ]);

        // Assign roles to demo users
        // Get the default organization ID if teams are enabled
        $defaultOrgId = null;
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();
            $defaultOrgId = $defaultOrg?->id;
        }

        if ($defaultOrgId) {
            // When teams are enabled, we need to manually assign roles with team context
            $superAdminRole = \App\Models\Auth\Role::where('name', 'Super Admin')->where('team_id', $defaultOrgId)->first();
            $adminRole = \App\Models\Auth\Role::where('name', 'Admin')->where('team_id', $defaultOrgId)->first();
            $managerRole = \App\Models\Auth\Role::where('name', 'Manager')->where('team_id', $defaultOrgId)->first();
            $userRole = \App\Models\Auth\Role::where('name', 'User')->where('team_id', $defaultOrgId)->first();

            if ($superAdminRole) {
                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $testUser->id,
                    'team_id' => $defaultOrgId,
                ]);
            }

            if ($adminRole) {
                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                    'role_id' => $adminRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $adminUser->id,
                    'team_id' => $defaultOrgId,
                ]);
            }

            if ($managerRole) {
                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                    'role_id' => $managerRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $managerUser->id,
                    'team_id' => $defaultOrgId,
                ]);
            }

            if ($userRole) {
                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                    'role_id' => $userRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $regularUser->id,
                    'team_id' => $defaultOrgId,
                ]);
            }
        } else {
            $testUser->assignRole('Super Admin');
            $adminUser->assignRole('Admin');
            $managerUser->assignRole('Manager');
            $regularUser->assignRole('User');
        }
    }
}
