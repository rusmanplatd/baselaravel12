#!/bin/bash

echo "🚀 Starting OAuth 2.0 / OpenID Connect Test Environment"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check if port is in use
check_port() {
    if lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${YELLOW}Port $1 is already in use${NC}"
        return 0
    else
        return 1
    fi
}

# Function to start server in background
start_server() {
    local name=$1
    local dir=$2
    local port=$3
    local cmd=$4
    
    echo -e "${BLUE}Starting $name on port $port...${NC}"
    cd "$dir"
    eval "$cmd" &
    local pid=$!
    echo $pid > "/tmp/${name}.pid"
    echo -e "${GREEN}$name started with PID $pid${NC}"
    sleep 2
}

# Check if we're in the right directory
if [[ ! -f "artisan" ]]; then
    echo -e "${RED}Error: This script should be run from the oauth-client directory${NC}"
    exit 1
fi

# Get the root project directory
ROOT_DIR="$(cd ../.. && pwd)"
OAUTH_CLIENT_DIR="$(pwd)"

echo "Root project: $ROOT_DIR"
echo "OAuth client: $OAUTH_CLIENT_DIR"

# Start the main Laravel server (port 8000)
if ! check_port 8000; then
    start_server "main-server" "$ROOT_DIR" 8000 "php artisan serve --host=0.0.0.0 --port=8000"
else
    echo -e "${YELLOW}Main server already running on port 8000${NC}"
fi

# Start the OAuth client server (port 8081)
if ! check_port 8081; then
    start_server "oauth-client" "$OAUTH_CLIENT_DIR" 8081 "php artisan serve --host=0.0.0.0 --port=8081"
else
    echo -e "${YELLOW}OAuth client already running on port 8081${NC}"
fi

echo ""
echo "🎉 OAuth Test Environment Ready!"
echo "=================================================="
echo -e "${GREEN}Main Server (Authorization Server):${NC} http://localhost:8000"
echo -e "${GREEN}OAuth Client (Test Client):${NC}        http://localhost:8081/oauth/"
echo ""
echo "📋 Available Endpoints:"
echo "  • OAuth Dashboard:      http://localhost:8081/oauth/"
echo "  • OAuth Discovery:      http://localhost:8000/.well-known/oauth-authorization-server"
echo "  • OIDC Discovery:       http://localhost:8000/.well-known/openid_configuration"
echo "  • Client Management:    http://localhost:8000/oauth/clients"
echo ""
echo "🔐 Test Client Configuration:"
echo "  • Client ID: a8704536-ee26-4675-b324-741444ffb54e"
echo "  • Client Name: Developer Tools"
echo "  • Redirect URI: http://localhost:8081/oauth/callback"
echo "  • Supported Scopes: openid, profile, email, organization.readonly"
echo ""
echo "🧪 Testing Steps:"
echo "  1. Open http://localhost:8081/oauth/ in your browser"
echo "  2. Select desired scopes"
echo "  3. Click 'Start OAuth Flow'"
echo "  4. Login on the authorization server"
echo "  5. Grant permissions to the test client"
echo "  6. View tokens and user information"
echo ""
echo "⚠️  Note: Make sure you have a user account on the main server"
echo "   You can register at: http://localhost:8000/register"
echo ""
echo "🛑 To stop servers:"
echo "   kill \$(cat /tmp/main-server.pid) && kill \$(cat /tmp/oauth-client.pid)"
echo "   rm /tmp/main-server.pid /tmp/oauth-client.pid"