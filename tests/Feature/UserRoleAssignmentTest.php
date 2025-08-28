<?php

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;

beforeEach(function () {
    // Clear any existing tenant context first
    if (class_exists(\App\Services\TenantService::class)) {
        \App\Services\TenantService::clearTenant();
    }

    // Create a test organization to use as team context FIRST
    $this->testOrganization = \App\Models\Organization::factory()->create([
        'name' => 'Test Organization',
        'organization_code' => 'TEST-ORG',
        'organization_type' => 'holding_company',
    ]);

    // Now create users - this ensures any activity logging has a valid org context
    $this->adminUser = User::factory()->create();
    $this->testUser = User::factory()->create();

    // Authenticate as admin user first so that created_by is properly set
    $this->actingAs($this->adminUser);

    // Associate admin user with the test organization
    \App\Models\OrganizationMembership::create([
        'user_id' => $this->adminUser->id,
        'organization_id' => $this->testOrganization->id,
        'role' => 'admin',
        'status' => 'active',
        'start_date' => now(),
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);

    // Create user management permissions
    $this->permissions = [
        Permission::create(['name' => 'view users', 'guard_name' => 'web']),
        Permission::create(['name' => 'edit users', 'guard_name' => 'web']),
        Permission::create(['name' => 'delete users', 'guard_name' => 'web']),
    ];

    // Set team context using the test organization ID and give admin user permissions
    setPermissionsTeamId($this->testOrganization->id);
    $this->adminUser->givePermissionTo($this->permissions);

    // Create test roles
    $this->roles = [
        Role::create(['name' => 'manager', 'guard_name' => 'web']),
        Role::create(['name' => 'employee', 'guard_name' => 'web']),
        Role::create(['name' => 'admin', 'guard_name' => 'web']),
    ];
});

afterEach(function () {
    // Clear tenant context to avoid interference with other tests
    if (class_exists(\App\Services\TenantService::class)) {
        \App\Services\TenantService::clearTenant();
        
        // Clear any static state
        $reflection = new \ReflectionClass(\App\Services\TenantService::class);
        if ($reflection->hasProperty('currentTenant')) {
            $currentTenantProperty = $reflection->getProperty('currentTenant');
            $currentTenantProperty->setValue(null);
        }
        
        if ($reflection->hasProperty('currentUser')) {
            $currentUserProperty = $reflection->getProperty('currentUser');
            $currentUserProperty->setValue(null);
        }
    }
    
    // Clear permissions team context
    setPermissionsTeamId(null);
});

test('can view users index page', function () {
    $response = $this->get(route('users.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Users/Index')
        ->has('users.data')
    );
});

test('can view user details with roles', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $this->testUser->assignRole('manager');

    $response = $this->get(route('users.show', $this->testUser));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Users/Show')
        ->where('user.name', $this->testUser->name)
        ->has('user.roles', 1)
        ->where('user.roles.0.name', 'manager')
    );
});

test('can assign roles to user', function () {
    setPermissionsTeamId($this->testOrganization->id);
    
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['manager', 'employee'],
    ]);

    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');

    $this->testUser->refresh();
    setPermissionsTeamId($this->testOrganization->id);
    expect($this->testUser->roles)->toHaveCount(2);
    expect($this->testUser->hasRole('manager'))->toBeTrue();
    expect($this->testUser->hasRole('employee'))->toBeTrue();
});

test('can remove roles from user', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $this->testUser->assignRole(['manager', 'employee']);

    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['manager'], // Remove employee role
    ]);

    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');

    $this->testUser->refresh();
    setPermissionsTeamId($this->testOrganization->id);
    expect($this->testUser->roles)->toHaveCount(1);
    expect($this->testUser->hasRole('manager'))->toBeTrue();
    expect($this->testUser->hasRole('employee'))->toBeFalse();
});

test('can remove all roles from user', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $this->testUser->assignRole(['manager', 'employee']);

    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => [], // Remove all roles
    ]);

    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');

    $this->testUser->refresh();
    setPermissionsTeamId($this->testOrganization->id);
    expect($this->testUser->roles)->toHaveCount(0);
});

test('role assignment validates role existence', function () {
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['nonexistent-role'],
    ]);

    $response->assertSessionHasErrors(['roles.0']);
});

test('can delete user and remove all roles', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $this->testUser->assignRole('manager');
    $userId = $this->testUser->id;

    $response = $this->delete(route('users.destroy', $this->testUser));

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success');

    expect(User::find($userId))->toBeNull();
});

test('can search users', function () {
    $response = $this->get(route('users.index', ['search' => $this->testUser->email]));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('users.data', 1)
        ->where('users.data.0.email', $this->testUser->email)
    );
});

test('users show effective permissions from roles', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $permission = Permission::create(['name' => 'test:permission', 'guard_name' => 'web']);
    $this->roles[0]->givePermissionTo($permission); // manager role gets permission
    $this->testUser->assignRole('manager');

    $response = $this->get(route('users.show', $this->testUser));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('user.roles.0.permissions', 1)
        ->where('user.roles.0.permissions.0.name', 'test:permission')
    );
});

test('unauthorized user cannot manage user roles', function () {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('users.index'));

    $response->assertStatus(403);
});

test('users index shows role counts', function () {
    setPermissionsTeamId($this->testOrganization->id);
    $this->testUser->assignRole(['manager', 'employee']);

    $response = $this->get(route('users.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->whereContains('users.data', fn ($user) => $user['id'] === $this->testUser->id && $user['roles_count'] === 2
    )
    );
});
