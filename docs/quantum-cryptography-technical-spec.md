# Quantum-Resistant Cryptography Technical Specification

## Overview

This document provides detailed technical specifications for implementing NIST-approved post-quantum cryptographic algorithms in the Laravel chat system. It covers algorithm integration, API specifications, data structures, and implementation patterns.

## Algorithm Specifications

### ML-KEM (Module-Lattice-Based Key-Encapsulation Mechanism)

**NIST Standard:** FIPS 203  
**Based on:** CRYSTALS-KYBER  
**Security Foundation:** Learning With Errors (LWE) problem over module lattices

#### Algorithm Parameters

| Parameter | ML-KEM-512 | ML-KEM-768 | ML-KEM-1024 |
|-----------|------------|------------|-------------|
| **Security Level** | NIST Level 1 | NIST Level 3 | NIST Level 5 |
| **Quantum Security** | ~2^143 | ~2^207 | ~2^272 |
| **Public Key Size** | 800 bytes | 1,184 bytes | 1,568 bytes |
| **Private Key Size** | 1,632 bytes | 2,400 bytes | 3,168 bytes |
| **Ciphertext Size** | 768 bytes | 1,088 bytes | 1,568 bytes |
| **Shared Secret Size** | 32 bytes | 32 bytes | 32 bytes |

#### Recommended Usage
- **ML-KEM-512:** IoT devices, resource-constrained environments
- **ML-KEM-768:** Standard implementation (recommended for chat system)
- **ML-KEM-1024:** High-security environments, long-term data protection

### ML-DSA (Module-Lattice-Based Digital Signature Algorithm)

**NIST Standard:** FIPS 204  
**Based on:** CRYSTALS-Dilithium  
**Security Foundation:** Module Learning With Errors (M-LWE) and Module Short Integer Solution (M-SIS)

#### Algorithm Parameters

| Parameter | ML-DSA-44 | ML-DSA-65 | ML-DSA-87 |
|-----------|-----------|-----------|-----------|
| **Security Level** | NIST Level 2 | NIST Level 3 | NIST Level 5 |
| **Public Key Size** | 1,312 bytes | 1,952 bytes | 2,592 bytes |
| **Private Key Size** | 2,560 bytes | 4,032 bytes | 4,896 bytes |
| **Signature Size** | 2,420 bytes | 3,309 bytes | 4,627 bytes |

### Hybrid Implementations

#### RSA-ML-KEM Hybrid Key Exchange
```
Hybrid KEM = RSA-KEM || ML-KEM
- RSA component: RSA-4096 with OAEP padding
- ML-KEM component: ML-KEM-768
- Combined security: max(RSA security, ML-KEM security)
- Shared secret: SHA-256(RSA_shared_secret || ML-KEM_shared_secret)
```

## Data Structures and Database Schema

### Enhanced Encryption Key Storage

```sql
-- chat_encryption_keys table (existing, no changes needed)
CREATE TABLE chat_encryption_keys (
    id CHAR(26) PRIMARY KEY,
    conversation_id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    device_id CHAR(26), -- Added in existing migration
    encrypted_key TEXT NOT NULL, -- Supports larger ML-KEM ciphertexts
    public_key TEXT NOT NULL, -- Supports ML-KEM public keys
    algorithm VARCHAR(50) DEFAULT 'RSA-4096-OAEP', -- New algorithm values
    key_strength INTEGER DEFAULT 4096, -- 512/768/1024 for ML-KEM
    key_version INTEGER DEFAULT 1,
    device_fingerprint VARCHAR(255),
    created_by_device_id CHAR(26),
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sys_users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES user_devices(id) ON DELETE CASCADE
);
```

### Algorithm-Specific Data Formats

#### ML-KEM Key Pair Structure
```php
interface MLKEMKeyPair
{
    public function getPublicKey(): string; // Raw bytes, base64 encoded for storage
    public function getPrivateKey(): string; // Raw bytes, encrypted for storage
    public function getAlgorithm(): string; // 'ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024'
    public function getKeySize(): int; // 512, 768, or 1024
}
```

#### ML-KEM Encapsulation Result
```php
interface MLKEMEncapsulation
{
    public function getCiphertext(): string; // Base64 encoded ciphertext
    public function getSharedSecret(): string; // 32-byte shared secret
    public function getAlgorithm(): string;
}
```

#### Hybrid Key Exchange Structure
```php
interface HybridKeyExchange
{
    public function getRsaComponent(): RSAKeyExchange;
    public function getMlKemComponent(): MLKEMEncapsulation;
    public function getCombinedSharedSecret(): string; // SHA-256 hash of both secrets
    public function getAlgorithm(): string; // 'HYBRID-RSA4096-MLKEM768'
}
```

## Service Layer Implementation

### Enhanced ChatEncryptionService

```php
<?php

namespace App\Services;

use App\Exceptions\EncryptionException;
use App\Exceptions\DecryptionException;

class ChatEncryptionService
{
    // Algorithm constants
    private const SUPPORTED_ALGORITHMS = [
        'RSA-4096-OAEP' => [
            'type' => 'rsa',
            'key_size' => 4096,
            'quantum_resistant' => false,
            'version' => 2,
        ],
        'ML-KEM-512' => [
            'type' => 'ml-kem',
            'security_level' => 1,
            'quantum_resistant' => true,
            'version' => 3,
        ],
        'ML-KEM-768' => [
            'type' => 'ml-kem',
            'security_level' => 3,
            'quantum_resistant' => true,
            'version' => 3,
        ],
        'ML-KEM-1024' => [
            'type' => 'ml-kem',
            'security_level' => 5,
            'quantum_resistant' => true,
            'version' => 3,
        ],
        'HYBRID-RSA4096-MLKEM768' => [
            'type' => 'hybrid',
            'components' => ['RSA-4096-OAEP', 'ML-KEM-768'],
            'quantum_resistant' => true,
            'version' => 3,
        ],
    ];

    /**
     * Generate key pair based on specified algorithm
     */
    public function generateKeyPair(?int $keySize = null, string $algorithm = 'RSA-4096-OAEP'): array
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw new EncryptionException("Unsupported algorithm: {$algorithm}");
        }

        $algorithmConfig = self::SUPPORTED_ALGORITHMS[$algorithm];

        return match ($algorithmConfig['type']) {
            'rsa' => $this->generateRSAKeyPair($keySize ?? $algorithmConfig['key_size']),
            'ml-kem' => $this->generateMLKEMKeyPair($algorithmConfig['security_level']),
            'hybrid' => $this->generateHybridKeyPair($algorithmConfig['components']),
            default => throw new EncryptionException("Unknown algorithm type: {$algorithmConfig['type']}")
        };
    }

    /**
     * Generate ML-KEM key pair
     */
    public function generateMLKEMKeyPair(int $securityLevel = 768): array
    {
        try {
            $keyPair = $this->getMLKEMProvider()->generateKeyPair($securityLevel);
            
            Log::info('ML-KEM key pair generated successfully', [
                'security_level' => $securityLevel,
                'public_key_size' => strlen($keyPair['public_key']),
                'private_key_size' => strlen($keyPair['private_key']),
            ]);

            return [
                'public_key' => base64_encode($keyPair['public_key']),
                'private_key' => $this->encryptPrivateKey($keyPair['private_key']),
                'algorithm' => "ML-KEM-{$securityLevel}",
                'key_strength' => $securityLevel,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate ML-KEM key pair', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('ML-KEM key generation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Generate hybrid RSA + ML-KEM key pair
     */
    public function generateHybridKeyPair(array $components): array
    {
        try {
            $rsaKeyPair = $this->generateRSAKeyPair(4096);
            $mlkemKeyPair = $this->generateMLKEMKeyPair(768);

            $hybridPublicKey = $this->combinePublicKeys($rsaKeyPair, $mlkemKeyPair);
            $hybridPrivateKey = $this->combinePrivateKeys($rsaKeyPair, $mlkemKeyPair);

            return [
                'public_key' => $hybridPublicKey,
                'private_key' => $hybridPrivateKey,
                'algorithm' => 'HYBRID-RSA4096-MLKEM768',
                'key_strength' => 768,
                'components' => [
                    'rsa' => [
                        'algorithm' => 'RSA-4096-OAEP',
                        'key_size' => 4096,
                    ],
                    'ml-kem' => [
                        'algorithm' => 'ML-KEM-768',
                        'security_level' => 768,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate hybrid key pair', ['error' => $e->getMessage()]);
            throw new EncryptionException('Hybrid key generation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Encapsulate shared secret using ML-KEM
     */
    public function encapsulateMLKEM(string $publicKey, string $algorithm): array
    {
        try {
            $securityLevel = $this->extractSecurityLevel($algorithm);
            $publicKeyBytes = base64_decode($publicKey);

            $result = $this->getMLKEMProvider()->encapsulate($publicKeyBytes, $securityLevel);

            Log::debug('ML-KEM encapsulation successful', [
                'algorithm' => $algorithm,
                'ciphertext_size' => strlen($result['ciphertext']),
                'shared_secret_size' => strlen($result['shared_secret']),
            ]);

            return [
                'ciphertext' => base64_encode($result['ciphertext']),
                'shared_secret' => $result['shared_secret'],
                'algorithm' => $algorithm,
            ];
        } catch (\Exception $e) {
            Log::error('ML-KEM encapsulation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('ML-KEM encapsulation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Decapsulate shared secret using ML-KEM
     */
    public function decapsulateMLKEM(string $ciphertext, string $privateKey, string $algorithm): string
    {
        try {
            $securityLevel = $this->extractSecurityLevel($algorithm);
            $ciphertextBytes = base64_decode($ciphertext);
            $privateKeyBytes = $this->decryptPrivateKey($privateKey);

            $sharedSecret = $this->getMLKEMProvider()->decapsulate(
                $ciphertextBytes, 
                $privateKeyBytes, 
                $securityLevel
            );

            Log::debug('ML-KEM decapsulation successful', [
                'algorithm' => $algorithm,
                'shared_secret_size' => strlen($sharedSecret),
            ]);

            return $sharedSecret;
        } catch (\Exception $e) {
            Log::error('ML-KEM decapsulation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
            throw new DecryptionException('ML-KEM decapsulation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Hybrid key encapsulation (RSA + ML-KEM)
     */
    public function encapsulateHybrid(string $hybridPublicKey): array
    {
        try {
            $keyComponents = $this->parseHybridPublicKey($hybridPublicKey);
            
            // Perform RSA encryption
            $rsaResult = $this->encryptSymmetricKey(
                $this->generateSymmetricKey(), 
                $keyComponents['rsa_public_key']
            );
            
            // Perform ML-KEM encapsulation
            $mlkemResult = $this->encapsulateMLKEM(
                $keyComponents['mlkem_public_key'], 
                'ML-KEM-768'
            );
            
            // Combine shared secrets
            $combinedSecret = $this->combineSharedSecrets(
                base64_decode($rsaResult), 
                $mlkemResult['shared_secret']
            );
            
            $hybridCiphertext = $this->combineHybridCiphertexts($rsaResult, $mlkemResult['ciphertext']);
            
            return [
                'ciphertext' => $hybridCiphertext,
                'shared_secret' => $combinedSecret,
                'algorithm' => 'HYBRID-RSA4096-MLKEM768',
                'components' => [
                    'rsa_ciphertext' => $rsaResult,
                    'mlkem_ciphertext' => $mlkemResult['ciphertext'],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Hybrid encapsulation failed', ['error' => $e->getMessage()]);
            throw new EncryptionException('Hybrid encapsulation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Algorithm negotiation for multi-device conversations
     */
    public function negotiateAlgorithm(array $deviceCapabilities): string
    {
        $allCapabilities = [];
        
        foreach ($deviceCapabilities as $device) {
            $capabilities = $device['quantum_capabilities'] ?? ['RSA-4096-OAEP'];
            $allCapabilities[] = $capabilities;
        }
        
        // Find intersection of all device capabilities
        $commonAlgorithms = array_intersect(...$allCapabilities);
        
        if (empty($commonAlgorithms)) {
            throw new EncryptionException('No compatible algorithms found among devices');
        }
        
        return $this->selectBestAlgorithm($commonAlgorithms);
    }

    /**
     * Select the most secure compatible algorithm
     */
    private function selectBestAlgorithm(array $algorithms): string
    {
        // Priority order: strongest quantum-resistant first
        $priority = [
            'ML-KEM-1024',              // Highest security
            'ML-KEM-768',               // Recommended standard
            'HYBRID-RSA4096-MLKEM768',  // Transition hybrid
            'ML-KEM-512',               // Basic quantum resistance
            'RSA-4096-OAEP',            // Legacy fallback
        ];
        
        foreach ($priority as $preferred) {
            if (in_array($preferred, $algorithms)) {
                Log::info('Algorithm selected for conversation', [
                    'selected' => $preferred,
                    'available' => $algorithms,
                ]);
                return $preferred;
            }
        }
        
        throw new EncryptionException('No suitable algorithm found');
    }

    /**
     * Validate algorithm compatibility
     */
    public function isAlgorithmCompatible(string $algorithm, int $encryptionVersion): bool
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            return false;
        }
        
        $algorithmConfig = self::SUPPORTED_ALGORITHMS[$algorithm];
        return $algorithmConfig['version'] <= $encryptionVersion;
    }

    /**
     * Get algorithm metadata
     */
    public function getAlgorithmInfo(string $algorithm): array
    {
        return self::SUPPORTED_ALGORITHMS[$algorithm] ?? [];
    }

    /**
     * Check if algorithm is quantum-resistant
     */
    public function isQuantumResistant(string $algorithm): bool
    {
        return self::SUPPORTED_ALGORITHMS[$algorithm]['quantum_resistant'] ?? false;
    }

    // Private helper methods
    
    private function getMLKEMProvider(): MLKEMProviderInterface
    {
        // Factory pattern to select best available ML-KEM implementation
        if (extension_loaded('liboqs')) {
            return new LibOQSMLKEMProvider();
        }
        
        if (class_exists('\Paragonie\MLKEM\MLKEM')) {
            return new PurePHPMLKEMProvider();
        }
        
        if ($this->opensslSupportsMLKEM()) {
            return new OpenSSLMLKEMProvider();
        }
        
        throw new EncryptionException('No ML-KEM provider available');
    }

    private function opensslSupportsMLKEM(): bool
    {
        // Check if OpenSSL version supports ML-KEM (future versions)
        return version_compare(OPENSSL_VERSION_TEXT, '3.2.0', '>=');
    }

    private function extractSecurityLevel(string $algorithm): int
    {
        if (preg_match('/ML-KEM-(\d+)/', $algorithm, $matches)) {
            return (int)$matches[1];
        }
        
        throw new EncryptionException("Cannot extract security level from algorithm: {$algorithm}");
    }

    private function combineSharedSecrets(string $rsaSecret, string $mlkemSecret): string
    {
        // NIST SP 800-56C compliant key combination
        $combined = $rsaSecret . $mlkemSecret;
        return hash('sha256', $combined, true);
    }

    private function combinePublicKeys(array $rsaKeyPair, array $mlkemKeyPair): string
    {
        $hybridKey = [
            'version' => '1.0',
            'algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'components' => [
                'rsa' => $rsaKeyPair['public_key'],
                'ml-kem' => $mlkemKeyPair['public_key'],
            ],
        ];
        
        return base64_encode(json_encode($hybridKey));
    }

    private function combinePrivateKeys(array $rsaKeyPair, array $mlkemKeyPair): string
    {
        $hybridKey = [
            'version' => '1.0',
            'algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'components' => [
                'rsa' => $rsaKeyPair['private_key'],
                'ml-kem' => $mlkemKeyPair['private_key'],
            ],
        ];
        
        return $this->encryptPrivateKey(json_encode($hybridKey));
    }

    private function parseHybridPublicKey(string $hybridPublicKey): array
    {
        $keyData = json_decode(base64_decode($hybridPublicKey), true);
        
        if (!$keyData || !isset($keyData['components'])) {
            throw new EncryptionException('Invalid hybrid public key format');
        }
        
        return [
            'rsa_public_key' => $keyData['components']['rsa'],
            'mlkem_public_key' => $keyData['components']['ml-kem'],
        ];
    }

    private function combineHybridCiphertexts(string $rsaCiphertext, string $mlkemCiphertext): string
    {
        $combined = [
            'version' => '1.0',
            'components' => [
                'rsa' => $rsaCiphertext,
                'ml-kem' => $mlkemCiphertext,
            ],
        ];
        
        return base64_encode(json_encode($combined));
    }

    private function encryptPrivateKey(string $privateKey): string
    {
        // Use Laravel's encryption for private key storage
        return Crypt::encryptString($privateKey);
    }

    private function decryptPrivateKey(string $encryptedPrivateKey): string
    {
        return Crypt::decryptString($encryptedPrivateKey);
    }
}
```

### ML-KEM Provider Interface

```php
<?php

namespace App\Services\Crypto;

interface MLKEMProviderInterface
{
    /**
     * Generate ML-KEM key pair
     */
    public function generateKeyPair(int $securityLevel): array;

    /**
     * Encapsulate shared secret
     */
    public function encapsulate(string $publicKey, int $securityLevel): array;

    /**
     * Decapsulate shared secret
     */
    public function decapsulate(string $ciphertext, string $privateKey, int $securityLevel): string;

    /**
     * Get supported security levels
     */
    public function getSupportedLevels(): array;

    /**
     * Validate key pair
     */
    public function validateKeyPair(string $publicKey, string $privateKey, int $securityLevel): bool;
}
```

### LibOQS Provider Implementation

```php
<?php

namespace App\Services\Crypto;

class LibOQSMLKEMProvider implements MLKEMProviderInterface
{
    private const ALGORITHM_MAP = [
        512 => 'Kyber512',
        768 => 'Kyber768',
        1024 => 'Kyber1024',
    ];

    public function generateKeyPair(int $securityLevel): array
    {
        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        $kem = new \OQS\KEM($algorithm);
        $keypair = $kem->keypair();
        
        return [
            'public_key' => $keypair[0],
            'private_key' => $keypair[1],
        ];
    }

    public function encapsulate(string $publicKey, int $securityLevel): array
    {
        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        $kem = new \OQS\KEM($algorithm);
        $result = $kem->encaps($publicKey);
        
        return [
            'ciphertext' => $result[0],
            'shared_secret' => $result[1],
        ];
    }

    public function decapsulate(string $ciphertext, string $privateKey, int $securityLevel): string
    {
        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        $kem = new \OQS\KEM($algorithm);
        return $kem->decaps($ciphertext, $privateKey);
    }

    public function getSupportedLevels(): array
    {
        return [512, 768, 1024];
    }

    public function validateKeyPair(string $publicKey, string $privateKey, int $securityLevel): bool
    {
        try {
            // Test encapsulation/decapsulation cycle
            $encapResult = $this->encapsulate($publicKey, $securityLevel);
            $decapResult = $this->decapsulate($encapResult['ciphertext'], $privateKey, $securityLevel);
            
            return $encapResult['shared_secret'] === $decapResult;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

## Device Capability Management

### Enhanced Device Model

```php
<?php

namespace App\Models;

class UserDevice extends Model
{
    protected $casts = [
        'device_capabilities' => 'array',
        'device_info' => 'array',
        'last_used_at' => 'datetime',
        'verified_at' => 'datetime',
        'locked_until' => 'datetime',
        'auto_trust_expires_at' => 'datetime',
        'last_key_rotation_at' => 'datetime',
    ];

    /**
     * Check if device supports quantum-resistant algorithms
     */
    public function supportsQuantumResistant(): bool
    {
        $capabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        return !empty(array_intersect($capabilities, $quantumAlgorithms));
    }

    /**
     * Get supported quantum algorithms
     */
    public function getQuantumCapabilities(): array
    {
        $capabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        return array_intersect($capabilities, $quantumAlgorithms);
    }

    /**
     * Check if device supports specific algorithm
     */
    public function supportsAlgorithm(string $algorithm): bool
    {
        $algorithmMap = [
            'RSA-4096-OAEP' => 'rsa-4096',
            'ML-KEM-512' => 'ml-kem-512',
            'ML-KEM-768' => 'ml-kem-768',
            'ML-KEM-1024' => 'ml-kem-1024',
            'HYBRID-RSA4096-MLKEM768' => 'hybrid',
        ];
        
        $capability = $algorithmMap[$algorithm] ?? strtolower($algorithm);
        $capabilities = $this->device_capabilities ?? [];
        
        return in_array($capability, $capabilities);
    }

    /**
     * Update device quantum capabilities
     */
    public function updateQuantumCapabilities(array $newCapabilities): void
    {
        $existingCapabilities = $this->device_capabilities ?? [];
        $quantumAlgorithms = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        // Remove old quantum capabilities
        $nonQuantumCapabilities = array_diff($existingCapabilities, $quantumAlgorithms);
        
        // Add new quantum capabilities
        $updatedCapabilities = array_merge($nonQuantumCapabilities, $newCapabilities);
        
        $this->update([
            'device_capabilities' => array_unique($updatedCapabilities),
            'encryption_version' => $this->determineEncryptionVersion($newCapabilities),
        ]);
    }

    /**
     * Determine encryption version based on capabilities
     */
    private function determineEncryptionVersion(array $capabilities): int
    {
        $quantumCapabilities = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
        
        if (array_intersect($capabilities, $quantumCapabilities)) {
            return 3; // Quantum-resistant version
        }
        
        return 2; // RSA version
    }
}
```

## Performance Optimization Strategies

### Batch Operations

```php
/**
 * Bulk encrypt messages for multiple recipients
 */
public function bulkEncryptForRecipients(string $message, array $devicePublicKeys, string $algorithm): array
{
    $results = [];
    $symmetricKey = $this->generateSymmetricKey();
    
    // Encrypt message once with symmetric key
    $encryptedMessage = $this->encryptMessage($message, $symmetricKey);
    
    // Encrypt symmetric key for each device
    foreach ($devicePublicKeys as $deviceId => $publicKey) {
        try {
            $encryptedSymKey = match ($algorithm) {
                'RSA-4096-OAEP' => $this->encryptSymmetricKey($symmetricKey, $publicKey),
                'ML-KEM-768' => $this->encapsulateMLKEM($publicKey, $algorithm)['ciphertext'],
                'HYBRID-RSA4096-MLKEM768' => $this->encapsulateHybrid($publicKey)['ciphertext'],
                default => throw new EncryptionException("Unsupported algorithm: {$algorithm}")
            };
            
            $results[$deviceId] = [
                'encrypted_message' => $encryptedMessage,
                'encrypted_key' => $encryptedSymKey,
                'algorithm' => $algorithm,
            ];
        } catch (\Exception $e) {
            $results[$deviceId] = [
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
}
```

### Caching Strategies

```php
/**
 * Cache device algorithm capabilities
 */
public function getCachedDeviceCapabilities(int $deviceId): array
{
    return Cache::remember(
        "device_capabilities_{$deviceId}",
        now()->addHours(24),
        fn() => UserDevice::findOrFail($deviceId)->getQuantumCapabilities()
    );
}

/**
 * Cache algorithm negotiation results
 */
public function getCachedNegotiatedAlgorithm(array $deviceIds): string
{
    $cacheKey = 'negotiated_algorithm_' . implode('_', sort($deviceIds));
    
    return Cache::remember(
        $cacheKey,
        now()->addHours(6),
        fn() => $this->negotiateAlgorithmForDevices($deviceIds)
    );
}
```

## Error Handling and Fallback Strategies

### Algorithm Fallback Chain

```php
public function encryptWithFallback(string $data, string $publicKey, array $preferredAlgorithms): array
{
    $errors = [];
    
    foreach ($preferredAlgorithms as $algorithm) {
        try {
            return $this->encryptWithAlgorithm($data, $publicKey, $algorithm);
        } catch (\Exception $e) {
            $errors[$algorithm] = $e->getMessage();
            Log::warning("Algorithm {$algorithm} failed, trying next", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    throw new EncryptionException('All algorithms failed: ' . json_encode($errors));
}

private function encryptWithAlgorithm(string $data, string $publicKey, string $algorithm): array
{
    return match ($algorithm) {
        'ML-KEM-768' => $this->encryptWithMLKEM($data, $publicKey, 768),
        'HYBRID-RSA4096-MLKEM768' => $this->encryptWithHybrid($data, $publicKey),
        'RSA-4096-OAEP' => $this->encryptWithRSA($data, $publicKey),
        default => throw new EncryptionException("Unsupported algorithm: {$algorithm}")
    };
}
```

### Graceful Degradation

```php
public function setupConversationWithGracefulDegradation(
    Conversation $conversation,
    array $participants
): array {
    $deviceCapabilities = $this->gatherDeviceCapabilities($participants);
    
    try {
        // Try quantum-resistant first
        $algorithm = $this->negotiateAlgorithm($deviceCapabilities);
        return $this->setupConversationEncryption($conversation, $participants, $algorithm);
    } catch (EncryptionException $e) {
        Log::warning('Quantum algorithm setup failed, falling back to RSA', [
            'conversation_id' => $conversation->id,
            'error' => $e->getMessage(),
        ]);
        
        // Fallback to RSA
        return $this->setupConversationEncryption($conversation, $participants, 'RSA-4096-OAEP');
    }
}
```

## Security Validation and Testing

### Algorithm Security Validation

```php
public function validateAlgorithmSecurity(string $algorithm): array
{
    $results = [
        'algorithm' => $algorithm,
        'quantum_resistant' => $this->isQuantumResistant($algorithm),
        'nist_approved' => $this->isNISTApproved($algorithm),
        'security_level' => $this->getSecurityLevel($algorithm),
        'recommended' => $this->isRecommendedAlgorithm($algorithm),
        'warnings' => [],
    ];
    
    // Add warnings for deprecated or weak algorithms
    if ($algorithm === 'RSA-4096-OAEP') {
        $results['warnings'][] = 'Algorithm is not quantum-resistant';
        $results['warnings'][] = 'Consider migrating to ML-KEM for long-term security';
    }
    
    if (!$results['nist_approved']) {
        $results['warnings'][] = 'Algorithm is not NIST-approved for post-quantum cryptography';
    }
    
    return $results;
}

private function isNISTApproved(string $algorithm): bool
{
    $nistApproved = [
        'ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024',
        'ML-DSA-44', 'ML-DSA-65', 'ML-DSA-87',
        'SLH-DSA-128s', 'SLH-DSA-128f', 'SLH-DSA-192s',
    ];
    
    return in_array($algorithm, $nistApproved);
}

private function getSecurityLevel(string $algorithm): int
{
    return match ($algorithm) {
        'ML-KEM-512' => 1,
        'ML-KEM-768', 'ML-DSA-65' => 3,
        'ML-KEM-1024', 'ML-DSA-87' => 5,
        'RSA-4096-OAEP' => 0, // Classical security only
        default => 0
    };
}
```

---
*Document Version: 1.0*  
*Last Updated: August 31, 2025*  
*Next Review: September 30, 2025*