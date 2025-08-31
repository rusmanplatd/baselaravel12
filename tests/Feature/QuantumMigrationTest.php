<?php

namespace Tests\Feature;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\QuantumCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QuantumMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if quantum provider is not available in test environment
        $quantumService = app(QuantumCryptoService::class);
        if (! $quantumService->isMLKEMAvailable()) {
            $this->markTestSkipped('ML-KEM provider not available in test environment');
        }
    }

    public function test_migration_readiness_assessment()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create mixed environment with quantum and legacy devices
        $quantumDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Quantum Device',
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'hybrid'],
        ]);

        $legacyDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Legacy Device',
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        // Create conversations with different encryption levels
        $rsaConversation = Conversation::factory()->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        $quantumConversation = Conversation::factory()->create([
            'encryption_algorithm' => 'ML-KEM-768',
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/assess');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_conversations',
            'total_messages',
            'quantum_ready_devices',
            'total_devices',
            'compatibility_issues',
            'recommended_strategy',
            'estimated_duration',
            'risk_level',
        ]);

        $data = $response->json();
        $this->assertEquals(2, $data['total_devices']);
        $this->assertEquals(1, $data['quantum_ready_devices']);
        $this->assertContains($data['recommended_strategy'], ['immediate', 'gradual', 'hybrid', 'delayed']);
        $this->assertContains($data['risk_level'], ['low', 'medium', 'high']);
    }

    public function test_immediate_migration_strategy()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create quantum-ready environment
        $device1 = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        $device2 = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        // Create conversations to migrate
        $conversations = Conversation::factory()->count(3)->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'immediate',
            'target_algorithm' => 'ML-KEM-768',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'started']);

        $migrationId = $response->json()['migration_id'];

        // Check migration status
        $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
        $statusResponse->assertStatus(200);

        $statusData = $statusResponse->json();
        $this->assertContains($statusData['status'], ['in_progress', 'completed']);
        $this->assertEquals('immediate', $statusData['strategy']);
    }

    public function test_gradual_migration_strategy()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create mixed environment
        $quantumDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'hybrid'],
        ]);

        $legacyDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 2,
            'device_capabilities' => ['rsa-4096'],
        ]);

        // Create many conversations to test batching
        $conversations = Conversation::factory()->count(10)->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'gradual',
            'batch_size' => 3,
            'target_algorithm' => 'HYBRID-RSA4096-MLKEM768',
        ]);

        $response->assertStatus(200);
        $migrationId = $response->json()['migration_id'];

        // Monitor migration progress
        $attempts = 0;
        $maxAttempts = 10;

        do {
            sleep(1);
            $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
            $statusData = $statusResponse->json();
            $attempts++;
        } while ($statusData['status'] === 'in_progress' && $attempts < $maxAttempts);

        $this->assertLessThan($maxAttempts, $attempts, 'Migration took too long to complete');
        $this->assertContains($statusData['status'], ['completed', 'failed']);

        if ($statusData['status'] === 'completed') {
            $this->assertGreaterThan(0, $statusData['results']['conversations_migrated']);
        }
    }

    public function test_hybrid_migration_strategy()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create mixed device capabilities
        $devices = [
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-768'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'encryption_version' => 2,
                'device_capabilities' => ['rsa-4096'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-768', 'hybrid', 'rsa-4096'],
            ]),
        ];

        $conversations = Conversation::factory()->count(5)->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'hybrid',
        ]);

        $response->assertStatus(200);
        $migrationId = $response->json()['migration_id'];

        // Wait for completion
        $attempts = 0;
        do {
            sleep(1);
            $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
            $statusData = $statusResponse->json();
            $attempts++;
        } while ($statusData['status'] === 'in_progress' && $attempts < 15);

        // Verify hybrid algorithm usage
        if ($statusData['status'] === 'completed') {
            $this->assertArrayHasKey('HYBRID-RSA4096-MLKEM768', $statusData['results']['algorithms_upgraded']);
        }
    }

    public function test_migration_with_encryption_key_rotation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $conversation = Conversation::factory()->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        // Create existing encryption keys
        $oldKey = EncryptionKey::factory()->create([
            'conversation_id' => $conversation->id,
            'algorithm' => 'RSA-4096-OAEP',
            'key_version' => 1,
            'is_active' => true,
        ]);

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'immediate',
            'rotate_keys' => true,
        ]);

        $response->assertStatus(200);
        $migrationId = $response->json()['migration_id'];

        // Wait for completion
        $attempts = 0;
        do {
            sleep(1);
            $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
            $statusData = $statusResponse->json();
            $attempts++;
        } while ($statusData['status'] === 'in_progress' && $attempts < 10);

        // Verify key rotation occurred
        $oldKey->refresh();
        $this->assertFalse($oldKey->is_active);

        $newKeys = EncryptionKey::where('conversation_id', $conversation->id)
            ->where('is_active', true)
            ->get();

        $this->assertGreaterThan(0, $newKeys->count());

        $newKey = $newKeys->first();
        $this->assertNotEquals('RSA-4096-OAEP', $newKey->algorithm);
        $this->assertGreaterThan(1, $newKey->key_version);
    }

    public function test_migration_rollback()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        $conversation = Conversation::factory()->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        // Start migration
        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'immediate',
        ]);

        $migrationId = $response->json()['migration_id'];

        // Cancel migration before completion
        $cancelResponse = $this->postJson("/api/v1/quantum/migration/{$migrationId}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJson(['status' => 'cancelled']);

        // Verify migration was cancelled
        $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
        $statusData = $statusResponse->json();

        $this->assertEquals('cancelled', $statusData['status']);
        $this->assertArrayHasKey('errors', $statusData['results']);
        $this->assertContains('Test cancellation', array_column($statusData['results']['errors'], 'message'));
    }

    public function test_migration_error_handling()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create device with incompatible capabilities
        $incompatibleDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 1, // Very old version
            'device_capabilities' => ['old-encryption'],
        ]);

        $conversation = Conversation::factory()->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        // Attempt migration that should fail
        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'immediate',
            'target_algorithm' => 'ML-KEM-1024', // Requires high capability
        ]);

        if ($response->status() === 200) {
            $migrationId = $response->json()['migration_id'];

            // Wait for failure
            $attempts = 0;
            do {
                sleep(1);
                $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
                $statusData = $statusResponse->json();
                $attempts++;
            } while ($statusData['status'] === 'in_progress' && $attempts < 10);

            // Verify error handling
            if ($statusData['status'] === 'failed') {
                $this->assertArrayHasKey('errors', $statusData['results']);
                $this->assertGreaterThan(0, count($statusData['results']['errors']));
            }
        } else {
            // Migration was rejected upfront due to compatibility issues
            $response->assertStatus(422);
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_migration_compatibility_checks()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Create devices with different capability levels
        $devices = [
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'High Security Device',
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-1024', 'ml-kem-768', 'hybrid'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Standard Device',
                'encryption_version' => 3,
                'device_capabilities' => ['ml-kem-768', 'hybrid'],
            ]),
            UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => 'Basic Device',
                'encryption_version' => 2,
                'device_capabilities' => ['rsa-4096'],
            ]),
        ];

        // Test compatibility for different target algorithms
        $algorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'];

        foreach ($algorithms as $algorithm) {
            $response = $this->postJson('/api/v1/quantum/migration/check-compatibility', [
                'target_algorithm' => $algorithm,
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'compatible',
                'compatibility_percentage',
                'incompatible_devices',
                'recommended_actions',
            ]);

            $data = $response->json();
            $this->assertIsBool($data['compatible']);
            $this->assertIsFloat($data['compatibility_percentage']);
            $this->assertIsArray($data['incompatible_devices']);
        }
    }

    public function test_migration_progress_monitoring()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        $conversations = Conversation::factory()->count(5)->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'gradual',
            'batch_size' => 2,
        ]);

        $migrationId = $response->json()['migration_id'];

        // Monitor progress updates
        $previousProgress = -1;
        $progressUpdates = [];

        for ($i = 0; $i < 10; $i++) {
            usleep(500000); // 0.5 seconds

            $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
            $statusData = $statusResponse->json();

            $currentProgress = $statusData['progress']['progress'];

            if ($currentProgress !== $previousProgress) {
                $progressUpdates[] = [
                    'progress' => $currentProgress,
                    'phase' => $statusData['progress']['phase'],
                    'step' => $statusData['progress']['current_step'],
                    'description' => $statusData['progress']['step_description'],
                ];
                $previousProgress = $currentProgress;
            }

            if ($statusData['status'] !== 'in_progress') {
                break;
            }
        }

        // Verify we received progress updates
        $this->assertGreaterThan(0, count($progressUpdates));

        // Verify progress is monotonically increasing
        for ($i = 1; $i < count($progressUpdates); $i++) {
            $this->assertGreaterThanOrEqual(
                $progressUpdates[$i - 1]['progress'],
                $progressUpdates[$i]['progress']
            );
        }
    }

    public function test_migration_performance_metrics()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768'],
        ]);

        // Create conversations with messages
        $conversations = Conversation::factory()->count(3)->create([
            'encryption_algorithm' => 'RSA-4096-OAEP',
        ]);

        foreach ($conversations as $conversation) {
            Message::factory()->count(5)->create([
                'conversation_id' => $conversation->id,
            ]);
        }

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/quantum/migration/start', [
            'strategy' => 'immediate',
            'measure_performance' => true,
        ]);

        $migrationId = $response->json()['migration_id'];

        // Wait for completion
        $attempts = 0;
        do {
            sleep(1);
            $statusResponse = $this->getJson("/api/v1/quantum/migration/{$migrationId}/status");
            $statusData = $statusResponse->json();
            $attempts++;
        } while ($statusData['status'] === 'in_progress' && $attempts < 15);

        $totalTime = microtime(true) - $startTime;

        if ($statusData['status'] === 'completed') {
            // Verify performance metrics
            $results = $statusData['results'];
            $this->assertArrayHasKey('conversations_migrated', $results);
            $this->assertGreaterThan(0, $results['conversations_migrated']);

            // Calculate migration rate
            $migrationRate = $results['conversations_migrated'] / $totalTime;
            $this->assertGreaterThan(0, $migrationRate);

            // Log performance metrics
            Log::info('Migration Performance Test Results', [
                'conversations_migrated' => $results['conversations_migrated'],
                'total_time_seconds' => $totalTime,
                'migration_rate_per_second' => $migrationRate,
                'devices_upgraded' => $results['devicesUpgraded'] ?? 0,
            ]);
        }
    }
}
