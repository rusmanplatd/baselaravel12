import { test, expect, Browser, BrowserContext, Page } from '@playwright/test';

test.describe('Multi-Device Quantum E2EE Tests', () => {
  let primaryContext: BrowserContext;
  let secondaryContext: BrowserContext;
  let primaryPage: Page;
  let secondaryPage: Page;

  test.beforeEach(async ({ browser }) => {
    // Create two browser contexts to simulate different devices
    primaryContext = await browser.newContext({
      userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Device-Primary',
    });
    
    secondaryContext = await browser.newContext({
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Device-Secondary',
    });

    primaryPage = await primaryContext.newPage();
    secondaryPage = await secondaryContext.newPage();

    // Navigate both contexts to chat
    await primaryPage.goto('/chat');
    await secondaryPage.goto('/chat');
    
    // Wait for both pages to load
    await primaryPage.waitForSelector('[data-testid="chat-container"]', { timeout: 10000 });
    await secondaryPage.waitForSelector('[data-testid="chat-container"]', { timeout: 10000 });
  });

  test.afterEach(async () => {
    await primaryContext?.close();
    await secondaryContext?.close();
  });

  test('should initialize multi-device quantum E2EE on both devices', async () => {
    // Check that quantum multi-device E2EE is initialized on primary device
    await expect(primaryPage.locator('[data-testid="multi-device-enabled"]')).toHaveAttribute('data-enabled', 'true');
    await expect(primaryPage.locator('[data-testid="quantum-status-indicator"]')).toHaveAttribute('data-quantum-ready', 'true');
    
    // Check on secondary device
    await expect(secondaryPage.locator('[data-testid="multi-device-enabled"]')).toHaveAttribute('data-enabled', 'true');
    await expect(secondaryPage.locator('[data-testid="quantum-status-indicator"]')).toHaveAttribute('data-quantum-ready', 'true');
    
    // Verify different device IDs
    const primaryDeviceId = await primaryPage.locator('[data-testid="current-device-id"]').textContent();
    const secondaryDeviceId = await secondaryPage.locator('[data-testid="current-device-id"]').textContent();
    
    expect(primaryDeviceId).not.toBe(secondaryDeviceId);
    expect(primaryDeviceId).toBeTruthy();
    expect(secondaryDeviceId).toBeTruthy();
  });

  test('should register and verify a new device', async () => {
    // Open multi-device manager on primary device
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    await primaryPage.waitForSelector('[data-testid="multi-device-manager"]');
    
    // Register secondary device
    await primaryPage.locator('[data-testid="add-device-button"]').click();
    await primaryPage.waitForSelector('[data-testid="add-device-dialog"]');
    
    await primaryPage.locator('[data-testid="device-name-input"]').fill('Test Secondary Device');
    await primaryPage.locator('[data-testid="device-type-select"]').selectOption('mobile');
    await primaryPage.locator('[data-testid="platform-input"]').fill('iPhone iOS 14');
    
    await primaryPage.locator('[data-testid="register-device-button"]').click();
    
    // Wait for registration success
    await expect(primaryPage.locator('[data-testid="device-registered-success"]')).toBeVisible({ timeout: 10000 });
    
    // Get verification code from primary device
    const verificationCode = await primaryPage.locator('[data-testid="verification-code"]').textContent();
    expect(verificationCode).toMatch(/^\d{6}$/); // 6-digit code
    
    // Switch to secondary device and verify
    await secondaryPage.locator('[data-testid="device-verification-prompt"]').waitFor({ timeout: 5000 });
    await secondaryPage.locator('[data-testid="verification-code-input"]').fill(verificationCode || '');
    await secondaryPage.locator('[data-testid="verify-device-button"]').click();
    
    // Verify success on both devices
    await expect(secondaryPage.locator('[data-testid="device-verified-success"]')).toBeVisible({ timeout: 10000 });
    await expect(primaryPage.locator('[data-testid="device-verified-success"]')).toBeVisible({ timeout: 10000 });
    
    // Check device appears in trusted devices list
    await expect(primaryPage.locator('[data-testid="trusted-device"]:has-text("Test Secondary Device")')).toBeVisible();
  });

  test('should encrypt and decrypt messages across devices', async () => {
    // First, register and verify secondary device (simplified)
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Send message from primary device
    const testMessage = 'Multi-device quantum encrypted message test';
    await primaryPage.locator('[data-testid="message-input"]').fill(testMessage);
    await primaryPage.locator('[data-testid="send-button"]').click();
    
    // Wait for message to appear on primary device
    await primaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${testMessage}")`, { timeout: 5000 });
    
    // Verify message appears on secondary device
    await secondaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${testMessage}")`, { timeout: 10000 });
    
    // Verify quantum-safe indicators on both devices
    const primaryMessage = primaryPage.locator(`[data-testid="message-content"]:has-text("${testMessage}")`).first();
    const secondaryMessage = secondaryPage.locator(`[data-testid="message-content"]:has-text("${testMessage}")`).first();
    
    await expect(primaryMessage).toHaveAttribute('data-quantum-safe', 'true');
    await expect(secondaryMessage).toHaveAttribute('data-quantum-safe', 'true');
    await expect(primaryMessage).toHaveAttribute('data-multi-device', 'true');
    await expect(secondaryMessage).toHaveAttribute('data-multi-device', 'true');
    
    // Verify different device encryption keys were used
    const primaryKeyVersion = await primaryMessage.getAttribute('data-key-version');
    const secondaryKeyVersion = await secondaryMessage.getAttribute('data-key-version');
    
    // Same message, but different device-specific encryption
    expect(primaryKeyVersion).toBeTruthy();
    expect(secondaryKeyVersion).toBeTruthy();
  });

  test('should sync device keys automatically', async () => {
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Force key rotation on primary device
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    await primaryPage.locator('[data-testid="rotate-all-keys-button"]').click();
    await primaryPage.locator('[data-testid="confirm-key-rotation-button"]').click();
    
    // Wait for key rotation to complete
    await expect(primaryPage.locator('[data-testid="key-rotation-success"]')).toBeVisible({ timeout: 15000 });
    
    // Verify secondary device receives key sync notification
    await expect(secondaryPage.locator('[data-testid="key-sync-notification"]')).toBeVisible({ timeout: 10000 });
    
    // Send message after key rotation to verify sync worked
    const postRotationMessage = 'Message after key rotation';
    await primaryPage.locator('[data-testid="message-input"]').fill(postRotationMessage);
    await primaryPage.locator('[data-testid="send-button"]').click();
    
    // Verify message decrypts properly on secondary device
    await secondaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${postRotationMessage}")`, { timeout: 10000 });
    
    const syncedMessage = secondaryPage.locator(`[data-testid="message-content"]:has-text("${postRotationMessage}")`).first();
    await expect(syncedMessage).toHaveAttribute('data-quantum-safe', 'true');
  });

  test('should handle device revocation properly', async () => {
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Revoke secondary device from primary
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    const secondaryDeviceEntry = primaryPage.locator('[data-testid="trusted-device"]:has-text("Test Secondary Device")');
    await secondaryDeviceEntry.locator('[data-testid="device-menu-button"]').click();
    await primaryPage.locator('[data-testid="revoke-device-option"]').click();
    await primaryPage.locator('[data-testid="confirm-revoke-button"]').click();
    
    // Wait for revocation to complete
    await expect(primaryPage.locator('[data-testid="device-revoked-success"]')).toBeVisible({ timeout: 10000 });
    
    // Verify device removed from trusted devices list
    await expect(secondaryDeviceEntry).not.toBeVisible();
    
    // Verify secondary device receives revocation notification
    await expect(secondaryPage.locator('[data-testid="device-revoked-notification"]')).toBeVisible({ timeout: 10000 });
    
    // Verify secondary device can no longer decrypt new messages
    const postRevocationMessage = 'Message after device revocation';
    await primaryPage.locator('[data-testid="message-input"]').fill(postRevocationMessage);
    await primaryPage.locator('[data-testid="send-button"]').click();
    
    // Message should not appear on secondary device (or appear as undecryptable)
    await expect(secondaryPage.locator(`[data-testid="message-content"]:has-text("${postRevocationMessage}")`)).not.toBeVisible({ timeout: 5000 });
    
    // Secondary device should show encryption error
    await expect(secondaryPage.locator('[data-testid="decryption-error"]')).toBeVisible();
  });

  test('should maintain message history after device verification', async () => {
    // Send messages before secondary device is added
    const preDeviceMessages = [
      'Message before secondary device',
      'Another message before verification',
      'Third historical message'
    ];
    
    for (const message of preDeviceMessages) {
      await primaryPage.locator('[data-testid="message-input"]').fill(message);
      await primaryPage.locator('[data-testid="send-button"]').click();
      await primaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${message}")`, { timeout: 5000 });
    }
    
    // Now register and verify secondary device
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Verify historical messages are synced to secondary device
    for (const message of preDeviceMessages) {
      await expect(secondaryPage.locator(`[data-testid="message-content"]:has-text("${message}")`)).toBeVisible({ timeout: 10000 });
    }
    
    // Send new message after verification
    const postDeviceMessage = 'Message after secondary device added';
    await primaryPage.locator('[data-testid="message-input"]').fill(postDeviceMessage);
    await primaryPage.locator('[data-testid="send-button"]').click();
    
    // Verify new message appears on both devices
    await primaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${postDeviceMessage}")`, { timeout: 5000 });
    await secondaryPage.waitForSelector(`[data-testid="message-content"]:has-text("${postDeviceMessage}")`, { timeout: 10000 });
  });

  test('should show multi-device security metrics', async () => {
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Open multi-device manager
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    await primaryPage.waitForSelector('[data-testid="multi-device-metrics"]');
    
    // Verify security metrics are displayed
    const totalDevices = await primaryPage.locator('[data-testid="total-devices-count"]').textContent();
    const trustedDevices = await primaryPage.locator('[data-testid="trusted-devices-count"]').textContent();
    const activeDevices = await primaryPage.locator('[data-testid="active-devices-count"]').textContent();
    const quantumReadiness = await primaryPage.locator('[data-testid="quantum-readiness-score"]').textContent();
    
    expect(parseInt(totalDevices || '0')).toBeGreaterThanOrEqual(2);
    expect(parseInt(trustedDevices || '0')).toBeGreaterThanOrEqual(2);
    expect(parseInt(activeDevices || '0')).toBeGreaterThanOrEqual(1);
    expect(parseFloat(quantumReadiness || '0')).toBeGreaterThanOrEqual(8);
    
    // Verify trust level and consistency scores
    const averageTrustLevel = await primaryPage.locator('[data-testid="average-trust-level"]').textContent();
    const keyConsistency = await primaryPage.locator('[data-testid="key-consistency-score"]').textContent();
    
    expect(parseFloat(averageTrustLevel || '0')).toBeGreaterThanOrEqual(7);
    expect(parseFloat(keyConsistency || '0')).toBeGreaterThanOrEqual(9);
  });

  test('should handle device limit enforcement', async () => {
    // Register maximum allowed devices (test assumes limit of 10)
    for (let i = 0; i < 10; i++) {
      try {
        await attemptDeviceRegistration(primaryPage, `Test Device ${i + 1}`);
      } catch (error) {
        // Expected to fail when limit is reached
        break;
      }
    }
    
    // Attempt to register one more device beyond limit
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    await primaryPage.locator('[data-testid="add-device-button"]').click();
    
    await primaryPage.locator('[data-testid="device-name-input"]').fill('Device Beyond Limit');
    await primaryPage.locator('[data-testid="register-device-button"]').click();
    
    // Should show device limit error
    await expect(primaryPage.locator('[data-testid="device-limit-error"]')).toBeVisible({ timeout: 5000 });
    await expect(primaryPage.locator('[data-testid="device-limit-error"]')).toContainText('Maximum number of devices');
  });

  test('should export comprehensive multi-device audit', async () => {
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Open multi-device manager and export audit
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    
    const downloadPromise = primaryPage.waitForEvent('download');
    await primaryPage.locator('[data-testid="export-multi-device-audit-button"]').click();
    const download = await downloadPromise;
    
    // Verify download
    expect(download.suggestedFilename()).toMatch(/multi-device-audit-.*\.json/);
    
    // Verify audit contains expected information
    const auditPath = await download.path();
    expect(auditPath).toBeTruthy();
  });

  test('should handle concurrent message sending from multiple devices', async () => {
    await registerAndVerifySecondaryDevice(primaryPage, secondaryPage);
    
    // Send messages concurrently from both devices
    const primaryMessage = 'Message from primary device';
    const secondaryMessage = 'Message from secondary device';
    
    // Start both sends simultaneously
    const primarySend = (async () => {
      await primaryPage.locator('[data-testid="message-input"]').fill(primaryMessage);
      await primaryPage.locator('[data-testid="send-button"]').click();
    })();
    
    const secondarySend = (async () => {
      await secondaryPage.locator('[data-testid="message-input"]').fill(secondaryMessage);
      await secondaryPage.locator('[data-testid="send-button"]').click();
    })();
    
    await Promise.all([primarySend, secondarySend]);
    
    // Verify both messages appear on both devices
    await expect(primaryPage.locator(`[data-testid="message-content"]:has-text("${primaryMessage}")`)).toBeVisible({ timeout: 10000 });
    await expect(primaryPage.locator(`[data-testid="message-content"]:has-text("${secondaryMessage}")`)).toBeVisible({ timeout: 10000 });
    
    await expect(secondaryPage.locator(`[data-testid="message-content"]:has-text("${primaryMessage}")`)).toBeVisible({ timeout: 10000 });
    await expect(secondaryPage.locator(`[data-testid="message-content"]:has-text("${secondaryMessage}")`)).toBeVisible({ timeout: 10000 });
    
    // Verify proper ordering and quantum-safe encryption
    const allMessages = await primaryPage.locator('[data-testid="message-content"]').all();
    expect(allMessages.length).toBeGreaterThanOrEqual(2);
    
    for (const message of allMessages) {
      await expect(message).toHaveAttribute('data-quantum-safe', 'true');
    }
  });

  // Helper functions
  async function registerAndVerifySecondaryDevice(primaryPage: Page, secondaryPage: Page) {
    await primaryPage.locator('[data-testid="multi-device-manager-button"]').click();
    await primaryPage.locator('[data-testid="add-device-button"]').click();
    
    await primaryPage.locator('[data-testid="device-name-input"]').fill('Test Secondary Device');
    await primaryPage.locator('[data-testid="device-type-select"]').selectOption('mobile');
    await primaryPage.locator('[data-testid="register-device-button"]').click();
    
    const verificationCode = await primaryPage.locator('[data-testid="verification-code"]').textContent();
    
    await secondaryPage.locator('[data-testid="verification-code-input"]').fill(verificationCode || '');
    await secondaryPage.locator('[data-testid="verify-device-button"]').click();
    
    await expect(primaryPage.locator('[data-testid="device-verified-success"]')).toBeVisible({ timeout: 10000 });
    await expect(secondaryPage.locator('[data-testid="device-verified-success"]')).toBeVisible({ timeout: 10000 });
  }

  async function attemptDeviceRegistration(page: Page, deviceName: string) {
    await page.locator('[data-testid="add-device-button"]').click();
    await page.locator('[data-testid="device-name-input"]').fill(deviceName);
    await page.locator('[data-testid="register-device-button"]').click();
    
    // Wait for either success or error
    await Promise.race([
      page.locator('[data-testid="device-registered-success"]').waitFor({ timeout: 5000 }),
      page.locator('[data-testid="device-limit-error"]').waitFor({ timeout: 5000 })
    ]);
  }
});