<?php

use App\Http\Controllers\Security\TrustedDeviceController;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('trusted device service can get user devices', function () {
    $user = User::factory()->create();
    $service = app(TrustedDeviceService::class);

    // Test that service can get user devices without errors
    $devices = $service->getUserTrustedDevices($user);

    expect($devices)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($devices)->toHaveCount(0); // Should be empty for new user
});

test('trusted device controller can create device', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $service = app(TrustedDeviceService::class);
    $controller = new TrustedDeviceController($service);

    $request = Request::create('/security/trusted-devices', 'POST', [
        'device_name' => 'Test Device',
        'remember_duration' => 30,
    ]);

    $request->headers->set('User-Agent', 'Test Browser');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $response = $controller->store($request);

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message');
    expect($data['message'])->toBe('Device marked as trusted successfully');
});

test('trusted device controller can update device', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $service = app(TrustedDeviceService::class);
    $controller = new TrustedDeviceController($service);

    // First create a device
    $createRequest = Request::create('/security/trusted-devices', 'POST', [
        'device_name' => 'Test Device',
    ]);
    $createRequest->headers->set('User-Agent', 'Test Browser');
    $createRequest->server->set('REMOTE_ADDR', '127.0.0.1');

    $createResponse = $controller->store($createRequest);
    $createData = json_decode($createResponse->getContent(), true);
    $deviceId = $createData['device']['id'];

    // Now update the device
    $updateRequest = Request::create('/security/trusted-devices/'.$deviceId, 'PUT', [
        'device_name' => 'Updated Device Name',
        'extend_days' => 60,
    ]);

    $response = $controller->update($updateRequest, $deviceId);

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message');
    expect($data['device']['device_name'])->toBe('Updated Device Name');
});

test('trusted device controller can revoke device', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $service = app(TrustedDeviceService::class);
    $controller = new TrustedDeviceController($service);

    // First create a device
    $createRequest = Request::create('/security/trusted-devices', 'POST', [
        'device_name' => 'Test Device',
    ]);
    $createRequest->headers->set('User-Agent', 'Test Browser');
    $createRequest->server->set('REMOTE_ADDR', '127.0.0.1');

    $createResponse = $controller->store($createRequest);
    $createData = json_decode($createResponse->getContent(), true);
    $deviceId = $createData['device']['id'];

    // Now revoke the device
    $response = $controller->destroy($deviceId);

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message');
    expect($data['message'])->toBe('Device revoked successfully');
});

test('trusted device controller can revoke all devices', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $service = app(TrustedDeviceService::class);
    $controller = new TrustedDeviceController($service);

    $response = $controller->revokeAll();

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message');
    expect($data)->toHaveKey('revoked_count');
});

test('trusted device controller can cleanup expired devices', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $service = app(TrustedDeviceService::class);
    $controller = new TrustedDeviceController($service);

    $response = $controller->cleanup();

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message');
    expect($data)->toHaveKey('cleaned_count');
});
