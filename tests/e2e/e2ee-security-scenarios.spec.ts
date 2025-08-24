import { test, expect, Page } from '@playwright/test';

class SecurityTestHelper {
  constructor(private page: Page) {}

  async loginAsUser(email: string, password: string = 'password123') {
    await this.page.goto('/login');
    await this.page.fill('input[name="email"]', email);
    await this.page.fill('input[name="password"]', password);
    await this.page.click('button[type="submit"]');
    await this.page.waitForURL('/dashboard');
  }

  async initializeSecureDevice(deviceName: string) {
    await this.page.goto('/settings/security');
    await this.page.click('[data-testid="add-device"]');
    await this.page.fill('[data-testid="device-name"]', deviceName);
    await this.page.selectOption('[data-testid="security-level"]', 'maximum');
    await this.page.check('[data-testid="enable-biometric"]');
    await this.page.click('[data-testid="initialize-device"]');
    
    await expect(this.page.locator('[data-testid="device-initialized"]')).toBeVisible();
  }

  async simulateSecurityThreat(threatType: string) {
    await this.page.evaluate((type) => {
      window.dispatchEvent(new CustomEvent('security-threat', {
        detail: { type, severity: 'high', timestamp: Date.now() }
      }));
    }, threatType);
  }

  async checkSecurityAlerts() {
    await this.page.goto('/security-monitor');
    await expect(this.page.locator('[data-testid="security-alerts"]')).toBeVisible();
  }

  async corruptStorageData() {
    await this.page.evaluate(() => {
      // Corrupt various E2EE data stores
      localStorage.setItem('e2ee_current_device', 'corrupted');
      localStorage.setItem('e2ee_conversation_keys', '{"invalid": "json"');
      localStorage.setItem('e2ee_pending_sync', '[invalid json]');
    });
  }

  async triggerFailedDecryption() {
    await this.page.evaluate(() => {
      // Mock a message with invalid encryption data
      window.mockInvalidMessage = {
        data: 'invalid-encrypted-data',
        iv: 'short-iv',
        hash: 'invalid-hash',
        keyVersion: 999
      };
    });
  }
}

test.describe('E2EE Security Scenarios', () => {
  let securityHelper: SecurityTestHelper;

  test.beforeEach(async ({ page }) => {
    securityHelper = new SecurityTestHelper(page);
  });

  test('should detect and handle storage tampering', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Secure Device');

    // Corrupt storage data
    await securityHelper.corruptStorageData();

    // Reload page to trigger storage validation
    await page.reload();

    // Should detect tampering and show security warning
    await expect(page.locator('[data-testid="security-warning"]')).toBeVisible();
    await expect(page.locator('[data-testid="storage-tamper-detected"]')).toContainText('Storage tampering detected');

    // Should offer recovery options
    await expect(page.locator('[data-testid="reinitialize-device"]')).toBeVisible();
    await expect(page.locator('[data-testid="restore-from-backup"]')).toBeVisible();
  });

  test('should handle encryption key compromise scenario', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Simulate key compromise detection
    await securityHelper.simulateSecurityThreat('key_compromise');

    await securityHelper.checkSecurityAlerts();

    // Should show critical security alert
    await expect(page.locator('[data-testid="critical-alert"]')).toBeVisible();
    await expect(page.locator('[data-testid="alert-message"]')).toContainText('encryption key may be compromised');

    // Should offer immediate remediation
    await expect(page.locator('[data-testid="emergency-key-rotation"]')).toBeVisible();
    await expect(page.locator('[data-testid="revoke-all-sessions"]')).toBeVisible();
  });

  test('should detect suspicious login patterns', async ({ page, browser }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Primary');

    // Simulate suspicious login from different location/device
    const suspiciousContext = await browser.newContext({
      geolocation: { latitude: 35.6762, longitude: 139.6503 }, // Tokyo
      locale: 'ja-JP'
    });
    const suspiciousPage = await suspiciousContext.newPage();
    
    // Rapid login attempts from suspicious location
    for (let i = 0; i < 3; i++) {
      await suspiciousPage.goto('/login');
      await suspiciousPage.fill('input[name="email"]', 'alice@example.com');
      await suspiciousPage.fill('input[name="password"]', 'wrongpassword');
      await suspiciousPage.click('button[type="submit"]');
      await suspiciousPage.waitForTimeout(500);
    }

    // Check security monitor on original device
    await securityHelper.checkSecurityAlerts();
    
    await expect(page.locator('[data-testid="suspicious-activity-alert"]')).toBeVisible();
    await expect(page.locator('[data-testid="unusual-location-alert"]')).toBeVisible();

    await suspiciousContext.close();
  });

  test('should handle message decryption failures gracefully', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Navigate to a conversation
    await page.goto('/conversations');
    await page.click('[data-testid="conversation"]');

    // Trigger failed decryption scenario
    await securityHelper.triggerFailedDecryption();

    // Try to decrypt the invalid message
    await page.evaluate(() => {
      window.dispatchEvent(new CustomEvent('process-message', {
        detail: window.mockInvalidMessage
      }));
    });

    // Should handle failure gracefully
    await expect(page.locator('[data-testid="decryption-failed"]')).toBeVisible();
    await expect(page.locator('[data-testid="message-unreadable"]')).toContainText('Could not decrypt this message');

    // Should offer recovery options
    await expect(page.locator('[data-testid="request-key-share"]')).toBeVisible();
    await expect(page.locator('[data-testid="report-issue"]')).toBeVisible();
  });

  test('should protect against replay attacks', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    await page.goto('/conversations');
    await page.click('[data-testid="new-conversation"]');

    // Send a message and capture its encrypted form
    await page.fill('[data-testid="message-input"]', 'Original message');
    
    const [request] = await Promise.all([
      page.waitForRequest(req => req.url().includes('/api/v1/chat/messages')),
      page.click('[data-testid="send-message"]')
    ]);

    const originalMessage = await request.postDataJSON();

    // Try to replay the same encrypted message
    await page.evaluate((messageData) => {
      // Simulate receiving the same encrypted message again
      window.dispatchEvent(new CustomEvent('receive-message', {
        detail: {
          ...messageData,
          timestamp: Date.now() // Different timestamp
        }
      }));
    }, originalMessage.encrypted_content);

    // Should reject the replayed message
    await expect(page.locator('[data-testid="replay-attack-detected"]')).toBeVisible();
    await expect(page.locator('[data-testid="security-alert"]')).toContainText('Potential replay attack detected');
  });

  test('should handle device impersonation attempts', async ({ page, browser }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Genuine Device');

    // Create another context simulating an attacker
    const attackerContext = await browser.newContext();
    const attackerPage = await attackerContext.newPage();
    
    await attackerPage.goto('/login');
    await attackerPage.fill('input[name="email"]', 'alice@example.com');
    await attackerPage.fill('input[name="password"]', 'password123');
    await attackerPage.click('button[type="submit"]');
    await attackerPage.waitForURL('/dashboard');

    // Attacker tries to register device with similar name
    await attackerPage.goto('/settings/security');
    await attackerPage.click('[data-testid="add-device"]');
    await attackerPage.fill('[data-testid="device-name"]', 'Alice Genuine Device'); // Same name
    await attackerPage.click('[data-testid="initialize-device"]');

    // Original device should detect impersonation attempt
    await page.reload();
    await securityHelper.checkSecurityAlerts();

    await expect(page.locator('[data-testid="device-impersonation-alert"]')).toBeVisible();
    await expect(page.locator('[data-testid="alert-message"]')).toContainText('similar device name detected');

    await attackerContext.close();
  });

  test('should handle man-in-the-middle attack simulation', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Mock intercepted and modified message
    await page.route('**/api/v1/chat/messages', async (route, request) => {
      const response = await route.fetch();
      const data = await response.json();
      
      // Simulate MITM attack - modify encrypted content
      if (data.encrypted_content) {
        data.encrypted_content.data = 'tampered-encrypted-data';
        data.encrypted_content.hmac = 'invalid-hmac';
      }
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(data)
      });
    });

    await page.goto('/conversations');
    await page.click('[data-testid="conversation"]');

    // Try to receive the tampered message
    await page.waitForTimeout(1000);

    // Should detect tampering through HMAC verification
    await expect(page.locator('[data-testid="message-integrity-failed"]')).toBeVisible();
    await expect(page.locator('[data-testid="security-alert"]')).toContainText('Message integrity check failed');
    
    // Should not display the tampered message content
    await expect(page.locator('[data-testid="tampered-message"]')).toHaveCount(0);
  });

  test('should handle quantum-resistant threat scenario', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Future-Proof Device');

    // Enable quantum-resistant mode
    await page.goto('/settings/security');
    await page.click('[data-testid="advanced-settings"]');
    await page.check('[data-testid="quantum-resistant-mode"]');
    await page.click('[data-testid="save-settings"]');

    // Should show quantum-resistant indicators
    await expect(page.locator('[data-testid="quantum-resistant-enabled"]')).toBeVisible();

    // Check that key sizes are upgraded
    const keyInfo = await page.locator('[data-testid="key-info"]').textContent();
    expect(keyInfo).toContain('RSA-4096');
    expect(keyInfo).toContain('AES-256-GCM');

    // Verify quantum-resistant algorithms are used for new messages
    await page.goto('/conversations');
    await page.fill('[data-testid="message-input"]', 'Quantum-resistant message');
    await page.click('[data-testid="send-message"]');

    await expect(page.locator('[data-testid="quantum-resistant-indicator"]')).toBeVisible();
  });

  test('should handle emergency key destruction', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Emergency Device');

    // Navigate to emergency settings
    await page.goto('/settings/security');
    await page.click('[data-testid="emergency-settings"]');

    // Simulate emergency situation
    await page.click('[data-testid="emergency-key-destruction"]');
    
    // Should require multiple confirmations
    await expect(page.locator('[data-testid="emergency-warning"]')).toContainText('This action cannot be undone');
    
    await page.fill('[data-testid="confirmation-text"]', 'DESTROY ALL KEYS');
    await page.click('[data-testid="confirm-destruction"]');

    // Require second factor authentication
    await page.fill('[data-testid="password-confirm"]', 'password123');
    await page.click('[data-testid="final-confirm"]');

    // Should destroy all keys and log out
    await expect(page.locator('[data-testid="keys-destroyed"]')).toBeVisible();
    await page.waitForURL('/login');

    // Should not be able to access encrypted content anymore
    await securityHelper.loginAsUser('alice@example.com');
    await page.goto('/conversations');
    await expect(page.locator('[data-testid="no-encryption-keys"]')).toBeVisible();
  });

  test('should handle forensic investigation scenario', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Enable audit logging
    await page.goto('/settings/security');
    await page.click('[data-testid="audit-settings"]');
    await page.check('[data-testid="detailed-audit-logging"]');
    await page.click('[data-testid="save-audit-settings"]');

    // Perform various activities to generate audit trail
    await page.goto('/conversations');
    await page.click('[data-testid="new-conversation"]');
    await page.fill('[data-testid="message-input"]', 'Audit test message');
    await page.click('[data-testid="send-message"]');

    // Rotate keys
    await page.click('[data-testid="conversation-menu"]');
    await page.click('[data-testid="rotate-keys"]');
    await page.click('[data-testid="confirm-rotation"]');

    // Generate audit report
    await page.goto('/settings/security');
    await page.click('[data-testid="audit-report"]');
    await page.click('[data-testid="generate-report"]');

    // Should show comprehensive audit trail
    await expect(page.locator('[data-testid="audit-events"]')).toBeVisible();
    
    const auditEvents = page.locator('[data-testid="audit-event"]');
    await expect(auditEvents).toHaveCount(4); // device init, message send, key rotation, report generation

    // Should include timeline and integrity verification
    await expect(page.locator('[data-testid="audit-timeline"]')).toBeVisible();
    await expect(page.locator('[data-testid="integrity-verified"]')).toBeVisible();

    // Export audit report
    const downloadPromise = page.waitForEvent('download');
    await page.click('[data-testid="export-audit-report"]');
    const download = await downloadPromise;
    
    expect(download.suggestedFilename()).toMatch(/audit-report-.*\.json$/);
  });

  test('should handle compliance and regulatory requirements', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Compliant Device');

    // Enable compliance mode
    await page.goto('/settings/security');
    await page.click('[data-testid="compliance-settings"]');
    await page.selectOption('[data-testid="compliance-standard"]', 'GDPR');
    await page.check('[data-testid="data-residency-eu"]');
    await page.check('[data-testid="right-to-erasure"]');
    await page.click('[data-testid="enable-compliance-mode"]');

    // Should show compliance indicators
    await expect(page.locator('[data-testid="gdpr-compliant"]')).toBeVisible();
    await expect(page.locator('[data-testid="eu-data-residency"]')).toBeVisible();

    // Test right to erasure
    await page.click('[data-testid="data-rights"]');
    await page.click('[data-testid="request-data-deletion"]');
    
    await page.fill('[data-testid="deletion-reason"]', 'User requested account deletion');
    await page.click('[data-testid="confirm-deletion"]');

    // Should initiate compliant data deletion process
    await expect(page.locator('[data-testid="deletion-scheduled"]')).toBeVisible();
    await expect(page.locator('[data-testid="deletion-timeline"]')).toContainText('30 days');

    // Should generate compliance certificate
    const certificatePromise = page.waitForEvent('download');
    await page.click('[data-testid="generate-compliance-certificate"]');
    const certificate = await certificatePromise;
    
    expect(certificate.suggestedFilename()).toMatch(/compliance-certificate-.*\.pdf$/);
  });
});

test.describe('E2EE Error Recovery', () => {
  let securityHelper: SecurityTestHelper;

  test.beforeEach(async ({ page }) => {
    securityHelper = new SecurityTestHelper(page);
  });

  test('should recover from browser storage corruption', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Create backup before corruption
    await page.goto('/settings/security');
    await page.click('[data-testid="create-backup"]');
    await page.fill('[data-testid="backup-passphrase"]', 'recovery-test-123');
    await page.click('[data-testid="generate-backup"]');
    
    const backupData = await page.locator('[data-testid="backup-data"]').textContent();

    // Simulate storage corruption
    await page.evaluate(() => {
      // Completely corrupt all E2EE storage
      Object.keys(localStorage).forEach(key => {
        if (key.startsWith('e2ee_')) {
          localStorage.setItem(key, 'CORRUPTED');
        }
      });
    });

    // Reload and should detect corruption
    await page.reload();
    await expect(page.locator('[data-testid="storage-corruption-detected"]')).toBeVisible();

    // Restore from backup
    await page.click('[data-testid="restore-from-backup"]');
    await page.fill('[data-testid="backup-data-input"]', backupData!);
    await page.fill('[data-testid="backup-passphrase"]', 'recovery-test-123');
    await page.click('[data-testid="restore-keys"]');

    // Should successfully restore functionality
    await expect(page.locator('[data-testid="restoration-successful"]')).toBeVisible();
    await expect(page.locator('[data-testid="device-functional"]')).toBeVisible();
  });

  test('should handle WebCrypto API unavailability', async ({ page }) => {
    // Disable WebCrypto API
    await page.addInitScript(() => {
      delete (window as any).crypto.subtle;
    });

    await securityHelper.loginAsUser('alice@example.com');

    // Should detect missing WebCrypto support
    await expect(page.locator('[data-testid="webcrypto-unavailable"]')).toBeVisible();
    await expect(page.locator('[data-testid="browser-not-supported"]')).toContainText('Your browser does not support the required security features');

    // Should suggest alternative browsers
    await expect(page.locator('[data-testid="supported-browsers"]')).toBeVisible();
    await expect(page.locator('[data-testid="fallback-options"]')).toBeVisible();
  });

  test('should handle memory pressure scenarios', async ({ page }) => {
    await securityHelper.loginAsUser('alice@example.com');
    await securityHelper.initializeSecureDevice('Alice Device');

    // Simulate memory pressure
    await page.evaluate(() => {
      // Exhaust available memory
      const arrays = [];
      try {
        while (true) {
          arrays.push(new Array(1000000).fill('memory-pressure-test'));
        }
      } catch (e) {
        // Expected out of memory
      }
    });

    // Should handle memory pressure gracefully
    await page.goto('/conversations');
    await page.fill('[data-testid="message-input"]', 'Memory pressure test');
    
    // Should show memory warning but continue functioning
    await expect(page.locator('[data-testid="memory-warning"]')).toBeVisible();
    
    // Should still be able to send message (with possible degraded performance)
    await page.click('[data-testid="send-message"]');
    await expect(page.locator('[data-testid="message"]')).toBeVisible();
  });
});