<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // System users and permissions
        $this->call([
            SystemUserSeeder::class,
            IndustrySpecificPermissionsSeeder::class,
        ]);

        // Region data
        $this->call([
            RegionSeeder::class,
        ]);

        // Organizational structure seeders
        $this->call([
            OrganizationPositionLevelSeeder::class,
            OrganizationSeeder::class,
            OrganizationVariantsSeeder::class,
            OrganizationUnitSeeder::class,
            OrganizationPositionSeeder::class,
            OrganizationMembershipSeeder::class,
            OrganizationalStructureSeeder::class,
        ]);

        // OAuth and scopes - comprehensive setup
        $this->call([
            PassportOAuthSeeder::class,
            PersonalAccessTokenSeeder::class,
            OAuthClientSeeder::class, // Ensure OAuth clients are properly seeded
        ]);

        // File system permissions
        $this->call([
            FileSystemPermissionsSeeder::class,
        ]);
    }
}
