<?php

use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserMfaSetting;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user activity is logged when user is created', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect(Activity::count())->toBe(1);
    
    $activity = Activity::first();
    expect($activity->log_name)->toBe('user');
    expect($activity->description)->toBe('User created');
    expect($activity->subject_id)->toBe($user->id);
    expect($activity->subject_type)->toBe(User::class);
});

test('user activity is logged when user is updated', function () {
    $user = User::factory()->create();
    
    // Clear creation activity
    Activity::truncate();
    
    $user->update(['name' => 'Updated Name']);

    expect(Activity::count())->toBe(1);
    
    $activity = Activity::first();
    expect($activity->log_name)->toBe('user');
    expect($activity->description)->toBe('User updated');
    expect($activity->properties->get('attributes'))->toHaveKey('name', 'Updated Name');
});

test('organization activity is logged when organization is created', function () {
    $organization = Organization::factory()->create([
        'name' => 'Test Organization',
        'organization_code' => 'TEST001',
    ]);

    $activities = Activity::where('log_name', 'organization')->get();
    expect($activities)->toHaveCount(1);
    
    $activity = $activities->first();
    expect($activity->description)->toBe('Organization created');
    expect($activity->subject_id)->toBe($organization->id);
});

test('organization membership activity is logged when membership is created', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    
    // Clear creation activities
    Activity::truncate();
    
    $membership = OrganizationMembership::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    $activities = Activity::where('log_name', 'organization')->get();
    expect($activities)->toHaveCount(1);
    
    $activity = $activities->first();
    expect($activity->description)->toBe('Organization membership created');
    expect($activity->subject_id)->toBe($membership->id);
});

test('mfa settings activity is logged when mfa is enabled', function () {
    $user = User::factory()->create();
    
    // Clear creation activities
    Activity::truncate();
    
    $mfaSettings = UserMfaSetting::create([
        'user_id' => $user->id,
        'totp_enabled' => true,
        'totp_secret' => 'test-secret',
        'totp_confirmed_at' => now(),
        'mfa_required' => false,
    ]);

    $activities = Activity::where('log_name', 'security')->get();
    expect($activities)->toHaveCount(1);
    
    $activity = $activities->first();
    expect($activity->description)->toBe('MFA settings created');
    expect($activity->subject_id)->toBe($mfaSettings->id);
    expect($activity->properties->get('attributes'))->toHaveKey('totp_enabled', true);
});

test('trusted device activity is logged when device is created', function () {
    $user = User::factory()->create();
    
    // Clear creation activities
    Activity::truncate();
    
    $device = TrustedDevice::create([
        'user_id' => $user->id,
        'device_name' => 'Test Device',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'platform' => 'Windows',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0...',
        'is_active' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $activities = Activity::where('log_name', 'security')->get();
    expect($activities)->toHaveCount(1);
    
    $activity = $activities->first();
    expect($activity->description)->toBe('Trusted device created');
    expect($activity->subject_id)->toBe($device->id);
});

test('activity log service logs auth events correctly', function () {
    $user = User::factory()->create();
    
    // Authenticate the user for the activity log
    $this->actingAs($user);
    
    ActivityLogService::logAuth('login', 'User logged in successfully', [
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Browser',
    ], $user);

    $activity = Activity::where('log_name', 'auth')->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('User logged in successfully');
    expect($activity->event)->toBe('login');
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->properties->get('ip_address'))->toBe('127.0.0.1');
});

test('activity log service logs organization events correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    
    $this->actingAs($user);
    
    ActivityLogService::logOrganization('updated', 'Organization settings updated', [
        'changed_fields' => ['name', 'description'],
    ], $organization);

    $activity = Activity::where('log_name', 'organization')->where('event', 'updated')->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('Organization settings updated');
    expect($activity->subject_id)->toBe($organization->id);
    expect($activity->properties->get('changed_fields'))->toBe(['name', 'description']);
});

test('activity log service logs oauth events correctly', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    ActivityLogService::logOAuth('client_created', 'OAuth client created', [
        'client_name' => 'Test Client',
        'grant_types' => ['authorization_code'],
    ], $user);

    $activity = Activity::where('log_name', 'oauth')->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('OAuth client created');
    expect($activity->event)->toBe('client_created');
    expect($activity->causer_id)->toBe($user->id);
});

test('activity log service logs system events correctly', function () {
    ActivityLogService::logSystem('maintenance', 'System maintenance performed', [
        'maintenance_type' => 'database_cleanup',
        'duration' => '5 minutes',
    ]);

    $activity = Activity::where('log_name', 'system')->first();
    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('System maintenance performed');
    expect($activity->event)->toBe('maintenance');
    expect($activity->properties->get('maintenance_type'))->toBe('database_cleanup');
});

test('activity queries work with scopes', function () {
    $user = User::factory()->create();
    
    // Clear all activities from previous tests
    Activity::truncate();
    
    $this->actingAs($user);
    
    ActivityLogService::logAuth('login', 'User logged in', [], $user);
    ActivityLogService::logOrganization('created', 'Organization created', []);
    ActivityLogService::logOAuth('token_issued', 'OAuth token issued', []);
    ActivityLogService::logSystem('backup', 'System backup completed', []);

    expect(Activity::auth()->count())->toBe(1);
    expect(Activity::organizationManagement()->count())->toBe(1);
    expect(Activity::oauth()->count())->toBe(1);
    expect(Activity::system()->count())->toBe(1);
    expect(Activity::forUser($user->id)->count())->toBe(4); // all activities logged with authenticated user as causer
});

test('activity log respects tenant scoping when available', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    
    $this->actingAs($user);
    
    // Simulate tenant context by setting organization_id directly
    $activity = ActivityLogService::logAuth('login', 'User logged in', [], $user);
    $activity->update(['organization_id' => $organization->id]);

    expect(Activity::forOrganization($organization->id)->count())->toBe(1);
    expect(Activity::forOrganization('non-existent')->count())->toBe(0);
});