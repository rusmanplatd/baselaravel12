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
        User::factory(15)->create();

        // Store the admin user ID for use in other seeders
        config(['seeder.admin_user_id' => $testUser->id]);

        // Role and Permission seeder
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Organizational structure seeders
        $this->call([
            OrganizationPositionLevelSeeder::class,
            OrganizationSeeder::class,
            OrganizationUnitSeeder::class,
            OrganizationPositionSeeder::class,
            OrganizationMembershipSeeder::class,
        ]);

        // Assign roles to demo users
        $testUser->assignRole('Super Admin');
        $adminUser->assignRole('Admin');
        $managerUser->assignRole('Manager');
        $regularUser->assignRole('User');
    }
}
