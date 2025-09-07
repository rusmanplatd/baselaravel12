<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Deprecated: This service contained server-side group encryption/decryption logic
 * which is incompatible with proper end-to-end encryption.
 *
 * In true E2EE, all group encryption operations happen on the client side.
 * The server only stores and forwards encrypted data as opaque blobs.
 */
class GroupEncryptionService
{
    public function __construct()
    {
        Log::info('GroupEncryptionService deprecated - server-side group encryption disabled for E2EE');
    }

    /**
     * @deprecated Server-side group encryption initialization violates E2EE principles
     */
    public function initializeGroupEncryption(
        Conversation $conversation,
        User $creator,
        string $encryptionMode = 'standard'
    ): void {
        throw new Exception('Server-side group encryption is disabled. Use client-side E2EE group encryption instead.');
    }

    /**
     * @deprecated Server-side encryption violates E2EE principles
     */
    public function encryptMessage(string $content, Conversation $conversation, User $user): array
    {
        throw new Exception('Server-side encryption is disabled. Use client-side E2EE encryption instead.');
    }

    /**
     * @deprecated Server-side decryption violates E2EE principles
     */
    public function decryptMessage($message, User $user): string
    {
        throw new Exception('Server-side decryption is disabled. Use client-side E2EE decryption instead.');
    }

    /**
     * @deprecated Server-side key rotation violates E2EE principles
     */
    public function rotateConversationKeys(Conversation $conversation): void
    {
        throw new Exception('Server-side key rotation is disabled. Handle key rotation on client side for E2EE.');
    }
}
