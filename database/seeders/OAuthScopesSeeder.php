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
            [
                'identifier' => 'organization:read',
                'name' => 'Organization Read',
                'description' => 'Read access to your organization data',
                'is_default' => false,
            ],
            [
                'identifier' => 'organization:write',
                'name' => 'Organization Write',
                'description' => 'Modify organization data',
                'is_default' => false,
            ],
            [
                'identifier' => 'organization:members',
                'name' => 'Organization Members',
                'description' => 'Access to organization membership information',
                'is_default' => false,
            ],
            [
                'identifier' => 'organization:admin',
                'name' => 'Organization Admin',
                'description' => 'Administrative access to organization settings',
                'is_default' => false,
            ],
            [
                'identifier' => 'organization:hierarchy',
                'name' => 'Organization Hierarchy',
                'description' => 'Access to full organization hierarchy',
                'is_default' => false,
            ],
            [
                'identifier' => 'tenant:read',
                'name' => 'Tenant Read',
                'description' => 'Read access to tenant-specific data',
                'is_default' => false,
            ],
            [
                'identifier' => 'tenant:admin',
                'name' => 'Tenant Admin',
                'description' => 'Administrative access within tenant scope',
                'is_default' => false,
            ],
            [
                'identifier' => 'user:read',
                'name' => 'User Read',
                'description' => 'Read user profile and basic information',
                'is_default' => false,
            ],
            [
                'identifier' => 'user:write',
                'name' => 'User Write',
                'description' => 'Update user profile and settings',
                'is_default' => false,
            ],
            [
                'identifier' => 'analytics:read',
                'name' => 'Analytics Read',
                'description' => 'Access to analytics and reporting data',
                'is_default' => false,
            ],
            [
                'identifier' => 'webhooks:manage',
                'name' => 'Webhooks Management',
                'description' => 'Create and manage webhook subscriptions',
                'is_default' => false,
            ],
            [
                'identifier' => 'api:full_access',
                'name' => 'Full API Access',
                'description' => 'Complete API access for trusted applications',
                'is_default' => false,
            ],
            [
                'identifier' => 'financial:read',
                'name' => 'Financial Data Read',
                'description' => 'Access to financial and billing information',
                'is_default' => false,
            ],
            [
                'identifier' => 'security:audit',
                'name' => 'Security Audit',
                'description' => 'Access to security logs and audit trails',
                'is_default' => false,
            ],
            [
                'identifier' => 'integration:third_party',
                'name' => 'Third-party Integrations',
                'description' => 'Access for third-party system integrations',
                'is_default' => false,
            ],
            [
                'identifier' => 'mobile:access',
                'name' => 'Mobile Application Access',
                'description' => 'Specialized access for mobile applications',
                'is_default' => false,
            ],
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
