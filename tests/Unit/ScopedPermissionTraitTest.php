<?php

namespace Tests\Unit;

use App\Models\Auth\Permission;
use App\Models\Auth\PermissionScope;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopedPermissionTraitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);

        // Set up permission scope
        PermissionScope::create([
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'parent_scope_type' => 'organization',
            'parent_scope_id' => $this->organization->id,
            'inherits_permissions' => true,
            'scope_path' => [
                ['type' => 'organization', 'id' => $this->organization->id],
                ['type' => 'project', 'id' => $this->project->id]
            ]
        ]);
    }

    public function test_has_permission_in_scope(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        $this->user->permissions()->attach($permission->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );
    }

    public function test_has_role_in_scope(): void
    {
        $role = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($role->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $this->assertTrue(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );
    }

    public function test_get_permissions_for_scope(): void
    {
        // Create direct permission
        $directPermission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        $this->user->permissions()->attach($directPermission->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        // Create role with permission
        $role = Role::create([
            'name' => 'project_viewer',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $rolePermission = Permission::create([
            'name' => 'view_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        $role->permissions()->attach($rolePermission->id);
        $this->user->roles()->attach($role->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $permissions = $this->user->getPermissionsForScope('project', $this->project->id);
        $permissionNames = $permissions->pluck('name');

        $this->assertCount(2, $permissions);
        $this->assertContains('edit_project', $permissionNames);
        $this->assertContains('view_project', $permissionNames);
    }

    public function test_get_roles_for_scope(): void
    {
        $role1 = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $role2 = Role::create([
            'name' => 'project_viewer',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($role1->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($role2->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $roles = $this->user->getRolesForScope('project', $this->project->id);
        $roleNames = $roles->pluck('name');

        $this->assertCount(2, $roles);
        $this->assertContains('project_admin', $roleNames);
        $this->assertContains('project_viewer', $roleNames);
    }

    public function test_give_permission_in_scope(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        $this->user->givePermissionInScope('edit_project', 'project', $this->project->id);

        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );
    }

    public function test_revoke_permission_in_scope(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        $this->user->givePermissionInScope('edit_project', 'project', $this->project->id);
        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );

        $this->user->revokePermissionInScope('edit_project', 'project', $this->project->id);
        $this->assertFalse(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );
    }

    public function test_assign_role_in_scope(): void
    {
        $role = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
        ]);

        $this->user->assignRoleInScope('project_admin', 'project', $this->project->id);

        $this->assertTrue(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );
    }

    public function test_remove_role_in_scope(): void
    {
        $role = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
        ]);

        $this->user->assignRoleInScope('project_admin', 'project', $this->project->id);
        $this->assertTrue(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );

        $this->user->removeRoleInScope('project_admin', 'project', $this->project->id);
        $this->assertFalse(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );
    }

    public function test_get_scopes_with_permission(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        // Grant permission in project scope
        $this->user->permissions()->attach($permission->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        // Grant permission in organization scope
        $this->user->permissions()->attach($permission->id, [
            'scope_type' => 'organization',
            'scope_id' => $this->organization->id,
            'team_id' => $this->organization->id,
        ]);

        $scopes = $this->user->getScopesWithPermission('edit_project');

        $this->assertCount(2, $scopes);
        
        $scopeIdentifiers = $scopes->map(function ($scope) {
            return $scope['type'] . ':' . $scope['id'];
        });

        $this->assertContains("project:{$this->project->id}", $scopeIdentifiers);
        $this->assertContains("organization:{$this->organization->id}", $scopeIdentifiers);
    }

    public function test_get_all_scoped_permissions(): void
    {
        // Direct permission
        $directPermission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        $this->user->permissions()->attach($directPermission->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        // Role permission
        $role = Role::create([
            'name' => 'project_viewer',
            'guard_name' => 'web',
        ]);

        $rolePermission = Permission::create([
            'name' => 'view_project',
            'guard_name' => 'web',
        ]);

        $role->permissions()->attach($rolePermission->id);
        $this->user->roles()->attach($role->id, [
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
            'team_id' => $this->organization->id,
        ]);

        $allPermissions = $this->user->getAllScopedPermissions();

        $this->assertCount(2, $allPermissions);
        
        $directPerms = $allPermissions->where('source', 'direct');
        $rolePerms = $allPermissions->where('source', 'role');
        
        $this->assertCount(1, $directPerms);
        $this->assertCount(1, $rolePerms);
        
        $this->assertEquals('edit_project', $directPerms->first()['permission']);
        $this->assertEquals('view_project', $rolePerms->first()['permission']);
    }

    public function test_has_any_permission_in_scope(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        $this->user->givePermissionInScope('edit_project', 'project', $this->project->id);

        $permissions = ['view_project', 'edit_project', 'delete_project'];
        
        $this->assertTrue(
            $this->user->hasAnyPermissionInScope($permissions, 'project', $this->project->id)
        );

        $this->assertFalse(
            $this->user->hasAnyPermissionInScope(['view_project', 'delete_project'], 'project', $this->project->id)
        );
    }

    public function test_has_all_permissions_in_scope(): void
    {
        $permissions = ['edit_project', 'view_project'];
        
        foreach ($permissions as $permissionName) {
            $permission = Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $this->user->givePermissionInScope($permissionName, 'project', $this->project->id);
        }

        $this->assertTrue(
            $this->user->hasAllPermissionsInScope($permissions, 'project', $this->project->id)
        );

        $this->assertFalse(
            $this->user->hasAllPermissionsInScope([...$permissions, 'delete_project'], 'project', $this->project->id)
        );
    }

    public function test_scope_query_methods(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
        ]);

        $this->user->givePermissionInScope('edit_project', 'project', $this->project->id);

        // Create another user without permission
        $otherUser = User::factory()->create();

        $usersWithPermission = User::withPermissionInScope('edit_project', 'project', $this->project->id)->get();
        
        $this->assertCount(1, $usersWithPermission);
        $this->assertEquals($this->user->id, $usersWithPermission->first()->id);
    }
}