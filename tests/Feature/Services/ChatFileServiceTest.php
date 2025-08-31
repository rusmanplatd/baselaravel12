<?php

declare(strict_types=1);

use App\Exceptions\ChatFileException;
use App\Services\ChatEncryptionService;
use App\Services\ChatFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('chat-files');
    $this->encryptionService = app(ChatEncryptionService::class);
    $this->fileService = app(ChatFileService::class);
    $this->symmetricKey = $this->encryptionService->generateSymmetricKey();
});

describe('File Upload and Storage', function () {
    it('can store a text file', function () {
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        expect($result)->toHaveKeys([
            'file_name',
            'file_size',
            'mime_type',
            'file_path',
            'encryption_iv',
            'encryption_tag',
        ]);

        expect($result['file_name'])->toBe('document.txt');
        expect($result['file_size'])->toBe($file->getSize());
        expect($result['mime_type'])->toBe('text/plain');

        // Verify file is stored
        Storage::disk('chat-files')->assertExists($result['file_path']);
    });

    it('can store an image file', function () {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        expect($result['file_name'])->toBe('photo.jpg');
        expect($result['mime_type'])->toContain('image/');

        Storage::disk('chat-files')->assertExists($result['file_path']);
    });

    it('encrypts file content during storage', function () {
        $originalContent = 'This is secret file content';
        $file = UploadedFile::fake()->createWithContent('secret.txt', $originalContent);

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        // Read stored file content
        $storedContent = Storage::disk('chat-files')->get($result['file_path']);

        // Stored content should be encrypted (different from original)
        expect($storedContent)->not()->toBe($originalContent);
        expect($storedContent)->not()->toContain('secret');
    });

    it('generates unique file paths for same filename', function () {
        $file1 = UploadedFile::fake()->create('document.txt', 100);
        $file2 = UploadedFile::fake()->create('document.txt', 200);

        $result1 = $this->fileService->storeFile($file1, $this->symmetricKey);
        $result2 = $this->fileService->storeFile($file2, $this->symmetricKey);

        expect($result1['file_path'])->not()->toBe($result2['file_path']);

        // Both files should be stored
        Storage::disk('chat-files')->assertExists($result1['file_path']);
        Storage::disk('chat-files')->assertExists($result2['file_path']);
    });

    it('validates file size limit', function () {
        // Create file larger than 100MB
        $largeFile = UploadedFile::fake()->create('large.pdf', 101 * 1024); // 101MB

        expect(fn () => $this->fileService->storeFile($largeFile, $this->symmetricKey))
            ->toThrow(ChatFileException::class, 'File size exceeds maximum allowed size of 100MB');
    });

    it('validates allowed file types', function () {
        $invalidFile = UploadedFile::fake()->create('malware.exe', 1024);

        expect(fn () => $this->fileService->storeFile($invalidFile, $this->symmetricKey))
            ->toThrow(ChatFileException::class, 'File type not allowed');
    });

    it('allows common document types', function () {
        $allowedTypes = [
            ['document.pdf', 'application/pdf'],
            ['document.doc', 'application/msword'],
            ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['spreadsheet.xls', 'application/vnd.ms-excel'],
            ['spreadsheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['presentation.ppt', 'application/vnd.ms-powerpoint'],
            ['presentation.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['text.txt', 'text/plain'],
        ];

        foreach ($allowedTypes as [$filename, $mimeType]) {
            $file = UploadedFile::fake()->create($filename, 100);
            $file->mimeType = $mimeType;

            $result = $this->fileService->storeFile($file, $this->symmetricKey);
            expect($result['mime_type'])->toBe($mimeType);
        }
    });

    it('allows common image types', function () {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        foreach ($imageTypes as $extension) {
            if ($extension === 'svg') {
                $file = UploadedFile::fake()->create("image.{$extension}", 100);
                $file->mimeType = 'image/svg+xml';
            } else {
                $file = UploadedFile::fake()->image("image.{$extension}");
            }

            $result = $this->fileService->storeFile($file, $this->symmetricKey);
            expect($result['file_name'])->toBe("image.{$extension}");
        }
    });

    it('allows common video types', function () {
        $videoTypes = [
            ['video.mp4', 'video/mp4'],
            ['video.webm', 'video/webm'],
            // Skip .ogg as it can be detected as audio/ogg instead of video/ogg by fake files
            ['video.avi', 'video/x-msvideo'],
            ['video.mov', 'video/quicktime'],
        ];

        foreach ($videoTypes as [$filename, $expectedMimeType]) {
            $file = UploadedFile::fake()->create($filename, 1024);

            $result = $this->fileService->storeFile($file, $this->symmetricKey);
            expect($result['file_name'])->toBe($filename);
            expect($result['mime_type'])->toBeString();
        }
    });

    it('allows common audio types', function () {
        $audioTypes = [
            ['audio.mp3', 'audio/mpeg'],
            ['audio.wav', 'audio/wav'],
            ['audio.ogg', 'audio/ogg'],
            ['audio.m4a', 'audio/mp4'],
        ];

        foreach ($audioTypes as [$filename, $expectedMimeType]) {
            $file = UploadedFile::fake()->create($filename, 1024);

            $result = $this->fileService->storeFile($file, $this->symmetricKey);
            expect($result['file_name'])->toBe($filename);
            expect($result['mime_type'])->toBeString();
        }
    });
});

describe('File Retrieval and Decryption', function () {
    it('can retrieve and decrypt stored file', function () {
        $originalContent = 'This is the original file content';
        $file = UploadedFile::fake()->createWithContent('test.txt', $originalContent);

        $storeResult = $this->fileService->storeFile($file, $this->symmetricKey);

        $retrievedContent = $this->fileService->getFileContent(
            $storeResult['file_path'],
            $this->symmetricKey,
            $storeResult['encryption_iv'],
            $storeResult['encryption_tag']
        );

        expect($retrievedContent)->toBe($originalContent);
    });

    it('throws exception for non-existent file', function () {
        expect(fn () => $this->fileService->getFileContent(
            'non-existent-path',
            $this->symmetricKey,
            'iv',
            'tag'
        ))->toThrow(ChatFileException::class, 'File not found');
    });

    it('throws exception for invalid decryption parameters', function () {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $storeResult = $this->fileService->storeFile($file, $this->symmetricKey);

        // Try with wrong symmetric key
        $wrongKey = $this->encryptionService->generateSymmetricKey();

        expect(fn () => $this->fileService->getFileContent(
            $storeResult['file_path'],
            $wrongKey,
            $storeResult['encryption_iv'],
            $storeResult['encryption_tag']
        ))->toThrow(ChatFileException::class, 'Failed to decrypt file');
    });
});

describe('File Deletion', function () {
    it('can delete stored file', function () {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $storeResult = $this->fileService->storeFile($file, $this->symmetricKey);

        // Verify file exists
        Storage::disk('chat-files')->assertExists($storeResult['file_path']);

        $deleted = $this->fileService->deleteFile($storeResult['file_path']);

        expect($deleted)->toBeTrue();
        Storage::disk('chat-files')->assertMissing($storeResult['file_path']);
    });

    it('handles deletion of non-existent file gracefully', function () {
        $deleted = $this->fileService->deleteFile('non-existent-path');

        expect($deleted)->toBeFalse();
    });

    it('can delete multiple files', function () {
        $files = [];

        // Store multiple files
        for ($i = 0; $i < 3; $i++) {
            $file = UploadedFile::fake()->create("test{$i}.txt", 100);
            $storeResult = $this->fileService->storeFile($file, $this->symmetricKey);
            $files[] = $storeResult['file_path'];
        }

        // Verify all files exist
        foreach ($files as $filePath) {
            Storage::disk('chat-files')->assertExists($filePath);
        }

        $deletedCount = $this->fileService->deleteMultipleFiles($files);

        expect($deletedCount)->toBe(3);

        // Verify all files are deleted
        foreach ($files as $filePath) {
            Storage::disk('chat-files')->assertMissing($filePath);
        }
    });
});

describe('File Metadata', function () {
    it('extracts correct file metadata', function () {
        $file = UploadedFile::fake()->create('document.pdf', 2048, 'application/pdf');

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        expect($result['file_name'])->toBe('document.pdf');
        expect($result['file_size'])->toBe($file->getSize());
        expect($result['mime_type'])->toBe('application/pdf');
    });

    it('handles files with no extension', function () {
        $file = UploadedFile::fake()->create('README', 100, 'text/plain');

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        expect($result['file_name'])->toBe('README');
        expect($result['mime_type'])->toBe('text/plain');
    });

    it('sanitizes file names', function () {
        $dangerousName = '../../../etc/passwd';
        $file = UploadedFile::fake()->create($dangerousName, 100, 'text/plain');

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        // File name should be sanitized
        expect($result['file_name'])->not()->toContain('../');
        expect($result['file_name'])->not()->toContain('/');
    });

    it('handles very long file names', function () {
        $longName = str_repeat('a', 300).'.txt';
        $file = UploadedFile::fake()->create($longName, 100, 'text/plain');

        $result = $this->fileService->storeFile($file, $this->symmetricKey);

        // File name should be truncated but still valid
        expect(strlen($result['file_name']))->toBeLessThanOrEqual(255);
        expect($result['file_name'])->toEndWith('.txt');
    });
});

describe('Security and Validation', function () {
    it('rejects executable files', function () {
        $executableExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'sh', 'php', 'js'];

        foreach ($executableExtensions as $extension) {
            $file = UploadedFile::fake()->create("malware.{$extension}", 100);

            expect(fn () => $this->fileService->storeFile($file, $this->symmetricKey))
                ->toThrow(ChatFileException::class, 'File type not allowed');
        }
    });

    it('validates file content matches extension', function () {
        // Use a mock to simulate a file with mismatched extension/MIME type
        $mockFile = Mockery::mock(UploadedFile::class);
        $mockFile->shouldReceive('getMimeType')->andReturn('application/x-executable');
        $mockFile->shouldReceive('getClientOriginalName')->andReturn('fake.jpg');
        $mockFile->shouldReceive('getSize')->andReturn(100);
        $mockFile->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
        $mockFile->shouldReceive('getRealPath')->andReturn('/tmp/fake');
        $mockFile->shouldReceive('isValid')->andReturn(true);

        // This should be rejected due to MIME type not being allowed
        expect(fn () => $this->fileService->storeFile($mockFile, $this->symmetricKey))
            ->toThrow(ChatFileException::class, 'File type not allowed');
    });

    it('prevents directory traversal in file paths', function () {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $storeResult = $this->fileService->storeFile($file, $this->symmetricKey);

        // Try to access file with directory traversal
        expect(fn () => $this->fileService->getFileContent(
            '../../../'.$storeResult['file_path'],
            $this->symmetricKey,
            $storeResult['encryption_iv'],
            $storeResult['encryption_tag']
        ))->toThrow(ChatFileException::class, 'Invalid file path');
    });

    it('validates encryption parameters', function () {
        $file = UploadedFile::fake()->create('test.txt', 100);

        expect(fn () => $this->fileService->storeFile($file, 'invalid-key'))
            ->toThrow(ChatFileException::class);
    });
});

describe('Performance and Limits', function () {
    it('handles maximum allowed file size', function () {
        // Create file exactly at 100MB limit
        $maxSizeFile = UploadedFile::fake()->create('max.pdf', 100 * 1024, 'application/pdf');

        $result = $this->fileService->storeFile($maxSizeFile, $this->symmetricKey);

        expect($result['file_size'])->toBe(100 * 1024 * 1024);
    });

    it('processes large files efficiently', function () {
        // Create a 10MB file
        $largeFile = UploadedFile::fake()->create('large.pdf', 10 * 1024, 'application/pdf');

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $this->fileService->storeFile($largeFile, $this->symmetricKey);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $processingTime = $endTime - $startTime;
        $memoryUsage = $endMemory - $startMemory;

        // Should process reasonably quickly and not use excessive memory
        expect($processingTime)->toBeLessThan(10.0); // Less than 10 seconds
        expect($memoryUsage)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB memory overhead

        // Verify file is properly stored
        Storage::disk('chat-files')->assertExists($result['file_path']);
    });

    it('handles concurrent file uploads', function () {
        $files = [];
        $results = [];

        // Create multiple files
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->create("concurrent{$i}.txt", 100);
        }

        $startTime = microtime(true);

        // Store files concurrently (simulated)
        foreach ($files as $file) {
            $results[] = $this->fileService->storeFile($file, $this->symmetricKey);
        }

        $endTime = microtime(true);

        expect($results)->toHaveCount(5);
        expect($endTime - $startTime)->toBeLessThan(5.0);

        // Verify all files are stored with unique paths
        $paths = array_column($results, 'file_path');
        expect($paths)->toBe(array_unique($paths));

        foreach ($paths as $path) {
            Storage::disk('chat-files')->assertExists($path);
        }
    });
});

describe('Error Recovery', function () {
    it('cleans up on encryption failure', function () {
        // Mock encryption service to fail
        $mockEncryption = $this->mock(ChatEncryptionService::class);
        $mockEncryption->shouldReceive('encryptFile')
            ->andThrow(new \Exception('Encryption failed'));

        $fileService = new ChatFileService($mockEncryption);
        $file = UploadedFile::fake()->create('test.txt', 100);

        expect(fn () => $fileService->storeFile($file, $this->symmetricKey))
            ->toThrow(ChatFileException::class, 'File upload failed');

        // Verify no files were left behind
        $files = Storage::disk('chat-files')->allFiles();
        expect($files)->toHaveCount(0);
    });

    it('handles storage disk full scenario', function () {
        // Mock the disk to return false when putting files
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('chat-files')->andReturn($mockDisk);

        $file = UploadedFile::fake()->create('test.txt', 100);

        expect(fn () => $this->fileService->storeFile($file, $this->symmetricKey))
            ->toThrow(ChatFileException::class, 'Failed to store file');
    });
});
