<?php

namespace Tests\Feature;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuantumDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private MultiDeviceEncryptionService $multiDeviceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->multiDeviceService = app(MultiDeviceEncryptionService::class);
    }

    public function test_quantum_device_registration()
    {
        $user = User::factory()->create();

        $device = $this->multiDeviceService->registerQuantumDevice(
            $user,
            'Test Quantum Device',
            'desktop',
            base64_encode('quantum-public-key'),
            hash('sha256', 'device-fingerprint'),
            ['ml-kem-768', 'ml-kem-512', 'hybrid']
        );

        $this->assertInstanceOf(UserDevice::class, $device);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertEquals('Test Quantum Device', $device->device_name);
        $this->assertEquals('desktop', $device->device_type);
        $this->assertEquals(3, $device->encryption_version);
        $this->assertTrue($device->supportsQuantumResistant());
        $this->assertTrue($device->isQuantumReady());
        $this->assertContains('ML-KEM-768', $device->getSupportedAlgorithms());
        $this->assertContains('HYBRID-RSA4096-MLKEM768', $device->getSupportedAlgorithms());
    }

    public function test_device_capability_upgrade()
    {
        $user = User::factory()->create();

        // Create legacy device
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Legacy Device',
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        $this->assertFalse($device->supportsQuantumResistant());
        $this->assertFalse($device->isQuantumReady());

        // Upgrade to quantum capabilities
        $device->updateQuantumCapabilities(['ml-kem-768', 'hybrid']);
        $device->refresh();

        $this->assertTrue($device->supportsQuantumResistant());
        $this->assertTrue($device->isQuantumReady());
        $this->assertEquals(3, $device->encryption_version);
        $this->assertContains('ML-KEM-768', $device->getSupportedAlgorithms());
    }

    public function test_bulk_device_upgrade()
    {
        $user = User::factory()->create();

        // Create multiple legacy devices
        $devices = UserDevice::factory()->count(5)->create([
            'user_id' => $user->id,
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/quantum/devices/bulk-upgrade', [
            'target_capabilities' => ['ml-kem-768', 'hybrid'],
            'upgrade_all' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'upgraded_devices',
            'failed_devices',
            'summary' => [
                'total_devices',
                'upgraded_count',
                'failed_count',
                'success_rate',
            ],
        ]);

        $data = $response->json();
        $this->assertEquals(5, $data['summary']['total_devices']);
        $this->assertEquals(5, $data['summary']['upgraded_count']);
        $this->assertEquals(0, $data['summary']['failed_count']);
        $this->assertEquals(100.0, $data['summary']['success_rate']);

        // Verify devices were upgraded
        $devices->each(function ($device) {
            $device->refresh();
            $this->assertEquals(3, $device->encryption_version);
            $this->assertTrue($device->supportsQuantumResistant());
        });
    }

    public function test_device_quantum_readiness_assessment()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create devices with different readiness levels
        $devices = [
            // Quantum ready
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Quantum Ready',
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-768'],
            ]),

            // Hybrid capable
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Hybrid Capable',
                'encryption_version' => 3,
                'device_capabilities' => ['hybrid', 'rsa-4096'],
            ]),

            // Legacy only
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Legacy Only',
                'encryption_version' => 2,
                'device_capabilities' => ['rsa-4096'],
            ]),

            // Not quantum ready (old version)
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Old Version',
                'encryption_version' => 1,
                'device_capabilities' => ['rsa-2048'],
            ]),
        ];

        $response = $this->getJson('/api/v1/quantum/devices/readiness-assessment');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_devices',
            'quantum_ready_devices',
            'hybrid_capable_devices',
            'legacy_devices',
            'quantum_readiness_percentage',
            'recommendations',
            'upgrade_priority',
            'device_breakdown',
        ]);

        $data = $response->json();
        $this->assertEquals(4, $data['total_devices']);
        $this->assertEquals(1, $data['quantum_ready_devices']);
        $this->assertEquals(1, $data['hybrid_capable_devices']);
        $this->assertEquals(2, $data['legacy_devices']);
        $this->assertEquals(25.0, $data['quantum_readiness_percentage']);

        $this->assertIsArray($data['recommendations']);
        $this->assertIsArray($data['upgrade_priority']);
        $this->assertArrayHasKey('high_priority', $data['upgrade_priority']);
        $this->assertArrayHasKey('medium_priority', $data['upgrade_priority']);
        $this->assertArrayHasKey('low_priority', $data['upgrade_priority']);
    }

    public function test_device_security_level_management()
    {
        $user = User::factory()->create();

        $device = $this->multiDeviceService->registerQuantumDevice(
            $user,
            'Security Test Device',
            'mobile',
            base64_encode('test-key'),
            hash('sha256', 'test-fp'),
            ['ml-kem-512'] // Start with lower security
        );

        $this->actingAs($user, 'api');

        // Test upgrading security level
        $response = $this->putJson("/api/v1/quantum/devices/{$device->id}/security-level", [
            'target_level' => 'high',
            'algorithms' => ['ml-kem-1024', 'ml-kem-768'],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'security_level_upgraded' => true,
            'new_capabilities' => ['ML-KEM-1024', 'ML-KEM-768'],
        ]);

        $device->refresh();
        $algorithms = $device->getSupportedAlgorithms();
        $this->assertContains('ML-KEM-1024', $algorithms);
        $this->assertContains('ML-KEM-768', $algorithms);
    }

    public function test_device_compatibility_check()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'hybrid'],
        ]);

        // Test compatibility with different algorithms
        $algorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP'];

        foreach ($algorithms as $algorithm) {
            $response = $this->postJson("/api/v1/quantum/devices/{$device->id}/compatibility-check", [
                'algorithm' => $algorithm,
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'compatible',
                'compatibility_score',
                'performance_impact',
                'security_level',
                'recommendation',
            ]);

            $data = $response->json();

            if (in_array($algorithm, ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768'])) {
                $this->assertTrue($data['compatible']);
                $this->assertGreaterThan(80, $data['compatibility_score']);
            } elseif ($algorithm === 'ML-KEM-1024') {
                $this->assertFalse($data['compatible']);
            }
        }
    }

    public function test_device_migration_to_quantum()
    {
        $user = User::factory()->create();

        $legacyDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Legacy Device',
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
            'last_used_at' => now(),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/v1/quantum/devices/{$legacyDevice->id}/migrate", [
            'migration_type' => 'quantum',
            'target_algorithms' => ['ml-kem-768', 'hybrid'],
            'preserve_keys' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'migration_successful',
            'device_id',
            'old_capabilities',
            'new_capabilities',
            'keys_preserved',
            'migration_time',
        ]);

        $data = $response->json();
        $this->assertTrue($data['migration_successful']);
        $this->assertTrue($data['keys_preserved']);

        $legacyDevice->refresh();
        $this->assertEquals(3, $legacyDevice->encryption_version);
        $this->assertTrue($legacyDevice->supportsQuantumResistant());
        $this->assertContains('ML-KEM-768', $legacyDevice->getSupportedAlgorithms());
    }

    public function test_device_key_rotation_on_upgrade()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        // Create encryption keys for the device
        $oldKeys = EncryptionKey::factory()->count(3)->create([
            'device_id' => $device->id,
            'algorithm' => 'RSA-4096-OAEP',
            'key_version' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/v1/quantum/devices/{$device->id}/migrate", [
            'migration_type' => 'quantum',
            'target_algorithms' => ['ml-kem-768'],
            'rotate_keys' => true,
        ]);

        $response->assertStatus(200);

        // Verify old keys are deactivated
        $oldKeys->each(function ($key) {
            $key->refresh();
            $this->assertFalse($key->is_active);
        });

        // Verify new keys are created
        $newKeys = EncryptionKey::where('device_id', $device->id)
            ->where('is_active', true)
            ->where('key_version', '>', 1)
            ->get();

        $this->assertGreaterThan(0, $newKeys->count());
        $newKeys->each(function ($key) {
            $this->assertNotEquals('RSA-4096-OAEP', $key->algorithm);
            $this->assertTrue($key->is_active);
        });
    }

    public function test_device_capability_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Test valid capabilities
        $validResponse = $this->postJson('/api/v1/quantum/devices/validate-capabilities', [
            'capabilities' => ['ml-kem-768', 'ml-kem-512', 'hybrid'],
        ]);

        $validResponse->assertStatus(200);
        $validResponse->assertJson([
            'valid' => true,
            'supported_algorithms' => ['ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'],
        ]);

        // Test invalid capabilities
        $invalidResponse = $this->postJson('/api/v1/quantum/devices/validate-capabilities', [
            'capabilities' => ['invalid-algorithm', 'ml-kem-999', 'fake-quantum'],
        ]);

        $invalidResponse->assertStatus(200);
        $data = $invalidResponse->json();
        $this->assertFalse($data['valid']);
        $this->assertArrayHasKey('invalid_capabilities', $data);
        $this->assertContains('invalid-algorithm', $data['invalid_capabilities']);
        $this->assertContains('ml-kem-999', $data['invalid_capabilities']);
    }

    public function test_device_performance_monitoring()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'ml-kem-512'],
        ]);

        // Simulate performance metrics collection
        Cache::put("device_performance_{$device->id}", [
            'key_generation_time_ms' => 15,
            'encryption_time_ms' => 2,
            'decryption_time_ms' => 3,
            'memory_usage_kb' => 1024,
            'battery_impact_low' => true,
            'last_measured' => now()->toISOString(),
        ], 3600);

        $response = $this->getJson("/api/v1/quantum/devices/{$device->id}/performance");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'device_id',
            'performance_metrics' => [
                'key_generation_time_ms',
                'encryption_time_ms',
                'decryption_time_ms',
                'memory_usage_kb',
                'battery_impact_low',
            ],
            'performance_grade',
            'recommendations',
        ]);

        $data = $response->json();
        $this->assertLessThan(100, $data['performance_metrics']['key_generation_time_ms']);
        $this->assertContains($data['performance_grade'], ['A', 'B', 'C', 'D', 'F']);
    }

    public function test_cross_device_quantum_setup()
    {
        $user = User::factory()->create();

        // Create multiple quantum-capable devices
        $devices = [
            $this->multiDeviceService->registerQuantumDevice(
                $user, 'Desktop', 'desktop', 'key1', 'fp1', ['ml-kem-1024', 'ml-kem-768']
            ),
            $this->multiDeviceService->registerQuantumDevice(
                $user, 'Mobile', 'mobile', 'key2', 'fp2', ['ml-kem-768', 'ml-kem-512']
            ),
            $this->multiDeviceService->registerQuantumDevice(
                $user, 'Tablet', 'tablet', 'key3', 'fp3', ['ml-kem-768', 'hybrid']
            ),
        ];

        $conversation = Conversation::factory()->create();

        $result = $this->multiDeviceService->setupQuantumConversationEncryption(
            $conversation,
            $devices,
            $devices[0] // Desktop as initiating device
        );

        $this->assertArrayHasKey('algorithm', $result);
        $this->assertArrayHasKey('created_keys', $result);
        $this->assertArrayHasKey('failed_keys', $result);

        // Should negotiate ML-KEM-768 as common algorithm
        $this->assertEquals('ML-KEM-768', $result['algorithm']);
        $this->assertEquals(3, count($result['created_keys']));
        $this->assertEquals(0, count($result['failed_keys']));

        // Verify encryption keys were created for all devices
        foreach ($devices as $device) {
            $keys = EncryptionKey::where('device_id', $device->id)
                ->where('conversation_id', $conversation->id)
                ->where('algorithm', 'ML-KEM-768')
                ->where('is_active', true)
                ->get();

            $this->assertGreaterThan(0, $keys->count());
        }
    }

    public function test_device_quantum_capability_expiration()
    {
        $user = User::factory()->create();

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
            'capabilities_verified_at' => now()->subDays(35), // Expired verification
            'last_quantum_health_check' => now()->subDays(30),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/v1/quantum/devices/{$device->id}/verify-capabilities");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'verification_needed',
            'capabilities_expired',
            'last_verified',
            'recommended_actions',
        ]);

        $data = $response->json();
        $this->assertTrue($data['verification_needed']);
        $this->assertTrue($data['capabilities_expired']);
        $this->assertIsArray($data['recommended_actions']);
    }

    public function test_device_quantum_health_monitoring()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        $response = $this->postJson("/api/v1/quantum/devices/{$device->id}/health-check");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'device_id',
            'health_status',
            'quantum_ready',
            'algorithm_support' => [
                'ml_kem_levels',
                'hybrid_support',
                'performance_metrics',
            ],
            'issues_detected',
            'recommendations',
            'last_health_check',
        ]);

        $data = $response->json();
        $this->assertContains($data['health_status'], ['healthy', 'warning', 'critical']);
        $this->assertTrue($data['quantum_ready']);
        $this->assertIsArray($data['algorithm_support']['ml_kem_levels']);
        $this->assertIsArray($data['issues_detected']);
    }
}
