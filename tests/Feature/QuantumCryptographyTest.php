<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\QuantumCryptoService;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Chat\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class QuantumCryptographyTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if quantum provider is not available in test environment
        $quantumService = app(QuantumCryptoService::class);
        if (!$quantumService->isMLKEMAvailable()) {
            $this->markTestSkipped('ML-KEM provider not available in test environment');
        }
    }
    
    public function test_quantum_health_check_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $response = $this->getJson('/api/v1/quantum/health');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'quantum_support' => [
                'ml_kem_available',
                'supported_algorithms'
            ],
            'system_info',
            'algorithms'
        ]);
        
        $data = $response->json();
        $this->assertContains($data['status'], ['healthy', 'degraded', 'unhealthy']);
    }
    
    public function test_ml_kem_key_generation()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        foreach ([512, 768, 1024] as $securityLevel) {
            $keyPair = $quantumService->generateMLKEMKeyPair($securityLevel);
            
            $this->assertArrayHasKey('public_key', $keyPair);
            $this->assertArrayHasKey('private_key', $keyPair);
            $this->assertEquals("ML-KEM-{$securityLevel}", $keyPair['algorithm']);
            $this->assertEquals($securityLevel, $keyPair['key_strength']);
            
            // Validate key format
            $this->assertNotEmpty($keyPair['public_key']);
            $this->assertNotEmpty($keyPair['private_key']);
        }
    }
    
    public function test_hybrid_key_generation()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        $keyPair = $quantumService->generateHybridKeyPair();
        
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertEquals('HYBRID-RSA4096-MLKEM768', $keyPair['algorithm']);
        $this->assertArrayHasKey('components', $keyPair);
        
        $components = $keyPair['components'];
        $this->assertArrayHasKey('rsa', $components);
        $this->assertArrayHasKey('ml-kem', $components);
    }
    
    public function test_ml_kem_encapsulation_decapsulation()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        foreach ([512, 768, 1024] as $securityLevel) {
            // Generate key pair
            $keyPair = $quantumService->generateMLKEMKeyPair($securityLevel);
            
            // Encapsulate
            $encapResult = $quantumService->encapsulateMLKEM($keyPair['public_key'], $securityLevel);
            
            $this->assertArrayHasKey('ciphertext', $encapResult);
            $this->assertArrayHasKey('shared_secret', $encapResult);
            $this->assertEquals(32, strlen($encapResult['shared_secret'])); // ML-KEM always produces 32-byte secrets
            
            // Decapsulate
            $decapSecret = $quantumService->decapsulateMLKEM(
                $encapResult['ciphertext'],
                $keyPair['private_key'],
                $securityLevel
            );
            
            $this->assertEquals($encapResult['shared_secret'], $decapSecret);
        }
    }
    
    public function test_hybrid_encapsulation_decapsulation()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        // Generate hybrid key pair
        $keyPair = $quantumService->generateHybridKeyPair();
        
        // Encapsulate
        $encapResult = $quantumService->encapsulateHybrid($keyPair['public_key']);
        
        $this->assertArrayHasKey('ciphertext', $encapResult);
        $this->assertArrayHasKey('shared_secret', $encapResult);
        $this->assertEquals('HYBRID-RSA4096-MLKEM768', $encapResult['algorithm']);
        $this->assertEquals(32, strlen($encapResult['shared_secret'])); // Combined secret is 32 bytes
        
        // Decapsulate
        $decapSecret = $quantumService->decapsulateHybrid(
            $encapResult['ciphertext'],
            $keyPair['private_key']
        );
        
        $this->assertEquals($encapResult['shared_secret'], $decapSecret);
    }
    
    public function test_algorithm_negotiation()
    {
        $encryptionService = app(ChatEncryptionService::class);
        
        // Test all quantum devices
        $deviceCapabilities = [
            ['ML-KEM-768', 'ML-KEM-512'],
            ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768'],
            ['ML-KEM-768', 'ML-KEM-1024']
        ];
        
        $algorithm = $encryptionService->negotiateAlgorithm($deviceCapabilities);
        $this->assertEquals('ML-KEM-768', $algorithm);
        
        // Test mixed environment
        $mixedCapabilities = [
            ['ML-KEM-768', 'RSA-4096-OAEP'],
            ['RSA-4096-OAEP', 'HYBRID-RSA4096-MLKEM768'],
            ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768']
        ];
        
        $algorithm = $encryptionService->negotiateAlgorithm($mixedCapabilities);
        $this->assertContains($algorithm, ['HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP']);
        
        // Test legacy only
        $legacyCapabilities = [
            ['RSA-4096-OAEP'],
            ['RSA-4096-OAEP'],
            ['RSA-4096-OAEP']
        ];
        
        $algorithm = $encryptionService->negotiateAlgorithm($legacyCapabilities);
        $this->assertEquals('RSA-4096-OAEP', $algorithm);
    }
    
    public function test_quantum_device_registration()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $response = $this->postJson('/api/v1/quantum/devices/register', [
            'device_name' => 'Test Quantum Device',
            'device_type' => 'desktop',
            'public_key' => base64_encode('fake-quantum-public-key'),
            'device_fingerprint' => hash('sha256', 'test-fingerprint'),
            'quantum_capabilities' => ['ml-kem-768', 'hybrid']
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['quantum_ready' => true]);
        
        $data = $response->json();
        $this->assertEquals(3, $data['device']['encryption_version']);
        $this->assertTrue($data['device']['quantum_ready']);
        $this->assertContains('ML-KEM-768', $data['device']['supported_algorithms']);
    }
    
    public function test_quantum_key_generation_api()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $algorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'];
        
        foreach ($algorithms as $algorithm) {
            $response = $this->postJson('/api/v1/quantum/generate-keypair', [
                'algorithm' => $algorithm
            ]);
            
            $response->assertStatus(200);
            $response->assertJsonStructure([
                'public_key',
                'private_key',
                'algorithm',
                'key_strength',
                'quantum_resistant'
            ]);
            
            $data = $response->json();
            $this->assertEquals($algorithm, $data['algorithm']);
            $this->assertTrue($data['quantum_resistant']);
        }
    }
    
    public function test_device_capabilities_api()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        // Create a quantum-capable device
        $multiDeviceService = app(MultiDeviceEncryptionService::class);
        $device = $multiDeviceService->registerQuantumDevice(
            $user,
            'Test Device',
            'desktop',
            base64_encode('test-public-key'),
            hash('sha256', 'test-fingerprint'),
            ['ml-kem-768', 'hybrid']
        );
        
        $response = $this->getJson('/api/v1/quantum/devices/capabilities');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'devices',
            'summary' => [
                'total_devices',
                'quantum_ready_devices',
                'quantum_ready_percentage',
                'recommended_algorithm'
            ]
        ]);
        
        $data = $response->json();
        $this->assertEquals(1, $data['summary']['total_devices']);
        $this->assertEquals(1, $data['summary']['quantum_ready_devices']);
        $this->assertEquals(100.0, $data['summary']['quantum_ready_percentage']);
    }
    
    public function test_conversation_algorithm_negotiation_api()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $conversation = Conversation::factory()->create();
        
        // Add user as participant
        $conversation->participants()->create([
            'user_id' => $user->id,
            'joined_at' => now()
        ]);
        
        // Create quantum device for user
        $multiDeviceService = app(MultiDeviceEncryptionService::class);
        $multiDeviceService->registerQuantumDevice(
            $user,
            'Test Device',
            'desktop',
            base64_encode('test-public-key'),
            hash('sha256', 'test-fingerprint'),
            ['ml-kem-768']
        );
        
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
        $this->assertTrue($data['quantum_resistant']);
        $this->assertContains($data['algorithm'], ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768']);
    }
    
    public function test_quantum_key_validation()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        // Test ML-KEM key validation
        $keyPair = $quantumService->generateMLKEMKeyPair(768);
        
        $isValid = $quantumService->validateQuantumKeyPair(
            $keyPair['public_key'],
            $keyPair['private_key'],
            'ML-KEM-768'
        );
        
        $this->assertTrue($isValid);
        
        // Test with invalid private key
        $invalidKeyPair = $quantumService->generateMLKEMKeyPair(768);
        
        $isInvalid = $quantumService->validateQuantumKeyPair(
            $keyPair['public_key'],
            $invalidKeyPair['private_key'], // Mismatched private key
            'ML-KEM-768'
        );
        
        $this->assertFalse($isInvalid);
    }
    
    public function test_device_quantum_capabilities_management()
    {
        $user = User::factory()->create();
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_capabilities' => ['messaging', 'encryption']
        ]);
        
        // Test quantum support detection
        $this->assertFalse($device->supportsQuantumResistant());
        $this->assertFalse($device->isQuantumReady());
        
        // Update capabilities
        $device->updateQuantumCapabilities(['ml-kem-768', 'hybrid']);
        $device->refresh();
        
        $this->assertTrue($device->supportsQuantumResistant());
        $this->assertTrue($device->isQuantumReady());
        $this->assertEquals(3, $device->encryption_version);
        $this->assertContains('ML-KEM-768', $device->getSupportedAlgorithms());
    }
    
    public function test_quantum_encryption_integration()
    {
        $encryptionService = app(ChatEncryptionService::class);
        
        // Test ML-KEM integration
        $keyPair = $encryptionService->generateKeyPair(null, 'ML-KEM-768');
        
        $this->assertEquals('ML-KEM-768', $keyPair['algorithm']);
        $this->assertEquals(768, $keyPair['key_strength']);
        
        // Test algorithm information
        $algorithmInfo = $encryptionService->getAlgorithmInfo('ML-KEM-768');
        $this->assertTrue($algorithmInfo['quantum_resistant']);
        $this->assertEquals('ml-kem', $algorithmInfo['type']);
        $this->assertEquals(3, $algorithmInfo['version']);
        
        // Test quantum resistance check
        $this->assertTrue($encryptionService->isQuantumResistant('ML-KEM-768'));
        $this->assertTrue($encryptionService->isQuantumResistant('HYBRID-RSA4096-MLKEM768'));
        $this->assertFalse($encryptionService->isQuantumResistant('RSA-4096-OAEP'));
    }
    
    public function test_quantum_conversation_setup()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $multiDeviceService = app(MultiDeviceEncryptionService::class);
        
        // Create quantum devices for both users
        $device1 = $multiDeviceService->registerQuantumDevice(
            $user1,
            'User 1 Device',
            'desktop',
            base64_encode('user1-public-key'),
            hash('sha256', 'user1-fingerprint'),
            ['ml-kem-768']
        );
        
        $device2 = $multiDeviceService->registerQuantumDevice(
            $user2,
            'User 2 Device',
            'mobile',
            base64_encode('user2-public-key'),
            hash('sha256', 'user2-fingerprint'),
            ['ml-kem-768', 'hybrid']
        );
        
        $conversation = Conversation::factory()->create();
        
        // Setup quantum encryption for conversation
        $result = $multiDeviceService->setupQuantumConversationEncryption(
            $conversation,
            [$device1, $device2],
            $device1
        );
        
        $this->assertArrayHasKey('algorithm', $result);
        $this->assertArrayHasKey('created_keys', $result);
        $this->assertEquals('ML-KEM-768', $result['algorithm']);
        $this->assertEquals(2, count($result['created_keys']));
        $this->assertEquals(0, count($result['failed_keys']));
    }
    
    public function test_rate_limiting_on_quantum_endpoints()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        // Test key generation rate limit (10 per minute)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/v1/quantum/generate-keypair', [
                'algorithm' => 'ML-KEM-768'
            ]);
            
            if ($i < 10) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
    
    public function test_error_handling_for_invalid_algorithms()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        $this->expectException(\InvalidArgumentException::class);
        $quantumService->generateMLKEMKeyPair(999); // Invalid security level
    }
    
    public function test_backward_compatibility_with_rsa()
    {
        $encryptionService = app(ChatEncryptionService::class);
        
        // Generate RSA key pair (default behavior)
        $rsaKeyPair = $encryptionService->generateKeyPair();
        
        $this->assertArrayHasKey('public_key', $rsaKeyPair);
        $this->assertArrayHasKey('private_key', $rsaKeyPair);
        
        // Test symmetric key encryption/decryption with RSA
        $symmetricKey = $encryptionService->generateSymmetricKey();
        $encryptedKey = $encryptionService->encryptSymmetricKey($symmetricKey, $rsaKeyPair['public_key']);
        $decryptedKey = $encryptionService->decryptSymmetricKey($encryptedKey, $rsaKeyPair['private_key']);
        
        $this->assertEquals($symmetricKey, $decryptedKey);
        
        // Test algorithm-specific encryption
        $encryptedKeyViaAlgorithm = $encryptionService->encryptSymmetricKeyWithAlgorithm(
            $symmetricKey,
            $rsaKeyPair['public_key'],
            'RSA-4096-OAEP'
        );
        
        $decryptedKeyViaAlgorithm = $encryptionService->decryptSymmetricKeyWithAlgorithm(
            $encryptedKeyViaAlgorithm,
            $rsaKeyPair['private_key'],
            'RSA-4096-OAEP'
        );
        
        $this->assertEquals($symmetricKey, $decryptedKeyViaAlgorithm);
    }
}