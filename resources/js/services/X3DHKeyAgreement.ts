/**
 * X3DH (Extended Triple Diffie-Hellman) Key Agreement Protocol Implementation
 * Based on Signal Protocol specification for secure key exchange
 * 
 * This implements the complete X3DH protocol including:
 * - Identity keys (long-term keys)
 * - Signed prekeys (medium-term keys, rotated weekly)
 * - One-time prekeys (short-term keys, used once)
 * - Bundle management and key agreement
 */

import { E2EEError } from './E2EEErrors';
import { apiService } from './ApiService';

export interface IdentityKey {
  keyPair: CryptoKeyPair;
  publicKeyRaw: ArrayBuffer;
  signature?: ArrayBuffer;
}

export interface SignedPreKey {
  keyId: number;
  keyPair: CryptoKeyPair;
  publicKeyRaw: ArrayBuffer;
  signature: ArrayBuffer;
  timestamp: number;
}

export interface OneTimePreKey {
  keyId: number;
  keyPair: CryptoKeyPair;
  publicKeyRaw: ArrayBuffer;
}

export interface PreKeyBundle {
  identityKey: ArrayBuffer;
  signedPreKey: {
    keyId: number;
    publicKey: ArrayBuffer;
    signature: ArrayBuffer;
  };
  oneTimePreKey?: {
    keyId: number;
    publicKey: ArrayBuffer;
  };
  registrationId: number;
}

export interface X3DHResult {
  sharedSecret: CryptoKey;
  associatedData: ArrayBuffer;
  ephemeralKeyPair?: CryptoKeyPair; // Only for initiator
}

export interface X3DHSession {
  sessionId: string;
  localIdentityKey: IdentityKey;
  remoteIdentityKey: ArrayBuffer;
  sharedSecret: CryptoKey;
  associatedData: ArrayBuffer;
  isInitiator: boolean;
  createdAt: Date;
}

const CURVE = 'P-256';
const HASH = 'SHA-256';
const KEY_DERIVATION_INFO = new TextEncoder().encode('Signal_X3DH_20191031');
const PREKEY_SIGNATURE_PREFIX = new TextEncoder().encode('SignalSignedPreKey');

export class X3DHKeyAgreement {
  private identityKey: IdentityKey | null = null;
  private signedPreKeys = new Map<number, SignedPreKey>();
  private oneTimePreKeys = new Map<number, OneTimePreKey>();
  private sessions = new Map<string, X3DHSession>();
  private registrationId: number;
  
  private readonly STORAGE_PREFIX = 'x3dh_';
  private readonly MAX_PREKEYS = 100;
  private readonly PREKEY_REFRESH_DAYS = 7;

  constructor() {
    this.registrationId = this.generateRegistrationId();
    this.loadStoredKeys();
  }

  /**
   * Initialize X3DH with new identity key
   */
  async initialize(): Promise<void> {
    try {
      // Load existing identity key or generate new one
      if (!this.identityKey) {
        await this.generateIdentityKey();
      }

      // Generate initial signed prekey
      await this.generateSignedPreKey();

      // Generate initial batch of one-time prekeys
      await this.generateOneTimePreKeys(20);

      // Upload prekey bundle to server
      await this.uploadPreKeyBundle();

      console.log('X3DH initialized successfully');
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Generate long-term identity key pair
   */
  private async generateIdentityKey(): Promise<void> {
    const keyPair = await crypto.subtle.generateKey(
      {
        name: 'ECDH',
        namedCurve: CURVE,
      },
      true,
      ['deriveKey']
    );

    const publicKeyRaw = await crypto.subtle.exportKey('raw', keyPair.publicKey);

    this.identityKey = {
      keyPair,
      publicKeyRaw,
    };

    await this.storeKey('identity_key', {
      privateKey: await crypto.subtle.exportKey('pkcs8', keyPair.privateKey),
      publicKey: publicKeyRaw,
    });
  }

  /**
   * Generate signed prekey (rotated weekly)
   */
  async generateSignedPreKey(): Promise<SignedPreKey> {
    if (!this.identityKey) {
      throw new Error('Identity key not initialized');
    }

    const keyId = Date.now();
    const keyPair = await crypto.subtle.generateKey(
      {
        name: 'ECDH',
        namedCurve: CURVE,
      },
      true,
      ['deriveKey']
    );

    const publicKeyRaw = await crypto.subtle.exportKey('raw', keyPair.publicKey);
    
    // Sign the prekey with identity key
    const signature = await this.signPreKey(publicKeyRaw, this.identityKey);

    const signedPreKey: SignedPreKey = {
      keyId,
      keyPair,
      publicKeyRaw,
      signature,
      timestamp: Date.now(),
    };

    this.signedPreKeys.set(keyId, signedPreKey);

    await this.storeKey(`signed_prekey_${keyId}`, {
      keyId,
      privateKey: await crypto.subtle.exportKey('pkcs8', keyPair.privateKey),
      publicKey: publicKeyRaw,
      signature,
      timestamp: signedPreKey.timestamp,
    });

    // Clean up old signed prekeys (keep last 3)
    await this.cleanupOldSignedPreKeys();

    return signedPreKey;
  }

  /**
   * Generate batch of one-time prekeys
   */
  async generateOneTimePreKeys(count: number): Promise<OneTimePreKey[]> {
    const newPreKeys: OneTimePreKey[] = [];

    for (let i = 0; i < count; i++) {
      const keyId = Date.now() + i;
      const keyPair = await crypto.subtle.generateKey(
        {
          name: 'ECDH',
          namedCurve: CURVE,
        },
        true,
        ['deriveKey']
      );

      const publicKeyRaw = await crypto.subtle.exportKey('raw', keyPair.publicKey);

      const oneTimePreKey: OneTimePreKey = {
        keyId,
        keyPair,
        publicKeyRaw,
      };

      this.oneTimePreKeys.set(keyId, oneTimePreKey);
      newPreKeys.push(oneTimePreKey);

      await this.storeKey(`onetime_prekey_${keyId}`, {
        keyId,
        privateKey: await crypto.subtle.exportKey('pkcs8', keyPair.privateKey),
        publicKey: publicKeyRaw,
      });
    }

    return newPreKeys;
  }

  /**
   * Sign prekey with identity key
   */
  private async signPreKey(preKeyPublic: ArrayBuffer, identityKey: IdentityKey): Promise<ArrayBuffer> {
    // Create signature input: prefix + prekey public
    const signatureInput = new Uint8Array(PREKEY_SIGNATURE_PREFIX.length + preKeyPublic.byteLength);
    signatureInput.set(PREKEY_SIGNATURE_PREFIX);
    signatureInput.set(new Uint8Array(preKeyPublic), PREKEY_SIGNATURE_PREFIX.length);

    // Convert ECDH key to signing key
    const signingKey = await crypto.subtle.importKey(
      'pkcs8',
      await crypto.subtle.exportKey('pkcs8', identityKey.keyPair.privateKey),
      {
        name: 'ECDSA',
        namedCurve: CURVE,
      },
      false,
      ['sign']
    );

    return await crypto.subtle.sign(
      {
        name: 'ECDSA',
        hash: HASH,
      },
      signingKey,
      signatureInput
    );
  }

  /**
   * Verify prekey signature
   */
  private async verifyPreKeySignature(
    preKeyPublic: ArrayBuffer,
    signature: ArrayBuffer,
    identityKeyPublic: ArrayBuffer
  ): Promise<boolean> {
    try {
      const signatureInput = new Uint8Array(PREKEY_SIGNATURE_PREFIX.length + preKeyPublic.byteLength);
      signatureInput.set(PREKEY_SIGNATURE_PREFIX);
      signatureInput.set(new Uint8Array(preKeyPublic), PREKEY_SIGNATURE_PREFIX.length);

      const verifyKey = await crypto.subtle.importKey(
        'raw',
        identityKeyPublic,
        {
          name: 'ECDSA',
          namedCurve: CURVE,
        },
        false,
        ['verify']
      );

      return await crypto.subtle.verify(
        {
          name: 'ECDSA',
          hash: HASH,
        },
        verifyKey,
        signature,
        signatureInput
      );
    } catch {
      return false;
    }
  }

  /**
   * Upload prekey bundle to server
   */
  async uploadPreKeyBundle(): Promise<void> {
    if (!this.identityKey) {
      throw new Error('Identity key not initialized');
    }

    const currentSignedPreKey = Array.from(this.signedPreKeys.values()).pop();
    if (!currentSignedPreKey) {
      throw new Error('No signed prekey available');
    }

    const oneTimePreKeysArray = Array.from(this.oneTimePreKeys.values()).map(key => ({
      key_id: key.keyId,
      public_key: this.arrayBufferToBase64(key.publicKeyRaw),
    }));

    const bundle = {
      registration_id: this.registrationId,
      identity_key: this.arrayBufferToBase64(this.identityKey.publicKeyRaw),
      signed_pre_key: {
        key_id: currentSignedPreKey.keyId,
        public_key: this.arrayBufferToBase64(currentSignedPreKey.publicKeyRaw),
        signature: this.arrayBufferToBase64(currentSignedPreKey.signature),
      },
      one_time_pre_keys: oneTimePreKeysArray,
    };

    await apiService.post('/api/v1/chat/x3dh/upload-bundle', bundle);
  }

  /**
   * Fetch prekey bundle from server for a user
   */
  async fetchPreKeyBundle(userId: string): Promise<PreKeyBundle> {
    const response = await apiService.get(`/api/v1/chat/x3dh/prekey-bundle/${userId}`);
    
    return {
      identityKey: this.base64ToArrayBuffer(response.identity_key),
      signedPreKey: {
        keyId: response.signed_pre_key.key_id,
        publicKey: this.base64ToArrayBuffer(response.signed_pre_key.public_key),
        signature: this.base64ToArrayBuffer(response.signed_pre_key.signature),
      },
      oneTimePreKey: response.one_time_pre_key ? {
        keyId: response.one_time_pre_key.key_id,
        publicKey: this.base64ToArrayBuffer(response.one_time_pre_key.public_key),
      } : undefined,
      registrationId: response.registration_id,
    };
  }

  /**
   * Perform X3DH key agreement as initiator
   */
  async performKeyAgreementInitiator(
    remoteUserId: string,
    bundle: PreKeyBundle
  ): Promise<X3DHResult> {
    if (!this.identityKey) {
      throw new Error('Identity key not initialized');
    }

    // Verify signed prekey signature
    const signatureValid = await this.verifyPreKeySignature(
      bundle.signedPreKey.publicKey,
      bundle.signedPreKey.signature,
      bundle.identityKey
    );

    if (!signatureValid) {
      throw new Error('Invalid signed prekey signature');
    }

    // Generate ephemeral key pair
    const ephemeralKeyPair = await crypto.subtle.generateKey(
      {
        name: 'ECDH',
        namedCurve: CURVE,
      },
      true,
      ['deriveKey']
    );

    // Import remote keys
    const remoteIdentityKey = await crypto.subtle.importKey(
      'raw',
      bundle.identityKey,
      { name: 'ECDH', namedCurve: CURVE },
      false,
      []
    );

    const remoteSignedPreKey = await crypto.subtle.importKey(
      'raw',
      bundle.signedPreKey.publicKey,
      { name: 'ECDH', namedCurve: CURVE },
      false,
      []
    );

    // Perform triple DH calculation
    const dh1 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: remoteSignedPreKey },
      this.identityKey.keyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    const dh2 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: remoteIdentityKey },
      ephemeralKeyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    const dh3 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: remoteSignedPreKey },
      ephemeralKeyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    // Fourth DH if one-time prekey is available
    let dh4: CryptoKey | undefined;
    if (bundle.oneTimePreKey) {
      const remoteOneTimePreKey = await crypto.subtle.importKey(
        'raw',
        bundle.oneTimePreKey.publicKey,
        { name: 'ECDH', namedCurve: CURVE },
        false,
        []
      );

      dh4 = await crypto.subtle.deriveKey(
        { name: 'ECDH', public: remoteOneTimePreKey },
        ephemeralKeyPair.privateKey,
        { name: 'HKDF', hash: HASH },
        true,
        ['deriveKey']
      );
    }

    // Concatenate DH outputs
    const dhOutputs = [
      await crypto.subtle.exportKey('raw', dh1),
      await crypto.subtle.exportKey('raw', dh2),
      await crypto.subtle.exportKey('raw', dh3),
    ];

    if (dh4) {
      dhOutputs.push(await crypto.subtle.exportKey('raw', dh4));
    }

    // Derive shared secret using HKDF
    const sharedSecret = await this.deriveSharedSecret(dhOutputs);

    // Create associated data
    const associatedData = await this.createAssociatedData(
      this.identityKey.publicKeyRaw,
      bundle.identityKey
    );

    // Store session
    const sessionId = `${remoteUserId}_${Date.now()}`;
    const session: X3DHSession = {
      sessionId,
      localIdentityKey: this.identityKey,
      remoteIdentityKey: bundle.identityKey,
      sharedSecret,
      associatedData,
      isInitiator: true,
      createdAt: new Date(),
    };

    this.sessions.set(sessionId, session);

    return {
      sharedSecret,
      associatedData,
      ephemeralKeyPair,
    };
  }

  /**
   * Perform X3DH key agreement as receiver
   */
  async performKeyAgreementReceiver(
    remoteUserId: string,
    ephemeralPublicKey: ArrayBuffer,
    remoteIdentityKey: ArrayBuffer,
    usedOneTimePreKeyId?: number
  ): Promise<X3DHResult> {
    if (!this.identityKey) {
      throw new Error('Identity key not initialized');
    }

    const currentSignedPreKey = Array.from(this.signedPreKeys.values()).pop();
    if (!currentSignedPreKey) {
      throw new Error('No signed prekey available');
    }

    // Import remote keys
    const remoteIdentityKeyObj = await crypto.subtle.importKey(
      'raw',
      remoteIdentityKey,
      { name: 'ECDH', namedCurve: CURVE },
      false,
      []
    );

    const ephemeralKeyObj = await crypto.subtle.importKey(
      'raw',
      ephemeralPublicKey,
      { name: 'ECDH', namedCurve: CURVE },
      false,
      []
    );

    // Perform triple DH calculation
    const dh1 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: remoteIdentityKeyObj },
      currentSignedPreKey.keyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    const dh2 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: ephemeralKeyObj },
      this.identityKey.keyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    const dh3 = await crypto.subtle.deriveKey(
      { name: 'ECDH', public: ephemeralKeyObj },
      currentSignedPreKey.keyPair.privateKey,
      { name: 'HKDF', hash: HASH },
      true,
      ['deriveKey']
    );

    // Fourth DH if one-time prekey was used
    let dh4: CryptoKey | undefined;
    if (usedOneTimePreKeyId) {
      const usedOneTimePreKey = this.oneTimePreKeys.get(usedOneTimePreKeyId);
      if (usedOneTimePreKey) {
        dh4 = await crypto.subtle.deriveKey(
          { name: 'ECDH', public: ephemeralKeyObj },
          usedOneTimePreKey.keyPair.privateKey,
          { name: 'HKDF', hash: HASH },
          true,
          ['deriveKey']
        );

        // Remove used one-time prekey
        this.oneTimePreKeys.delete(usedOneTimePreKeyId);
        await this.removeStoredKey(`onetime_prekey_${usedOneTimePreKeyId}`);
      }
    }

    // Concatenate DH outputs
    const dhOutputs = [
      await crypto.subtle.exportKey('raw', dh1),
      await crypto.subtle.exportKey('raw', dh2),
      await crypto.subtle.exportKey('raw', dh3),
    ];

    if (dh4) {
      dhOutputs.push(await crypto.subtle.exportKey('raw', dh4));
    }

    // Derive shared secret using HKDF
    const sharedSecret = await this.deriveSharedSecret(dhOutputs);

    // Create associated data
    const associatedData = await this.createAssociatedData(
      remoteIdentityKey,
      this.identityKey.publicKeyRaw
    );

    // Store session
    const sessionId = `${remoteUserId}_${Date.now()}`;
    const session: X3DHSession = {
      sessionId,
      localIdentityKey: this.identityKey,
      remoteIdentityKey,
      sharedSecret,
      associatedData,
      isInitiator: false,
      createdAt: new Date(),
    };

    this.sessions.set(sessionId, session);

    return {
      sharedSecret,
      associatedData,
    };
  }

  /**
   * Derive shared secret from DH outputs using HKDF
   */
  private async deriveSharedSecret(dhOutputs: ArrayBuffer[]): Promise<CryptoKey> {
    // Concatenate all DH outputs
    const totalLength = dhOutputs.reduce((sum, output) => sum + output.byteLength, 0);
    const combined = new Uint8Array(totalLength);
    let offset = 0;

    for (const output of dhOutputs) {
      combined.set(new Uint8Array(output), offset);
      offset += output.byteLength;
    }

    // Import combined key material
    const inputKeyMaterial = await crypto.subtle.importKey(
      'raw',
      combined,
      'HKDF',
      false,
      ['deriveKey']
    );

    // Derive final shared secret
    return await crypto.subtle.deriveKey(
      {
        name: 'HKDF',
        hash: HASH,
        salt: new Uint8Array(32), // 32-byte zero salt
        info: KEY_DERIVATION_INFO,
      },
      inputKeyMaterial,
      {
        name: 'HKDF',
        hash: HASH,
      },
      true,
      ['deriveKey']
    );
  }

  /**
   * Create associated data for the session
   */
  private async createAssociatedData(
    initiatorIdentityKey: ArrayBuffer,
    receiverIdentityKey: ArrayBuffer
  ): Promise<ArrayBuffer> {
    const combined = new Uint8Array(initiatorIdentityKey.byteLength + receiverIdentityKey.byteLength);
    combined.set(new Uint8Array(initiatorIdentityKey));
    combined.set(new Uint8Array(receiverIdentityKey), initiatorIdentityKey.byteLength);

    return await crypto.subtle.digest(HASH, combined);
  }

  /**
   * Clean up old signed prekeys (keep only the latest 3)
   */
  private async cleanupOldSignedPreKeys(): Promise<void> {
    const sortedKeys = Array.from(this.signedPreKeys.entries())
      .sort(([, a], [, b]) => b.timestamp - a.timestamp);

    if (sortedKeys.length <= 3) return;

    const keysToDelete = sortedKeys.slice(3);
    for (const [keyId] of keysToDelete) {
      this.signedPreKeys.delete(keyId);
      await this.removeStoredKey(`signed_prekey_${keyId}`);
    }
  }

  /**
   * Maintenance: refresh prekeys if needed
   */
  async refreshPreKeysIfNeeded(): Promise<void> {
    const now = Date.now();
    const oneWeek = 7 * 24 * 60 * 60 * 1000;

    // Check if signed prekey needs refresh
    const currentSignedPreKey = Array.from(this.signedPreKeys.values()).pop();
    if (!currentSignedPreKey || now - currentSignedPreKey.timestamp > oneWeek) {
      await this.generateSignedPreKey();
    }

    // Check if we need more one-time prekeys
    if (this.oneTimePreKeys.size < 10) {
      await this.generateOneTimePreKeys(50);
    }

    // Upload refreshed bundle
    await this.uploadPreKeyBundle();
  }

  /**
   * Get current prekey statistics
   */
  getPreKeyStatistics(): {
    identityKeyExists: boolean;
    signedPreKeys: number;
    oneTimePreKeys: number;
    sessions: number;
  } {
    return {
      identityKeyExists: !!this.identityKey,
      signedPreKeys: this.signedPreKeys.size,
      oneTimePreKeys: this.oneTimePreKeys.size,
      sessions: this.sessions.size,
    };
  }

  /**
   * Generate registration ID
   */
  private generateRegistrationId(): number {
    return Math.floor(Math.random() * 16384) + 1;
  }

  /**
   * Storage methods
   */
  private async storeKey(key: string, data: any): Promise<void> {
    try {
      localStorage.setItem(this.STORAGE_PREFIX + key, JSON.stringify(data));
    } catch (error) {
      console.error('Failed to store key:', error);
    }
  }

  private async getStoredKey(key: string): Promise<any> {
    try {
      const stored = localStorage.getItem(this.STORAGE_PREFIX + key);
      return stored ? JSON.parse(stored) : null;
    } catch (error) {
      console.error('Failed to get stored key:', error);
      return null;
    }
  }

  private async removeStoredKey(key: string): Promise<void> {
    try {
      localStorage.removeItem(this.STORAGE_PREFIX + key);
    } catch (error) {
      console.error('Failed to remove stored key:', error);
    }
  }

  /**
   * Load stored keys on initialization
   */
  private async loadStoredKeys(): Promise<void> {
    try {
      // Load identity key
      const identityData = await this.getStoredKey('identity_key');
      if (identityData) {
        const privateKey = await crypto.subtle.importKey(
          'pkcs8',
          identityData.privateKey,
          { name: 'ECDH', namedCurve: CURVE },
          true,
          ['deriveKey']
        );

        const publicKey = await crypto.subtle.importKey(
          'raw',
          identityData.publicKey,
          { name: 'ECDH', namedCurve: CURVE },
          true,
          []
        );

        this.identityKey = {
          keyPair: { privateKey, publicKey },
          publicKeyRaw: identityData.publicKey,
        };
      }

      // Load signed prekeys
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key?.startsWith(this.STORAGE_PREFIX + 'signed_prekey_')) {
          const keyData = await this.getStoredKey(key.substring(this.STORAGE_PREFIX.length));
          if (keyData) {
            const privateKey = await crypto.subtle.importKey(
              'pkcs8',
              keyData.privateKey,
              { name: 'ECDH', namedCurve: CURVE },
              true,
              ['deriveKey']
            );

            const publicKey = await crypto.subtle.importKey(
              'raw',
              keyData.publicKey,
              { name: 'ECDH', namedCurve: CURVE },
              true,
              []
            );

            this.signedPreKeys.set(keyData.keyId, {
              keyId: keyData.keyId,
              keyPair: { privateKey, publicKey },
              publicKeyRaw: keyData.publicKey,
              signature: keyData.signature,
              timestamp: keyData.timestamp,
            });
          }
        }
      }

      // Load one-time prekeys
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key?.startsWith(this.STORAGE_PREFIX + 'onetime_prekey_')) {
          const keyData = await this.getStoredKey(key.substring(this.STORAGE_PREFIX.length));
          if (keyData) {
            const privateKey = await crypto.subtle.importKey(
              'pkcs8',
              keyData.privateKey,
              { name: 'ECDH', namedCurve: CURVE },
              true,
              ['deriveKey']
            );

            const publicKey = await crypto.subtle.importKey(
              'raw',
              keyData.publicKey,
              { name: 'ECDH', namedCurve: CURVE },
              true,
              []
            );

            this.oneTimePreKeys.set(keyData.keyId, {
              keyId: keyData.keyId,
              keyPair: { privateKey, publicKey },
              publicKeyRaw: keyData.publicKey,
            });
          }
        }
      }
    } catch (error) {
      console.error('Failed to load stored keys:', error);
    }
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
}

// Singleton instance
export const x3dhKeyAgreement = new X3DHKeyAgreement();
export default X3DHKeyAgreement;