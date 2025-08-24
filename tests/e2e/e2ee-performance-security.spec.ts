import { test, expect, Page } from '@playwright/test';

test.describe('E2EE Performance and Security Tests', () => {
  let page: Page;
  let user = {
    email: 'perf.test@example.com',
    password: 'SecurePassword123!'
  };

  test.beforeEach(async ({ browser }) => {
    const context = await browser.newContext();
    page = await context.newPage();
    
    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    
    // Setup device if needed
    await page.goto('/chat');
    if (await page.locator('[data-testid="device-setup-required"]').isVisible()) {
      await page.click('button:has-text("Setup Device Encryption")');
      
      // Mock device setup
      await page.route('**/api/v1/chat/devices', async (route) => {
        if (route.request().method() === 'POST') {
          await route.fulfill({
            status: 201,
            contentType: 'application/json',
            body: JSON.stringify({
              success: true,
              device: {
                id: 'perf_device_id',
                device_name: 'Performance Test Device',
                device_type: 'desktop',
                security_level: 'high',
                is_trusted: true
              }
            })
          });
        }
      });
      
      await page.click('button:has-text("Continue")');
      await page.click('button:has-text("Complete Setup")');
    }
  });

  test('Encryption performance handles large messages efficiently', async () => {
    // Create a test conversation
    await page.click('[data-testid="create-conversation"]');
    await page.fill('input[placeholder*="Search for users"]', 'large.message@example.com');
    await page.click('button:has-text("Create Conversation")');

    // Generate a large message (10KB)
    const largeMessage = 'A'.repeat(10 * 1024);
    
    let encryptionTime = 0;
    let messageSize = 0;

    // Mock message sending to measure performance
    await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'POST') {
        const startTime = Date.now();
        const postData = route.request().postDataJSON();
        encryptionTime = Date.now() - startTime;
        messageSize = JSON.stringify(postData.encrypted_content).length;
        
        expect(postData.encrypted_content).toBeDefined();
        expect(postData.content_hash).toBeDefined();
        
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            id: 'large_msg_id',
            content: largeMessage,
            encrypted_content: postData.encrypted_content,
            content_hash: postData.content_hash,
            created_at: new Date().toISOString()
          })
        });
      }
    });

    // Send the large message
    await page.fill('[data-testid="message-input"]', largeMessage);
    const sendStart = Date.now();
    await page.click('[data-testid="send-message"]');
    const sendEnd = Date.now();

    // Wait for message to appear
    await expect(page.locator('[data-testid="message-bubble"]').last()).toBeVisible();

    // Verify performance metrics
    const totalTime = sendEnd - sendStart;
    expect(totalTime).toBeLessThan(5000); // Should complete within 5 seconds
    expect(messageSize).toBeGreaterThan(10000); // Encrypted message should be larger than original
    expect(messageSize).toBeLessThan(50000); // But not excessively large

    console.log(`Large message encryption: ${totalTime}ms total, encrypted size: ${messageSize} bytes`);
  });

  test('Rapid message sending maintains encryption integrity', async () => {
    // Send 20 messages in rapid succession
    const messages = Array.from({ length: 20 }, (_, i) => `Rapid message ${i + 1}`);
    const messageIds: string[] = [];
    let encryptionFailures = 0;

    // Mock message API to track all messages
    await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'POST') {
        const postData = route.request().postDataJSON();
        const messageId = `rapid_msg_${Date.now()}_${Math.random()}`;
        messageIds.push(messageId);
        
        // Verify encryption data is present
        if (!postData.encrypted_content || !postData.content_hash) {
          encryptionFailures++;
        }
        
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            id: messageId,
            content: postData.content || '[Decryption failed]',
            encrypted_content: postData.encrypted_content,
            content_hash: postData.content_hash,
            created_at: new Date().toISOString()
          })
        });
      }
    });

    // Send messages rapidly
    for (const message of messages) {
      await page.fill('[data-testid="message-input"]', message);
      await page.click('[data-testid="send-message"]');
      // Small delay to avoid overwhelming the browser
      await page.waitForTimeout(50);
    }

    // Wait for all messages to appear
    await expect(page.locator('[data-testid="message-bubble"]')).toHaveCount(20);

    // Verify no encryption failures
    expect(encryptionFailures).toBe(0);
    expect(messageIds).toHaveLength(20);

    // All messages should have encryption indicators
    const encryptedMessages = await page.locator('[data-testid="message-bubble"] [title*="encrypted"]').count();
    expect(encryptedMessages).toBe(20);
  });

  test('Memory usage remains stable during extended messaging session', async () => {
    // Start performance monitoring
    const initialMemory = await page.evaluate(() => {
      if (window.performance && (window.performance as any).memory) {
        return (window.performance as any).memory.usedJSHeapSize;
      }
      return 0;
    });

    // Send 100 messages to test memory stability
    for (let i = 0; i < 100; i++) {
      // Mock each message send
      await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
        if (route.request().method() === 'POST') {
          await route.fulfill({
            status: 201,
            contentType: 'application/json',
            body: JSON.stringify({
              id: `memory_test_msg_${i}`,
              content: `Memory test message ${i}`,
              encrypted_content: 'encrypted_data',
              content_hash: 'content_hash',
              created_at: new Date().toISOString()
            })
          });
        }
      });

      await page.fill('[data-testid="message-input"]', `Memory test message ${i}`);
      await page.click('[data-testid="send-message"]');
      
      if (i % 10 === 0) {
        // Periodic memory check
        const currentMemory = await page.evaluate(() => {
          if (window.performance && (window.performance as any).memory) {
            return (window.performance as any).memory.usedJSHeapSize;
          }
          return 0;
        });
        
        console.log(`Memory at message ${i}: ${currentMemory} bytes`);
        
        // Memory should not grow excessively
        if (initialMemory > 0 && currentMemory > 0) {
          const memoryGrowth = (currentMemory - initialMemory) / initialMemory;
          expect(memoryGrowth).toBeLessThan(5); // Memory shouldn't grow more than 500%
        }
      }
    }

    // Final memory check
    const finalMemory = await page.evaluate(() => {
      if (window.performance && (window.performance as any).memory) {
        return (window.performance as any).memory.usedJSHeapSize;
      }
      return 0;
    });

    console.log(`Initial memory: ${initialMemory}, Final memory: ${finalMemory}`);
  });

  test('Encryption keys are never exposed in browser storage', async () => {
    // Check localStorage for sensitive data
    const localStorage = await page.evaluate(() => {
      const keys = Object.keys(window.localStorage);
      const values = keys.map(key => ({
        key,
        value: window.localStorage.getItem(key)
      }));
      return values;
    });

    // Check sessionStorage
    const sessionStorage = await page.evaluate(() => {
      const keys = Object.keys(window.sessionStorage);
      const values = keys.map(key => ({
        key,
        value: window.sessionStorage.getItem(key)
      }));
      return values;
    });

    // Verify no private keys are stored in plaintext
    for (const item of [...localStorage, ...sessionStorage]) {
      expect(item.value).not.toContain('-----BEGIN PRIVATE KEY-----');
      expect(item.value).not.toContain('-----BEGIN RSA PRIVATE KEY-----');
      expect(item.key.toLowerCase()).not.toContain('private');
      expect(item.key.toLowerCase()).not.toContain('secret');
    }

    // Check for IndexedDB usage (keys should be stored encrypted if at all)
    const indexedDBDatabases = await page.evaluate(async () => {
      if ('indexedDB' in window) {
        try {
          const dbs = await indexedDB.databases();
          return dbs.map(db => db.name);
        } catch (e) {
          return [];
        }
      }
      return [];
    });

    console.log('IndexedDB databases:', indexedDBDatabases);
    
    // If E2EE keys are stored in IndexedDB, they should be encrypted
    if (indexedDBDatabases.length > 0) {
      // Additional checks could be added here to verify encryption
      console.log('Found IndexedDB databases - ensure keys are encrypted');
    }
  });

  test('Decryption failure recovery handles gracefully', async () => {
    let decryptionAttempts = 0;

    // Mock messages with some that fail decryption
    await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([
            {
              id: 'good_msg_1',
              content: 'This message decrypts fine',
              encrypted_content: 'valid_encrypted_data',
              content_hash: 'valid_hash',
              created_at: new Date(Date.now() - 60000).toISOString()
            },
            {
              id: 'bad_msg_1',
              content: null, // This will fail decryption
              encrypted_content: 'corrupted_encrypted_data',
              content_hash: 'invalid_hash',
              created_at: new Date(Date.now() - 30000).toISOString()
            },
            {
              id: 'good_msg_2',
              content: 'Another good message',
              encrypted_content: 'valid_encrypted_data_2',
              content_hash: 'valid_hash_2',
              created_at: new Date().toISOString()
            }
          ])
        });
      }
    });

    // Navigate to conversation
    await page.click('[data-testid="conversation-item"]');

    // Should show both good and failed messages
    await expect(page.locator('[data-testid="message-bubble"]')).toHaveCount(3);
    
    // Good messages should display content
    await expect(page.locator('text=This message decrypts fine')).toBeVisible();
    await expect(page.locator('text=Another good message')).toBeVisible();
    
    // Failed message should show error state
    await expect(page.locator('text=[Message could not be decrypted]')).toBeVisible();
    
    // Failed message should have warning indicator
    const failedMessage = page.locator('[data-testid="message-bubble"]:has-text("[Message could not be decrypted]")');
    await expect(failedMessage.locator('.text-yellow-500')).toBeVisible();
  });

  test('Key rotation completes within performance thresholds', async () => {
    // Open device management
    await page.click('button:has-text("Devices")');
    await page.click('[data-testid="security-tab"]');

    let rotationStartTime = 0;
    let rotationEndTime = 0;

    // Mock key rotation API with timing
    await page.route('**/api/v1/chat/devices/*/rotate-keys', async (route) => {
      if (route.request().method() === 'POST') {
        rotationStartTime = Date.now();
        
        // Simulate some processing time
        await new Promise(resolve => setTimeout(resolve, 500));
        
        rotationEndTime = Date.now();
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'Key rotation completed',
            rotatedConversations: ['conv_1', 'conv_2', 'conv_3'],
            totalRotated: 3,
            processingTime: rotationEndTime - rotationStartTime
          })
        });
      }
    });

    // Initiate key rotation
    const rotationStart = Date.now();
    await page.click('button:has-text("Rotate Keys")');
    
    // Wait for completion
    await expect(page.locator('text=Key rotation completed')).toBeVisible();
    const rotationComplete = Date.now();

    // Verify performance thresholds
    const totalTime = rotationComplete - rotationStart;
    expect(totalTime).toBeLessThan(10000); // Should complete within 10 seconds
    
    console.log(`Key rotation completed in ${totalTime}ms`);
  });

  test('Cross-device key sharing performs efficiently', async () => {
    // Mock multiple devices for key sharing test
    await page.route('**/api/v1/chat/devices', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([
            {
              id: 'device_1',
              name: 'Primary Device',
              type: 'desktop',
              isTrusted: true,
              lastUsed: new Date().toISOString()
            },
            {
              id: 'device_2',
              name: 'Mobile Device',
              type: 'mobile',
              isTrusted: true,
              lastUsed: new Date().toISOString()
            },
            {
              id: 'device_3',
              name: 'Tablet Device',
              type: 'tablet',
              isTrusted: false,
              lastUsed: new Date().toISOString()
            }
          ])
        });
      }
    });

    await page.click('button:has-text("Devices")');
    
    let keyShareStartTime = 0;
    let sharedConversations = 0;

    // Mock key sharing
    await page.route('**/api/v1/chat/devices/*/share-keys', async (route) => {
      if (route.request().method() === 'POST') {
        keyShareStartTime = Date.now();
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'Keys shared successfully',
            sharedConversations: 15, // Large number to test performance
            failedConversations: 0,
            processingTime: Date.now() - keyShareStartTime
          })
        });
      }
    });

    // Share keys with newly trusted device
    const untrustedDevice = page.locator('[data-testid="device-card"]:has-text("Tablet Device")');
    await untrustedDevice.locator('button:has-text("Trust")').click();
    await page.click('button:has-text("Trust Device")');

    // Share keys
    const shareStart = Date.now();
    await untrustedDevice.locator('button:has-text("Share Keys")').click();
    
    await expect(page.locator('text=Keys shared successfully')).toBeVisible();
    const shareComplete = Date.now();

    // Verify performance
    const totalShareTime = shareComplete - shareStart;
    expect(totalShareTime).toBeLessThan(15000); // 15 conversations should share within 15 seconds
    
    console.log(`Key sharing for 15 conversations completed in ${totalShareTime}ms`);
  });

  test('Browser compatibility and WebCrypto API availability', async () => {
    // Check WebCrypto API availability and features
    const cryptoFeatures = await page.evaluate(() => {
      if (!window.crypto || !window.crypto.subtle) {
        return { available: false };
      }

      return {
        available: true,
        methods: {
          generateKey: typeof window.crypto.subtle.generateKey === 'function',
          exportKey: typeof window.crypto.subtle.exportKey === 'function',
          importKey: typeof window.crypto.subtle.importKey === 'function',
          encrypt: typeof window.crypto.subtle.encrypt === 'function',
          decrypt: typeof window.crypto.subtle.decrypt === 'function',
          digest: typeof window.crypto.subtle.digest === 'function'
        },
        randomValues: typeof window.crypto.getRandomValues === 'function'
      };
    });

    // Verify all required crypto methods are available
    expect(cryptoFeatures.available).toBe(true);
    expect(cryptoFeatures.methods.generateKey).toBe(true);
    expect(cryptoFeatures.methods.exportKey).toBe(true);
    expect(cryptoFeatures.methods.importKey).toBe(true);
    expect(cryptoFeatures.methods.encrypt).toBe(true);
    expect(cryptoFeatures.methods.decrypt).toBe(true);
    expect(cryptoFeatures.methods.digest).toBe(true);
    expect(cryptoFeatures.randomValues).toBe(true);

    console.log('WebCrypto API features:', cryptoFeatures);
  });

  test('Error handling for network failures during encryption operations', async () => {
    // Simulate network failure during device registration
    await page.route('**/api/v1/chat/devices', async (route) => {
      await route.abort('connectionrefused');
    });

    // Try to send a message when network is down
    await page.fill('[data-testid="message-input"]', 'Network failure test message');
    await page.click('[data-testid="send-message"]');

    // Should show appropriate error message
    await expect(page.locator('[data-testid="send-error"]')).toBeVisible();
    
    // Message should remain in input for retry
    expect(await page.locator('[data-testid="message-input"]').inputValue()).toContain('Network failure test message');

    // Restore network
    await page.unroute('**/api/v1/chat/devices');
    
    // Mock successful send
    await page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          id: 'retry_msg',
          content: 'Network failure test message',
          created_at: new Date().toISOString()
        })
      });
    });

    // Retry should work
    await page.click('[data-testid="send-message"]');
    await expect(page.locator('text=Network failure test message')).toBeVisible();
  });
});