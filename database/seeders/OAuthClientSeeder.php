<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultOrg = Organization::first();
        
        if (!$defaultOrg) {
            $this->command->warn('No organizations found. Creating default organization first.');
            $defaultOrg = Organization::create([
                'name' => 'Default Organization',
                'type' => 'organization',
                'is_active' => true,
                'description' => 'Default organization for OAuth clients',
            ]);
        }

        // Create main web application client
        $this->createClient([
            'id' => '01K4B3Y03E5Q1QC121V7TP828J',
            'name' => 'Main Web Application',
            'secret' => null, // Public client for SPA
            'provider' => 'users',
            'redirect_uris' => [
                config('app.url') . '/auth/callback',
                config('app.url') . '/oauth/callback',
            ],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'all_users',
            'client_type' => 'public',
            'description' => 'Main single-page application OAuth client',
        ]);

        // Create mobile application client
        $this->createClient([
            'id' => '01K4B3Y03JK4A40A6YE5ZDP230',
            'name' => 'Mobile Application',
            'secret' => null, // Public client for mobile apps
            'provider' => 'users',
            'redirect_uris' => [
                'app://oauth/callback',
                'http://localhost:3000/auth/callback', // For development
            ],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'all_users',
            'client_type' => 'public',
            'description' => 'Mobile application OAuth client',
        ]);

        // Create personal access token client
        $this->createClient([
            'id' => '01K41FNNTCEWA8R2RBRCNYSVXA',
            'name' => 'Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'all_users',
            'client_type' => 'public',
            'description' => 'Personal access token client for API access',
        ]);

        // Create management dashboard client
        $this->createClient([
            'id' => '01K4B3Y03PK034FAJ7PCMN4DK1',
            'name' => 'Management Dashboard',
            'secret' => Hash::make('dashboard-secret-' . \Illuminate\Support\Str::random(20)),
            'provider' => 'users',
            'redirect_uris' => [
                config('app.url') . '/admin/auth/callback',
            ],
            'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'organization_members',
            'client_type' => 'confidential',
            'description' => 'Management dashboard for organization members',
        ]);

        // Create developer tools client
        $this->createClient([
            'id' => '01K4B3Y03TM2WE4P1M1MH6S4A5',
            'name' => 'Developer Tools',
            'secret' => Hash::make('dev-tools-secret-' . \Illuminate\Support\Str::random(20)),
            'provider' => 'users',
            'redirect_uris' => [
                'http://localhost:8080/auth/callback',
                'http://localhost:3000/auth/callback',
                'http://127.0.0.1:8080/auth/callback',
            ],
            'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials', 'password'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'roles' => ['developer', 'admin'],
                'organization_roles' => ['developer', 'admin', 'owner'],
                'email_domains' => ['localhost', 'example.com'],
            ],
            'client_type' => 'confidential',
            'description' => 'Developer tools and testing client',
        ]);

        // Create external partner integration client
        $this->createClient([
            'id' => '01K4B3Y03YMC0V885T65ZR986B',
            'name' => 'External Partner Integration',
            'secret' => Hash::make('partner-secret-' . \Illuminate\Support\Str::random(32)),
            'provider' => null, // Client credentials only
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
            'revoked' => false,
            'organization_id' => $defaultOrg->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'organization_roles' => ['partner', 'admin'],
            ],
            'client_type' => 'confidential',
            'description' => 'External partner API integration client',
        ]);
    }

    /**
     * Create an OAuth client if it doesn't exist.
     */
    private function createClient(array $attributes): void
    {
        $existing = Client::find($attributes['id']);
        
        if ($existing) {
            $this->command->info("OAuth client '{$attributes['name']}' already exists, skipping.");
            return;
        }

        try {
            Client::create($attributes);
            $this->command->info("Created OAuth client: {$attributes['name']}");
        } catch (\Exception $e) {
            $this->command->error("Failed to create OAuth client '{$attributes['name']}': " . $e->getMessage());
        }
    }
}