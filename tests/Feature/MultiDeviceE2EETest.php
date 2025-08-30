<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\Chat\Conversation;
use App\Services\MultiDeviceQuantumE2EEService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MultiDeviceE2EETest extends TestCase
{
    use RefreshDatabase;

    private MultiDeviceQuantumE2EEService $multiDeviceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->multiDeviceService = app(MultiDeviceQuantumE2EEService::class);
    }

    public function test_user_can_initialize_multi_device_support()
    {
        $user = User::factory()->create();

        $result = $this->multiDeviceService->initializeMultiDevice($user);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('setup_complete', $result);
        $this->assertTrue($result['setup_complete']);
    }

    public function test_user_can_register_device()
    {
        $user = User::factory()->create();
        $this->multiDeviceService->initializeMultiDevice($user);

        $deviceData = [
            'device_name' => 'Test Device',
            'device_type' => 'desktop',
            'platform' => 'Windows',
            'public_key' => ['test' => 'key'],
            'quantum_key_info' => ['quantum' => 'info'],
            'quantum_security_level' => 8,
        ];

        $result = $this->multiDeviceService->registerDevice($user, $deviceData);

        if (!$result['success']) {
            $this->fail('Device registration failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('device_id', $result);
        $this->assertArrayHasKey('verification_code', $result);
    }

    public function test_user_can_verify_device()
    {
        $user = User::factory()->create();
        $this->multiDeviceService->initializeMultiDevice($user);

        $deviceData = [
            'device_name' => 'Test Device',
            'device_type' => 'mobile',
            'platform' => 'iOS',
            'public_key' => ['test' => 'key'],
            'quantum_key_info' => ['quantum' => 'info'],
        ];

        $registerResult = $this->multiDeviceService->registerDevice($user, $deviceData);
        $device = UserDevice::where('user_id', $user->id)
            ->where('device_name', 'Test Device')
            ->first();

        // Simulate verification code (we'd normally get this from the device)
        $verificationCode = '123456';
        $device->update(['verification_token' => $verificationCode]);

        $result = $this->multiDeviceService->verifyDevice($user, $device->id, $verificationCode);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('device', $result);
        
        $device->refresh();
        $this->assertTrue($device->is_trusted);
    }

    public function test_device_sync_keys_updates_session()
    {
        $user = User::factory()->create();
        $this->multiDeviceService->initializeMultiDevice($user);

        $deviceData = [
            'device_name' => 'Test Device',
            'device_type' => 'tablet',
            'platform' => 'Android',
            'public_key' => ['test' => 'key'],
            'quantum_key_info' => ['quantum' => 'info'],
        ];

        $registerResult = $this->multiDeviceService->registerDevice($user, $deviceData);
        $device = UserDevice::where('user_id', $user->id)->first();
        
        // Verify device
        $device->update([
            'verification_token' => '123456',
            'is_trusted' => true
        ]);

        $result = $this->multiDeviceService->syncDeviceKeys($user, $device->id);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('synced_devices', $result);
        $this->assertArrayHasKey('sync_results', $result);
    }

    public function test_cross_device_message_encryption()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => 'direct']);
        
        // Initialize multi-device
        $this->multiDeviceService->initializeMultiDevice($user);

        // Register and verify devices
        $device1Data = [
            'device_name' => 'Desktop',
            'device_type' => 'desktop',
            'platform' => 'Windows',
            'public_key' => ['desktop' => 'key'],
            'quantum_key_info' => ['desktop' => 'quantum'],
        ];

        $device2Data = [
            'device_name' => 'Mobile',
            'device_type' => 'mobile',
            'platform' => 'iOS',
            'public_key' => ['mobile' => 'key'],
            'quantum_key_info' => ['mobile' => 'quantum'],
        ];

        $this->multiDeviceService->registerDevice($user, $device1Data);
        $this->multiDeviceService->registerDevice($user, $device2Data);

        $devices = UserDevice::where('user_id', $user->id)->get();
        foreach ($devices as $device) {
            $device->update([
                'verification_token' => '123456',
                'is_trusted' => true
            ]);
        }

        $senderDeviceId = $devices->first()->id;
        $messageContent = 'Test encrypted message';
        $encryptedData = ['test' => 'encrypted_content'];

        $result = $this->multiDeviceService->encryptForMultipleDevices(
            $user,
            $senderDeviceId,
            $conversation->id,
            $messageContent,
            $encryptedData
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertArrayHasKey('target_device_count', $result);
        $this->assertEquals(2, $result['target_device_count']);
    }

    public function test_api_device_registration_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Initialize multi-device first
        $response = $this->postJson('/api/v1/chat/multidevice/initialize');
        $response->assertStatus(200);

        // Register device
        $deviceData = [
            'device_name' => 'API Test Device',
            'device_type' => 'desktop',
            'platform' => 'Linux',
            'public_key' => ['api' => 'test'],
            'quantum_key_info' => ['api' => 'quantum'],
            'quantum_security_level' => 9,
        ];

        $response = $this->postJson('/api/v1/chat/multidevice/devices/register', $deviceData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'device_id',
            'verification_code',
            'expires_at',
        ]);
    }

    public function test_api_device_list_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create some devices
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Test Device',
            'device_type' => 'mobile',
            'is_trusted' => true,
        ]);

        $response = $this->getJson('/api/v1/chat/multidevice/devices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'devices' => [
                '*' => [
                    'device_name',
                    'device_type',
                    'is_trusted',
                    'is_online',
                ]
            ]
        ]);
    }

    public function test_api_device_metrics_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/chat/multidevice/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'metrics' => [
                'total_devices',
                'trusted_devices',
                'quantum_safe_devices',
            ]
        ]);
    }
}