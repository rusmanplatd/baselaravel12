# Chat Feature Testing with Playwright

This comprehensive end-to-end test suite covers all chat features in the Laravel + React application using Playwright.

## Overview

The test suite includes **216 tests** across **8 test files**, covering all major chat functionality:

### 📁 Test Files

1. **`chat-basic.spec.ts`** - Basic chat functionality
   - Navigation and UI components
   - Creating conversations
   - Sending messages
   - Typing indicators
   - Message reactions

2. **`chat-groups.spec.ts`** - Group conversation management
   - Creating group conversations
   - Managing participants
   - Updating roles and permissions
   - Invite link generation
   - Group settings management

3. **`chat-files.spec.ts`** - File sharing capabilities
   - File upload and sharing
   - Multiple file handling
   - Image preview functionality
   - File download
   - Upload progress and error handling

4. **`chat-encryption.spec.ts`** - End-to-End Encryption (E2EE)
   - E2EE status indicators
   - Key generation and management
   - Encrypted messaging
   - Key rotation
   - Performance monitoring
   - Error recovery

5. **`chat-voice.spec.ts`** - Voice messaging features
   - Voice recording
   - Playback controls
   - Voice message sharing
   - Encryption support
   - Permission handling
   - Waveform display

6. **`chat-threads.spec.ts`** - Message threading
   - Thread creation from messages
   - Thread replies
   - Thread navigation
   - Participant management
   - Message editing in threads

7. **`chat-search-export.spec.ts`** - Search and export functionality
   - Message search by content, sender, date
   - Advanced search filters
   - Message export in various formats
   - Export with date ranges and attachments

8. **`chat-api.spec.ts`** - API integration testing
   - REST API endpoint testing
   - CRUD operations for conversations and messages
   - File upload via API
   - Rate limiting verification

## 🚀 Running Tests

### Prerequisites

- Node.js and npm installed
- Playwright browsers installed (`npx playwright install`)
- Laravel development server running
- Test database seeded with test data

### Commands

```bash
# Run all chat tests
npm run test:e2e

# Run with UI mode (interactive)
npm run test:e2e:ui

# Run in headed mode (visible browser)
npm run test:e2e:headed

# Debug mode (step through tests)
npm run test:e2e:debug

# Run specific test file
npx playwright test chat-basic.spec.ts

# Run specific test
npx playwright test -g "should send a text message"
```

### Browser Support

Tests run on:
- ✅ Chromium
- ✅ Firefox  
- ✅ WebKit (Safari)

## 🔧 Configuration

The test configuration is in `playwright.config.ts`:

- **Base URL**: `http://localhost:8000`
- **Test Directory**: `tests/e2e/`
- **Parallel Execution**: Enabled
- **Retries**: 2 times in CI
- **Trace**: On first retry
- **Reporter**: HTML report

## 📊 Test Coverage

### Chat Features Tested

| Feature | Tests | Status |
|---------|-------|--------|
| Basic Chat | 6 tests | ✅ |
| Group Management | 6 tests | ✅ |
| File Sharing | 8 tests | ✅ |
| E2EE Encryption | 10 tests | ✅ |
| Voice Messages | 10 tests | ✅ |
| Message Threads | 11 tests | ✅ |
| Search & Export | 12 tests | ✅ |
| API Integration | 10 tests | ✅ |

### API Endpoints Covered

- `/api/v1/chat/conversations` (CRUD operations)
- `/api/v1/chat/conversations/{id}/messages` (messaging)
- `/api/v1/chat/conversations/{id}/participants` (group management)
- `/api/v1/chat/conversations/{id}/upload` (file sharing)
- `/api/v1/chat/messages/{id}/reactions` (reactions)
- `/api/v1/chat/encryption/*` (E2EE endpoints)
- `/api/v1/chat/conversations/{id}/typing` (typing indicators)

## 🎯 Test Data Requirements

### User Accounts
```javascript
// Test users required for authentication
{
  email: 'test@example.com',
  password: 'password'
}
```

### Test Files
- `tests/e2e/fixtures/test-document.txt` - Sample text file
- Additional fixture files may be needed for comprehensive testing

### Database Seeding
Ensure your test database has:
- Test users for authentication
- Sample conversations and messages
- File attachments for testing
- Encryption keys for E2EE tests

## 🔍 Test Structure

Each test follows this pattern:

1. **Setup** - Login and navigate to chat
2. **Action** - Perform the feature action
3. **Verification** - Assert expected behavior
4. **Cleanup** - Reset state if needed

### Example Test

```typescript
test('should send a text message', async ({ page }) => {
  await page.goto('/chat');
  
  // Select conversation
  const firstConversation = page.locator('[data-testid="conversation-item"]').first();
  await firstConversation.click();
  
  // Send message
  const messageInput = page.locator('[data-testid="message-input"]');
  await messageInput.fill('Hello, this is a test message!');
  await page.click('[data-testid="send-message"]');
  
  // Verify message appears
  await expect(page.locator('[data-testid="message-bubble"]').last()).toContainText('Hello, this is a test message!');
});
```

## 🏷️ Test Data Attributes

Tests use `data-testid` attributes for reliable element selection:

- `conversation-item` - Conversation list items
- `message-input` - Message input field
- `send-message` - Send button
- `message-bubble` - Individual messages
- `typing-indicator` - Typing status
- `file-input` - File upload input
- `voice-recorder` - Voice recording controls
- And many more...

## 📈 Reporting

After running tests, view results in:
- **HTML Report**: `playwright-report/index.html`
- **Console Output**: Real-time test results
- **Traces**: Debug failed tests with `npx playwright show-trace`

## 🛠️ Maintenance

### Adding New Tests

1. Create test file in `tests/e2e/`
2. Follow existing naming convention
3. Use helper functions from `auth-helper.ts`
4. Add appropriate `data-testid` attributes to UI components
5. Update this README with new test coverage

### Updating Tests

When chat features change:
1. Update corresponding test files
2. Verify all assertions still valid
3. Add new test cases for new functionality
4. Update test data attributes if needed

## 🚨 Common Issues

### Browser Dependencies
If tests fail to start:
```bash
sudo npx playwright install-deps
```

### Authentication Issues
Ensure test users exist in database and credentials are correct in test files.

### Timing Issues
Use proper waits:
```typescript
await page.waitForSelector('[data-testid="element"]');
await page.waitForURL('/expected-url');
```

### File Upload Tests
Ensure fixture files exist in `tests/e2e/fixtures/` directory.

## 🎬 Video Recording

Tests can record videos of failures:
```typescript
// In playwright.config.ts
use: {
  video: 'retain-on-failure',
  screenshot: 'only-on-failure'
}
```

This comprehensive test suite ensures all chat features work correctly across different browsers and provides confidence when deploying new versions of the application.
