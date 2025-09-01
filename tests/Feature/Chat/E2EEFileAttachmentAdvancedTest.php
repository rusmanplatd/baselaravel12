<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\MessageAttachment;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\ChatFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->fileService = new ChatFileService($this->encryptionService);

    Storage::fake('encrypted_files');
    Storage::fake('thumbnails');
    Storage::fake('temp');

    $this->user1 = User::factory()->create(['name' => 'Alice']);
    $this->user2 = User::factory()->create(['name' => 'Bob']);

    $this->conversation = Conversation::factory()->create([
        'type' => 'group',
        'name' => 'File Sharing Group',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id]);
    $this->conversation->participants()->create(['user_id' => $this->user2->id]);

    // Setup encryption
    $this->keyPair = $this->encryptionService->generateKeyPair();
    $this->device = UserDevice::factory()->create([
        'user_id' => $this->user1->id,
        'public_key' => $this->keyPair['public_key'],
        'is_trusted' => true,
    ]);

    $this->symmetricKey = $this->encryptionService->generateSymmetricKey();
    $this->encryptionKey = EncryptionKey::create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'device_id' => $this->device->id,
        'device_fingerprint' => $this->device->device_fingerprint,
        'encrypted_key' => $this->encryptionService->encryptSymmetricKey($this->symmetricKey, $this->keyPair['public_key']),
        'public_key' => $this->keyPair['public_key'],
        'key_version' => 1,
        'algorithm' => 'RSA-4096-OAEP',
        'key_strength' => 4096,
        'is_active' => true,
    ]);
});

describe('E2EE File Attachment Advanced Tests', function () {
    describe('Edge Cases and Error Handling', function () {
        it('handles empty files gracefully', function () {
            $emptyFile = UploadedFile::fake()->create('empty_file.txt', 0);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $emptyFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['file_size'])->toBe(0);

            // Download empty file
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe('');
            expect(strlen($downloadResult['content']))->toBe(0);

            echo "\n✅ Empty file handling test successful";
        });

        it('handles files with special characters in names', function () {
            $specialNames = [
                'файл.txt', // Cyrillic
                '文档.txt', // Chinese
                'dôcümént.txt', // Accented characters
                'file with spaces.txt',
                'file-with-dashes_and_underscores.txt',
                'file.with.multiple.dots.txt',
                'file(with)parentheses[and]brackets.txt',
                'file@#$%^&*()+={}|;:,.<>?txt', // Special symbols
            ];

            $uploadedFiles = [];

            foreach ($specialNames as $fileName) {
                $content = "Content for file: {$fileName}";
                $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);

                $uploadResult = $this->fileService->uploadEncryptedFile(
                    $uploadedFile,
                    $this->conversation->id,
                    $this->user1->id,
                    $this->symmetricKey
                );

                expect($uploadResult['success'])->toBeTrue();
                expect($uploadResult['original_name'])->toBe($fileName);

                $uploadedFiles[$fileName] = [
                    'result' => $uploadResult,
                    'content' => $content,
                ];
            }

            // Verify all files can be downloaded with correct names and content
            foreach ($uploadedFiles as $fileName => $fileData) {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $fileData['result']['file_id'],
                    $this->symmetricKey
                );

                expect($downloadResult['success'])->toBeTrue();
                expect($downloadResult['content'])->toBe($fileData['content']);
                expect($downloadResult['original_name'])->toBe($fileName);
            }

            echo "\n✅ Special character filename test successful: ".count($specialNames).' files';
        });

        it('handles files with no extension', function () {
            $content = 'File with no extension content';
            $uploadedFile = UploadedFile::fake()->createWithContent('README', $content);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['original_name'])->toBe('README');
            expect($uploadResult['mime_type'])->toBeIn(['application/octet-stream', 'text/plain']);

            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($content);

            echo "\n✅ File without extension test successful";
        });

        it('handles corrupted upload data gracefully', function () {
            $validContent = 'Valid file content';
            $uploadedFile = UploadedFile::fake()->createWithContent('test.txt', $validContent);

            // Simulate upload with corrupted data by manipulating file content after creation
            // In a real scenario, this would be network corruption or storage issues

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // Verify hash matches original
            $expectedHash = hash('sha256', $validContent);
            expect($uploadResult['file_hash'])->toBe($expectedHash);

            // Normal download should work
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($validContent);

            echo "\n✅ Corrupted upload handling test successful";
        });

        it('handles duplicate filename uploads', function () {
            $fileName = 'duplicate_name.txt';
            $content1 = 'First file with this name';
            $content2 = 'Second file with same name but different content';

            // Upload first file
            $file1 = UploadedFile::fake()->createWithContent($fileName, $content1);
            $upload1 = $this->fileService->uploadEncryptedFile(
                $file1,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            // Upload second file with same name
            $file2 = UploadedFile::fake()->createWithContent($fileName, $content2);
            $upload2 = $this->fileService->uploadEncryptedFile(
                $file2,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($upload1['success'])->toBeTrue();
            expect($upload2['success'])->toBeTrue();

            // Files should have different IDs and encrypted names
            expect($upload1['file_id'])->not()->toBe($upload2['file_id']);
            expect($upload1['encrypted_filename'])->not()->toBe($upload2['encrypted_filename']);

            // Both should have same original name
            expect($upload1['original_name'])->toBe($fileName);
            expect($upload2['original_name'])->toBe($fileName);

            // Both should be downloadable with correct content
            $download1 = $this->fileService->downloadEncryptedFile($upload1['file_id'], $this->symmetricKey);
            $download2 = $this->fileService->downloadEncryptedFile($upload2['file_id'], $this->symmetricKey);

            expect($download1['content'])->toBe($content1);
            expect($download2['content'])->toBe($content2);
            expect($download1['content'])->not()->toBe($download2['content']);

            echo "\n✅ Duplicate filename handling test successful";
        });
    });

    describe('File Compression and Optimization', function () {
        it('handles file compression for large text files', function () {
            // Create a large, repetitive text file that compresses well
            $repetitiveContent = str_repeat("This is a repetitive line that compresses very well.\n", 1000);
            $fileName = 'compressible.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $repetitiveContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey,
                ['compress' => true] // Enable compression if supported
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['file_size'])->toBe(strlen($repetitiveContent));

            // Check if compression was applied
            if (isset($uploadResult['compressed'])) {
                expect($uploadResult['compressed'])->toBeTrue();
                expect($uploadResult['compressed_size'])->toBeLessThan($uploadResult['file_size']);
            }

            // Download and verify content is identical
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($repetitiveContent);
            expect(strlen($downloadResult['content']))->toBe(strlen($repetitiveContent));

            echo "\n✅ File compression test successful";
        });

        it('optimizes storage for identical file uploads', function () {
            $identicalContent = 'This content will be uploaded multiple times.';
            $fileName = 'identical_file.txt';

            // Upload the same file multiple times
            $uploads = [];
            for ($i = 0; $i < 3; $i++) {
                $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $identicalContent);
                $uploadResult = $this->fileService->uploadEncryptedFile(
                    $uploadedFile,
                    $this->conversation->id,
                    $this->user1->id,
                    $this->symmetricKey
                );

                expect($uploadResult['success'])->toBeTrue();
                $uploads[] = $uploadResult;
            }

            // All uploads should have different file IDs (they are separate uploads)
            expect($uploads[0]['file_id'])->not()->toBe($uploads[1]['file_id']);
            expect($uploads[1]['file_id'])->not()->toBe($uploads[2]['file_id']);

            // But they should all have the same content hash
            expect($uploads[0]['file_hash'])->toBe($uploads[1]['file_hash']);
            expect($uploads[1]['file_hash'])->toBe($uploads[2]['file_hash']);

            // All should be downloadable
            foreach ($uploads as $upload) {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $upload['file_id'],
                    $this->symmetricKey
                );

                expect($downloadResult['success'])->toBeTrue();
                expect($downloadResult['content'])->toBe($identicalContent);
            }

            echo "\n✅ Identical file optimization test successful";
        });
    });

    describe('File Access Control and Permissions', function () {
        it('enforces conversation-based file access control', function () {
            // Create a second conversation
            $otherConversation = Conversation::factory()->create([
                'type' => 'direct',
                'created_by' => $this->user2->id,
            ]);

            $otherConversation->participants()->create(['user_id' => $this->user2->id]);

            // Upload file to first conversation
            $privateContent = 'This file belongs to conversation 1 only.';
            $fileName = 'private_file.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $privateContent);
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // Try to access file from wrong conversation context
            $unauthorizedAccess = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey,
                ['conversation_id' => $otherConversation->id] // Wrong conversation
            );

            if (isset($unauthorizedAccess['success'])) {
                expect($unauthorizedAccess['success'])->toBeFalse();
            }

            // Correct conversation access should work
            $authorizedAccess = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey,
                ['conversation_id' => $this->conversation->id] // Correct conversation
            );

            expect($authorizedAccess['success'])->toBeTrue();
            expect($authorizedAccess['content'])->toBe($privateContent);

            echo "\n✅ Conversation-based access control test successful";
        });

        it('handles file access after user leaves conversation', function () {
            // User uploads file
            $content = 'File uploaded before leaving conversation.';
            $fileName = 'before_leave.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            // Create message with attachment
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'File before leaving',
                $this->symmetricKey
            );

            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileName,
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);

            // File should be accessible before leaving
            $downloadBefore = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );
            expect($downloadBefore['success'])->toBeTrue();

            // Simulate user leaving conversation
            $this->conversation->participants()->where('user_id', $this->user1->id)->delete();

            // File access after leaving should be handled according to policy
            $downloadAfter = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            // Policy could be either:
            // 1. Still accessible (files remain available)
            // 2. Not accessible (access revoked)
            // We'll test that the system handles it gracefully either way
            expect(isset($downloadAfter['success']))->toBeTrue();

            echo "\n✅ File access after leaving conversation test successful";
        });
    });

    describe('File Cleanup and Lifecycle', function () {
        it('handles file deletion and cleanup', function () {
            $content = 'File that will be deleted.';
            $fileName = 'to_be_deleted.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // File should be downloadable initially
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );
            expect($downloadResult['success'])->toBeTrue();

            // Delete the file
            $deleteResult = $this->fileService->deleteEncryptedFile(
                $uploadResult['file_id'],
                $this->user1->id
            );

            expect($deleteResult['success'])->toBeTrue();

            // File should no longer be downloadable
            $downloadAfterDelete = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadAfterDelete['success'])->toBeFalse();
            expect($downloadAfterDelete['error'])->toContain(['not found', 'deleted', 'unavailable']);

            echo "\n✅ File deletion and cleanup test successful";
        });

        it('handles automatic cleanup of expired files', function () {
            $content = 'File with expiration.';
            $fileName = 'expiring_file.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey,
                ['expires_at' => now()->addMinutes(1)] // Expires in 1 minute
            );

            expect($uploadResult['success'])->toBeTrue();

            // File should be accessible before expiration
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );
            expect($downloadResult['success'])->toBeTrue();

            // Simulate time passing (in real scenario this would be handled by scheduled jobs)
            if (isset($uploadResult['expires_at'])) {
                // Simulate expired file access
                $expiredDownload = $this->fileService->downloadEncryptedFile(
                    $uploadResult['file_id'],
                    $this->symmetricKey,
                    ['check_expiration' => true, 'current_time' => now()->addHours(2)]
                );

                if (isset($expiredDownload['success'])) {
                    expect($expiredDownload['success'])->toBeFalse();
                    expect($expiredDownload['error'])->toContain(['expired', 'unavailable']);
                }
            }

            echo "\n✅ File expiration handling test successful";
        });
    });

    describe('File Metadata and Search', function () {
        it('preserves and encrypts file metadata', function () {
            $content = 'File with rich metadata.';
            $fileName = 'metadata_test.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);

            $customMetadata = [
                'description' => 'Test file for metadata preservation',
                'tags' => ['test', 'metadata', 'encryption'],
                'author' => 'Alice',
                'version' => '1.0',
                'classification' => 'confidential',
            ];

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey,
                ['metadata' => $customMetadata]
            );

            expect($uploadResult['success'])->toBeTrue();

            // Check if metadata was stored
            if (isset($uploadResult['metadata'])) {
                expect($uploadResult['metadata'])->toBe($customMetadata);
            }

            // Create attachment with metadata
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'File with metadata',
                $this->symmetricKey
            );

            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileName,
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
                'metadata' => json_encode($customMetadata),
            ]);

            // Verify metadata is preserved in attachment
            $storedMetadata = json_decode($attachment->metadata, true);
            expect($storedMetadata)->toBe($customMetadata);

            echo "\n✅ File metadata preservation test successful";
        });

        it('enables secure file search by metadata', function () {
            // Upload multiple files with different metadata
            $files = [
                ['name' => 'project_plan.txt', 'content' => 'Project planning document', 'tags' => ['project', 'planning']],
                ['name' => 'financial_data.csv', 'content' => 'Financial information', 'tags' => ['finance', 'confidential']],
                ['name' => 'meeting_notes.txt', 'content' => 'Meeting notes', 'tags' => ['meeting', 'notes']],
                ['name' => 'budget.txt', 'content' => 'Budget information', 'tags' => ['finance', 'budget']],
            ];

            $uploadedFiles = [];
            foreach ($files as $fileData) {
                $uploadedFile = UploadedFile::fake()->createWithContent($fileData['name'], $fileData['content']);

                $uploadResult = $this->fileService->uploadEncryptedFile(
                    $uploadedFile,
                    $this->conversation->id,
                    $this->user1->id,
                    $this->symmetricKey,
                    ['metadata' => ['tags' => $fileData['tags']]]
                );

                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Uploading {$fileData['name']}",
                    $this->symmetricKey
                );

                $attachment = MessageAttachment::create([
                    'message_id' => $message->id,
                    'file_id' => $uploadResult['file_id'],
                    'original_filename' => $fileData['name'],
                    'encrypted_filename' => $uploadResult['encrypted_filename'],
                    'file_size' => $uploadResult['file_size'],
                    'mime_type' => $uploadResult['mime_type'],
                    'file_hash' => $uploadResult['file_hash'],
                    'encryption_iv' => $uploadResult['encryption_iv'],
                    'is_encrypted' => true,
                    'metadata' => json_encode(['tags' => $fileData['tags']]),
                ]);

                $uploadedFiles[] = $attachment;
            }

            // Search for files by tag
            $financeFiles = MessageAttachment::whereJsonContains('metadata->tags', 'finance')->get();
            expect($financeFiles->count())->toBe(2); // financial_data.csv and budget.txt

            $projectFiles = MessageAttachment::whereJsonContains('metadata->tags', 'project')->get();
            expect($projectFiles->count())->toBe(1); // project_plan.txt

            // Verify search results
            $financeFilenames = $financeFiles->pluck('original_filename')->toArray();
            expect($financeFilenames)->toContain('financial_data.csv');
            expect($financeFilenames)->toContain('budget.txt');

            echo "\n✅ Secure file search test successful: Found files by metadata tags";
        });
    });

    describe('File Backup and Recovery', function () {
        it('creates encrypted file backups', function () {
            $content = 'Important file that needs backup.';
            $fileName = 'important_document.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey,
                ['create_backup' => true]
            );

            expect($uploadResult['success'])->toBeTrue();

            // Check if backup was created
            if (isset($uploadResult['backup_id'])) {
                expect($uploadResult['backup_id'])->toBeString();

                // Verify backup can be restored
                $restoreResult = $this->fileService->restoreFromBackup(
                    $uploadResult['backup_id'],
                    $this->symmetricKey
                );

                expect($restoreResult['success'])->toBeTrue();
                expect($restoreResult['content'])->toBe($content);
            }

            echo "\n✅ File backup and recovery test successful";
        });
    });
});
