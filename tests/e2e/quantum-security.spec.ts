import { test, expect } from '@playwright/test';

test.describe('Quantum-Safe E2EE Security Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/chat');
    
    // Wait for app to load
    await page.waitForSelector('[data-testid="chat-container"]', { timeout: 10000 });
  });

  test('should initialize quantum-safe E2EE successfully', async ({ page }) => {
    // Check that quantum E2EE is initialized
    await expect(page.locator('[data-testid="quantum-status-indicator"]')).toHaveAttribute('data-quantum-ready', 'true');
    
    // Verify algorithm is quantum-safe
    const algorithmInfo = await page.locator('[data-testid="encryption-algorithm"]').textContent();
    expect(algorithmInfo).toContain('PQ-E2EE-v1.0');
    
    // Check security level
    const securityLevel = await page.locator('[data-testid="security-level"]').getAttribute('data-level');
    expect(parseInt(securityLevel || '0')).toBeGreaterThanOrEqual(5);
  });

  test('should encrypt and decrypt messages with quantum-safe algorithms', async ({ page }) => {
    const testMessage = 'This is a quantum-safe test message';
    
    // Send a message
    await page.locator('[data-testid="message-input"]').fill(testMessage);
    await page.locator('[data-testid="send-button"]').click();
    
    // Wait for message to appear
    await page.waitForSelector(`[data-testid="message-content"]:has-text("${testMessage}")`, { timeout: 5000 });
    
    // Verify message is displayed (meaning it was encrypted and then decrypted)
    await expect(page.locator(`[data-testid="message-content"]:has-text("${testMessage}")`)).toBeVisible();
    
    // Check that the message has quantum-safe indicators
    const messageElement = page.locator(`[data-testid="message-content"]:has-text("${testMessage}")`).first();
    const quantumSafe = await messageElement.getAttribute('data-quantum-safe');
    expect(quantumSafe).toBe('true');
  });

  test('should generate and rotate quantum-safe keys', async ({ page }) => {
    // Open security settings
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    // Generate new key pair
    await page.locator('[data-testid="generate-keys-button"]').click();
    
    // Wait for key generation confirmation
    await expect(page.locator('[data-testid="key-generation-success"]')).toBeVisible({ timeout: 10000 });
    
    // Verify key generation timestamp is recent
    const keyTimestamp = await page.locator('[data-testid="key-generation-time"]').textContent();
    const now = new Date();
    const keyTime = new Date(keyTimestamp || '');
    expect(now.getTime() - keyTime.getTime()).toBeLessThan(30000); // Less than 30 seconds ago
    
    // Test key rotation
    await page.locator('[data-testid="rotate-keys-button"]').click();
    await page.locator('[data-testid="confirm-rotation-button"]').click();
    
    // Wait for rotation confirmation
    await expect(page.locator('[data-testid="key-rotation-success"]')).toBeVisible({ timeout: 10000 });
  });

  test('should verify forward secrecy is enabled', async ({ page }) => {
    // Check forward secrecy status in security settings
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    // Verify forward secrecy is enabled
    const forwardSecrecyStatus = await page.locator('[data-testid="forward-secrecy-status"]').getAttribute('data-enabled');
    expect(forwardSecrecyStatus).toBe('true');
    
    // Check that new messages use different ratchet keys
    await page.locator('[data-testid="close-modal-button"]').click();
    
    // Send multiple messages and verify they have different key versions
    const messages = ['Message 1', 'Message 2', 'Message 3'];
    const keyVersions = new Set();
    
    for (const message of messages) {
      await page.locator('[data-testid="message-input"]').fill(message);
      await page.locator('[data-testid="send-button"]').click();
      await page.waitForSelector(`[data-testid="message-content"]:has-text("${message}")`, { timeout: 5000 });
      
      const messageElement = page.locator(`[data-testid="message-content"]:has-text("${message}")`).first();
      const keyVersion = await messageElement.getAttribute('data-key-version');
      keyVersions.add(keyVersion);
    }
    
    // Should have different key versions for forward secrecy
    expect(keyVersions.size).toBeGreaterThan(1);
  });

  test('should detect and handle quantum threats', async ({ page }) => {
    // Simulate a potential quantum attack by tampering with message integrity
    await page.evaluate(() => {
      // Inject a malformed quantum message
      window.postMessage({
        type: 'QUANTUM_THREAT_SIMULATION',
        data: {
          threatType: 'signature_forgery',
          severity: 'high'
        }
      }, '*');
    });
    
    // Check that threat detection system responds
    await expect(page.locator('[data-testid="security-alert"]')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('[data-testid="threat-level"]')).toHaveAttribute('data-level', 'high');
    
    // Verify that security measures are activated
    const securityMode = await page.locator('[data-testid="security-mode"]').getAttribute('data-mode');
    expect(securityMode).toBe('enhanced');
  });

  test('should backup and restore quantum keys', async ({ page }) => {
    // Open security settings
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    // Create backup
    await page.locator('[data-testid="create-backup-button"]').click();
    
    // Enter backup password
    const backupPassword = 'quantum-secure-backup-2025';
    await page.locator('[data-testid="backup-password-input"]').fill(backupPassword);
    await page.locator('[data-testid="confirm-backup-button"]').click();
    
    // Wait for backup to be created
    await expect(page.locator('[data-testid="backup-created"]')).toBeVisible({ timeout: 10000 });
    
    // Get backup data
    const backupData = await page.locator('[data-testid="backup-data"]').textContent();
    expect(backupData).toBeTruthy();
    expect(backupData?.length).toBeGreaterThan(100); // Backup should contain substantial data
    
    // Clear encryption data to test restore
    await page.locator('[data-testid="clear-encryption-button"]').click();
    await page.locator('[data-testid="confirm-clear-button"]').click();
    
    // Verify encryption is cleared
    await expect(page.locator('[data-testid="quantum-status-indicator"]')).toHaveAttribute('data-quantum-ready', 'false');
    
    // Restore from backup
    await page.locator('[data-testid="restore-backup-button"]').click();
    await page.locator('[data-testid="backup-data-input"]').fill(backupData || '');
    await page.locator('[data-testid="restore-password-input"]').fill(backupPassword);
    await page.locator('[data-testid="confirm-restore-button"]').click();
    
    // Verify restoration success
    await expect(page.locator('[data-testid="restore-success"]')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('[data-testid="quantum-status-indicator"]')).toHaveAttribute('data-quantum-ready', 'true');
  });

  test('should validate quantum resistance against known attacks', async ({ page }) => {
    // Open security diagnostics
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    await page.locator('[data-testid="security-diagnostics-tab"]').click();
    
    // Run quantum resistance validation
    await page.locator('[data-testid="validate-quantum-resistance-button"]').click();
    
    // Wait for validation to complete
    await expect(page.locator('[data-testid="validation-in-progress"]')).toBeVisible();
    await expect(page.locator('[data-testid="validation-complete"]')).toBeVisible({ timeout: 15000 });
    
    // Check validation results
    const resistanceScore = await page.locator('[data-testid="quantum-resistance-score"]').textContent();
    expect(parseFloat(resistanceScore || '0')).toBeGreaterThanOrEqual(0.95); // 95% or higher
    
    // Verify specific algorithm resistances
    const algorithmResults = await page.locator('[data-testid="algorithm-resistance"]').allTextContents();
    for (const result of algorithmResults) {
      expect(result).toContain('PASS');
    }
    
    // Check for known vulnerabilities
    const vulnerabilities = await page.locator('[data-testid="vulnerability-count"]').textContent();
    expect(parseInt(vulnerabilities || '0')).toBe(0);
  });

  test('should handle multi-device quantum synchronization', async ({ context }) => {
    // Create a second page (simulating another device)
    const secondPage = await context.newPage();
    await secondPage.goto('/chat');
    await secondPage.waitForSelector('[data-testid="chat-container"]');
    
    // Send a message from first device
    const testMessage = 'Multi-device quantum sync test';
    await page.locator('[data-testid="message-input"]').fill(testMessage);
    await page.locator('[data-testid="send-button"]').click();
    
    // Wait for message to appear on first device
    await page.waitForSelector(`[data-testid="message-content"]:has-text("${testMessage}")`, { timeout: 5000 });
    
    // Verify message appears on second device with proper quantum decryption
    await secondPage.waitForSelector(`[data-testid="message-content"]:has-text("${testMessage}")`, { timeout: 10000 });
    
    // Check that both devices show quantum-safe status
    const device1QuantumStatus = await page.locator('[data-testid="quantum-status-indicator"]').getAttribute('data-quantum-ready');
    const device2QuantumStatus = await secondPage.locator('[data-testid="quantum-status-indicator"]').getAttribute('data-quantum-ready');
    
    expect(device1QuantumStatus).toBe('true');
    expect(device2QuantumStatus).toBe('true');
    
    // Verify key synchronization
    await page.locator('[data-testid="security-settings-button"]').click();
    await secondPage.locator('[data-testid="security-settings-button"]').click();
    
    await page.waitForSelector('[data-testid="security-modal"]');
    await secondPage.waitForSelector('[data-testid="security-modal"]');
    
    const device1KeyId = await page.locator('[data-testid="current-key-id"]').textContent();
    const device2KeyId = await secondPage.locator('[data-testid="current-key-id"]').textContent();
    
    expect(device1KeyId).toBe(device2KeyId);
    
    await secondPage.close();
  });

  test('should export comprehensive security audit report', async ({ page }) => {
    // Open security settings
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    // Navigate to audit tab
    await page.locator('[data-testid="security-audit-tab"]').click();
    
    // Generate security report
    await page.locator('[data-testid="generate-audit-report-button"]').click();
    
    // Wait for report generation
    await expect(page.locator('[data-testid="report-generating"]')).toBeVisible();
    await expect(page.locator('[data-testid="report-ready"]')).toBeVisible({ timeout: 15000 });
    
    // Download and verify report
    const downloadPromise = page.waitForEvent('download');
    await page.locator('[data-testid="download-audit-report-button"]').click();
    const download = await downloadPromise;
    
    expect(download.suggestedFilename()).toMatch(/quantum-security-audit-.*\.json/);
    
    // Verify report content (if possible to read)
    const reportPath = await download.path();
    expect(reportPath).toBeTruthy();
  });

  test('should maintain performance with quantum algorithms', async ({ page }) => {
    const messageCount = 50;
    const messages = Array.from({ length: messageCount }, (_, i) => `Performance test message ${i + 1}`);
    
    // Measure encryption/send time
    const startTime = Date.now();
    
    for (const message of messages) {
      await page.locator('[data-testid="message-input"]').fill(message);
      await page.locator('[data-testid="send-button"]').click();
      
      // Don't wait for each message to appear to test throughput
      if (message === messages[messages.length - 1]) {
        await page.waitForSelector(`[data-testid="message-content"]:has-text("${message}")`, { timeout: 10000 });
      }
    }
    
    const endTime = Date.now();
    const totalTime = endTime - startTime;
    const averageTimePerMessage = totalTime / messageCount;
    
    // Should be able to encrypt/send at least 2 messages per second
    expect(averageTimePerMessage).toBeLessThan(500);
    
    // Verify all messages were properly encrypted and decrypted
    for (let i = 0; i < Math.min(5, messageCount); i++) {
      const messageElement = page.locator(`[data-testid="message-content"]:has-text("${messages[i]}")`).first();
      const quantumSafe = await messageElement.getAttribute('data-quantum-safe');
      expect(quantumSafe).toBe('true');
    }
  });

  test('should handle conversation key rotation seamlessly', async ({ page }) => {
    // Send initial message
    await page.locator('[data-testid="message-input"]').fill('Message before key rotation');
    await page.locator('[data-testid="send-button"]').click();
    await page.waitForSelector('[data-testid="message-content"]:has-text("Message before key rotation")');
    
    // Force key rotation
    await page.locator('[data-testid="security-settings-button"]').click();
    await page.waitForSelector('[data-testid="security-modal"]');
    
    await page.locator('[data-testid="rotate-conversation-keys-button"]').click();
    await page.locator('[data-testid="confirm-rotation-button"]').click();
    
    // Wait for rotation to complete
    await expect(page.locator('[data-testid="key-rotation-success"]')).toBeVisible({ timeout: 10000 });
    await page.locator('[data-testid="close-modal-button"]').click();
    
    // Send message after rotation
    await page.locator('[data-testid="message-input"]').fill('Message after key rotation');
    await page.locator('[data-testid="send-button"]').click();
    await page.waitForSelector('[data-testid="message-content"]:has-text("Message after key rotation")');
    
    // Verify both messages are visible (old messages should still be decryptable)
    await expect(page.locator('[data-testid="message-content"]:has-text("Message before key rotation")')).toBeVisible();
    await expect(page.locator('[data-testid="message-content"]:has-text("Message after key rotation")')).toBeVisible();
    
    // Verify they use different key versions
    const beforeRotation = page.locator('[data-testid="message-content"]:has-text("Message before key rotation")').first();
    const afterRotation = page.locator('[data-testid="message-content"]:has-text("Message after key rotation")').first();
    
    const keyVersion1 = await beforeRotation.getAttribute('data-key-version');
    const keyVersion2 = await afterRotation.getAttribute('data-key-version');
    
    expect(keyVersion1).not.toBe(keyVersion2);
  });
});