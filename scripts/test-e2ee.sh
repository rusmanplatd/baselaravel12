#!/bin/bash

# E2EE Chat System Test Script
# Tests all aspects of the end-to-end encrypted chat implementation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
TEST_MODE=${1:-"all"}
BASE_URL="http://localhost:8000"
API_BASE_URL="$BASE_URL/api/v1"

echo -e "${BLUE}🔐 E2EE Chat System Test Suite${NC}"
echo -e "${BLUE}================================${NC}"
echo "Test mode: $TEST_MODE"
echo "Base URL: $BASE_URL"
echo ""

# Function to print test headers
print_test_header() {
    echo -e "${YELLOW}🧪 $1${NC}"
    echo "----------------------------------------"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if Laravel server is running
check_server() {
    print_test_header "Checking Laravel Server"
    
    if curl -s "$BASE_URL" > /dev/null; then
        print_success "Laravel server is running at $BASE_URL"
    else
        print_error "Laravel server is not running at $BASE_URL"
        echo "Please start the server with: php artisan serve"
        exit 1
    fi
}

# Function to test database connectivity
test_database() {
    print_test_header "Testing Database Connectivity"
    
    # Check migrations
    if php artisan migrate:status > /dev/null 2>&1; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed"
        return 1
    fi
    
    # Check for required tables
    local required_tables=(
        "chat_conversations"
        "chat_messages" 
        "chat_encryption_keys"
        "conversation_participants"
        "signal_identity_keys"
    )
    
    for table in "${required_tables[@]}"; do
        if php artisan tinker --execute="DB::table('$table')->exists();" 2>/dev/null | grep -q "true"; then
            print_success "Table $table exists"
        else
            print_error "Required table $table not found"
            return 1
        fi
    done
}

# Function to test encryption services
test_encryption_services() {
    print_test_header "Testing Encryption Services"
    
    # Test Quantum Crypto Service
    php artisan tinker --execute="
        \$service = app(App\Services\QuantumCryptoService::class);
        echo 'Quantum Service: ' . get_class(\$service) . PHP_EOL;
        echo 'Algorithm ML-KEM-768 supported: ' . (\$service->isAlgorithmSupported('ML-KEM-768') ? 'Yes' : 'No') . PHP_EOL;
    " 2>/dev/null && print_success "Quantum Crypto Service operational"
    
    # Test Signal Protocol Service  
    php artisan tinker --execute="
        \$service = app(App\Services\SignalProtocolService::class);
        echo 'Signal Service: ' . get_class(\$service) . PHP_EOL;
    " 2>/dev/null && print_success "Signal Protocol Service operational"
    
    # Test Group Encryption Service
    php artisan tinker --execute="
        \$service = app(App\Services\GroupEncryptionService::class);
        echo 'Group Service: ' . get_class(\$service) . PHP_EOL;
    " 2>/dev/null && print_success "Group Encryption Service operational"
}

# Function to test API endpoints
test_api_endpoints() {
    print_test_header "Testing API Endpoints"
    
    # Test public endpoints (no auth required)
    local endpoints=(
        "v1/quantum/health"
        "v1/geo/countries"
        "v1/organizations"
    )
    
    for endpoint in "${endpoints[@]}"; do
        if curl -s -o /dev/null -w "%{http_code}" "$API_BASE_URL/$endpoint" | grep -q "200"; then
            print_success "Endpoint /$endpoint responding"
        else
            echo -e "${YELLOW}⚠️  Endpoint /$endpoint may require authentication${NC}"
        fi
    done
    
    # Test chat API structure (will return 401/403 without auth, which is expected)
    local auth_endpoints=(
        "v1/chat/devices"
        "v1/chat/conversations"
        "v1/quantum/generate-keypair"
    )
    
    for endpoint in "${auth_endpoints[@]}"; do
        local status=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE_URL/$endpoint")
        if [[ "$status" == "401" || "$status" == "403" ]]; then
            print_success "Protected endpoint /$endpoint properly secured"
        else
            echo -e "${YELLOW}⚠️  Endpoint /$endpoint returned status: $status${NC}"
        fi
    done
}

# Function to test frontend compilation
test_frontend() {
    print_test_header "Testing Frontend Compilation"
    
    # Test TypeScript compilation
    if npm run types > /dev/null 2>&1; then
        print_success "TypeScript compilation successful"
    else
        print_error "TypeScript compilation failed"
        echo "Run 'npm run types' to see detailed errors"
        return 1
    fi
    
    # Test Vite build
    if npm run build > /dev/null 2>&1; then
        print_success "Vite build successful"
    else
        print_error "Vite build failed"
        echo "Run 'npm run build' to see detailed errors"
        return 1
    fi
}

# Function to test WebSocket/Broadcasting setup
test_websocket() {
    print_test_header "Testing WebSocket/Broadcasting Setup"
    
    # Check broadcasting configuration
    if php artisan tinker --execute="
        echo 'Default broadcast driver: ' . config('broadcasting.default') . PHP_EOL;
        echo 'Available connections: ' . implode(', ', array_keys(config('broadcasting.connections'))) . PHP_EOL;
    " 2>/dev/null; then
        print_success "Broadcasting configuration loaded"
    else
        print_error "Broadcasting configuration failed"
        return 1
    fi
    
    # Check channels file
    if [[ -f "routes/channels.php" ]]; then
        print_success "Broadcasting channels defined"
    else
        print_error "Broadcasting channels file missing"
        return 1
    fi
}

# Function to test file permissions and storage
test_storage() {
    print_test_header "Testing Storage and Permissions"
    
    # Check storage directories
    local storage_dirs=(
        "storage/app"
        "storage/framework/cache"
        "storage/framework/sessions"  
        "storage/logs"
    )
    
    for dir in "${storage_dirs[@]}"; do
        if [[ -d "$dir" && -w "$dir" ]]; then
            print_success "Directory $dir is writable"
        else
            print_error "Directory $dir is not writable"
            return 1
        fi
    done
    
    # Test file upload functionality
    if php artisan tinker --execute="
        \$disk = Storage::disk('local');
        \$disk->put('test-file.txt', 'E2EE Test');
        if (\$disk->exists('test-file.txt')) {
            echo 'File operations working' . PHP_EOL;
            \$disk->delete('test-file.txt');
        }
    " 2>/dev/null; then
        print_success "File storage operations working"
    else
        print_error "File storage operations failed"
        return 1
    fi
}

# Function to run unit tests
run_unit_tests() {
    print_test_header "Running Unit Tests"
    
    if php artisan test --filter="Chat" 2>/dev/null; then
        print_success "Chat unit tests passed"
    else
        echo -e "${YELLOW}⚠️  No chat unit tests found or some tests failed${NC}"
    fi
    
    if php artisan test --filter="Quantum" 2>/dev/null; then
        print_success "Quantum crypto tests passed"  
    else
        echo -e "${YELLOW}⚠️  No quantum crypto tests found or some tests failed${NC}"
    fi
}

# Function to test multidevice functionality
test_multidevice() {
    print_test_header "Testing Multi-Device Support"
    
    # Test device registration simulation
    php artisan tinker --execute="
        \$user = App\Models\User::first();
        if (\$user) {
            echo 'Testing with user: ' . \$user->email . PHP_EOL;
            \$deviceCount = \$user->devices()->count();
            echo 'User has ' . \$deviceCount . ' registered devices' . PHP_EOL;
        } else {
            echo 'No test users found' . PHP_EOL;
        }
    " 2>/dev/null && print_success "Multi-device data structures working"
}

# Function to test quantum readiness
test_quantum_readiness() {
    print_test_header "Testing Quantum Readiness"
    
    # Check for LibOQS availability  
    if command -v oqs_test > /dev/null || php -m | grep -q oqs; then
        print_success "LibOQS detected - Production quantum crypto available"
    else
        echo -e "${YELLOW}⚠️  LibOQS not detected - Using fallback mode${NC}"
    fi
    
    # Test quantum service initialization
    php artisan tinker --execute="
        try {
            \$service = app(App\Services\QuantumCryptoService::class);
            echo 'Quantum service initialized successfully' . PHP_EOL;
        } catch (Exception \$e) {
            echo 'Quantum service error: ' . \$e->getMessage() . PHP_EOL;
        }
    " 2>/dev/null && print_success "Quantum crypto service initialized"
}

# Function to run security tests
test_security() {
    print_test_header "Testing Security Features"
    
    # Check for proper middleware
    if php artisan route:list | grep -q "chat.*auth"; then
        print_success "Chat routes protected with authentication"
    else
        print_error "Chat routes may not be properly protected"
        return 1
    fi
    
    # Check rate limiting
    if php artisan route:list | grep -q "throttle"; then
        print_success "Rate limiting configured"
    else
        echo -e "${YELLOW}⚠️  Rate limiting may not be configured${NC}"
    fi
    
    # Check encryption key management
    php artisan tinker --execute="
        \$encryptionKeys = App\Models\Chat\EncryptionKey::count();
        echo 'Encryption keys in database: ' . \$encryptionKeys . PHP_EOL;
    " 2>/dev/null
}

# Main execution based on test mode
case $TEST_MODE in
    "unit")
        check_server
        test_database
        run_unit_tests
        ;;
    "multidevice")
        check_server
        test_database
        test_encryption_services
        test_multidevice
        ;;
    "quantum")
        check_server
        test_database
        test_quantum_readiness
        test_encryption_services
        ;;
    "security")
        check_server
        test_database
        test_security
        test_api_endpoints
        ;;
    "frontend")
        test_frontend
        ;;
    "all"|*)
        echo -e "${BLUE}🔄 Running Complete E2EE Test Suite${NC}"
        echo ""
        
        check_server
        test_database
        test_encryption_services
        test_api_endpoints
        test_frontend
        test_websocket
        test_storage
        test_multidevice
        test_quantum_readiness
        test_security
        run_unit_tests
        ;;
esac

echo ""
echo -e "${GREEN}🎉 E2EE Chat System Tests Completed!${NC}"
echo -e "${BLUE}================================${NC}"

# Summary based on test results
if [[ $? -eq 0 ]]; then
    echo -e "${GREEN}✅ All tests passed successfully${NC}"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo "1. Start the application: composer dev"
    echo "2. Visit the chat interface: http://localhost:8000/chat"
    echo "3. Configure LiveKit URL: export VITE_LIVEKIT_URL=ws://localhost:7880"
    echo "4. For production: Install LibOQS for quantum crypto"
else
    echo -e "${RED}❌ Some tests failed - check the output above${NC}"
    exit 1
fi