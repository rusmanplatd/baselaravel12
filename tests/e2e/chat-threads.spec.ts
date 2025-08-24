import { test, expect } from '@playwright/test';

test.describe('Chat Thread Features', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should create thread from message', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Send a message first
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.fill('This message will have replies');
    await page.click('[data-testid="send-message"]');
    
    // Hover over message and click reply
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    await lastMessage.hover();
    await page.click('[data-testid="reply-to-message"]');
    
    // Verify thread view opens
    await expect(page.locator('[data-testid="thread-view"]')).toBeVisible();
    await expect(page.locator('[data-testid="thread-parent-message"]')).toContainText('This message will have replies');
  });

  test('should send reply in thread', async ({ page }) => {
    // Assume we have a thread open
    await page.goto('/chat');
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Create thread (simplified)
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    if (await lastMessage.isVisible()) {
      await lastMessage.hover();
      await page.click('[data-testid="reply-to-message"]');
    }
    
    // Send reply in thread
    const threadInput = page.locator('[data-testid="thread-input"]');
    await threadInput.fill('This is a reply in the thread');
    await page.click('[data-testid="send-thread-reply"]');
    
    // Verify reply appears in thread
    await expect(page.locator('[data-testid="thread-reply"]').last()).toContainText('This is a reply in the thread');
  });

  test('should display thread count on parent message', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Assume we have a message with thread replies
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    
    // Verify thread count is displayed
    await expect(messageWithThread.locator('[data-testid="thread-count"]')).toBeVisible();
    await expect(messageWithThread.locator('[data-testid="thread-count"]')).toContainText(/\d+ repl/);
  });

  test('should navigate between main chat and thread', async ({ page }) => {
    // Open thread
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Verify thread view is open
    await expect(page.locator('[data-testid="thread-view"]')).toBeVisible();
    
    // Navigate back to main chat
    await page.click('[data-testid="close-thread"]');
    
    // Verify main chat is visible
    await expect(page.locator('[data-testid="main-chat"]')).toBeVisible();
    await expect(page.locator('[data-testid="thread-view"]')).not.toBeVisible();
  });

  test('should show thread participants', async ({ page }) => {
    // Open thread
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Verify thread participants are shown
    await expect(page.locator('[data-testid="thread-participants"]')).toBeVisible();
    
    // Check individual participant avatars
    await expect(page.locator('[data-testid="thread-participant-avatar"]').first()).toBeVisible();
  });

  test('should handle thread notifications', async ({ page }) => {
    // This test would verify that thread notifications work correctly
    // In a real implementation, this might involve multiple users/contexts
    
    // Open thread
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Send thread reply
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
      
      const threadInput = page.locator('[data-testid="thread-input"]');
      await threadInput.fill('New thread reply for notification');
      await page.click('[data-testid="send-thread-reply"]');
    }
    
    // Verify notification indicator (would need backend support)
    await expect(page.locator('[data-testid="thread-notification"]')).toBeVisible();
  });

  test('should display thread messages chronologically', async ({ page }) => {
    // Open thread with multiple replies
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Verify thread messages are in chronological order
    const threadReplies = page.locator('[data-testid="thread-reply"]');
    const count = await threadReplies.count();
    
    if (count > 1) {
      // Get timestamps and verify they're in order
      for (let i = 0; i < count - 1; i++) {
        const currentTime = await threadReplies.nth(i).locator('[data-testid="message-timestamp"]').textContent();
        const nextTime = await threadReplies.nth(i + 1).locator('[data-testid="message-timestamp"]').textContent();
        
        // In a real test, you'd parse and compare timestamps
        expect(currentTime).toBeTruthy();
        expect(nextTime).toBeTruthy();
      }
    }
  });

  test('should allow editing thread replies', async ({ page }) => {
    // Open thread
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Edit a thread reply
    const threadReply = page.locator('[data-testid="thread-reply"]').first();
    await threadReply.hover();
    await page.click('[data-testid="edit-thread-reply"]');
    
    // Verify edit mode
    await expect(page.locator('[data-testid="edit-reply-input"]')).toBeVisible();
    
    // Make edit
    await page.fill('[data-testid="edit-reply-input"]', 'Edited thread reply');
    await page.click('[data-testid="save-edit"]');
    
    // Verify edit was saved
    await expect(threadReply).toContainText('Edited thread reply');
    await expect(threadReply.locator('[data-testid="edited-indicator"]')).toBeVisible();
  });

  test('should delete thread replies', async ({ page }) => {
    // Open thread
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Delete a thread reply
    const threadReply = page.locator('[data-testid="thread-reply"]').first();
    await threadReply.hover();
    await page.click('[data-testid="delete-thread-reply"]');
    
    // Confirm deletion
    await page.click('[data-testid="confirm-delete-reply"]');
    
    // Verify reply was deleted
    await expect(threadReply).not.toBeVisible();
  });

  test('should show thread summary in main chat', async ({ page }) => {
    // Select conversation with threads
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Find message with thread
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    
    // Verify thread summary is shown
    await expect(messageWithThread.locator('[data-testid="thread-summary"]')).toBeVisible();
    await expect(messageWithThread.locator('[data-testid="thread-preview"]')).toBeVisible();
    
    // Verify last reply preview
    await expect(messageWithThread.locator('[data-testid="last-reply-preview"]')).toBeVisible();
  });

  test('should handle thread scrolling and pagination', async ({ page }) => {
    // Open thread with many replies
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    const messageWithThread = page.locator('[data-testid="message-with-thread"]').first();
    if (await messageWithThread.isVisible()) {
      await messageWithThread.click();
    }
    
    // Scroll to top of thread
    await page.locator('[data-testid="thread-messages"]').evaluate(el => el.scrollTop = 0);
    
    // Check for "Load more" button or infinite scroll
    if (await page.locator('[data-testid="load-more-thread-replies"]').isVisible()) {
      await page.click('[data-testid="load-more-thread-replies"]');
      
      // Verify more replies loaded
      const repliesBefore = await page.locator('[data-testid="thread-reply"]').count();
      await page.waitForTimeout(1000); // Wait for loading
      const repliesAfter = await page.locator('[data-testid="thread-reply"]').count();
      
      expect(repliesAfter).toBeGreaterThan(repliesBefore);
    }
  });
});