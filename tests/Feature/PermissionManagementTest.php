<?php

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create permission management permissions
    $this->permissions = [
        Permission::create(['name' => 'view permissions', 'guard_name' => 'web']),
        Permission::create(['name' => 'create permissions', 'guard_name' => 'web']),
        Permission::create(['name' => 'edit permissions', 'guard_name' => 'web']),
        Permission::create(['name' => 'delete permissions', 'guard_name' => 'web']),
        Permission::create(['name' => 'manage permissions', 'guard_name' => 'web']),
    ];

    // Give user all permission permissions
    $this->user->givePermissionTo($this->permissions);

    $this->actingAs($this->user);
});

test('can view permissions index page', function () {
    $response = $this->get(route('permissions.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Permissions/Index')
        ->has('permissions.data')
    );
});

test('can view permission details', function () {
    $permission = Permission::factory()->create();
    $role = Role::factory()->create();
    $role->givePermissionTo($permission);

    $response = $this->get(route('permissions.show', $permission));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Permissions/Show')
        ->where('permission.name', $permission->name)
        ->has('permission.roles', 1)
    );
});

test('can create permission', function () {
    $response = $this->post(route('permissions.store'), [
        'name' => 'test:permission',
        'guard_name' => 'web',
    ]);

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('success');

    $permission = Permission::where('name', 'test:permission')->first();
    expect($permission)->not->toBeNull();
    expect($permission->guard_name)->toBe('web');
});

test('can update permission', function () {
    $permission = Permission::factory()->create(['name' => 'old:permission']);

    $response = $this->put(route('permissions.update', $permission), [
        'name' => 'new:permission',
        'guard_name' => 'web',
    ]);

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('success');

    $permission->refresh();
    expect($permission->name)->toBe('new:permission');
});

test('can delete permission without roles', function () {
    $permission = Permission::factory()->create();

    $response = $this->delete(route('permissions.destroy', $permission));

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('success');

    expect(Permission::find($permission->id))->toBeNull();
});

test('cannot delete permission assigned to roles', function () {
    $permission = Permission::factory()->create();
    $role = Role::factory()->create();
    $role->givePermissionTo($permission);

    $response = $this->delete(route('permissions.destroy', $permission));

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('error');

    expect(Permission::find($permission->id))->not->toBeNull();
});

test('permission name is required', function () {
    $response = $this->post(route('permissions.store'), [
        'name' => '',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('permission name must be unique', function () {
    Permission::factory()->create(['name' => 'duplicate:permission']);

    $response = $this->post(route('permissions.store'), [
        'name' => 'duplicate:permission',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('permission name must follow valid format', function () {
    $response = $this->post(route('permissions.store'), [
        'name' => 'Invalid Permission Name!@#',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('can search permissions', function () {
    Permission::create(['name' => 'organization:read', 'guard_name' => 'web']);
    Permission::create(['name' => 'user:read', 'guard_name' => 'web']);
    Permission::create(['name' => 'organization:write', 'guard_name' => 'web']);

    $response = $this->get(route('permissions.index', ['search' => 'organization']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('permissions.data', 2)
    );
});

test('can filter permissions by guard', function () {
    Permission::create(['name' => 'web:permission', 'guard_name' => 'web']);
    Permission::create(['name' => 'api:permission', 'guard_name' => 'api']);

    $response = $this->get(route('permissions.index', ['guard_name' => 'api']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('permissions.data', 1)
        ->where('permissions.data.0.guard_name', 'api')
    );
});

test('unauthorized user cannot access permission management', function () {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('permissions.index'));

    $response->assertStatus(403);
});
