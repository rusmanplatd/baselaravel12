# Docker Environment Setup

This directory contains Docker configurations for running the Laravel + React application across different environments using **FrankenPHP** - a modern PHP application server written in Go that gives performance boosts.

## Quick Start

```bash
# Install and setup project
make install

# Or manually
./scripts/docker-setup.sh local --fresh --build
```

## Architecture

### FrankenPHP Benefits
- **Performance**: Native binary with optimized PHP runtime
- **HTTP/2 & HTTP/3**: Native support for modern protocols  
- **Early Hints**: 103 Early Hints support for better performance
- **Worker Mode**: Keep application in memory between requests
- **Built-in HTTPS**: Automatic TLS certificate generation
- **Zero Configuration**: Works out-of-the-box with sane defaults

## Environments

### 1. Local Development (`docker-compose.yml`)
- **Purpose**: Local development with hot reloading
- **Features**: 
  - FrankenPHP with development mode
  - Vite HMR proxy integration
  - MailHog for email testing
  - Fresh database seeding
  - Xdebug support
- **URL**: http://localhost (HTTP) or https://localhost (HTTPS)
- **MailHog**: http://localhost:8025

### 2. Development (`docker-compose.dev.yml`)
- **Purpose**: CI/CD deployment target for development
- **Features**:
  - FrankenPHP production build
  - Traefik integration with SSL
  - Worker and scheduler processes
  - Resource monitoring
- **URL**: https://dev.yourdomain.com

### 3. Staging (`docker-compose.staging.yml`)
- **Purpose**: Production-like environment for testing
- **Features**:
  - FrankenPHP with security hardening
  - Resource limits and scaling
  - Backup capabilities
  - Enhanced monitoring
  - Multiple worker replicas
- **URL**: https://staging.yourdomain.com

### 4. Production (`docker-compose.prod.yml`)
- **Purpose**: Full production deployment
- **Features**:
  - FrankenPHP worker mode for maximum performance
  - Docker Swarm orchestration
  - High availability setup
  - Advanced monitoring and logging
  - Automated backups
  - Multi-replica scaling
- **URL**: https://yourdomain.com

### 5. Testing (`docker-compose.test.yml`)
- **Purpose**: Automated testing and CI/CD
- **Features**:
  - FrankenPHP test configuration
  - Parallel test execution
  - Multiple test profiles
  - Code quality checks
  - Browser testing support
  - In-memory databases

## Usage

### Make Commands
```bash
make help              # Show available commands
make local             # Start local development
make local-fresh       # Start with fresh database
make dev               # Deploy to development
make staging           # Deploy to staging  
make prod              # Deploy to production
make prod-build        # Build and deploy to production
make test              # Run complete test suite
make test-unit         # Run unit tests only
make test-feature      # Run feature tests only
make lint              # Run code quality checks
make logs service=app  # Show specific service logs
make shell             # Open app container shell
make migrate           # Run database migrations
```

### Script Commands
```bash
./scripts/docker-setup.sh local    # Local development
./scripts/docker-setup.sh dev      # Development deployment
./scripts/docker-setup.sh staging  # Staging deployment
./scripts/docker-setup.sh prod     # Production deployment
./scripts/docker-setup.sh test     # Run tests
./scripts/docker-setup.sh build    # Build images
./scripts/docker-setup.sh stop     # Stop all services
./scripts/docker-setup.sh clean    # Clean up resources
./scripts/docker-setup.sh logs     # Show logs
```

### Direct Docker Compose
```bash
# Local development
docker-compose up -d

# Development environment
docker-compose -f docker-compose.dev.yml up -d

# Staging environment
docker-compose -f docker-compose.staging.yml up -d

# Production environment (Docker Swarm)
docker stack deploy -c docker-compose.prod.yml laravel-app-prod

# Run tests
docker-compose -f docker-compose.test.yml up --exit-code-from app-test
```

## Test Profiles

### Unit Tests
```bash
make test-unit
# or
docker-compose -f docker-compose.test.yml --profile unit-tests up
```

### Feature Tests
```bash
make test-feature
# or
docker-compose -f docker-compose.test.yml --profile feature-tests up
```

### Browser Tests (Dusk)
```bash
make test-browser
# or
docker-compose -f docker-compose.test.yml --profile browser-tests up
```

### Code Quality
```bash
make lint
# or
docker-compose -f docker-compose.test.yml --profile code-quality up
```

## Environment Files

Each environment requires its own `.env` file:

- `.env.local` - Local development
- `.env.dev` - Development environment
- `.env.staging` - Staging environment
- `.env.testing` - Testing (already included)

Use the provided `.env.example.*` files as templates.

## Configuration Files

### FrankenPHP Configurations
- `frankenphp/Caddyfile` - Production configuration with security headers, caching, rate limiting
- `frankenphp/Caddyfile.local` - Development configuration with Vite proxy and debugging
- `frankenphp-worker.php` - Worker mode script for production performance

### Legacy Nginx (Removed - replaced by FrankenPHP)
FrankenPHP replaces Nginx as it includes a high-performance web server built-in.

### Supervisor
- `supervisor/supervisord.conf` - Process management for background workers

### PostgreSQL
- `postgres/init/01-create-databases.sql` - Database initialization
- `postgres/postgresql.conf` - Production PostgreSQL configuration
- `redis/redis.conf` - Production Redis configuration

## Services

### Core Services
- **app** - Laravel application with FrankenPHP server
- **postgres** - PostgreSQL database with production tuning
- **redis** - Redis for caching/sessions/queues

### Development Services
- **ssr** - Server-side rendering (Node.js)
- **worker** - Queue worker processes
- **scheduler** - Laravel task scheduler
- **mailhog** - Email testing (local only)

### Testing Services
- **app-test** - Test runner
- **app-browser-test** - Browser test runner
- **pest-unit** - Unit test runner
- **pest-feature** - Feature test runner

## Volumes

### Persistent Data
- **postgres_data** - Database storage
- **redis_data** - Redis persistence

### Development
- Code is mounted as volumes for live reloading
- Node modules and vendor directories are managed as named volumes

## Networks

- **laravel** - Internal application network
- **traefik** - External load balancer network (dev/staging)
- **laravel-test** - Isolated testing network

## Health Checks

All services include health checks:
- PostgreSQL: `pg_isready`
- Redis: `redis-cli ping`
- Services wait for dependencies to be healthy

## Resource Limits (Staging)

- **app**: 1GB limit, 512MB reserved
- **postgres**: 512MB limit, 256MB reserved
- **redis**: 256MB limit, 128MB reserved
- **nginx**: 128MB limit, 64MB reserved
- **worker**: 2 replicas, 512MB each

## Security Features

### Staging Environment
- Security headers (CSP, HSTS, etc.)
- Rate limiting for auth/API endpoints
- Access restrictions for sensitive directories
- Secure cookie settings
- HTTPS enforcement

## Backup

Staging environment includes backup service:
```bash
# Run database backup
docker-compose -f docker-compose.staging.yml --profile backup up backup
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R $USER:$USER .
   ```

2. **Port Conflicts**
   - Check if ports 80, 5432, 6379 are available
   - Modify port mappings in compose files if needed

3. **Memory Issues**
   - Increase Docker memory allocation
   - Reduce worker replicas in staging

4. **Database Connection**
   ```bash
   # Check database logs
   make logs service=postgres
   
   # Connect to database
   make shell-db
   ```

5. **Asset Issues**
   ```bash
   # Rebuild with fresh assets
   make build-local
   ```

### Useful Commands

```bash
# View all containers
docker ps -a

# View Docker resources
docker system df

# Clean up unused resources
docker system prune -a

# View container logs
docker logs <container_name>

# Execute command in container
docker exec -it <container_name> sh
```

## FrankenPHP Features

### Worker Mode (Production)
- Keeps Laravel application in memory between requests
- Dramatically improves performance (5-10x faster than traditional PHP-FPM)
- Enabled automatically in production environment

### HTTP/2 & HTTP/3 Support
- Native support for modern protocols
- Server Push capabilities
- Improved multiplexing and performance

### Built-in HTTPS
- Automatic TLS certificate generation in production
- HTTP/2 requires HTTPS for best performance
- Zero-configuration SSL

### Early Hints (103)
- Preload critical resources before full response
- Significantly improves perceived performance
- Configured automatically for CSS/JS assets

## Production Deployment

### Docker Swarm Setup
Production uses Docker Swarm for orchestration:

```bash
# Initialize swarm (if not already done)
docker swarm init

# Deploy to production
make prod

# Scale services
docker service scale laravel-app-prod_app=5
docker service scale laravel-app-prod_worker=10
```

### Key Production Features
1. **FrankenPHP Worker Mode** - Maximum performance
2. **Multi-replica scaling** - High availability  
3. **Resource limits** - Prevent resource exhaustion
4. **Health checks** - Automatic restart on failure
5. **Logging & Monitoring** - Comprehensive observability
6. **Automated backups** - Data protection
7. **Security hardening** - Production-ready security

### External Services
Consider managed services for production:
- **Database**: AWS RDS, Google Cloud SQL
- **Redis**: AWS ElastiCache, Redis Cloud
- **Storage**: AWS S3, Google Cloud Storage
- **CDN**: CloudFlare, AWS CloudFront
- **Monitoring**: DataDog, New Relic, Sentry

## Development Workflow

1. **Setup**: `make install`
2. **Development**: `make local`
3. **Testing**: `make test` before commits
4. **Code Quality**: `make lint` before commits
5. **Deployment**: `make dev` for development environment
6. **Production**: `make prod` (requires confirmation)