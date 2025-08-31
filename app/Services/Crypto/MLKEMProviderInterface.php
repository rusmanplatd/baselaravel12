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

    /**
     * Check if provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get provider name
     */
    public function getProviderName(): string;
}