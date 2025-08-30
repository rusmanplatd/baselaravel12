/**
 * Unit tests for Quantum-Safe E2EE implementation
 * Tests NIST post-quantum cryptographic algorithms and security features
 */

import { describe, it, expect, beforeEach, jest } from '@jest/globals';
import { QuantumSafeE2EE } from '@/services/QuantumSafeE2EE';
import { QuantumKeyExchangeProtocol } from '@/services/QuantumKeyExchangeProtocol';

// Mock Web Crypto API for testing
const mockSubtle = {
  generateKey: jest.fn(),
  sign: jest.fn(),
  verify: jest.fn(),
  encrypt: jest.fn(),
  decrypt: jest.fn(),
  deriveBits: jest.fn(),
  deriveKey: jest.fn(),
  importKey: jest.fn(),
  exportKey: jest.fn(),
};

const mockCrypto = {
  subtle: mockSubtle,
  getRandomValues: jest.fn((arr) => {
    for (let i = 0; i < arr.length; i++) {
      arr[i] = Math.floor(Math.random() * 256);
    }
    return arr;
  }),
};

// @ts-ignore
global.crypto = mockCrypto;
global.window = { crypto: mockCrypto } as any;

describe('QuantumSafeE2EE', () => {
  let quantumE2EE: QuantumSafeE2EE;

  beforeEach(() => {
    quantumE2EE = new QuantumSafeE2EE();
    jest.clearAllMocks();
  });

  describe('Device Initialization', () => {
    it('should initialize device with quantum-safe key pair', async () => {
      const userId = 'test-user-123';
      
      const result = await quantumE2EE.initializeDevice(userId);
      
      expect(result).toBe(true);
    });

    it('should generate unique key pairs for different users', async () => {
      const user1 = 'user-1';
      const user2 = 'user-2';
      
      await quantumE2EE.initializeDevice(user1);
      const keyPair1 = await quantumE2EE.getDeviceKeyPair();
      
      await quantumE2EE.initializeDevice(user2);
      const keyPair2 = await quantumE2EE.getDeviceKeyPair();
      
      expect(keyPair1.publicKey).not.toEqual(keyPair2.publicKey);
      expect(keyPair1.privateKey).not.toEqual(keyPair2.privateKey);
    });
  });

  describe('Quantum Key Generation', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should generate quantum-resistant key pairs', async () => {
      const keyPair = await quantumE2EE.generateQuantumKeyPair();
      
      expect(keyPair).toBeDefined();
      expect(keyPair.publicKey).toBeInstanceOf(Uint8Array);
      expect(keyPair.privateKey).toBeInstanceOf(Uint8Array);
      expect(keyPair.algorithm).toBe('ML-KEM-1024');
    });

    it('should generate different keys on each call', async () => {
      const keyPair1 = await quantumE2EE.generateQuantumKeyPair();
      const keyPair2 = await quantumE2EE.generateQuantumKeyPair();
      
      expect(keyPair1.publicKey).not.toEqual(keyPair2.publicKey);
      expect(keyPair1.privateKey).not.toEqual(keyPair2.privateKey);
    });
  });

  describe('Message Encryption/Decryption', () => {
    const conversationId = 'conv-123';
    const testMessage = 'This is a quantum-safe test message';

    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should encrypt and decrypt messages correctly', async () => {
      const encrypted = await quantumE2EE.encryptMessage(testMessage, conversationId);
      expect(encrypted).toBeDefined();
      expect(encrypted.ciphertext).toBeInstanceOf(Uint8Array);
      expect(encrypted.nonce).toBeInstanceOf(Uint8Array);
      expect(encrypted.tag).toBeInstanceOf(Uint8Array);
      expect(encrypted.signature).toBeInstanceOf(Uint8Array);
      expect(encrypted.algorithm).toBe('PQ-E2EE-v1.0');

      const decrypted = await quantumE2EE.decryptMessage(encrypted, conversationId);
      expect(decrypted).toBe(testMessage);
    });

    it('should produce different ciphertexts for same message', async () => {
      const encrypted1 = await quantumE2EE.encryptMessage(testMessage, conversationId);
      const encrypted2 = await quantumE2EE.encryptMessage(testMessage, conversationId);
      
      expect(encrypted1.ciphertext).not.toEqual(encrypted2.ciphertext);
      expect(encrypted1.nonce).not.toEqual(encrypted2.nonce);
    });

    it('should fail to decrypt with wrong conversation ID', async () => {
      const encrypted = await quantumE2EE.encryptMessage(testMessage, conversationId);
      
      await expect(async () => {
        await quantumE2EE.decryptMessage(encrypted, 'wrong-conv-id');
      }).rejects.toThrow();
    });

    it('should fail to decrypt tampered messages', async () => {
      const encrypted = await quantumE2EE.encryptMessage(testMessage, conversationId);
      
      // Tamper with the ciphertext
      encrypted.ciphertext[0] = encrypted.ciphertext[0] ^ 1;
      
      await expect(async () => {
        await quantumE2EE.decryptMessage(encrypted, conversationId);
      }).rejects.toThrow();
    });
  });

  describe('Forward Secrecy', () => {
    const conversationId = 'conv-forward-secrecy';

    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should use different keys for forward secrecy', async () => {
      const message1 = 'First message';
      const message2 = 'Second message';

      const encrypted1 = await quantumE2EE.encryptMessage(message1, conversationId);
      const encrypted2 = await quantumE2EE.encryptMessage(message2, conversationId);

      // Key versions should be different for forward secrecy
      expect(encrypted1.keyVersion).not.toBe(encrypted2.keyVersion);
    });

    it('should still decrypt old messages after key rotation', async () => {
      const message = 'Message before rotation';
      const encrypted = await quantumE2EE.encryptMessage(message, conversationId);

      // Rotate keys
      await quantumE2EE.rotateKeys(conversationId, 'Test rotation');

      // Should still be able to decrypt the old message
      const decrypted = await quantumE2EE.decryptMessage(encrypted, conversationId);
      expect(decrypted).toBe(message);
    });
  });

  describe('Security Metrics', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should return comprehensive security metrics', async () => {
      const metrics = await quantumE2EE.getSecurityMetrics();
      
      expect(metrics).toBeDefined();
      expect(metrics.isQuantumResistant).toBe(true);
      expect(metrics.overallSecurityScore).toBeGreaterThanOrEqual(8);
      expect(metrics.algorithmStrengths).toBeDefined();
      expect(metrics.threatAssessment).toBeDefined();
    });

    it('should detect high security score for quantum algorithms', async () => {
      const metrics = await quantumE2EE.getSecurityMetrics();
      
      expect(metrics.algorithmStrengths.keyEncapsulation).toBeGreaterThanOrEqual(9);
      expect(metrics.algorithmStrengths.digitalSignature).toBeGreaterThanOrEqual(9);
      expect(metrics.algorithmStrengths.symmetricEncryption).toBeGreaterThanOrEqual(9);
      expect(metrics.algorithmStrengths.keyDerivation).toBeGreaterThanOrEqual(9);
    });
  });

  describe('Threat Detection', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should detect signature forgery attempts', async () => {
      const conversationId = 'conv-threat-test';
      const message = 'Legitimate message';
      
      const encrypted = await quantumE2EE.encryptMessage(message, conversationId);
      
      // Tamper with signature
      encrypted.signature[0] = encrypted.signature[0] ^ 1;
      
      await expect(async () => {
        await quantumE2EE.decryptMessage(encrypted, conversationId);
      }).rejects.toThrow(/signature verification failed/i);
    });

    it('should detect replay attacks', async () => {
      const conversationId = 'conv-replay-test';
      const message = 'Test message';
      
      const encrypted = await quantumE2EE.encryptMessage(message, conversationId);
      
      // First decryption should work
      const decrypted1 = await quantumE2EE.decryptMessage(encrypted, conversationId);
      expect(decrypted1).toBe(message);
      
      // Second decryption of same message should fail (replay detection)
      await expect(async () => {
        await quantumE2EE.decryptMessage(encrypted, conversationId);
      }).rejects.toThrow(/replay/i);
    });
  });

  describe('Key Management', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should rotate keys successfully', async () => {
      const conversationId = 'conv-rotation-test';
      
      // Get initial key state
      const initialMetrics = await quantumE2EE.getSecurityMetrics();
      
      // Rotate keys
      await quantumE2EE.rotateKeys(conversationId, 'Test rotation');
      
      // Get updated metrics
      const updatedMetrics = await quantumE2EE.getSecurityMetrics();
      
      expect(updatedMetrics.lastKeyRotation).not.toBe(initialMetrics.lastKeyRotation);
    });

    it('should create and restore backups', async () => {
      const password = 'secure-backup-password-2025';
      
      // Create backup
      const backup = await quantumE2EE.createBackup(password);
      expect(backup).toBeDefined();
      expect(backup.length).toBeGreaterThan(100);
      
      // Clear current keys
      await quantumE2EE.initializeDevice('new-user'); // Reset state
      
      // Restore from backup
      const restored = await quantumE2EE.restoreFromBackup(backup, password);
      expect(restored).toBe(true);
    });

    it('should fail backup restoration with wrong password', async () => {
      const correctPassword = 'correct-password';
      const wrongPassword = 'wrong-password';
      
      const backup = await quantumE2EE.createBackup(correctPassword);
      
      await expect(async () => {
        await quantumE2EE.restoreFromBackup(backup, wrongPassword);
      }).rejects.toThrow();
    });
  });

  describe('Performance', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should encrypt messages efficiently', async () => {
      const conversationId = 'conv-performance-test';
      const message = 'Performance test message';
      const iterations = 10;
      
      const startTime = Date.now();
      
      for (let i = 0; i < iterations; i++) {
        await quantumE2EE.encryptMessage(`${message} ${i}`, conversationId);
      }
      
      const endTime = Date.now();
      const averageTime = (endTime - startTime) / iterations;
      
      // Should encrypt in under 100ms per message on average
      expect(averageTime).toBeLessThan(100);
    });

    it('should handle bulk operations efficiently', async () => {
      const conversationId = 'conv-bulk-test';
      const messageCount = 50;
      const messages = Array.from({ length: messageCount }, (_, i) => `Bulk message ${i}`);
      
      const startTime = Date.now();
      
      // Encrypt all messages
      const encrypted = await Promise.all(
        messages.map(msg => quantumE2EE.encryptMessage(msg, conversationId))
      );
      
      // Decrypt all messages
      const decrypted = await Promise.all(
        encrypted.map(enc => quantumE2EE.decryptMessage(enc, conversationId))
      );
      
      const endTime = Date.now();
      const totalTime = endTime - startTime;
      
      // Verify all messages were processed correctly
      expect(decrypted).toEqual(messages);
      
      // Should process all messages in under 5 seconds
      expect(totalTime).toBeLessThan(5000);
    });
  });

  describe('Security Audit', () => {
    beforeEach(async () => {
      await quantumE2EE.initializeDevice('test-user');
    });

    it('should generate comprehensive audit report', async () => {
      // Perform some operations to generate audit data
      await quantumE2EE.encryptMessage('Test message', 'conv-audit');
      await quantumE2EE.rotateKeys('conv-audit', 'Test rotation');
      
      const audit = await quantumE2EE.exportSecurityAudit();
      
      expect(audit).toBeDefined();
      expect(audit.timestamp).toBeDefined();
      expect(audit.securityMetrics).toBeDefined();
      expect(audit.algorithmInfo).toBeDefined();
      expect(audit.operationalMetrics).toBeDefined();
      expect(audit.threatAnalysis).toBeDefined();
      
      // Check specific audit content
      expect(audit.algorithmInfo.keyEncapsulation).toBe('ML-KEM-1024');
      expect(audit.algorithmInfo.digitalSignature).toBe('ML-DSA-87');
      expect(audit.algorithmInfo.symmetricEncryption).toBe('XChaCha20-Poly1305');
    });
  });
});

describe('QuantumKeyExchangeProtocol', () => {
  let quantumE2EE: QuantumSafeE2EE;
  let keyExchange: QuantumKeyExchangeProtocol;

  beforeEach(async () => {
    quantumE2EE = new QuantumSafeE2EE();
    keyExchange = new QuantumKeyExchangeProtocol(quantumE2EE);
    await quantumE2EE.initializeDevice('test-user');
  });

  describe('Key Exchange Process', () => {
    it('should complete full key exchange protocol', async () => {
      const targetUserId = 'target-user';
      const conversationId = 'conv-key-exchange';

      // Initiate key exchange
      const request = await keyExchange.initiateKeyExchange(targetUserId, conversationId);
      
      expect(request.sessionId).toBeDefined();
      expect(request.publicKey).toBeInstanceOf(Uint8Array);
      expect(request.signature).toBeInstanceOf(Uint8Array);
      expect(request.nonce).toBeInstanceOf(Uint8Array);

      // Process request and generate response
      const response = await keyExchange.processKeyExchangeRequest(request);
      
      expect(response.sessionId).toBe(request.sessionId);
      expect(response.encapsulatedSecret).toBeInstanceOf(Uint8Array);
      expect(response.signature).toBeInstanceOf(Uint8Array);

      // Process response to get shared secret
      const sharedSecret = await keyExchange.processKeyExchangeResponse(response);
      
      expect(sharedSecret).toBeInstanceOf(Uint8Array);
      expect(sharedSecret.length).toBeGreaterThan(0);
    });

    it('should derive conversation keys from shared secret', async () => {
      const targetUserId = 'target-user';
      const conversationId = 'conv-derive-keys';
      const participantIds = ['user1', 'user2'];

      // Complete key exchange
      const request = await keyExchange.initiateKeyExchange(targetUserId, conversationId);
      const response = await keyExchange.processKeyExchangeRequest(request);
      await keyExchange.processKeyExchangeResponse(response);

      // Derive conversation keys
      const keys = await keyExchange.deriveConversationKeys(
        request.sessionId,
        conversationId,
        participantIds
      );

      expect(keys.encryptionKey).toBeInstanceOf(Uint8Array);
      expect(keys.macKey).toBeInstanceOf(Uint8Array);
      expect(keys.keyId).toBeDefined();
      expect(keys.encryptionKey.length).toBe(32);
      expect(keys.macKey.length).toBe(32);
    });

    it('should handle session expiration', async () => {
      // Mock time to simulate expired session
      const originalDate = Date.now;
      Date.now = jest.fn(() => originalDate() + 400000); // 6+ minutes in future

      const targetUserId = 'target-user';
      const conversationId = 'conv-expired';

      await expect(async () => {
        const request = await keyExchange.initiateKeyExchange(targetUserId, conversationId);
        await keyExchange.processKeyExchangeRequest(request);
      }).rejects.toThrow(/expired/i);

      // Restore original Date.now
      Date.now = originalDate;
    });

    it('should detect invalid signatures', async () => {
      const targetUserId = 'target-user';
      const conversationId = 'conv-invalid-sig';

      const request = await keyExchange.initiateKeyExchange(targetUserId, conversationId);
      
      // Tamper with signature
      request.signature[0] = request.signature[0] ^ 1;

      await expect(async () => {
        await keyExchange.processKeyExchangeRequest(request);
      }).rejects.toThrow(/signature/i);
    });
  });

  describe('Session Management', () => {
    it('should track active sessions', async () => {
      const initialCount = keyExchange.getActiveSessionCount();
      
      const request = await keyExchange.initiateKeyExchange('user1', 'conv1');
      
      expect(keyExchange.getActiveSessionCount()).toBe(initialCount + 1);
    });

    it('should clear expired sessions', async () => {
      // Create some sessions
      await keyExchange.initiateKeyExchange('user1', 'conv1');
      await keyExchange.initiateKeyExchange('user2', 'conv2');
      
      const sessionCount = keyExchange.getActiveSessionCount();
      expect(sessionCount).toBeGreaterThanOrEqual(2);
      
      // Mock time passage
      const originalDate = Date.now;
      Date.now = jest.fn(() => originalDate() + 400000); // 6+ minutes in future
      
      const clearedCount = keyExchange.clearExpiredSessions();
      expect(clearedCount).toBeGreaterThanOrEqual(2);
      
      // Restore original Date.now
      Date.now = originalDate;
    });
  });
});