<?php

use App\Models\Activity;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogExportService;

beforeEach(function () {
    $this->exportService = app(ActivityLogExportService::class);

    // Create organization
    $this->organization = Organization::factory()->create([
        'name' => 'Test Organization',
        'organization_code' => 'TEST',
    ]);

    // Create admin user with export permissions
    $this->adminUser = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    // Create regular user without export permissions
    $this->regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
    ]);

    // Create permissions using factory
    $auditAdminPermission = Permission::factory()->create([
        'name' => 'audit_log:admin',
        'guard_name' => 'web',
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);

    $auditReadPermission = Permission::factory()->create([
        'name' => 'audit_log:read',
        'guard_name' => 'web',
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);

    // Create admin role with audit permissions (use adminUser as created_by)
    $adminRole = Role::factory()->create([
        'name' => 'admin',
        'guard_name' => 'web',
        'team_id' => $this->organization->id,
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);
    $adminRole->givePermissionTo(['audit_log:admin', 'audit_log:read']);

    $userRole = Role::factory()->create([
        'name' => 'user',
        'guard_name' => 'web',
        'team_id' => $this->organization->id,
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);
    $userRole->givePermissionTo(['audit_log:read']);

    // Directly add roles to users via the pivot table to bypass team validation
    $this->adminUser->roles()->attach($adminRole->id, ['team_id' => $this->organization->id]);
    $this->regularUser->roles()->attach($userRole->id, ['team_id' => $this->organization->id]);

    // Create test activities
    createTestActivities();
});

function createTestActivities()
{
    // Create activities for different scenarios
    Activity::factory()->count(5)->create([
        'causer_id' => test()->adminUser->id,
        'organization_id' => test()->organization->id,
        'log_name' => 'auth',
        'event' => 'login',
        'description' => 'User logged in',
    ]);

    Activity::factory()->count(3)->create([
        'causer_id' => test()->regularUser->id,
        'organization_id' => test()->organization->id,
        'log_name' => 'organization',
        'event' => 'created',
        'description' => 'Organization created',
    ]);

    Activity::factory()->count(2)->create([
        'causer_id' => test()->adminUser->id,
        'organization_id' => null,
        'log_name' => 'system',
        'event' => 'maintenance',
        'description' => 'System maintenance performed',
    ]);
}

it('allows admin to access export all endpoint', function () {
    $this->actingAs($this->adminUser);

    // Set the team context for Spatie permissions
    setPermissionsTeamId($this->organization->id);

    $response = $this->postJson(route('activity-log.export.all'), [
        'format' => 'csv',
        'columns' => ['id', 'log_name', 'description', 'created_at'],
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('allows admin to access export filtered endpoint', function () {
    $this->actingAs($this->adminUser);

    // Set the team context for Spatie permissions
    setPermissionsTeamId($this->organization->id);

    $response = $this->postJson(route('activity-log.export.filtered'), [
        'format' => 'json',
        'columns' => ['id', 'log_name', 'description'],
        'filters' => [
            'resource' => 'auth',
            'from_date' => now()->subDays(7)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/json');
});

it('prevents regular user from accessing export endpoints', function () {
    $this->actingAs($this->regularUser);

    $response = $this->postJson(route('activity-log.export.all'), [
        'format' => 'csv',
        'columns' => ['id', 'log_name', 'description'],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Export validation failed',
            'errors' => ['You do not have permission to export activity logs.'],
        ]);
});

it('redirects unauthenticated users trying to access export endpoints', function () {
    $response = $this->postJson(route('activity-log.export.all'), [
        'format' => 'csv',
        'columns' => ['id', 'log_name', 'description'],
    ]);

    $response->assertStatus(401);
});

it('returns correct data from validate export endpoint', function () {
    $this->actingAs($this->adminUser);
    setPermissionsTeamId($this->organization->id);

    $response = $this->postJson(route('activity-log.export.validate'), [
        'filters' => [
            'resource' => 'auth',
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'valid',
            'errors',
            'estimated_records',
            'max_records',
        ]);

    $data = $response->json();
    expect($data['valid'])->toBeTrue();
    expect($data['estimated_records'])->toBe(5); // We created 5 auth activities
    expect($data['max_records'])->toBe(50000);
});

it('validates required parameters for export', function () {
    $this->actingAs($this->adminUser);
    setPermissionsTeamId($this->organization->id);

    // Test missing format
    $response = $this->postJson(route('activity-log.export.all'), [
        'columns' => ['id', 'log_name'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['format']);

    // Test invalid format
    $response = $this->postJson(route('activity-log.export.all'), [
        'format' => 'xml',
        'columns' => ['id', 'log_name'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['format']);
});

it('validates date range for filtered export', function () {
    $this->actingAs($this->adminUser);
    setPermissionsTeamId($this->organization->id);

    // Test invalid date range (from_date after to_date)
    $response = $this->postJson(route('activity-log.export.filtered'), [
        'format' => 'csv',
        'columns' => ['id', 'log_name'],
        'filters' => [
            'from_date' => '2024-12-31',
            'to_date' => '2024-01-01',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['filters.to_date']);
});

it('returns available columns for export', function () {
    $this->actingAs($this->adminUser);
    setPermissionsTeamId($this->organization->id);

    $response = $this->get(route('activity-log.export.columns'));

    $response->assertOk()
        ->assertJsonStructure([
            'columns' => [
                'basic' => [],
                'user_info' => [],
                'subject_info' => [],
                'organization_info' => [],
                'metadata' => [],
            ],
        ]);

    $data = $response->json();
    expect($data['columns']['basic'])->toHaveKey('id');
    expect($data['columns']['user_info'])->toHaveKey('causer_name');
    expect($data['columns']['organization_info'])->toHaveKey('organization_name');
});

it('can export all activities through service', function () {
    setPermissionsTeamId($this->organization->id);
    $result = $this->exportService->exportAll($this->adminUser, 'csv');

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['content', 'filename', 'mime_type', 'total_records']);
    expect($result['mime_type'])->toBe('text/csv');
    expect($result['total_records'])->toBeGreaterThanOrEqual(10); // We created at least 10 activities
    expect($result['filename'])->toContain('.csv');
});

it('can export filtered activities through service', function () {
    setPermissionsTeamId($this->organization->id);
    $filters = [
        'resource' => 'auth',
        'organization_id' => $this->organization->id,
    ];

    $result = $this->exportService->exportFiltered($this->adminUser, $filters, 'json');

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['content', 'total_records']);
    expect($result['mime_type'])->toBe('application/json');
    expect($result['total_records'])->toBe(5); // 5 auth activities in this organization

    $content = json_decode($result['content'], true);
    expect($content)->toHaveKeys(['export_info', 'data']);
    expect($content['data'])->toHaveCount(5);
});

it('respects user permissions when exporting', function () {
    // Regular user should only see their own activities
    $result = $this->exportService->exportAll($this->regularUser, 'csv');

    expect($result)->toBeArray();
    expect($result['total_records'])->toBe(3); // Regular user only created 3 activities

    // Verify CSV content contains only regular user's activities
    $csvLines = explode("\n", $result['content']);
    expect(count($csvLines))->toBeGreaterThan(3); // Header + data lines + empty line at end
});

it('validates available column selection', function () {
    $availableColumns = $this->exportService->getAvailableColumns();

    expect($availableColumns)->toBeArray();
    expect($availableColumns)->toHaveKeys(['basic', 'user_info', 'organization_info']);

    // Test that all basic columns are present
    $basicColumns = $availableColumns['basic'];
    expect($basicColumns)->toHaveKeys(['id', 'log_name', 'description']);
});

it('handles custom column selection', function () {
    setPermissionsTeamId($this->organization->id);
    $customColumns = ['id', 'log_name', 'causer_name', 'organization_name'];
    $result = $this->exportService->exportAll($this->adminUser, 'csv', $customColumns);

    $csvLines = explode("\n", trim($result['content']));
    $headerLine = $csvLines[0];

    // Verify that the header contains our custom columns
    expect($headerLine)->toContain('ID');
    expect($headerLine)->toContain('Resource');
    expect($headerLine)->toContain('User Name');
    expect($headerLine)->toContain('Organization Name');
});

it('shows export button for admin users on activity log index', function () {
    $this->actingAs($this->adminUser);
    setPermissionsTeamId($this->organization->id);

    $response = $this->get(route('activity-log.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.canExport', true)
            ->has('exportColumns')
        );
});

it('hides export button for regular users on activity log index', function () {
    $this->actingAs($this->regularUser);

    $response = $this->get(route('activity-log.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.canExport', false)
        );
});
