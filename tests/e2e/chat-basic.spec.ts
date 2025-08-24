import { test, expect } from '@playwright/test';

test.describe('Chat Basic Features', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');
    
    // Login with test user
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Wait for redirect to dashboard
    await page.waitForURL('/dashboard');
  });

  test('should navigate to chat page', async ({ page }) => {
    await page.goto('/chat');
    await expect(page).toHaveURL('/chat');
    await expect(page).toHaveTitle(/Chat/);
  });

  test('should display chat layout components', async ({ page }) => {
    await page.goto('/chat');
    
    // Check for main chat components
    await expect(page.locator('[data-testid="chat-layout"]')).toBeVisible();
    await expect(page.locator('[data-testid="conversation-list"]')).toBeVisible();
    await expect(page.locator('[data-testid="chat-window"]')).toBeVisible();
  });

  test('should create a new conversation', async ({ page }) => {
    await page.goto('/chat');
    
    // Click on create conversation button
    await page.click('[data-testid="create-conversation"]');
    
    // Fill in conversation details
    await page.fill('[name="name"]', 'Test Conversation');
    await page.fill('[name="description"]', 'This is a test conversation');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Verify conversation was created
    await expect(page.locator('[data-testid="conversation-item"]').first()).toContainText('Test Conversation');
  });

  test('should send a text message', async ({ page }) => {
    await page.goto('/chat');
    
    // Select first conversation (if any exists)
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Type and send message
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.fill('Hello, this is a test message!');
    await page.click('[data-testid="send-message"]');
    
    // Verify message appears in chat
    await expect(page.locator('[data-testid="message-bubble"]').last()).toContainText('Hello, this is a test message!');
  });

  test('should display typing indicator', async ({ page }) => {
    await page.goto('/chat');
    
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Start typing in message input
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.focus();
    await messageInput.type('Starting to type...');
    
    // Check if typing indicator is visible (this would need backend support)
    await expect(page.locator('[data-testid="typing-indicator"]')).toBeVisible();
  });

  test('should show message reactions', async ({ page }) => {
    await page.goto('/chat');
    
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Send a message first
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.fill('React to this message!');
    await page.click('[data-testid="send-message"]');
    
    // Right-click on message to show reactions
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    await lastMessage.hover();
    await page.click('[data-testid="add-reaction"]');
    
    // Select an emoji reaction
    await page.click('[data-testid="emoji-👍"]');
    
    // Verify reaction is added
    await expect(lastMessage.locator('[data-testid="reaction-👍"]')).toBeVisible();
  });
});