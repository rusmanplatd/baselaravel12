#!/bin/bash

# E2EE Chat Testing Script
# This script runs comprehensive tests for end-to-end encryption functionality

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-5430}
DB_DATABASE=${DB_DATABASE:-baselaravel12react_test}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-Your_Strong_P455word}

# Test environment variables
export DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

# Functions
print_header() {
    echo -e "\n${BLUE}================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}================================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check dependencies
check_dependencies() {
    print_header "Checking Dependencies"
    
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed or not in PATH"
        exit 1
    fi
    
    php_version=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    print_info "PHP version: $php_version"
    
    if ! php artisan --version &> /dev/null; then
        print_error "Laravel Artisan not available"
        exit 1
    fi
    
    print_success "Dependencies check passed"
}

# Setup test environment
setup_test_environment() {
    print_header "Setting Up Test Environment"
    
    # Generate app key if not exists
    if ! grep -q "APP_KEY=" .env || [ -z "$(grep APP_KEY= .env | cut -d'=' -f2)" ]; then
        print_info "Generating application key..."
        php artisan key:generate --env=testing --force
    fi
    
    # Run migrations
    print_info "Running database migrations..."
    php artisan migrate:fresh --env=testing --force
    
    # Run seeders if they exist
    if [ -d "database/seeders" ] && [ "$(ls -A database/seeders)" ]; then
        print_info "Running database seeders..."
        php artisan db:seed --env=testing --force 2>/dev/null || print_warning "No seeders to run"
    fi
    
    print_success "Test environment setup completed"
}

# Run encryption service unit tests
run_unit_tests() {
    print_header "Running Encryption Service Unit Tests"
    
    print_info "Testing basic encryption service functionality..."
    php artisan test tests/Unit/Services/ChatEncryptionServiceTest.php --stop-on-failure
    
    print_info "Testing enhanced encryption features..."
    php artisan test tests/Unit/Services/ChatEncryptionServiceEnhancedTest.php --stop-on-failure
    
    print_success "Unit tests completed successfully"
}

# Run integration tests
run_integration_tests() {
    print_header "Running E2EE Integration Tests"
    
    print_info "Testing model integration and full E2EE flow..."
    php artisan test tests/Feature/Chat/EncryptionIntegrationTest.php --stop-on-failure
    
    print_success "Integration tests completed successfully"
}

# Run multi-device E2EE tests
run_multidevice_e2ee_tests() {
    print_header "Running Multi-Device E2EE Tests"
    
    print_info "Testing multi-device registration and key sharing..."
    php artisan test tests/Feature/Chat/MultiDeviceE2EETest.php --stop-on-failure
    
    print_info "Testing multi-device API endpoints..."
    php artisan test tests/Feature/Api/MultiDeviceApiTest.php --stop-on-failure
    
    print_success "Multi-device E2EE tests completed successfully"
}

# Run existing chat tests
run_existing_chat_tests() {
    print_header "Running Existing Chat Tests"
    
    if [ -f "tests/Feature/Chat/ConversationTest.php" ]; then
        print_info "Testing conversation functionality..."
        php artisan test tests/Feature/Chat/ConversationTest.php --stop-on-failure
    fi
    
    if [ -f "tests/Feature/Chat/MessageTest.php" ]; then
        print_info "Testing message functionality..."
        php artisan test tests/Feature/Chat/MessageTest.php --stop-on-failure
    fi
    
    if [ -f "tests/Feature/Chat/ChatApiTest.php" ]; then
        print_info "Testing chat API endpoints..."
        php artisan test tests/Feature/Chat/ChatApiTest.php --stop-on-failure
    fi
    
    print_success "Existing chat tests completed successfully"
}

# Run file service tests
run_file_service_tests() {
    print_header "Running Chat File Service Tests"
    
    if [ -f "tests/Unit/Services/ChatFileServiceTest.php" ]; then
        print_info "Testing file encryption/decryption..."
        php artisan test tests/Unit/Services/ChatFileServiceTest.php --stop-on-failure
    else
        print_warning "Chat file service tests not found, skipping..."
    fi
}

# Performance benchmarks
run_performance_benchmarks() {
    print_header "Running Performance Benchmarks"
    
    print_info "Running encryption performance tests..."
    php artisan test tests/Unit/Services/ChatEncryptionServiceEnhancedTest.php --filter="Performance" --stop-on-failure
    
    print_info "Running integration performance tests..."
    php artisan test tests/Feature/Chat/EncryptionIntegrationTest.php --filter="Performance" --stop-on-failure
    
    print_success "Performance benchmarks completed"
}

# Security validation tests
run_security_tests() {
    print_header "Running Security Validation Tests"
    
    print_info "Running security hardening tests..."
    php artisan test tests/Unit/Services/ChatEncryptionServiceEnhancedTest.php --filter="Security" --stop-on-failure
    
    print_info "Testing error handling and edge cases..."
    php artisan test tests/Unit/Services/ChatEncryptionServiceEnhancedTest.php --filter="Error" --stop-on-failure
    
    print_success "Security tests completed successfully"
}

# Cleanup
cleanup() {
    print_header "Cleaning Up"
    
    # Clear test cache
    php artisan cache:clear --env=testing 2>/dev/null || true
    php artisan config:clear --env=testing 2>/dev/null || true
    php artisan route:clear --env=testing 2>/dev/null || true
    php artisan view:clear --env=testing 2>/dev/null || true
    
    print_success "Cleanup completed"
}

# Generate test report
generate_report() {
    print_header "Generating Test Report"
    
    local timestamp=$(date '+%Y-%m-%d_%H-%M-%S')
    local report_file="storage/logs/e2ee_test_report_${timestamp}.log"
    
    {
        echo "E2EE Chat Test Report"
        echo "Generated: $(date)"
        echo "Environment: Testing"
        echo "Database: $DB_CONNECTION"
        echo ""
        echo "Test Categories:"
        echo "- Basic Encryption Service: ✓"
        echo "- Enhanced Encryption Features: ✓"
        echo "- Model Integration: ✓"
        echo "- Full E2EE Flow: ✓"
        echo "- Multi-Device E2EE: ✓"
        echo "- Performance Benchmarks: ✓"
        echo "- Security Validation: ✓"
        echo ""
        echo "All tests passed successfully!"
    } > "$report_file"
    
    print_success "Test report generated: $report_file"
}

# Main execution
main() {
    print_header "E2EE Chat Testing Suite"
    print_info "Starting comprehensive end-to-end encryption tests..."
    
    local start_time=$(date +%s)
    
    # Run all test phases
    check_dependencies
    setup_test_environment
    run_unit_tests
    run_integration_tests
    run_multidevice_e2ee_tests
    run_existing_chat_tests
    run_file_service_tests
    run_performance_benchmarks
    run_security_tests
    cleanup
    generate_report
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    print_header "Test Suite Completed Successfully"
    print_success "Total execution time: ${duration} seconds"
    print_success "All E2EE functionality is working correctly!"
    
    echo ""
    print_info "Next steps:"
    echo "1. Review the test report in storage/logs/"
    echo "2. Run 'php artisan migrate' to apply new encryption fields to your database"
    echo "3. Update your frontend to use the enhanced encryption features"
    echo "4. Monitor encryption performance in production"
    echo ""
}

# Handle script arguments
case "${1:-all}" in
    "unit")
        check_dependencies
        setup_test_environment
        run_unit_tests
        ;;
    "integration")
        check_dependencies
        setup_test_environment
        run_integration_tests
        ;;
    "multidevice")
        check_dependencies
        setup_test_environment
        run_multidevice_e2ee_tests
        ;;
    "performance")
        check_dependencies
        setup_test_environment
        run_performance_benchmarks
        ;;
    "security")
        check_dependencies
        setup_test_environment
        run_security_tests
        ;;
    "setup")
        check_dependencies
        setup_test_environment
        ;;
    "all"|*)
        main
        ;;
esac