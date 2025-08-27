<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OAuthClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample user first
        $user = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'OAuth Admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create sample organization
        $organization = Organization::firstOrCreate([
            'organization_code' => 'SAMPLE_ORG',
        ], [
            'name' => 'Sample Organization',
            'organization_type' => 'holding_company',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Sample OAuth clients with different access scopes
        $clients = [
            [
                'name' => 'Public Mobile App',
                'user_access_scope' => 'all_users',
                'description' => 'A mobile application accessible by all registered users',
                'redirect_uris' => ['https://mobile.example.com/callback'],
            ],
            [
                'name' => 'Organization Portal',
                'user_access_scope' => 'organization_members',
                'description' => 'Internal portal for organization members only',
                'redirect_uris' => ['https://portal.example.com/oauth/callback'],
            ],
            [
                'name' => 'Management Dashboard',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'organization_roles' => ['manager', 'admin'],
                    'position_levels' => ['c_level', 'vice_president', 'director'],
                ],
                'description' => 'Executive dashboard with restricted access',
                'redirect_uris' => ['https://dashboard.example.com/auth/callback'],
            ],
            [
                'name' => 'Partner Integration',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'email_domains' => ['partner.example.com', 'external.org'],
                ],
                'description' => 'Third-party partner integration service',
                'redirect_uris' => ['https://partner.example.com/oauth/return'],
            ],
            [
                'name' => 'Developer Tools',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'roles' => ['developer', 'admin'],
                    'email_domains' => ['example.com'],
                ],
                'description' => 'Development tools for internal team',
                'redirect_uris' => ['http://localhost:3000/auth/callback', 'https://dev-tools.example.com/callback'],
            ],
        ];

        foreach ($clients as $clientData) {
            // Use raw DB insert to avoid activity logging issues with UUID vs ULID
            $clientId = (string) Str::uuid();

            \DB::table('oauth_clients')->insertOrIgnore([
                'id' => $clientId,
                'name' => $clientData['name'],
                'owner_id' => $user->id,
                'owner_type' => User::class,
                'secret' => Str::random(40),
                'provider' => null,
                'redirect_uris' => json_encode($clientData['redirect_uris']),
                'grant_types' => json_encode(['authorization_code', 'refresh_token']),
                'revoked' => false,
                'organization_id' => $organization->id,
                'allowed_scopes' => json_encode(['openid', 'profile', 'email', 'https://api.yourcompany.com/auth/organization.readonly']),
                'client_type' => 'public',
                'user_access_scope' => $clientData['user_access_scope'],
                'user_access_rules' => isset($clientData['user_access_rules']) ? json_encode($clientData['user_access_rules']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Created OAuth client: {$clientData['name']} (ID: {$clientId})");
        }

        $this->command->info('OAuth clients seeded successfully with various access control configurations.');
        $this->command->table(
            ['Access Scope', 'Description'],
            [
                ['all_users', 'Any authenticated user can access'],
                ['organization_members', 'Only organization members can access'],
                ['custom', 'Access controlled by custom rules (roles, positions, email domains, etc.)'],
            ]
        );
    }
}
