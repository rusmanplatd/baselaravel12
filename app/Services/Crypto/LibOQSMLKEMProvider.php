<?php

namespace App\Services\Crypto;

use App\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Log;

class LibOQSMLKEMProvider implements MLKEMProviderInterface
{
    private const ALGORITHM_MAP = [
        512 => 'Kyber512',
        768 => 'Kyber768',
        1024 => 'Kyber1024',
    ];

    public function generateKeyPair(int $securityLevel): array
    {
        if (!$this->isAvailable()) {
            throw new EncryptionException('LibOQS extension not available');
        }

        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        try {
            $kem = new \OQS\KEM($algorithm);
            $keypair = $kem->keypair();
            
            Log::info('LibOQS ML-KEM key pair generated', [
                'algorithm' => $algorithm,
                'security_level' => $securityLevel,
                'public_key_size' => strlen($keypair[0]),
                'private_key_size' => strlen($keypair[1]),
            ]);
            
            return [
                'public_key' => $keypair[0],
                'private_key' => $keypair[1],
            ];
        } catch (\Exception $e) {
            Log::error('LibOQS key generation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('LibOQS key generation failed: ' . $e->getMessage(), $e);
        }
    }

    public function encapsulate(string $publicKey, int $securityLevel): array
    {
        if (!$this->isAvailable()) {
            throw new EncryptionException('LibOQS extension not available');
        }

        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        try {
            $kem = new \OQS\KEM($algorithm);
            $result = $kem->encaps($publicKey);
            
            Log::debug('LibOQS ML-KEM encapsulation successful', [
                'algorithm' => $algorithm,
                'ciphertext_size' => strlen($result[0]),
                'shared_secret_size' => strlen($result[1]),
            ]);
            
            return [
                'ciphertext' => $result[0],
                'shared_secret' => $result[1],
            ];
        } catch (\Exception $e) {
            Log::error('LibOQS encapsulation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('LibOQS encapsulation failed: ' . $e->getMessage(), $e);
        }
    }

    public function decapsulate(string $ciphertext, string $privateKey, int $securityLevel): string
    {
        if (!$this->isAvailable()) {
            throw new EncryptionException('LibOQS extension not available');
        }

        $algorithm = self::ALGORITHM_MAP[$securityLevel] ?? throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        
        try {
            $kem = new \OQS\KEM($algorithm);
            $sharedSecret = $kem->decaps($ciphertext, $privateKey);
            
            Log::debug('LibOQS ML-KEM decapsulation successful', [
                'algorithm' => $algorithm,
                'shared_secret_size' => strlen($sharedSecret),
            ]);
            
            return $sharedSecret;
        } catch (\Exception $e) {
            Log::error('LibOQS decapsulation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
            throw new EncryptionException('LibOQS decapsulation failed: ' . $e->getMessage(), $e);
        }
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
            Log::warning('LibOQS key pair validation failed', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isAvailable(): bool
    {
        return extension_loaded('liboqs') && class_exists('\OQS\KEM');
    }

    public function getProviderName(): string
    {
        return 'LibOQS';
    }
}