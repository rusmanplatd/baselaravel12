<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Services\ChatEncryptionService;
use App\Services\ChatFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->fileService = new ChatFileService($this->encryptionService);
    
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);

    // Setup fake storage for testing
    Storage::fake('encrypted_files');
});

describe('E2EE File and Media Encryption', function () {
    describe('Text File Encryption', function () {
        it('encrypts and decrypts text files end-to-end', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create test text file
            $textContent = "This is a confidential document.\nIt contains sensitive information.\n\nLine 3 with special chars: @#$%^&*()";
            $textFile = UploadedFile::fake()->createWithContent('document.txt', $textContent);
            
            // Encrypt file
            $encryptedResult = $this->fileService->encryptFile(
                file_get_contents($textFile->getPathname()),
                $symmetricKey,
                $textFile->getClientOriginalName(),
                $textFile->getMimeType()
            );

            expect($encryptedResult)->toHaveKeys(['encrypted_data', 'iv', 'hash', 'original_name', 'mime_type', 'size']);
            expect($encryptedResult['original_name'])->toBe('document.txt');
            expect($encryptedResult['mime_type'])->toBe('text/plain');
            expect($encryptedResult['size'])->toBe(strlen($textContent));

            // Decrypt file
            $decryptedContent = $this->fileService->decryptFile(
                $encryptedResult['encrypted_data'],
                $encryptedResult['iv'],
                $symmetricKey
            );

            expect($decryptedContent)->toBe($textContent);
        });

        it('handles various text file formats', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $testFiles = [
                'config.json' => '{"name": "test", "value": 123, "nested": {"key": "value"}}',
                'data.csv' => "Name,Age,City\nJohn,30,New York\nJane,25,Los Angeles",
                'script.js' => 'function encrypt() { return "secure"; }',
                'style.css' => 'body { color: red; background: blue; }',
                'document.xml' => '<?xml version="1.0"?><root><item>test</item></root>',
                'README.md' => '# Test Document\n## Section\n- Item 1\n- Item 2',
            ];

            foreach ($testFiles as $filename => $content) {
                $file = UploadedFile::fake()->createWithContent($filename, $content);
                
                // Encrypt
                $encrypted = $this->fileService->encryptFile(
                    file_get_contents($file->getPathname()),
                    $symmetricKey,
                    $file->getClientOriginalName(),
                    $file->getMimeType()
                );

                // Decrypt and verify
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );

                expect($decrypted)->toBe($content);
                expect($encrypted['original_name'])->toBe($filename);
            }
        });

        it('maintains file integrity with checksums', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = "File integrity test content with checksum verification.";
            
            $file = UploadedFile::fake()->createWithContent('integrity.txt', $content);
            
            $encrypted = $this->fileService->encryptFile(
                file_get_contents($file->getPathname()),
                $symmetricKey,
                $file->getClientOriginalName(),
                $file->getMimeType()
            );

            // Verify hash is present and correct
            expect($encrypted['hash'])->not()->toBeEmpty();
            $expectedHash = hash('sha256', $content);
            
            // Decrypt
            $decrypted = $this->fileService->decryptFile(
                $encrypted['encrypted_data'],
                $encrypted['iv'],
                $symmetricKey
            );

            // Verify content and hash
            expect($decrypted)->toBe($content);
            expect(hash('sha256', $decrypted))->toBe($expectedHash);
        });
    });

    describe('Binary File Encryption', function () {
        it('encrypts and decrypts binary files correctly', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create binary content (simulating a small executable or binary file)
            $binaryContent = '';
            for ($i = 0; $i < 256; $i++) {
                $binaryContent .= chr($i);
            }
            
            $binaryFile = UploadedFile::fake()->create('binary.bin', strlen($binaryContent));
            file_put_contents($binaryFile->getPathname(), $binaryContent);
            
            // Encrypt
            $encrypted = $this->fileService->encryptFile(
                file_get_contents($binaryFile->getPathname()),
                $symmetricKey,
                $binaryFile->getClientOriginalName(),
                'application/octet-stream'
            );

            // Decrypt and verify byte-for-byte accuracy
            $decrypted = $this->fileService->decryptFile(
                $encrypted['encrypted_data'],
                $encrypted['iv'],
                $symmetricKey
            );

            expect($decrypted)->toBe($binaryContent);
            expect(strlen($decrypted))->toBe(256);
            
            // Verify each byte
            for ($i = 0; $i < 256; $i++) {
                expect(ord($decrypted[$i]))->toBe($i);
            }
        });

        it('handles ZIP and archive files', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create a ZIP-like binary signature and content
            $zipSignature = "\x50\x4B\x03\x04"; // ZIP file signature
            $zipContent = $zipSignature . str_repeat("compressed_data", 100);
            
            $zipFile = UploadedFile::fake()->create('archive.zip', strlen($zipContent));
            file_put_contents($zipFile->getPathname(), $zipContent);
            
            // Encrypt
            $encrypted = $this->fileService->encryptFile(
                file_get_contents($zipFile->getPathname()),
                $symmetricKey,
                'archive.zip',
                'application/zip'
            );

            // Decrypt and verify
            $decrypted = $this->fileService->decryptFile(
                $encrypted['encrypted_data'],
                $encrypted['iv'],
                $symmetricKey
            );

            expect($decrypted)->toBe($zipContent);
            expect(substr($decrypted, 0, 4))->toBe($zipSignature);
            expect($encrypted['mime_type'])->toBe('application/zip');
        });
    });

    describe('Image File Encryption', function () {
        it('encrypts and decrypts image files maintaining binary integrity', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create fake image files
            $imageFiles = [
                'image.jpg' => UploadedFile::fake()->image('test.jpg', 100, 100),
                'image.png' => UploadedFile::fake()->image('test.png', 50, 50),
                'image.gif' => UploadedFile::fake()->create('test.gif', 1024, 'image/gif'),
            ];

            foreach ($imageFiles as $name => $file) {
                $originalContent = file_get_contents($file->getPathname());
                $originalSize = filesize($file->getPathname());
                
                // Encrypt
                $encrypted = $this->fileService->encryptFile(
                    $originalContent,
                    $symmetricKey,
                    $name,
                    $file->getMimeType()
                );

                expect($encrypted['size'])->toBe($originalSize);
                expect($encrypted['original_name'])->toBe($name);
                
                // Decrypt
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );

                // Verify binary integrity
                expect($decrypted)->toBe($originalContent);
                expect(strlen($decrypted))->toBe($originalSize);
            }
        });

        it('handles large image files efficiently', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create larger fake image (1MB)
            $largeImageSize = 1024 * 1024; // 1MB
            $largeImage = UploadedFile::fake()->create('large_image.jpg', $largeImageSize, 'image/jpeg');
            
            $originalContent = file_get_contents($largeImage->getPathname());
            
            $startTime = microtime(true);
            
            // Encrypt
            $encrypted = $this->fileService->encryptFile(
                $originalContent,
                $symmetricKey,
                'large_image.jpg',
                'image/jpeg'
            );
            
            $encryptTime = microtime(true) - $startTime;
            
            // Should encrypt within reasonable time
            expect($encryptTime)->toBeLessThan(10.0);
            expect($encrypted['size'])->toBe($largeImageSize);
            
            $decryptStartTime = microtime(true);
            
            // Decrypt
            $decrypted = $this->fileService->decryptFile(
                $encrypted['encrypted_data'],
                $encrypted['iv'],
                $symmetricKey
            );
            
            $decryptTime = microtime(true) - $decryptStartTime;
            
            expect($decryptTime)->toBeLessThan(10.0);
            expect($decrypted)->toBe($originalContent);
            expect(strlen($decrypted))->toBe($largeImageSize);
        });
    });

    describe('Voice Message Encryption', function () {
        it('encrypts voice messages with metadata', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Simulate voice message data
            $voiceContent = str_repeat("audio_data", 1000); // Fake audio binary
            $transcript = "This is the voice message transcript";
            $waveformData = implode(',', range(0, 100)); // Waveform visualization data
            $duration = 15; // seconds
            
            // Create voice message
            $voiceMessage = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $voiceContent,
                $symmetricKey,
                [
                    'type' => 'voice',
                    'voice_duration_seconds' => $duration,
                    'voice_transcript' => $transcript,
                    'voice_waveform_data' => $waveformData,
                ]
            );

            expect($voiceMessage->type)->toBe('voice');
            expect($voiceMessage->voice_duration_seconds)->toBe($duration);
            expect($voiceMessage->encrypted_voice_transcript)->not()->toBeNull();
            expect($voiceMessage->encrypted_voice_waveform_data)->not()->toBeNull();

            // Decrypt main content
            $decryptedContent = $voiceMessage->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($voiceContent);

            // Decrypt transcript
            $decryptedTranscript = $voiceMessage->decryptVoiceTranscript($symmetricKey);
            expect($decryptedTranscript)->toBe($transcript);

            // Decrypt waveform
            $decryptedWaveform = $voiceMessage->decryptVoiceWaveformData($symmetricKey);
            expect($decryptedWaveform)->toBe($waveformData);
        });

        it('handles voice messages without transcript', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $voiceContent = str_repeat("audio_only", 500);
            $waveformData = implode(',', range(0, 50));
            
            $voiceMessage = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $voiceContent,
                $symmetricKey,
                [
                    'type' => 'voice',
                    'voice_duration_seconds' => 10,
                    'voice_waveform_data' => $waveformData,
                    // No transcript provided
                ]
            );

            expect($voiceMessage->type)->toBe('voice');
            expect($voiceMessage->encrypted_voice_transcript)->toBeNull();
            expect($voiceMessage->encrypted_voice_waveform_data)->not()->toBeNull();

            // Decrypt should work without transcript
            $decryptedContent = $voiceMessage->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($voiceContent);

            $decryptedWaveform = $voiceMessage->decryptVoiceWaveformData($symmetricKey);
            expect($decryptedWaveform)->toBe($waveformData);
        });
    });

    describe('File Message Integration', function () {
        it('creates encrypted file messages with full metadata', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create test file
            $fileContent = "Confidential document content for message attachment.";
            $fileName = "confidential.txt";
            $mimeType = "text/plain";
            
            $testFile = UploadedFile::fake()->createWithContent($fileName, $fileContent);
            
            // Encrypt file
            $encryptedFile = $this->fileService->encryptFile(
                file_get_contents($testFile->getPathname()),
                $symmetricKey,
                $fileName,
                $mimeType
            );

            // Create file message
            $fileMessage = Message::create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user1->id,
                'type' => 'file',
                'encrypted_content' => json_encode([
                    'data' => $encryptedFile['encrypted_data'],
                    'iv' => $encryptedFile['iv'],
                    'hmac' => $encryptedFile['hash'],
                    'timestamp' => time(),
                    'nonce' => bin2hex(random_bytes(16)),
                ]),
                'content_hash' => hash('sha256', $fileContent),
                'content_hmac' => hash_hmac('sha256', $encryptedFile['encrypted_data'], $symmetricKey),
                'file_name' => $encryptedFile['original_name'],
                'file_mime_type' => $encryptedFile['mime_type'],
                'file_size' => $encryptedFile['size'],
            ]);

            expect($fileMessage->type)->toBe('file');
            expect($fileMessage->file_name)->toBe($fileName);
            expect($fileMessage->file_mime_type)->toBe($mimeType);
            expect($fileMessage->file_size)->toBe(strlen($fileContent));

            // Decrypt file content
            $encryptedData = json_decode($fileMessage->encrypted_content, true);
            $decryptedContent = $this->fileService->decryptFile(
                $encryptedData['data'],
                $encryptedData['iv'],
                $symmetricKey
            );

            expect($decryptedContent)->toBe($fileContent);
        });

        it('handles multiple file attachments in conversation', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $testFiles = [
                ['name' => 'document1.pdf', 'content' => 'PDF content 1', 'type' => 'application/pdf'],
                ['name' => 'image1.jpg', 'content' => 'JPEG binary data 1', 'type' => 'image/jpeg'],
                ['name' => 'data.xlsx', 'content' => 'Excel data content', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ];

            $fileMessages = [];

            foreach ($testFiles as $fileData) {
                // Encrypt file
                $encrypted = $this->fileService->encryptFile(
                    $fileData['content'],
                    $symmetricKey,
                    $fileData['name'],
                    $fileData['type']
                );

                // Create message
                $message = Message::create([
                    'conversation_id' => $this->conversation->id,
                    'sender_id' => $this->user1->id,
                    'type' => 'file',
                    'encrypted_content' => json_encode([
                        'data' => $encrypted['encrypted_data'],
                        'iv' => $encrypted['iv'],
                        'hmac' => $encrypted['hash'],
                        'timestamp' => time(),
                        'nonce' => bin2hex(random_bytes(16)),
                    ]),
                    'content_hash' => hash('sha256', $fileData['content']),
                    'content_hmac' => hash_hmac('sha256', $encrypted['encrypted_data'], $symmetricKey),
                    'file_name' => $fileData['name'],
                    'file_mime_type' => $fileData['type'],
                    'file_size' => strlen($fileData['content']),
                ]);

                $fileMessages[] = $message;
            }

            expect(count($fileMessages))->toBe(3);

            // Verify all files can be decrypted
            foreach ($fileMessages as $index => $message) {
                $encryptedData = json_decode($message->encrypted_content, true);
                $decryptedContent = $this->fileService->decryptFile(
                    $encryptedData['data'],
                    $encryptedData['iv'],
                    $symmetricKey
                );

                expect($decryptedContent)->toBe($testFiles[$index]['content']);
                expect($message->file_name)->toBe($testFiles[$index]['name']);
                expect($message->file_mime_type)->toBe($testFiles[$index]['type']);
            }
        });
    });

    describe('File Size and Format Limitations', function () {
        it('handles maximum file size constraints', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Test with different file sizes
            $fileSizes = [
                '10KB' => 10 * 1024,
                '100KB' => 100 * 1024,
                '1MB' => 1024 * 1024,
                '10MB' => 10 * 1024 * 1024,
            ];

            foreach ($fileSizes as $sizeName => $sizeBytes) {
                $content = str_repeat('A', $sizeBytes);
                $fileName = "large_file_{$sizeName}.txt";
                
                $startTime = microtime(true);
                $startMemory = memory_get_usage();
                
                // Encrypt
                $encrypted = $this->fileService->encryptFile(
                    $content,
                    $symmetricKey,
                    $fileName,
                    'text/plain'
                );
                
                $encryptTime = microtime(true) - $startTime;
                $memoryUsed = memory_get_usage() - $startMemory;
                
                // Performance checks (adjust based on system capacity)
                expect($encryptTime)->toBeLessThan(30.0); // Within 30 seconds
                expect($memoryUsed)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB overhead
                
                // Decrypt sample to verify
                $decryptStartTime = microtime(true);
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );
                $decryptTime = microtime(true) - $decryptStartTime;
                
                expect($decryptTime)->toBeLessThan(30.0);
                expect(strlen($decrypted))->toBe($sizeBytes);
                expect(substr($decrypted, 0, 10))->toBe(str_repeat('A', 10));
                
                // Cleanup memory
                unset($content, $encrypted, $decrypted);
                gc_collect_cycles();
            }
        });

        it('validates file format security', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Test various file types for security considerations
            $testFiles = [
                'safe_text.txt' => ['content' => 'Safe text content', 'safe' => true],
                'script.js' => ['content' => 'alert("potentially dangerous")', 'safe' => false],
                'executable.exe' => ['content' => 'MZ\x90\x00', 'safe' => false], // PE header
                'image.jpg' => ['content' => "\xFF\xD8\xFF", 'safe' => true], // JPEG header
                'archive.zip' => ['content' => 'PK\x03\x04', 'safe' => true], // ZIP header
            ];

            foreach ($testFiles as $fileName => $fileData) {
                $encrypted = $this->fileService->encryptFile(
                    $fileData['content'],
                    $symmetricKey,
                    $fileName,
                    'application/octet-stream'
                );

                // All files should encrypt successfully
                expect($encrypted['encrypted_data'])->not()->toBeEmpty();
                expect($encrypted['original_name'])->toBe($fileName);

                // Decrypt to verify integrity
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );

                expect($decrypted)->toBe($fileData['content']);
                
                // File type safety checks would be implemented at application level,
                // not at encryption level. Encryption preserves all data equally.
            }
        });
    });

    describe('File Encryption Error Handling', function () {
        it('handles encryption failures gracefully', function () {
            // Test with invalid symmetric key
            $invalidKey = 'invalid_key_too_short';
            $content = 'Test file content';
            
            expect(fn () => $this->fileService->encryptFile($content, $invalidKey, 'test.txt', 'text/plain'))
                ->toThrow(\App\Exceptions\EncryptionException::class);
        });

        it('handles decryption failures with wrong keys', function () {
            $correctKey = $this->encryptionService->generateSymmetricKey();
            $wrongKey = $this->encryptionService->generateSymmetricKey();
            
            $content = 'Secret file content';
            
            // Encrypt with correct key
            $encrypted = $this->fileService->encryptFile($content, $correctKey, 'secret.txt', 'text/plain');
            
            // Try to decrypt with wrong key
            expect(fn () => $this->fileService->decryptFile($encrypted['encrypted_data'], $encrypted['iv'], $wrongKey))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('detects corrupted encrypted file data', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = 'File content to be corrupted';
            
            // Encrypt normally
            $encrypted = $this->fileService->encryptFile($content, $symmetricKey, 'test.txt', 'text/plain');
            
            // Corrupt the encrypted data
            $corruptedData = substr($encrypted['encrypted_data'], 0, -10) . 'CORRUPTED!';
            
            // Attempt to decrypt corrupted data
            expect(fn () => $this->fileService->decryptFile($corruptedData, $encrypted['iv'], $symmetricKey))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles empty file encryption', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $emptyContent = '';
            
            // Empty files should be handled gracefully
            $encrypted = $this->fileService->encryptFile($emptyContent, $symmetricKey, 'empty.txt', 'text/plain');
            
            expect($encrypted['size'])->toBe(0);
            expect($encrypted['original_name'])->toBe('empty.txt');
            
            $decrypted = $this->fileService->decryptFile($encrypted['encrypted_data'], $encrypted['iv'], $symmetricKey);
            expect($decrypted)->toBe($emptyContent);
        });
    });

    describe('File Metadata Preservation', function () {
        it('preserves all file metadata through encryption/decryption cycle', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $testCases = [
                ['name' => 'document.pdf', 'type' => 'application/pdf', 'content' => 'PDF document content'],
                ['name' => 'photo.jpeg', 'type' => 'image/jpeg', 'content' => 'Binary photo data'],
                ['name' => 'data.csv', 'type' => 'text/csv', 'content' => 'CSV,data,content'],
                ['name' => 'archive.tar.gz', 'type' => 'application/gzip', 'content' => 'Compressed archive'],
            ];

            foreach ($testCases as $testCase) {
                $encrypted = $this->fileService->encryptFile(
                    $testCase['content'],
                    $symmetricKey,
                    $testCase['name'],
                    $testCase['type']
                );

                // Verify all metadata is preserved
                expect($encrypted['original_name'])->toBe($testCase['name']);
                expect($encrypted['mime_type'])->toBe($testCase['type']);
                expect($encrypted['size'])->toBe(strlen($testCase['content']));
                expect($encrypted['hash'])->toBe(hash('sha256', $testCase['content']));

                // Decrypt and verify content
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );

                expect($decrypted)->toBe($testCase['content']);
                expect(hash('sha256', $decrypted))->toBe($encrypted['hash']);
            }
        });

        it('handles special characters in filenames', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $specialFilenames = [
                'file with spaces.txt',
                'file-with-dashes.txt',
                'file_with_underscores.txt',
                'file.with.dots.txt',
                'файл-кириллица.txt',
                '文件中文.txt',
                'file(with)parentheses.txt',
                'file[with]brackets.txt',
                'file{with}braces.txt',
            ];

            foreach ($specialFilenames as $filename) {
                $content = "Content for {$filename}";
                
                $encrypted = $this->fileService->encryptFile($content, $symmetricKey, $filename, 'text/plain');
                
                expect($encrypted['original_name'])->toBe($filename);
                
                $decrypted = $this->fileService->decryptFile(
                    $encrypted['encrypted_data'],
                    $encrypted['iv'],
                    $symmetricKey
                );
                
                expect($decrypted)->toBe($content);
            }
        });
    });
});