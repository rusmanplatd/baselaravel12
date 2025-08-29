<?php

namespace Database\Seeders;

use App\Models\OAuthScope;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PassportOAuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding comprehensive OAuth 2.0/OIDC setup...');

        // First, seed OAuth scopes
        $this->seedOAuthScopes();

        // Then, create sample users and organizations if needed
        $user = $this->createSampleUser();
        $organization = $this->createSampleOrganization($user);

        // Seed various OAuth clients with different configurations
        $this->seedOAuthClients($user, $organization);

        $this->command->info('PassportOAuth seeding completed successfully!');
    }

    /**
     * Seed comprehensive OAuth scopes.
     */
    protected function seedOAuthScopes(): void
    {
        $scopes = [
            // OpenID Connect core scopes
            [
                'identifier' => 'openid',
                'name' => 'OpenID Connect',
                'description' => 'Authenticate using OpenID Connect',
                'is_default' => true,
            ],
            [
                'identifier' => 'profile',
                'name' => 'Profile Information',
                'description' => 'Access your basic profile information like name and picture',
                'is_default' => true,
            ],
            [
                'identifier' => 'email',
                'name' => 'Email Address',
                'description' => 'Access your email address',
                'is_default' => true,
            ],

            // Google-style organization management scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/organization.readonly',
                'name' => 'Organization Read Access',
                'description' => 'Read access to your organization data',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/organization',
                'name' => 'Organization Management',
                'description' => 'Full access to modify organization data',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/organization.members',
                'name' => 'Organization Members',
                'description' => 'Access to organization membership information',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/organization.admin',
                'name' => 'Organization Administration',
                'description' => 'Administrative access to organization settings and hierarchy',
                'is_default' => false,
            ],

            // Google-style user management scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/userinfo.profile',
                'name' => 'User Profile Access',
                'description' => 'Read user profile and basic information',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/userinfo.email',
                'name' => 'User Email Access',
                'description' => 'Access user email information',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/user.modify',
                'name' => 'User Profile Management',
                'description' => 'Update user profile and settings',
                'is_default' => false,
            ],

            // Chat and communication scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/chat.readonly',
                'name' => 'Chat Read Access',
                'description' => 'Read access to chat conversations and messages',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/chat',
                'name' => 'Chat Management',
                'description' => 'Full access to chat functionality including sending messages',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/chat.encryption',
                'name' => 'Chat Encryption Keys',
                'description' => 'Access to encrypted chat functionality and key management',
                'is_default' => false,
            ],

            // Google-style analytics and reporting scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/analytics.readonly',
                'name' => 'Analytics Read Access',
                'description' => 'Access to analytics and reporting data',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/reports',
                'name' => 'Reports Access',
                'description' => 'Generate and access business reports',
                'is_default' => false,
            ],

            // Google-style integration scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/webhooks',
                'name' => 'Webhooks Management',
                'description' => 'Create and manage webhook subscriptions',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/integrations',
                'name' => 'Third-party Integrations',
                'description' => 'Access for third-party system integrations',
                'is_default' => false,
            ],

            // Google-style financial and security scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/finance.readonly',
                'name' => 'Financial Data Read',
                'description' => 'Access to financial and billing information',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/audit.readonly',
                'name' => 'Security Audit Access',
                'description' => 'Access to security logs and audit trails',
                'is_default' => false,
            ],

            // File storage and management
            [
                'identifier' => 'https://api.yourcompany.com/auth/files.readonly',
                'name' => 'File Read Access',
                'description' => 'Access to read and download files',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/files',
                'name' => 'File Management',
                'description' => 'Upload, modify, and delete files',
                'is_default' => false,
            ],

            // Device and security management
            [
                'identifier' => 'https://api.yourcompany.com/auth/devices',
                'name' => 'Device Management',
                'description' => 'Manage user devices and trusted device settings',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/security',
                'name' => 'Security Management',
                'description' => 'Access to security settings and multi-factor authentication',
                'is_default' => false,
            ],

            // Google-style platform scopes
            [
                'identifier' => 'https://api.yourcompany.com/auth/platform.full',
                'name' => 'Full Platform Access',
                'description' => 'Complete platform access for trusted applications',
                'is_default' => false,
            ],
            [
                'identifier' => 'https://api.yourcompany.com/auth/mobile',
                'name' => 'Mobile Application Access',
                'description' => 'Specialized access for mobile applications',
                'is_default' => false,
            ],

            // Standard OAuth scopes
            [
                'identifier' => 'offline_access',
                'name' => 'Offline Access',
                'description' => 'Ability to refresh tokens when user is offline',
                'is_default' => false,
            ],
        ];

        foreach ($scopes as $scope) {
            OAuthScope::updateOrCreate(
                ['identifier' => $scope['identifier']],
                $scope
            );
        }

        $this->command->info('✓ OAuth scopes seeded successfully.');
    }

    /**
     * Create sample user for OAuth clients.
     */
    protected function createSampleUser(): User
    {
        $user = User::firstOrCreate([
            'email' => 'oauth-admin@example.com',
        ], [
            'name' => 'OAuth Administrator',
            'password' => Hash::make('secure-password-123'),
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * Create sample organization for OAuth clients.
     */
    protected function createSampleOrganization(User $user): Organization
    {
        $organization = Organization::firstOrCreate([
            'organization_code' => 'OAUTH_ORG',
        ], [
            'name' => 'OAuth Organization',
            'organization_type' => 'holding_company',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $organization;
    }

    /**
     * Seed comprehensive OAuth clients.
     */
    protected function seedOAuthClients(User $user, Organization $organization): void
    {
        $clients = [
            // Web Application - Full access
            [
                'name' => 'Main Web Application',
                'client_type' => 'confidential',
                'user_access_scope' => 'all_users',
                'description' => 'Primary web application with full platform access',
                'website' => 'https://app.yourcompany.com',
                'redirect_uris' => [
                    'https://app.yourcompany.com/oauth/callback',
                    'https://staging.yourcompany.com/oauth/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email', 'offline_access',
                    'https://api.yourcompany.com/auth/organization.readonly',
                    'https://api.yourcompany.com/auth/organization.members',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/userinfo.email',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/chat',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/files',
                    'https://api.yourcompany.com/auth/devices',
                ],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ],

            // Mobile Application
            [
                'name' => 'Mobile Application',
                'client_type' => 'public',
                'user_access_scope' => 'all_users',
                'description' => 'Official mobile application for iOS and Android',
                'redirect_uris' => [
                    'com.yourcompany.mobile://oauth/callback',
                    'https://mobile.yourcompany.com/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email', 'offline_access',
                    'https://api.yourcompany.com/auth/mobile',
                    'https://api.yourcompany.com/auth/organization.readonly',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/chat',
                    'https://api.yourcompany.com/auth/chat.encryption',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/devices',
                    'https://api.yourcompany.com/auth/security',
                ],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ],

            // Management Dashboard - Organization members only
            [
                'name' => 'Management Dashboard',
                'client_type' => 'confidential',
                'user_access_scope' => 'organization_members',
                'description' => 'Executive dashboard for organization management',
                'website' => 'https://dashboard.yourcompany.com',
                'redirect_uris' => [
                    'https://dashboard.yourcompany.com/oauth/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email',
                    'https://api.yourcompany.com/auth/organization',
                    'https://api.yourcompany.com/auth/organization.admin',
                    'https://api.yourcompany.com/auth/organization.members',
                    'https://api.yourcompany.com/auth/analytics.readonly',
                    'https://api.yourcompany.com/auth/reports',
                    'https://api.yourcompany.com/auth/audit.readonly',
                    'https://api.yourcompany.com/auth/finance.readonly',
                ],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ],

            // Developer Tools - Custom access
            [
                'name' => 'Developer Tools',
                'client_type' => 'confidential',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'roles' => ['developer', 'admin', 'super-admin'],
                    'email_domains' => ['yourcompany.com'],
                ],
                'description' => 'Development tools and API testing for internal team',
                'website' => 'https://dev-tools.yourcompany.com',
                'redirect_uris' => [
                    'http://localhost:3000/oauth/callback',
                    'http://localhost:8080/oauth/callback',
                    'https://dev-tools.yourcompany.com/oauth/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email',
                    'https://api.yourcompany.com/auth/platform.full',
                    'https://api.yourcompany.com/auth/webhooks',
                    'https://api.yourcompany.com/auth/integrations',
                ],
                'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials'],
            ],

            // Third-Party Integration Service
            [
                'name' => 'External Partner Integration',
                'client_type' => 'confidential',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'email_domains' => ['partner.example.com', 'trusted-partner.org'],
                ],
                'description' => 'Third-party partner integration service',
                'website' => 'https://partner.example.com',
                'redirect_uris' => [
                    'https://partner.example.com/oauth/callback',
                    'https://integration.partner.example.com/auth/return',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email',
                    'https://api.yourcompany.com/auth/organization.readonly',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/integrations',
                ],
                'grant_types' => ['authorization_code', 'client_credentials'],
            ],

            // Analytics Service - Server-to-Server
            [
                'name' => 'Analytics Service',
                'client_type' => 'confidential',
                'user_access_scope' => 'all_users',
                'description' => 'Backend analytics and reporting service (machine-to-machine)',
                'redirect_uris' => [], // No redirect URIs for client credentials flow
                'allowed_scopes' => [
                    'https://api.yourcompany.com/auth/analytics.readonly',
                    'https://api.yourcompany.com/auth/reports',
                    'https://api.yourcompany.com/auth/organization.readonly',
                ],
                'grant_types' => ['client_credentials'],
            ],

            // Support Portal - Custom management access
            [
                'name' => 'Support Portal',
                'client_type' => 'confidential',
                'user_access_scope' => 'custom',
                'user_access_rules' => [
                    'organization_roles' => ['manager', 'admin', 'support'],
                    'position_levels' => ['director', 'vice_president', 'c_level'],
                ],
                'description' => 'Customer support and helpdesk portal for management team',
                'website' => 'https://support.yourcompany.com',
                'redirect_uris' => [
                    'https://support.yourcompany.com/oauth/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email',
                    'https://api.yourcompany.com/auth/organization.members',
                    'https://api.yourcompany.com/auth/userinfo.profile',
                    'https://api.yourcompany.com/auth/chat.readonly',
                    'https://api.yourcompany.com/auth/audit.readonly',
                ],
                'grant_types' => ['authorization_code', 'refresh_token'],
            ],

            // File Storage Service
            [
                'name' => 'File Storage Service',
                'client_type' => 'confidential',
                'user_access_scope' => 'all_users',
                'description' => 'Dedicated file storage and management service',
                'website' => 'https://files.yourcompany.com',
                'redirect_uris' => [
                    'https://files.yourcompany.com/oauth/callback',
                ],
                'allowed_scopes' => [
                    'openid', 'profile', 'email',
                    'https://api.yourcompany.com/auth/files',
                    'https://api.yourcompany.com/auth/files.readonly',
                    'https://api.yourcompany.com/auth/organization.readonly',
                ],
                'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials'],
            ],
        ];

        foreach ($clients as $clientData) {
            // Use direct database insertion to avoid potential ULID/UUID issues
            $clientId = (string) Str::ulid();
            $secret = $clientData['client_type'] === 'public' ? null : Str::random(32);

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

            $this->command->info("✓ Created OAuth client: {$clientData['name']} (ID: {$clientId})");
        }

        $this->command->info('✓ OAuth clients seeded successfully.');
    }
}