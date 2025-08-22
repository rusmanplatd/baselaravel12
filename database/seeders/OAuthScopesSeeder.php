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
            // Legacy read/write scopes removed - use organization/tenant scopes
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
        ];

        foreach ($scopes as $scope) {
            \App\Models\OAuthScope::updateOrCreate(
                ['identifier' => $scope['identifier']],
                $scope
            );
        }

        $this->command->info('OAuth scopes seeded successfully.');
        $this->command->info('Added organization and tenant-specific scopes.');
    }
}
