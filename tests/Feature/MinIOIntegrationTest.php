<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('minio disk can store and retrieve files', function () {
    // Skip test if MinIO is not configured
    if (config('filesystems.default') !== 'minio' && ! config('filesystems.disks.minio.endpoint')) {
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
    if (config('filesystems.default') !== 'minio' && ! config('filesystems.disks.minio.endpoint')) {
        $this->markTestSkipped('MinIO is not configured');
    }

    Storage::fake('minio');

    $disk = Storage::disk('minio');

    // Create a fake uploaded file
    $file = UploadedFile::fake()->create('test-document.pdf', 1024);

    // Store the file
    $path = $disk->putFile('uploads', $file);

    // Assert file was stored
    expect($disk->exists($path))->toBeTrue();

    // Assert file size is correct
    expect($disk->size($path))->toBe($file->getSize());

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
