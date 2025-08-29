<?php

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();

    // Create or get permission management permissions (avoid duplicates)
    $permissionNames = ['view permissions', 'create permissions', 'edit permissions', 'delete permissions', 'manage permissions'];
    $this->permissions = [];

    foreach ($permissionNames as $permissionName) {
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ], [
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $this->permissions[] = $permission;
    }

    // Give user all permission permissions with team context
    setPermissionsTeamId($this->organization->id);
    $this->user->givePermissionTo($this->permissions);

    $this->actingAs($this->user, 'api');
});

test('can view permissions index page', function () {
    setPermissionsTeamId($this->organization->id);
    $response = $this->get(route('permissions.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Permissions/Index')
        ->has('permissions.data')
    );
});

test('can view permission details', function () {
    setPermissionsTeamId($this->organization->id);
    $permission = Permission::factory()->create(['name' => 'test:view-details-permission']);
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
    setPermissionsTeamId($this->organization->id);

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
    setPermissionsTeamId($this->organization->id);
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
    setPermissionsTeamId($this->organization->id);
    $permission = Permission::factory()->create(['name' => 'test:can-delete-permission']);

    $response = $this->delete(route('permissions.destroy', $permission));

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('success');

    expect(Permission::find($permission->id))->toBeNull();
});

test('cannot delete permission assigned to roles', function () {
    setPermissionsTeamId($this->organization->id);
    $permission = Permission::factory()->create(['name' => 'test:cannot-delete-permission']);
    $role = Role::factory()->create();
    $role->givePermissionTo($permission);

    $response = $this->delete(route('permissions.destroy', $permission));

    $response->assertRedirect(route('permissions.index'));
    $response->assertSessionHas('error');

    expect(Permission::find($permission->id))->not->toBeNull();
});

test('permission name is required', function () {
    setPermissionsTeamId($this->organization->id);

    $response = $this->post(route('permissions.store'), [
        'name' => '',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('permission name must be unique', function () {
    setPermissionsTeamId($this->organization->id);
    Permission::factory()->create(['name' => 'duplicate:permission']);

    $response = $this->post(route('permissions.store'), [
        'name' => 'duplicate:permission',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('permission name must follow valid format', function () {
    setPermissionsTeamId($this->organization->id);

    $response = $this->post(route('permissions.store'), [
        'name' => 'Invalid Permission Name!@#',
        'guard_name' => 'web',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('can search permissions', function () {
    setPermissionsTeamId($this->organization->id);

    Permission::create(['name' => 'org:read', 'guard_name' => 'web']);
    Permission::create(['name' => 'user:read', 'guard_name' => 'web']);
    Permission::create(['name' => 'org:write', 'guard_name' => 'web']);

    $response = $this->get(route('permissions.index', ['search' => 'org']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('permissions.data', 2)
    );
});

test('can filter permissions by guard', function () {
    setPermissionsTeamId($this->organization->id);

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

    $this->actingAs($unauthorizedUser, 'api');

    $response = $this->get(route('permissions.index'));

    $response->assertStatus(403);
});
