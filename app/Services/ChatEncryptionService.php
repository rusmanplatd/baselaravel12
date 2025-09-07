<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;

/**
 * Deprecated: This service contained server-side encryption/decryption logic
 * which is incompatible with proper end-to-end encryption.
 *
 * In true E2EE, all encryption/decryption happens on the client side.
 * The server only stores and forwards encrypted data as opaque blobs.
 */
class ChatEncryptionService
{
    public function __construct() {}

    /**
     * @deprecated Server-side encryption violates E2EE principles
     * Encryption should happen on the client side
     */
    public function encryptMessage(string $content, Conversation $conversation, User $user): array
    {
        throw new \Exception('Server-side encryption is disabled. Use client-side E2EE encryption instead.');
    }

    /**
     * @deprecated Server-side decryption violates E2EE principles
     * Decryption should happen on the client side
     */
    public function decryptMessage(Message $message, User $user): ?string
    {
        throw new \Exception('Server-side decryption is disabled. Use client-side E2EE decryption instead.');
    }

    /**
     * @deprecated Key rotation should be handled client-side for E2EE
     */
    public function rotateConversationKeys(Conversation $conversation): void
    {
        throw new \Exception('Server-side key rotation is disabled. Handle key rotation on client side for E2EE.');
    }
}
