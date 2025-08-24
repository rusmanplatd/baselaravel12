import { test, expect, Page, BrowserContext } from '@playwright/test';
import { faker } from '@faker-js/faker';

// Test data
const testUsers = {
  alice: {
    email: 'alice@example.com',
    password: 'password123',
    name: 'Alice Smith'
  },
  bob: {
    email: 'bob@example.com', 
    password: 'password123',
    name: 'Bob Johnson'
  }
};

class E2EETestHelper {
  constructor(private page: Page) {}

  async login(user: typeof testUsers.alice) {
    await this.page.goto('/login');
    await this.page.fill('input[name="email"]', user.email);
    await this.page.fill('input[name="password"]', user.password);
    await this.page.click('button[type="submit"]');
    await this.page.waitForURL('/dashboard');
  }

  async setupDevice(deviceName: string, securityLevel: 'low' | 'medium' | 'high' | 'maximum' = 'high') {
    // Navigate to device setup
    await this.page.click('[data-testid="user-menu"]');
    await this.page.click('[data-testid="security-settings"]');
    await this.page.click('[data-testid="device-setup"]');

    // Initialize device
    await this.page.fill('[data-testid="device-name"]', deviceName);
    await this.page.selectOption('[data-testid="security-level"]', securityLevel);
    await this.page.click('[data-testid="initialize-device"]');

    // Wait for device initialization
    await expect(this.page.locator('[data-testid="device-status"]')).toContainText('Initialized');
  }

  async verifyDevice(method: 'security_key' | 'passkey' | 'qr_code' = 'security_key') {
    await this.page.click('[data-testid="verify-device"]');
    await this.page.selectOption('[data-testid="verification-method"]', method);
    await this.page.click('[data-testid="start-verification"]');

    // Simulate verification process
    if (method === 'security_key') {
      // Mock security key response
      await this.page.evaluate(() => {
        window.mockSecurityKeyResponse = {
          signature: 'mock-signature-12345',
          challenge: 'mock-challenge'
        };
      });
      await this.page.click('[data-testid="complete-verification"]');
    }

    await expect(this.page.locator('[data-testid="device-status"]')).toContainText('Trusted');
  }

  async createConversation(participantEmail: string) {
    await this.page.click('[data-testid="new-conversation"]');
    await this.page.fill('[data-testid="participant-email"]', participantEmail);
    await this.page.click('[data-testid="enable-encryption"]');
    await this.page.click('[data-testid="create-conversation"]');
    
    // Wait for conversation to be created
    await expect(this.page.locator('[data-testid="conversation-header"]')).toBeVisible();
  }

  async sendMessage(message: string) {
    await this.page.fill('[data-testid="message-input"]', message);
    await this.page.click('[data-testid="send-message"]');
    
    // Wait for message to be sent and encrypted
    await expect(
      this.page.locator(`[data-testid="message"][data-content="${message}"]`)
    ).toBeVisible();
  }

  async verifyMessageEncrypted(message: string) {
    // Check that the message is marked as encrypted
    const messageElement = this.page.locator(`[data-testid="message"][data-content="${message}"]`);
    await expect(messageElement.locator('[data-testid="encryption-indicator"]')).toBeVisible();
  }

  async createKeyBackup(passphrase: string) {
    await this.page.click('[data-testid="security-menu"]');
    await this.page.click('[data-testid="key-backup"]');
    await this.page.click('[data-testid="create-backup"]');
    await this.page.fill('[data-testid="backup-passphrase"]', passphrase);
    await this.page.fill('[data-testid="confirm-passphrase"]', passphrase);
    await this.page.click('[data-testid="generate-backup"]');

    // Wait for backup creation
    await expect(this.page.locator('[data-testid="backup-success"]')).toBeVisible();
    
    // Get backup data
    const backupData = await this.page.locator('[data-testid="backup-data"]').textContent();
    return backupData;
  }

  async restoreKeyBackup(backupData: string, passphrase: string) {
    await this.page.click('[data-testid="security-menu"]');
    await this.page.click('[data-testid="key-backup"]');
    await this.page.click('[data-testid="restore-backup"]');
    await this.page.fill('[data-testid="backup-data"]', backupData);
    await this.page.fill('[data-testid="backup-passphrase"]', passphrase);
    await this.page.click('[data-testid="restore-keys"]');

    await expect(this.page.locator('[data-testid="restore-success"]')).toBeVisible();
  }

  async checkSecurityMonitor() {
    await this.page.click('[data-testid="security-menu"]');
    await this.page.click('[data-testid="security-monitor"]');

    // Verify security monitor components
    await expect(this.page.locator('[data-testid="security-score"]')).toBeVisible();
    await expect(this.page.locator('[data-testid="device-metrics"]')).toBeVisible();
    await expect(this.page.locator('[data-testid="security-alerts"]')).toBeVisible();
  }

  async rotateKeys(conversationId: string) {
    await this.page.click('[data-testid="conversation-menu"]');
    await this.page.click('[data-testid="security-settings"]');
    await this.page.click('[data-testid="rotate-keys"]');
    await this.page.click('[data-testid="confirm-rotation"]');

    await expect(this.page.locator('[data-testid="rotation-success"]')).toBeVisible();
  }

  async syncMessages() {
    await this.page.click('[data-testid="sync-menu"]');
    await this.page.click('[data-testid="sync-messages"]');
    
    // Wait for sync completion
    await expect(this.page.locator('[data-testid="sync-status"]')).toContainText('Synced');
  }
}

test.describe('Advanced Multi-Device E2EE', () => {
  let aliceContext: BrowserContext;
  let bobContext: BrowserContext;
  let alicePage: Page;
  let bobPage: Page;
  let aliceHelper: E2EETestHelper;
  let bobHelper: E2EETestHelper;

  test.beforeAll(async ({ browser }) => {
    // Create separate browser contexts for different users
    aliceContext = await browser.newContext();
    bobContext = await browser.newContext();
    
    alicePage = await aliceContext.newPage();
    bobPage = await bobContext.newPage();
    
    aliceHelper = new E2EETestHelper(alicePage);
    bobHelper = new E2EETestHelper(bobPage);

    // Set up test users
    await aliceHelper.login(testUsers.alice);
    await bobHelper.login(testUsers.bob);
  });

  test.afterAll(async () => {
    await aliceContext.close();
    await bobContext.close();
  });

  test('should support device initialization and verification', async () => {
    // Alice initializes her device
    await aliceHelper.setupDevice('Alice Desktop', 'high');
    await aliceHelper.verifyDevice('security_key');

    // Bob initializes his device
    await bobHelper.setupDevice('Bob Mobile', 'medium');
    await bobHelper.verifyDevice('passkey');

    // Verify both devices are properly set up
    await alicePage.goto('/settings/security');
    await expect(alicePage.locator('[data-testid="device-status"]')).toContainText('Trusted');
    
    await bobPage.goto('/settings/security');
    await expect(bobPage.locator('[data-testid="device-status"]')).toContainText('Trusted');
  });

  test('should create encrypted conversation between devices', async () => {
    // Alice creates encrypted conversation with Bob
    await aliceHelper.createConversation(testUsers.bob.email);
    
    // Bob should receive conversation invitation
    await bobPage.reload();
    await expect(bobPage.locator('[data-testid="conversation-invitation"]')).toBeVisible();
    await bobPage.click('[data-testid="accept-invitation"]');

    // Both users should see the encrypted conversation
    await expect(alicePage.locator('[data-testid="encryption-enabled"]')).toBeVisible();
    await expect(bobPage.locator('[data-testid="encryption-enabled"]')).toBeVisible();
  });

  test('should send and receive encrypted messages', async () => {
    const message = `Test encrypted message ${Date.now()}`;
    
    // Alice sends encrypted message
    await aliceHelper.sendMessage(message);
    await aliceHelper.verifyMessageEncrypted(message);

    // Bob should receive and decrypt the message
    await bobPage.waitForTimeout(2000); // Wait for real-time sync
    await expect(
      bobPage.locator(`[data-testid="message"][data-content="${message}"]`)
    ).toBeVisible();
    
    // Verify message shows as encrypted for Bob too
    const messageElement = bobPage.locator(`[data-testid="message"][data-content="${message}"]`);
    await expect(messageElement.locator('[data-testid="encryption-indicator"]')).toBeVisible();
  });

  test('should support key backup and restore', async () => {
    const passphrase = 'super-secure-passphrase-123';
    
    // Alice creates key backup
    const backupData = await aliceHelper.createKeyBackup(passphrase);
    expect(backupData).toBeTruthy();

    // Simulate device loss - clear Alice's device data
    await alicePage.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });

    // Alice restores from backup
    await aliceHelper.restoreKeyBackup(backupData!, passphrase);

    // Verify Alice can still read encrypted messages
    await alicePage.goto('/conversations');
    const previousMessage = await alicePage.locator('[data-testid="message"]').first().textContent();
    expect(previousMessage).toBeTruthy();
  });

  test('should rotate encryption keys', async () => {
    const conversationId = await alicePage.getAttribute('[data-testid="conversation"]', 'data-id');
    
    // Alice initiates key rotation
    await aliceHelper.rotateKeys(conversationId!);

    // Send message with new keys
    const newMessage = `Message after key rotation ${Date.now()}`;
    await aliceHelper.sendMessage(newMessage);

    // Bob should receive message encrypted with new keys
    await bobPage.waitForTimeout(2000);
    await expect(
      bobPage.locator(`[data-testid="message"][data-content="${newMessage}"]`)
    ).toBeVisible();

    // Verify key version indicator shows updated version
    await expect(alicePage.locator('[data-testid="key-version"]')).toContainText('v2');
  });

  test('should handle multi-device key sharing', async () => {
    // Alice adds a second device
    const aliceSecondContext = await alicePage.context().browser()!.newContext();
    const aliceSecondPage = await aliceSecondContext.newPage();
    const aliceSecondHelper = new E2EETestHelper(aliceSecondPage);

    await aliceSecondHelper.login(testUsers.alice);
    await aliceSecondHelper.setupDevice('Alice Tablet', 'medium');

    // First device should detect new device and offer key sharing
    await alicePage.reload();
    await expect(alicePage.locator('[data-testid="new-device-detected"]')).toBeVisible();
    await alicePage.click('[data-testid="share-keys"]');

    // Second device should receive key share
    await aliceSecondPage.reload();
    await expect(aliceSecondPage.locator('[data-testid="key-share-received"]')).toBeVisible();
    await aliceSecondPage.click('[data-testid="accept-keys"]');

    // Verify second device can access encrypted conversations
    await aliceSecondPage.goto('/conversations');
    await expect(aliceSecondPage.locator('[data-testid="encrypted-conversation"]')).toBeVisible();

    await aliceSecondContext.close();
  });

  test('should sync messages across devices', async () => {
    // Create second device context for Bob
    const bobSecondContext = await bobPage.context().browser()!.newContext();
    const bobSecondPage = await bobSecondContext.newPage();
    const bobSecondHelper = new E2EETestHelper(bobSecondPage);

    await bobSecondHelper.login(testUsers.bob);
    await bobSecondHelper.setupDevice('Bob Desktop', 'high');
    await bobSecondHelper.verifyDevice();

    // Send message from Bob's first device
    const syncMessage = `Cross-device sync test ${Date.now()}`;
    await bobHelper.sendMessage(syncMessage);

    // Message should appear on Bob's second device after sync
    await bobSecondHelper.syncMessages();
    await expect(
      bobSecondPage.locator(`[data-testid="message"][data-content="${syncMessage}"]`)
    ).toBeVisible();

    await bobSecondContext.close();
  });

  test('should monitor security and detect threats', async () => {
    // Check security monitor
    await aliceHelper.checkSecurityMonitor();

    // Simulate suspicious activity
    await alicePage.evaluate(() => {
      // Trigger multiple rapid authentication attempts
      for (let i = 0; i < 5; i++) {
        window.dispatchEvent(new CustomEvent('security-event', {
          detail: { type: 'failed_verification', severity: 'medium' }
        }));
      }
    });

    // Security monitor should show alerts
    await alicePage.reload();
    await aliceHelper.checkSecurityMonitor();
    
    await expect(alicePage.locator('[data-testid="security-alerts"]')).toContainText('Alert');
    await expect(alicePage.locator('[data-testid="security-score"]')).toBeVisible();
  });

  test('should handle device verification failure and lockout', async () => {
    // Create new device context
    const suspiciousContext = await alicePage.context().browser()!.newContext();
    const suspiciousPage = await suspiciousContext.newPage();
    const suspiciousHelper = new E2EETestHelper(suspiciousPage);

    await suspiciousHelper.login(testUsers.alice);
    await suspiciousHelper.setupDevice('Suspicious Device', 'low');

    // Simulate multiple failed verification attempts
    for (let i = 0; i < 6; i++) {
      await suspiciousPage.click('[data-testid="verify-device"]');
      await suspiciousPage.selectOption('[data-testid="verification-method"]', 'security_key');
      await suspiciousPage.click('[data-testid="start-verification"]');
      
      // Provide invalid verification
      await suspiciousPage.evaluate(() => {
        window.mockSecurityKeyResponse = {
          signature: 'invalid-signature',
          challenge: 'invalid-challenge'
        };
      });
      await suspiciousPage.click('[data-testid="complete-verification"]');
      
      // Should show failure
      await expect(suspiciousPage.locator('[data-testid="verification-failed"]')).toBeVisible();
    }

    // Device should be locked after too many failures
    await expect(suspiciousPage.locator('[data-testid="device-locked"]')).toBeVisible();
    await expect(suspiciousPage.locator('[data-testid="lockout-timer"]')).toBeVisible();

    await suspiciousContext.close();
  });

  test('should recover from message decryption failures', async () => {
    // Simulate corrupted message data
    await alicePage.evaluate(() => {
      // Corrupt stored encryption keys
      const corruptedKey = localStorage.getItem('e2ee_conversation_keys');
      if (corruptedKey) {
        localStorage.setItem('e2ee_conversation_keys', 'corrupted-data');
      }
    });

    // Try to send message - should trigger key recovery
    const recoveryMessage = `Recovery test ${Date.now()}`;
    await aliceHelper.sendMessage(recoveryMessage);

    // Should show recovery process
    await expect(alicePage.locator('[data-testid="key-recovery"]')).toBeVisible();
    await alicePage.click('[data-testid="recover-keys"]');

    // After recovery, should be able to send message
    await aliceHelper.sendMessage(recoveryMessage);
    await expect(
      alicePage.locator(`[data-testid="message"][data-content="${recoveryMessage}"]`)
    ).toBeVisible();
  });

  test('should export and import security reports', async () => {
    await aliceHelper.checkSecurityMonitor();
    
    // Export security report
    await alicePage.click('[data-testid="export-report"]');
    
    // Wait for download
    const downloadPromise = alicePage.waitForEvent('download');
    await alicePage.click('[data-testid="download-json"]');
    const download = await downloadPromise;
    
    expect(download.suggestedFilename()).toMatch(/security-report.*\.json$/);
  });

  test('should handle network disconnection gracefully', async () => {
    // Simulate network disconnection
    await alicePage.context().setOffline(true);

    const offlineMessage = `Offline message ${Date.now()}`;
    await aliceHelper.sendMessage(offlineMessage);

    // Should show offline indicator and queue message
    await expect(alicePage.locator('[data-testid="offline-indicator"]')).toBeVisible();
    await expect(alicePage.locator('[data-testid="message-queued"]')).toBeVisible();

    // Restore network connection
    await alicePage.context().setOffline(false);

    // Message should be sent when connection is restored
    await alicePage.waitForTimeout(3000);
    await expect(alicePage.locator('[data-testid="message-sent"]')).toBeVisible();
    await expect(
      alicePage.locator(`[data-testid="message"][data-content="${offlineMessage}"]`)
    ).toBeVisible();
  });

  test('should validate end-to-end encryption integrity', async () => {
    const integrityMessage = `Integrity test ${Date.now()}`;
    
    // Send message and capture network traffic
    const [response] = await Promise.all([
      alicePage.waitForResponse(resp => resp.url().includes('/api/v1/chat/messages')),
      aliceHelper.sendMessage(integrityMessage)
    ]);

    // Verify message was sent encrypted (server should not see plaintext)
    const responseData = await response.json();
    expect(responseData.message.content).not.toBe(integrityMessage);
    expect(responseData.message.encrypted).toBe(true);

    // Verify Bob receives and can decrypt the message
    await bobPage.waitForTimeout(2000);
    const decryptedMessage = await bobPage.locator(`[data-testid="message"][data-content="${integrityMessage}"]`);
    await expect(decryptedMessage).toBeVisible();

    // Verify encryption indicators
    await expect(decryptedMessage.locator('[data-testid="encryption-indicator"]')).toBeVisible();
    await expect(decryptedMessage.locator('[data-testid="integrity-verified"]')).toBeVisible();
  });
});

test.describe('Multi-Device E2EE Performance', () => {
  test('should handle high message throughput', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const helper = new E2EETestHelper(page);

    await helper.login(testUsers.alice);
    await helper.setupDevice('Performance Test Device', 'high');
    await helper.verifyDevice();

    // Send 100 messages rapidly
    const messages = Array.from({ length: 100 }, (_, i) => `Performance test message ${i}`);
    const startTime = Date.now();

    for (const message of messages) {
      await helper.sendMessage(message);
    }

    const endTime = Date.now();
    const totalTime = endTime - startTime;
    const avgTimePerMessage = totalTime / messages.length;

    console.log(`Sent ${messages.length} messages in ${totalTime}ms (avg: ${avgTimePerMessage}ms per message)`);
    
    // Should complete within reasonable time (less than 50ms per message)
    expect(avgTimePerMessage).toBeLessThan(50);

    await context.close();
  });

  test('should handle large conversation history', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const helper = new E2EETestHelper(page);

    await helper.login(testUsers.alice);
    await helper.setupDevice('History Test Device', 'medium');
    await helper.verifyDevice();

    // Create conversation with many messages
    await helper.createConversation(testUsers.bob.email);

    const startTime = Date.now();
    
    // Load conversation history
    await page.click('[data-testid="load-history"]');
    await page.waitForSelector('[data-testid="message"]');

    const loadTime = Date.now() - startTime;
    console.log(`Loaded conversation history in ${loadTime}ms`);

    // Should load within reasonable time
    expect(loadTime).toBeLessThan(5000);

    // Verify messages are properly decrypted
    const messageCount = await page.locator('[data-testid="message"]').count();
    expect(messageCount).toBeGreaterThan(0);

    await context.close();
  });
});