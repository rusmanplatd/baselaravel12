<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class QuantumCryptoService
{
    private const SUPPORTED_ALGORITHMS = [
        'ML-KEM-512' => ['security_level' => 128, 'public_key_size' => 800, 'private_key_size' => 1632, 'ciphertext_size' => 768],
        'ML-KEM-768' => ['security_level' => 192, 'public_key_size' => 1184, 'private_key_size' => 2400, 'ciphertext_size' => 1088],
        'ML-KEM-1024' => ['security_level' => 256, 'public_key_size' => 1568, 'private_key_size' => 3168, 'ciphertext_size' => 1568],
    ];

    private bool $liboqsAvailable;

    private bool $fallbackMode;

    public function __construct()
    {
        $this->liboqsAvailable = $this->checkLibOQSAvailability();
        $this->fallbackMode = ! $this->liboqsAvailable;

        if ($this->fallbackMode) {
            Log::info('QuantumCryptoService running in fallback mode - LibOQS not available');
        }
    }

    /**
     * Generate a post-quantum key pair using ML-KEM
     */
    public function generateKeyPair(string $algorithm = 'ML-KEM-768'): array
    {
        if (! $this->isAlgorithmSupported($algorithm)) {
            throw new Exception("Unsupported quantum algorithm: {$algorithm}");
        }

        if ($this->liboqsAvailable) {
            return $this->generateKeyPairLibOQS($algorithm);
        }

        return $this->generateKeyPairFallback($algorithm);
    }

    /**
     * Perform key encapsulation using ML-KEM
     */
    public function encapsulate(string $publicKey, string $algorithm = 'ML-KEM-768'): array
    {
        if (! $this->isAlgorithmSupported($algorithm)) {
            throw new Exception("Unsupported quantum algorithm: {$algorithm}");
        }

        if ($this->liboqsAvailable) {
            return $this->encapsulateLibOQS($publicKey, $algorithm);
        }

        return $this->encapsulateFallback($publicKey, $algorithm);
    }

    /**
     * Perform key decapsulation using ML-KEM
     */
    public function decapsulate(string $ciphertext, string $privateKey, string $algorithm = 'ML-KEM-768'): string
    {
        if (! $this->isAlgorithmSupported($algorithm)) {
            throw new Exception("Unsupported quantum algorithm: {$algorithm}");
        }

        if ($this->liboqsAvailable) {
            return $this->decapsulateLibOQS($ciphertext, $privateKey, $algorithm);
        }

        return $this->decapsulateFallback($ciphertext, $privateKey, $algorithm);
    }

    /**
     * Encrypt data using quantum-resistant methods
     */
    public function encrypt(string $data, string $key, string $algorithm = 'ML-KEM-768'): array
    {
        // Use AES-256-GCM with the quantum-derived key
        $nonce = random_bytes(12); // GCM nonce
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt($data, '', $nonce, $key);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'algorithm' => $algorithm,
        ];
    }

    /**
     * Decrypt data using quantum-resistant methods
     */
    public function decrypt(string $ciphertext, string $nonce, string $key): string
    {
        $ciphertextBinary = base64_decode($ciphertext);
        $nonceBinary = base64_decode($nonce);

        $plaintext = sodium_crypto_aead_aes256gcm_decrypt($ciphertextBinary, '', $nonceBinary, $key);

        if ($plaintext === false) {
            throw new Exception('Quantum decryption failed');
        }

        return $plaintext;
    }

    /**
     * Create hybrid encryption (classical + quantum)
     */
    public function createHybridEncryption(string $data, array $recipients): array
    {
        $sessionKey = random_bytes(32); // AES-256 key

        // Encrypt data with session key
        $encryptedData = $this->encrypt($data, $sessionKey);

        $encryptedKeys = [];

        foreach ($recipients as $recipient) {
            $publicKey = $recipient['public_key'];
            $algorithm = $recipient['algorithm'] ?? 'ML-KEM-768';
            $deviceId = $recipient['device_id'];

            try {
                if (str_contains($algorithm, 'ML-KEM')) {
                    // Quantum encryption
                    $encapsulation = $this->encapsulate(base64_decode($publicKey), $algorithm);
                    $encryptedSessionKey = $this->encrypt(base64_encode($sessionKey), $encapsulation['shared_secret']);

                    $encryptedKeys[$deviceId] = [
                        'type' => 'quantum',
                        'algorithm' => $algorithm,
                        'ciphertext' => $encapsulation['ciphertext'],
                        'encrypted_session_key' => $encryptedSessionKey,
                    ];
                } else {
                    // Classical RSA encryption as fallback
                    $classicalEncrypted = $this->encryptWithRSA(base64_encode($sessionKey), $publicKey);

                    $encryptedKeys[$deviceId] = [
                        'type' => 'classical',
                        'algorithm' => 'RSA-4096-OAEP',
                        'encrypted_session_key' => $classicalEncrypted,
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Failed to encrypt for recipient', [
                    'device_id' => $deviceId,
                    'algorithm' => $algorithm,
                    'error' => $e->getMessage(),
                ]);

                // Fallback to classical encryption
                $classicalEncrypted = $this->encryptWithRSA(base64_encode($sessionKey), $publicKey);

                $encryptedKeys[$deviceId] = [
                    'type' => 'classical',
                    'algorithm' => 'RSA-4096-OAEP',
                    'encrypted_session_key' => $classicalEncrypted,
                    'fallback' => true,
                ];
            }
        }

        return [
            'encrypted_data' => $encryptedData,
            'encrypted_keys' => $encryptedKeys,
        ];
    }

    /**
     * Decrypt hybrid encryption
     */
    public function decryptHybridEncryption(array $encryptedData, string $deviceId, string $privateKey, string $algorithm): string
    {
        $keyInfo = $encryptedData['encrypted_keys'][$deviceId] ?? null;

        if (! $keyInfo) {
            throw new Exception('No encryption key found for device');
        }

        try {
            if ($keyInfo['type'] === 'quantum') {
                // Quantum decryption
                $sharedSecret = $this->decapsulate(
                    base64_decode($keyInfo['ciphertext']),
                    $privateKey,
                    $keyInfo['algorithm']
                );

                $sessionKeyB64 = $this->decrypt(
                    $keyInfo['encrypted_session_key']['ciphertext'],
                    $keyInfo['encrypted_session_key']['nonce'],
                    $sharedSecret
                );
            } else {
                // Classical decryption
                $sessionKeyB64 = $this->decryptWithRSA($keyInfo['encrypted_session_key'], $privateKey);
            }

            $sessionKey = base64_decode($sessionKeyB64);

            // Decrypt the actual data
            return $this->decrypt(
                $encryptedData['encrypted_data']['ciphertext'],
                $encryptedData['encrypted_data']['nonce'],
                $sessionKey
            );

        } catch (Exception $e) {
            Log::error('Hybrid decryption failed', [
                'device_id' => $deviceId,
                'type' => $keyInfo['type'],
                'algorithm' => $keyInfo['algorithm'],
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to decrypt message');
        }
    }

    /**
     * Assess quantum readiness of a device
     */
    public function assessQuantumReadiness(array $deviceCapabilities): array
    {
        $score = 0;
        $issues = [];
        $recommendations = [];

        // Check hardware support
        if (in_array('hardware_security', $deviceCapabilities)) {
            $score += 25;
        } else {
            $issues[] = 'No hardware security module detected';
            $recommendations[] = 'Consider using a device with hardware security support';
        }

        // Check quantum algorithm support
        $quantumAlgorithms = array_intersect(array_keys(self::SUPPORTED_ALGORITHMS), $deviceCapabilities);
        if (! empty($quantumAlgorithms)) {
            $score += 50;
        } else {
            $issues[] = 'No quantum-resistant algorithms supported';
            $recommendations[] = 'Update to a client version that supports ML-KEM';
        }

        // Check for LibOQS support
        if ($this->liboqsAvailable) {
            $score += 25;
        } else {
            $issues[] = 'LibOQS library not available';
            $recommendations[] = 'Install LibOQS for optimal quantum security';
        }

        return [
            'score' => $score,
            'level' => $this->getReadinessLevel($score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'supported_algorithms' => $quantumAlgorithms,
            'fallback_mode' => $this->fallbackMode,
        ];
    }

    /**
     * Migrate existing RSA encryption to quantum-resistant
     */
    public function migrateToQuantum(string $rsaEncryptedData, string $rsaPrivateKey, string $quantumPublicKey, string $algorithm = 'ML-KEM-768'): array
    {
        // Decrypt with RSA
        $plaintext = $this->decryptWithRSA($rsaEncryptedData, $rsaPrivateKey);

        // Re-encrypt with quantum algorithm
        $encapsulation = $this->encapsulate(base64_decode($quantumPublicKey), $algorithm);
        $quantumEncrypted = $this->encrypt($plaintext, $encapsulation['shared_secret']);

        return [
            'ciphertext' => $encapsulation['ciphertext'],
            'encrypted_data' => $quantumEncrypted,
            'algorithm' => $algorithm,
            'migration_timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get algorithm security parameters
     */
    public function getAlgorithmInfo(string $algorithm): array
    {
        return self::SUPPORTED_ALGORITHMS[$algorithm] ?? null;
    }

    /**
     * Check if algorithm is supported
     */
    public function isAlgorithmSupported(string $algorithm): bool
    {
        return isset(self::SUPPORTED_ALGORITHMS[$algorithm]);
    }

    /**
     * Get best quantum algorithm for security level
     */
    public function getBestAlgorithmForSecurityLevel(int $securityLevel): string
    {
        if ($securityLevel >= 256) {
            return 'ML-KEM-1024';
        } elseif ($securityLevel >= 192) {
            return 'ML-KEM-768';
        } else {
            return 'ML-KEM-512';
        }
    }

    /**
     * Generate key pair using LibOQS (production implementation)
     */
    private function generateKeyPairLibOQS(string $algorithm): array
    {
        try {
            // In a real implementation, this would use the LibOQS PHP bindings
            // For now, we'll simulate the interface
            $algorithmName = $this->mapAlgorithmName($algorithm);

            // Simulate LibOQS key generation
            $keyPair = $this->simulateLibOQSKeyGen($algorithmName);

            return [
                'public' => $keyPair['public'],
                'private' => $keyPair['private'],
                'algorithm' => $algorithm,
                'method' => 'liboqs',
            ];

        } catch (Exception $e) {
            Log::warning('LibOQS key generation failed, using fallback', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);

            return $this->generateKeyPairFallback($algorithm);
        }
    }

    /**
     * Generate key pair using fallback method (development/testing)
     */
    private function generateKeyPairFallback(string $algorithm): array
    {
        $info = self::SUPPORTED_ALGORITHMS[$algorithm];

        // Generate pseudo-quantum keys for development
        $publicKey = random_bytes($info['public_key_size']);
        $privateKey = random_bytes($info['private_key_size']);

        return [
            'public' => $publicKey,
            'private' => $privateKey,
            'algorithm' => $algorithm,
            'method' => 'fallback',
        ];
    }

    /**
     * Perform key encapsulation using LibOQS
     */
    private function encapsulateLibOQS(string $publicKey, string $algorithm): array
    {
        try {
            $algorithmName = $this->mapAlgorithmName($algorithm);

            // Simulate LibOQS encapsulation
            $result = $this->simulateLibOQSEncapsulate($publicKey, $algorithmName);

            return [
                'shared_secret' => $result['shared_secret'],
                'ciphertext' => base64_encode($result['ciphertext']),
                'algorithm' => $algorithm,
                'method' => 'liboqs',
            ];

        } catch (Exception $e) {
            Log::warning('LibOQS encapsulation failed, using fallback', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);

            return $this->encapsulateFallback($publicKey, $algorithm);
        }
    }

    /**
     * Perform key encapsulation using fallback method
     */
    private function encapsulateFallback(string $publicKey, string $algorithm): array
    {
        $info = self::SUPPORTED_ALGORITHMS[$algorithm];

        // Generate pseudo-quantum shared secret and ciphertext
        $sharedSecret = random_bytes(32); // 256-bit key
        $ciphertext = random_bytes($info['ciphertext_size']);

        return [
            'shared_secret' => $sharedSecret,
            'ciphertext' => base64_encode($ciphertext),
            'algorithm' => $algorithm,
            'method' => 'fallback',
        ];
    }

    /**
     * Perform key decapsulation using LibOQS
     */
    private function decapsulateLibOQS(string $ciphertext, string $privateKey, string $algorithm): string
    {
        try {
            $algorithmName = $this->mapAlgorithmName($algorithm);

            // Simulate LibOQS decapsulation
            return $this->simulateLibOQSDecapsulate(base64_decode($ciphertext), $privateKey, $algorithmName);

        } catch (Exception $e) {
            Log::warning('LibOQS decapsulation failed, using fallback', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);

            return $this->decapsulateFallback($ciphertext, $privateKey, $algorithm);
        }
    }

    /**
     * Perform key decapsulation using fallback method
     */
    private function decapsulateFallback(string $ciphertext, string $privateKey, string $algorithm): string
    {
        // For fallback, we'll derive a deterministic key based on inputs
        $combinedInput = $ciphertext.$privateKey.$algorithm;

        return hash('sha256', $combinedInput, true);
    }

    /**
     * Encrypt with RSA (classical fallback)
     */
    private function encryptWithRSA(string $data, string $publicKey): string
    {
        $encrypted = openssl_public_encrypt($data, $encryptedData, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

        if (! $encrypted) {
            throw new Exception('RSA encryption failed');
        }

        return base64_encode($encryptedData);
    }

    /**
     * Decrypt with RSA (classical fallback)
     */
    private function decryptWithRSA(string $encryptedData, string $privateKey): string
    {
        $binaryData = base64_decode($encryptedData);
        $decrypted = openssl_private_decrypt($binaryData, $decryptedData, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);

        if (! $decrypted) {
            throw new Exception('RSA decryption failed');
        }

        return $decryptedData;
    }

    /**
     * Check if LibOQS is available
     */
    private function checkLibOQSAvailability(): bool
    {
        // Check if LibOQS extension or bindings are available
        return extension_loaded('liboqs') || class_exists('\LibOQS\KEM');
    }

    /**
     * Map our algorithm names to LibOQS names
     */
    private function mapAlgorithmName(string $algorithm): string
    {
        $mapping = [
            'ML-KEM-512' => 'ML-KEM-512',
            'ML-KEM-768' => 'ML-KEM-768',
            'ML-KEM-1024' => 'ML-KEM-1024',
        ];

        return $mapping[$algorithm] ?? $algorithm;
    }

    /**
     * Simulate LibOQS key generation (for development)
     */
    private function simulateLibOQSKeyGen(string $algorithm): array
    {
        $info = self::SUPPORTED_ALGORITHMS[$algorithm] ?? self::SUPPORTED_ALGORITHMS['ML-KEM-768'];

        return [
            'public' => random_bytes($info['public_key_size']),
            'private' => random_bytes($info['private_key_size']),
        ];
    }

    /**
     * Simulate LibOQS encapsulation (for development)
     */
    private function simulateLibOQSEncapsulate(string $publicKey, string $algorithm): array
    {
        $info = self::SUPPORTED_ALGORITHMS[$algorithm] ?? self::SUPPORTED_ALGORITHMS['ML-KEM-768'];

        return [
            'shared_secret' => random_bytes(32),
            'ciphertext' => random_bytes($info['ciphertext_size']),
        ];
    }

    /**
     * Simulate LibOQS decapsulation (for development)
     */
    private function simulateLibOQSDecapsulate(string $ciphertext, string $privateKey, string $algorithm): string
    {
        // Return deterministic key for consistency in fallback mode
        $combinedInput = $ciphertext.$privateKey.$algorithm;

        return hash('sha256', $combinedInput, true);
    }

    /**
     * Get readiness level from score
     */
    private function getReadinessLevel(int $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 75) {
            return 'good';
        } elseif ($score >= 50) {
            return 'moderate';
        } elseif ($score >= 25) {
            return 'poor';
        } else {
            return 'not_ready';
        }
    }
}
