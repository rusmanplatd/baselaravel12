import { test, expect, Page, BrowserContext } from '@playwright/test';

interface TestUser {
  name: string;
  email: string;
  password: string;
}

interface DeviceInfo {
  name: string;
  type: 'mobile' | 'desktop' | 'web' | 'tablet';
  platform: string;
  userAgent: string;
  fingerprint: string;
  publicKey: string;
  privateKey: string;
  capabilities: string[];
  securityLevel: 'low' | 'medium' | 'high' | 'maximum';
}

// Mock crypto functions for testing
class E2EECrypto {
  static generateKeyPair(): { publicKey: string; privateKey: string } {
    // In real implementation, this would use WebCrypto API
    const keyId = Math.random().toString(36).substring(7);
    return {
      publicKey: `-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA${keyId}\n-----END PUBLIC KEY-----`,
      privateKey: `-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC${keyId}\n-----END PRIVATE KEY-----`
    };
  }
  
  static generateSymmetricKey(): string {
    return btoa(Math.random().toString(36).repeat(4));
  }
  
  static generateFingerprint(): string {
    return Array.from({ length: 16 }, () => 
      Math.floor(Math.random() * 256).toString(16).padStart(2, '0')
    ).join(':');
  }
}

test.describe('Multi-Device E2EE Chat System', () => {
  let user1: TestUser;
  let user2: TestUser;
  let device1Context: BrowserContext;
  let device2Context: BrowserContext;
  let device1Page: Page;
  let device2Page: Page;
  let device1Info: DeviceInfo;
  let device2Info: DeviceInfo;

  test.beforeAll(async ({ browser }) => {
    // Create test users
    user1 = {
      name: 'Alice Johnson',
      email: 'alice@example.com',
      password: 'SecurePassword123!'
    };
    
    user2 = {
      name: 'Bob Smith',
      email: 'bob@example.com',
      password: 'SecurePassword456!'
    };

    // Create device contexts with different user agents
    device1Context = await browser.newContext({
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
      viewport: { width: 375, height: 812 }
    });
    
    device2Context = await browser.newContext({
      userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
      viewport: { width: 1440, height: 900 }
    });

    device1Page = await device1Context.newPage();
    device2Page = await device2Context.newPage();

    // Generate device information
    const keyPair1 = E2EECrypto.generateKeyPair();
    const keyPair2 = E2EECrypto.generateKeyPair();
    
    device1Info = {
      name: 'iPhone 15 Pro',
      type: 'mobile',
      platform: 'iOS',
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
      fingerprint: E2EECrypto.generateFingerprint(),
      publicKey: keyPair1.publicKey,
      privateKey: keyPair1.privateKey,
      capabilities: ['messaging', 'encryption', 'biometric'],
      securityLevel: 'high'
    };
    
    device2Info = {
      name: 'MacBook Pro',
      type: 'desktop',
      platform: 'macOS',
      userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
      fingerprint: E2EECrypto.generateFingerprint(),
      publicKey: keyPair2.publicKey,
      privateKey: keyPair2.privateKey,
      capabilities: ['messaging', 'encryption', 'file_sharing'],
      securityLevel: 'high'
    };
  });

  test.afterAll(async () => {
    await device1Context.close();
    await device2Context.close();
  });

  test('User can register account and first device', async () => {
    // Navigate to registration page on device 1
    await device1Page.goto('/register');
    
    // Fill registration form
    await device1Page.fill('input[name="name"]', user1.name);
    await device1Page.fill('input[name="email"]', user1.email);
    await device1Page.fill('input[name="password"]', user1.password);
    await device1Page.fill('input[name="password_confirmation"]', user1.password);
    
    // Mock device registration API call
    await device1Page.route('**/api/v1/chat/devices', async (route) => {
      const request = route.request();
      if (request.method() === 'POST') {
        const postData = request.postDataJSON();
        expect(postData.device_name).toBe(device1Info.name);
        expect(postData.device_type).toBe(device1Info.type);
        expect(postData.device_capabilities).toContain('messaging');
        expect(postData.device_capabilities).toContain('encryption');
        
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Device registered successfully',
            device: {
              id: 'device_1_id',
              device_name: device1Info.name,
              device_type: device1Info.type,
              security_level: device1Info.securityLevel,
              security_score: 85,
              is_trusted: false,
              requires_verification: true,
              device_capabilities: device1Info.capabilities
            },
            verification: {
              challenge: {
                challenge_id: 'challenge_123',
                device_id: 'device_1_id',
                timestamp: Date.now(),
                nonce: 'test_nonce',
                verification_type: 'security_key'
              },
              expires_at: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
              verification_methods: ['security_key', 'verification_code']
            }
          })
        });
      }
    });
    
    // Submit registration
    await device1Page.click('button[type="submit"]');
    
    // Expect to be on device setup page
    await expect(device1Page).toHaveURL(/.*\/setup-device/);
    await expect(device1Page.locator('[data-testid="device-setup-form"]')).toBeVisible();
  });

  test('User can verify and trust first device', async () => {
    // Assume we're on the device setup page from previous test
    await device1Page.goto('/setup-device');
    
    // Fill device information
    await device1Page.fill('input[name="device_name"]', device1Info.name);
    await device1Page.selectOption('select[name="device_type"]', device1Info.type);
    await device1Page.fill('input[name="platform"]', device1Info.platform);
    await device1Page.fill('textarea[name="public_key"]', device1Info.publicKey);
    
    // Mock device verification API
    await device1Page.route('**/api/v1/chat/devices/*/verify', async (route) => {
      const request = route.request();
      if (request.method() === 'POST') {
        const postData = request.postDataJSON();
        expect(postData.challenge_id).toBe('challenge_123');
        expect(postData.response.signature).toBeDefined();
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Device verified successfully',
            device: {
              id: 'device_1_id',
              is_trusted: true,
              verified_at: new Date().toISOString(),
              security_score: 95
            }
          })
        });
      }
    });
    
    // Submit device setup
    await device1Page.click('button[data-testid="setup-device"]');
    
    // Expect success message
    await expect(device1Page.locator('[data-testid="device-verified"]')).toBeVisible();
    await expect(device1Page.locator('[data-testid="device-verified"]')).toContainText('Device verified successfully');
  });

  test('User can add second device and initiate key sharing', async () => {
    // Navigate to login on device 2
    await device2Page.goto('/login');
    
    // Fill login form
    await device2Page.fill('input[name="email"]', user1.email);
    await device2Page.fill('input[name="password"]', user1.password);
    
    // Mock login with new device detection
    await device2Page.route('**/login', async (route) => {
      await route.fulfill({
        status: 302,
        headers: { 'Location': '/device-verification' }
      });
    });
    
    await device2Page.click('button[type="submit"]');
    
    // Should be redirected to device verification
    await expect(device2Page).toHaveURL(/.*\/device-verification/);
    
    // Fill new device information
    await device2Page.fill('input[name="device_name"]', device2Info.name);
    await device2Page.selectOption('select[name="device_type"]', device2Info.type);
    await device2Page.fill('textarea[name="public_key"]', device2Info.publicKey);
    
    // Mock device registration and key sharing initiation
    await device2Page.route('**/api/v1/chat/devices', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Device registered successfully',
            device: {
              id: 'device_2_id',
              device_name: device2Info.name,
              device_type: device2Info.type,
              security_level: device2Info.securityLevel,
              security_score: 80,
              is_trusted: false,
              requires_verification: true,
              device_capabilities: device2Info.capabilities
            }
          })
        });
      }
    });
    
    // Mock key sharing from trusted device
    await device2Page.route('**/api/v1/chat/devices/*/share-keys', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Key sharing initiated',
            shared_conversations: [
              {
                conversation_id: 'conv_123',
                conversation_name: 'Test Chat',
                key_share_id: 'share_123'
              }
            ],
            total_keys_shared: 1,
            failed_conversations: []
          })
        });
      }
    });
    
    await device2Page.click('button[data-testid="register-device"]');
    
    // Expect device registered and key sharing initiated
    await expect(device2Page.locator('[data-testid="key-sharing-initiated"]')).toBeVisible();
  });

  test('User can complete cross-device verification', async () => {
    // On device 1, expect to see verification request
    await device1Page.goto('/devices');
    
    // Mock devices list
    await device1Page.route('**/api/v1/chat/devices', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            devices: [
              {
                id: 'device_1_id',
                device_name: device1Info.name,
                device_type: device1Info.type,
                platform: device1Info.platform,
                short_fingerprint: device1Info.fingerprint.substring(0, 8) + '...',
                is_trusted: true,
                last_used_at: new Date().toISOString(),
                created_at: new Date().toISOString(),
                is_current: true
              },
              {
                id: 'device_2_id',
                device_name: device2Info.name,
                device_type: device2Info.type,
                platform: device2Info.platform,
                short_fingerprint: device2Info.fingerprint.substring(0, 8) + '...',
                is_trusted: false,
                last_used_at: new Date().toISOString(),
                created_at: new Date().toISOString(),
                is_current: false
              }
            ],
            total: 2
          })
        });
      }
    });
    
    await device1Page.reload();
    
    // Should see both devices
    await expect(device1Page.locator('[data-testid="device-list"]')).toBeVisible();
    await expect(device1Page.locator('[data-device-id="device_1_id"]')).toContainText(device1Info.name);
    await expect(device1Page.locator('[data-device-id="device_2_id"]')).toContainText(device2Info.name);
    
    // Trust the second device
    await device1Page.click('[data-device-id="device_2_id"] [data-testid="trust-device"]');
    
    // Mock device trust API
    await device1Page.route('**/api/v1/chat/devices/device_2_id/trust', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Device marked as trusted',
            device: {
              id: 'device_2_id',
              is_trusted: true,
              verified_at: new Date().toISOString()
            }
          })
        });
      }
    });
    
    // Confirm trust
    await device1Page.click('[data-testid="confirm-trust"]');
    
    // Expect success message
    await expect(device1Page.locator('[data-testid="device-trusted"]')).toBeVisible();
    await expect(device1Page.locator('[data-testid="device-trusted"]')).toContainText('Device marked as trusted');
  });

  test('User can send encrypted message between devices', async () => {
    // Navigate to chat on device 1
    await device1Page.goto('/chat');
    
    // Mock conversations list
    await device1Page.route('**/api/v1/chat/conversations', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            conversations: [
              {
                id: 'conv_123',
                name: 'Test Chat',
                type: 'direct',
                last_message: null,
                updated_at: new Date().toISOString(),
                participants: [
                  { user: { id: 'user_1', name: user1.name } },
                  { user: { id: 'user_2', name: user2.name } }
                ]
              }
            ]
          })
        });
      }
    });
    
    await device1Page.reload();
    await device1Page.click('[data-conversation-id="conv_123"]');
    
    // Mock send message API
    await device1Page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'POST') {
        const postData = route.request().postDataJSON();
        expect(postData.encrypted_content).toBeDefined();
        expect(postData.device_id).toBe('device_1_id');
        
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Message sent successfully',
            message: {
              id: 'msg_123',
              content: 'Hello from device 1!',
              sender_id: 'user_1',
              encrypted_content: postData.encrypted_content,
              device_id: 'device_1_id',
              sent_at: new Date().toISOString()
            }
          })
        });
      }
    });
    
    // Send a message
    await device1Page.fill('[data-testid="message-input"]', 'Hello from device 1!');
    await device1Page.click('[data-testid="send-message"]');
    
    // Expect message to appear in chat
    await expect(device1Page.locator('[data-message-id="msg_123"]')).toBeVisible();
    await expect(device1Page.locator('[data-message-id="msg_123"]')).toContainText('Hello from device 1!');
  });

  test('Second device can decrypt and read messages', async () => {
    // Navigate to chat on device 2
    await device2Page.goto('/chat');
    
    // Mock getting conversation messages
    await device2Page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            messages: [
              {
                id: 'msg_123',
                sender_id: 'user_1',
                sender_name: user1.name,
                encrypted_content: 'encrypted_message_data',
                iv: 'test_iv',
                device_id: 'device_1_id',
                sent_at: new Date().toISOString()
              }
            ],
            pagination: {
              current_page: 1,
              total: 1
            }
          })
        });
      }
    });
    
    // Mock conversation key retrieval
    await device2Page.route('**/api/v1/chat/conversations/*/device-key', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            key_id: 'key_123',
            encrypted_key: 'encrypted_symmetric_key_data',
            public_key: device2Info.publicKey,
            key_version: 1,
            device_fingerprint: device2Info.fingerprint
          })
        });
      }
    });
    
    await device2Page.click('[data-conversation-id="conv_123"]');
    
    // Should show the decrypted message
    await expect(device2Page.locator('[data-message-id="msg_123"]')).toBeVisible();
    await expect(device2Page.locator('[data-message-id="msg_123"]')).toContainText('Hello from device 1!');
  });

  test('User can initiate key rotation from any device', async () => {
    // On device 2, go to security settings
    await device2Page.goto('/settings/security');
    
    // Mock security report
    await device2Page.route('**/api/v1/chat/devices/*/security-report', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            device_id: 'device_2_id',
            device_name: device2Info.name,
            integrity_report: {
              device_id: 'device_2_id',
              security_score: 90,
              status: 'healthy',
              issues: [],
              recommendations: []
            },
            encryption_summary: {
              device_id: 'device_2_id',
              device_name: device2Info.name,
              is_trusted: true,
              security_level: device2Info.securityLevel,
              security_score: 90,
              encryption_version: 2,
              active_conversation_keys: 1,
              pending_key_shares: 0,
              requires_key_rotation: false,
              last_used: new Date().toISOString(),
              last_key_rotation: null
            },
            generated_at: new Date().toISOString()
          })
        });
      }
    });
    
    await device2Page.reload();
    
    // Should show security report
    await expect(device2Page.locator('[data-testid="security-report"]')).toBeVisible();
    await expect(device2Page.locator('[data-testid="security-score"]')).toContainText('90');
    
    // Click rotate keys button
    await device2Page.click('[data-testid="rotate-keys"]');
    
    // Mock key rotation API
    await device2Page.route('**/api/v1/chat/devices/*/rotate-keys', async (route) => {
      if (route.request().method() === 'POST') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Key rotation completed',
            rotated_conversations: ['conv_123'],
            results: {
              'conv_123': {
                rotated_devices: [
                  {
                    device_id: 'device_1_id',
                    device_name: device1Info.name,
                    user_id: 'user_1',
                    encryption_key_id: 'key_new_1'
                  },
                  {
                    device_id: 'device_2_id',
                    device_name: device2Info.name,
                    user_id: 'user_1',
                    encryption_key_id: 'key_new_2'
                  }
                ],
                failed_devices: [],
                key_version: 2
              }
            },
            total_rotated: 1
          })
        });
      }
    });
    
    // Confirm key rotation
    await device2Page.click('[data-testid="confirm-rotation"]');
    
    // Expect success message
    await expect(device2Page.locator('[data-testid="rotation-success"]')).toBeVisible();
    await expect(device2Page.locator('[data-testid="rotation-success"]')).toContainText('Key rotation completed');
  });

  test('Both devices can continue messaging after key rotation', async () => {
    // On device 1, send another message
    await device1Page.goto('/chat');
    await device1Page.click('[data-conversation-id="conv_123"]');
    
    // Mock send message with new key version
    await device1Page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'POST') {
        const postData = route.request().postDataJSON();
        expect(postData.key_version).toBe(2);
        
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Message sent successfully',
            message: {
              id: 'msg_456',
              content: 'Hello after key rotation!',
              sender_id: 'user_1',
              encrypted_content: postData.encrypted_content,
              device_id: 'device_1_id',
              key_version: 2,
              sent_at: new Date().toISOString()
            }
          })
        });
      }
    });
    
    await device1Page.fill('[data-testid="message-input"]', 'Hello after key rotation!');
    await device1Page.click('[data-testid="send-message"]');
    
    // On device 2, should be able to see and decrypt the new message
    await device2Page.goto('/chat');
    await device2Page.click('[data-conversation-id="conv_123"]');
    
    // Mock getting updated messages
    await device2Page.route('**/api/v1/chat/conversations/*/messages', async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            messages: [
              {
                id: 'msg_123',
                sender_id: 'user_1',
                sender_name: user1.name,
                content: 'Hello from device 1!',
                device_id: 'device_1_id',
                key_version: 1,
                sent_at: new Date(Date.now() - 60000).toISOString()
              },
              {
                id: 'msg_456',
                sender_id: 'user_1',
                sender_name: user1.name,
                content: 'Hello after key rotation!',
                device_id: 'device_1_id',
                key_version: 2,
                sent_at: new Date().toISOString()
              }
            ],
            pagination: {
              current_page: 1,
              total: 2
            }
          })
        });
      }
    });
    
    await device2Page.reload();
    
    // Should see both messages
    await expect(device2Page.locator('[data-message-id="msg_123"]')).toBeVisible();
    await expect(device2Page.locator('[data-message-id="msg_456"]')).toBeVisible();
    await expect(device2Page.locator('[data-message-id="msg_456"]')).toContainText('Hello after key rotation!');
  });

  test('User can remove device and revoke access', async () => {
    // On device 1, go to device management
    await device1Page.goto('/settings/devices');
    
    // Click remove on device 2
    await device1Page.click('[data-device-id="device_2_id"] [data-testid="remove-device"]');
    
    // Mock device removal API
    await device1Page.route('**/api/v1/chat/devices/device_2_id', async (route) => {
      if (route.request().method() === 'DELETE') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Device removed successfully'
          })
        });
      }
    });
    
    // Confirm removal
    await device1Page.click('[data-testid="confirm-removal"]');
    
    // Expect success message
    await expect(device1Page.locator('[data-testid="device-removed"]')).toBeVisible();
    
    // Device 2 should no longer be able to access chat
    await device2Page.goto('/chat');
    
    // Mock unauthorized access
    await device2Page.route('**/api/v1/chat/**', async (route) => {
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({
          error: 'Device access has been revoked'
        })
      });
    });
    
    // Should show access revoked message
    await expect(device2Page.locator('[data-testid="access-revoked"]')).toBeVisible();
    await expect(device2Page.locator('[data-testid="access-revoked"]')).toContainText('Device access has been revoked');
  });
});