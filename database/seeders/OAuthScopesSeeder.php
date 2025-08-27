<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OAuthScopesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
            \App\Models\OAuthScope::updateOrCreate(
                ['identifier' => $scope['identifier']],
                $scope
            );
        }

        $this->command->info('OAuth scopes seeded successfully.');
    }
}
