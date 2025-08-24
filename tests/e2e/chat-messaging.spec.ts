import { test, expect } from '@playwright/test';

test.describe('Chat Messaging Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
    await page.waitForTimeout(2000);
  });

  test('should be able to send a message when conversation is selected', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      // Select first conversation
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      // Count existing messages
      const existingMessages = await page.locator('[data-testid="message-bubble"]').count();
      
      // Type and send a message
      const testMessage = `Test message ${Date.now()}`;
      const messageInput = page.locator('[data-testid="message-input"]');
      await messageInput.fill(testMessage);
      await page.click('[data-testid="send-message"]');
      
      // Wait a bit for the message to be processed
      await page.waitForTimeout(2000);
      
      // Verify message was sent (input should be cleared)
      await expect(messageInput).toHaveValue('');
      
      // Should have one more message bubble than before
      const newMessageCount = await page.locator('[data-testid="message-bubble"]').count();
      expect(newMessageCount).toBe(existingMessages + 1);
      
      // The last message should contain our test content
      await expect(page.locator('[data-testid="message-bubble"]').last()).toContainText(testMessage);
    } else {
      // Skip if no conversations exist
      test.skip();
    }
  });

  test('should clear input after sending message', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      const messageInput = page.locator('[data-testid="message-input"]');
      await messageInput.fill('This message should be cleared after sending');
      await page.click('[data-testid="send-message"]');
      
      // Wait for message to be processed
      await page.waitForTimeout(1000);
      
      // Input should be empty
      await expect(messageInput).toHaveValue('');
    } else {
      test.skip();
    }
  });

  test('should disable send button when input is empty', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      const messageInput = page.locator('[data-testid="message-input"]');
      const sendButton = page.locator('[data-testid="send-message"]');
      
      // Initially empty input should disable send button
      await expect(sendButton).toBeDisabled();
      
      // Type something to enable it
      await messageInput.fill('Some text');
      await expect(sendButton).toBeEnabled();
      
      // Clear input to disable it again
      await messageInput.clear();
      await expect(sendButton).toBeDisabled();
    } else {
      test.skip();
    }
  });

  test('should show encryption status in conversation header', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      // Should show encryption status
      await expect(page.locator('text=End-to-end encrypted, Encryption initializing')).toBeVisible();
    } else {
      test.skip();
    }
  });

  test('should support keyboard shortcuts for sending messages', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      const messageInput = page.locator('[data-testid="message-input"]');
      const testMessage = `Keyboard test ${Date.now()}`;
      
      // Type message
      await messageInput.fill(testMessage);
      
      // Press Enter to send (should work as keyboard shortcut)
      await messageInput.press('Enter');
      
      // Wait for message processing
      await page.waitForTimeout(1500);
      
      // Input should be cleared and message should appear
      await expect(messageInput).toHaveValue('');
      await expect(page.locator('[data-testid="message-bubble"]').last()).toContainText(testMessage);
    } else {
      test.skip();
    }
  });

  test('should show conversation participant info', async ({ page }) => {
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      // Should show conversation title in header
      const conversationHeader = page.locator('h2');
      await expect(conversationHeader).toBeVisible();
      
      // Should show some form of participant information
      const participantInfo = page.locator('text=Direct message, Group, member');
      await expect(participantInfo).toBeVisible();
    } else {
      test.skip();
    }
  });
});