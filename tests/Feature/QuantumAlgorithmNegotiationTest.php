<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Chat\Conversation;
use App\Models\Chat\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

class QuantumAlgorithmNegotiationTest extends TestCase
{
    use RefreshDatabase;
    
    private ChatEncryptionService $encryptionService;
    private MultiDeviceEncryptionService $multiDeviceService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->encryptionService = app(ChatEncryptionService::class);
        $this->multiDeviceService = app(MultiDeviceEncryptionService::class);
    }
    
    public function test_all_quantum_devices_negotiation()
    {
        $deviceCapabilities = [
            ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512'],
            ['ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'],
            ['ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should pick the highest common algorithm
        $this->assertEquals('ML-KEM-768', $algorithm);
    }
    
    public function test_mixed_quantum_classical_negotiation()
    {
        $deviceCapabilities = [
            ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP'],
            ['RSA-4096-OAEP', 'HYBRID-RSA4096-MLKEM768'],
            ['ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should pick hybrid mode for compatibility
        $this->assertEquals('HYBRID-RSA4096-MLKEM768', $algorithm);
    }
    
    public function test_all_legacy_devices_negotiation()
    {
        $deviceCapabilities = [
            ['RSA-4096-OAEP', 'RSA-2048-OAEP'],
            ['RSA-4096-OAEP'],
            ['RSA-4096-OAEP', 'RSA-2048-OAEP']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should fallback to RSA
        $this->assertEquals('RSA-4096-OAEP', $algorithm);
    }
    
    public function test_single_device_negotiation()
    {
        $deviceCapabilities = [
            ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should pick the highest security level available
        $this->assertEquals('ML-KEM-1024', $algorithm);
    }
    
    public function test_no_common_algorithms_negotiation()
    {
        $deviceCapabilities = [
            ['ML-KEM-1024'],
            ['ML-KEM-512'],
            ['RSA-4096-OAEP']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should fallback to most compatible algorithm
        $this->assertContains($algorithm, ['RSA-4096-OAEP', 'HYBRID-RSA4096-MLKEM768']);
    }
    
    public function test_empty_capabilities_negotiation()
    {
        $deviceCapabilities = [
            [],
            ['ML-KEM-768'],
            []
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should fallback to default safe algorithm
        $this->assertEquals('RSA-4096-OAEP', $algorithm);
    }
    
    public function test_unknown_algorithms_filtered_out()
    {
        $deviceCapabilities = [
            ['UNKNOWN-ALGORITHM', 'ML-KEM-768', 'INVALID-ALG'],
            ['ML-KEM-768', 'ANOTHER-UNKNOWN', 'RSA-4096-OAEP'],
            ['FAKE-ALG', 'ML-KEM-768']
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should ignore unknown algorithms and negotiate with known ones
        $this->assertEquals('ML-KEM-768', $algorithm);
    }
    
    public function test_priority_based_negotiation()
    {
        // Test that algorithm priority is respected
        $deviceCapabilities = [
            ['RSA-4096-OAEP', 'ML-KEM-768', 'ML-KEM-512'], // Lower priority first
            ['ML-KEM-512', 'RSA-4096-OAEP', 'ML-KEM-768'], // Mixed order
            ['ML-KEM-768', 'ML-KEM-512', 'RSA-4096-OAEP']  // Quantum first
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should pick highest priority quantum algorithm
        $this->assertEquals('ML-KEM-768', $algorithm);
    }
    
    public function test_conversation_specific_negotiation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        // Create devices with different capabilities
        $devices = [
            $this->multiDeviceService->registerQuantumDevice(
                $user1, 'User1 Quantum', 'desktop', 'key1', 'fp1', ['ml-kem-1024', 'ml-kem-768']
            ),
            $this->multiDeviceService->registerQuantumDevice(
                $user2, 'User2 Hybrid', 'mobile', 'key2', 'fp2', ['ml-kem-768', 'hybrid']
            ),
            UserDevice::factory()->create([
                'user_id' => $user3->id,
                'device_name' => 'User3 Legacy',
                'encryption_version' => 2,
                'device_capabilities' => ['rsa-4096']
            ])
        ];
        
        $conversation = Conversation::factory()->create();
        
        // Add participants
        foreach ([$user1, $user2, $user3] as $user) {
            Participant::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'joined_at' => now()
            ]);
        }
        
        $this->actingAs($user1, 'api');
        
        $response = $this->postJson("/api/v1/quantum/conversations/{$conversation->id}/negotiate-algorithm");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'algorithm',
            'algorithm_info',
            'quantum_resistant',
            'participants',
            'compatible_devices'
        ]);
        
        $data = $response->json();
        
        // Should negotiate hybrid mode due to legacy device
        $this->assertContains($data['algorithm'], ['HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']);
        $this->assertEquals(3, count($data['participants']));
        $this->assertEquals(3, count($data['compatible_devices']));
    }
    
    public function test_algorithm_compatibility_matrix()
    {
        $testCases = [
            // Perfect quantum compatibility
            [
                'devices' => [
                    ['ML-KEM-768', 'ML-KEM-512'],
                    ['ML-KEM-768', 'ML-KEM-1024'],
                    ['ML-KEM-768']
                ],
                'expected' => 'ML-KEM-768',
                'quantum_resistant' => true
            ],
            
            // Hybrid compatibility
            [
                'devices' => [
                    ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768'],
                    ['HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP'],
                    ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']
                ],
                'expected' => 'HYBRID-RSA4096-MLKEM768',
                'quantum_resistant' => true
            ],
            
            // Legacy fallback
            [
                'devices' => [
                    ['RSA-4096-OAEP'],
                    ['RSA-4096-OAEP', 'RSA-2048-OAEP'],
                    ['RSA-4096-OAEP']
                ],
                'expected' => 'RSA-4096-OAEP',
                'quantum_resistant' => false
            ],
            
            // Security level downgrade
            [
                'devices' => [
                    ['ML-KEM-1024', 'ML-KEM-768'],
                    ['ML-KEM-512', 'ML-KEM-768'],
                    ['ML-KEM-768', 'ML-KEM-1024']
                ],
                'expected' => 'ML-KEM-768',
                'quantum_resistant' => true
            ]
        ];
        
        foreach ($testCases as $index => $testCase) {
            $algorithm = $this->encryptionService->negotiateAlgorithm($testCase['devices']);
            
            $this->assertEquals(
                $testCase['expected'],
                $algorithm,
                "Test case {$index}: Expected {$testCase['expected']}, got {$algorithm}"
            );
            
            $isQuantumResistant = $this->encryptionService->isQuantumResistant($algorithm);
            $this->assertEquals(
                $testCase['quantum_resistant'],
                $isQuantumResistant,
                "Test case {$index}: Quantum resistance mismatch for {$algorithm}"
            );
        }
    }
    
    public function test_algorithm_negotiation_with_preferences()
    {
        // Test with user/admin preferences for algorithm selection
        $deviceCapabilities = [
            ['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512'],
            ['ML-KEM-768', 'ML-KEM-512'],
            ['ML-KEM-768', 'ML-KEM-1024']
        ];
        
        // Test without preferences (should pick highest common)
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        $this->assertEquals('ML-KEM-768', $algorithm);
        
        // Test with preference for ML-KEM-512 (performance over security)
        $algorithmWithPreference = $this->encryptionService->negotiateAlgorithm(
            $deviceCapabilities,
            ['preferred_algorithm' => 'ML-KEM-512']
        );
        $this->assertEquals('ML-KEM-512', $algorithmWithPreference);
        
        // Test with preference for unavailable algorithm (should ignore preference)
        $algorithmUnavailable = $this->encryptionService->negotiateAlgorithm(
            $deviceCapabilities,
            ['preferred_algorithm' => 'ML-KEM-2048'] // Doesn't exist
        );
        $this->assertEquals('ML-KEM-768', $algorithmUnavailable);
    }
    
    public function test_negotiation_with_device_constraints()
    {
        $user = User::factory()->create();
        
        // Create devices with specific constraints
        $lowPowerDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'IoT Device',
            'device_type' => 'iot',
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-512'], // Only supports fastest algorithm
            'device_constraints' => ['low_power', 'limited_memory']
        ]);
        
        $highSecurityDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Security Server',
            'device_type' => 'server',
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-1024', 'ml-kem-768', 'ml-kem-512'],
            'device_constraints' => ['high_security_required']
        ]);
        
        $standardDevice = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => 'Standard Device',
            'device_type' => 'desktop',
            'encryption_version' => 3,
            'device_capabilities' => ['ml-kem-768', 'ml-kem-512', 'hybrid']
        ]);
        
        $conversation = Conversation::factory()->create();
        
        $this->actingAs($user, 'api');
        
        $response = $this->postJson("/api/v1/quantum/conversations/{$conversation->id}/negotiate-algorithm", [
            'consider_constraints' => true,
            'participants' => [$user->id]
        ]);
        
        $response->assertStatus(200);
        $data = $response->json();
        
        // Should accommodate the lowest common denominator (IoT device)
        $this->assertEquals('ML-KEM-512', $data['algorithm']);
        
        // Should provide constraint information
        $this->assertArrayHasKey('constraints_considered', $data);
        $this->assertContains('low_power', $data['constraints_considered']);
    }
    
    public function test_negotiation_performance_impact()
    {
        // Create large number of devices to test performance
        $deviceCapabilities = [];
        
        for ($i = 0; $i < 100; $i++) {
            $capabilities = [];
            
            // Randomly assign capabilities
            if (rand(0, 1)) $capabilities[] = 'ML-KEM-1024';
            if (rand(0, 1)) $capabilities[] = 'ML-KEM-768';
            if (rand(0, 1)) $capabilities[] = 'ML-KEM-512';
            if (rand(0, 1)) $capabilities[] = 'HYBRID-RSA4096-MLKEM768';
            if (rand(0, 1)) $capabilities[] = 'RSA-4096-OAEP';
            
            if (empty($capabilities)) {
                $capabilities = ['RSA-4096-OAEP']; // Ensure at least one capability
            }
            
            $deviceCapabilities[] = $capabilities;
        }
        
        $startTime = microtime(true);
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        $endTime = microtime(true);
        $negotiationTime = $endTime - $startTime;
        
        // Should complete negotiation within reasonable time (< 1 second for 100 devices)
        $this->assertLessThan(1.0, $negotiationTime, 
            "Algorithm negotiation took too long: {$negotiationTime} seconds");
        
        // Should still produce a valid algorithm
        $validAlgorithms = [
            'ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512', 
            'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP'
        ];
        $this->assertContains($algorithm, $validAlgorithms);
        
        // Log performance metrics
        \Log::info('Algorithm Negotiation Performance Test', [
            'device_count' => 100,
            'negotiation_time_ms' => $negotiationTime * 1000,
            'selected_algorithm' => $algorithm
        ]);
    }
    
    public function test_negotiation_with_version_compatibility()
    {
        $deviceCapabilities = [
            ['ML-KEM-768:v3', 'ML-KEM-512:v3'], // Version 3 support
            ['ML-KEM-768:v2', 'RSA-4096-OAEP:v2'], // Older version
            ['ML-KEM-768:v3', 'HYBRID-RSA4096-MLKEM768:v3'] // Latest version
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($deviceCapabilities);
        
        // Should find compatible version across devices
        $this->assertContains($algorithm, ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']);
    }
    
    public function test_negotiation_error_handling()
    {
        // Test with malformed device capabilities
        $malformedCapabilities = [
            null,
            ['ML-KEM-768'],
            'not_an_array',
            []
        ];
        
        $algorithm = $this->encryptionService->negotiateAlgorithm($malformedCapabilities);
        
        // Should handle errors gracefully and return safe default
        $this->assertEquals('RSA-4096-OAEP', $algorithm);
        
        // Test with invalid algorithm names
        $invalidCapabilities = [
            ['ML-KEM-999', 'INVALID-ALG'],
            ['FAKE-QUANTUM-ALG'],
            ['']
        ];
        
        $algorithmInvalid = $this->encryptionService->negotiateAlgorithm($invalidCapabilities);
        $this->assertEquals('RSA-4096-OAEP', $algorithmInvalid);
    }
}