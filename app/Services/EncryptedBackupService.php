<?php

namespace App\Services;

use App\Models\Chat\BackupVerification;
use App\Models\Chat\ChatBackup;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class EncryptedBackupService
{
    public function __construct(
        private readonly SignalProtocolService $signalService,
        private readonly QuantumCryptoService $quantumService,
        private readonly EncryptedFileService $fileService
    ) {}

    /**
     * Create a new backup job
     */
    public function createBackup(
        User $user,
        array $backupData,
        string $deviceId
    ): ChatBackup {
        try {
            // Set expiration date (default 30 days)
            $expiresAt = now()->addDays($backupData['retention_days'] ?? 30);

            $backup = ChatBackup::create([
                'user_id' => $user->id,
                'conversation_id' => $backupData['conversation_id'] ?? null,
                'backup_type' => $backupData['backup_type'] ?? 'full_account',
                'export_format' => $backupData['export_format'] ?? 'json',
                'backup_scope' => $backupData['backup_scope'] ?? ['messages', 'files', 'polls', 'surveys', 'reactions'],
                'date_range' => $backupData['date_range'] ?? null,
                'encryption_settings' => [
                    'algorithm' => $backupData['encryption_algorithm'] ?? 'aes-256-gcm',
                    'preserve_e2ee' => $backupData['preserve_encryption'] ?? true,
                    'device_id' => $deviceId,
                ],
                'include_attachments' => $backupData['include_attachments'] ?? true,
                'include_metadata' => $backupData['include_metadata'] ?? true,
                'preserve_encryption' => $backupData['preserve_encryption'] ?? true,
                'expires_at' => $expiresAt,
            ]);

            Log::info('Backup job created', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
                'backup_type' => $backup->backup_type,
            ]);

            return $backup;

        } catch (Exception $e) {
            Log::error('Failed to create backup job', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process a backup job
     */
    public function processBackup(ChatBackup $backup): void
    {
        try {
            $backup->markAsStarted();

            // Calculate total items to process
            $totalItems = $this->calculateTotalItems($backup);
            $backup->update(['total_items' => $totalItems]);

            // Create backup directory
            $backupDir = storage_path('app/backups/'.$backup->id);
            if (! file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $processedItems = 0;

            // Process based on backup type
            switch ($backup->backup_type) {
                case 'full_account':
                    $processedItems = $this->processFullAccountBackup($backup, $backupDir);
                    break;
                case 'conversation':
                    $processedItems = $this->processConversationBackup($backup, $backupDir);
                    break;
                case 'date_range':
                    $processedItems = $this->processDateRangeBackup($backup, $backupDir);
                    break;
            }

            // Create final archive
            $archivePath = $this->createBackupArchive($backup, $backupDir);
            $fileHash = hash_file('sha256', $archivePath);
            $fileSize = filesize($archivePath);

            // Clean up temporary directory
            $this->cleanupDirectory($backupDir);

            // Mark backup as completed
            $backup->markAsCompleted($archivePath, $fileHash, $fileSize);

            // Create verification record
            $this->createBackupVerification($backup, $fileHash);

            Log::info('Backup processing completed', [
                'backup_id' => $backup->id,
                'processed_items' => $processedItems,
                'file_size' => $fileSize,
            ]);

        } catch (Exception $e) {
            $backup->markAsFailed($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            Log::error('Backup processing failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process full account backup
     */
    private function processFullAccountBackup(ChatBackup $backup, string $backupDir): int
    {
        $processedItems = 0;
        $user = $backup->user;

        // Get all user conversations
        $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        foreach ($conversations as $conversation) {
            $processedItems += $this->exportConversationData($backup, $conversation, $backupDir);
            $backup->updateProgress($processedItems, "Processing conversation: {$conversation->name}");
        }

        // Export user profile and settings
        $this->exportUserProfile($backup, $user, $backupDir);
        $processedItems++;

        return $processedItems;
    }

    /**
     * Process single conversation backup
     */
    private function processConversationBackup(ChatBackup $backup, string $backupDir): int
    {
        $conversation = $backup->conversation;

        return $this->exportConversationData($backup, $conversation, $backupDir);
    }

    /**
     * Process date range backup
     */
    private function processDateRangeBackup(ChatBackup $backup, string $backupDir): int
    {
        $processedItems = 0;
        $user = $backup->user;
        $dateStart = $backup->getDateRangeStart();
        $dateEnd = $backup->getDateRangeEnd();

        // Get conversations with activity in date range
        $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereHas('messages', function ($query) use ($dateStart, $dateEnd) {
            $query->whereBetween('created_at', [$dateStart, $dateEnd]);
        })->get();

        foreach ($conversations as $conversation) {
            $processedItems += $this->exportConversationData($backup, $conversation, $backupDir, $dateStart, $dateEnd);
            $backup->updateProgress($processedItems, "Processing conversation: {$conversation->name}");
        }

        return $processedItems;
    }

    /**
     * Export conversation data
     */
    private function exportConversationData(
        ChatBackup $backup,
        Conversation $conversation,
        string $backupDir,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): int {
        $processedItems = 0;
        $conversationDir = $backupDir.'/conversations/'.$conversation->id;

        if (! file_exists($conversationDir)) {
            mkdir($conversationDir, 0755, true);
        }

        // Export conversation metadata
        $conversationData = [
            'id' => $conversation->id,
            'name' => $conversation->name,
            'type' => $conversation->type,
            'created_at' => $conversation->created_at,
            'participants' => $conversation->participants->map(function ($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'role' => $participant->role,
                    'joined_at' => $participant->joined_at,
                ];
            }),
        ];

        file_put_contents(
            $conversationDir.'/conversation.json',
            json_encode($conversationData, JSON_PRETTY_PRINT)
        );
        $processedItems++;

        // Export messages
        if ($backup->includesMessages()) {
            $processedItems += $this->exportMessages($backup, $conversation, $conversationDir, $dateStart, $dateEnd);
        }

        // Export files
        if ($backup->includesFiles()) {
            $processedItems += $this->exportFiles($backup, $conversation, $conversationDir, $dateStart, $dateEnd);
        }

        // Export polls
        if ($backup->includesPolls()) {
            $processedItems += $this->exportPolls($backup, $conversation, $conversationDir, $dateStart, $dateEnd);
        }

        // Export surveys
        if ($backup->includesSurveys()) {
            $processedItems += $this->exportSurveys($backup, $conversation, $conversationDir, $dateStart, $dateEnd);
        }

        return $processedItems;
    }

    /**
     * Export messages from a conversation
     */
    private function exportMessages(
        ChatBackup $backup,
        Conversation $conversation,
        string $conversationDir,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): int {
        $messagesQuery = $conversation->messages()
            ->with(['sender', 'reactions', 'readReceipts']);

        if ($dateStart && $dateEnd) {
            $messagesQuery->whereBetween('created_at', [$dateStart, $dateEnd]);
        }

        $messages = $messagesQuery->get();
        $messagesData = [];

        foreach ($messages as $message) {
            $messageData = [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name,
                'type' => $message->type,
                'created_at' => $message->created_at,
            ];

            // Handle encrypted content
            if ($backup->preservesEncryption()) {
                $messageData['encrypted_content'] = $message->encrypted_content;
                $messageData['content_hash'] = $message->content_hash;
                $messageData['encryption_version'] = $message->encryption_version;
            } else {
                // Decrypt content for export (if user has access)
                try {
                    $decryptedContent = $this->signalService->decryptMessage(
                        $backup->user,
                        $conversation,
                        $message->encrypted_content,
                        $backup->encryption_settings['device_id']
                    );
                    $messageData['content'] = $decryptedContent;
                } catch (Exception $e) {
                    $messageData['content'] = '[Unable to decrypt]';
                    $messageData['decrypt_error'] = $e->getMessage();
                }
            }

            // Include reactions if requested
            if ($backup->includesReactions()) {
                $messageData['reactions'] = $message->reactions->map(function ($reaction) {
                    return [
                        'user_id' => $reaction->user_id,
                        'emoji' => $reaction->emoji,
                        'created_at' => $reaction->created_at,
                    ];
                });
            }

            // Include metadata if requested
            if ($backup->includesMetadata()) {
                $messageData['metadata'] = [
                    'read_receipts' => $message->readReceipts->map(function ($receipt) {
                        return [
                            'user_id' => $receipt->user_id,
                            'read_at' => $receipt->read_at,
                        ];
                    }),
                    'is_edited' => $message->is_edited,
                    'edited_at' => $message->edited_at,
                    'reply_to_id' => $message->reply_to_id,
                ];
            }

            $messagesData[] = $messageData;
        }

        file_put_contents(
            $conversationDir.'/messages.json',
            json_encode($messagesData, JSON_PRETTY_PRINT)
        );

        return count($messages);
    }

    /**
     * Export files from messages
     */
    private function exportFiles(
        ChatBackup $backup,
        Conversation $conversation,
        string $conversationDir,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): int {
        if (! $backup->includesAttachments()) {
            return 0;
        }

        $filesDir = $conversationDir.'/files';
        if (! file_exists($filesDir)) {
            mkdir($filesDir, 0755, true);
        }

        $messagesQuery = $conversation->messages()
            ->where('type', 'file')
            ->with('files');

        if ($dateStart && $dateEnd) {
            $messagesQuery->whereBetween('created_at', [$dateStart, $dateEnd]);
        }

        $messages = $messagesQuery->get();
        $processedFiles = 0;

        foreach ($messages as $message) {
            foreach ($message->files as $file) {
                try {
                    if ($backup->preservesEncryption()) {
                        // Copy encrypted file
                        $sourcePath = $this->getEncryptedFilePath($file);
                        $targetPath = $filesDir.'/'.$file->encrypted_filename;

                        if (file_exists($sourcePath)) {
                            copy($sourcePath, $targetPath);
                            $processedFiles++;
                        }
                    } else {
                        // Decrypt and export file
                        $decryptedFile = $this->fileService->downloadDecryptedFile(
                            $file,
                            $backup->user,
                            $backup->encryption_settings['device_id']
                        );

                        file_put_contents(
                            $filesDir.'/'.$file->original_filename,
                            $decryptedFile['content']
                        );
                        $processedFiles++;
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to export file', [
                        'file_id' => $file->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $processedFiles;
    }

    /**
     * Export polls from conversation
     */
    private function exportPolls(
        ChatBackup $backup,
        Conversation $conversation,
        string $conversationDir,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): int {
        $messagesQuery = $conversation->messages()
            ->where('type', 'poll')
            ->with('poll.votes');

        if ($dateStart && $dateEnd) {
            $messagesQuery->whereBetween('created_at', [$dateStart, $dateEnd]);
        }

        $messages = $messagesQuery->get();
        $pollsData = [];

        foreach ($messages as $message) {
            if ($message->poll) {
                $pollData = [
                    'id' => $message->poll->id,
                    'message_id' => $message->id,
                    'creator_id' => $message->poll->creator_id,
                    'poll_type' => $message->poll->poll_type,
                    'anonymous' => $message->poll->anonymous,
                    'created_at' => $message->poll->created_at,
                    'total_votes' => $message->poll->getTotalVotes(),
                ];

                if ($backup->preservesEncryption()) {
                    $pollData['encrypted_question'] = $message->poll->encrypted_question;
                    $pollData['encrypted_options'] = $message->poll->encrypted_options;
                    $pollData['question_hash'] = $message->poll->question_hash;
                    $pollData['option_hashes'] = $message->poll->option_hashes;
                }

                $pollsData[] = $pollData;
            }
        }

        if (! empty($pollsData)) {
            file_put_contents(
                $conversationDir.'/polls.json',
                json_encode($pollsData, JSON_PRETTY_PRINT)
            );
        }

        return count($pollsData);
    }

    /**
     * Export surveys from conversation
     */
    private function exportSurveys(
        ChatBackup $backup,
        Conversation $conversation,
        string $conversationDir,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ): int {
        $messagesQuery = $conversation->messages()
            ->where('type', 'survey')
            ->with('survey.questions', 'survey.responses');

        if ($dateStart && $dateEnd) {
            $messagesQuery->whereBetween('created_at', [$dateStart, $dateEnd]);
        }

        $messages = $messagesQuery->get();
        $surveysData = [];

        foreach ($messages as $message) {
            if ($message->survey) {
                $surveyData = [
                    'id' => $message->survey->id,
                    'message_id' => $message->id,
                    'creator_id' => $message->survey->creator_id,
                    'anonymous' => $message->survey->anonymous,
                    'created_at' => $message->survey->created_at,
                    'total_responses' => $message->survey->getTotalResponses(),
                    'completion_rate' => $message->survey->getCompletionRate(),
                ];

                if ($backup->preservesEncryption()) {
                    $surveyData['encrypted_title'] = $message->survey->encrypted_title;
                    $surveyData['encrypted_description'] = $message->survey->encrypted_description;
                    $surveyData['title_hash'] = $message->survey->title_hash;
                    $surveyData['description_hash'] = $message->survey->description_hash;
                }

                $surveysData[] = $surveyData;
            }
        }

        if (! empty($surveysData)) {
            file_put_contents(
                $conversationDir.'/surveys.json',
                json_encode($surveysData, JSON_PRETTY_PRINT)
            );
        }

        return count($surveysData);
    }

    /**
     * Export user profile data
     */
    private function exportUserProfile(ChatBackup $backup, User $user, string $backupDir): void
    {
        $profileData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'export_timestamp' => now()->toISOString(),
            'backup_metadata' => [
                'backup_id' => $backup->id,
                'backup_type' => $backup->backup_type,
                'export_format' => $backup->export_format,
                'preserves_encryption' => $backup->preservesEncryption(),
            ],
        ];

        file_put_contents(
            $backupDir.'/profile.json',
            json_encode($profileData, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Create backup archive
     */
    private function createBackupArchive(ChatBackup $backup, string $backupDir): string
    {
        $archivePath = storage_path("app/backups/{$backup->id}.zip");

        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE) === true) {
            $this->addDirectoryToZip($zip, $backupDir, '');
            $zip->close();
        } else {
            throw new Exception('Failed to create backup archive');
        }

        return $archivePath;
    }

    /**
     * Add directory to ZIP archive recursively
     */
    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void
    {
        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory.'/'.$file;
            $zipPath = $prefix.$file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirectoryToZip($zip, $filePath, $zipPath.'/');
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }

    /**
     * Create backup verification record
     */
    private function createBackupVerification(ChatBackup $backup, string $fileHash): BackupVerification
    {
        return BackupVerification::create([
            'backup_id' => $backup->id,
            'verification_method' => 'hash',
            'verification_data' => $fileHash,
            'is_verified' => true,
            'verified_at' => now(),
            'verification_notes' => 'SHA-256 hash verification',
        ]);
    }

    /**
     * Calculate total items to process
     */
    private function calculateTotalItems(ChatBackup $backup): int
    {
        $total = 0;

        if ($backup->isFullAccount()) {
            $conversations = Conversation::whereHas('participants', function ($query) use ($backup) {
                $query->where('user_id', $backup->user_id);
            })->count();

            $total = $conversations * 100; // Rough estimate
        } elseif ($backup->isConversationBackup()) {
            $total = $backup->conversation->messages()->count();
        } elseif ($backup->isDateRangeBackup()) {
            $dateStart = $backup->getDateRangeStart();
            $dateEnd = $backup->getDateRangeEnd();

            $total = Message::whereHas('conversation.participants', function ($query) use ($backup) {
                $query->where('user_id', $backup->user_id);
            })->whereBetween('created_at', [$dateStart, $dateEnd])->count();
        }

        return max(1, $total); // Ensure at least 1 to avoid division by zero
    }

    /**
     * Get encrypted file path
     */
    private function getEncryptedFilePath($messageFile): string
    {
        return storage_path("app/chat/files/{$messageFile->message->conversation_id}/{$messageFile->encrypted_filename}");
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $files = array_diff(scandir($directory), ['.', '..']);

            foreach ($files as $file) {
                $filePath = $directory.'/'.$file;

                if (is_dir($filePath)) {
                    $this->cleanupDirectory($filePath);
                } else {
                    unlink($filePath);
                }
            }

            rmdir($directory);
        }
    }

    /**
     * Clean up expired backups
     */
    public function cleanupExpiredBackups(): int
    {
        $expiredBackups = ChatBackup::expired()->get();
        $cleaned = 0;

        foreach ($expiredBackups as $backup) {
            try {
                // Delete backup file
                if ($backup->backup_file_path && file_exists($backup->backup_file_path)) {
                    unlink($backup->backup_file_path);
                }

                // Delete backup record
                $backup->delete();
                $cleaned++;

                Log::info('Expired backup cleaned up', [
                    'backup_id' => $backup->id,
                    'user_id' => $backup->user_id,
                ]);

            } catch (Exception $e) {
                Log::error('Failed to cleanup expired backup', [
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cleaned;
    }
}
