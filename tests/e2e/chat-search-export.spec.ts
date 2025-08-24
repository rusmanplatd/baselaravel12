import { test, expect } from '@playwright/test';

test.describe('Chat Search and Export Features', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should open message search', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Verify search interface is visible
    await expect(page.locator('[data-testid="search-dialog"]')).toBeVisible();
    await expect(page.locator('[data-testid="search-input"]')).toBeVisible();
  });

  test('should search messages by text content', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Enter search query
    await page.fill('[data-testid="search-input"]', 'test message');
    await page.press('[data-testid="search-input"]', 'Enter');
    
    // Verify search results
    await expect(page.locator('[data-testid="search-results"]')).toBeVisible();
    await expect(page.locator('[data-testid="search-result-item"]').first()).toBeVisible();
    
    // Click on search result
    await page.locator('[data-testid="search-result-item"]').first().click();
    
    // Verify navigation to message
    await expect(page.locator('[data-testid="highlighted-message"]')).toBeVisible();
  });

  test('should search messages by sender', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Search by sender
    await page.fill('[data-testid="search-input"]', 'from:test@example.com');
    await page.press('[data-testid="search-input"]', 'Enter');
    
    // Verify results from specific sender
    await expect(page.locator('[data-testid="search-results"]')).toBeVisible();
    const results = page.locator('[data-testid="search-result-item"]');
    await expect(results.first().locator('[data-testid="sender-name"]')).toContainText('test@example.com');
  });

  test('should search messages by date range', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Open advanced search options
    await page.click('[data-testid="advanced-search"]');
    
    // Set date range
    await page.fill('[data-testid="date-from"]', '2024-01-01');
    await page.fill('[data-testid="date-to"]', '2024-12-31');
    
    // Search
    await page.click('[data-testid="search-button"]');
    
    // Verify results within date range
    await expect(page.locator('[data-testid="search-results"]')).toBeVisible();
  });

  test('should search messages by file type', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Open advanced search options
    await page.click('[data-testid="advanced-search"]');
    
    // Select file type filter
    await page.selectOption('[data-testid="file-type-filter"]', 'image');
    
    // Search
    await page.click('[data-testid="search-button"]');
    
    // Verify image file results
    await expect(page.locator('[data-testid="file-result-item"]').first()).toBeVisible();
    await expect(page.locator('[data-testid="file-result-item"]').first().locator('[data-testid="file-type"]')).toContainText('image');
  });

  test('should export conversation messages', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Open conversation menu
    await page.click('[data-testid="conversation-menu"]');
    
    // Click export messages
    await page.click('[data-testid="export-messages"]');
    
    // Verify export dialog
    await expect(page.locator('[data-testid="export-dialog"]')).toBeVisible();
  });

  test('should export messages in different formats', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Open export dialog
    await page.click('[data-testid="conversation-menu"]');
    await page.click('[data-testid="export-messages"]');
    
    // Test JSON export
    await page.selectOption('[data-testid="export-format"]', 'json');
    
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="start-export"]');
    
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('.json');
  });

  test('should export messages with date range', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Open export dialog
    await page.click('[data-testid="conversation-menu"]');
    await page.click('[data-testid="export-messages"]');
    
    // Set date range
    await page.fill('[data-testid="export-date-from"]', '2024-01-01');
    await page.fill('[data-testid="export-date-to"]', '2024-12-31');
    
    // Start export
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="start-export"]');
    
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBeTruthy();
  });

  test('should export messages including attachments', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Open export dialog
    await page.click('[data-testid="conversation-menu"]');
    await page.click('[data-testid="export-messages"]');
    
    // Include attachments
    await page.check('[data-testid="include-attachments"]');
    
    // Select ZIP format for attachments
    await page.selectOption('[data-testid="export-format"]', 'zip');
    
    // Start export
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="start-export"]');
    
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('.zip');
  });

  test('should show export progress', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Start export
    await page.click('[data-testid="conversation-menu"]');
    await page.click('[data-testid="export-messages"]');
    await page.click('[data-testid="start-export"]');
    
    // Verify export progress is shown
    await expect(page.locator('[data-testid="export-progress"]')).toBeVisible();
    await expect(page.locator('[data-testid="export-status"]')).toContainText('Preparing export...');
  });

  test('should handle search with no results', async ({ page }) => {
    // Open search
    await page.click('[data-testid="open-search"]');
    
    // Search for non-existent content
    await page.fill('[data-testid="search-input"]', 'xyz123nonexistent456');
    await page.press('[data-testid="search-input"]', 'Enter');
    
    // Verify no results message
    await expect(page.locator('[data-testid="no-search-results"]')).toBeVisible();
    await expect(page.locator('[data-testid="no-results-message"]')).toContainText('No messages found');
  });

  test('should clear search results', async ({ page }) => {
    // Open search and perform search
    await page.click('[data-testid="open-search"]');
    await page.fill('[data-testid="search-input"]', 'test');
    await page.press('[data-testid="search-input"]', 'Enter');
    
    // Verify results exist
    await expect(page.locator('[data-testid="search-results"]')).toBeVisible();
    
    // Clear search
    await page.click('[data-testid="clear-search"]');
    
    // Verify search is cleared
    await expect(page.locator('[data-testid="search-input"]')).toHaveValue('');
    await expect(page.locator('[data-testid="search-results"]')).not.toBeVisible();
  });

  test('should search within current conversation only', async ({ page }) => {
    // Select specific conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Open search
    await page.click('[data-testid="search-in-conversation"]');
    
    // Verify search is scoped to current conversation
    await expect(page.locator('[data-testid="search-scope"]')).toContainText('Current conversation');
    
    // Perform search
    await page.fill('[data-testid="search-input"]', 'test');
    await page.press('[data-testid="search-input"]', 'Enter');
    
    // Verify results are from current conversation only
    await expect(page.locator('[data-testid="search-results"]')).toBeVisible();
  });
});