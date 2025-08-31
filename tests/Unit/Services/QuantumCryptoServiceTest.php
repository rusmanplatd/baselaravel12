<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\QuantumCryptoService;
use App\Services\ChatEncryptionService;
use App\Services\Crypto\FallbackMLKEMProvider;
use App\Exceptions\EncryptionException;
use App\Exceptions\DecryptionException;
use Mockery;

class QuantumCryptoServiceTest extends TestCase
{
    private QuantumCryptoService $quantumService;
    private ChatEncryptionService $chatEncryptionService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->chatEncryptionService = Mockery::mock(ChatEncryptionService::class);
        $this->quantumService = new QuantumCryptoService($this->chatEncryptionService);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_supported_algorithms_constants()
    {
        $algorithms = $this->quantumService->getSupportedAlgorithms();
        
        $expectedAlgorithms = [
            'ML-KEM-512',
            'ML-KEM-768',
            'ML-KEM-1024',
            'HYBRID-RSA4096-MLKEM768'
        ];
        
        foreach ($expectedAlgorithms as $algorithm) {
            $this->assertContains($algorithm, $algorithms);
        }
    }
    
    public function test_ml_kem_security_level_support()
    {
        $this->assertTrue($this->quantumService->isMLKEMSupported(512));
        $this->assertTrue($this->quantumService->isMLKEMSupported(768));
        $this->assertTrue($this->quantumService->isMLKEMSupported(1024));
        $this->assertFalse($this->quantumService->isMLKEMSupported(256));
        $this->assertFalse($this->quantumService->isMLKEMSupported(999));
    }
    
    public function test_quantum_resistance_check()
    {
        // Quantum-resistant algorithms
        $this->assertTrue($this->quantumService->isQuantumResistant('ML-KEM-512'));
        $this->assertTrue($this->quantumService->isQuantumResistant('ML-KEM-768'));
        $this->assertTrue($this->quantumService->isQuantumResistant('ML-KEM-1024'));
        $this->assertTrue($this->quantumService->isQuantumResistant('HYBRID-RSA4096-MLKEM768'));
        
        // Non-quantum-resistant algorithms
        $this->assertFalse($this->quantumService->isQuantumResistant('RSA-4096-OAEP'));
        $this->assertFalse($this->quantumService->isQuantumResistant('unknown-algorithm'));
    }
    
    public function test_algorithm_info_retrieval()
    {
        $info = $this->quantumService->getAlgorithmInfo('ML-KEM-768');
        
        $this->assertIsArray($info);
        $this->assertEquals('ml-kem', $info['type']);
        $this->assertEquals(768, $info['security_level']);
        $this->assertTrue($info['quantum_resistant']);
        $this->assertEquals(3, $info['version']);
    }
    
    public function test_hybrid_algorithm_info()
    {
        $info = $this->quantumService->getAlgorithmInfo('HYBRID-RSA4096-MLKEM768');
        
        $this->assertIsArray($info);
        $this->assertEquals('hybrid', $info['type']);
        $this->assertTrue($info['quantum_resistant']);
        $this->assertArrayHasKey('components', $info);
        $this->assertContains('RSA-4096-OAEP', $info['components']);
        $this->assertContains('ML-KEM-768', $info['components']);
    }
    
    public function test_ml_kem_key_generation_with_fallback()
    {
        // Force using fallback provider by mocking
        $this->app->bind(QuantumCryptoService::class, function () {
            $service = new QuantumCryptoService($this->chatEncryptionService);
            
            // Use reflection to set the provider to fallback
            $reflection = new \ReflectionClass($service);
            $providerProperty = $reflection->getProperty('mlkemProvider');
            $providerProperty->setAccessible(true);
            $providerProperty->setValue($service, new FallbackMLKEMProvider());
            
            return $service;
        });
        
        $quantumService = app(QuantumCryptoService::class);
        
        foreach ([512, 768, 1024] as $securityLevel) {
            $keyPair = $quantumService->generateMLKEMKeyPair($securityLevel);
            
            $this->assertArrayHasKey('public_key', $keyPair);
            $this->assertArrayHasKey('private_key', $keyPair);
            $this->assertArrayHasKey('algorithm', $keyPair);
            $this->assertArrayHasKey('key_strength', $keyPair);
            $this->assertArrayHasKey('provider', $keyPair);
            
            $this->assertEquals("ML-KEM-{$securityLevel}", $keyPair['algorithm']);
            $this->assertEquals($securityLevel, $keyPair['key_strength']);
            $this->assertStringContains('NOT SECURE', $keyPair['provider']);
        }
    }
    
    public function test_encapsulation_decapsulation_with_fallback()
    {
        // Force using fallback provider
        $this->app->bind(QuantumCryptoService::class, function () {
            $service = new QuantumCryptoService($this->chatEncryptionService);
            
            $reflection = new \ReflectionClass($service);
            $providerProperty = $reflection->getProperty('mlkemProvider');
            $providerProperty->setAccessible(true);
            $providerProperty->setValue($service, new FallbackMLKEMProvider());
            
            return $service;
        });
        
        $quantumService = app(QuantumCryptoService::class);
        
        // Generate key pair
        $keyPair = $quantumService->generateMLKEMKeyPair(768);
        
        // Encapsulate
        $encapResult = $quantumService->encapsulateMLKEM($keyPair['public_key'], 768);
        
        $this->assertArrayHasKey('ciphertext', $encapResult);
        $this->assertArrayHasKey('shared_secret', $encapResult);
        $this->assertEquals('ML-KEM-768', $encapResult['algorithm']);
        $this->assertEquals(32, strlen($encapResult['shared_secret']));
        
        // Decapsulate
        $decapSecret = $quantumService->decapsulateMLKEM(
            $encapResult['ciphertext'],
            $keyPair['private_key'],
            768
        );
        
        $this->assertEquals($encapResult['shared_secret'], $decapSecret);
    }
    
    public function test_hybrid_key_generation()
    {
        // Mock RSA key pair generation
        $this->chatEncryptionService
            ->shouldReceive('generateKeyPair')
            ->with(4096)
            ->andReturn([
                'public_key' => 'rsa-public-key',
                'private_key' => 'rsa-private-key'
            ]);
        
        // Force using fallback provider for ML-KEM
        $this->app->bind(QuantumCryptoService::class, function () {
            $service = new QuantumCryptoService($this->chatEncryptionService);
            
            $reflection = new \ReflectionClass($service);
            $providerProperty = $reflection->getProperty('mlkemProvider');
            $providerProperty->setAccessible(true);
            $providerProperty->setValue($service, new FallbackMLKEMProvider());
            
            return $service;
        });
        
        $quantumService = app(QuantumCryptoService::class);
        $keyPair = $quantumService->generateHybridKeyPair();
        
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertEquals('HYBRID-RSA4096-MLKEM768', $keyPair['algorithm']);
        $this->assertArrayHasKey('components', $keyPair);
        
        // Verify hybrid public key structure
        $publicKeyData = json_decode(base64_decode($keyPair['public_key']), true);
        $this->assertEquals('HYBRID-RSA4096-MLKEM768', $publicKeyData['algorithm']);
        $this->assertArrayHasKey('rsa', $publicKeyData['components']);
        $this->assertArrayHasKey('ml-kem', $publicKeyData['components']);
    }
    
    public function test_invalid_security_level_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported security level: 999');
        
        $this->quantumService->generateMLKEMKeyPair(999);
    }
    
    public function test_invalid_base64_public_key_throws_exception()
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid base64 public key');
        
        $this->quantumService->encapsulateMLKEM('invalid-base64!', 768);
    }
    
    public function test_invalid_base64_ciphertext_throws_exception()
    {
        $keyPair = ['private_key' => base64_encode('fake-private-key')];
        
        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('Invalid base64 ciphertext');
        
        $this->quantumService->decapsulateMLKEM('invalid-base64!', $keyPair['private_key'], 768);
    }
    
    public function test_ml_kem_availability_detection()
    {
        // Test availability detection
        $isAvailable = $this->quantumService->isMLKEMAvailable();
        $this->assertIsBool($isAvailable);
        
        // In test environment, fallback should be available
        $this->assertTrue($isAvailable);
    }
    
    public function test_shared_secret_combination()
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->quantumService);
        $method = $reflection->getMethod('combineSharedSecrets');
        $method->setAccessible(true);
        
        $rsaSecret = 'rsa-shared-secret';
        $mlkemSecret = 'mlkem-shared-secret';
        
        $combined = $method->invoke($this->quantumService, $rsaSecret, $mlkemSecret);
        
        $this->assertEquals(32, strlen($combined)); // SHA-256 produces 32 bytes
        
        // Test deterministic combination
        $combined2 = $method->invoke($this->quantumService, $rsaSecret, $mlkemSecret);
        $this->assertEquals($combined, $combined2);
        
        // Test different inputs produce different results
        $combined3 = $method->invoke($this->quantumService, $rsaSecret, 'different-secret');
        $this->assertNotEquals($combined, $combined3);
    }
    
    public function test_key_validation_with_invalid_algorithm()
    {
        $result = $this->quantumService->validateQuantumKeyPair(
            'fake-public-key',
            'fake-private-key',
            'INVALID-ALGORITHM'
        );
        
        $this->assertFalse($result);
    }
    
    public function test_hybrid_key_parsing()
    {
        // Test invalid hybrid public key format
        $invalidHybridKey = base64_encode(json_encode(['invalid' => 'structure']));
        
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Invalid hybrid public key format');
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->quantumService);
        $method = $reflection->getMethod('parseHybridPublicKey');
        $method->setAccessible(true);
        
        $method->invoke($this->quantumService, $invalidHybridKey);
    }
    
    public function test_fallback_provider_characteristics()
    {
        $provider = new FallbackMLKEMProvider();
        
        $this->assertTrue($provider->isAvailable());
        $this->assertEquals('Fallback (NOT SECURE)', $provider->getProviderName());
        $this->assertEquals([512, 768, 1024], $provider->getSupportedLevels());
        
        // Test key generation
        $keyPair = $provider->generateKeyPair(768);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        
        // Test key size validation
        $this->assertTrue($provider->validateKeyPair($keyPair['public_key'], $keyPair['private_key'], 768));
        $this->assertFalse($provider->validateKeyPair('wrong-size', $keyPair['private_key'], 768));
    }
    
    public function test_encryption_service_integration_points()
    {
        // Test that quantum service properly integrates with chat encryption service
        $service = new QuantumCryptoService($this->chatEncryptionService);
        
        $algorithms = $service->getSupportedAlgorithms();
        foreach ($algorithms as $algorithm) {
            $info = $service->getAlgorithmInfo($algorithm);
            $this->assertIsArray($info);
            $this->assertArrayHasKey('quantum_resistant', $info);
            $this->assertArrayHasKey('version', $info);
        }
    }
}