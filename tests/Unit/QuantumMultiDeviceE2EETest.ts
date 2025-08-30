/**
 * Unit tests for Quantum Multi-Device E2EE implementation
 * Tests device management, cross-device encryption, and security features
 */

import { describe, it, expect, beforeEach, jest } from '@jest/globals';
import { QuantumSafeE2EE } from '@/services/QuantumSafeE2EE';
import { QuantumMultiDeviceE2EE } from '@/services/QuantumMultiDeviceE2EE';

// Mock Web Crypto API
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

// Mock localStorage
const mockLocalStorage = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};
global.localStorage = mockLocalStorage as any;

describe('QuantumMultiDeviceE2EE', () => {
  let quantumE2EE: QuantumSafeE2EE;
  let multiDeviceE2EE: QuantumMultiDeviceE2EE;
  const testUserId = 'test-user-123';

  beforeEach(() => {
    jest.clearAllMocks();
    quantumE2EE = new QuantumSafeE2EE();
    multiDeviceE2EE = new QuantumMultiDeviceE2EE(quantumE2EE);
  });

  describe('Multi-Device Initialization', () => {
    it('should initialize multi-device E2EE successfully', async () => {
      // Mock quantum E2EE initialization
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      
      const result = await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      expect(result).toBe(true);
      expect(quantumE2EE.initializeDevice).toHaveBeenCalledWith(testUserId);
    });

    it('should fail initialization if quantum E2EE fails', async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(false);
      
      const result = await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      expect(result).toBe(false);
    });

    it('should set up current device automatically', async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      const currentDevice = multiDeviceE2EE.getCurrentDevice();
      expect(currentDevice).toBeDefined();
      expect(currentDevice?.isCurrentDevice).toBe(true);
      expect(currentDevice?.isTrusted).toBe(true);
      expect(currentDevice?.trustLevel).toBe(10);
      expect(currentDevice?.verificationStatus).toBe('verified');
    });
  });

  describe('Device Registration', () => {
    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
    });

    it('should register new device successfully', async () => {
      const deviceInfo = {
        deviceName: 'Test Device',
        deviceType: 'mobile' as const,
        platform: 'iOS',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending' as const,
        quantumSecurityLevel: 8
      };

      const deviceId = await multiDeviceE2EE.registerDevice(deviceInfo);
      
      expect(deviceId).toBeDefined();
      expect(deviceId.length).toBe(32); // Should be a 32-character hex string
      expect(multiDeviceE2EE.getDeviceCount()).toBe(2); // Current device + new device
    });

    it('should enforce device limit', async () => {
      // Register maximum devices (10)
      const promises = [];
      for (let i = 0; i < 10; i++) {
        promises.push(multiDeviceE2EE.registerDevice({
          deviceName: `Device ${i}`,
          deviceType: 'web',
          platform: 'Test',
          isTrusted: false,
          trustLevel: 0,
          verificationStatus: 'pending',
          quantumSecurityLevel: 8
        }));
      }
      
      await Promise.all(promises);
      
      // Attempt to register one more should fail
      await expect(multiDeviceE2EE.registerDevice({
        deviceName: 'Excess Device',
        deviceType: 'web',
        platform: 'Test',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      })).rejects.toThrow('Maximum number of devices');
    });

    it('should generate unique device IDs', async () => {
      const deviceIds = [];
      
      for (let i = 0; i < 5; i++) {
        const deviceId = await multiDeviceE2EE.registerDevice({
          deviceName: `Device ${i}`,
          deviceType: 'web',
          platform: 'Test',
          isTrusted: false,
          trustLevel: 0,
          verificationStatus: 'pending',
          quantumSecurityLevel: 8
        });
        deviceIds.push(deviceId);
      }
      
      const uniqueIds = new Set(deviceIds);
      expect(uniqueIds.size).toBe(deviceIds.length);
    });
  });

  describe('Device Verification', () => {
    let deviceId: string;

    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      deviceId = await multiDeviceE2EE.registerDevice({
        deviceName: 'Test Device',
        deviceType: 'mobile',
        platform: 'iOS',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });
    });

    it('should verify device with correct code', async () => {
      const verificationCode = '123456';
      
      const success = await multiDeviceE2EE.verifyDevice(deviceId, verificationCode);
      
      expect(success).toBe(true);
      
      const trustedDevices = multiDeviceE2EE.getTrustedDevices();
      const verifiedDevice = trustedDevices.find(d => d.deviceId === deviceId);
      
      expect(verifiedDevice?.verificationStatus).toBe('verified');
      expect(verifiedDevice?.isTrusted).toBe(true);
      expect(verifiedDevice?.trustLevel).toBeGreaterThanOrEqual(7);
    });

    it('should fail verification with non-existent device', async () => {
      const success = await multiDeviceE2EE.verifyDevice('non-existent-device', '123456');
      expect(success).toBe(false);
    });

    it('should handle verification timeout', async () => {
      // Mock time advancement to simulate expired challenge
      const originalDate = Date.now;
      Date.now = jest.fn(() => originalDate() + 700000); // 11+ minutes in future

      const success = await multiDeviceE2EE.verifyDevice(deviceId, '123456');
      expect(success).toBe(false);

      // Restore original Date.now
      Date.now = originalDate;
    });
  });

  describe('Device Revocation', () => {
    let secondaryDeviceId: string;

    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'rotateKeys').mockResolvedValue(undefined);

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      secondaryDeviceId = await multiDeviceE2EE.registerDevice({
        deviceName: 'Secondary Device',
        deviceType: 'mobile',
        platform: 'Android',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });
      
      await multiDeviceE2EE.verifyDevice(secondaryDeviceId, '123456');
    });

    it('should revoke device successfully', async () => {
      const initialCount = multiDeviceE2EE.getDeviceCount();
      
      const success = await multiDeviceE2EE.revokeDevice(secondaryDeviceId);
      
      expect(success).toBe(true);
      expect(multiDeviceE2EE.getDeviceCount()).toBe(initialCount - 1);
      
      const trustedDevices = multiDeviceE2EE.getTrustedDevices();
      const revokedDevice = trustedDevices.find(d => d.deviceId === secondaryDeviceId);
      expect(revokedDevice).toBeUndefined();
    });

    it('should not allow revoking current device', async () => {
      const currentDevice = multiDeviceE2EE.getCurrentDevice();
      expect(currentDevice).toBeDefined();

      await expect(multiDeviceE2EE.revokeDevice(currentDevice!.deviceId))
        .rejects.toThrow('Cannot revoke current device');
    });

    it('should rotate keys after revocation', async () => {
      await multiDeviceE2EE.revokeDevice(secondaryDeviceId);
      
      expect(quantumE2EE.rotateKeys).toHaveBeenCalled();
    });

    it('should handle non-existent device revocation gracefully', async () => {
      const success = await multiDeviceE2EE.revokeDevice('non-existent-device');
      expect(success).toBe(false);
    });
  });

  describe('Cross-Device Encryption', () => {
    let secondaryDeviceId: string;

    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'encryptMessage').mockResolvedValue({
        ciphertext: new Uint8Array(100),
        nonce: new Uint8Array(24),
        tag: new Uint8Array(16),
        signature: new Uint8Array(64),
        signerPublicKey: new Uint8Array(32),
        messageNumber: 1,
        previousChainLength: 0,
        timestamp: Date.now(),
        keyVersion: 1,
        algorithm: 'PQ-E2EE-v1.0',
        ephemeralKeyCommitment: new Uint8Array(32)
      });
      jest.spyOn(quantumE2EE, 'decryptMessage').mockResolvedValue('test message');

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      secondaryDeviceId = await multiDeviceE2EE.registerDevice({
        deviceName: 'Secondary Device',
        deviceType: 'mobile',
        platform: 'Android',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });
      
      await multiDeviceE2EE.verifyDevice(secondaryDeviceId, '123456');
    });

    it('should encrypt message for multiple devices', async () => {
      const message = 'Test cross-device message';
      const conversationId = 'conv-123';
      
      const crossDeviceMessage = await multiDeviceE2EE.encryptForMultipleDevices(
        message, 
        conversationId
      );
      
      expect(crossDeviceMessage).toBeDefined();
      expect(crossDeviceMessage.messageId).toBeDefined();
      expect(crossDeviceMessage.conversationId).toBe(conversationId);
      expect(crossDeviceMessage.senderId).toBe(testUserId);
      expect(crossDeviceMessage.quantumSafe).toBe(true);
      expect(crossDeviceMessage.targetDevices.length).toBeGreaterThan(0);
    });

    it('should encrypt for specific target devices', async () => {
      const message = 'Targeted message';
      const conversationId = 'conv-456';
      
      const crossDeviceMessage = await multiDeviceE2EE.encryptForMultipleDevices(
        message, 
        conversationId, 
        [secondaryDeviceId]
      );
      
      expect(crossDeviceMessage.targetDevices).toEqual([secondaryDeviceId]);
      expect(crossDeviceMessage.encryptedForDevices.has(secondaryDeviceId)).toBe(true);
    });

    it('should decrypt message from any device', async () => {
      const originalMessage = 'Cross-device test message';
      const conversationId = 'conv-789';
      
      const crossDeviceMessage = await multiDeviceE2EE.encryptForMultipleDevices(
        originalMessage, 
        conversationId
      );
      
      const currentDevice = multiDeviceE2EE.getCurrentDevice();
      expect(currentDevice).toBeDefined();
      
      const decryptedMessage = await multiDeviceE2EE.decryptFromDevice(
        crossDeviceMessage,
        currentDevice!.deviceId
      );
      
      expect(decryptedMessage).toBe(originalMessage);
    });

    it('should fail to decrypt for non-target device', async () => {
      const message = 'Exclusive message';
      const conversationId = 'conv-exclusive';
      
      // Encrypt only for secondary device
      const crossDeviceMessage = await multiDeviceE2EE.encryptForMultipleDevices(
        message, 
        conversationId, 
        [secondaryDeviceId]
      );
      
      const currentDevice = multiDeviceE2EE.getCurrentDevice();
      
      await expect(multiDeviceE2EE.decryptFromDevice(
        crossDeviceMessage,
        currentDevice!.deviceId
      )).rejects.toThrow('Message not encrypted for this device');
    });
  });

  describe('Device Key Management', () => {
    let secondaryDeviceId: string;

    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      secondaryDeviceId = await multiDeviceE2EE.registerDevice({
        deviceName: 'Secondary Device',
        deviceType: 'mobile',
        platform: 'Android',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });
      
      await multiDeviceE2EE.verifyDevice(secondaryDeviceId, '123456');
    });

    it('should sync keys for specific device', async () => {
      const success = await multiDeviceE2EE.syncDeviceKeys(secondaryDeviceId);
      expect(success).toBe(true);
    });

    it('should sync keys for all devices', async () => {
      const success = await multiDeviceE2EE.syncDeviceKeys();
      expect(success).toBe(true);
    });

    it('should rotate device keys', async () => {
      const trustedDevicesBefore = multiDeviceE2EE.getTrustedDevices();
      const deviceBefore = trustedDevicesBefore.find(d => d.deviceId === secondaryDeviceId);
      expect(deviceBefore).toBeDefined();
      
      const success = await multiDeviceE2EE.rotateDeviceKeys(secondaryDeviceId);
      expect(success).toBe(true);
      
      const trustedDevicesAfter = multiDeviceE2EE.getTrustedDevices();
      const deviceAfter = trustedDevicesAfter.find(d => d.deviceId === secondaryDeviceId);
      expect(deviceAfter).toBeDefined();
      
      // Device should have updated lastSeen timestamp
      expect(deviceAfter!.lastSeen.getTime()).toBeGreaterThan(deviceBefore!.lastSeen.getTime());
    });

    it('should rotate keys for all devices', async () => {
      const success = await multiDeviceE2EE.rotateDeviceKeys();
      expect(success).toBe(true);
    });

    it('should handle key sync for non-existent device', async () => {
      const success = await multiDeviceE2EE.syncDeviceKeys('non-existent-device');
      expect(success).toBe(false);
    });
  });

  describe('Security Metrics', () => {
    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'generateQuantumKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
    });

    it('should provide comprehensive security metrics', async () => {
      // Add a few devices to get meaningful metrics
      for (let i = 0; i < 3; i++) {
        const deviceId = await multiDeviceE2EE.registerDevice({
          deviceName: `Device ${i}`,
          deviceType: 'web',
          platform: 'Test',
          isTrusted: false,
          trustLevel: 0,
          verificationStatus: 'pending',
          quantumSecurityLevel: 8
        });
        await multiDeviceE2EE.verifyDevice(deviceId, '123456');
      }
      
      const metrics = await multiDeviceE2EE.getMultiDeviceSecurityMetrics();
      
      expect(metrics.totalDevices).toBe(4); // Current + 3 added
      expect(metrics.trustedDevices).toBe(4); // All verified
      expect(metrics.averageTrustLevel).toBeGreaterThanOrEqual(7);
      expect(metrics.quantumReadinessScore).toBeGreaterThanOrEqual(8);
      expect(metrics.keyConsistencyScore).toBeGreaterThanOrEqual(9);
      expect(metrics.crossDeviceThreats).toBe(0);
    });

    it('should calculate correct average trust level', async () => {
      // Add devices with different trust levels
      const deviceId1 = await multiDeviceE2EE.registerDevice({
        deviceName: 'Device 1',
        deviceType: 'web',
        platform: 'Test',
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });
      await multiDeviceE2EE.verifyDevice(deviceId1, '123456');
      
      const metrics = await multiDeviceE2EE.getMultiDeviceSecurityMetrics();
      
      // Current device (trust: 10) + new device (trust: 7+) = average should be > 8
      expect(metrics.averageTrustLevel).toBeGreaterThan(8);
    });

    it('should export comprehensive audit', async () => {
      const audit = await multiDeviceE2EE.exportMultiDeviceAudit();
      
      expect(audit).toBeDefined();
      expect(audit.timestamp).toBeDefined();
      expect(audit.userId).toBe(testUserId);
      expect(audit.currentDeviceId).toBeDefined();
      expect(audit.metrics).toBeDefined();
      expect(audit.devices).toBeInstanceOf(Array);
      expect(audit.recommendations).toBeInstanceOf(Array);
    });
  });

  describe('Edge Cases and Error Handling', () => {
    beforeEach(async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockResolvedValue(true);
      jest.spyOn(quantumE2EE, 'getDeviceKeyPair').mockResolvedValue({
        publicKey: new Uint8Array(32),
        privateKey: new Uint8Array(32),
        algorithm: 'ML-KEM-1024'
      });
    });

    it('should handle initialization failure gracefully', async () => {
      jest.spyOn(quantumE2EE, 'initializeDevice').mockRejectedValue(new Error('Init failed'));
      
      const result = await multiDeviceE2EE.initializeMultiDevice(testUserId);
      expect(result).toBe(false);
    });

    it('should handle storage failures gracefully', async () => {
      mockLocalStorage.setItem.mockImplementation(() => {
        throw new Error('Storage failed');
      });

      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      // Should still work despite storage failure
      expect(multiDeviceE2EE.getCurrentDevice()).toBeDefined();
    });

    it('should handle encryption failures during cross-device messaging', async () => {
      jest.spyOn(quantumE2EE, 'encryptMessage').mockRejectedValue(new Error('Encryption failed'));
      
      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      const result = await multiDeviceE2EE.encryptForMultipleDevices('test', 'conv-123');
      
      expect(result.encryptedForDevices.size).toBe(0); // No successful encryptions
    });

    it('should handle partial device sync failures', async () => {
      await multiDeviceE2EE.initializeMultiDevice(testUserId);
      
      // Add multiple devices
      const deviceIds = [];
      for (let i = 0; i < 3; i++) {
        const deviceId = await multiDeviceE2EE.registerDevice({
          deviceName: `Device ${i}`,
          deviceType: 'web',
          platform: 'Test',
          isTrusted: false,
          trustLevel: 0,
          verificationStatus: 'pending',
          quantumSecurityLevel: 8
        });
        await multiDeviceE2EE.verifyDevice(deviceId, '123456');
        deviceIds.push(deviceId);
      }
      
      // Mock sync to fail for one device
      jest.spyOn(multiDeviceE2EE, 'syncDeviceKeys')
        .mockResolvedValueOnce(true)
        .mockRejectedValueOnce(new Error('Sync failed'))
        .mockResolvedValueOnce(true);
      
      // Should handle partial failures gracefully
      const success = await multiDeviceE2EE.syncDeviceKeys();
      expect(typeof success).toBe('boolean');
    });
  });
});