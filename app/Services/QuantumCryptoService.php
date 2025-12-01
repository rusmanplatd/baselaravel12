<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Deprecated: This service contained server-side quantum encryption/decryption logic
 * which is incompatible with proper end-to-end encryption.
 *
 * In true E2EE, all quantum cryptography operations happen on the client side.
 * The server only stores and forwards encrypted data as opaque blobs.
 */
class QuantumCryptoService
{
    public function __construct()
    {
        Log::info('QuantumCryptoService deprecated - server-side quantum crypto disabled for E2EE');
    }

    /**
     * @deprecated Server-side quantum key generation violates E2EE principles
     */
    public function generateKeyPair(string $algorithm = 'ML-KEM-768'): array
    {
        throw new Exception('Server-side quantum key generation is disabled. Use client-side quantum crypto for E2EE.');
    }

    /**
     * @deprecated Server-side quantum encapsulation violates E2EE principles
     */
    public function encapsulate(string $publicKey, string $algorithm = 'ML-KEM-768'): array
    {
        throw new Exception('Server-side quantum encapsulation is disabled. Use client-side quantum crypto for E2EE.');
    }

    /**
     * @deprecated Server-side quantum decapsulation violates E2EE principles
     */
    public function decapsulate(string $ciphertext, string $privateKey, string $algorithm = 'ML-KEM-768'): string
    {
        throw new Exception('Server-side quantum decapsulation is disabled. Use client-side quantum crypto for E2EE.');
    }

    /**
     * @deprecated Server-side encryption violates E2EE principles
     */
    public function encryptForConversation(string $content, $conversation, $user): array
    {
        throw new Exception('Server-side encryption is disabled. Use client-side E2EE encryption instead.');
    }

    /**
     * @deprecated Server-side decryption violates E2EE principles
     */
    public function decryptMessage($message, $user): string
    {
        throw new Exception('Server-side decryption is disabled. Use client-side E2EE decryption instead.');
    }

    /**
     * @deprecated Server-side key rotation violates E2EE principles
     */
    public function rotateConversationKeys($conversation): void
    {
        throw new Exception('Server-side key rotation is disabled. Handle key rotation on client side for E2EE.');
    }
}
