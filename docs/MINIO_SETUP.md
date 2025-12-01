# MinIO Integration Setup

This guide covers the MinIO object storage integration in the Laravel application.

## Overview

MinIO is an S3-compatible object storage service that runs in your Docker environment. It provides:

- S3-compatible API for file storage
- Built-in web console for file management
- High performance and scalability
- Local development storage solution

## Configuration

### Docker Services

MinIO services are configured in both `docker-compose.yml` and `docker-compose.dev.yml`:

- **MinIO Server**: Runs on port 9000 (API)
- **MinIO Console**: Runs on port 9001 (Web UI)
- **Default Credentials**: minioadmin/minioadmin

### Laravel Configuration

The MinIO disk is configured in `config/filesystems.php`:

```php
'minio' => [
    'driver' => 's3',
    'key' => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
    'secret' => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
    'region' => env('MINIO_DEFAULT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    'bucket' => env('MINIO_BUCKET', env('AWS_BUCKET')),
    'url' => env('MINIO_URL'),
    'endpoint' => env('MINIO_ENDPOINT', env('AWS_ENDPOINT')),
    'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', true)),
    'throw' => false,
    'report' => false,
],
```

### Environment Variables

Add these to your `.env` file:

```bash
# Set MinIO as default filesystem (optional)
FILESYSTEM_DISK=minio

# MinIO Configuration
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_DEFAULT_REGION=us-east-1
MINIO_BUCKET=laravel
MINIO_ENDPOINT=http://localhost:9000
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

For Docker environments, the endpoint should be `http://minio:9000`.

## Setup and Testing

### 1. Start Docker Services

```bash
docker-compose up -d
```

### 2. Set up MinIO Bucket

Run the setup command to create the bucket and test the connection:

```bash
php artisan minio:setup
```

This command will:
- Create the configured bucket if it doesn't exist
- Test file upload/download/deletion
- Verify the MinIO connection

### 3. Access MinIO Console

Visit http://localhost:9001 in your browser:
- Username: `minioadmin`
- Password: `minioadmin`

## Usage in Code

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Store a file
$path = Storage::disk('minio')->put('folder/file.txt', $content);

// Retrieve a file
$content = Storage::disk('minio')->get('folder/file.txt');

// Check if file exists
$exists = Storage::disk('minio')->exists('folder/file.txt');

// Delete a file
Storage::disk('minio')->delete('folder/file.txt');

// Get file URL (for public access)
$url = Storage::disk('minio')->url('folder/file.txt');
```

### File Uploads

```php
// Handle uploaded files
$request->validate(['file' => 'required|file|max:10240']);

$path = Storage::disk('minio')->putFile('uploads', $request->file('file'));
```

### Chat File Service Integration

The existing `ChatFileService` will automatically use MinIO when it's configured as the default disk or explicitly specified.

## Testing

Run the MinIO integration tests:

```bash
php artisan test tests/Feature/MinIOIntegrationTest.php
```

## Troubleshooting

### Connection Issues

1. **Check Docker services**: Ensure MinIO container is running
2. **Verify network**: Make sure the app container can reach MinIO
3. **Check credentials**: Verify MINIO_ACCESS_KEY and MINIO_SECRET_KEY
4. **Check endpoint**: Use `http://minio:9000` for Docker, `http://localhost:9000` for local

### Bucket Access Issues

1. **Create bucket manually**: Use the MinIO console at http://localhost:9001
2. **Check bucket policies**: Ensure proper read/write permissions
3. **Run setup command**: `php artisan minio:setup` to create and test

### Performance Considerations

- MinIO performs better with larger files (>1MB)
- Use appropriate bucket policies for public/private access
- Consider using CDN for public file distribution
- Monitor storage usage through MinIO console

## Production Deployment

For production:

1. Use strong credentials (not minioadmin/minioadmin)
2. Configure proper bucket policies
3. Set up SSL/TLS termination
4. Consider using external MinIO cluster
5. Implement backup strategies
6. Monitor storage metrics

## Security Notes

- Change default credentials in production
- Use IAM policies for fine-grained access control
- Enable encryption at rest if required
- Implement proper network security (VPC, firewalls)
- Regular security updates for MinIO container