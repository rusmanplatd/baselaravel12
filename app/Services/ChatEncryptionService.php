<?php

namespace App\Services;

use App\Exceptions\DecryptionException;
use App\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ChatEncryptionService
{
    private const KEY_SIZE = 32;

    private const IV_SIZE = 16;

    private const RSA_KEY_SIZE = 4096; // Enhanced from 2048 for better security

    private const PBKDF2_ITERATIONS = 100000; // Increased from 10000 for better security

    private const MIN_PASSWORD_ENTROPY = 50; // Minimum entropy for derived passwords

    public function generateKeyPair(?int $keySize = null, string $algorithm = 'RSA-4096-OAEP'): array
    {
        // Support quantum algorithms
        if ($algorithm !== 'RSA-4096-OAEP') {
            return $this->generateQuantumKeyPair($algorithm, $keySize);
        }

        try {
            // Use smaller keys in testing environment for performance
            $actualKeySize = $keySize ?? (app()->environment('testing') ? 2048 : self::RSA_KEY_SIZE);

            $config = [
                'digest_alg' => 'sha512',
                'private_key_bits' => $actualKeySize,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            // Only add config path if it exists to avoid OpenSSL warnings
            $configPath = config('app.openssl_config_path');
            if ($configPath && file_exists($configPath)) {
                $config['config'] = $configPath;
            }

            $resource = openssl_pkey_new($config);

            if (! $resource) {
                $error = openssl_error_string();
                Log::error('Failed to generate RSA key pair', ['openssl_error' => $error]);
                throw new EncryptionException("RSA key pair generation failed: {$error}");
            }

            if (! openssl_pkey_export($resource, $privateKey)) {
                $error = openssl_error_string();
                Log::error('Failed to export private key', ['openssl_error' => $error]);
                throw new EncryptionException("Private key export failed: {$error}");
            }

            $publicKeyDetails = openssl_pkey_get_details($resource);
            if (! $publicKeyDetails || ! isset($publicKeyDetails['key'])) {
                Log::error('Failed to get public key details');
                throw new EncryptionException('Public key extraction failed');
            }

            $publicKey = $publicKeyDetails['key'];

            Log::info('Successfully generated RSA key pair');

            return [
                'public_key' => $publicKey,
                'private_key' => $privateKey,
            ];
        } catch (EncryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during key pair generation', ['exception' => $e->getMessage()]);
            throw new EncryptionException('Unexpected key generation error', $e);
        }
    }

    public function generateSymmetricKey(): string
    {
        return random_bytes(self::KEY_SIZE);
    }

    public function encryptSymmetricKey(string $symmetricKey, string $publicKey): string
    {
        try {
            $publicKeyResource = openssl_pkey_get_public($publicKey);
            if (! $publicKeyResource) {
                throw new EncryptionException('Invalid public key');
            }

            $encrypted = '';
            if (! openssl_public_encrypt($symmetricKey, $encrypted, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING)) {
                $error = openssl_error_string();
                Log::error('Failed to encrypt symmetric key', ['openssl_error' => $error]);
                throw new EncryptionException("Symmetric key encryption failed: {$error}");
            }

            return base64_encode($encrypted);
        } catch (EncryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during symmetric key encryption', ['exception' => $e->getMessage()]);
            throw new EncryptionException('Unexpected symmetric key encryption error', $e);
        }
    }

    public function decryptSymmetricKey(string $encryptedKey, string $privateKey): string
    {
        try {
            // Validate inputs
            if (empty($encryptedKey)) {
                throw new DecryptionException('Encrypted key cannot be empty');
            }

            if (empty($privateKey)) {
                throw new DecryptionException('Private key cannot be empty');
            }

            // Check if private key has proper format
            if (! str_contains($privateKey, '-----BEGIN') || ! str_contains($privateKey, '-----END')) {
                Log::warning('Private key may be in unexpected format', [
                    'key_length' => strlen($privateKey),
                    'starts_with' => substr($privateKey, 0, 50),
                ]);
            }

            $privateKeyResource = openssl_pkey_get_private($privateKey);
            if (! $privateKeyResource) {
                $opensslError = openssl_error_string();
                Log::error('Failed to parse private key', [
                    'openssl_error' => $opensslError,
                    'key_length' => strlen($privateKey),
                    'key_preview' => substr($privateKey, 0, 100),
                ]);
                throw new DecryptionException("Invalid private key format: {$opensslError}");
            }

            $encrypted = base64_decode($encryptedKey, true);
            if ($encrypted === false) {
                throw new DecryptionException('Invalid base64 encoded symmetric encryption key');
            }

            $decrypted = '';

            if (! openssl_private_decrypt($encrypted, $decrypted, $privateKeyResource, OPENSSL_PKCS1_OAEP_PADDING)) {
                $error = openssl_error_string();
                Log::error('Failed to decrypt symmetric key', [
                    'openssl_error' => $error,
                    'encrypted_length' => strlen($encrypted),
                    'key_details' => openssl_pkey_get_details($privateKeyResource)['bits'] ?? 'unknown',
                ]);
                throw new DecryptionException("Symmetric key decryption failed: {$error}");
            }

            return $decrypted;
        } catch (DecryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during symmetric key decryption', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new DecryptionException('Unexpected symmetric key decryption error: '.$e->getMessage(), $e);
        }
    }

    public function encryptMessage(string $message, string $symmetricKey): array
    {
        try {
            // Allow empty messages for file encryption use cases
            // Empty files should be handled gracefully

            if (strlen($symmetricKey) !== self::KEY_SIZE) {
                throw new EncryptionException('Invalid symmetric key length');
            }

            $iv = random_bytes(self::IV_SIZE);
            $timestamp = time();
            $nonce = bin2hex(random_bytes(8));

            // Create authenticated data including timestamp and nonce for replay protection
            $authData = json_encode([
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ]);

            // Prepend auth data to message for HMAC calculation
            $dataToEncrypt = $authData.'|'.$message;

            $encrypted = openssl_encrypt(
                $dataToEncrypt,
                'aes-256-cbc',
                $symmetricKey,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                $error = openssl_error_string();
                Log::error('Failed to encrypt message', ['openssl_error' => $error]);
                throw new EncryptionException("Message encryption failed: {$error}");
            }

            $encryptedData = base64_encode($encrypted);
            $ivBase64 = base64_encode($iv);

            // Calculate HMAC for integrity and authenticity
            $hmac = hash_hmac('sha256', $encryptedData.$ivBase64.$authData, $symmetricKey);

            // Hash original message for additional verification
            $contentHash = hash('sha256', $message);

            Log::debug('Message encrypted successfully', [
                'message_length' => strlen($message),
                'encrypted_length' => strlen($encryptedData),
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ]);

            return [
                'data' => $encryptedData,
                'iv' => $ivBase64,
                'hash' => $contentHash,
                'hmac' => $hmac,
                'auth_data' => base64_encode($authData),
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ];
        } catch (EncryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during message encryption', ['exception' => $e->getMessage()]);
            throw new EncryptionException('Unexpected encryption error', $e);
        }
    }

    public function decryptMessage(string $encryptedContent, string $iv, string $symmetricKey, ?string $hmac = null, ?string $authData = null, int $maxAge = 3600): string
    {
        try {
            if (strlen($symmetricKey) !== self::KEY_SIZE) {
                throw new DecryptionException('Invalid symmetric key length');
            }

            $encrypted = base64_decode($encryptedContent);
            if ($encrypted === false) {
                throw new DecryptionException('Invalid base64 encoded data');
            }

            $ivBytes = base64_decode($iv);
            if ($ivBytes === false || strlen($ivBytes) !== self::IV_SIZE) {
                throw new DecryptionException('Invalid initialization vector');
            }

            // Verify HMAC if provided
            if ($hmac && $authData) {
                $authDataDecoded = base64_decode($authData);
                $expectedHmac = hash_hmac('sha256', $encryptedContent.$iv.$authDataDecoded, $symmetricKey);

                if (! hash_equals($hmac, $expectedHmac)) {
                    throw new DecryptionException('Message authentication failed - HMAC mismatch');
                }

                // Validate timestamp for replay protection
                $authDataArray = json_decode($authDataDecoded, true);
                if ($authDataArray && isset($authDataArray['timestamp'])) {
                    $messageAge = time() - $authDataArray['timestamp'];
                    if ($messageAge > $maxAge) {
                        throw new DecryptionException('Message too old - potential replay attack');
                    }
                    if ($messageAge < -300) { // Allow 5 minutes clock skew
                        throw new DecryptionException('Message from future - clock skew too large');
                    }
                }
            }

            $decrypted = openssl_decrypt(
                $encrypted,
                'aes-256-cbc',
                $symmetricKey,
                OPENSSL_RAW_DATA,
                $ivBytes
            );

            if ($decrypted === false) {
                $error = openssl_error_string();
                Log::warning('Failed to decrypt message', ['openssl_error' => $error]);
                throw new DecryptionException("Message decryption failed: {$error}");
            }

            // Extract the actual message content after auth data
            if (strpos($decrypted, '|') !== false) {
                $parts = explode('|', $decrypted, 2);
                if (count($parts) === 2) {
                    $decrypted = $parts[1]; // Get message content after auth data
                }
            }

            Log::debug('Message decrypted successfully', [
                'encrypted_length' => strlen($encryptedContent),
                'decrypted_length' => strlen($decrypted),
                'hmac_verified' => ! is_null($hmac),
            ]);

            return $decrypted;
        } catch (DecryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during message decryption', ['exception' => $e->getMessage()]);
            throw new DecryptionException('Unexpected decryption error', $e);
        }
    }

    public function verifyMessageHash(string $message, string $hash): bool
    {
        return hash_equals($hash, hash('sha256', $message));
    }

    public function encryptForStorage(string $data): string
    {
        return Crypt::encryptString($data);
    }

    public function decryptFromStorage(string $encryptedData): string
    {
        return Crypt::decryptString($encryptedData);
    }

    public function encryptFile(string $fileContent, string $symmetricKey): array
    {
        return $this->encryptMessage($fileContent, $symmetricKey);
    }

    public function decryptFile(string $encryptedContent, string $iv, string $symmetricKey): string
    {
        return $this->decryptMessage($encryptedContent, $iv, $symmetricKey);
    }

    public function deriveKeyFromPassword(string $password, string $salt, ?int $iterations = null): string
    {
        try {
            $iterations = $iterations ?? self::PBKDF2_ITERATIONS;

            if (strlen($salt) < 16) {
                throw new EncryptionException('Salt must be at least 16 bytes long');
            }

            if (strlen($password) < 8) {
                throw new EncryptionException('Password must be at least 8 characters long');
            }

            $entropy = $this->calculatePasswordEntropy($password);
            if ($entropy < self::MIN_PASSWORD_ENTROPY) {
                Log::warning('Low password entropy detected', ['entropy' => $entropy]);
            }

            $derivedKey = hash_pbkdf2('sha256', $password, $salt, $iterations, self::KEY_SIZE, true);
            if ($derivedKey === false) {
                throw new EncryptionException('Key derivation failed');
            }

            Log::info('Successfully derived key from password', [
                'salt_length' => strlen($salt),
                'iterations' => $iterations,
                'entropy' => $entropy,
            ]);

            return $derivedKey;
        } catch (\Exception $e) {
            Log::error('Error deriving key from password', ['exception' => $e->getMessage()]);
            throw new EncryptionException('Key derivation error: '.$e->getMessage(), $e);
        }
    }

    public function generateSalt(int $length = 32): string
    {
        return random_bytes($length);
    }

    public function calculatePasswordEntropy(string $password): float
    {
        $length = strlen($password);
        if ($length === 0) {
            return 0.0;
        }

        $charset = 0;
        if (preg_match('/[a-z]/', $password)) {
            $charset += 26;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $charset += 26;
        }
        if (preg_match('/[0-9]/', $password)) {
            $charset += 10;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $charset += 32;
        }

        return $charset > 0 ? $length * log($charset, 2) : 0.0;
    }

    public function rotateSymmetricKey(string $conversationId): string
    {
        try {
            $newKey = $this->generateSymmetricKey();

            Log::info('Symmetric key rotated for conversation', [
                'conversation_id' => $conversationId,
                'timestamp' => now()->toISOString(),
            ]);

            return $newKey;
        } catch (\Exception $e) {
            Log::error('Failed to rotate symmetric key', [
                'conversation_id' => $conversationId,
                'exception' => $e->getMessage(),
            ]);
            throw new EncryptionException('Key rotation failed: '.$e->getMessage(), $e);
        }
    }

    public function verifyKeyIntegrity(string $publicKey, string $privateKey): bool
    {
        try {
            $testData = 'integrity-test-'.bin2hex(random_bytes(16));
            $symmetricKey = $this->generateSymmetricKey();

            $encryptedSymKey = $this->encryptSymmetricKey($symmetricKey, $publicKey);
            $decryptedSymKey = $this->decryptSymmetricKey($encryptedSymKey, $privateKey);

            $encrypted = $this->encryptMessage($testData, $decryptedSymKey);
            $decrypted = $this->decryptMessage($encrypted['data'], $encrypted['iv'], $decryptedSymKey);

            return $decrypted === $testData && $this->verifyMessageHash($testData, $encrypted['hash']);
        } catch (\Exception $e) {
            Log::warning('Key integrity verification failed', ['exception' => $e->getMessage()]);

            return false;
        }
    }

    public function encryptForMultipleParticipants(string $conversationId, string $symmetricKey, array $participantPublicKeys): array
    {
        $encryptedKeys = [];
        $errors = [];

        foreach ($participantPublicKeys as $userId => $publicKey) {
            try {
                $encryptedKey = $this->encryptSymmetricKey($symmetricKey, $publicKey);
                $encryptedKeys[$userId] = [
                    'encrypted_key' => $encryptedKey,
                    'public_key' => $publicKey,
                    'created_at' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                $errors[$userId] = $e->getMessage();
                Log::warning('Failed to encrypt key for participant', [
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'encrypted_keys' => $encryptedKeys,
            'errors' => $errors,
            'success_count' => count($encryptedKeys),
            'total_count' => count($participantPublicKeys),
        ];
    }

    public function createBackupEncryptionKey(string $password, array $keyData): string
    {
        try {
            if (strlen($password) < 8) {
                throw new EncryptionException('Backup password must be at least 8 characters long');
            }

            $salt = $this->generateSalt();
            $derivedKey = $this->deriveKeyFromPassword($password, $salt);

            $backupData = [
                'version' => '2.0',
                'created_at' => now()->toISOString(),
                'key_data' => $keyData,
                'checksum' => hash('sha256', json_encode($keyData)),
            ];

            $encrypted = $this->encryptMessage(json_encode($backupData), $derivedKey);

            return base64_encode(json_encode([
                'salt' => base64_encode($salt),
                'encrypted_data' => $encrypted,
                'version' => '2.0',
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to create backup encryption key', ['exception' => $e->getMessage()]);
            throw new EncryptionException('Backup creation failed: '.$e->getMessage(), $e);
        }
    }

    public function restoreFromBackup(string $encryptedBackup, string $password): array
    {
        try {
            $backupData = json_decode(base64_decode($encryptedBackup), true);
            if (! $backupData || ! isset($backupData['salt'], $backupData['encrypted_data'])) {
                throw new DecryptionException('Invalid backup format');
            }

            $salt = base64_decode($backupData['salt']);
            $derivedKey = $this->deriveKeyFromPassword($password, $salt);

            $decryptedJson = $this->decryptMessage(
                $backupData['encrypted_data']['data'],
                $backupData['encrypted_data']['iv'],
                $derivedKey,
                $backupData['encrypted_data']['hmac'] ?? null,
                $backupData['encrypted_data']['auth_data'] ?? null
            );

            $restoredData = json_decode($decryptedJson, true);
            if (! $restoredData || ! isset($restoredData['key_data'])) {
                throw new DecryptionException('Invalid backup content');
            }

            // Verify checksum
            $expectedChecksum = hash('sha256', json_encode($restoredData['key_data']));
            if (isset($restoredData['checksum']) && ! hash_equals($restoredData['checksum'], $expectedChecksum)) {
                throw new DecryptionException('Backup integrity check failed');
            }

            return $restoredData['key_data'];
        } catch (\Exception $e) {
            Log::error('Failed to restore from backup', ['exception' => $e->getMessage()]);
            throw new DecryptionException('Backup restoration failed: '.$e->getMessage(), $e);
        }
    }

    public function bulkDecryptMessages(array $encryptedMessages, string $symmetricKey): array
    {
        $results = [];
        $errors = [];

        foreach ($encryptedMessages as $messageId => $encryptedData) {
            try {
                $decrypted = $this->decryptMessage(
                    $encryptedData['data'],
                    $encryptedData['iv'],
                    $symmetricKey,
                    $encryptedData['hmac'] ?? null,
                    $encryptedData['auth_data'] ?? null
                );
                $results[$messageId] = $decrypted;
            } catch (\Exception $e) {
                $errors[$messageId] = $e->getMessage();
                Log::debug('Failed to decrypt message in bulk operation', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'decrypted' => $results,
            'errors' => $errors,
            'success_count' => count($results),
            'total_count' => count($encryptedMessages),
        ];
    }

    /**
     * Generate quantum-resistant key pair
     */
    private function generateQuantumKeyPair(string $algorithm, ?int $keySize): array
    {
        $quantumService = app(QuantumCryptoService::class);
        
        return match ($algorithm) {
            'ML-KEM-512' => $quantumService->generateMLKEMKeyPair(512),
            'ML-KEM-768' => $quantumService->generateMLKEMKeyPair(768),
            'ML-KEM-1024' => $quantumService->generateMLKEMKeyPair(1024),
            'HYBRID-RSA4096-MLKEM768' => $quantumService->generateHybridKeyPair(4096, 768),
            default => throw new EncryptionException("Unsupported quantum algorithm: {$algorithm}")
        };
    }

    /**
     * Encrypt symmetric key with algorithm support
     */
    public function encryptSymmetricKeyWithAlgorithm(string $symmetricKey, string $publicKey, string $algorithm): string
    {
        return match ($algorithm) {
            'ML-KEM-512' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 512)['ciphertext'],
            'ML-KEM-768' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 768)['ciphertext'],
            'ML-KEM-1024' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 1024)['ciphertext'],
            'HYBRID-RSA4096-MLKEM768' => app(QuantumCryptoService::class)->encapsulateHybrid($publicKey)['ciphertext'],
            default => $this->encryptSymmetricKey($symmetricKey, $publicKey)
        };
    }

    /**
     * Decrypt symmetric key with algorithm support
     */
    public function decryptSymmetricKeyWithAlgorithm(string $encryptedKey, string $privateKey, string $algorithm): string
    {
        return match ($algorithm) {
            'ML-KEM-512' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 512),
            'ML-KEM-768' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 768),
            'ML-KEM-1024' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 1024),
            'HYBRID-RSA4096-MLKEM768' => app(QuantumCryptoService::class)->decapsulateHybrid($encryptedKey, $privateKey),
            default => $this->decryptSymmetricKey($encryptedKey, $privateKey)
        };
    }

    /**
     * Algorithm negotiation for multi-device conversations
     */
    public function negotiateAlgorithm(array $deviceCapabilities): string
    {
        $commonAlgorithms = [];
        
        foreach ($deviceCapabilities as $deviceCaps) {
            if (empty($commonAlgorithms)) {
                $commonAlgorithms = $deviceCaps;
            } else {
                $commonAlgorithms = array_intersect($commonAlgorithms, $deviceCaps);
            }
        }
        
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
        // Priority: strongest quantum-resistant first
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
     * Check if algorithm is quantum-resistant
     */
    public function isQuantumResistant(string $algorithm): bool
    {
        $quantumAlgorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'];
        return in_array($algorithm, $quantumAlgorithms);
    }

    /**
     * Get algorithm information
     */
    public function getAlgorithmInfo(string $algorithm): array
    {
        $algorithmMap = [
            'RSA-4096-OAEP' => [
                'type' => 'rsa',
                'key_size' => 4096,
                'quantum_resistant' => false,
                'version' => 2,
            ],
            'ML-KEM-512' => [
                'type' => 'ml-kem',
                'security_level' => 512,
                'quantum_resistant' => true,
                'version' => 3,
            ],
            'ML-KEM-768' => [
                'type' => 'ml-kem',
                'security_level' => 768,
                'quantum_resistant' => true,
                'version' => 3,
            ],
            'ML-KEM-1024' => [
                'type' => 'ml-kem',
                'security_level' => 1024,
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

        return $algorithmMap[$algorithm] ?? [];
    }

    public function validateEncryptionHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            // Test key generation
            $startTime = microtime(true);
            $keyPair = $this->generateKeyPair();
            $keyGenTime = (microtime(true) - $startTime) * 1000;

            $health['checks']['key_generation'] = [
                'status' => 'pass',
                'duration_ms' => round($keyGenTime, 2),
            ];

            if ($keyGenTime > 5000) { // 5 seconds
                $health['warnings'][] = "Key generation is slow ({$keyGenTime}ms)";
            }

            // Test symmetric encryption
            $startTime = microtime(true);
            $symmetricKey = $this->generateSymmetricKey();
            $testMessage = 'Health check test message';
            $encrypted = $this->encryptMessage($testMessage, $symmetricKey);
            $decrypted = $this->decryptMessage(
                $encrypted['data'],
                $encrypted['iv'],
                $symmetricKey,
                $encrypted['hmac'],
                $encrypted['auth_data']
            );
            $encryptionTime = (microtime(true) - $startTime) * 1000;

            if ($decrypted !== $testMessage) {
                $health['errors'][] = 'Symmetric encryption test failed';
                $health['status'] = 'unhealthy';
            } else {
                $health['checks']['symmetric_encryption'] = [
                    'status' => 'pass',
                    'duration_ms' => round($encryptionTime, 2),
                ];
            }

            // Test key integrity
            $integrityValid = $this->verifyKeyIntegrity($keyPair['public_key'], $keyPair['private_key']);
            $health['checks']['key_integrity'] = [
                'status' => $integrityValid ? 'pass' : 'fail',
            ];

            if (! $integrityValid) {
                $health['errors'][] = 'Key integrity verification failed';
                $health['status'] = 'unhealthy';
            }

        } catch (\Exception $e) {
            $health['errors'][] = 'Health check exception: '.$e->getMessage();
            $health['status'] = 'unhealthy';
        }

        return $health;
    }
}
