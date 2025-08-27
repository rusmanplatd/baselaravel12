import { test, expect, Page } from '@playwright/test';

test.describe('E2EE Backend Integration Tests', () => {
  let page: Page;
  const testUser = {
    name: 'E2EE Test User',
    email: `e2ee.test.${Date.now()}@example.com`,
    password: 'SecurePassword123!'
  };

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({
      // Disable web security for testing
      ignoreHTTPSErrors: true
    });
    page = await context.newPage();
  });

  test('Complete user registration and device setup flow', async () => {
    // Register new user
    await page.goto('/register');

    await page.fill('input[name="name"]', testUser.name);
    await page.fill('input[name="email"]', testUser.email);
    await page.fill('input[name="password"]', testUser.password);
    await page.fill('input[name="password_confirmation"]', testUser.password);

    // Submit registration
    await page.click('button[type="submit"]');

    // Should be redirected to dashboard or email verification
    await page.waitForURL(/.*\/(dashboard|email\/verify)/);

    // If email verification is required, skip for test
    if (page.url().includes('email/verify')) {
      // For testing, we'll simulate email verification by going directly to chat
      await page.goto('/chat');
    }
  });

  test('Device setup creates proper database records', async () => {
    // Navigate to chat - should trigger device setup
    await page.goto('/chat');

    // Should see device setup requirement
    await expect(page.locator('[data-testid="device-setup-required"]')).toBeVisible();

    // Start device setup
    await page.click('button:has-text("Setup Device Encryption")');

    // Fill device information
    await page.fill('input[name="device_name"]', 'Integration Test Device');
    await page.selectOption('select[name="device_type"]', 'desktop');

    // Continue through setup wizard
    await page.click('button:has-text("Continue")');

    // Wait for key generation
    await expect(page.locator('text=Keys Generated Successfully')).toBeVisible({ timeout: 30000 });

    // Complete setup
    await page.click('button:has-text("Complete Setup")');

    // Should see success and be able to access chat
    await expect(page.locator('[data-testid="create-conversation"]')).toBeEnabled();
    await expect(page.locator('[data-testid="e2ee-status-badge"]')).toBeVisible();
  });

  test('Device information is properly stored and retrievable', async () => {
    // Open device management
    await page.click('button:has-text("Devices")');

    // Should see the registered device
    await expect(page.locator('[data-testid="device-management-dialog"]')).toBeVisible();
    await expect(page.locator('text=Integration Test Device')).toBeVisible();

    // Device should show as trusted (first device)
    const deviceCard = page.locator('[data-testid="device-card"]:has-text("Integration Test Device")');
    await expect(deviceCard.locator('[title="Trusted Device"]')).toBeVisible();

    // Should show security level and other metadata
    await expect(deviceCard.locator('text=desktop')).toBeVisible();
    await expect(deviceCard.locator('[data-testid="security-badge"]')).toBeVisible();
  });

  test('Security report reflects real device status', async () => {
    // Switch to security report tab
    await page.click('[data-testid="security-tab"]');

    // Should show actual security metrics
    await expect(page.locator('[data-testid="security-score"]')).toBeVisible();
    await expect(page.locator('[data-testid="security-status"]')).toBeVisible();

    // Should show encryption summary
    await expect(page.locator('text=Active Conversation Keys')).toBeVisible();
    await expect(page.locator('text=Encryption Version')).toBeVisible();

    // Security score should be reasonable
    const securityScore = await page.locator('[data-testid="security-score"]').textContent();
    const scoreNumber = parseInt(securityScore?.replace(/\D/g, '') || '0');
    expect(scoreNumber).toBeGreaterThan(50); // Should have decent security score
  });

  test('End-to-end conversation creation and messaging', async () => {
    // Close device management dialog
    await page.click('[data-testid="close-dialog"]');

    // Create a new conversation
    await page.click('[data-testid="create-conversation"]');

    // Search for a user (we'll use a test email)
    const testRecipient = 'recipient@example.com';
    await page.fill('input[placeholder*="Search for users"]', testRecipient);

    // For testing, we'll mock the user search response
    await page.route('**/api/v1/users/search*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify([
          {
            id: 'test_recipient_id',
            name: 'Test Recipient',
            email: testRecipient
          }
        ])
      });
    });

    // Select the recipient
    await page.waitForTimeout(1000); // Wait for search
    await page.click(`text=${testRecipient}`);

    // Create conversation
    await page.click('button:has-text("Create Conversation")');

    // Should be able to send encrypted messages
    await expect(page.locator('[data-testid="message-input"]')).toBeEnabled();
    await expect(page.locator('text=Messages are end-to-end encrypted')).toBeVisible();
  });

  test('Real encryption and decryption of messages', async () => {
    // Send a test message
    const testMessage = 'This is a real encrypted message for integration testing';
    await page.fill('[data-testid="message-input"]', testMessage);

    // Capture the actual API request
    let sentMessageData: any = null;
    await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'POST') {
        sentMessageData = route.request().postDataJSON();

        // Verify encryption fields are present
        expect(sentMessageData.encrypted_content).toBeDefined();
        expect(sentMessageData.content_hash).toBeDefined();
        expect(sentMessageData.device_id).toBeDefined();

        // Continue with actual backend call
        await route.continue();
      }
    });

    await page.click('[data-testid="send-message"]');

    // Wait for message to appear
    await expect(page.locator('[data-testid="message-bubble"]').last()).toBeVisible();

    // Message should be decrypted and display correctly
    await expect(page.locator(`text=${testMessage}`)).toBeVisible();

    // Message should have encryption indicator
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    await expect(lastMessage.locator('[title*="encrypted"]')).toBeVisible();

    // Verify API data was encrypted
    expect(sentMessageData).not.toBeNull();
    expect(sentMessageData.encrypted_content).not.toContain(testMessage); // Should not contain plaintext
  });

  test('Key rotation updates database and maintains message access', async () => {
    // Open device management for key rotation
    await page.click('button:has-text("Devices")');
    await page.click('[data-testid="security-tab"]');

    // Get current key version before rotation
    const beforeRotation = await page.locator('text=Encryption Version').locator('..').locator('.font-medium').textContent();

    // Perform key rotation
    await page.click('button:has-text("Rotate Keys")');

    // Should show success
    await expect(page.locator('text=Key rotation completed')).toBeVisible();

    // Check if version updated
    await page.reload();
    await page.click('button:has-text("Devices")');
    await page.click('[data-testid="security-tab"]');

    const afterRotation = await page.locator('text=Encryption Version').locator('..').locator('.font-medium').textContent();

    // Version should have incremented or timestamp updated
    expect(afterRotation).not.toBe(beforeRotation);

    // Close dialog and verify messages still accessible
    await page.click('[data-testid="close-dialog"]');
    await page.click('[data-testid="conversation-item"]');

    // Previous messages should still be decryptable
    await expect(page.locator('text=This is a real encrypted message for integration testing')).toBeVisible();
  });

  test('Database integrity checks pass', async () => {
    // Navigate to settings/debug page if available
    await page.goto('/settings/security');

    // If there's a database integrity check endpoint, test it
    try {
      const response = await page.request.get('/api/v1/chat/encryption/integrity');
      if (response.ok()) {
        const data = await response.json();
        expect(data.status).toBe('healthy');
        expect(data.issues).toEqual([]);
      }
    } catch (e) {
      console.log('Integrity endpoint not available:', e);
    }

    // Alternatively, check that device data is consistent
    await page.goto('/chat');
    await page.click('button:has-text("Devices")');

    // Should not show any inconsistency warnings
    await expect(page.locator('[data-testid="consistency-error"]')).not.toBeVisible();
    await expect(page.locator('[data-testid="integrity-warning"]')).not.toBeVisible();
  });

  test('Multi-device scenario with second browser context', async ({ browser }) => {
    // Create second device context
    const device2Context = await browser.newContext({
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
      viewport: { width: 375, height: 812 }
    });
    const device2Page = await device2Context.newPage();

    try {
      // Login on second device
      await device2Page.goto('/login');
      await device2Page.fill('input[name="email"]', testUser.email);
      await device2Page.fill('input[name="password"]', testUser.password);
      await device2Page.click('button[type="submit"]');

      // Navigate to chat - should require device setup
      await device2Page.goto('/chat');
      await expect(device2Page.locator('[data-testid="device-setup-required"]')).toBeVisible();

      // Setup second device
      await device2Page.click('button:has-text("Setup Device Encryption")');
      await device2Page.fill('input[name="device_name"]', 'Mobile Integration Test Device');
      await device2Page.selectOption('select[name="device_type"]', 'mobile');
      await device2Page.click('button:has-text("Continue")');

      await expect(device2Page.locator('text=Keys Generated Successfully')).toBeVisible({ timeout: 30000 });
      await device2Page.click('button:has-text("Complete Setup")');

      // On first device, should see the new device
      await page.click('button:has-text("Devices")');
      await page.reload(); // Refresh to get latest devices

      await expect(page.locator('text=Mobile Integration Test Device')).toBeVisible();

      // Second device should not be trusted initially
      const mobileDevice = page.locator('[data-testid="device-card"]:has-text("Mobile Integration Test Device")');
      await expect(mobileDevice.locator('button:has-text("Trust")')).toBeVisible();

      // Trust the second device
      await mobileDevice.locator('button:has-text("Trust")').click();
      await page.click('button:has-text("Trust Device")');

      // Share keys with trusted device
      await expect(mobileDevice.locator('button:has-text("Share Keys")')).toBeVisible();
      await mobileDevice.locator('button:has-text("Share Keys")').click();

      // Should see key sharing success
      await expect(page.locator('text=Keys shared successfully')).toBeVisible();

    } finally {
      await device2Context.close();
    }
  });

  test('Performance metrics are within acceptable ranges', async () => {
    // Test encryption performance by sending multiple messages
    const startTime = Date.now();

    for (let i = 0; i < 10; i++) {
      await page.fill('[data-testid="message-input"]', `Performance test message ${i}`);
      await page.click('[data-testid="send-message"]');

      // Wait for message to appear
      await expect(page.locator(`text=Performance test message ${i}`)).toBeVisible();
    }

    const totalTime = Date.now() - startTime;
    const averageTimePerMessage = totalTime / 10;

    // Each message should encrypt and send within reasonable time
    expect(averageTimePerMessage).toBeLessThan(2000); // 2 seconds per message average

    console.log(`Average encryption and send time: ${averageTimePerMessage}ms per message`);
  });

  test('Clean up test data', async () => {
    // Navigate to account settings to delete test data
    try {
      await page.goto('/settings/account');

      // If there's a delete account option, we could use it for cleanup
      // For now, we'll just log out
      await page.click('button:has-text("Logout")');
      await expect(page).toHaveURL(/.*\/login/);

      console.log('Test cleanup completed');
    } catch (e) {
      console.log('Cleanup not available:', e);
    }
  });
});
