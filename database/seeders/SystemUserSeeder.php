<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run()
    {
        // Create system user
        User::firstOrCreate([
            'email' => 'system@system.local',
        ], [
            'name' => 'System User',
            'email' => 'system@system.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create additional system/service users
        $systemUsers = [
            [
                'name' => 'OAuth Service',
                'email' => 'oauth@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Audit Service',
                'email' => 'audit@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Backup Service',
                'email' => 'backup@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Monitoring Service',
                'email' => 'monitor@system.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Notification Service',
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

        // Store the admin user ID for use in other seeders
        config(['seeder.admin_user_id' => $testUser->id]);

        // Create organization-specific users
        $organizationUsers = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@techcorp.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'jane.doe@techcorp.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Emily Davis',
                'email' => 'emily.davis@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Robert Taylor',
                'email' => 'robert.taylor@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Lisa Anderson',
                'email' => 'lisa.anderson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Jennifer Martinez',
                'email' => 'jennifer.martinez@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Alex Thompson',
                'email' => 'alex.thompson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Maria Rodriguez',
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

        // Assign roles to demo users
        // Get the default organization ID if teams are enabled
        $defaultOrgId = null;
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();
            $defaultOrgId = $defaultOrg?->id;
        }

        if ($defaultOrgId) {
            // When teams are enabled, we need to manually assign roles with team context
            $superAdminRole = \App\Models\Auth\Role::where('name', 'super-admin')->where('team_id', $defaultOrgId)->first();
            $adminRole = \App\Models\Auth\Role::where('name', 'organization-admin')->where('team_id', $defaultOrgId)->first();
            $managerRole = \App\Models\Auth\Role::where('name', 'manager')->where('team_id', $defaultOrgId)->first();
            $userRole = \App\Models\Auth\Role::where('name', 'employee')->where('team_id', $defaultOrgId)->first();

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
            $testUser->assignRole('super-admin');
            $adminUser->assignRole('organization-admin');
            $managerUser->assignRole('manager');
            $regularUser->assignRole('employee');
        }
    }
}
