import { test, expect } from '@playwright/test';

test.describe('Chat Comprehensive Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
  });

  test('should navigate to chat and display main interface', async ({ page }) => {
    await page.goto('/chat');
    
    // Verify main layout components are visible
    await expect(page.locator('[data-testid="chat-layout"]')).toBeVisible();
    await expect(page.locator('[data-testid="conversation-list"]')).toBeVisible();
    await expect(page.locator('[data-testid="chat-window"]')).toBeVisible();
    
    // Check for Messages header
    await expect(page.locator('h1:has-text("Messages")')).toBeVisible();
    
    // Check for encryption status
    await expect(page.locator('text=encrypted')).toBeVisible();
  });

  test('should show create conversation button', async ({ page }) => {
    await page.goto('/chat');
    
    // Should show create conversation buttons
    await expect(page.locator('[data-testid="create-conversation"]')).toBeVisible();
    await expect(page.locator('text=Create Group')).toBeVisible();
  });

  test('should open create conversation dialog', async ({ page }) => {
    await page.goto('/chat');
    
    // Click create conversation button
    await page.click('[data-testid="create-conversation"]');
    
    // Dialog should open
    await expect(page.locator('text=Start a direct conversation')).toBeVisible();
    
    // Click on the combobox trigger to open the search input
    await page.click('button[role="combobox"]');
    
    // Now the search input should be visible
    await expect(page.locator('[placeholder*="Search users by name or email"]')).toBeVisible();
  });

  test('should show empty conversation state when no conversations exist', async ({ page }) => {
    await page.goto('/chat');
    
    // Should show empty state if no conversations
    const emptyState = page.locator('text=No conversations');
    const selectConversation = page.locator('text=Select a conversation');
    
    // Either we have conversations or show appropriate empty state
    const hasConversations = await page.locator('[data-testid="conversation-item"]').count() > 0;
    
    if (!hasConversations) {
      await expect(emptyState.or(selectConversation)).toBeVisible();
    }
  });

  test('should display conversation list if conversations exist', async ({ page }) => {
    await page.goto('/chat');
    
    // Wait for conversations to load
    await page.waitForTimeout(2000);
    
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      // Should show conversation items
      await expect(conversationItems.first()).toBeVisible();
      
      // Each conversation should have basic info
      await expect(conversationItems.first().locator('text=Direct message, Group conversation')).toBeVisible();
    }
  });

  test('should be able to select a conversation if any exist', async ({ page }) => {
    await page.goto('/chat');
    await page.waitForTimeout(2000);
    
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      // Click on first conversation
      await conversationItems.first().click();
      
      // Should show the conversation details
      await page.waitForTimeout(1000);
      
      // The conversation should be highlighted/selected
      const firstConversation = conversationItems.first();
      const className = await firstConversation.getAttribute('class');
      expect(className).toContain('bg-blue-50');
    }
  });

  test('should show chat window when conversation is selected', async ({ page }) => {
    await page.goto('/chat');
    await page.waitForTimeout(2000);
    
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      // Select first conversation
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      // Should show message input
      await expect(page.locator('[data-testid="message-input"]')).toBeVisible();
      await expect(page.locator('[data-testid="send-message"]')).toBeVisible();
      
      // Should show conversation header
      await expect(page.locator('text=End-to-end encrypted')).toBeVisible();
    }
  });

  test('should be able to type in message input when conversation selected', async ({ page }) => {
    await page.goto('/chat');
    await page.waitForTimeout(2000);
    
    const conversationItems = page.locator('[data-testid="conversation-item"]');
    const conversationCount = await conversationItems.count();
    
    if (conversationCount > 0) {
      // Select first conversation
      await conversationItems.first().click();
      await page.waitForTimeout(1000);
      
      // Type in message input
      const messageInput = page.locator('[data-testid="message-input"]');
      await messageInput.fill('Test message from Playwright');
      
      // Verify text was entered
      await expect(messageInput).toHaveValue('Test message from Playwright');
      
      // Send button should be enabled
      await expect(page.locator('[data-testid="send-message"]')).toBeEnabled();
    }
  });

  test('should show placeholder when no conversation selected', async ({ page }) => {
    await page.goto('/chat');
    
    // If no conversation is auto-selected, should show placeholder
    const hasSelectedConversation = await page.locator('[data-testid="message-input"]').isVisible();
    
    if (!hasSelectedConversation) {
      await expect(page.locator('text=Select a conversation')).toBeVisible();
    }
  });

  test('should handle authentication and encryption status', async ({ page }) => {
    await page.goto('/chat');
    
    // Should show encryption status
    const encryptionText = page.locator('text=encrypted');
    await expect(encryptionText).toBeVisible();
    
    // Should show user is authenticated (no login redirect)
    await expect(page).toHaveURL(/.*chat.*/);
  });
});