# OAuth 2.0 / OpenID Connect Test Client

A comprehensive Laravel application for testing OAuth 2.0 and OpenID Connect flows with automated Playwright testing. This client implements the complete authorization code flow with PKCE, token refresh, token revocation, and user info retrieval.

## 🚀 Features

### 🔐 Complete OAuth 2.0 Implementation
- **Authorization Code Flow**: Full implementation with state parameter for CSRF protection
- **Token Exchange**: Secure exchange of authorization code for access tokens
- **Token Refresh**: Automatic and manual token refresh functionality  
- **Token Revocation**: Ability to revoke access and refresh tokens
- **Scope Selection**: Interactive scope selection with descriptions
- **Error Handling**: Comprehensive error handling for all OAuth scenarios

### 🎫 OpenID Connect Support
- **ID Token Processing**: Decoding and display of ID token claims
- **UserInfo Endpoint**: Retrieval of user information using access tokens
- **Discovery Support**: Integration with OIDC discovery endpoints
- **Multiple Grant Types**: Support for authorization_code and refresh_token grants

### 🎭 Automated Testing with Playwright
- **End-to-End Tests**: Complete OAuth flow testing including user registration
- **Error Scenario Testing**: Comprehensive error handling verification
- **Cross-Browser Testing**: Support for Chromium, Firefox, and WebKit
- **Performance Testing**: Load time and concurrent request testing
- **Mobile Testing**: Responsive design verification
- **Accessibility Testing**: Basic accessibility compliance checks

### 🎨 Modern User Interface
- **Responsive Design**: Works on desktop and mobile devices
- **Interactive Testing**: Real-time token operations (refresh, revoke)
- **Token Management**: Copy-to-clipboard functionality for tokens
- **Detailed Results**: Comprehensive display of tokens, claims, and user data
- **Error Visualization**: Clear error reporting with contextual information

## 📋 Quick Start

### Option 1: Automated Setup (Recommended)
```bash
cd examples/oauth-client
./start-oauth-test.sh
```

This will start both servers and provide testing instructions.

### Option 2: Manual Setup
```bash
# Start the main authorization server
cd ../..
php artisan serve --port=8000

# Start the OAuth client (in new terminal)
cd examples/oauth-client
php artisan serve --port=8081
```

### Option 3: Run with Tests
```bash
cd examples/oauth-client
./run-tests.sh
```

## 🧪 Testing with Playwright

### Running Tests

```bash
# Install dependencies and run all tests
./run-tests.sh

# Run with browser windows visible
./run-tests.sh headed

# Run in debug mode (step through tests)
./run-tests.sh debug

# Open Playwright UI for interactive testing
./run-tests.sh ui

# Run basic dashboard tests only
./run-tests.sh basic
```

### Manual Test Commands

```bash
# Install Playwright
npm install
npx playwright install

# Run specific test suites
npm run test                    # All tests
npm run test:headed            # With browser UI
npm run test:debug             # Debug mode
npm run test:ui                # Interactive UI

# Run specific test files
npx playwright test oauth-flow.spec.js
npx playwright test oauth-integration.spec.js
```

### Test Categories

1. **OAuth Flow Tests** (`oauth-flow.spec.js`)
   - Dashboard functionality
   - Scope selection
   - Authorization flow initiation
   - Error handling
   - Token operations

2. **Integration Tests** (`oauth-integration.spec.js`)
   - Complete OAuth flow with user registration
   - Token refresh and revocation
   - Cross-browser compatibility
   - Performance testing
   - Error scenarios

## ⚙️ Configuration

### Client Credentials
Pre-configured for the "Developer Tools" OAuth client:

- **Client ID**: `a8704536-ee26-4675-b324-741444ffb54e`
- **Client Secret**: Set in `.env` as `OAUTH_CLIENT_SECRET`
- **Redirect URI**: `http://localhost:8081/oauth/callback`

### Environment Variables
```env
APP_URL=http://localhost:8081
OAUTH_CLIENT_SECRET=U2IvscRm4w9GrYfQd8hjbyvjYMtLsSDBcfQyeOwu
```

### Supported Scopes
- `openid` - OpenID Connect identity
- `profile` - Basic profile information  
- `email` - Email address
- `https://api.yourcompany.com/auth/organization.readonly` - Organization data

## 🎯 Usage Guide

### 1. Access the OAuth Dashboard
Navigate to: `http://localhost:8081/oauth/`

### 2. Test OAuth Flow
1. Select desired scopes
2. Click "Start OAuth Flow"
3. Login/register on the authorization server
4. Grant permissions
5. View tokens and user information

### 3. Test Token Operations
- **Refresh Token**: Test automatic token renewal
- **Revoke Token**: Test token cleanup
- **User Info**: Verify UserInfo endpoint access

## 🏗️ Architecture

### Controller Structure
```
app/Http/Controllers/OAuthClientController.php
├── index()                # OAuth dashboard
├── startAuthorization()   # Begin OAuth flow
├── callback()             # Handle OAuth callback
├── refresh()              # Token refresh API
├── revoke()               # Token revocation API
└── discovery()            # Server discovery API
```

### View Templates
```
resources/views/
├── oauth-dashboard.blade.php  # Main testing interface
├── oauth-result.blade.php     # Results display
└── welcome.blade.php         # Default page
```

### Test Structure
```
tests/e2e/
├── oauth-flow.spec.js         # Basic OAuth flow tests
├── oauth-integration.spec.js  # End-to-end integration tests
├── global-setup.js           # Test environment setup
└── global-teardown.js        # Test cleanup
```

## 🔍 API Endpoints

### Client Routes
- `GET /oauth/` - OAuth dashboard
- `POST /oauth/authorize` - Start authorization
- `GET /oauth/callback` - OAuth callback
- `POST /oauth/refresh` - Refresh tokens
- `POST /oauth/revoke` - Revoke tokens
- `GET /oauth/discovery` - Server discovery

### Testing Endpoints
The Playwright tests interact with these endpoints to verify functionality.

## 🛡️ Security Features

### CSRF Protection
- State parameter validation prevents CSRF attacks
- Laravel CSRF tokens for API calls

### Secure Token Handling
- Tokens displayed securely with copy functionality
- No persistent token storage
- Proper session cleanup

### Error Handling
- Comprehensive OAuth error scenarios
- Network error handling
- User-friendly error messages

## 🧪 Testing Scenarios

### Automated Test Coverage

1. **Basic Functionality**
   - Dashboard rendering
   - Scope selection
   - Form submission
   - Discovery endpoint access

2. **OAuth Flow Testing**
   - Authorization initiation
   - Callback handling
   - Token exchange
   - User info retrieval

3. **Error Scenarios**
   - Invalid state parameters
   - Missing authorization codes
   - Network timeouts
   - Server errors

4. **Token Operations**
   - Token refresh
   - Token revocation
   - API error handling

5. **Cross-Platform**
   - Multiple browsers
   - Mobile responsive design
   - Performance benchmarks

### Manual Testing
1. Complete authorization flow
2. Scope variations
3. Token operations
4. Error conditions
5. Multiple sessions

## 📊 Test Results

After running tests, check:
- `test-results/report/index.html` - HTML test report
- `test-results/` - Screenshots of failed tests
- Console output for detailed logs

## 🔧 Customization

### Adding New Tests
1. Create new test files in `tests/e2e/`
2. Follow existing patterns for page interactions
3. Use descriptive test names and assertions
4. Add to test runner if needed

### Modifying OAuth Configuration
1. Update controller constructor for different servers
2. Modify client credentials in environment
3. Update redirect URIs as needed
4. Adjust scope handling for different providers

## 🚨 Troubleshooting

### Common Issues

**"Client not found" Error**
- Ensure main server is running (port 8000)
- Verify client seeded in database
- Check client ID matches seeded client

**"Connection refused" Error**
- Verify both servers running (8000, 8081)
- Check firewall settings
- Ensure network connectivity

**Test Failures**
- Check server logs for errors
- Verify database connectivity
- Review test screenshots in `test-results/`

### Debug Mode
- Set `APP_DEBUG=true` in `.env`
- Use `./run-tests.sh debug` for step-through
- Check Laravel logs in `storage/logs/`

## 🤝 Contributing

When contributing:
1. Maintain backward compatibility
2. Add comprehensive error handling
3. Update tests for new features
4. Follow Laravel coding standards
5. Update documentation

## 📝 License

This OAuth client example is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🎉 Getting Started

Ready to test OAuth 2.0? Run:

```bash
cd examples/oauth-client
./start-oauth-test.sh
```

Then visit `http://localhost:8081/oauth/` to begin testing!

For automated testing:
```bash
./run-tests.sh
```

Happy testing! 🎭✨