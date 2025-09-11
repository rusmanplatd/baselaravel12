<?php

namespace Tests\Feature;

use App\Facades\ScopedPermission;
use App\Models\Auth\Permission;
use App\Models\Auth\PermissionScope;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopedPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Organization $organization;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->organization = Organization::factory()->create();
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id
        ]);

        // Set up permission scopes
        ScopedPermission::setupScopeHierarchy($this->organization);
        ScopedPermission::setupScopeHierarchy(
            $this->project,
            'organization',
            $this->organization->id
        );
    }

    public function test_can_create_global_permissions(): void
    {
        $permission = Permission::create([
            'name' => 'manage_system',
            'guard_name' => 'web',
            'is_global' => true,
        ]);

        $this->assertTrue($permission->isGlobal());
        $this->assertFalse($permission->isScoped());
        $this->assertNull($permission->getScopeIdentifier());
    }

    public function test_can_create_scoped_permissions(): void
    {
        $permission = Permission::create([
            'name' => 'edit_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        $this->assertFalse($permission->isGlobal());
        $this->assertTrue($permission->isScoped());
        $this->assertEquals("project:{$this->project->id}", $permission->getScopeIdentifier());
    }

    public function test_can_assign_permission_in_scope(): void
    {
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'edit_project',
            'project',
            $this->project->id
        );

        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );

        $this->assertFalse(
            $this->otherUser->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );
    }

    public function test_can_assign_role_in_scope(): void
    {
        // Create scoped role
        $role = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        // Give role some permissions
        $permission = Permission::create([
            'name' => 'manage_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);
        $role->givePermissionTo($permission);

        // Assign role to user in scope
        ScopedPermission::assignRoleToUser(
            $this->user,
            'project_admin',
            'project',
            $this->project->id
        );

        $this->assertTrue(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );

        $this->assertTrue(
            $this->user->hasPermissionInScope('manage_project', 'project', $this->project->id)
        );
    }

    public function test_permission_inheritance_works(): void
    {
        // Give user organization-level permission
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'view_projects',
            'organization',
            $this->organization->id
        );

        // User should have permission in project scope through inheritance
        $this->assertTrue(
            $this->user->hasPermissionInScope('view_projects', 'project', $this->project->id)
        );
    }

    public function test_can_get_effective_permissions(): void
    {
        // Grant direct permission
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'edit_project',
            'project',
            $this->project->id
        );

        // Grant permission through role
        $role = Role::create([
            'name' => 'project_viewer',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        $permission = Permission::create([
            'name' => 'view_project',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);
        $role->givePermissionTo($permission);

        ScopedPermission::assignRoleToUser($this->user, 'project_viewer', 'project', $this->project->id);

        // Get effective permissions
        $effectivePermissions = ScopedPermission::getUserEffectivePermissions(
            $this->user,
            'project',
            $this->project->id
        );

        $permissionNames = $effectivePermissions->pluck('name');
        $this->assertContains('edit_project', $permissionNames);
        $this->assertContains('view_project', $permissionNames);
    }

    public function test_can_revoke_permission_in_scope(): void
    {
        // Grant permission
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'edit_project',
            'project',
            $this->project->id
        );

        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );

        // Revoke permission
        ScopedPermission::revokePermissionFromUser(
            $this->user,
            'edit_project',
            'project',
            $this->project->id
        );

        $this->assertFalse(
            $this->user->hasPermissionInScope('edit_project', 'project', $this->project->id)
        );
    }

    public function test_can_remove_role_in_scope(): void
    {
        // Create and assign role
        $role = Role::create([
            'name' => 'project_admin',
            'guard_name' => 'web',
            'scope_type' => 'project',
            'scope_id' => $this->project->id,
        ]);

        ScopedPermission::assignRoleToUser($this->user, 'project_admin', 'project', $this->project->id);

        $this->assertTrue(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );

        // Remove role
        ScopedPermission::removeRoleFromUser($this->user, 'project_admin', 'project', $this->project->id);

        $this->assertFalse(
            $this->user->hasRoleInScope('project_admin', 'project', $this->project->id)
        );
    }

    public function test_global_permissions_apply_everywhere(): void
    {
        $globalPermission = Permission::create([
            'name' => 'system_admin',
            'guard_name' => 'web',
            'is_global' => true,
        ]);

        $this->user->givePermissionTo($globalPermission);

        // Global permission should apply to any scope
        $this->assertTrue(
            $this->user->hasPermissionInScope('system_admin', 'project', $this->project->id)
        );
        $this->assertTrue(
            $this->user->hasPermissionInScope('system_admin', 'organization', $this->organization->id)
        );
    }

    public function test_can_get_users_with_permission_in_scope(): void
    {
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'view_project',
            'project',
            $this->project->id
        );

        $users = ScopedPermission::getUsersWithPermissionInScope(
            'view_project',
            'project',
            $this->project->id
        );

        $this->assertCount(1, $users);
        $this->assertEquals($this->user->id, $users->first()->id);
    }

    public function test_can_bulk_assign_permissions(): void
    {
        $users = collect([$this->user, $this->otherUser]);
        $permissions = ['view_project', 'edit_project'];

        ScopedPermission::bulkAssignPermissions(
            $users,
            $permissions,
            'project',
            $this->project->id
        );

        foreach ($users as $user) {
            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $user->hasPermissionInScope($permission, 'project', $this->project->id)
                );
            }
        }
    }

    public function test_can_clone_permissions_between_scopes(): void
    {
        // Set up source project with permissions
        $sourceProject = Project::factory()->create(['organization_id' => $this->organization->id]);
        $targetProject = Project::factory()->create(['organization_id' => $this->organization->id]);

        // Grant permissions in source project
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'edit_project',
            'project',
            $sourceProject->id
        );

        // Clone permissions
        ScopedPermission::clonePermissions(
            'project',
            $sourceProject->id,
            'project',
            $targetProject->id
        );

        // User should now have permissions in target project
        $this->assertTrue(
            $this->user->hasPermissionInScope('edit_project', 'project', $targetProject->id)
        );
    }

    public function test_permission_scope_hierarchy(): void
    {
        $inheritanceTree = ScopedPermission::getPermissionInheritanceTree(
            'project',
            $this->project->id
        );

        $this->assertIsArray($inheritanceTree);
        // Should contain organization as parent scope
        $this->assertCount(1, $inheritanceTree);
        $this->assertEquals('organization', $inheritanceTree[0]['scope_type']);
        $this->assertEquals($this->organization->id, $inheritanceTree[0]['scope_id']);
    }

    public function test_permission_scope_model_relationships(): void
    {
        $scope = PermissionScope::where('scope_type', 'project')
            ->where('scope_id', $this->project->id)
            ->first();

        $this->assertNotNull($scope);
        $this->assertEquals('project', $scope->scope_type);
        $this->assertEquals($this->project->id, $scope->scope_id);
        $this->assertTrue($scope->inherits_permissions);
    }

    public function test_scoped_permission_middleware_integration(): void
    {
        // Create a permission and assign to user
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'view_project',
            'project',
            $this->project->id
        );

        // Test API endpoint with scoped permission middleware
        $this->actingAs($this->user)
            ->getJson("/api/projects/{$this->project->id}")
            ->assertStatus(200); // Should have access

        // Test with user who doesn't have permission
        $this->actingAs($this->otherUser)
            ->getJson("/api/projects/{$this->project->id}")
            ->assertStatus(403); // Should be forbidden
    }

    public function test_can_check_multiple_permissions_in_scope(): void
    {
        $permissions = ['view_project', 'edit_project'];
        
        // Grant only one permission
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'view_project',
            'project',
            $this->project->id
        );

        $this->assertTrue(
            $this->user->hasAnyPermissionInScope($permissions, 'project', $this->project->id)
        );

        $this->assertFalse(
            $this->user->hasAllPermissionsInScope($permissions, 'project', $this->project->id)
        );

        // Grant the second permission
        ScopedPermission::grantPermissionToUser(
            $this->user,
            'edit_project',
            'project',
            $this->project->id
        );

        $this->assertTrue(
            $this->user->hasAllPermissionsInScope($permissions, 'project', $this->project->id)
        );
    }
}