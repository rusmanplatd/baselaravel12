/**
 * Double Ratchet End-to-End Encryption Implementation
 * Based on Signal Protocol for forward secrecy and break-in recovery
 */

import { E2EEError, E2EEErrorCode } from './E2EEErrors';

export interface RatchetKeys {
  rootKey: CryptoKey;
  chainKey: CryptoKey;
  sendingChainKey?: CryptoKey;
  receivingChainKey?: CryptoKey;
  messageKey?: CryptoKey;
}

export interface RatchetState {
  DHs: CryptoKey;  // Our sending DH key pair
  DHr: CryptoKey;  // Their receiving DH public key
  RK: CryptoKey;   // Root key
  CKs: CryptoKey;  // Sending chain key
  CKr?: CryptoKey; // Receiving chain key
  Ns: number;      // Number of messages in sending chain
  Nr: number;      // Number of messages in receiving chain
  PN: number;      // Number of messages in previous sending chain
  MKSKIPPED: Map<string, CryptoKey>; // Skipped message keys
  conversationId: string;
  deviceId: string;
  remoteDeviceId: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface DoubleRatchetMessage {
  header: {
    DH: string;      // Public key as base64
    PN: number;      // Previous chain length
    N: number;       // Message number in current chain
  };
  ciphertext: string;
  iv: string;
  authTag: string;
  timestamp: number;
}

export interface RatchetSession {
  sessionId: string;
  conversationId: string;
  participantId: string;
  state: RatchetState;
  isActive: boolean;
}

const MAX_SKIP = 1000; // Maximum number of message keys to skip
const INFO_DH_RATCHET = new TextEncoder().encode('DoubleRatchetDH');
const INFO_MESSAGE_KEYS = new TextEncoder().encode('MessageKeys');
const INFO_CHAIN_KEY = new TextEncoder().encode('ChainKey');

export class DoubleRatchetE2EE {
  private sessions = new Map<string, RatchetState>();
  private readonly STORAGE_KEY = 'double_ratchet_sessions';

  constructor() {
    this.loadSessions();
  }

  /**
   * Initialize a new ratchet session as the sender
   */
  async initializeSession(
    conversationId: string,
    deviceId: string,
    remoteDeviceId: string,
    remotePublicKey: CryptoKey,
    sharedKey: CryptoKey
  ): Promise<string> {
    try {
      // Generate our initial DH key pair
      const dhKeyPair = await crypto.subtle.generateKey(
        {
          name: 'ECDH',
          namedCurve: 'P-256',
        },
        true,
        ['deriveKey']
      );

      // Perform initial DH calculation
      const dhOutput = await crypto.subtle.deriveKey(
        {
          name: 'ECDH',
          public: remotePublicKey,
        },
        dhKeyPair.privateKey,
        {
          name: 'HKDF',
          hash: 'SHA-256',
        },
        false,
        ['deriveKey']
      );

      // Derive root key and sending chain key
      const { rootKey, chainKey } = await this.kdfRK(sharedKey, dhOutput);

      const sessionId = `${conversationId}_${deviceId}_${remoteDeviceId}`;
      
      const state: RatchetState = {
        DHs: dhKeyPair.privateKey,
        DHr: remotePublicKey,
        RK: rootKey,
        CKs: chainKey,
        Ns: 0,
        Nr: 0,
        PN: 0,
        MKSKIPPED: new Map(),
        conversationId,
        deviceId,
        remoteDeviceId,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      this.sessions.set(sessionId, state);
      await this.saveSessions();

      return sessionId;
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Initialize a new ratchet session as the receiver
   */
  async initializeSessionReceiver(
    conversationId: string,
    deviceId: string,
    remoteDeviceId: string,
    sharedKey: CryptoKey,
    initialMessage: DoubleRatchetMessage
  ): Promise<string> {
    try {
      // Import remote public key from header
      const remotePublicKey = await this.importPublicKey(initialMessage.header.DH);

      // Generate our DH key pair
      const dhKeyPair = await crypto.subtle.generateKey(
        {
          name: 'ECDH',
          namedCurve: 'P-256',
        },
        true,
        ['deriveKey']
      );

      const sessionId = `${conversationId}_${deviceId}_${remoteDeviceId}`;

      // Initialize with shared key as root key
      const state: RatchetState = {
        DHs: dhKeyPair.privateKey,
        DHr: remotePublicKey,
        RK: sharedKey,
        CKs: await this.generateChainKey(), // Will be updated on first send
        Nr: 0,
        Ns: 0,
        PN: 0,
        MKSKIPPED: new Map(),
        conversationId,
        deviceId,
        remoteDeviceId,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      this.sessions.set(sessionId, state);

      // Perform DH ratchet step to initialize receiving chain
      await this.dhRatchetReceive(sessionId, remotePublicKey);

      // Decrypt the initial message
      await this.decrypt(sessionId, initialMessage);

      await this.saveSessions();
      return sessionId;
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Encrypt a message using the double ratchet
   */
  async encrypt(sessionId: string, plaintext: string): Promise<DoubleRatchetMessage> {
    const state = this.sessions.get(sessionId);
    if (!state) {
      throw E2EEError.sessionNotFound(sessionId);
    }

    try {
      // Generate message key from sending chain
      const { messageKey, newChainKey } = await this.kdfCK(state.CKs);
      state.CKs = newChainKey;

      // Encrypt the message
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const encodedPlaintext = new TextEncoder().encode(plaintext);

      const encryptedData = await crypto.subtle.encrypt(
        {
          name: 'AES-GCM',
          iv: iv,
        },
        messageKey,
        encodedPlaintext
      );

      // Extract auth tag (last 16 bytes)
      const ciphertext = encryptedData.slice(0, -16);
      const authTag = encryptedData.slice(-16);

      // Export current DH public key for header
      const dhPublicKeyRaw = await crypto.subtle.exportKey('raw', 
        await crypto.subtle.importKey('raw', 
          await crypto.subtle.exportKey('pkcs8', state.DHs), 
          { name: 'ECDH', namedCurve: 'P-256' }, 
          true, 
          ['deriveKey']
        )
      );

      const message: DoubleRatchetMessage = {
        header: {
          DH: this.arrayBufferToBase64(dhPublicKeyRaw),
          PN: state.PN,
          N: state.Ns,
        },
        ciphertext: this.arrayBufferToBase64(ciphertext),
        iv: this.arrayBufferToBase64(iv),
        authTag: this.arrayBufferToBase64(authTag),
        timestamp: Date.now(),
      };

      // Increment sending counter
      state.Ns += 1;
      state.updatedAt = new Date();

      await this.saveSessions();

      return message;
    } catch (error) {
      throw E2EEError.encryptionFailed(sessionId, error instanceof Error ? error : undefined);
    }
  }

  /**
   * Decrypt a message using the double ratchet
   */
  async decrypt(sessionId: string, message: DoubleRatchetMessage): Promise<string> {
    const state = this.sessions.get(sessionId);
    if (!state) {
      throw E2EEError.sessionNotFound(sessionId);
    }

    try {
      // Import the DH public key from the header
      const dhPublicKey = await this.importPublicKey(message.header.DH);

      // Check if we need to perform DH ratchet step
      if (!await this.compareKeys(dhPublicKey, state.DHr)) {
        await this.dhRatchetReceive(sessionId, dhPublicKey);
      }

      // Try to decrypt with skipped message keys first
      const skippedKey = state.MKSKIPPED.get(`${message.header.DH}_${message.header.N}`);
      if (skippedKey) {
        state.MKSKIPPED.delete(`${message.header.DH}_${message.header.N}`);
        return await this.decryptWithKey(message, skippedKey);
      }

      // Skip messages if necessary
      if (state.CKr && message.header.N > state.Nr) {
        await this.skipMessageKeys(state, message.header.N);
      }

      // Generate message key
      if (!state.CKr) {
        throw E2EEError.decryptionFailed(sessionId, undefined, 'No receiving chain key');
      }

      const { messageKey, newChainKey } = await this.kdfCK(state.CKr);
      state.CKr = newChainKey;
      state.Nr += 1;

      const decrypted = await this.decryptWithKey(message, messageKey);

      state.updatedAt = new Date();
      await this.saveSessions();

      return decrypted;
    } catch (error) {
      throw E2EEError.decryptionFailed(
        sessionId,
        message.header.N,
        error instanceof Error ? error : undefined
      );
    }
  }

  /**
   * Perform DH ratchet step when receiving a new DH public key
   */
  private async dhRatchetReceive(sessionId: string, remotePublicKey: CryptoKey): Promise<void> {
    const state = this.sessions.get(sessionId);
    if (!state) {
      throw E2EEError.sessionNotFound(sessionId);
    }

    // Store previous sending chain length
    state.PN = state.Ns;
    state.Ns = 0;
    state.Nr = 0;

    // Update receiving DH key
    state.DHr = remotePublicKey;

    // Perform DH operation
    const dhOutput = await crypto.subtle.deriveKey(
      {
        name: 'ECDH',
        public: remotePublicKey,
      },
      state.DHs,
      {
        name: 'HKDF',
        hash: 'SHA-256',
      },
      false,
      ['deriveKey']
    );

    // Update root key and create receiving chain key
    const { rootKey, chainKey } = await this.kdfRK(state.RK, dhOutput);
    state.RK = rootKey;
    state.CKr = chainKey;

    // Generate new DH key pair for sending
    const newDhKeyPair = await crypto.subtle.generateKey(
      {
        name: 'ECDH',
        namedCurve: 'P-256',
      },
      true,
      ['deriveKey']
    );
    state.DHs = newDhKeyPair.privateKey;

    // Perform another DH operation for sending chain
    const dhOutput2 = await crypto.subtle.deriveKey(
      {
        name: 'ECDH',
        public: remotePublicKey,
      },
      newDhKeyPair.privateKey,
      {
        name: 'HKDF',
        hash: 'SHA-256',
      },
      false,
      ['deriveKey']
    );

    // Update root key and create sending chain key
    const { rootKey: newRootKey, chainKey: sendingChainKey } = await this.kdfRK(state.RK, dhOutput2);
    state.RK = newRootKey;
    state.CKs = sendingChainKey;
  }

  /**
   * Skip message keys for out-of-order message handling
   */
  private async skipMessageKeys(state: RatchetState, until: number): Promise<void> {
    if (!state.CKr) return;

    if (state.Nr + MAX_SKIP < until) {
      throw E2EEError.decryptionFailed(
        `${state.conversationId}_${state.deviceId}_${state.remoteDeviceId}`,
        until,
        new Error('Too many skipped messages')
      );
    }

    let chainKey = state.CKr;
    while (state.Nr < until) {
      const { messageKey, newChainKey } = await this.kdfCK(chainKey);
      const dhPublicKeyRaw = await crypto.subtle.exportKey('raw', state.DHr);
      const keyId = `${this.arrayBufferToBase64(dhPublicKeyRaw)}_${state.Nr}`;
      
      state.MKSKIPPED.set(keyId, messageKey);
      chainKey = newChainKey;
      state.Nr += 1;
    }

    state.CKr = chainKey;
  }

  /**
   * Decrypt message with specific key
   */
  private async decryptWithKey(message: DoubleRatchetMessage, key: CryptoKey): Promise<string> {
    const iv = this.base64ToArrayBuffer(message.iv);
    const ciphertext = this.base64ToArrayBuffer(message.ciphertext);
    const authTag = this.base64ToArrayBuffer(message.authTag);

    // Combine ciphertext and auth tag for AES-GCM
    const combined = new Uint8Array(ciphertext.byteLength + authTag.byteLength);
    combined.set(new Uint8Array(ciphertext));
    combined.set(new Uint8Array(authTag), ciphertext.byteLength);

    const decryptedBuffer = await crypto.subtle.decrypt(
      {
        name: 'AES-GCM',
        iv: iv,
      },
      key,
      combined
    );

    return new TextDecoder().decode(decryptedBuffer);
  }

  /**
   * Key derivation function for root key
   */
  private async kdfRK(rootKey: CryptoKey, dhOutput: CryptoKey): Promise<{ rootKey: CryptoKey; chainKey: CryptoKey }> {
    // Use HKDF to derive new root key and chain key
    const expandedKey = await crypto.subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: new Uint8Array(32), // Empty salt
        info: INFO_DH_RATCHET,
      },
      dhOutput,
      {
        name: 'HKDF',
        hash: 'SHA-256',
      },
      true,
      ['deriveKey']
    );

    // Extract 64 bytes and split into two 32-byte keys
    const keyMaterial = await crypto.subtle.exportKey('raw', expandedKey);
    const keyArray = new Uint8Array(keyMaterial);

    if (keyArray.length < 64) {
      // If not enough material, derive more
      const salt = keyArray.slice(0, 32);
      const info = new Uint8Array([...INFO_DH_RATCHET, 1]);
      
      const hkdf1 = await crypto.subtle.importKey('raw', salt, 'HKDF', false, ['deriveKey']);
      const hkdf2 = await crypto.subtle.importKey('raw', salt, 'HKDF', false, ['deriveKey']);

      const rootKeyMaterial = await crypto.subtle.deriveKey(
        { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: new Uint8Array([...info, 1]) },
        hkdf1,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
      );

      const chainKeyMaterial = await crypto.subtle.deriveKey(
        { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: new Uint8Array([...info, 2]) },
        hkdf2,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
      );

      return { rootKey: rootKeyMaterial, chainKey: chainKeyMaterial };
    }

    const rootKeyBytes = keyArray.slice(0, 32);
    const chainKeyBytes = keyArray.slice(32, 64);

    const newRootKey = await crypto.subtle.importKey(
      'raw',
      rootKeyBytes,
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );

    const newChainKey = await crypto.subtle.importKey(
      'raw',
      chainKeyBytes,
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );

    return { rootKey: newRootKey, chainKey: newChainKey };
  }

  /**
   * Key derivation function for chain key
   */
  private async kdfCK(chainKey: CryptoKey): Promise<{ messageKey: CryptoKey; newChainKey: CryptoKey }> {
    // Use the chain key to derive both message key and next chain key
    const constant1 = new Uint8Array([1]);
    const constant2 = new Uint8Array([2]);

    const messageKeyMaterial = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: new Uint8Array(12) },
      chainKey,
      constant1
    );

    const chainKeyMaterial = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: new Uint8Array(12) },
      chainKey,
      constant2
    );

    // Remove auth tag for raw key material
    const messageKeyBytes = messageKeyMaterial.slice(0, -16);
    const chainKeyBytes = chainKeyMaterial.slice(0, -16);

    const messageKey = await crypto.subtle.importKey(
      'raw',
      messageKeyBytes.slice(0, 32),
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );

    const newChainKey = await crypto.subtle.importKey(
      'raw',
      chainKeyBytes.slice(0, 32),
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );

    return { messageKey, newChainKey };
  }

  /**
   * Generate a new chain key
   */
  private async generateChainKey(): Promise<CryptoKey> {
    const keyBytes = crypto.getRandomValues(new Uint8Array(32));
    return await crypto.subtle.importKey(
      'raw',
      keyBytes,
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );
  }

  /**
   * Import a public key from base64
   */
  private async importPublicKey(base64Key: string): Promise<CryptoKey> {
    const keyData = this.base64ToArrayBuffer(base64Key);
    return await crypto.subtle.importKey(
      'raw',
      keyData,
      {
        name: 'ECDH',
        namedCurve: 'P-256',
      },
      false,
      []
    );
  }

  /**
   * Compare two CryptoKeys
   */
  private async compareKeys(key1: CryptoKey, key2: CryptoKey): Promise<boolean> {
    try {
      const raw1 = await crypto.subtle.exportKey('raw', key1);
      const raw2 = await crypto.subtle.exportKey('raw', key2);
      
      const arr1 = new Uint8Array(raw1);
      const arr2 = new Uint8Array(raw2);
      
      if (arr1.length !== arr2.length) return false;
      
      for (let i = 0; i < arr1.length; i++) {
        if (arr1[i] !== arr2[i]) return false;
      }
      
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Rotate session keys for forward secrecy
   */
  async rotateSession(sessionId: string): Promise<void> {
    const state = this.sessions.get(sessionId);
    if (!state) {
      throw E2EEError.sessionNotFound(sessionId);
    }

    try {
      // Generate new DH key pair
      const newDhKeyPair = await crypto.subtle.generateKey(
        {
          name: 'ECDH',
          namedCurve: 'P-256',
        },
        true,
        ['deriveKey']
      );

      // Trigger DH ratchet by updating our key
      state.DHs = newDhKeyPair.privateKey;
      state.updatedAt = new Date();

      await this.saveSessions();
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Clean up old skipped keys to prevent memory leaks
   */
  async cleanupSkippedKeys(sessionId: string, olderThanMs: number = 7 * 24 * 60 * 60 * 1000): Promise<number> {
    const state = this.sessions.get(sessionId);
    if (!state) return 0;

    const cutoffTime = Date.now() - olderThanMs;
    let cleaned = 0;

    // This is a simplified cleanup - in practice you'd want to track key timestamps
    for (const [keyId, _] of state.MKSKIPPED) {
      // Simple heuristic: remove keys that are very old message numbers
      const parts = keyId.split('_');
      if (parts.length >= 2) {
        const messageNum = parseInt(parts[1]);
        if (messageNum < state.Nr - 100) { // Keep only recent message numbers
          state.MKSKIPPED.delete(keyId);
          cleaned++;
        }
      }
    }

    if (cleaned > 0) {
      state.updatedAt = new Date();
      await this.saveSessions();
    }

    return cleaned;
  }

  /**
   * Get session information
   */
  getSessionInfo(sessionId: string): Partial<RatchetState> | null {
    const state = this.sessions.get(sessionId);
    if (!state) return null;

    return {
      conversationId: state.conversationId,
      deviceId: state.deviceId,
      remoteDeviceId: state.remoteDeviceId,
      Ns: state.Ns,
      Nr: state.Nr,
      PN: state.PN,
      createdAt: state.createdAt,
      updatedAt: state.updatedAt,
    };
  }

  /**
   * Utility methods
   */
  private arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    const binary = String.fromCharCode(...bytes);
    return btoa(binary);
  }

  private base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  /**
   * Session persistence
   */
  private async saveSessions(): Promise<void> {
    try {
      // Convert sessions to serializable format
      const sessionsData = Array.from(this.sessions.entries()).map(([sessionId, state]) => ({
        sessionId,
        // Note: CryptoKeys cannot be directly serialized
        // In production, you'd use exportKey/importKey for persistence
        conversationId: state.conversationId,
        deviceId: state.deviceId,
        remoteDeviceId: state.remoteDeviceId,
        Ns: state.Ns,
        Nr: state.Nr,
        PN: state.PN,
        createdAt: state.createdAt.toISOString(),
        updatedAt: state.updatedAt.toISOString(),
        // Skipped keys would need special serialization handling
      }));

      // Store in secure storage (this is simplified for demo)
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(sessionsData));
    } catch (error) {
      console.error('Failed to save ratchet sessions:', error);
    }
  }

  private loadSessions(): void {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const sessionsData = JSON.parse(stored);
        // Note: In production, you'd need to reconstruct CryptoKeys
        // This is a simplified implementation
        console.log('Loaded ratchet sessions:', sessionsData);
      }
    } catch (error) {
      console.error('Failed to load ratchet sessions:', error);
    }
  }
}

// Singleton instance
export const doubleRatchetE2EE = new DoubleRatchetE2EE();
export default DoubleRatchetE2EE;