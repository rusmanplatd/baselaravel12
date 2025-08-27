<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetupMinIO extends Command
{
    protected $signature = 'minio:setup {--bucket=laravel}';

    protected $description = 'Set up MinIO bucket and test connection';

    public function handle()
    {
        $bucketName = $this->option('bucket');

        $this->info('Setting up MinIO...');

        try {
            // Get MinIO disk configuration
            $disk = Storage::disk('minio');

            // Create S3 client directly for bucket management
            $config = config('filesystems.disks.minio');
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style_endpoint'],
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ]);

            // Check if bucket exists
            if (! $s3Client->doesBucketExist($bucketName)) {
                $this->info("Creating bucket: {$bucketName}");
                $s3Client->createBucket([
                    'Bucket' => $bucketName,
                ]);
                $this->info("Bucket '{$bucketName}' created successfully!");
            } else {
                $this->info("Bucket '{$bucketName}' already exists.");
            }

            // Test file upload
            $testContent = 'MinIO test file - '.now()->toDateTimeString();
            $testFileName = 'test/minio-test.txt';

            $this->info('Testing file upload...');
            $disk->put($testFileName, $testContent);
            $this->info("Test file uploaded: {$testFileName}");

            // Test file retrieval
            $retrieved = $disk->get($testFileName);
            if ($retrieved === $testContent) {
                $this->info('File retrieval test: ✓ PASSED');
            } else {
                $this->error('File retrieval test: ✗ FAILED');

                return 1;
            }

            // Test file deletion
            $disk->delete($testFileName);
            $this->info('Test file deleted successfully');

            $this->info('✓ MinIO setup and testing completed successfully!');
            $this->info('✓ MinIO Console: http://localhost:9001');
            $this->info('✓ Username: minioadmin');
            $this->info('✓ Password: minioadmin');

            return 0;

        } catch (AwsException $e) {
            $this->error('AWS/MinIO Error: '.$e->getMessage());

            return 1;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return 1;
        }
    }
}
