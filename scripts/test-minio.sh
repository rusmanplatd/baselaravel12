#!/bin/bash

# Test MinIO integration script
# This script tests MinIO setup and integration

echo "🚀 Testing MinIO Integration..."
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

echo "✅ Docker is running"

# Start services in background
echo "🐳 Starting Docker services..."
docker compose up -d minio postgres redis

# Wait for MinIO to be ready
echo "⏳ Waiting for MinIO to be ready..."
sleep 10

# Check if MinIO is responding
echo "🔍 Checking MinIO health..."
if curl -f http://localhost:9000/minio/health/live > /dev/null 2>&1; then
    echo "✅ MinIO is healthy"
else
    echo "❌ MinIO is not responding"
    echo "💡 Try: docker compose logs minio"
    exit 1
fi

# Set environment to use MinIO for testing
export FILESYSTEM_DISK=minio
export MINIO_ACCESS_KEY=minioadmin
export MINIO_SECRET_KEY=minioadmin
export MINIO_DEFAULT_REGION=us-east-1
export MINIO_BUCKET=laravel
export MINIO_ENDPOINT=http://localhost:9000
export MINIO_USE_PATH_STYLE_ENDPOINT=true

# Test MinIO setup command
echo "🔧 Running MinIO setup..."
if php artisan minio:setup; then
    echo "✅ MinIO setup completed successfully"
else
    echo "❌ MinIO setup failed"
    exit 1
fi

# Run tests with MinIO configuration
echo "🧪 Running MinIO integration tests..."
if FILESYSTEM_DISK=minio php artisan test tests/Feature/MinIOIntegrationTest.php; then
    echo "✅ All tests passed"
else
    echo "❌ Some tests failed"
    exit 1
fi

echo ""
echo "🎉 MinIO integration test completed successfully!"
echo ""
echo "📋 MinIO Information:"
echo "   - API Endpoint: http://localhost:9000"
echo "   - Web Console: http://localhost:9001"
echo "   - Username: minioadmin"
echo "   - Password: minioadmin"
echo "   - Bucket: laravel"
echo ""
echo "💡 To use MinIO in your application:"
echo "   1. Set FILESYSTEM_DISK=minio in your .env file"
echo "   2. Configure MinIO environment variables"
echo "   3. Run 'php artisan minio:setup' to create bucket"