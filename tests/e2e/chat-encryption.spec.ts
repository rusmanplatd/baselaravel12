import { test, expect } from '@playwright/test';

test.describe('Chat End-to-End Encryption (E2EE)', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should display E2EE status indicator', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Check for E2EE status indicator
    await expect(page.locator('[data-testid="e2ee-status"]')).toBeVisible();
    
    // Verify encryption is enabled
    await expect(page.locator('[data-testid="e2ee-enabled"]')).toBeVisible();
  });

  test('should generate and manage encryption keys', async ({ page }) => {
    // Navigate to E2EE settings
    await page.goto('/chat/settings/encryption');
    
    // Generate new key pair
    await page.click('[data-testid="generate-keypair"]');
    
    // Verify key generation success
    await expect(page.locator('[data-testid="keypair-generated"]')).toBeVisible();
    
    // Display public key
    await page.click('[data-testid="show-public-key"]');
    await expect(page.locator('[data-testid="public-key-display"]')).toBeVisible();
  });

  test('should send encrypted messages', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Verify encryption is active
    await expect(page.locator('[data-testid="encryption-active"]')).toBeVisible();
    
    // Send an encrypted message
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.fill('This is an encrypted message');
    await page.click('[data-testid="send-message"]');
    
    // Verify message has encryption indicator
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    await expect(lastMessage.locator('[data-testid="encrypted-badge"]')).toBeVisible();
  });

  test('should handle encryption key rotation', async ({ page }) => {
    // Select group conversation for key rotation test
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    if (await groupConversation.isVisible()) {
      await groupConversation.click();
    }
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Navigate to security settings
    await page.click('[data-testid="security-settings"]');
    
    // Rotate encryption key
    await page.click('[data-testid="rotate-key"]');
    
    // Confirm key rotation
    await page.click('[data-testid="confirm-rotation"]');
    
    // Verify key rotation success
    await expect(page.locator('[data-testid="key-rotated"]')).toBeVisible();
  });

  test('should display E2EE error recovery options', async ({ page }) => {
    // Navigate to E2EE settings
    await page.goto('/chat/settings/encryption');
    
    // Simulate encryption error scenario
    await page.click('[data-testid="simulate-key-error"]');
    
    // Verify error recovery panel appears
    await expect(page.locator('[data-testid="e2ee-recovery-panel"]')).toBeVisible();
    
    // Test recovery options
    await expect(page.locator('[data-testid="regenerate-keys"]')).toBeVisible();
    await expect(page.locator('[data-testid="reset-encryption"]')).toBeVisible();
  });

  test('should verify message integrity', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Send a message
    const messageInput = page.locator('[data-testid="message-input"]');
    await messageInput.fill('Message for integrity verification');
    await page.click('[data-testid="send-message"]');
    
    // Right-click on message to access verification
    const lastMessage = page.locator('[data-testid="message-bubble"]').last();
    await lastMessage.click({ button: 'right' });
    
    // Verify message integrity
    await page.click('[data-testid="verify-integrity"]');
    
    // Check verification result
    await expect(page.locator('[data-testid="integrity-verified"]')).toBeVisible();
  });

  test('should handle E2EE performance monitoring', async ({ page }) => {
    // Navigate to E2EE performance dashboard
    await page.goto('/chat/settings/encryption/performance');
    
    // Verify performance metrics are displayed
    await expect(page.locator('[data-testid="encryption-metrics"]')).toBeVisible();
    await expect(page.locator('[data-testid="decrypt-time"]')).toBeVisible();
    await expect(page.locator('[data-testid="encrypt-time"]')).toBeVisible();
    
    // Check performance warnings
    if (await page.locator('[data-testid="performance-warning"]').isVisible()) {
      await expect(page.locator('[data-testid="performance-suggestions"]')).toBeVisible();
    }
  });

  test('should export and import encryption keys', async ({ page }) => {
    // Navigate to E2EE settings
    await page.goto('/chat/settings/encryption');
    
    // Export keys
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="export-keys"]');
    
    // Verify download
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('.key');
    
    // Test key import
    await page.click('[data-testid="import-keys"]');
    
    // Select key file
    const fileInput = page.locator('[data-testid="key-file-input"]');
    // Note: In real test, you'd use an actual key file
    // await fileInput.setInputFiles(keyFilePath);
    
    // Verify import option is available
    await expect(page.locator('[data-testid="import-button"]')).toBeVisible();
  });

  test('should handle encrypted file sharing', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Upload and encrypt file
    const fileInput = page.locator('[data-testid="file-input"]');
    // Note: In real test, you'd provide an actual file
    // await fileInput.setInputFiles(testFilePath);
    
    // Enable file encryption
    await page.check('[data-testid="encrypt-file"]');
    
    // Send encrypted file
    await page.click('[data-testid="send-file"]');
    
    // Verify file has encryption indicator
    const fileMessage = page.locator('[data-testid="file-message"]').last();
    await expect(fileMessage.locator('[data-testid="file-encrypted"]')).toBeVisible();
  });
});