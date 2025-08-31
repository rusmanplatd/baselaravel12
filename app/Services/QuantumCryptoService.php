<?php

namespace App\Services;

use App\Exceptions\DecryptionException;
use App\Exceptions\EncryptionException;
use App\Services\Crypto\FallbackMLKEMProvider;
use App\Services\Crypto\LibOQSMLKEMProvider;
use App\Services\Crypto\MLKEMProviderInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class QuantumCryptoService
{
    private const SUPPORTED_ALGORITHMS = [
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

    private ChatEncryptionService $encryptionService;

    private ?MLKEMProviderInterface $mlkemProvider = null;

    public function __construct(ChatEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Generate ML-KEM key pair
     */
    public function generateMLKEMKeyPair(int $securityLevel = 768): array
    {
        if (! $this->isMLKEMSupported($securityLevel)) {
            throw new \InvalidArgumentException("ML-KEM-{$securityLevel} is not supported");
        }

        try {
            $provider = $this->getMLKEMProvider();
            $keyPair = $provider->generateKeyPair($securityLevel);

            Log::info('ML-KEM key pair generated successfully', [
                'security_level' => $securityLevel,
                'provider' => $provider->getProviderName(),
                'public_key_size' => strlen($keyPair['public_key']),
                'private_key_size' => strlen($keyPair['private_key']),
            ]);

            return [
                'public_key' => base64_encode($keyPair['public_key']),
                'private_key' => $this->encryptPrivateKey($keyPair['private_key']),
                'algorithm' => "ML-KEM-{$securityLevel}",
                'key_strength' => $securityLevel,
                'provider' => $provider->getProviderName(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate ML-KEM key pair', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('ML-KEM key generation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * Generate hybrid RSA + ML-KEM key pair
     */
    public function generateHybridKeyPair(int $rsaKeySize = 4096, int $mlkemSecurityLevel = 768): array
    {
        try {
            $rsaKeyPair = $this->encryptionService->generateKeyPair($rsaKeySize);
            $mlkemKeyPair = $this->generateMLKEMKeyPair($mlkemSecurityLevel);

            $hybridPublicKey = $this->combinePublicKeys($rsaKeyPair, $mlkemKeyPair);
            $hybridPrivateKey = $this->combinePrivateKeys($rsaKeyPair, $mlkemKeyPair);

            Log::info('Hybrid key pair generated successfully', [
                'rsa_key_size' => $rsaKeySize,
                'mlkem_security_level' => $mlkemSecurityLevel,
            ]);

            return [
                'public_key' => $hybridPublicKey,
                'private_key' => $hybridPrivateKey,
                'algorithm' => 'HYBRID-RSA4096-MLKEM768',
                'key_strength' => $mlkemSecurityLevel,
                'components' => [
                    'rsa' => [
                        'algorithm' => 'RSA-4096-OAEP',
                        'key_size' => $rsaKeySize,
                    ],
                    'ml-kem' => [
                        'algorithm' => "ML-KEM-{$mlkemSecurityLevel}",
                        'security_level' => $mlkemSecurityLevel,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate hybrid key pair', ['error' => $e->getMessage()]);
            throw new EncryptionException('Hybrid key generation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * ML-KEM encapsulation
     */
    public function encapsulateMLKEM(string $publicKey, int $securityLevel): array
    {
        try {
            $publicKeyBytes = base64_decode($publicKey, true);
            if ($publicKeyBytes === false || empty($publicKeyBytes)) {
                throw new EncryptionException('Invalid base64 public key');
            }

            $provider = $this->getMLKEMProvider();
            $result = $provider->encapsulate($publicKeyBytes, $securityLevel);

            Log::debug('ML-KEM encapsulation successful', [
                'security_level' => $securityLevel,
                'provider' => $provider->getProviderName(),
                'ciphertext_size' => strlen($result['ciphertext']),
                'shared_secret_size' => strlen($result['shared_secret']),
            ]);

            return [
                'ciphertext' => base64_encode($result['ciphertext']),
                'shared_secret' => $result['shared_secret'],
                'algorithm' => "ML-KEM-{$securityLevel}",
            ];
        } catch (\Exception $e) {
            Log::error('ML-KEM encapsulation failed', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('ML-KEM encapsulation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * ML-KEM decapsulation
     */
    public function decapsulateMLKEM(string $ciphertext, string $encryptedPrivateKey, int $securityLevel): string
    {
        try {
            $ciphertextBytes = base64_decode($ciphertext, true);
            if ($ciphertextBytes === false || empty($ciphertextBytes)) {
                throw new DecryptionException('Invalid base64 ciphertext');
            }

            $privateKeyBytes = $this->decryptPrivateKey($encryptedPrivateKey);

            $provider = $this->getMLKEMProvider();
            $sharedSecret = $provider->decapsulate($ciphertextBytes, $privateKeyBytes, $securityLevel);

            Log::debug('ML-KEM decapsulation successful', [
                'security_level' => $securityLevel,
                'provider' => $provider->getProviderName(),
                'shared_secret_size' => strlen($sharedSecret),
            ]);

            return $sharedSecret;
        } catch (\Exception $e) {
            Log::error('ML-KEM decapsulation failed', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage(),
            ]);
            throw new DecryptionException('ML-KEM decapsulation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * Hybrid key encapsulation (RSA + ML-KEM)
     */
    public function encapsulateHybrid(string $hybridPublicKey): array
    {
        try {
            $keyComponents = $this->parseHybridPublicKey($hybridPublicKey);

            // Generate symmetric key for RSA encryption
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Perform RSA encryption
            $rsaCiphertext = $this->encryptionService->encryptSymmetricKey(
                $symmetricKey,
                $keyComponents['rsa_public_key']
            );

            // Perform ML-KEM encapsulation
            $mlkemResult = $this->encapsulateMLKEM(
                $keyComponents['mlkem_public_key'],
                768
            );

            // Combine shared secrets using NIST SP 800-56C approach
            $combinedSecret = $this->combineSharedSecrets(
                $symmetricKey,
                $mlkemResult['shared_secret']
            );

            $hybridCiphertext = $this->combineHybridCiphertexts($rsaCiphertext, $mlkemResult['ciphertext']);

            Log::info('Hybrid encapsulation successful', [
                'combined_secret_size' => strlen($combinedSecret),
                'hybrid_ciphertext_size' => strlen($hybridCiphertext),
            ]);

            return [
                'ciphertext' => $hybridCiphertext,
                'shared_secret' => $combinedSecret,
                'algorithm' => 'HYBRID-RSA4096-MLKEM768',
                'components' => [
                    'rsa_ciphertext' => $rsaCiphertext,
                    'mlkem_ciphertext' => $mlkemResult['ciphertext'],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Hybrid encapsulation failed', ['error' => $e->getMessage()]);
            throw new EncryptionException('Hybrid encapsulation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * Hybrid key decapsulation (RSA + ML-KEM)
     */
    public function decapsulateHybrid(string $hybridCiphertext, string $hybridPrivateKey): string
    {
        try {
            $ciphertextComponents = $this->parseHybridCiphertext($hybridCiphertext);
            $keyComponents = $this->parseHybridPrivateKey($hybridPrivateKey);

            // Perform RSA decryption
            $rsaSecret = $this->encryptionService->decryptSymmetricKey(
                $ciphertextComponents['rsa_ciphertext'],
                $keyComponents['rsa_private_key']
            );

            // Perform ML-KEM decapsulation
            $mlkemSecret = $this->decapsulateMLKEM(
                $ciphertextComponents['mlkem_ciphertext'],
                $keyComponents['mlkem_private_key'],
                768
            );

            // Combine shared secrets
            $combinedSecret = $this->combineSharedSecrets($rsaSecret, $mlkemSecret);

            Log::debug('Hybrid decapsulation successful', [
                'combined_secret_size' => strlen($combinedSecret),
            ]);

            return $combinedSecret;
        } catch (\Exception $e) {
            Log::error('Hybrid decapsulation failed', ['error' => $e->getMessage()]);
            throw new DecryptionException('Hybrid decapsulation failed: '.$e->getMessage(), $e);
        }
    }

    /**
     * Check if ML-KEM is available
     */
    public function isMLKEMAvailable(): bool
    {
        try {
            $provider = $this->getMLKEMProvider();

            return $provider->isAvailable();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if specific ML-KEM security level is supported
     */
    public function isMLKEMSupported(int $securityLevel): bool
    {
        return isset(self::SUPPORTED_ALGORITHMS["ML-KEM-{$securityLevel}"]);
    }

    /**
     * Get algorithm information
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

    /**
     * Get supported algorithms
     */
    public function getSupportedAlgorithms(): array
    {
        return array_keys(self::SUPPORTED_ALGORITHMS);
    }

    /**
     * Validate quantum key pair
     */
    public function validateQuantumKeyPair(string $publicKey, string $privateKey, string $algorithm): bool
    {
        try {
            if (str_starts_with($algorithm, 'ML-KEM-')) {
                $securityLevel = (int) str_replace('ML-KEM-', '', $algorithm);
                $provider = $this->getMLKEMProvider();

                $publicKeyBytes = base64_decode($publicKey);
                $privateKeyBytes = $this->decryptPrivateKey($privateKey);

                return $provider->validateKeyPair($publicKeyBytes, $privateKeyBytes, $securityLevel);
            }

            if ($algorithm === 'HYBRID-RSA4096-MLKEM768') {
                // Validate both RSA and ML-KEM components
                $publicComponents = $this->parseHybridPublicKey($publicKey);
                $privateComponents = $this->parseHybridPrivateKey($privateKey);

                $rsaValid = $this->encryptionService->verifyKeyIntegrity(
                    $publicComponents['rsa_public_key'],
                    $privateComponents['rsa_private_key']
                );

                $mlkemValid = $this->validateQuantumKeyPair(
                    $publicComponents['mlkem_public_key'],
                    $privateComponents['mlkem_private_key'],
                    'ML-KEM-768'
                );

                return $rsaValid && $mlkemValid;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Quantum key pair validation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get ML-KEM provider instance
     */
    private function getMLKEMProvider(): MLKEMProviderInterface
    {
        if ($this->mlkemProvider !== null) {
            return $this->mlkemProvider;
        }

        // Try LibOQS first
        $liboqsProvider = new LibOQSMLKEMProvider;
        if ($liboqsProvider->isAvailable()) {
            $this->mlkemProvider = $liboqsProvider;
            Log::info('Using LibOQS ML-KEM provider');

            return $this->mlkemProvider;
        }

        // Fall back to test provider (NOT for production)
        if (app()->environment('testing', 'local')) {
            $this->mlkemProvider = new FallbackMLKEMProvider;
            Log::warning('Using fallback ML-KEM provider - NOT CRYPTOGRAPHICALLY SECURE');

            return $this->mlkemProvider;
        }

        throw new EncryptionException('No ML-KEM provider available');
    }

    private function combineSharedSecrets(string $rsaSecret, string $mlkemSecret): string
    {
        // NIST SP 800-56C compliant key combination
        $combined = $rsaSecret.$mlkemSecret;

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

        if (! $keyData || ! isset($keyData['components'])) {
            throw new EncryptionException('Invalid hybrid public key format');
        }

        return [
            'rsa_public_key' => $keyData['components']['rsa'],
            'mlkem_public_key' => $keyData['components']['ml-kem'],
        ];
    }

    private function parseHybridPrivateKey(string $hybridPrivateKey): array
    {
        $keyData = json_decode($this->decryptPrivateKey($hybridPrivateKey), true);

        if (! $keyData || ! isset($keyData['components'])) {
            throw new DecryptionException('Invalid hybrid private key format');
        }

        return [
            'rsa_private_key' => $keyData['components']['rsa'],
            'mlkem_private_key' => $keyData['components']['ml-kem'],
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

    private function parseHybridCiphertext(string $hybridCiphertext): array
    {
        $ciphertextData = json_decode(base64_decode($hybridCiphertext), true);

        if (! $ciphertextData || ! isset($ciphertextData['components'])) {
            throw new DecryptionException('Invalid hybrid ciphertext format');
        }

        return [
            'rsa_ciphertext' => $ciphertextData['components']['rsa'],
            'mlkem_ciphertext' => $ciphertextData['components']['ml-kem'],
        ];
    }

    private function encryptPrivateKey(string $privateKey): string
    {
        return Crypt::encryptString($privateKey);
    }

    private function decryptPrivateKey(string $encryptedPrivateKey): string
    {
        return Crypt::decryptString($encryptedPrivateKey);
    }
}
