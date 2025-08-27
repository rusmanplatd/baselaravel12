<?php

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();

    // Create permissions
    $this->permissions = [
        Permission::factory()->create(['name' => 'view roles', 'guard_name' => 'web', 'created_by' => $this->user->id, 'updated_by' => $this->user->id]),
        Permission::factory()->create(['name' => 'create roles', 'guard_name' => 'web', 'created_by' => $this->user->id, 'updated_by' => $this->user->id]),
        Permission::factory()->create(['name' => 'edit roles', 'guard_name' => 'web', 'created_by' => $this->user->id, 'updated_by' => $this->user->id]),
        Permission::factory()->create(['name' => 'delete roles', 'guard_name' => 'web', 'created_by' => $this->user->id, 'updated_by' => $this->user->id]),
        Permission::factory()->create(['name' => 'manage roles', 'guard_name' => 'web', 'created_by' => $this->user->id, 'updated_by' => $this->user->id]),
    ];

    // Give user all role permissions with team context
    setPermissionsTeamId($this->organization->id);
    $this->user->givePermissionTo($this->permissions);

    $this->actingAs($this->user);
});

test('can view roles index page', function () {
    setPermissionsTeamId($this->organization->id);
    $roles = Role::factory(3)->create([
        'team_id' => $this->organization->id,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $response = $this->get(route('roles.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Roles/Index')
        ->has('roles.data', 3)
    );
});

test('can view role details', function () {
    $role = Role::factory()->create();
    $role->givePermissionTo($this->permissions[0]);

    $response = $this->get(route('roles.show', $role));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Roles/Show')
        ->where('role.name', $role->name)
        ->has('role.permissions', 1)
    );
});

test('can create role with permissions', function () {
    $response = $this->post(route('roles.store'), [
        'name' => 'Test Role',
        'permissions' => ['view roles', 'create roles'],
    ]);

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('success');

    $role = Role::where('name', 'Test Role')->first();
    expect($role)->not->toBeNull();
    expect($role->permissions)->toHaveCount(2);
});

test('can update role permissions', function () {
    $role = Role::factory()->create();
    $role->givePermissionTo([$this->permissions[0]]);

    $response = $this->put(route('roles.update', $role), [
        'name' => $role->name,
        'permissions' => ['view roles', 'create roles', 'edit roles'],
    ]);

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('success');

    $role->refresh();
    expect($role->permissions)->toHaveCount(3);
});

test('can delete role without users', function () {
    $role = Role::factory()->create();

    $response = $this->delete(route('roles.destroy', $role));

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('success');

    expect(Role::find($role->id))->toBeNull();
});

test('cannot delete role with assigned users', function () {
    $role = Role::factory()->create();
    $testUser = User::factory()->create();
    $testUser->assignRole($role);

    $response = $this->delete(route('roles.destroy', $role));

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('error');

    expect(Role::find($role->id))->not->toBeNull();
});

test('role name is required', function () {
    $response = $this->post(route('roles.store'), [
        'name' => '',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('can search roles', function () {
    Role::factory()->create(['name' => 'Admin Role']);
    Role::factory()->create(['name' => 'User Role']);
    Role::factory()->create(['name' => 'Manager Role']);

    $response = $this->get(route('roles.index', ['search' => 'Admin']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('roles.data', 1)
        ->where('roles.data.0.name', 'Admin Role')
    );
});

test('unauthorized user cannot access role management', function () {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('roles.index'));

    $response->assertStatus(403);
});
