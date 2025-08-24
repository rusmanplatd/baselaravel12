import { test, expect } from '@playwright/test';
import path from 'path';

test.describe('Chat File Features', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should upload and send file', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Create a test file
    const testFilePath = path.join(__dirname, 'fixtures', 'test-document.txt');
    
    // Upload file
    const fileInput = page.locator('[data-testid="file-input"]');
    await fileInput.setInputFiles(testFilePath);
    
    // Add file caption
    await page.fill('[data-testid="file-caption"]', 'This is a test document');
    
    // Send file
    await page.click('[data-testid="send-file"]');
    
    // Verify file message appears
    const fileMessage = page.locator('[data-testid="file-message"]').last();
    await expect(fileMessage).toBeVisible();
    await expect(fileMessage).toContainText('test-document.txt');
  });

  test('should upload multiple files', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Create test files
    const testFiles = [
      path.join(__dirname, 'fixtures', 'test-image.jpg'),
      path.join(__dirname, 'fixtures', 'test-document.pdf')
    ];
    
    // Upload multiple files
    const fileInput = page.locator('[data-testid="file-input"]');
    await fileInput.setInputFiles(testFiles);
    
    // Verify file previews
    await expect(page.locator('[data-testid="file-preview"]')).toHaveCount(2);
    
    // Send files
    await page.click('[data-testid="send-files"]');
    
    // Verify file messages appear
    await expect(page.locator('[data-testid="file-message"]')).toHaveCount(2);
  });

  test('should preview image files', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Upload image file
    const imageFile = path.join(__dirname, 'fixtures', 'test-image.jpg');
    const fileInput = page.locator('[data-testid="file-input"]');
    await fileInput.setInputFiles(imageFile);
    
    // Send image
    await page.click('[data-testid="send-file"]');
    
    // Click on image to preview
    const imageMessage = page.locator('[data-testid="image-message"]').last();
    await imageMessage.click();
    
    // Verify image preview modal opens
    await expect(page.locator('[data-testid="image-preview-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="preview-image"]')).toBeVisible();
  });

  test('should download files', async ({ page }) => {
    // Assume we have a file message
    const fileMessage = page.locator('[data-testid="file-message"]').first();
    
    // Set up download promise
    const downloadPromise = page.waitForEvent('download');
    
    // Click download button
    await fileMessage.locator('[data-testid="download-file"]').click();
    
    // Wait for download to complete
    const download = await downloadPromise;
    
    // Verify download
    expect(download.suggestedFilename()).toBeTruthy();
  });

  test('should delete file messages', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Find a file message
    const fileMessage = page.locator('[data-testid="file-message"]').first();
    
    // Right-click to show context menu
    await fileMessage.click({ button: 'right' });
    
    // Click delete option
    await page.click('[data-testid="delete-message"]');
    
    // Confirm deletion
    await page.click('[data-testid="confirm-delete"]');
    
    // Verify message is removed
    await expect(fileMessage).not.toBeVisible();
  });

  test('should handle file upload errors', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Try to upload a very large file (mock)
    const oversizedFile = path.join(__dirname, 'fixtures', 'oversized-file.bin');
    
    const fileInput = page.locator('[data-testid="file-input"]');
    await fileInput.setInputFiles(oversizedFile);
    
    // Verify error message
    await expect(page.locator('[data-testid="upload-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="upload-error"]')).toContainText('File size exceeds limit');
  });

  test('should show file upload progress', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Upload a moderately sized file
    const testFile = path.join(__dirname, 'fixtures', 'medium-file.pdf');
    const fileInput = page.locator('[data-testid="file-input"]');
    await fileInput.setInputFiles(testFile);
    
    // Send file
    await page.click('[data-testid="send-file"]');
    
    // Verify upload progress is shown
    await expect(page.locator('[data-testid="upload-progress"]')).toBeVisible();
    
    // Wait for upload to complete
    await expect(page.locator('[data-testid="upload-complete"]')).toBeVisible();
  });
});