<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // OAuth Scope mappings: Old -> New
        $oauthScopeMappings = [
            'organization:read' => 'https://api.yourcompany.com/auth/organization.readonly',
            'organization:write' => 'https://api.yourcompany.com/auth/organization',
            'organization:members' => 'https://api.yourcompany.com/auth/organization.members',
            'organization:admin' => 'https://api.yourcompany.com/auth/organization.admin',
            'organization:hierarchy' => 'https://api.yourcompany.com/auth/organization.admin',
            'user:read' => 'https://api.yourcompany.com/auth/userinfo.profile',
            'user:write' => 'https://api.yourcompany.com/auth/user.modify',
            'tenant:read' => 'https://api.yourcompany.com/auth/organization.readonly',
            'tenant:admin' => 'https://api.yourcompany.com/auth/organization.admin',
            'analytics:read' => 'https://api.yourcompany.com/auth/analytics.readonly',
            'webhooks:manage' => 'https://api.yourcompany.com/auth/webhooks',
            'api:full_access' => 'https://api.yourcompany.com/auth/platform.full',
            'financial:read' => 'https://api.yourcompany.com/auth/finance.readonly',
            'security:audit' => 'https://api.yourcompany.com/auth/audit.readonly',
            'integration:third_party' => 'https://api.yourcompany.com/auth/integrations',
            'mobile:access' => 'https://api.yourcompany.com/auth/mobile',
        ];

        // Permission mappings: Old -> New
        $permissionMappings = [
            // User permissions
            'user.view' => 'user:read',
            'user.create' => 'user:write',
            'user.edit' => 'user:write',
            'user.delete' => 'user:delete',
            'user.impersonate' => 'user:impersonate',
            
            // Organization permissions
            'organization.view' => 'org:read',
            'organization.create' => 'org:write',
            'organization.edit' => 'org:write',
            'organization.delete' => 'org:delete',
            'organization.hierarchy.view' => 'org:read',
            'organization.hierarchy.manage' => 'org:admin',
            
            // Membership permissions
            'membership.view' => 'org_member:read',
            'membership.create' => 'org_member:write',
            'membership.edit' => 'org_member:write',
            'membership.delete' => 'org_member:delete',
            'membership.activate' => 'org_member:write',
            'membership.deactivate' => 'org_member:write',
            'membership.terminate' => 'org_member:delete',
            
            // Unit permissions
            'unit.view' => 'org_unit:read',
            'unit.create' => 'org_unit:write',
            'unit.edit' => 'org_unit:write',
            'unit.delete' => 'org_unit:delete',
            
            // Position permissions
            'position.view' => 'org_position:read',
            'position.create' => 'org_position:write',
            'position.edit' => 'org_position:write',
            'position.delete' => 'org_position:delete',
            'position.level.view' => 'org_position:read',
            'position.level.create' => 'org_position:write',
            'position.level.edit' => 'org_position:write',
            'position.level.delete' => 'org_position:delete',
            
            // Legacy position levels
            'view organization position levels' => 'org_position:read',
            'create organization position levels' => 'org_position:write',
            'edit organization position levels' => 'org_position:write',
            'delete organization position levels' => 'org_position:delete',
            
            // OAuth permissions
            'oauth.client.view' => 'oauth_app:read',
            'oauth.client.create' => 'oauth_app:write',
            'oauth.client.edit' => 'oauth_app:write',
            'oauth.client.delete' => 'oauth_app:delete',
            'oauth.client.regenerate' => 'oauth_app:admin',
            'oauth.analytics.view' => 'oauth_token:read',
            'oauth.tokens.view' => 'oauth_token:read',
            'oauth.tokens.revoke' => 'oauth_token:delete',
            
            // Activity log permissions
            'activity.view.own' => 'audit_log:read',
            'activity.view.organization' => 'audit_log:read',
            'activity.view.all' => 'audit_log:admin',
            'activity.delete' => 'audit_log:delete',
            'activity.export' => 'audit_log:admin',
            'activity.purge' => 'audit_log:admin',
            
            // Role permissions
            'role.view' => 'role:read',
            'role.create' => 'role:write',
            'role.edit' => 'role:write',
            'role.delete' => 'role:delete',
            'view roles' => 'role:read',
            'create roles' => 'role:write',
            'edit roles' => 'role:write',
            'delete roles' => 'role:delete',
            'manage roles' => 'role:admin',
            'permission.assign' => 'permission:write',
            'permission.revoke' => 'permission:write',
            'view permissions' => 'permission:read',
            'create permissions' => 'permission:write',
            'edit permissions' => 'permission:write',
            'delete permissions' => 'permission:delete',
            'manage permissions' => 'permission:admin',
            'assign permissions' => 'permission:write',
            'revoke permissions' => 'permission:write',
            
            // System permissions
            'system.settings.view' => 'system:read',
            'system.settings.edit' => 'system:write',
            'system.maintenance' => 'system:admin',
            'system.logs.view' => 'system:read',
            
            // Profile permissions
            'profile.view' => 'profile:read',
            'profile.edit' => 'profile:write',
            'security.mfa.manage' => 'security:write',
            'security.password.change' => 'security:write',
            'security.sessions.manage' => 'security:write',
        ];

        $this->info('Starting migration to new naming conventions...');

        // Update OAuth scopes in oauth_scopes table
        if (Schema::hasTable('oauth_scopes')) {
            foreach ($oauthScopeMappings as $oldScope => $newScope) {
                DB::table('oauth_scopes')
                    ->where('identifier', $oldScope)
                    ->update(['identifier' => $newScope]);
                $this->info("Updated OAuth scope: $oldScope -> $newScope");
            }
        }

        // Update OAuth client allowed_scopes
        if (Schema::hasTable('oauth_clients')) {
            $clients = DB::table('oauth_clients')->get();
            foreach ($clients as $client) {
                if ($client->allowed_scopes) {
                    $scopes = json_decode($client->allowed_scopes, true);
                    $updatedScopes = [];
                    
                    foreach ($scopes as $scope) {
                        $updatedScopes[] = $oauthScopeMappings[$scope] ?? $scope;
                    }
                    
                    DB::table('oauth_clients')
                        ->where('id', $client->id)
                        ->update(['allowed_scopes' => json_encode($updatedScopes)]);
                    $this->info("Updated client scopes for client ID: {$client->id}");
                }
            }
        }

        // Update permissions table
        if (Schema::hasTable('permissions')) {
            foreach ($permissionMappings as $oldPermission => $newPermission) {
                $exists = DB::table('permissions')
                    ->where('name', $oldPermission)
                    ->exists();
                    
                if ($exists) {
                    // Check if new permission already exists
                    $newExists = DB::table('permissions')
                        ->where('name', $newPermission)
                        ->exists();
                        
                    if (!$newExists) {
                        // Update old permission to new name
                        DB::table('permissions')
                            ->where('name', $oldPermission)
                            ->update(['name' => $newPermission]);
                        $this->info("Updated permission: $oldPermission -> $newPermission");
                    } else {
                        // New permission exists, need to migrate relationships then delete old
                        $oldPermissionId = DB::table('permissions')
                            ->where('name', $oldPermission)
                            ->value('id');
                        $newPermissionId = DB::table('permissions')
                            ->where('name', $newPermission)
                            ->value('id');
                            
                        // Migrate role_has_permissions
                        if (Schema::hasTable('role_has_permissions')) {
                            DB::table('role_has_permissions')
                                ->where('permission_id', $oldPermissionId)
                                ->whereNotExists(function ($query) use ($newPermissionId) {
                                    $query->select(DB::raw(1))
                                        ->from('role_has_permissions as rhp2')
                                        ->whereColumn('rhp2.role_id', 'role_has_permissions.role_id')
                                        ->where('rhp2.permission_id', $newPermissionId);
                                })
                                ->update(['permission_id' => $newPermissionId]);
                        }
                        
                        // Migrate model_has_permissions
                        if (Schema::hasTable('model_has_permissions')) {
                            DB::table('model_has_permissions')
                                ->where('permission_id', $oldPermissionId)
                                ->whereNotExists(function ($query) use ($newPermissionId) {
                                    $query->select(DB::raw(1))
                                        ->from('model_has_permissions as mhp2')
                                        ->whereColumn('mhp2.model_type', 'model_has_permissions.model_type')
                                        ->whereColumn('mhp2.model_id', 'model_has_permissions.model_id')
                                        ->where('mhp2.permission_id', $newPermissionId);
                                })
                                ->update(['permission_id' => $newPermissionId]);
                        }
                        
                        // Delete old permission
                        DB::table('permissions')->where('id', $oldPermissionId)->delete();
                        $this->info("Migrated and removed duplicate permission: $oldPermission -> $newPermission");
                    }
                }
            }
        }

        // Update OAuth access tokens scopes (if needed)
        if (Schema::hasTable('oauth_access_tokens')) {
            $tokens = DB::table('oauth_access_tokens')->whereNotNull('scopes')->get();
            foreach ($tokens as $token) {
                $scopes = json_decode($token->scopes, true);
                if (is_array($scopes)) {
                    $updatedScopes = [];
                    foreach ($scopes as $scope) {
                        $updatedScopes[] = $oauthScopeMappings[$scope] ?? $scope;
                    }
                    
                    DB::table('oauth_access_tokens')
                        ->where('id', $token->id)
                        ->update(['scopes' => json_encode($updatedScopes)]);
                }
            }
            $this->info('Updated OAuth access token scopes');
        }

        $this->info('Migration to new naming conventions completed successfully!');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->info('Reverting to old naming conventions is not supported.');
        $this->info('Please restore from backup if you need to rollback.');
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "[INFO] $message\n";
        }
    }
};