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
        status.textContent?.includes('End-to-end encrypted')
      );
    }, { timeout: 30000 });
  }

  async createDirectConversation(userEmail: string) {
    await this.page.click('[data-testid="new-chat-button"]');
    await this.page.fill('input[placeholder*="email"]', userEmail);
    await this.page.click('button[type="submit"]');

    // Wait for conversation to be created and E2EE to be set up
    await this.page.waitForSelector('[data-testid="chat-messages"]');
    await this.waitForE2EEInitialization();
  }

  async sendMessage(message: string) {
    const messageInput = this.page.locator('[data-testid="message-input"]');
    await messageInput.fill(message);
    await this.page.click('[data-testid="send-button"]');

    // Wait for message to appear in chat
    await this.page.waitForSelector(`text="${message}"`, { timeout: 5000 });
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
    const healthCheck = await user1Helper.performHealthCheck();
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
});
