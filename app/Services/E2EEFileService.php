<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * E2EE File Service
 * 
 * Handles encrypted file storage where files are encrypted client-side
 * before being sent to the server. The server stores encrypted blobs
 * without any knowledge of the original content.
 */
class E2EEFileService
{
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const ALLOWED_MIME_TYPES = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf', 'text/plain', 'text/markdown',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Audio
        'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac', 'audio/mpeg',
        // Video
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/avi',
        // Archives
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/gzip', 'application/x-tar',
    ];

    public function __construct(
        private readonly string $diskName = 'minio'
    ) {}

    /**
     * Store an encrypted file blob
     * 
     * @param UploadedFile $encryptedFile The pre-encrypted file from client
     * @param User $uploader
     * @param Conversation $conversation
     * @param string $deviceId
     * @param array $metadata Client-provided metadata (filename, mime_type, etc.)
     * @return array File info including storage path and metadata
     * @throws ValidationException
     * @throws Exception
     */
    public function storeEncryptedFile(
        UploadedFile $encryptedFile,
        User $uploader,
        Conversation $conversation,
        string $deviceId,
        array $metadata = []
    ): array {
        // Validate file size
        if ($encryptedFile->getSize() > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages([
                'file' => ['File size exceeds maximum allowed size of 100MB']
            ]);
        }

        // Extract and validate metadata
        $originalFilename = $metadata['original_filename'] ?? $encryptedFile->getClientOriginalName();
        $originalMimeType = $metadata['original_mime_type'] ?? 'application/octet-stream';
        $originalSize = (int)($metadata['original_size'] ?? 0);
        $fileHash = $metadata['file_hash'] ?? '';
        $encryptionKeyData = $metadata['encryption_key_data'] ?? [];

        // Validate original mime type
        if (!in_array($originalMimeType, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'file' => ['File type not allowed: ' . $originalMimeType]
            ]);
        }

        // Generate unique file ID and storage path
        $fileId = Str::ulid();
        $storagePath = $this->generateStoragePath($conversation->id, $fileId);

        try {
            // Store the encrypted blob without any knowledge of its content
            $disk = Storage::disk($this->diskName);
            $storedPath = $disk->putFileAs(
                dirname($storagePath),
                $encryptedFile,
                basename($storagePath)
            );

            if (!$storedPath) {
                throw new Exception('Failed to store encrypted file');
            }

            // Handle thumbnail if provided
            $thumbnailPath = null;
            if (isset($metadata['encrypted_thumbnail']) && $metadata['encrypted_thumbnail']) {
                $thumbnailPath = $this->storeThumbnail($conversation->id, $fileId, $metadata['encrypted_thumbnail']);
            }

            Log::info('Encrypted file stored successfully', [
                'file_id' => $fileId,
                'user_id' => $uploader->id,
                'conversation_id' => $conversation->id,
                'original_filename' => $originalFilename,
                'original_size' => $originalSize,
                'encrypted_size' => $encryptedFile->getSize(),
                'storage_path' => $storedPath,
                'has_thumbnail' => !is_null($thumbnailPath),
            ]);

            return [
                'file_id' => $fileId,
                'storage_path' => $storedPath,
                'encrypted_filename' => basename($storedPath),
                'original_filename' => $originalFilename,
                'original_mime_type' => $originalMimeType,
                'original_size' => $originalSize,
                'encrypted_size' => $encryptedFile->getSize(),
                'file_hash' => $fileHash,
                'encryption_key_data' => $encryptionKeyData,
                'thumbnail_path' => $thumbnailPath,
                'metadata' => [
                    'uploaded_at' => now()->toISOString(),
                    'uploader_device_id' => $deviceId,
                    'storage_backend' => $this->diskName,
                    'file_extension' => pathinfo($originalFilename, PATHINFO_EXTENSION),
                    'is_image' => str_starts_with($originalMimeType, 'image/'),
                    'is_video' => str_starts_with($originalMimeType, 'video/'),
                    'is_audio' => str_starts_with($originalMimeType, 'audio/'),
                    'supports_preview' => $this->supportsPreview($originalMimeType),
                ],
            ];

        } catch (Exception $e) {
            // Cleanup on failure
            if (isset($storedPath)) {
                Storage::disk($this->diskName)->delete($storedPath);
            }
            if (isset($thumbnailPath)) {
                Storage::disk($this->diskName)->delete($thumbnailPath);
            }

            Log::error('Failed to store encrypted file', [
                'error' => $e->getMessage(),
                'user_id' => $uploader->id,
                'conversation_id' => $conversation->id,
                'file_size' => $encryptedFile->getSize(),
            ]);

            throw new Exception('Failed to store encrypted file: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve an encrypted file blob
     * 
     * @param MessageFile $messageFile
     * @param User $user
     * @param string $deviceId
     * @return array Encrypted file content and metadata
     * @throws Exception
     */
    public function retrieveEncryptedFile(
        MessageFile $messageFile,
        User $user,
        string $deviceId
    ): array {
        try {
            $disk = Storage::disk($this->diskName);
            $storagePath = $this->getStoragePathFromMessageFile($messageFile);

            if (!$disk->exists($storagePath)) {
                throw new Exception('Encrypted file not found in storage');
            }

            $encryptedContent = $disk->get($storagePath);

            Log::info('Encrypted file retrieved', [
                'file_id' => $messageFile->id,
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'file_size' => strlen($encryptedContent),
            ]);

            return [
                'encrypted_content' => $encryptedContent,
                'original_filename' => $messageFile->original_filename,
                'original_mime_type' => $messageFile->mime_type,
                'original_size' => $messageFile->file_size,
                'encrypted_size' => $messageFile->encrypted_size,
                'file_hash' => $messageFile->file_hash,
                'encryption_key_data' => $messageFile->encryption_key_encrypted,
                'metadata' => json_decode($messageFile->metadata, true) ?? [],
            ];

        } catch (Exception $e) {
            Log::error('Failed to retrieve encrypted file', [
                'error' => $e->getMessage(),
                'file_id' => $messageFile->id,
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ]);

            throw new Exception('Failed to retrieve encrypted file: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve an encrypted thumbnail
     */
    public function retrieveEncryptedThumbnail(
        MessageFile $messageFile,
        User $user,
        string $deviceId
    ): ?array {
        if (!$messageFile->thumbnail_path) {
            return null;
        }

        try {
            $disk = Storage::disk($this->diskName);

            if (!$disk->exists($messageFile->thumbnail_path)) {
                return null;
            }

            $encryptedThumbnail = $disk->get($messageFile->thumbnail_path);

            return [
                'encrypted_content' => $encryptedThumbnail,
                'size' => strlen($encryptedThumbnail),
            ];

        } catch (Exception $e) {
            Log::warning('Failed to retrieve encrypted thumbnail', [
                'error' => $e->getMessage(),
                'file_id' => $messageFile->id,
                'thumbnail_path' => $messageFile->thumbnail_path,
            ]);

            return null;
        }
    }

    /**
     * Delete an encrypted file and its thumbnail
     */
    public function deleteEncryptedFile(MessageFile $messageFile): bool
    {
        try {
            $disk = Storage::disk($this->diskName);
            $storagePath = $this->getStoragePathFromMessageFile($messageFile);

            // Delete main file
            $mainDeleted = true;
            if ($disk->exists($storagePath)) {
                $mainDeleted = $disk->delete($storagePath);
            }

            // Delete thumbnail
            $thumbnailDeleted = true;
            if ($messageFile->thumbnail_path && $disk->exists($messageFile->thumbnail_path)) {
                $thumbnailDeleted = $disk->delete($messageFile->thumbnail_path);
            }

            $success = $mainDeleted && $thumbnailDeleted;

            Log::info('Encrypted file deletion', [
                'file_id' => $messageFile->id,
                'success' => $success,
                'main_file_deleted' => $mainDeleted,
                'thumbnail_deleted' => $thumbnailDeleted,
            ]);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to delete encrypted file', [
                'error' => $e->getMessage(),
                'file_id' => $messageFile->id,
            ]);

            return false;
        }
    }

    /**
     * Generate storage path for file
     */
    private function generateStoragePath(string $conversationId, string $fileId): string
    {
        // Organize files by conversation and date for better performance
        $datePath = now()->format('Y/m/d');
        return "chat-files/{$conversationId}/{$datePath}/{$fileId}.encrypted";
    }

    /**
     * Get storage path from MessageFile model
     */
    private function getStoragePathFromMessageFile(MessageFile $messageFile): string
    {
        // Extract from metadata or reconstruct
        $metadata = json_decode($messageFile->metadata, true) ?? [];
        if (isset($metadata['storage_path'])) {
            return $metadata['storage_path'];
        }

        // Fallback: reconstruct path (older files)
        return "chat-files/{$messageFile->message->conversation_id}/files/{$messageFile->encrypted_filename}";
    }

    /**
     * Store encrypted thumbnail
     */
    private function storeThumbnail(string $conversationId, string $fileId, string $encryptedThumbnailData): ?string
    {
        try {
            $thumbnailPath = "chat-files/{$conversationId}/thumbnails/{$fileId}.thumb.encrypted";
            $disk = Storage::disk($this->diskName);

            // Decode base64 thumbnail data
            $thumbnailContent = base64_decode($encryptedThumbnailData);
            if ($thumbnailContent === false) {
                throw new Exception('Invalid thumbnail data');
            }

            $success = $disk->put($thumbnailPath, $thumbnailContent);
            return $success ? $thumbnailPath : null;

        } catch (Exception $e) {
            Log::warning('Failed to store encrypted thumbnail', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'file_id' => $fileId,
            ]);
            return null;
        }
    }

    /**
     * Check if file type supports preview
     */
    private function supportsPreview(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') ||
               str_starts_with($mimeType, 'video/') ||
               str_starts_with($mimeType, 'audio/') ||
               $mimeType === 'application/pdf' ||
               $mimeType === 'text/plain';
    }

    /**
     * Get file type category
     */
    public static function getFileTypeCategory(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/markdown'
        ])) {
            return 'document';
        } elseif (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar'
        ])) {
            return 'archive';
        } else {
            return 'file';
        }
    }

    /**
     * Bulk file operations for better performance
     */
    public function bulkStoreEncryptedFiles(
        array $encryptedFiles,
        User $uploader,
        Conversation $conversation,
        string $deviceId,
        array $options = []
    ): array {
        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($encryptedFiles as $index => $fileData) {
            try {
                $result = $this->storeEncryptedFile(
                    $fileData['file'],
                    $uploader,
                    $conversation,
                    $deviceId,
                    $fileData['metadata'] ?? []
                );

                $results['successful'][] = [
                    'index' => $index,
                    'result' => $result,
                ];

            } catch (Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'filename' => $fileData['metadata']['original_filename'] ?? 'unknown',
                ];
            }
        }

        return $results;
    }
}