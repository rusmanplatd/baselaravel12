<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\User;
use App\Models\UserDevice;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class EncryptedFileService
{
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

    private const CHUNK_SIZE = 1024 * 1024; // 1MB chunks

    private const THUMBNAIL_SIZE = 200;

    public function __construct(
        private QuantumCryptoService $quantumService
    ) {}

    /**
     * Upload and encrypt file with chunked processing
     */
    public function uploadEncryptedFile(
        UploadedFile $file,
        User $uploader,
        Conversation $conversation,
        string $deviceId,
        array $options = []
    ): array {
        try {
            // Validate file
            $this->validateFile($file);

            // Generate file encryption key
            $fileKey = random_bytes(32); // AES-256 key
            $fileIv = random_bytes(16);  // AES-256-CBC IV

            // Create unique file identifier
            $fileId = $this->generateFileId();
            $storagePath = "chat/files/{$conversation->id}/{$fileId}";

            // Process file based on type
            $fileMetadata = $this->processFile($file);

            // Encrypt file in chunks
            $encryptedFilePath = $this->encryptFileInChunks($file, $fileKey, $fileIv, $storagePath);

            // Generate encrypted thumbnail if applicable
            $encryptedThumbnail = $this->generateEncryptedThumbnail($file, $fileKey);

            // Create file record
            $fileRecord = [
                'file_id' => $fileId,
                'original_filename' => $file->getClientOriginalName(),
                'encrypted_filename' => basename($encryptedFilePath),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'encrypted_size' => Storage::size($encryptedFilePath),
                'encrypted_storage_path' => encrypt($encryptedFilePath),
                'encryption_keys' => $this->encryptFileKey($fileKey, $conversation, $uploader, $deviceId),
                'file_iv' => base64_encode($fileIv),
                'file_hash' => $this->calculateFileHash($file),
                'thumbnail_path' => $encryptedThumbnail['path'] ?? null,
                'thumbnail_encrypted' => ! empty($encryptedThumbnail),
                'metadata' => $fileMetadata,
                'uploaded_by' => $uploader->id,
                'device_id' => $deviceId,
                'conversation_id' => $conversation->id,
                'encryption_algorithm' => $this->getEncryptionAlgorithm($conversation),
                'created_at' => now(),
            ];

            Log::info('File uploaded and encrypted', [
                'file_id' => $fileId,
                'conversation_id' => $conversation->id,
                'uploader_id' => $uploader->id,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return $fileRecord;

        } catch (Exception $e) {
            Log::error('Failed to upload encrypted file', [
                'conversation_id' => $conversation->id,
                'uploader_id' => $uploader->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Download and decrypt file
     */
    public function downloadDecryptedFile(
        $messageFile,
        User $requester,
        string $deviceId
    ): array {
        try {
            // Check access permissions
            if (! $this->canAccessFile($messageFile, $requester)) {
                throw new Exception('Access denied to file');
            }

            // Get decryption key
            $fileKey = $this->decryptFileKey($messageFile, $requester, $deviceId);

            // Decrypt file
            $encryptedFilePath = $this->getStoragePath($messageFile);
            $decryptedContent = $this->decryptFileInChunks($encryptedFilePath, $fileKey, $this->getFileIv($messageFile));

            // Verify file integrity
            if (! $this->verifyFileIntegrity($decryptedContent, $messageFile->file_hash)) {
                throw new Exception('File integrity verification failed');
            }

            return [
                'content' => $decryptedContent,
                'mime_type' => $messageFile->mime_type,
                'filename' => $messageFile->original_filename,
                'size' => $messageFile->file_size,
                'file_hash' => $messageFile->file_hash,
                'integrity_verified' => true,
            ];

        } catch (Exception $e) {
            Log::error('Failed to download decrypted file', [
                'file_id' => $fileId,
                'requester_id' => $requester->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get file preview (for images/videos)
     */
    public function getFilePreview(
        string $fileId,
        User $requester,
        UserDevice $device
    ): ?array {
        try {
            $attachment = $this->getFileAttachment($fileId);

            if (! $attachment || ! $this->canAccessFile($attachment, $requester)) {
                return null;
            }

            if (! $this->isPreviewableFile($attachment['mime_type'])) {
                return null;
            }

            // Return thumbnail if available
            if ($attachment['thumbnail_encrypted']) {
                $fileKey = $this->decryptFileKey($attachment, $requester, $device);
                $thumbnailData = $this->decryptThumbnail($attachment['thumbnail_encrypted'], $fileKey);

                return [
                    'thumbnail' => base64_encode($thumbnailData),
                    'mime_type' => $attachment['mime_type'],
                    'filename' => $attachment['original_filename'],
                    'size' => $attachment['file_size'],
                    'metadata' => $attachment['metadata'],
                ];
            }

            return [
                'mime_type' => $attachment['mime_type'],
                'filename' => $attachment['original_filename'],
                'size' => $attachment['file_size'],
                'metadata' => $attachment['metadata'],
            ];

        } catch (Exception $e) {
            Log::error('Failed to get file preview', [
                'file_id' => $fileId,
                'requester_id' => $requester->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete encrypted file
     */
    public function deleteEncryptedFile(
        string $fileId,
        User $deleter,
        string $reason = 'User deletion'
    ): bool {
        try {
            $attachment = $this->getFileAttachment($fileId);

            if (! $attachment) {
                return false;
            }

            // Check deletion permissions
            if (! $this->canDeleteFile($attachment, $deleter)) {
                throw new Exception('Insufficient permissions to delete file');
            }

            // Delete encrypted file from storage
            $encryptedFilePath = decrypt($attachment['encrypted_storage_path']);
            if (Storage::disk('private')->exists($encryptedFilePath)) {
                Storage::disk('private')->delete($encryptedFilePath);
            }

            // Mark attachment as deleted
            $this->markAttachmentDeleted($fileId, $deleter->id, $reason);

            Log::info('Encrypted file deleted', [
                'file_id' => $fileId,
                'deleted_by' => $deleter->id,
                'reason' => $reason,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete encrypted file', [
                'file_id' => $fileId,
                'deleter_id' => $deleter->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Bulk encrypt files for group sharing
     */
    public function bulkEncryptFiles(array $files, Conversation $conversation, User $uploader): array
    {
        $results = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                // Get uploader's active device
                $device = $uploader->devices()->active()->first();
                if (! $device) {
                    throw new Exception('No active device found for uploader');
                }

                $result = $this->uploadEncryptedFile($file, $conversation, $uploader, $device);
                $results[] = $result;

            } catch (Exception $e) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'successful' => $results,
            'errors' => $errors,
            'total_processed' => count($files),
            'successful_count' => count($results),
            'error_count' => count($errors),
        ];
    }

    /**
     * Get file sharing statistics
     */
    public function getFileStatistics(Conversation $conversation): array
    {
        // This would query your message_attachments table
        return [
            'total_files' => 0, // Count from database
            'total_size' => 0,  // Sum from database
            'file_types' => [],  // Group by mime_type
            'monthly_uploads' => [], // Files uploaded per month
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new Exception('Invalid file upload');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception('File too large. Maximum size: '.(self::MAX_FILE_SIZE / 1024 / 1024).'MB');
        }

        // Check for potentially dangerous file types
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'php', 'js'];
        $extension = $file->getClientOriginalExtension();

        if (in_array(strtolower($extension), $dangerousExtensions)) {
            throw new Exception('File type not allowed for security reasons');
        }
    }

    /**
     * Process file and extract metadata
     */
    private function processFile(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
        ];

        // Add specific metadata based on file type
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $metadata = array_merge($metadata, $this->getImageMetadata($file));
        } elseif (str_starts_with($file->getMimeType(), 'video/')) {
            $metadata = array_merge($metadata, $this->getVideoMetadata($file));
        } elseif (str_starts_with($file->getMimeType(), 'audio/')) {
            $metadata = array_merge($metadata, $this->getAudioMetadata($file));
        }

        return $metadata;
    }

    /**
     * Encrypt file in chunks for memory efficiency
     */
    private function encryptFileInChunks(UploadedFile $file, string $key, string $iv, string $storagePath): string
    {
        $inputHandle = fopen($file->getRealPath(), 'rb');
        $tempPath = storage_path('app/temp/'.uniqid('enc_', true));
        $outputHandle = fopen($tempPath, 'wb');

        $cipher = 'aes-256-cbc';
        $chunkNumber = 0;

        while (! feof($inputHandle)) {
            $chunk = fread($inputHandle, self::CHUNK_SIZE);

            if ($chunk === false) {
                break;
            }

            // Encrypt chunk
            $encryptedChunk = openssl_encrypt($chunk, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            fwrite($outputHandle, $encryptedChunk);

            // Update IV for next chunk (simple CBC chaining)
            $iv = substr($encryptedChunk, -16);
            $chunkNumber++;
        }

        fclose($inputHandle);
        fclose($outputHandle);

        // Move to final storage location
        $finalPath = "private/{$storagePath}";
        Storage::disk('local')->put($finalPath, file_get_contents($tempPath));
        unlink($tempPath);

        return $finalPath;
    }

    /**
     * Decrypt file in chunks
     */
    private function decryptFileInChunks(string $encryptedFilePath, string $key, string $iv): string
    {
        $inputHandle = Storage::disk('private')->readStream($encryptedFilePath);
        $output = '';
        $cipher = 'aes-256-cbc';

        while (! feof($inputHandle)) {
            $encryptedChunk = fread($inputHandle, self::CHUNK_SIZE + 16); // +16 for padding

            if ($encryptedChunk === false) {
                break;
            }

            $decryptedChunk = openssl_decrypt($encryptedChunk, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            $output .= $decryptedChunk;

            // Update IV for next chunk
            if (strlen($encryptedChunk) >= 16) {
                $iv = substr($encryptedChunk, -16);
            }
        }

        fclose($inputHandle);

        return $output;
    }

    /**
     * Generate encrypted thumbnail for images/videos
     */
    private function generateEncryptedThumbnail(UploadedFile $file, string $fileKey): ?string
    {
        try {
            if (! $this->isPreviewableFile($file->getMimeType())) {
                return null;
            }

            // Generate thumbnail
            $thumbnail = Image::read($file->getRealPath())
                ->resize(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->toJpeg(80);

            // Encrypt thumbnail
            $thumbnailIv = random_bytes(16);
            $encryptedThumbnail = openssl_encrypt(
                $thumbnail->toString(),
                'aes-256-cbc',
                $fileKey,
                OPENSSL_RAW_DATA,
                $thumbnailIv
            );

            return base64_encode($thumbnailIv.$encryptedThumbnail);

        } catch (Exception $e) {
            Log::warning('Failed to generate thumbnail', [
                'mime_type' => $file->getMimeType(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Decrypt thumbnail
     */
    private function decryptThumbnail(string $encryptedThumbnail, string $fileKey): string
    {
        $data = base64_decode($encryptedThumbnail);
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);

        $decrypted = openssl_decrypt($encryptedData, 'aes-256-cbc', $fileKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new Exception('Thumbnail decryption failed');
        }

        return $decrypted;
    }

    /**
     * Encrypt file key for all conversation participants
     */
    private function encryptFileKey(string $fileKey, Conversation $conversation, User $uploader, string $deviceId): array
    {
        $encryptedKeys = [];

        // Get all active participants and their devices
        $participants = $conversation->activeParticipants()->with('user.devices')->get();

        foreach ($participants as $participant) {
            $userDevices = $participant->user->devices()->active()->get();

            foreach ($userDevices as $participantDevice) {
                try {
                    // Encrypt file key for this device
                    if ($participantDevice->quantum_ready) {
                        $encryptedKey = $this->quantumService->encrypt(
                            base64_encode($fileKey),
                            $participantDevice->getPreferredAlgorithm()
                        );
                    } else {
                        // Classical encryption fallback
                        $encryptedKey = openssl_public_encrypt(
                            $fileKey,
                            $ciphertext,
                            $participantDevice->public_key,
                            OPENSSL_PKCS1_OAEP_PADDING
                        );
                        $encryptedKey = ['ciphertext' => base64_encode($ciphertext)];
                    }

                    $encryptedKeys[$participantDevice->id] = $encryptedKey;

                } catch (Exception $e) {
                    Log::warning('Failed to encrypt file key for device', [
                        'device_id' => $participantDevice->id,
                        'user_id' => $participant->user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $encryptedKeys;
    }

    /**
     * Generate unique file ID
     */
    private function generateFileId(): string
    {
        return uniqid('file_', true).'_'.bin2hex(random_bytes(8));
    }

    /**
     * Calculate file hash for integrity verification
     */
    private function calculateFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Verify file integrity
     */
    private function verifyFileIntegrity(string $content, string $expectedHash): bool
    {
        return hash_equals($expectedHash, hash('sha256', $content));
    }

    /**
     * Get encryption algorithm for conversation
     */
    private function getEncryptionAlgorithm(Conversation $conversation): string
    {
        return $conversation->encryption_algorithm ?? 'AES-256-GCM';
    }

    /**
     * Check if file type is previewable
     */
    private function isPreviewableFile(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') ||
               str_starts_with($mimeType, 'video/');
    }

    /**
     * Get image metadata
     */
    private function getImageMetadata(UploadedFile $file): array
    {
        try {
            $image = Image::read($file->getRealPath());

            return [
                'width' => $image->width(),
                'height' => $image->height(),
                'type' => 'image',
            ];
        } catch (Exception $e) {
            return ['type' => 'image'];
        }
    }

    /**
     * Get video metadata (placeholder - would need FFmpeg)
     */
    private function getVideoMetadata(UploadedFile $file): array
    {
        // Would integrate with FFmpeg or similar
        return [
            'type' => 'video',
            'duration' => null,
            'width' => null,
            'height' => null,
        ];
    }

    /**
     * Get audio metadata (placeholder)
     */
    private function getAudioMetadata(UploadedFile $file): array
    {
        return [
            'type' => 'audio',
            'duration' => null,
            'bitrate' => null,
        ];
    }

    /**
     * Get file attachment by ID (would query your database)
     */
    private function getFileAttachment(string $fileId): ?array
    {
        // This would query your message_attachments table
        // For now, returning null as placeholder
        return null;
    }

    /**
     * Check if user can delete file
     */
    private function canDeleteFile(array $attachment, User $user): bool
    {
        // Can delete if user uploaded the file or is admin/moderator
        if ($attachment['uploaded_by'] === $user->id) {
            return true;
        }

        $conversationId = $attachment['conversation_id'];
        $conversation = Conversation::find($conversationId);
        $participant = $conversation?->participants()->where('user_id', $user->id)->first();

        return $participant && $participant->isModerator();
    }

    /**
     * Mark attachment as deleted
     */
    private function markAttachmentDeleted(string $fileId, string $deletedBy, string $reason): void
    {
        // This would update your message_attachments table
        // Mark as deleted rather than actually deleting the record
    }

    /**
     * Get thumbnail for a file
     */
    public function getThumbnail($messageFile, User $user, string $deviceId): ?array
    {
        try {
            if (! $messageFile->hasThumbnail()) {
                return null;
            }

            // Check access permissions
            if (! $this->canAccessFile(['conversation_id' => $messageFile->message->conversation_id], $user)) {
                throw new Exception('Access denied to thumbnail');
            }

            // Get file key
            $fileKey = $this->decryptFileKey($messageFile, $user, $deviceId);

            // Decrypt thumbnail
            $thumbnailData = $this->decryptThumbnail($messageFile->thumbnail_path, $fileKey);

            return [
                'content' => $thumbnailData,
                'mime_type' => 'image/jpeg',
            ];

        } catch (Exception $e) {
            Log::error('Failed to get thumbnail', [
                'file_id' => $messageFile->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Bulk upload multiple files
     */
    public function bulkUploadFiles(
        array $files,
        User $uploader,
        Conversation $conversation,
        string $deviceId,
        array $options = []
    ): array {
        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($files as $index => $file) {
            try {
                $result = $this->uploadEncryptedFile($file, $uploader, $conversation, $deviceId, $options);
                $results['successful'][] = [
                    'index' => $index,
                    'file_id' => $result['file_id'],
                    'original_filename' => $result['original_filename'],
                ];
            } catch (Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Helper methods for file controller integration
     */
    private function canAccessFile($messageFile, User $user): bool
    {
        if (is_array($messageFile)) {
            // Legacy array format
            $conversationId = $messageFile['conversation_id'];
            $conversation = Conversation::find($conversationId);

            return $conversation && $conversation->participants()->where('user_id', $user->id)->exists();
        }

        // MessageFile model
        return $messageFile->message->conversation->participants()->where('user_id', $user->id)->exists();
    }

    private function decryptFileKey($messageFile, User $requester, string $deviceId): string
    {
        if (is_array($messageFile)) {
            // Legacy array format
            return $this->decryptFileKeyArray($messageFile, $requester, $deviceId);
        }

        // MessageFile model
        $encryptionKeys = $messageFile->encryption_key_encrypted;

        if (! isset($encryptionKeys[$deviceId])) {
            throw new Exception('File key not found for this device');
        }

        $encryptedKeyData = $encryptionKeys[$deviceId];

        // For now, assume classical encryption (would need device lookup for quantum)
        $fileKey = base64_decode($encryptedKeyData['key'] ?? $encryptedKeyData);

        return $fileKey;
    }

    private function getStoragePath($messageFile): string
    {
        // Would typically decrypt the stored path
        return storage_path("app/chat/files/{$messageFile->message->conversation_id}/{$messageFile->encrypted_filename}");
    }

    private function getFileIv($messageFile): string
    {
        // Extract IV from metadata or separate field
        $metadata = is_array($messageFile->metadata) ? $messageFile->metadata : json_decode($messageFile->metadata, true);

        return base64_decode($metadata['iv'] ?? '');
    }

    private function decryptFileKeyArray(array $attachment, User $requester, string $deviceId): string
    {
        $encryptedKeys = $attachment['file_key_encrypted'];

        if (! isset($encryptedKeys[$deviceId])) {
            throw new Exception('File key not found for this device');
        }

        return base64_decode($encryptedKeys[$deviceId]);
    }
}
