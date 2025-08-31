<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('minio disk can store and retrieve files', function () {
    // Skip test if MinIO is not configured
    if (! config('filesystems.disks.minio.endpoint')) {
        $this->markTestSkipped('MinIO is not configured');
    }

    Storage::fake('minio');

    $disk = Storage::disk('minio');

    // Test file creation
    $content = 'Test MinIO integration content';
    $filename = 'test-files/minio-test.txt';

    $disk->put($filename, $content);

    // Assert file exists
    expect($disk->exists($filename))->toBeTrue();

    // Assert content is correct
    expect($disk->get($filename))->toBe($content);

    // Test file deletion
    $disk->delete($filename);
    expect($disk->exists($filename))->toBeFalse();
});

test('minio disk can handle file uploads', function () {
    // Skip test if MinIO is not configured
    if (! config('filesystems.disks.minio.endpoint')) {
        $this->markTestSkipped('MinIO is not configured');
    }

    Storage::fake('minio');

    $disk = Storage::disk('minio');

    // Create a fake uploaded file with actual content
    $file = UploadedFile::fake()->createWithContent('test-document.pdf', 'Test PDF content for MinIO integration test');

    // Store the file
    $path = $disk->putFile('uploads', $file);

    // Assert file was stored
    expect($disk->exists($path))->toBeTrue();

    // Assert file has content
    $content = $disk->get($path);
    expect($content)->toContain('Test PDF content');

    // Clean up
    $disk->delete($path);
});

test('minio configuration is valid when enabled', function () {
    $config = config('filesystems.disks.minio');

    if (! $config || ! $config['endpoint']) {
        $this->markTestSkipped('MinIO is not configured');
    }

    // Check required configuration keys
    expect($config)->toHaveKeys([
        'driver',
        'key',
        'secret',
        'region',
        'bucket',
        'endpoint',
        'use_path_style_endpoint',
    ]);

    expect($config['driver'])->toBe('s3');
    expect($config['use_path_style_endpoint'])->toBeTrue();
});

test('minio real integration with actual storage', function () {
    if (! config('filesystems.disks.minio.endpoint')) {
        $this->markTestSkipped('MinIO is not configured');
    }

    $disk = Storage::disk('minio');
    $testContent = 'Real MinIO integration test - '.now()->toISOString();
    $testFilename = 'test-integration-'.uniqid().'.txt';

    try {
        // Store file
        $success = $disk->put($testFilename, $testContent);
        expect($success)->toBeTrue();

        // Verify file exists
        expect($disk->exists($testFilename))->toBeTrue();

        // Verify file content
        expect($disk->get($testFilename))->toBe($testContent);

        // Verify file size
        expect($disk->size($testFilename))->toBe(strlen($testContent));

        // Verify we can get file metadata
        $lastModified = $disk->lastModified($testFilename);
        expect($lastModified)->toBeInt();
        expect($lastModified)->toBeGreaterThan(0);
    } finally {
        // Cleanup - delete test file
        if ($disk->exists($testFilename)) {
            $disk->delete($testFilename);
        }
    }
})->skip(fn () => app()->environment('testing') && ! config('filesystems.disks.minio.endpoint'), 'MinIO not available in testing environment');
