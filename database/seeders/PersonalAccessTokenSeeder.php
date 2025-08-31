<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PersonalAccessTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates OAuth clients specifically for the api/generate-token endpoint
     * which uses Laravel Passport's personal access token functionality.
     */
    public function run(): void
    {
        $this->command->info('Seeding Personal Access Token OAuth clients...');

        // Create or get sample user and organization
        $user = $this->createSampleUser();
        $organization = $this->createSampleOrganization($user);

        // Create OAuth clients for personal access token generation
        $this->seedPersonalAccessTokenClients($user, $organization);

        $this->command->info('Personal Access Token seeding completed successfully!');
    }

    /**
     * Create sample user for personal access token clients.
     */
    protected function createSampleUser(): User
    {
        $user = User::firstOrCreate([
            'email' => 'token-admin@example.com',
        ], [
            'name' => 'Token Administrator',
            'password' => Hash::make('secure-token-password'),
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * Create sample organization for personal access token clients.
     */
    protected function createSampleOrganization(User $user): Organization
    {
        $organization = Organization::firstOrCreate([
            'organization_code' => 'TOKEN_ORG',
        ], [
            'name' => 'Personal Access Token Organization',
            'organization_type' => 'holding_company',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $organization;
    }

    /**
     * Seed OAuth clients specifically for personal access token generation.
     */
    protected function seedPersonalAccessTokenClients(User $user, Organization $organization): void
    {
        $clients = [
            // Personal Access Token Client - Main Application
            [
                'name' => 'Personal Access Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'all_users',
                'description' => 'Default client for generating personal access tokens via api/generate-token',
                'website' => 'https://app.yourcompany.com',
                'redirect_uris' => [], // No redirect URIs needed for personal access tokens
                'allowed_scopes' => [
                    'openid',
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/organization.readonly',
                    'https://api.yourcompany.com/auth/organization.members',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/userinfo.email',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/chat',
                    'https://api.yourcompany.com/auth/chat.encryption',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/files',
                    'https://api.yourcompany.com/auth/devices',
                    'https://api.yourcompany.com/auth/security',
                ],
                'grant_types' => ['personal_access'],
            ],

            // Chat Application Token Client
            [
                'name' => 'Chat Application Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'all_users',
                'description' => 'Specialized client for chat application personal access tokens',
                'website' => 'https://chat.yourcompany.com',
                'redirect_uris' => [],
                'allowed_scopes' => [
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/chat',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/chat.encryption',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/files',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                ],
                'grant_types' => ['personal_access'],
            ],

            // Mobile Application Token Client
            [
                'name' => 'Mobile Application Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'all_users',
                'description' => 'Personal access tokens for mobile applications',
                'redirect_uris' => [],
                'allowed_scopes' => [
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/mobile',
                    'https://api.yourcompany.com/auth/organization.readonly',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/chat',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/devices',
                    'https://api.yourcompany.com/auth/security',
                ],
                'grant_types' => ['personal_access'],
            ],

            // API Development Token Client
            [
                'name' => 'API Development Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'roles' => ['developer', 'admin', 'super-admin'],
                    'email_domains' => ['yourcompany.com'],
                ],
                'description' => 'Personal access tokens for API development and testing',
                'website' => 'https://api.yourcompany.com',
                'redirect_uris' => [],
                'allowed_scopes' => [
                    'openid',
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/platform.full',
                    'https://api.yourcompany.com/auth/organization',
                    'https://api.yourcompany.com/auth/organization.admin',
                    'https://api.yourcompany.com/auth/analytics.readonly',
                    'https://api.yourcompany.com/auth/webhooks',
                    'https://api.yourcompany.com/auth/integrations',
                    'https://api.yourcompany.com/auth/audit.readonly',
                ],
                'grant_types' => ['personal_access'],
            ],

            // Organization Admin Token Client
            [
                'name' => 'Organization Admin Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'organization_roles' => ['admin', 'manager'],
                    'position_levels' => ['director', 'vice_president', 'c_level'],
                ],
                'description' => 'Personal access tokens for organization administrators',
                'redirect_uris' => [],
                'allowed_scopes' => [
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/organization',
                    'https://api.yourcompany.com/auth/organization.admin',
                    'https://api.yourcompany.com/auth/organization.members',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/analytics.readonly',
                    'https://api.yourcompany.com/auth/reports',
                    'https://api.yourcompany.com/auth/audit.readonly',
                ],
                'grant_types' => ['personal_access'],
            ],

            // Limited Scope Token Client
            [
                'name' => 'Limited Scope Token Client',
                'client_type' => 'personal_access',
                'user_access_scope' => 'all_users',
                'description' => 'Personal access tokens with limited scope for basic API access',
                'redirect_uris' => [],
                'allowed_scopes' => [
                    'profile',
                    'email',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/userinfo.email',
                    'https://api.yourcompany.com/auth/organization.readonly',
                ],
                'grant_types' => ['personal_access'],
            ],
        ];

        foreach ($clients as $clientData) {
            // Use ULID for consistent ID length (26 characters)
            $clientId = (string) Str::ulid();

            // Personal access token clients don't need secrets
            $secret = null;

            DB::table('oauth_clients')->insertOrIgnore([
                'id' => $clientId,
                'name' => $clientData['name'],
                'owner_id' => $user->id,
                'owner_type' => User::class,
                'secret' => $secret,
                'provider' => null,
                'redirect_uris' => json_encode($clientData['redirect_uris']),
                'grant_types' => json_encode($clientData['grant_types']),
                'revoked' => false,
                'organization_id' => $organization->id,
                'allowed_scopes' => json_encode($clientData['allowed_scopes']),
                'client_type' => $clientData['client_type'],
                'user_access_scope' => $clientData['user_access_scope'],
                'user_access_rules' => isset($clientData['user_access_rules']) ? json_encode($clientData['user_access_rules']) : null,
                'description' => $clientData['description'],
                'website' => $clientData['website'] ?? null,
                'logo_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("✓ Created personal access token client: {$clientData['name']} (ID: {$clientId})");
        }

        $this->command->info('✓ Personal access token OAuth clients seeded successfully.');

        // Display information about the api/generate-token endpoint
        $this->command->info('');
        $this->command->info('🔗 API Generate Token Endpoint Information:');
        $this->command->info('   Route: POST /api/generate-token');
        $this->command->info('   Purpose: Generate personal access tokens for authenticated users');
        $this->command->info('   Usage: Used by the "Chat Application Token" client in the route');
        $this->command->info('   Token Name: "Chat Application Token"');
        $this->command->info('   Expiration: '.now()->addMonths(6)->format('Y-m-d H:i:s').' (6 months)');
        $this->command->info('');
        $this->command->info('📋 Available Personal Access Token Clients:');

        $this->command->table(
            ['Client Name', 'Access Scope', 'Primary Use Case'],
            [
                ['Personal Access Token Client', 'all_users', 'Default client for general API access'],
                ['Chat Application Token Client', 'all_users', 'Specialized for chat functionality'],
                ['Mobile Application Token Client', 'all_users', 'Mobile app API access'],
                ['API Development Token Client', 'custom (developers)', 'API development and testing'],
                ['Organization Admin Token Client', 'custom (admins)', 'Administrative access'],
                ['Limited Scope Token Client', 'all_users', 'Basic profile and organization read access'],
            ]
        );
    }
}
