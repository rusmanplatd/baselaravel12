#!/bin/bash

echo "🧪 OAuth 2.0 / OpenID Connect Test Runner"
echo "========================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check if port is in use
check_port() {
    if lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null ; then
        return 0
    else
        return 1
    fi
}

echo -e "${BLUE}Checking prerequisites...${NC}"

# Check if we have Node.js and npm
if ! command_exists node; then
    echo -e "${RED}❌ Node.js is not installed${NC}"
    exit 1
fi

if ! command_exists npm; then
    echo -e "${RED}❌ npm is not installed${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Node.js and npm are available${NC}"

# Check if dependencies are installed
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}📦 Installing npm dependencies...${NC}"
    npm install
fi

# Check if Playwright is installed
if [ ! -d "node_modules/@playwright" ]; then
    echo -e "${YELLOW}🎭 Installing Playwright...${NC}"
    npm install @playwright/test
fi

# Try to install browsers (may fail in some environments)
echo -e "${YELLOW}🌐 Installing Playwright browsers...${NC}"
npx playwright install chromium 2>/dev/null || echo -e "${YELLOW}⚠️  Browser installation may have failed (this is OK for some environments)${NC}"

echo -e "${BLUE}Checking servers...${NC}"

# Check if main server is running
if ! check_port 8000; then
    echo -e "${YELLOW}⚠️  Main server (port 8000) is not running${NC}"
    echo -e "${BLUE}Starting main server...${NC}"
    
    ROOT_DIR="$(cd ../.. && pwd)"
    cd "$ROOT_DIR"
    
    # Start main server in background
    DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5430 DB_DATABASE=baselaravel12react DB_USERNAME=postgres DB_PASSWORD=Your_Strong_P455word php artisan serve --host=0.0.0.0 --port=8000 &
    MAIN_SERVER_PID=$!
    echo $MAIN_SERVER_PID > /tmp/oauth-test-main-server.pid
    
    # Wait for server to start
    sleep 3
    
    cd - > /dev/null
else
    echo -e "${GREEN}✅ Main server is running on port 8000${NC}"
fi

# Check if OAuth client is running
if ! check_port 8081; then
    echo -e "${YELLOW}⚠️  OAuth client (port 8081) is not running${NC}"
    echo -e "${BLUE}Starting OAuth client...${NC}"
    
    # Start OAuth client in background
    php artisan serve --host=0.0.0.0 --port=8081 &
    CLIENT_SERVER_PID=$!
    echo $CLIENT_SERVER_PID > /tmp/oauth-test-client-server.pid
    
    # Wait for server to start
    sleep 2
else
    echo -e "${GREEN}✅ OAuth client is running on port 8081${NC}"
fi

# Wait a bit more to ensure servers are fully ready
echo -e "${BLUE}Waiting for servers to be ready...${NC}"
sleep 3

echo -e "${GREEN}🚀 Running OAuth tests...${NC}"
echo "========================================"

# Parse command line arguments
TEST_TYPE="$1"
HEADED=""
DEBUG=""
UI=""

case "$TEST_TYPE" in
    "headed")
        HEADED="--headed"
        echo -e "${BLUE}Running tests in headed mode (you'll see browser windows)${NC}"
        ;;
    "debug")
        DEBUG="--debug"
        echo -e "${BLUE}Running tests in debug mode${NC}"
        ;;
    "ui")
        UI="--ui"
        echo -e "${BLUE}Opening Playwright UI${NC}"
        ;;
    "basic")
        echo -e "${BLUE}Running basic OAuth dashboard tests only${NC}"
        npx playwright test oauth-flow.spec.js --grep "should display OAuth dashboard correctly" $HEADED $DEBUG $UI
        exit $?
        ;;
    *)
        echo -e "${BLUE}Running all tests (headless mode)${NC}"
        ;;
esac

# Run the tests
if [ -n "$UI" ]; then
    npx playwright test $UI
elif [ -n "$DEBUG" ]; then
    npx playwright test $DEBUG
else
    npx playwright test $HEADED --reporter=list
fi

TEST_EXIT_CODE=$?

echo ""
echo "========================================"
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed (exit code: $TEST_EXIT_CODE)${NC}"
fi

# Cleanup function
cleanup() {
    echo -e "${YELLOW}🧹 Cleaning up test servers...${NC}"
    
    # Kill servers if we started them
    if [ -f /tmp/oauth-test-main-server.pid ]; then
        kill $(cat /tmp/oauth-test-main-server.pid) 2>/dev/null || true
        rm /tmp/oauth-test-main-server.pid
        echo -e "${GREEN}✅ Main server stopped${NC}"
    fi
    
    if [ -f /tmp/oauth-test-client-server.pid ]; then
        kill $(cat /tmp/oauth-test-client-server.pid) 2>/dev/null || true
        rm /tmp/oauth-test-client-server.pid
        echo -e "${GREEN}✅ OAuth client stopped${NC}"
    fi
}

# Set up trap to cleanup on exit
trap cleanup EXIT

echo ""
echo "📊 Test Results:"
echo "  • Check test-results/ directory for detailed reports"
echo "  • HTML report: test-results/report/index.html"
echo "  • Screenshots: test-results/ (for failed tests)"
echo ""
echo "📚 Usage examples:"
echo "  ./run-tests.sh           # Run all tests (headless)"
echo "  ./run-tests.sh headed    # Run with browser visible"
echo "  ./run-tests.sh debug     # Run in debug mode"
echo "  ./run-tests.sh ui        # Open Playwright UI"
echo "  ./run-tests.sh basic     # Run basic dashboard test only"

exit $TEST_EXIT_CODE