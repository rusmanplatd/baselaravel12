<?php

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->trustedDeviceService = app(TrustedDeviceService::class);

    // Set up a mock request for testing
    $this->mockRequest = Request::create('/', 'GET');
    $this->mockRequest->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $this->mockRequest->server->set('REMOTE_ADDR', '127.0.0.1');
});

test('trusted device service works correctly', function () {
    // Test the service directly to avoid Inertia/asset issues in testing
    $service = app(\App\Services\TrustedDeviceService::class);

    // Test that service can get user devices without errors
    $devices = $service->getUserTrustedDevices($this->user);

    expect($devices)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($devices)->toHaveCount(0); // Should be empty for new user
});

test('user can create trusted device', function () {
    $response = $this->actingAs($this->user)
        ->post(route('security.trusted-devices.store'), [
            'device_name' => 'Test Device',
            'remember_duration' => 30,
        ]);

    $response->assertOk();
    $response->assertJson([
        'message' => 'Device marked as trusted successfully',
    ]);

    $this->assertDatabaseHas('trusted_devices', [
        'user_id' => $this->user->id,
        'device_name' => 'Test Device',
        'is_active' => true,
    ]);
});

test('trusted device is created with auto-generated name when not provided', function () {
    $response = $this->actingAs($this->user)
        ->post(route('security.trusted-devices.store'), [
            'remember_duration' => 30,
        ]);

    $response->assertOk();

    $device = TrustedDevice::where('user_id', $this->user->id)->first();
    expect($device)->not->toBeNull();
    expect($device->device_name)->not->toBeEmpty();
});

test('user can update trusted device', function () {
    $device = TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'device_name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('security.trusted-devices.update', $device->id), [
            'device_name' => 'New Name',
            'extend_days' => 60,
        ]);

    $response->assertOk();

    $device->refresh();
    expect($device->device_name)->toBe('New Name');
    expect($device->expires_at->greaterThan(now()->addDays(30)))->toBeTrue();
});

test('user can revoke trusted device', function () {
    $device = TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('security.trusted-devices.destroy', $device->id));

    $response->assertOk();

    $device->refresh();
    expect($device->is_active)->toBeFalse();
});

test('user cannot access other users trusted devices', function () {
    $otherUser = User::factory()->create();
    $device = TrustedDevice::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('security.trusted-devices.destroy', $device->id));

    $response->assertNotFound();
});

test('user can revoke all trusted devices except current', function () {
    // Create multiple trusted devices
    TrustedDevice::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('security.trusted-devices.revoke-all'));

    $response->assertOk();
    $response->assertJsonStructure(['revoked_count']);
});

test('user can cleanup expired devices', function () {
    // Create expired device
    TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'expires_at' => now()->subDays(1),
        'is_active' => true,
    ]);

    // Create active device
    TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'expires_at' => now()->addDays(30),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('security.trusted-devices.cleanup'));

    $response->assertOk();
    $response->assertJsonStructure(['cleaned_count']);
});

test('trusted device service can create and check device', function () {
    $device = $this->trustedDeviceService->createTrustedDevice($this->mockRequest, $this->user, 'Test Device');

    expect($device)->not->toBeNull();
    expect($device->device_name)->toBe('Test Device');
    expect($device->user_id)->toBe($this->user->id);
    expect($device->is_active)->toBeTrue();

    // Test checking device with token
    $this->mockRequest->cookies->set('trusted_device_token', $device->device_token);
    $foundDevice = $this->trustedDeviceService->checkTrustedDevice($this->mockRequest, $this->user);

    expect($foundDevice)->not->toBeNull();
    expect($foundDevice->id)->toBe($device->id);
});

test('trusted device service returns null for invalid token', function () {
    $this->mockRequest->cookies->set('trusted_device_token', 'invalid-token');

    $foundDevice = $this->trustedDeviceService->checkTrustedDevice($this->mockRequest, $this->user);

    expect($foundDevice)->toBeNull();
});

test('trusted device has proper scopes', function () {
    $activeDevice = TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $expiredDevice = TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'expires_at' => now()->subDays(1),
    ]);

    $activeDevices = TrustedDevice::active()->get();
    $expiredDevices = TrustedDevice::expired()->get();

    expect($activeDevices->contains($activeDevice))->toBeTrue();
    expect($activeDevices->contains($expiredDevice))->toBeFalse();
    expect($expiredDevices->contains($expiredDevice))->toBeTrue();
});

test('trusted device methods work correctly', function () {
    $device = TrustedDevice::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
        'expires_at' => now()->addDays(30),
    ]);

    expect($device->isActive())->toBeTrue();
    expect($device->isExpired())->toBeFalse();

    // Test extend method
    $originalExpiry = $device->expires_at;
    $device->extend(60);
    $device->refresh();

    expect($device->expires_at->greaterThan($originalExpiry))->toBeTrue();

    // Test revoke method
    $device->revoke();
    $device->refresh();

    expect($device->is_active)->toBeFalse();
});
