<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Deprecated: This service contained server-side file encryption/decryption logic
 * which is incompatible with proper end-to-end encryption.
 *
 * In true E2EE, all file encryption operations happen on the client side.
 * The server only stores and forwards encrypted files as opaque blobs.
 */
class EncryptedFileService
{
    public function __construct()
    {
        Log::info('EncryptedFileService deprecated - server-side file encryption disabled for E2EE');
    }

    /**
     * @deprecated Server-side file encryption violates E2EE principles
     */
    public function uploadEncryptedFile(
        UploadedFile $file,
        User $uploader,
        Conversation $conversation,
        string $deviceId,
        array $options = []
    ): array {
        throw new Exception('Server-side file encryption is disabled. Use client-side E2EE file encryption instead.');
    }

    /**
     * @deprecated Server-side file decryption violates E2EE principles
     */
    public function downloadEncryptedFile(string $fileId, User $user, string $deviceId): array
    {
        throw new Exception('Server-side file decryption is disabled. Use client-side E2EE file decryption instead.');
    }

    /**
     * @deprecated Server-side thumbnail generation violates E2EE principles
     */
    public function generateEncryptedThumbnail(UploadedFile $file, string $key): ?string
    {
        throw new Exception('Server-side thumbnail encryption is disabled. Use client-side E2EE thumbnail generation instead.');
    }
}
