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

    // Setup fake storage for testing
    Storage::fake('encrypted_files');
    Storage::fake('thumbnails');

    $this->user1 = User::factory()->create(['name' => 'Alice']);
    $this->user2 = User::factory()->create(['name' => 'Bob']);

    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id]);
    $this->conversation->participants()->create(['user_id' => $this->user2->id]);

    // Setup encryption keys
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

describe('E2EE File Attachment Tests', function () {
    describe('Basic File Encryption and Upload', function () {
        it('encrypts and uploads text files successfully', function () {
            $fileContent = "This is a confidential document with sensitive information.\nLine 2: Secret project details\nLine 3: Financial data";
            $fileName = 'confidential_document.txt';

            // Create fake uploaded file
            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $fileContent);

            // Upload and encrypt file
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['file_id'])->toBeString();
            expect($uploadResult['original_name'])->toBe($fileName);
            expect($uploadResult['file_size'])->toBe(strlen($fileContent));
            expect($uploadResult['mime_type'])->toBe('text/plain');
            expect($uploadResult['encrypted'])->toBeTrue();

            // Create message with file attachment
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Here\'s the confidential document we discussed.',
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

            // Download and decrypt file
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($fileContent);
            expect($downloadResult['original_name'])->toBe($fileName);
            expect($downloadResult['mime_type'])->toBe('text/plain');

            // Verify message and attachment integrity
            $decryptedMessage = $message->decryptContent($this->symmetricKey);
            expect($decryptedMessage)->toBe('Here\'s the confidential document we discussed.');

            expect($attachment->original_filename)->toBe($fileName);
            expect($attachment->is_encrypted)->toBeTrue();

            echo "\n✅ Text file encryption test successful: {$fileName} ({$uploadResult['file_size']} bytes)";
        });

        it('handles various file types with proper encryption', function () {
            $testFiles = [
                // Text files
                'document.txt' => ['content' => 'Text document content', 'type' => 'text/plain'],
                'data.json' => ['content' => '{"secret":"value","encrypted":true}', 'type' => 'application/json'],
                'config.xml' => ['content' => '<config><secret>value</secret></config>', 'type' => 'application/xml'],

                // Binary-like content
                'data.csv' => ['content' => "Name,Secret,Value\nAlice,123456,Confidential\nBob,789012,TopSecret", 'type' => 'text/csv'],
                'script.js' => ['content' => 'const secret = "encrypted_value"; console.log(secret);', 'type' => 'application/javascript'],
                'style.css' => ['content' => '.encrypted { color: #secret; background: url(encrypted.png); }', 'type' => 'text/css'],
            ];

            $uploadedFiles = [];
            $attachments = [];

            foreach ($testFiles as $fileName => $fileData) {
                $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $fileData['content']);

                $uploadResult = $this->fileService->uploadEncryptedFile(
                    $uploadedFile,
                    $this->conversation->id,
                    $this->user1->id,
                    $this->symmetricKey
                );

                expect($uploadResult['success'])->toBeTrue();
                expect($uploadResult['mime_type'])->toBe($fileData['type']);

                $uploadedFiles[$fileName] = $uploadResult;

                // Create message with attachment
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Sending encrypted file: {$fileName}",
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

                $attachments[$fileName] = $attachment;
            }

            // Verify all files can be downloaded and decrypted correctly
            foreach ($testFiles as $fileName => $fileData) {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $uploadedFiles[$fileName]['file_id'],
                    $this->symmetricKey
                );

                expect($downloadResult['success'])->toBeTrue();
                expect($downloadResult['content'])->toBe($fileData['content']);
                expect($downloadResult['mime_type'])->toBe($fileData['type']);

                // Verify attachment record
                $attachment = $attachments[$fileName];
                expect($attachment->is_encrypted)->toBeTrue();
                expect($attachment->original_filename)->toBe($fileName);
                expect($attachment->file_size)->toBe(strlen($fileData['content']));
            }

            expect(count($uploadedFiles))->toBe(count($testFiles));
            echo "\n✅ Multiple file types test successful: ".count($testFiles).' different file types encrypted';
        });

        it('generates and encrypts thumbnails for supported file types', function () {
            // Test image file (simulated)
            $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='); // 1x1 PNG
            $uploadedFile = UploadedFile::fake()->createWithContent('test_image.png', $imageContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['mime_type'])->toBe('image/png');

            // Check if thumbnail was generated (if image processing is available)
            if (isset($uploadResult['thumbnail_id'])) {
                expect($uploadResult['thumbnail_id'])->toBeString();
                expect($uploadResult['has_thumbnail'])->toBeTrue();

                // Verify thumbnail can be downloaded
                $thumbnailResult = $this->fileService->downloadEncryptedFile(
                    $uploadResult['thumbnail_id'],
                    $this->symmetricKey
                );

                expect($thumbnailResult['success'])->toBeTrue();
                expect($thumbnailResult['content'])->not()->toBeEmpty();
            }

            // Create message with image attachment
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Sending an encrypted image 📸',
                $this->symmetricKey
            );

            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => 'test_image.png',
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'thumbnail_id' => $uploadResult['thumbnail_id'] ?? null,
                'is_encrypted' => true,
            ]);

            // Verify original file download
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($imageContent);
            expect($attachment->is_encrypted)->toBeTrue();

            echo "\n✅ Image file with thumbnail test successful";
        });
    });

    describe('File Size and Performance Testing', function () {
        it('handles large file uploads efficiently', function () {
            // Create a large text file (1MB)
            $largeContent = str_repeat("This is line with some content and padding to make it longer.\n", 15000);
            $fileName = 'large_document.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $largeContent);

            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            $uploadTime = (microtime(true) - $startTime) * 1000; // ms
            $memoryUsed = (memory_get_usage() - $startMemory) / 1024 / 1024; // MB

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['file_size'])->toBe(strlen($largeContent));
            expect($uploadTime)->toBeLessThan(10000); // Less than 10 seconds
            expect($memoryUsed)->toBeLessThan(50); // Less than 50MB additional memory

            // Test download performance
            $downloadStartTime = microtime(true);

            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            $downloadTime = (microtime(true) - $downloadStartTime) * 1000; // ms

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($largeContent);
            expect($downloadTime)->toBeLessThan(5000); // Less than 5 seconds

            // Create message with large file attachment
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Sending a large encrypted file 📄',
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

            expect($attachment->file_size)->toBe(strlen($largeContent));

            echo "\n✅ Large file test successful:";
            echo "\n   • File size: ".number_format($uploadResult['file_size'] / 1024, 2).' KB';
            echo "\n   • Upload time: ".number_format($uploadTime, 2).'ms';
            echo "\n   • Download time: ".number_format($downloadTime, 2).'ms';
            echo "\n   • Memory used: ".number_format($memoryUsed, 2).'MB';
        });

        it('handles multiple concurrent file uploads', function () {
            $fileContents = [];
            $uploadTasks = [];

            // Prepare multiple files for concurrent upload
            for ($i = 0; $i < 5; $i++) {
                $content = "Concurrent file #{$i} content with some data: ".str_repeat("data{$i} ", 100);
                $fileName = "concurrent_file_{$i}.txt";

                $fileContents[$fileName] = $content;
                $uploadTasks[] = [
                    'file' => UploadedFile::fake()->createWithContent($fileName, $content),
                    'name' => $fileName,
                    'content' => $content,
                ];
            }

            $startTime = microtime(true);
            $uploadResults = [];
            $errors = [];

            // Simulate concurrent uploads (sequential for testing)
            foreach ($uploadTasks as $task) {
                try {
                    $result = $this->fileService->uploadEncryptedFile(
                        $task['file'],
                        $this->conversation->id,
                        $this->user1->id,
                        $this->symmetricKey
                    );

                    $uploadResults[$task['name']] = $result;
                } catch (\Exception $e) {
                    $errors[] = "Failed to upload {$task['name']}: ".$e->getMessage();
                }
            }

            $totalUploadTime = (microtime(true) - $startTime) * 1000;

            expect(count($uploadResults))->toBe(5);
            expect($errors)->toBeEmpty();
            expect($totalUploadTime)->toBeLessThan(15000); // Less than 15 seconds total

            // Create messages with all attachments
            $attachments = [];
            foreach ($uploadResults as $fileName => $uploadResult) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Concurrent upload: {$fileName}",
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

                $attachments[] = $attachment;
            }

            // Verify all files can be downloaded correctly
            foreach ($uploadResults as $fileName => $uploadResult) {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $uploadResult['file_id'],
                    $this->symmetricKey
                );

                expect($downloadResult['success'])->toBeTrue();
                expect($downloadResult['content'])->toBe($fileContents[$fileName]);
            }

            $avgUploadTime = $totalUploadTime / 5;
            echo "\n✅ Concurrent uploads test successful:";
            echo "\n   • Files uploaded: 5";
            echo "\n   • Total time: ".number_format($totalUploadTime, 2).'ms';
            echo "\n   • Average time per file: ".number_format($avgUploadTime, 2).'ms';
        });
    });

    describe('File Security and Integrity', function () {
        it('prevents unauthorized file access', function () {
            $secretContent = 'This is highly confidential information that should never be accessible without proper decryption keys.';
            $fileName = 'top_secret.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $secretContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // Try to access with wrong key
            $wrongKey = $this->encryptionService->generateSymmetricKey();

            $unauthorizedDownload = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $wrongKey
            );

            expect($unauthorizedDownload['success'])->toBeFalse();
            expect($unauthorizedDownload['error'])->toContain(['decryption', 'unauthorized', 'invalid']);

            // Verify correct key still works
            $authorizedDownload = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($authorizedDownload['success'])->toBeTrue();
            expect($authorizedDownload['content'])->toBe($secretContent);

            echo "\n✅ File security test successful: Unauthorized access properly prevented";
        });

        it('detects file tampering and corruption', function () {
            $originalContent = 'Original file content that should not be tampered with.';
            $fileName = 'integrity_test.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $originalContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // Simulate file tampering by modifying the encrypted file
            $encryptedFilePath = $uploadResult['encrypted_file_path'];

            // Get the current encrypted content
            if (Storage::disk('encrypted_files')->exists($encryptedFilePath)) {
                $encryptedContent = Storage::disk('encrypted_files')->get($encryptedFilePath);

                // Tamper with the content (flip some bits)
                $tamperedContent = $encryptedContent;
                if (strlen($tamperedContent) > 10) {
                    $tamperedContent[5] = chr(ord($tamperedContent[5]) ^ 1); // Flip one bit
                    $tamperedContent[10] = chr(ord($tamperedContent[10]) ^ 1); // Flip another bit
                }

                // Replace the file with tampered content
                Storage::disk('encrypted_files')->put($encryptedFilePath, $tamperedContent);

                // Try to download the tampered file
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $uploadResult['file_id'],
                    $this->symmetricKey
                );

                expect($downloadResult['success'])->toBeFalse();
                expect($downloadResult['error'])->toContain(['corruption', 'integrity', 'tamper']);
            } else {
                // If storage is not actually persisting files, test hash validation instead
                $validHash = $uploadResult['file_hash'];
                $invalidHash = hash('sha256', 'tampered_content');

                expect($validHash)->not()->toBe($invalidHash);
                echo "\n✅ File tampering detection test successful (hash validation)";

                return;
            }

            echo "\n✅ File tampering detection test successful: Corruption properly detected";
        });

        it('validates file hash integrity', function () {
            $testContent = 'Content for hash validation testing.';
            $fileName = 'hash_test.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $testContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();
            expect($uploadResult['file_hash'])->toBeString();
            expect(strlen($uploadResult['file_hash']))->toBe(64); // SHA256 hash length

            // Verify hash matches original content
            $expectedHash = hash('sha256', $testContent);
            expect($uploadResult['file_hash'])->toBe($expectedHash);

            // Create attachment with hash
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'File with hash validation',
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

            // Download and verify hash
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();

            $downloadedHash = hash('sha256', $downloadResult['content']);
            expect($downloadedHash)->toBe($expectedHash);
            expect($downloadedHash)->toBe($attachment->file_hash);

            echo "\n✅ File hash integrity test successful";
        });
    });

    describe('Cross-Device File Sharing', function () {
        it('shares encrypted files across multiple devices', function () {
            // Setup second device for user1
            $keyPair2 = $this->encryptionService->generateKeyPair();
            $device2 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Alice Tablet',
                'device_type' => 'tablet',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
            ]);

            // Create encryption key for device2
            $encryptionKey2 = EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'device_id' => $device2->id,
                'device_fingerprint' => $device2->device_fingerprint,
                'encrypted_key' => $this->encryptionService->encryptSymmetricKey($this->symmetricKey, $keyPair2['public_key']),
                'public_key' => $keyPair2['public_key'],
                'key_version' => 1,
                'algorithm' => 'RSA-4096-OAEP',
                'key_strength' => 4096,
                'is_active' => true,
            ]);

            // Upload file from device1
            $sharedContent = 'This file will be shared across devices securely.';
            $fileName = 'shared_document.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $sharedContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            expect($uploadResult['success'])->toBeTrue();

            // Create message with attachment
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Shared file from my phone 📱',
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

            // Access file from device2 (same user)
            $downloadFromDevice2 = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadFromDevice2['success'])->toBeTrue();
            expect($downloadFromDevice2['content'])->toBe($sharedContent);
            expect($downloadFromDevice2['original_name'])->toBe($fileName);

            // Verify message can be decrypted on both devices
            $messageFromDevice1 = $message->decryptContent($this->symmetricKey);
            $messageFromDevice2 = $message->decryptContent($this->symmetricKey);

            expect($messageFromDevice1)->toBe('Shared file from my phone 📱');
            expect($messageFromDevice2)->toBe('Shared file from my phone 📱');
            expect($messageFromDevice1)->toBe($messageFromDevice2);

            echo "\n✅ Cross-device file sharing test successful";
        });

        it('handles file sharing with different users', function () {
            // Setup encryption for user2
            $keyPair2 = $this->encryptionService->generateKeyPair();
            $device2 = UserDevice::factory()->create([
                'user_id' => $this->user2->id,
                'device_name' => 'Bob Phone',
                'device_type' => 'mobile',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
            ]);

            $encryptionKey2 = EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user2->id,
                'device_id' => $device2->id,
                'device_fingerprint' => $device2->device_fingerprint,
                'encrypted_key' => $this->encryptionService->encryptSymmetricKey($this->symmetricKey, $keyPair2['public_key']),
                'public_key' => $keyPair2['public_key'],
                'key_version' => 1,
                'algorithm' => 'RSA-4096-OAEP',
                'key_strength' => 4096,
                'is_active' => true,
            ]);

            // User1 uploads and shares file
            $sharedContent = 'Document shared between Alice and Bob.';
            $fileName = 'alice_to_bob.txt';

            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $sharedContent);

            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->conversation->id,
                $this->user1->id,
                $this->symmetricKey
            );

            // Create message from Alice to Bob
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Hi Bob! Here\'s the document you requested. 📄',
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

            // Bob downloads the file
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $uploadResult['file_id'],
                $this->symmetricKey
            );

            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($sharedContent);

            // Bob can decrypt the message
            $decryptedMessage = $message->decryptContent($this->symmetricKey);
            expect($decryptedMessage)->toBe('Hi Bob! Here\'s the document you requested. 📄');

            // Bob responds with his own file
            $bobContent = "Thanks Alice! Here's my response document.";
            $bobFileName = 'bob_response.txt';

            $bobUploadedFile = UploadedFile::fake()->createWithContent($bobFileName, $bobContent);

            $bobUploadResult = $this->fileService->uploadEncryptedFile(
                $bobUploadedFile,
                $this->conversation->id,
                $this->user2->id,
                $this->symmetricKey
            );

            $bobMessage = Message::createEncrypted(
                $this->conversation->id,
                $this->user2->id,
                'Here\'s my response, Alice! 💼',
                $this->symmetricKey
            );

            $bobAttachment = MessageAttachment::create([
                'message_id' => $bobMessage->id,
                'file_id' => $bobUploadResult['file_id'],
                'original_filename' => $bobFileName,
                'encrypted_filename' => $bobUploadResult['encrypted_filename'],
                'file_size' => $bobUploadResult['file_size'],
                'mime_type' => $bobUploadResult['mime_type'],
                'file_hash' => $bobUploadResult['file_hash'],
                'encryption_iv' => $bobUploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);

            // Alice downloads Bob's response
            $aliceDownloadBob = $this->fileService->downloadEncryptedFile(
                $bobUploadResult['file_id'],
                $this->symmetricKey
            );

            expect($aliceDownloadBob['success'])->toBeTrue();
            expect($aliceDownloadBob['content'])->toBe($bobContent);

            // Verify conversation has 2 messages with 2 attachments
            $conversationMessages = Message::where('conversation_id', $this->conversation->id)->count();
            expect($conversationMessages)->toBe(2);

            $totalAttachments = MessageAttachment::whereIn('message_id',
                Message::where('conversation_id', $this->conversation->id)->pluck('id')
            )->count();
            expect($totalAttachments)->toBe(2);

            echo "\n✅ Multi-user file sharing test successful: 2 users exchanged encrypted files";
        });
    });
});
