<?php

namespace App\Services\Crypto;

use App\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Log;

/**
 * Fallback ML-KEM provider for development/testing when LibOQS is not available
 * This is NOT cryptographically secure and should only be used for testing
 */
class FallbackMLKEMProvider implements MLKEMProviderInterface
{
    private const KEY_SIZES = [
        512 => ['public' => 800, 'private' => 1632, 'ciphertext' => 768],
        768 => ['public' => 1184, 'private' => 2400, 'ciphertext' => 1088],
        1024 => ['public' => 1568, 'private' => 3168, 'ciphertext' => 1568],
    ];

    public function generateKeyPair(int $securityLevel): array
    {
        if (!isset(self::KEY_SIZES[$securityLevel])) {
            throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        }

        $sizes = self::KEY_SIZES[$securityLevel];
        
        // Generate fake keys with correct sizes for testing
        $publicKey = random_bytes($sizes['public']);
        $privateKey = random_bytes($sizes['private']);
        
        // Store a hash for key pair validation
        $keyHash = hash('sha256', $publicKey . $privateKey);
        cache()->put('fallback_keypair_' . hash('sha256', $publicKey), $keyHash, now()->addMinutes(10));
        
        Log::warning('Using fallback ML-KEM provider - NOT CRYPTOGRAPHICALLY SECURE', [
            'security_level' => $securityLevel,
            'public_key_size' => strlen($publicKey),
            'private_key_size' => strlen($privateKey),
        ]);
        
        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ];
    }

    public function encapsulate(string $publicKey, int $securityLevel): array
    {
        if (!isset(self::KEY_SIZES[$securityLevel])) {
            throw new \InvalidArgumentException("Unsupported security level: {$securityLevel}");
        }

        $sizes = self::KEY_SIZES[$securityLevel];
        
        // Generate fake ciphertext and shared secret
        $ciphertext = random_bytes($sizes['ciphertext']);
        $sharedSecret = random_bytes(32); // ML-KEM always produces 32-byte shared secrets
        
        // Store the mapping for decapsulation (in real implementation, this would be cryptographically derived)
        $this->storeFakeMapping($ciphertext, $sharedSecret);
        
        Log::debug('Fallback ML-KEM encapsulation (NOT SECURE)', [
            'security_level' => $securityLevel,
            'ciphertext_size' => strlen($ciphertext),
            'shared_secret_size' => strlen($sharedSecret),
        ]);
        
        return [
            'ciphertext' => $ciphertext,
            'shared_secret' => $sharedSecret,
        ];
    }

    public function decapsulate(string $ciphertext, string $privateKey, int $securityLevel): string
    {
        // Retrieve the fake mapping (in real implementation, this would be cryptographically derived)
        $sharedSecret = $this->retrieveFakeMapping($ciphertext);
        
        if (!$sharedSecret) {
            throw new EncryptionException('Fallback decapsulation failed - mapping not found');
        }
        
        Log::debug('Fallback ML-KEM decapsulation (NOT SECURE)', [
            'security_level' => $securityLevel,
            'shared_secret_size' => strlen($sharedSecret),
        ]);
        
        return $sharedSecret;
    }

    public function getSupportedLevels(): array
    {
        return [512, 768, 1024];
    }

    public function validateKeyPair(string $publicKey, string $privateKey, int $securityLevel): bool
    {
        try {
            // Simple validation - check key sizes
            $expectedSizes = self::KEY_SIZES[$securityLevel] ?? null;
            if (!$expectedSizes) {
                return false;
            }
            
            $sizeValid = strlen($publicKey) === $expectedSizes['public'] 
                && strlen($privateKey) === $expectedSizes['private'];
                
            if (!$sizeValid) {
                return false;
            }
            
            // For the fallback provider, we can't do real cryptographic validation
            // but we can check if the keys were generated together by comparing a hash
            // This is obviously not secure but works for testing key pair matching
            $keyHash = hash('sha256', $publicKey . $privateKey);
            $storedHash = cache()->get('fallback_keypair_' . hash('sha256', $publicKey));
            
            return $storedHash === $keyHash;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isAvailable(): bool
    {
        return true; // Always available as fallback
    }

    public function getProviderName(): string
    {
        return 'Fallback (NOT SECURE)';
    }

    private function storeFakeMapping(string $ciphertext, string $sharedSecret): void
    {
        $key = 'fallback_mlkem_' . hash('sha256', $ciphertext);
        cache()->put($key, $sharedSecret, now()->addMinutes(5));
    }

    private function retrieveFakeMapping(string $ciphertext): ?string
    {
        $key = 'fallback_mlkem_' . hash('sha256', $ciphertext);
        return cache()->get($key);
    }
}