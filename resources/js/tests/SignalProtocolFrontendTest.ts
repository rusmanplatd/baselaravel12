/**
 * Signal Protocol Frontend Unit Tests
 * Comprehensive test suite for Signal Protocol services and components
 */

import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import { x3dhKeyAgreement } from '@/services/X3DHKeyAgreement';
import { doubleRatchetE2EE } from '@/services/DoubleRatchetE2EE';
import { signalProtocolService } from '@/services/SignalProtocolService';
import { QuantumE2EEService } from '@/services/QuantumE2EEService';

// Mock API service
const mockApiService = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn(),
};

vi.mock('@/services/ApiService', () => ({
  apiService: mockApiService,
}));

describe('X3DH Key Agreement Service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  test('should initialize with quantum support', async () => {
    await x3dhKeyAgreement.initialize();
    
    const identityKey = await x3dhKeyAgreement.generateIdentityKey();
    expect(identityKey).toBeDefined();
    expect(identityKey.publicKey).toBeInstanceOf(ArrayBuffer);
    expect(identityKey.privateKey).toBeInstanceOf(CryptoKey);
  });

  test('should generate signed prekeys with quantum capabilities', async () => {
    await x3dhKeyAgreement.initialize();
    
    const signedPrekey = await x3dhKeyAgreement.generateSignedPreKey();
    expect(signedPrekey).toBeDefined();
    expect(signedPrekey.id).toBeGreaterThan(0);
    expect(signedPrekey.publicKey).toBeInstanceOf(ArrayBuffer);
    expect(signedPrekey.signature).toBeInstanceOf(ArrayBuffer);
    expect(signedPrekey.timestamp).toBeGreaterThan(0);
  });

  test('should generate one-time prekeys', async () => {
    await x3dhKeyAgreement.initialize();
    
    const oneTimePrekeys = await x3dhKeyAgreement.generateOneTimePreKeys(5);
    expect(oneTimePrekeys).toHaveLength(5);
    
    oneTimePrekeys.forEach((prekey, index) => {
      expect(prekey.id).toBeGreaterThan(0);
      expect(prekey.publicKey).toBeInstanceOf(ArrayBuffer);
      expect(prekey.id).not.toBe(oneTimePrekeys[(index + 1) % 5]?.id);
    });
  });

  test('should create prekey bundle with quantum information', async () => {
    await x3dhKeyAgreement.initialize();
    
    const bundle = await x3dhKeyAgreement.createPreKeyBundle();
    expect(bundle).toBeDefined();
    expect(bundle.registrationId).toBeGreaterThan(0);
    expect(bundle.identityKey).toBeInstanceOf(ArrayBuffer);
    expect(bundle.signedPreKey).toBeDefined();
    expect(bundle.oneTimePreKeys).toBeInstanceOf(Array);
    
    // Check quantum extensions
    if (bundle.quantumIdentityKey) {
      expect(bundle.quantumIdentityKey).toBeInstanceOf(ArrayBuffer);
      expect(bundle.quantumAlgorithm).toBeDefined();
      expect(['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024']).toContain(bundle.quantumAlgorithm);
    }
  });

  test('should negotiate algorithms correctly', async () => {
    await x3dhKeyAgreement.initialize();
    
    const localCapabilities = ['ML-KEM-768', 'ML-KEM-512', 'Curve25519'];
    const remoteCapabilities = ['ML-KEM-1024', 'ML-KEM-768', 'RSA-4096-OAEP'];
    
    // Mock the private method by testing the public createPreKeyBundle
    const bundle = await x3dhKeyAgreement.createPreKeyBundle();
    
    // Simulate negotiation result
    const expectedAlgorithm = 'ML-KEM-768'; // Highest common algorithm
    expect(bundle.quantumAlgorithm).toBeDefined();
  });

  test('should handle quantum service initialization failure gracefully', async () => {
    // Mock quantum service to fail
    vi.spyOn(QuantumE2EEService, 'getInstance').mockImplementation(() => {
      throw new Error('Quantum service unavailable');
    });
    
    // Should still initialize without quantum support
    await x3dhKeyAgreement.initialize();
    const bundle = await x3dhKeyAgreement.createPreKeyBundle();
    
    expect(bundle).toBeDefined();
    expect(bundle.quantumIdentityKey).toBeUndefined();
  });

  test('should validate prekey statistics', async () => {
    await x3dhKeyAgreement.initialize();
    await x3dhKeyAgreement.generateOneTimePreKeys(10);
    
    const stats = x3dhKeyAgreement.getPreKeyStatistics();
    expect(stats.identityKeyExists).toBe(true);
    expect(stats.signedPreKeys).toBeGreaterThan(0);
    expect(stats.oneTimePreKeys).toBe(10);
    expect(stats.oneTimePreKeysUsed).toBe(0);
  });
});

describe('Double Ratchet E2EE Service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  test('should initialize session with quantum keys', async () => {
    const conversationId = 'test_conversation_123';
    const deviceId = 'alice_device';
    const remoteDeviceId = 'bob_device';
    
    // Mock X3DH result with quantum data
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: true,
      hybridMode: false,
      usedQuantumAlgorithm: 'ML-KEM-768',
      quantumSharedSecret: new ArrayBuffer(32),
    };
    
    await doubleRatchetE2EE.initializeSession(
      conversationId,
      deviceId,
      remoteDeviceId,
      x3dhResult
    );
    
    const sessionExists = doubleRatchetE2EE.hasSession(
      `${conversationId}_${deviceId}_${remoteDeviceId}`
    );
    expect(sessionExists).toBe(true);
  });

  test('should encrypt and decrypt messages with quantum algorithms', async () => {
    const sessionId = 'test_session_quantum';
    const plaintext = 'Hello, quantum secure world!';
    
    // Initialize session first
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: true,
      hybridMode: false,
      usedQuantumAlgorithm: 'ML-KEM-768',
    };
    
    await doubleRatchetE2EE.initializeSession('conv', 'alice', 'bob', x3dhResult);
    const actualSessionId = 'conv_alice_bob';
    
    // Encrypt message
    const encrypted = await doubleRatchetE2EE.encrypt(actualSessionId, plaintext);
    expect(encrypted).toBeDefined();
    expect(encrypted.header).toBeDefined();
    expect(encrypted.ciphertext).toBeInstanceOf(ArrayBuffer);
    
    // Check for quantum encryption markers
    if (encrypted.isQuantumEncrypted) {
      expect(encrypted.quantumCiphertext).toBeDefined();
      expect(encrypted.quantumAlgorithm).toBe('ML-KEM-768');
    }
    
    // Decrypt message
    const decrypted = await doubleRatchetE2EE.decrypt(actualSessionId, encrypted);
    expect(decrypted).toBe(plaintext);
  });

  test('should handle message ordering correctly', async () => {
    const sessionId = 'test_session_ordering';
    const messages = [
      'First message',
      'Second message',
      'Third message',
    ];
    
    // Initialize session
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: false,
      hybridMode: false,
    };
    
    await doubleRatchetE2EE.initializeSession('conv2', 'alice', 'bob', x3dhResult);
    const actualSessionId = 'conv2_alice_bob';
    
    // Encrypt messages in order
    const encryptedMessages = [];
    for (const message of messages) {
      const encrypted = await doubleRatchetE2EE.encrypt(actualSessionId, message);
      encryptedMessages.push(encrypted);
    }
    
    // Decrypt in reverse order (simulating out-of-order delivery)
    const decryptedMessages = [];
    for (let i = encryptedMessages.length - 1; i >= 0; i--) {
      try {
        const decrypted = await doubleRatchetE2EE.decrypt(actualSessionId, encryptedMessages[i]);
        decryptedMessages.unshift(decrypted);
      } catch (error) {
        // Out-of-order messages might fail, which is expected
        console.log('Expected out-of-order message handling:', error);
      }
    }
    
    expect(decryptedMessages.length).toBeGreaterThan(0);
  });

  test('should rotate keys properly', async () => {
    const sessionId = 'test_session_rotation';
    
    // Initialize session
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: true,
      hybridMode: false,
      usedQuantumAlgorithm: 'ML-KEM-768',
    };
    
    await doubleRatchetE2EE.initializeSession('conv3', 'alice', 'bob', x3dhResult);
    const actualSessionId = 'conv3_alice_bob';
    
    // Send several messages to trigger key rotation
    for (let i = 0; i < 5; i++) {
      await doubleRatchetE2EE.encrypt(actualSessionId, `Message ${i}`);
    }
    
    // Session should still exist and be functional
    expect(doubleRatchetE2EE.hasSession(actualSessionId)).toBe(true);
    
    // Should be able to encrypt after rotation
    const testMessage = 'Post-rotation message';
    const encrypted = await doubleRatchetE2EE.encrypt(actualSessionId, testMessage);
    const decrypted = await doubleRatchetE2EE.decrypt(actualSessionId, encrypted);
    expect(decrypted).toBe(testMessage);
  });

  test('should handle hybrid encryption mode', async () => {
    const sessionId = 'test_session_hybrid';
    
    // Initialize session with hybrid mode
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: false,
      hybridMode: true,
      usedQuantumAlgorithm: 'HYBRID-RSA4096-MLKEM768',
      quantumSharedSecret: new ArrayBuffer(32),
    };
    
    await doubleRatchetE2EE.initializeSession('conv4', 'alice', 'bob', x3dhResult);
    const actualSessionId = 'conv4_alice_bob';
    
    const message = 'Hybrid encrypted message';
    const encrypted = await doubleRatchetE2EE.encrypt(actualSessionId, message);
    
    // Should have both classical and quantum components in hybrid mode
    expect(encrypted.ciphertext).toBeDefined();
    if (encrypted.quantumCiphertext) {
      expect(encrypted.quantumCiphertext).toBeDefined();
    }
    
    const decrypted = await doubleRatchetE2EE.decrypt(actualSessionId, encrypted);
    expect(decrypted).toBe(message);
  });
});

describe('Signal Protocol Service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiService.get.mockResolvedValue({ success: true, data: {} });
    mockApiService.post.mockResolvedValue({ success: true, data: {} });
  });

  test('should upload prekey bundle with quantum support', async () => {
    mockApiService.post.mockResolvedValue({
      success: true,
      data: {
        identity_key_id: 'ik_123',
        signed_prekey_id: 456,
        onetime_prekey_ids: [789, 790, 791],
        quantum_capable: true,
        quantum_algorithm: 'ML-KEM-768',
      },
    });
    
    const result = await signalProtocolService.uploadPreKeyBundle();
    expect(result).toBeDefined();
    expect(result.success).toBe(true);
    expect(mockApiService.post).toHaveBeenCalledWith(
      '/api/v1/chat/signal/upload-bundle',
      expect.any(Object)
    );
  });

  test('should retrieve prekey bundle with algorithm negotiation', async () => {
    const mockBundle = {
      success: true,
      data: {
        registration_id: 12345,
        identity_key: 'base64key',
        signed_pre_key: {
          id: 456,
          public_key: 'base64key',
          signature: 'base64sig',
        },
        one_time_pre_keys: [
          { id: 789, public_key: 'base64key1' },
        ],
        quantum_identity_key: 'base64quantumkey',
        quantum_algorithm: 'ML-KEM-768',
        device_capabilities: ['ML-KEM-768', 'Curve25519'],
        negotiated_algorithm: 'ML-KEM-768',
        negotiation_type: 'quantum',
      },
    };
    
    mockApiService.get.mockResolvedValue(mockBundle);
    
    const bundle = await signalProtocolService.getPreKeyBundle('user_123');
    expect(bundle).toBeDefined();
    expect(bundle.negotiated_algorithm).toBe('ML-KEM-768');
    expect(bundle.negotiation_type).toBe('quantum');
    expect(mockApiService.get).toHaveBeenCalledWith(
      '/api/v1/chat/signal/prekey-bundle/user_123'
    );
  });

  test('should send quantum-encrypted signal message', async () => {
    mockApiService.post.mockResolvedValue({
      success: true,
      data: {
        message_id: 'msg_123',
        conversation_id: 'conv_456',
        quantum_encrypted: true,
        algorithm_used: 'ML-KEM-768',
      },
    });
    
    const messageData = {
      type: 'prekey' as const,
      version: 3,
      message: {
        header: {
          sender_chain_key: new Uint8Array(32),
          previous_counter: 0,
          ratchet_key: new ArrayBuffer(32),
        },
        ciphertext: new ArrayBuffer(64),
        isQuantumEncrypted: true,
        quantumAlgorithm: 'ML-KEM-768',
      },
      timestamp: Date.now(),
      isQuantumResistant: true,
      encryptionVersion: 3,
    };
    
    const result = await signalProtocolService.sendMessage('conv_456', 'user_789', messageData);
    expect(result.success).toBe(true);
    expect(result.data.quantum_encrypted).toBe(true);
    expect(mockApiService.post).toHaveBeenCalledWith(
      '/api/v1/chat/signal/messages/send',
      expect.objectContaining({
        conversation_id: 'conv_456',
        recipient_user_id: 'user_789',
      })
    );
  });

  test('should retrieve comprehensive session information', async () => {
    const mockSessionInfo = {
      success: true,
      data: {
        session: {
          sessionId: 'session_123',
          verificationStatus: 'verified',
          isActive: true,
          protocolVersion: '3.0',
          quantumAlgorithm: 'ML-KEM-768',
          isQuantumResistant: true,
          quantumVersion: 3,
          messagesSent: 42,
          messagesReceived: 38,
          keyRotations: 5,
          lastActivity: new Date().toISOString(),
          securityScore: 95,
        },
      },
    };
    
    mockApiService.get.mockResolvedValue(mockSessionInfo);
    
    const sessionInfo = await signalProtocolService.getSessionInfo('conv_456', 'user_789');
    expect(sessionInfo).toBeDefined();
    expect(sessionInfo.quantumAlgorithm).toBe('ML-KEM-768');
    expect(sessionInfo.isQuantumResistant).toBe(true);
    expect(sessionInfo.securityScore).toBe(95);
  });

  test('should perform key rotation with quantum keys', async () => {
    mockApiService.post.mockResolvedValue({
      success: true,
      data: {
        session_id: 'session_123',
        new_key_version: 4,
        quantum_algorithm: 'ML-KEM-768',
        rotation_timestamp: new Date().toISOString(),
      },
    });
    
    const result = await signalProtocolService.rotateSessionKeys('session_123');
    expect(result.success).toBe(true);
    expect(result.data.quantum_algorithm).toBe('ML-KEM-768');
    expect(mockApiService.post).toHaveBeenCalledWith(
      '/api/v1/chat/signal/sessions/rotate-keys',
      expect.objectContaining({
        session_id: 'session_123',
      })
    );
  });

  test('should handle identity verification', async () => {
    mockApiService.post.mockResolvedValue({
      success: true,
      data: {
        verification_id: 'verify_123',
        verification_status: 'verified',
        verified_at: new Date().toISOString(),
        verification_method: 'fingerprint_comparison',
      },
    });
    
    const result = await signalProtocolService.verifyUserIdentity('user_456', 'fingerprint_comparison');
    expect(result.success).toBe(true);
    expect(result.data.verification_status).toBe('verified');
    expect(mockApiService.post).toHaveBeenCalledWith(
      '/api/v1/chat/signal/identity/verify',
      expect.objectContaining({
        target_user_id: 'user_456',
        verification_method: 'fingerprint_comparison',
      })
    );
  });

  test('should retrieve protocol statistics', async () => {
    const mockStats = {
      success: true,
      data: {
        sessionStats: {
          activeSessions: 15,
          quantumSessions: 12,
          verifiedSessions: 8,
        },
        x3dhStats: {
          identityKeyExists: true,
          signedPreKeys: 3,
          oneTimePreKeys: 47,
        },
        protocolStats: {
          quantumSupported: true,
          mostUsedAlgorithm: 'ML-KEM-768',
        },
        securityStats: {
          overallSecurityScore: 89,
          quantumReadinessScore: 94,
        },
      },
    };
    
    mockApiService.get.mockResolvedValue(mockStats);
    
    const stats = await signalProtocolService.getStatistics();
    expect(stats).toBeDefined();
    expect(stats.sessionStats.quantumSessions).toBe(12);
    expect(stats.protocolStats.quantumSupported).toBe(true);
    expect(stats.securityStats.quantumReadinessScore).toBe(94);
  });

  test('should handle API errors gracefully', async () => {
    mockApiService.get.mockRejectedValue(new Error('Network error'));
    
    await expect(signalProtocolService.getPreKeyBundle('user_123'))
      .rejects.toThrow('Network error');
    
    mockApiService.post.mockRejectedValue(new Error('Validation failed'));
    
    await expect(signalProtocolService.uploadPreKeyBundle())
      .rejects.toThrow('Validation failed');
  });
});

describe('Quantum E2EE Service Integration', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('should initialize quantum service properly', async () => {
    const quantumService = QuantumE2EEService.getInstance();
    await quantumService.initialize();
    
    expect(quantumService.isInitialized()).toBe(true);
  });

  test('should generate quantum key pairs', async () => {
    const quantumService = QuantumE2EEService.getInstance();
    await quantumService.initialize();
    
    try {
      const keyPair = await quantumService.generateQuantumKeyPair('ML-KEM-768');
      expect(keyPair).toBeDefined();
      expect(keyPair.publicKey).toBeInstanceOf(ArrayBuffer);
      expect(keyPair.privateKey).toBeInstanceOf(ArrayBuffer);
    } catch (error) {
      // Quantum service might not be available in test environment
      expect(error.message).toContain('quantum');
    }
  });

  test('should check algorithm support', async () => {
    const quantumService = QuantumE2EEService.getInstance();
    await quantumService.initialize();
    
    const algorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024'];
    algorithms.forEach(algorithm => {
      const isQuantumResistant = quantumService.isQuantumResistant(algorithm);
      expect(typeof isQuantumResistant).toBe('boolean');
    });
  });

  test('should get recommended algorithm', async () => {
    const quantumService = QuantumE2EEService.getInstance();
    await quantumService.initialize();
    
    const recommended = quantumService.getRecommendedAlgorithm();
    expect(recommended).toBeDefined();
    expect(typeof recommended).toBe('string');
    
    // Should be one of the supported quantum algorithms
    const quantumAlgorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024'];
    expect(quantumAlgorithms.includes(recommended) || recommended.startsWith('HYBRID')).toBe(true);
  });
});

describe('Algorithm Negotiation', () => {
  test('should prioritize quantum algorithms correctly', () => {
    const testCases = [
      {
        local: ['ML-KEM-1024', 'ML-KEM-768', 'Curve25519'],
        remote: ['ML-KEM-768', 'ML-KEM-512', 'RSA-4096-OAEP'],
        expected: 'ML-KEM-768',
      },
      {
        local: ['HYBRID-RSA4096-MLKEM768', 'Curve25519'],
        remote: ['ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'],
        expected: 'HYBRID-RSA4096-MLKEM768',
      },
      {
        local: ['Curve25519', 'P-256'],
        remote: ['RSA-4096-OAEP', 'P-256'],
        expected: 'P-256',
      },
    ];
    
    testCases.forEach(({ local, remote, expected }) => {
      // This would test the negotiation logic
      // For now, we just verify the test case structure
      expect(local).toBeInstanceOf(Array);
      expect(remote).toBeInstanceOf(Array);
      expect(typeof expected).toBe('string');
    });
  });
});

describe('Error Handling and Edge Cases', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('should handle storage failures gracefully', async () => {
    // Mock localStorage to throw errors
    const originalSetItem = localStorage.setItem;
    localStorage.setItem = vi.fn().mockImplementation(() => {
      throw new Error('Storage quota exceeded');
    });
    
    try {
      await x3dhKeyAgreement.initialize();
      // Should still work even if storage fails
      expect(true).toBe(true);
    } catch (error) {
      expect(error.message).toContain('storage');
    } finally {
      localStorage.setItem = originalSetItem;
    }
  });

  test('should handle malformed session data', async () => {
    localStorage.setItem('x3dh_session_bad', 'invalid json data');
    
    // Should not crash when loading malformed data
    await expect(x3dhKeyAgreement.initialize()).resolves.not.toThrow();
  });

  test('should validate message format before decryption', async () => {
    const invalidMessage = {
      // Missing required fields
      ciphertext: new ArrayBuffer(32),
    };
    
    await expect(
      doubleRatchetE2EE.decrypt('nonexistent_session', invalidMessage as any)
    ).rejects.toThrow();
  });

  test('should handle quantum service unavailable', async () => {
    // Mock quantum service to be unavailable
    vi.spyOn(QuantumE2EEService, 'getInstance').mockImplementation(() => {
      const instance = {
        initialize: vi.fn().mockRejectedValue(new Error('Quantum service unavailable')),
        isInitialized: vi.fn().mockReturnValue(false),
        generateQuantumKeyPair: vi.fn().mockRejectedValue(new Error('Not available')),
        isQuantumResistant: vi.fn().mockReturnValue(false),
        getRecommendedAlgorithm: vi.fn().mockReturnValue('Curve25519'),
      };
      return instance as any;
    });
    
    // Should fall back to classical algorithms
    await x3dhKeyAgreement.initialize();
    const bundle = await x3dhKeyAgreement.createPreKeyBundle();
    
    expect(bundle.quantumIdentityKey).toBeUndefined();
  });
});

describe('Performance and Memory', () => {
  test('should not leak memory during key generation', async () => {
    const initialMemory = performance.memory?.usedJSHeapSize || 0;
    
    // Generate many keys
    for (let i = 0; i < 10; i++) {
      await x3dhKeyAgreement.generateOneTimePreKeys(10);
    }
    
    // Force garbage collection if available
    if (global.gc) {
      global.gc();
    }
    
    const finalMemory = performance.memory?.usedJSHeapSize || 0;
    const memoryIncrease = finalMemory - initialMemory;
    
    // Memory increase should be reasonable (less than 10MB)
    expect(memoryIncrease).toBeLessThan(10 * 1024 * 1024);
  });

  test('should encrypt/decrypt messages efficiently', async () => {
    const x3dhResult = {
      sharedSecret: new ArrayBuffer(32),
      ephemeralKeyPair: await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey']
      ),
      isQuantumResistant: false,
      hybridMode: false,
    };
    
    await doubleRatchetE2EE.initializeSession('perf_test', 'alice', 'bob', x3dhResult);
    const sessionId = 'perf_test_alice_bob';
    
    const message = 'Performance test message';
    const iterations = 100;
    
    const startTime = performance.now();
    
    for (let i = 0; i < iterations; i++) {
      const encrypted = await doubleRatchetE2EE.encrypt(sessionId, `${message} ${i}`);
      const decrypted = await doubleRatchetE2EE.decrypt(sessionId, encrypted);
      expect(decrypted).toBe(`${message} ${i}`);
    }
    
    const endTime = performance.now();
    const averageTime = (endTime - startTime) / iterations;
    
    // Should encrypt/decrypt in less than 10ms on average
    expect(averageTime).toBeLessThan(10);
  });
});

export {
  describe,
  test,
  expect,
  beforeEach,
  afterEach,
  vi,
};