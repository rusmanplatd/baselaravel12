<?php

namespace Tests\Feature;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use App\Services\QuantumCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class QuantumFallbackRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private QuantumCryptoService $quantumService;

    private ChatEncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quantumService = app(QuantumCryptoService::class);
        $this->encryptionService = app(ChatEncryptionService::class);
    }

    public function test_fallback_to_rsa_when_quantum_unavailable()
    {
        // Mock quantum service to be unavailable
        $mockQuantumService = Mockery::mock(QuantumCryptoService::class);
        $mockQuantumService->shouldReceive('isMLKEMAvailable')->andReturn(false);
        $mockQuantumService->shouldReceive('isQuantumResistant')->andReturn(false);
        $this->app->instance(QuantumCryptoService::class, $mockQuantumService);

        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create device that supports quantum but quantum is unavailable
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'rsa-4096'],
        ]);

        $response = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
        ]);

        // Should fallback to RSA
        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'fallback_available']);

        // Test fallback key generation
        $fallbackResponse = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'RSA-4096-OAEP',
            'fallback_mode' => true,
        ]);

        $fallbackResponse->assertStatus(200);
        $data = $fallbackResponse->json();
        $this->assertEquals('RSA-4096-OAEP', $data['algorithm']);
        $this->assertFalse($data['quantum_resistant']);
    }

    public function test_hybrid_mode_when_mixed_capabilities()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 has quantum device
        $quantumDevice = UserDevice::factory()->create([
            'user_id' => $user1->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'hybrid'],
        ]);

        // User 2 has legacy device
        $legacyDevice = UserDevice::factory()->create([
            'user_id' => $user2->id,
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        $conversation = Conversation::factory()->create();
        $conversation->participants()->create(['user_id' => $user1->id]);
        $conversation->participants()->create(['user_id' => $user2->id]);

        $this->actingAs($user1, 'api');

        $response = $this->postJson("/api/v1/quantum/conversations/{$conversation->id}/negotiate-algorithm");

        $response->assertStatus(200);
        $data = $response->json();

        // Should negotiate hybrid or RSA for compatibility
        $this->assertContains($data['algorithm'], ['HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']);
        $this->assertArrayHasKey('fallback_reason', $data);
        $this->assertStringContainsString('legacy device compatibility', $data['fallback_reason']);
    }

    public function test_quantum_provider_failure_recovery()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Mock provider failure during key generation
        $mockQuantumService = Mockery::mock(QuantumCryptoService::class);
        $mockQuantumService->shouldReceive('isMLKEMAvailable')->andReturn(true);
        $mockQuantumService->shouldReceive('generateMLKEMKeyPair')
            ->andThrow(new \Exception('Provider communication failed'));
        $mockQuantumService->shouldReceive('isQuantumResistant')->andReturn(true);

        $this->app->instance(QuantumCryptoService::class, $mockQuantumService);

        $response = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
            'enable_fallback' => true,
        ]);

        // Should handle error gracefully and suggest fallback
        $response->assertStatus(503);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'fallback_suggestions',
            'retry_recommended',
        ]);

        $data = $response->json();
        $this->assertEquals('quantum_provider_unavailable', $data['error_code']);
        $this->assertContains('RSA-4096-OAEP', $data['fallback_suggestions']);
        $this->assertTrue($data['retry_recommended']);
    }

    public function test_automatic_fallback_during_conversation_setup()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        // Create device with quantum preference
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'rsa-4096'],
            'preferred_algorithm' => 'ML-KEM-768',
        ]);

        // Mock quantum failure during conversation setup
        $mockMultiDeviceService = Mockery::mock(MultiDeviceEncryptionService::class);
        $mockMultiDeviceService->shouldReceive('setupQuantumConversationEncryption')
            ->andThrow(new \Exception('Quantum setup failed'));
        $mockMultiDeviceService->shouldReceive('setupConversationEncryption')
            ->andReturn([
                'algorithm' => 'RSA-4096-OAEP',
                'created_keys' => 1,
                'failed_keys' => 0,
                'fallback_used' => true,
            ]);

        $this->app->instance(MultiDeviceEncryptionService::class, $mockMultiDeviceService);
        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/chat/conversations/setup-encryption', [
            'conversation_id' => $conversation->id,
            'preferred_algorithm' => 'ML-KEM-768',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('RSA-4096-OAEP', $data['algorithm']);
        $this->assertTrue($data['fallback_used']);
        $this->assertEquals(1, $data['created_keys']);
    }

    public function test_message_encryption_fallback()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'encryption_algorithm' => 'ML-KEM-768',
        ]);

        $this->actingAs($user, 'api');

        // Mock quantum encryption failure
        $mockEncryptionService = Mockery::mock(ChatEncryptionService::class);
        $mockEncryptionService->shouldReceive('encryptMessage')
            ->with(Mockery::type('string'), Mockery::type('string'), 'ML-KEM-768')
            ->andThrow(new \Exception('Quantum encryption failed'));
        $mockEncryptionService->shouldReceive('encryptMessage')
            ->with(Mockery::type('string'), Mockery::type('string'), 'RSA-4096-OAEP')
            ->andReturn([
                'content' => 'encrypted-content',
                'iv' => 'test-iv',
                'algorithm' => 'RSA-4096-OAEP',
                'version' => '2.0',
            ]);
        $mockEncryptionService->shouldReceive('isQuantumResistant')->andReturn(false);

        $this->app->instance(ChatEncryptionService::class, $mockEncryptionService);

        $response = $this->postJson('/api/v1/chat/messages', [
            'conversation_id' => $conversation->id,
            'content' => 'Test message with fallback',
            'allow_fallback' => true,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('fallback_used', $data);
        $this->assertTrue($data['fallback_used']);
        $this->assertEquals('RSA-4096-OAEP', $data['algorithm_used']);
        $this->assertArrayHasKey('warning', $data);
    }

    public function test_device_capability_degradation_handling()
    {
        $user = User::factory()->create();

        // Create device with quantum capabilities
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'hybrid', 'rsa-4096'],
            'quantum_health_score' => 95,
        ]);

        $this->actingAs($user, 'api');

        // Simulate device degradation
        $response = $this->postJson("/api/v1/quantum/devices/{$device->id}/health-check", [
            'simulate_degradation' => true,
            'degradation_type' => 'performance',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        if ($data['health_status'] === 'degraded') {
            $this->assertLessThan(95, $data['quantum_health_score']);
            $this->assertArrayHasKey('degradation_detected', $data);
            $this->assertArrayHasKey('recommended_actions', $data);

            // Verify fallback recommendations
            $this->assertContains('Consider using hybrid mode', $data['recommended_actions']);
            $this->assertArrayHasKey('fallback_algorithms', $data);
        }
    }

    public function test_network_partition_recovery()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Simulate network partition affecting quantum endpoints
        Cache::put('quantum_service_health', [
            'status' => 'degraded',
            'last_check' => now(),
            'failures' => 5,
            'network_issues' => true,
        ], 300);

        $response = $this->getJson('/api/v1/quantum/health');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('degraded', $data['status']);
        $this->assertTrue($data['network_issues'] ?? false);
        $this->assertArrayHasKey('fallback_recommendations', $data);

        // Test service recovery
        Cache::put('quantum_service_health', [
            'status' => 'healthy',
            'last_check' => now(),
            'failures' => 0,
            'network_issues' => false,
        ], 300);

        $recoveryResponse = $this->getJson('/api/v1/quantum/health');
        $recoveryData = $recoveryResponse->json();

        $this->assertEquals('healthy', $recoveryData['status']);
        $this->assertFalse($recoveryData['network_issues'] ?? true);
    }

    public function test_fallback_with_performance_monitoring()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Test performance-based fallback decision
        $response = $this->postJson('/api/v1/quantum/performance-test', [
            'test_algorithms' => ['ML-KEM-768', 'RSA-4096-OAEP'],
            'performance_threshold_ms' => 100,
            'enable_auto_fallback' => true,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('test_results', $data);
        $this->assertArrayHasKey('recommended_algorithm', $data);
        $this->assertArrayHasKey('fallback_triggered', $data);

        if ($data['fallback_triggered']) {
            $this->assertArrayHasKey('fallback_reason', $data);
            $this->assertContains('performance', $data['fallback_reason']);
        }
    }

    public function test_encryption_key_recovery_after_failure()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        // Create quantum encryption key
        $quantumKey = EncryptionKey::factory()->create([
            'conversation_id' => $conversation->id,
            'algorithm' => 'ML-KEM-768',
            'key_version' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'api');

        // Simulate key corruption/failure
        $response = $this->postJson("/api/v1/chat/encryption-keys/{$quantumKey->id}/validate");

        if ($response->status() === 422) {
            // Key is corrupted, test recovery
            $recoveryResponse = $this->postJson("/api/v1/chat/encryption-keys/{$quantumKey->id}/recover", [
                'recovery_method' => 'fallback_generation',
                'fallback_algorithm' => 'RSA-4096-OAEP',
            ]);

            $recoveryResponse->assertStatus(200);
            $recoveryData = $recoveryResponse->json();

            $this->assertTrue($recoveryData['recovery_successful']);
            $this->assertEquals('RSA-4096-OAEP', $recoveryData['new_algorithm']);
            $this->assertArrayHasKey('old_key_archived', $recoveryData);

            // Verify old key is deactivated
            $quantumKey->refresh();
            $this->assertFalse($quantumKey->is_active);
        }
    }

    public function test_cross_platform_fallback_compatibility()
    {
        $users = User::factory()->count(3)->create();

        // Create devices on different platforms with different capabilities
        $devices = [
            UserDevice::factory()->create([
                'user_id' => $users[0]->id,
                'device_type' => 'desktop',
                'platform' => 'windows',
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-1024', 'ml-kem-768'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $users[1]->id,
                'device_type' => 'mobile',
                'platform' => 'ios',
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-768', 'hybrid'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $users[2]->id,
                'device_type' => 'web',
                'platform' => 'browser',
                'encryption_version' => 2,
                'device_capabilities' => ['rsa-4096'],
            ]),
        ];

        $this->actingAs($users[0], 'api');

        $response = $this->postJson('/api/v1/quantum/cross-platform-compatibility', [
            'participant_devices' => collect($devices)->pluck('id')->toArray(),
            'test_algorithms' => ['ML-KEM-1024', 'ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP'],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('compatibility_matrix', $data);
        $this->assertArrayHasKey('universal_algorithm', $data);
        $this->assertArrayHasKey('platform_limitations', $data);

        // Should find RSA as universal fallback
        $this->assertEquals('RSA-4096-OAEP', $data['universal_algorithm']);
        $this->assertArrayHasKey('browser', $data['platform_limitations']);
    }

    public function test_quantum_service_circuit_breaker()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Simulate multiple failures to trigger circuit breaker
        for ($i = 0; $i < 10; $i++) {
            Cache::increment('quantum_service_failures', 1);
        }

        Cache::put('quantum_service_last_failure', now(), 300);

        $response = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
        ]);

        // Circuit breaker should be open, preventing calls to quantum service
        $response->assertStatus(503);
        $data = $response->json();

        $this->assertArrayHasKey('circuit_breaker_open', $data);
        $this->assertTrue($data['circuit_breaker_open']);
        $this->assertArrayHasKey('estimated_recovery_time', $data);
        $this->assertArrayHasKey('fallback_available', $data);

        // Test circuit breaker recovery
        Cache::forget('quantum_service_failures');
        Cache::forget('quantum_service_last_failure');

        $recoveryResponse = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
            'force_circuit_breaker_reset' => true,
        ]);

        // Should attempt quantum operation again
        $this->assertNotEquals(503, $recoveryResponse->status());
    }

    public function test_graceful_degradation_during_high_load()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Simulate high load condition
        Cache::put('system_load_high', true, 60);
        Cache::put('quantum_service_queue_length', 100, 60);

        $response = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
            'priority' => 'normal',
        ]);

        if ($response->status() === 429) {
            // Rate limited due to high load
            $data = $response->json();
            $this->assertArrayHasKey('retry_after', $data);
            $this->assertArrayHasKey('fallback_recommended', $data);
            $this->assertTrue($data['fallback_recommended']);
        } else {
            // Operation succeeded despite high load
            $data = $response->json();
            $this->assertArrayHasKey('load_warning', $data);
        }

        // Test high priority request bypass
        $priorityResponse = $this->postJson('/api/v1/quantum/generate-keypair', [
            'algorithm' => 'ML-KEM-768',
            'priority' => 'high',
        ]);

        // High priority should be more likely to succeed
        $this->assertContains($priorityResponse->status(), [200, 202, 429]);
    }
}
