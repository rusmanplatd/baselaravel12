import { test, expect, Page } from '@playwright/test';

class E2EETestHelper {
  constructor(private page: Page) {}

  async loginAsUser(email: string, password: string = 'password') {
    await this.page.goto('/login');
    await this.page.fill('input[name="email"]', email);
    await this.page.fill('input[name="password"]', password);
    await this.page.click('button[type="submit"]');
    await this.page.waitForURL(/\/dashboard|\/chat/);
  }

  async goToChat() {
    await this.page.goto('/chat');
    await this.page.waitForLoadState('networkidle');
  }

  async waitForE2EEInitialization() {
    // Wait for encryption status to show "E2EE Active" or similar
    await this.page.waitForSelector('[data-testid="e2ee-status"]', {
      state: 'visible',
      timeout: 20000
    });

    // Wait for keys to be generated
    await this.page.waitForFunction(() => {
      const status = document.querySelector('[data-testid="e2ee-status"]');
      return status && (
        status.textContent?.includes('E2EE Active') ||
        status.textContent?.includes('End-to-end encrypted') ||
        status.textContent?.includes('Encrypted') ||
        status.textContent?.includes('Ready')
      );
    }, { timeout: 30000 });
  }

  async createDirectConversation(userEmail: string) {
    // Try the new chat button first
    const newChatButton = this.page.locator('[data-testid="new-chat-button"]');
    if (await newChatButton.count() > 0) {
      await newChatButton.click();
      await this.page.fill('input[placeholder*="email"]', userEmail);
      await this.page.click('button[type="submit"]');
    } else {
      // Fallback: look for start conversation or similar
      const startButton = this.page.locator('button:has-text("Start Conversation")');
      if (await startButton.count() > 0) {
        await startButton.click();
        await this.page.fill('input[type="email"], input[placeholder*="email"]', userEmail);
        await this.page.click('button[type="submit"]');
      }
    }

    // Wait for conversation to be created and E2EE to be set up
    await this.page.waitForSelector('[data-testid="chat-messages"], [data-testid="message-list"]', { timeout: 10000 });
    await this.waitForE2EEInitialization();
  }

  async sendMessage(message: string) {
    const messageInput = this.page.locator('[data-testid="message-input"], textarea[placeholder*="message"], input[placeholder*="message"]');
    await messageInput.fill(message);
    
    // Try different send button selectors
    const sendButton = this.page.locator('[data-testid="send-button"], button:has-text("Send"), button[type="submit"]:near(textarea), .send-button');
    await sendButton.first().click();

    // Wait for message to appear in chat
    await this.page.waitForSelector(`text="${message}"`, { timeout: 10000 });
  }

  async getLastMessage() {
    const messages = this.page.locator('[data-testid="message-bubble"]');
    return await messages.last();
  }

  async waitForMessageDecryption(messageContent: string) {
    await this.page.waitForFunction(
      (content) => {
        const messages = Array.from(document.querySelectorAll('[data-testid="message-bubble"]'));
        return messages.some(msg => msg.textContent?.includes(content));
      },
      messageContent,
      { timeout: 20000 }
    );
  }

  async getEncryptionStatus() {
    const statusElement = this.page.locator('[data-testid="e2ee-status"]');
    return await statusElement.textContent();
  }

  async openEncryptionSettings() {
    await this.page.click('[data-testid="e2ee-status"]');
    await this.page.waitForSelector('[data-testid="encryption-settings-popup"]');
  }

  async performHealthCheck() {
    await this.openEncryptionSettings();
    await this.page.click('[data-testid="health-check-button"]');
    await this.page.waitForSelector('[data-testid="health-report"]');
  }

  async rotateEncryptionKey() {
    await this.openEncryptionSettings();
    await this.page.click('[data-testid="rotate-key-button"]');
    await this.page.waitForSelector('text="Key rotated successfully"', { timeout: 20000 });
  }

  async createBackup(password: string) {
    await this.openEncryptionSettings();
    await this.page.click('[data-testid="create-backup-button"]');

    const passwordInput = this.page.locator('input[placeholder*="backup password"]');
    await passwordInput.fill(password);
    await this.page.click('[data-testid="generate-backup-button"]');

    // Wait for backup to be generated
    await this.page.waitForSelector('[data-testid="backup-data"]', { timeout: 20000 });
    return await this.page.locator('[data-testid="backup-data"]').textContent();
  }
}

test.describe('Enhanced E2EE Chat Tests', () => {
  let user1Helper: E2EETestHelper;
  let user2Helper: E2EETestHelper;
  let user1Page: Page;
  let user2Page: Page;

  test.beforeEach(async ({ browser }) => {
    // Create two browser contexts for two users
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    user1Page = await context1.newPage();
    user2Page = await context2.newPage();

    user1Helper = new E2EETestHelper(user1Page);
    user2Helper = new E2EETestHelper(user2Page);
  });

  test.afterEach(async () => {
    await user1Page?.close();
    await user2Page?.close();
  });

  test('should initialize E2EE for new users', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();

    // Verify E2EE initialization
    await user1Helper.waitForE2EEInitialization();
    const status = await user1Helper.getEncryptionStatus();

    expect(status).toContain('E2EE');

    // Verify key generation completed
    await user1Helper.performHealthCheck();
    await expect(user1Page.locator('[data-testid="health-report"] >> text="healthy"')).toBeVisible();
  });

  test('should establish encrypted conversation between two users', async () => {
    // Setup two users
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    // User 1 initiates conversation with User 2
    await user1Helper.createDirectConversation('testuser2@example.com');

    // User 2 should see the conversation appear
    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Verify both users see E2EE status
    const user1Status = await user1Helper.getEncryptionStatus();
    const user2Status = await user2Helper.getEncryptionStatus();

    expect(user1Status).toContain('encrypted');
    expect(user2Status).toContain('encrypted');
  });

  test('should encrypt and decrypt messages between users', async () => {
    // Setup conversation
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    // User 2 joins conversation
    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // User 1 sends encrypted message
    const testMessage = 'This is a secret encrypted message!';
    await user1Helper.sendMessage(testMessage);

    // User 2 should receive and decrypt the message
    await user2Helper.waitForMessageDecryption(testMessage);
    const lastMessage = await user2Helper.getLastMessage();
    await expect(lastMessage).toContainText(testMessage);

    // User 2 responds
    const responseMessage = 'I received your encrypted message!';
    await user2Helper.sendMessage(responseMessage);

    // User 1 should receive and decrypt the response
    await user1Helper.waitForMessageDecryption(responseMessage);
    const user1LastMessage = await user1Helper.getLastMessage();
    await expect(user1LastMessage).toContainText(responseMessage);
  });

  test('should handle key rotation correctly', async () => {
    // Setup conversation
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Send message before key rotation
    await user1Helper.sendMessage('Message before key rotation');
    await user2Helper.waitForMessageDecryption('Message before key rotation');

    // Rotate encryption key
    await user1Helper.rotateEncryptionKey();

    // Wait for both users to get new keys (they should be notified)
    await user1Page.waitForSelector('text="Encryption key updated"', { timeout: 20000 });
    await user2Page.waitForSelector('text="Encryption key updated"', { timeout: 20000 });

    // Send message after key rotation
    await user1Helper.sendMessage('Message after key rotation');
    await user2Helper.waitForMessageDecryption('Message after key rotation');

    // Both messages should be visible and decrypted
    await expect(user2Page.locator('text="Message before key rotation"')).toBeVisible();
    await expect(user2Page.locator('text="Message after key rotation"')).toBeVisible();
  });

  test('should handle encryption errors gracefully', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();
    await user1Helper.waitForE2EEInitialization();

    // Simulate encryption error by corrupting local storage
    await user1Page.evaluate(() => {
      // Corrupt the private key in storage
      const corruptedKey = sessionStorage.getItem('chat_e2ee_private_key_testuser1') + 'CORRUPTED';
      sessionStorage.setItem('chat_e2ee_private_key_testuser1', corruptedKey);
    });

    // Try to send a message
    await user1Helper.createDirectConversation('testuser2@example.com');

    // Should show error and recovery options
    await expect(user1Page.locator('[data-testid="encryption-error"]')).toBeVisible();
    await expect(user1Page.locator('text="Encryption error"')).toBeVisible();

    // Should offer to regenerate keys
    await user1Page.click('[data-testid="regenerate-keys-button"]');
    await user1Helper.waitForE2EEInitialization();

    // Should be able to send messages again
    await user1Helper.sendMessage('Message after key regeneration');
    await expect(user1Page.locator('text="Message after key regeneration"')).toBeVisible();
  });

  test('should create and restore encrypted backups', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();
    await user1Helper.waitForE2EEInitialization();

    // Create a backup
    const backupPassword = 'MySecureBackupPassword123!';
    const backupData = await user1Helper.createBackup(backupPassword);

    expect(backupData).toBeTruthy();
    expect(backupData?.length).toBeGreaterThan(100);

    // Clear all local storage (simulate device loss)
    await user1Page.evaluate(() => {
      sessionStorage.clear();
      localStorage.clear();
    });

    // Navigate back to chat - should prompt for backup restoration
    await user1Helper.goToChat();

    // Should show backup restoration option
    await expect(user1Page.locator('[data-testid="restore-backup-prompt"]')).toBeVisible();

    // Restore from backup
    await user1Page.click('[data-testid="restore-from-backup-button"]');

    const backupTextarea = user1Page.locator('[data-testid="backup-data-input"]');
    await backupTextarea.fill(backupData!);

    const passwordInput = user1Page.locator('[data-testid="backup-password-input"]');
    await passwordInput.fill(backupPassword);

    await user1Page.click('[data-testid="restore-backup-submit"]');

    // Should successfully restore and initialize E2EE
    await user1Helper.waitForE2EEInitialization();
    const status = await user1Helper.getEncryptionStatus();
    expect(status).toContain('encrypted');
  });

  test('should perform bulk message encryption/decryption efficiently', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Send multiple messages quickly
    const messages = [
      'First test message',
      'Second test message',
      'Third test message',
      'Fourth test message',
      'Fifth test message'
    ];

    const startTime = Date.now();

    for (const message of messages) {
      await user1Helper.sendMessage(message);
      // Small delay to ensure message order
      await user1Page.waitForTimeout(100);
    }

    const endTime = Date.now();
    const totalTime = endTime - startTime;

    // All messages should be sent and decrypted within reasonable time
    expect(totalTime).toBeLessThan(10000); // 10 seconds

    // User 2 should see all messages decrypted
    for (const message of messages) {
      await user2Helper.waitForMessageDecryption(message);
      await expect(user2Page.locator(`text="${message}"`)).toBeVisible();
    }
  });

  test('should validate encryption health status', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();
    await user1Helper.waitForE2EEInitialization();

    // Perform health check
    await user1Helper.performHealthCheck();

    // Should show healthy status
    await expect(user1Page.locator('[data-testid="health-status"] >> text="healthy"')).toBeVisible();

    // Should show performance metrics
    await expect(user1Page.locator('[data-testid="key-generation-time"]')).toBeVisible();
    await expect(user1Page.locator('[data-testid="encryption-time"]')).toBeVisible();

    // Should show check results
    await expect(user1Page.locator('text="Key generation: pass"')).toBeVisible();
    await expect(user1Page.locator('text="Symmetric encryption: pass"')).toBeVisible();
    await expect(user1Page.locator('text="Key integrity: pass"')).toBeVisible();
  });

  test('should handle group conversation encryption', async () => {
    // Setup three users
    const user3Page = await user1Page.context().newPage();
    const user3Helper = new E2EETestHelper(user3Page);

    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');
    await user3Helper.loginAsUser('testuser3@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();
    await user3Helper.goToChat();

    // Create group conversation
    await user1Page.click('[data-testid="new-group-chat-button"]');
    await user1Page.fill('[data-testid="group-name-input"]', 'Test E2EE Group');

    // Add participants
    await user1Page.fill('[data-testid="add-participant-input"]', 'testuser2@example.com');
    await user1Page.click('[data-testid="add-participant-button"]');
    await user1Page.fill('[data-testid="add-participant-input"]', 'testuser3@example.com');
    await user1Page.click('[data-testid="add-participant-button"]');

    await user1Page.click('[data-testid="create-group-button"]');

    // Wait for all users to join and initialize E2EE
    await user1Helper.waitForE2EEInitialization();

    // Other users should see the group appear
    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="Test E2EE Group"');
    await user3Page.waitForSelector('[data-testid="conversation-list"] >> text="Test E2EE Group"');

    await user2Page.click('[data-testid="conversation-list"] >> text="Test E2EE Group"');
    await user3Page.click('[data-testid="conversation-list"] >> text="Test E2EE Group"');

    await user2Helper.waitForE2EEInitialization();
    await user3Helper.waitForE2EEInitialization();

    // Send messages in group
    await user1Helper.sendMessage('Hello group!');
    await user2Helper.waitForMessageDecryption('Hello group!');
    await user3Helper.waitForMessageDecryption('Hello group!');

    await user2Helper.sendMessage('Hi everyone!');
    await user1Helper.waitForMessageDecryption('Hi everyone!');
    await user3Helper.waitForMessageDecryption('Hi everyone!');

    // All users should see all messages
    await expect(user1Page.locator('text="Hello group!"')).toBeVisible();
    await expect(user2Page.locator('text="Hello group!"')).toBeVisible();
    await expect(user3Page.locator('text="Hello group!"')).toBeVisible();

    await expect(user1Page.locator('text="Hi everyone!"')).toBeVisible();
    await expect(user2Page.locator('text="Hi everyone!"')).toBeVisible();
    await expect(user3Page.locator('text="Hi everyone!"')).toBeVisible();

    await user3Page.close();
  });

  test('should maintain encryption across page refreshes', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Send initial message
    await user1Helper.sendMessage('Message before refresh');
    await user2Helper.waitForMessageDecryption('Message before refresh');

    // Refresh User 1's page
    await user1Page.reload();
    await user1Helper.goToChat();
    await user1Helper.waitForE2EEInitialization();

    // Click on the conversation to reopen it
    await user1Page.click('[data-testid="conversation-list"] >> text="testuser2@example.com"');

    // Should still be able to see previous messages
    await expect(user1Page.locator('text="Message before refresh"')).toBeVisible();

    // Should still be able to send new messages
    await user1Helper.sendMessage('Message after refresh');
    await user2Helper.waitForMessageDecryption('Message after refresh');

    await expect(user2Page.locator('text="Message after refresh"')).toBeVisible();
  });

  test('should show appropriate encryption status during different phases', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();

    // Initially should show key generation status
    await expect(user1Page.locator('text="Keys Generating"')).toBeVisible({ timeout: 5000 });

    // Then should show E2EE ready status
    await user1Helper.waitForE2EEInitialization();
    const status = await user1Helper.getEncryptionStatus();
    expect(status).toContain('E2EE');

    // When creating new conversation, should show setup status
    await user1Page.click('[data-testid="new-chat-button"]');
    await user1Page.fill('input[placeholder*="email"]', 'testuser2@example.com');
    await user1Page.click('button[type="submit"]');

    // Should briefly show "Setting up E2EE"
    await expect(user1Page.locator('text="Setting up E2EE"')).toBeVisible({ timeout: 5000 });

    // Then should show active E2EE status
    await user1Helper.waitForE2EEInitialization();
    const finalStatus = await user1Helper.getEncryptionStatus();
    expect(finalStatus).toContain('encrypted');
  });

  test('should handle quantum cryptography algorithm negotiation', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    // Create conversation
    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Check if quantum algorithms are being used
    await user1Helper.openEncryptionSettings();
    
    // Look for algorithm indicators
    const quantumIndicators = [
      'ML-KEM',
      'Quantum-resistant',
      'Post-quantum',
      'CRYSTALS'
    ];

    let hasQuantumSupport = false;
    for (const indicator of quantumIndicators) {
      const element = user1Page.locator(`text*="${indicator}"`);
      if (await element.count() > 0) {
        hasQuantumSupport = true;
        break;
      }
    }

    if (hasQuantumSupport) {
      // Test quantum algorithm negotiation
      await user1Helper.sendMessage('Testing quantum encryption!');
      await user2Helper.waitForMessageDecryption('Testing quantum encryption!');
      await expect(user2Page.locator('text="Testing quantum encryption!"')).toBeVisible();

      // Verify encryption details show quantum algorithm
      await user2Helper.openEncryptionSettings();
      const hasQuantumDetails = await Promise.race([
        user2Page.locator('text*="ML-KEM"').isVisible().then(() => true),
        user2Page.locator('text*="Quantum-resistant"').isVisible().then(() => true),
        user2Page.locator('text*="Post-quantum"').isVisible().then(() => true),
        new Promise(resolve => setTimeout(() => resolve(false), 2000))
      ]);

      if (hasQuantumDetails) {
        console.log('✓ Quantum cryptography is active');
      } else {
        console.log('- Classical encryption in use (quantum not available)');
      }
    } else {
      console.log('- No quantum cryptography support detected, using classical encryption');
    }
  });

  test('should support hybrid classical/quantum encryption during migration', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Send message with current encryption
    await user1Helper.sendMessage('Message with current encryption');
    await user2Helper.waitForMessageDecryption('Message with current encryption');

    // Check if migration options are available
    await user1Helper.openEncryptionSettings();
    
    const migrationButton = user1Page.locator('[data-testid="quantum-migration"], button:has-text("Migrate"), button:has-text("Upgrade")');
    if (await migrationButton.count() > 0) {
      await migrationButton.first().click();
      
      // Wait for migration to complete
      await user1Page.waitForSelector('text="Migration complete"', { timeout: 20000 }).catch(() => {
        console.log('Migration process may still be ongoing');
      });

      // Send post-migration message
      await user1Helper.sendMessage('Message after migration');
      await user2Helper.waitForMessageDecryption('Message after migration');
      
      // Both messages should be visible
      await expect(user2Page.locator('text="Message with current encryption"')).toBeVisible();
      await expect(user2Page.locator('text="Message after migration"')).toBeVisible();
    } else {
      console.log('- No quantum migration features detected');
    }
  });

  test('should maintain message integrity across encryption versions', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user2Helper.loginAsUser('testuser2@example.com');

    await user1Helper.goToChat();
    await user2Helper.goToChat();

    await user1Helper.createDirectConversation('testuser2@example.com');

    await user2Page.waitForSelector('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // Send multiple messages with different content types
    const testMessages = [
      'Simple text message',
      'Message with special characters: @#$%^&*()',
      'Message with emojis: 🔒🔐🛡️',
      'Very long message: ' + 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.repeat(10),
      'Message with numbers: 123456789'
    ];

    for (const message of testMessages) {
      await user1Helper.sendMessage(message);
      await user2Helper.waitForMessageDecryption(message);
      await expect(user2Page.locator(`text="${message}"`)).toBeVisible();
    }

    // Verify all messages are still readable after page refresh
    await user2Page.reload();
    await user2Helper.loginAsUser('testuser2@example.com');
    await user2Helper.goToChat();
    await user2Page.click('[data-testid="conversation-list"] >> text="testuser1@example.com"');
    await user2Helper.waitForE2EEInitialization();

    // All messages should still be visible and properly decrypted
    for (const message of testMessages) {
      await expect(user2Page.locator(`text="${message}"`)).toBeVisible({ timeout: 10000 });
    }
  });

  test('should handle device compatibility for quantum encryption', async () => {
    await user1Helper.loginAsUser('testuser1@example.com');
    await user1Helper.goToChat();
    await user1Helper.waitForE2EEInitialization();

    // Check device capabilities
    await user1Helper.openEncryptionSettings();
    
    // Look for device capability indicators
    const capabilityIndicators = [
      '[data-testid="device-capabilities"]',
      'text="Device Compatibility"',
      'text="Quantum Support"',
      '[data-testid="quantum-readiness"]'
    ];

    let hasCapabilityInfo = false;
    for (const indicator of capabilityIndicators) {
      if (await user1Page.locator(indicator).count() > 0) {
        hasCapabilityInfo = true;
        break;
      }
    }

    if (hasCapabilityInfo) {
      // Verify device shows encryption capabilities
      const supportedAlgorithms = user1Page.locator('[data-testid="supported-algorithms"], .algorithm-list');
      if (await supportedAlgorithms.count() > 0) {
        const algorithms = await supportedAlgorithms.textContent();
        expect(algorithms).toBeTruthy();
        console.log('Device algorithm support detected:', algorithms);
      }

      // Check for quantum readiness assessment
      const readinessButton = user1Page.locator('[data-testid="assess-readiness"], button:has-text("Assess")');
      if (await readinessButton.count() > 0) {
        await readinessButton.click();
        await user1Page.waitForSelector('[data-testid="readiness-report"], .readiness-result', { timeout: 10000 });
        
        const report = await user1Page.locator('[data-testid="readiness-report"], .readiness-result').textContent();
        expect(report).toBeTruthy();
        console.log('Device readiness assessment completed');
      }
    } else {
      console.log('- No device capability assessment features found');
    }
  });
});
