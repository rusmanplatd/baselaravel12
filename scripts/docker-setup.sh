#!/bin/bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

show_usage() {
    echo "Docker Environment Setup Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  local     - Start local development environment"
    echo "  dev       - Deploy to development environment"
    echo "  staging   - Deploy to staging environment"
    echo "  prod      - Deploy to production environment"
    echo "  test      - Run test suite"
    echo "  build     - Build Docker images"
    echo "  stop      - Stop all services"
    echo "  clean     - Clean up containers, volumes, and images"
    echo "  logs      - Show logs for services"
    echo ""
    echo "Options:"
    echo "  --build   - Force rebuild images"
    echo "  --fresh   - Start with fresh database"
    echo "  --help    - Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 local --fresh"
    echo "  $0 test --build"
    echo "  $0 staging"
    echo "  $0 prod"
    echo "  $0 logs app"
}

check_requirements() {
    log_info "Checking requirements..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed"
        exit 1
    fi
    
    log_success "All requirements met"
}

setup_env_file() {
    local env_file="$1"
    
    if [[ ! -f "$env_file" ]]; then
        log_info "Creating $env_file from .env.example"
        cp .env.example "$env_file"
        
        # Generate app key if not exists
        if ! grep -q "APP_KEY=base64:" "$env_file"; then
            log_info "Generating application key..."
            docker run --rm -v "$(pwd)":/app -w /app php:8.3-cli php artisan key:generate --no-interaction
        fi
    fi
}

start_local() {
    log_info "Starting local development environment..."
    
    setup_env_file ".env.local"
    
    local compose_args="docker-compose"
    
    if [[ "$BUILD" == "true" ]]; then
        compose_args="$compose_args --build"
    fi
    
    if [[ "$FRESH" == "true" ]]; then
        log_warning "Starting with fresh database..."
        docker-compose down -v
    fi
    
    $compose_args up -d
    
    log_success "Local environment started"
    log_info "Application: http://localhost"
    log_info "MailHog: http://localhost:8025"
}

deploy_dev() {
    log_info "Deploying to development environment..."
    
    setup_env_file ".env.dev"
    
    local compose_args="docker-compose -f docker-compose.dev.yml"
    
    if [[ "$BUILD" == "true" ]]; then
        compose_args="$compose_args --build"
    fi
    
    $compose_args up -d
    
    log_success "Development environment deployed"
}

deploy_staging() {
    log_info "Deploying to staging environment..."
    
    setup_env_file ".env.staging"
    
    local compose_args="docker-compose -f docker-compose.staging.yml"
    
    if [[ "$BUILD" == "true" ]]; then
        compose_args="$compose_args --build"
    fi
    
    $compose_args up -d
    
    log_success "Staging environment deployed"
}

deploy_prod() {
    log_info "Deploying to production environment..."
    
    log_warning "This will deploy to PRODUCTION. Are you sure? (y/N)"
    read -r response
    
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        log_info "Production deployment cancelled"
        return 0
    fi
    
    setup_env_file ".env.prod"
    
    local compose_args="docker-compose -f docker-compose.prod.yml"
    
    if [[ "$BUILD" == "true" ]]; then
        compose_args="$compose_args --build"
    fi
    
    # Use deploy mode for production (Docker Swarm)
    log_info "Deploying to Docker Swarm..."
    docker stack deploy -c docker-compose.prod.yml laravel-app-prod
    
    log_success "Production environment deployed"
}

run_tests() {
    log_info "Running test suite..."
    
    local compose_args="docker-compose -f docker-compose.test.yml"
    
    if [[ "$BUILD" == "true" ]]; then
        compose_args="$compose_args --build"
    fi
    
    # Run different test profiles
    log_info "Running unit tests..."
    $compose_args --profile unit-tests up --exit-code-from pest-unit
    
    log_info "Running feature tests..."
    $compose_args --profile feature-tests up --exit-code-from pest-feature
    
    log_info "Running code quality checks..."
    $compose_args --profile code-quality up --exit-code-from pint
    $compose_args --profile code-quality up --exit-code-from eslint
    $compose_args --profile code-quality up --exit-code-from typescript
    
    log_success "All tests passed"
}

build_images() {
    log_info "Building Docker images..."
    
    docker-compose build
    docker-compose -f docker-compose.dev.yml build
    docker-compose -f docker-compose.staging.yml build
    docker-compose -f docker-compose.prod.yml build
    docker-compose -f docker-compose.test.yml build
    
    log_success "Images built successfully"
}

stop_services() {
    log_info "Stopping all services..."
    
    docker-compose down
    docker-compose -f docker-compose.dev.yml down
    docker-compose -f docker-compose.staging.yml down
    docker-compose -f docker-compose.test.yml down
    
    # Stop production stack if running
    if docker stack ls | grep -q laravel-app-prod; then
        log_info "Stopping production stack..."
        docker stack rm laravel-app-prod
    fi
    
    log_success "All services stopped"
}

clean_up() {
    log_warning "This will remove all containers, volumes, and images. Are you sure? (y/N)"
    read -r response
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        log_info "Cleaning up..."
        
        # Stop all services
        stop_services
        
        # Remove volumes
        docker volume prune -f
        
        # Remove images
        docker image prune -a -f
        
        # Remove networks
        docker network prune -f
        
        log_success "Cleanup completed"
    else
        log_info "Cleanup cancelled"
    fi
}

show_logs() {
    local service="$1"
    
    if [[ -z "$service" ]]; then
        docker-compose logs -f
    else
        docker-compose logs -f "$service"
    fi
}

# Parse command line arguments
COMMAND=""
BUILD="false"
FRESH="false"

while [[ $# -gt 0 ]]; do
    case $1 in
        local|dev|staging|prod|test|build|stop|clean|logs)
            COMMAND="$1"
            shift
            ;;
        --build)
            BUILD="true"
            shift
            ;;
        --fresh)
            FRESH="true"
            shift
            ;;
        --help)
            show_usage
            exit 0
            ;;
        *)
            if [[ "$COMMAND" == "logs" ]] && [[ -z "$2" ]]; then
                SERVICE="$1"
            fi
            shift
            ;;
    esac
done

# Check if command is provided
if [[ -z "$COMMAND" ]]; then
    log_error "No command provided"
    show_usage
    exit 1
fi

# Check requirements
check_requirements

# Execute command
case $COMMAND in
    local)
        start_local
        ;;
    dev)
        deploy_dev
        ;;
    staging)
        deploy_staging
        ;;
    prod)
        deploy_prod
        ;;
    test)
        run_tests
        ;;
    build)
        build_images
        ;;
    stop)
        stop_services
        ;;
    clean)
        clean_up
        ;;
    logs)
        show_logs "$SERVICE"
        ;;
    *)
        log_error "Unknown command: $COMMAND"
        show_usage
        exit 1
        ;;
esac