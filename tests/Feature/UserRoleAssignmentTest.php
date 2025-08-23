<?php

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;

beforeEach(function () {
    $this->adminUser = User::factory()->create();
    $this->testUser = User::factory()->create();
    
    // Create user management permissions
    $this->permissions = [
        Permission::create(['name' => 'view users', 'guard_name' => 'web']),
        Permission::create(['name' => 'edit users', 'guard_name' => 'web']),
        Permission::create(['name' => 'delete users', 'guard_name' => 'web']),
    ];
    
    // Give admin user permissions
    $this->adminUser->givePermissionTo($this->permissions);
    
    // Create test roles
    $this->roles = [
        Role::create(['name' => 'manager', 'guard_name' => 'web']),
        Role::create(['name' => 'employee', 'guard_name' => 'web']),
        Role::create(['name' => 'admin', 'guard_name' => 'web']),
    ];
    
    $this->actingAs($this->adminUser);
});

test('can view users index page', function () {
    $response = $this->get(route('users.index'));
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Users/Index')
             ->has('users.data')
    );
});

test('can view user details with roles', function () {
    $this->testUser->assignRole('manager');
    
    $response = $this->get(route('users.show', $this->testUser));
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Users/Show')
             ->where('user.name', $this->testUser->name)
             ->has('user.roles', 1)
             ->where('user.roles.0.name', 'manager')
    );
});

test('can assign roles to user', function () {
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['manager', 'employee'],
    ]);
    
    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');
    
    $this->testUser->refresh();
    expect($this->testUser->roles)->toHaveCount(2);
    expect($this->testUser->hasRole('manager'))->toBeTrue();
    expect($this->testUser->hasRole('employee'))->toBeTrue();
});

test('can remove roles from user', function () {
    $this->testUser->assignRole(['manager', 'employee']);
    
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['manager'], // Remove employee role
    ]);
    
    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');
    
    $this->testUser->refresh();
    expect($this->testUser->roles)->toHaveCount(1);
    expect($this->testUser->hasRole('manager'))->toBeTrue();
    expect($this->testUser->hasRole('employee'))->toBeFalse();
});

test('can remove all roles from user', function () {
    $this->testUser->assignRole(['manager', 'employee']);
    
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => [], // Remove all roles
    ]);
    
    $response->assertRedirect(route('users.show', $this->testUser));
    $response->assertSessionHas('success');
    
    $this->testUser->refresh();
    expect($this->testUser->roles)->toHaveCount(0);
});

test('role assignment validates role existence', function () {
    $response = $this->post(route('users.assignRoles', $this->testUser), [
        'roles' => ['nonexistent-role'],
    ]);
    
    $response->assertSessionHasErrors(['roles.0']);
});

test('can delete user and remove all roles', function () {
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
    $response->assertInertia(fn ($page) =>
        $page->has('users.data', 1)
             ->where('users.data.0.email', $this->testUser->email)
    );
});

test('users show effective permissions from roles', function () {
    $permission = Permission::create(['name' => 'test:permission', 'guard_name' => 'web']);
    $this->roles[0]->givePermissionTo($permission); // manager role gets permission
    $this->testUser->assignRole('manager');
    
    $response = $this->get(route('users.show', $this->testUser));
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->has('user.roles.0.permissions', 1)
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
    $this->testUser->assignRole(['manager', 'employee']);
    
    $response = $this->get(route('users.index'));
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->whereContains('users.data', fn ($user) => 
            $user['id'] === $this->testUser->id && $user['roles_count'] === 2
        )
    );
});