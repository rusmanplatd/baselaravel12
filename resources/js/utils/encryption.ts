import {
  EncryptedMessageData,
  KeyPair,
  EncryptionOptions,
  DecryptionResult
} from '@/types/chat';

/**
 * Enhanced client-side encryption utilities for chat E2EE
 * This provides a TypeScript interface to browser crypto APIs
 * and maintains compatibility with the PHP backend encryption
 */

export class ChatEncryption {
  private static readonly KEY_SIZE = 256; // 256 bits
  private static readonly IV_SIZE = 128; // 128 bits
  private static readonly RSA_KEY_SIZE = 4096; // 4096 bits for enhanced security
  private static readonly PBKDF2_ITERATIONS = 100000;
  private static readonly VERSION = '2.0';

  /**
   * Generate an RSA key pair for asymmetric encryption
   */
  static async generateKeyPair(): Promise<KeyPair> {
    try {
      const keyPair = await window.crypto.subtle.generateKey(
        {
          name: 'RSA-OAEP',
          modulusLength: this.RSA_KEY_SIZE,
          publicExponent: new Uint8Array([1, 0, 1]),
          hash: 'SHA-512',
        },
        true,
        ['encrypt', 'decrypt']
      );

      const publicKeyPem = await this.exportPublicKeyToPem(keyPair.publicKey);
      const privateKeyPem = await this.exportPrivateKeyToPem(keyPair.privateKey);

      return {
        public_key: publicKeyPem,
        private_key: privateKeyPem,
      };
    } catch (error) {
      console.error('Failed to generate key pair:', error);
      throw new Error('Key pair generation failed');
    }
  }

  /**
   * Generate a symmetric key for message encryption
   */
  static async generateSymmetricKey(): Promise<CryptoKey> {
    return await window.crypto.subtle.generateKey(
      {
        name: 'AES-CBC',
        length: this.KEY_SIZE,
      },
      true,
      ['encrypt', 'decrypt']
    );
  }

  /**
   * Encrypt a symmetric key with RSA public key
   */
  static async encryptSymmetricKey(
    symmetricKey: CryptoKey,
    publicKeyPem: string
  ): Promise<string> {
    try {
      const publicKey = await this.importPublicKeyFromPem(publicKeyPem);
      const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);

      const encrypted = await window.crypto.subtle.encrypt(
        {
          name: 'RSA-OAEP',
        },
        publicKey,
        keyBytes
      );

      return this.arrayBufferToBase64(encrypted);
    } catch (error) {
      console.error('Failed to encrypt symmetric key:', error);
      throw new Error('Symmetric key encryption failed');
    }
  }

  /**
   * Decrypt a symmetric key with RSA private key
   */
  static async decryptSymmetricKey(
    encryptedKey: string,
    privateKeyPem: string
  ): Promise<CryptoKey> {
    try {
      const privateKey = await this.importPrivateKeyFromPem(privateKeyPem);
      const encryptedBytes = this.base64ToArrayBuffer(encryptedKey);

      const decryptedBytes = await window.crypto.subtle.decrypt(
        {
          name: 'RSA-OAEP',
        },
        privateKey,
        encryptedBytes
      );

      return await window.crypto.subtle.importKey(
        'raw',
        decryptedBytes,
        { name: 'AES-CBC' },
        true,
        ['encrypt', 'decrypt']
      );
    } catch (error) {
      console.error('Failed to decrypt symmetric key:', error);
      throw new Error('Symmetric key decryption failed');
    }
  }

  /**
   * Encrypt a message with enhanced v2.0 format
   */
  static async encryptMessage(
    message: string,
    symmetricKey: CryptoKey,
    options: EncryptionOptions = {}
  ): Promise<EncryptedMessageData> {
    try {
      const version = options.version || this.VERSION;
      const iv = window.crypto.getRandomValues(new Uint8Array(this.IV_SIZE / 8));
      const timestamp = Math.floor(Date.now() / 1000);
      const nonce = this.generateNonce();

      // Create auth data for v2.0
      const authData = {
        timestamp,
        nonce,
        version,
      };
      const authDataString = JSON.stringify(authData);
      const authDataBytes = new TextEncoder().encode(authDataString);

      // Combine auth data with message for encryption
      const messageBytes = new TextEncoder().encode(message);
      const combinedData = new Uint8Array(
        authDataBytes.length + 1 + messageBytes.length
      );
      combinedData.set(authDataBytes, 0);
      combinedData.set(new Uint8Array([124]), authDataBytes.length); // '|' separator
      combinedData.set(messageBytes, authDataBytes.length + 1);

      // Encrypt the combined data
      const encrypted = await window.crypto.subtle.encrypt(
        {
          name: 'AES-CBC',
          iv: iv,
        },
        symmetricKey,
        combinedData
      );

      const encryptedData = this.arrayBufferToBase64(encrypted);
      const ivBase64 = this.arrayBufferToBase64(iv);
      const authDataBase64 = this.arrayBufferToBase64(authDataBytes);

      // Calculate HMAC for integrity
      const hmacKey = await this.deriveHmacKey(symmetricKey);
      const hmac = await this.calculateHMAC(
        hmacKey,
        encryptedData + ivBase64 + authDataString
      );

      // Hash original message
      const contentHash = await this.calculateSHA256(message);

      return {
        data: encryptedData,
        iv: ivBase64,
        hash: contentHash,
        hmac,
        auth_data: authDataBase64,
        timestamp,
        nonce,
        version: version as '1.0' | '2.0',
      };
    } catch (error) {
      console.error('Failed to encrypt message:', error);
      throw new Error('Message encryption failed');
    }
  }

  /**
   * Decrypt a message with support for both v1.0 and v2.0 formats
   */
  static async decryptMessage(
    encryptedData: EncryptedMessageData,
    symmetricKey: CryptoKey,
    options: EncryptionOptions = {}
  ): Promise<DecryptionResult> {
    try {
      const { data, iv, hash, hmac, auth_data, version = '1.0' } = encryptedData;

      // Verify HMAC and replay protection for v2.0 messages
      if (version === '2.0' && hmac && auth_data) {
        await this.verifyMessageAuthenticity(hmac, auth_data, data, iv, symmetricKey, options);
      }

      // Decrypt the message content
      const content = await this.decryptMessageContent(data, iv, symmetricKey, version);

      // Verify content hash
      const verified = await this.verifyContentHash(content, hash);

      return {
        content,
        verified,
        version: version as '1.0' | '2.0',
        timestamp: encryptedData.timestamp,
      };
    } catch (error: unknown) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error';
      console.error('Failed to decrypt message:', error);
      throw new Error(`Message decryption failed: ${errorMessage}`);
    }
  }

  /**
   * Verify message authenticity for v2.0 format
   */
  private static async verifyMessageAuthenticity(
    hmac: string,
    auth_data: string,
    data: string,
    iv: string,
    symmetricKey: CryptoKey,
    options: EncryptionOptions
  ): Promise<void> {
    const hmacKey = await this.deriveHmacKey(symmetricKey);
    const authDataString = new TextDecoder().decode(
      this.base64ToArrayBuffer(auth_data)
    );
    const expectedHmac = await this.calculateHMAC(
      hmacKey,
      data + iv + authDataString
    );

    if (hmac !== expectedHmac) {
      throw new Error('Message authentication failed - HMAC mismatch');
    }

    // Validate timestamp for replay protection
    if (options.enableReplayProtection !== false) {
      await this.validateMessageTimestamp(authDataString, options.maxAge || 3600);
    }
  }

  /**
   * Validate message timestamp for replay protection
   */
  private static async validateMessageTimestamp(authDataString: string, maxAge: number): Promise<void> {
    const authDataObj = JSON.parse(authDataString);
    if (authDataObj.timestamp) {
      const messageAge = Math.floor(Date.now() / 1000) - authDataObj.timestamp;
      if (messageAge > maxAge) {
        throw new Error('Message too old - potential replay attack');
      }
      if (messageAge < -300) { // Allow 5 minutes clock skew
        throw new Error('Message from future - clock skew too large');
      }
    }
  }

  /**
   * Decrypt message content
   */
  private static async decryptMessageContent(
    data: string,
    iv: string,
    symmetricKey: CryptoKey,
    version: string
  ): Promise<string> {
    const encryptedBytes = this.base64ToArrayBuffer(data);
    const ivBytes = this.base64ToArrayBuffer(iv);

    const decrypted = await window.crypto.subtle.decrypt(
      {
        name: 'AES-CBC',
        iv: ivBytes,
      },
      symmetricKey,
      encryptedBytes
    );

    let content = new TextDecoder().decode(decrypted);

    // For v2.0 messages, extract actual content after auth data
    if (version === '2.0' && content.includes('|')) {
      const separatorIndex = content.indexOf('|');
      if (separatorIndex !== -1) {
        content = content.substring(separatorIndex + 1);
      }
    }

    return content;
  }

  /**
   * Verify content hash
   */
  private static async verifyContentHash(content: string, expectedHash: string): Promise<boolean> {
    const contentHash = await this.calculateSHA256(content);
    const verified = expectedHash === contentHash;

    if (!verified) {
      console.warn('Content hash verification failed');
    }

    return verified;
  }

  /**
   * Derive a key from password using PBKDF2
   */
  static async deriveKeyFromPassword(
    password: string,
    salt: string,
    iterations: number = this.PBKDF2_ITERATIONS
  ): Promise<CryptoKey> {
    const encoder = new TextEncoder();
    const passwordBytes = encoder.encode(password);
    const saltBytes = encoder.encode(salt);

    const keyMaterial = await window.crypto.subtle.importKey(
      'raw',
      passwordBytes,
      { name: 'PBKDF2' },
      false,
      ['deriveBits', 'deriveKey']
    );

    return await window.crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt: saltBytes,
        iterations,
        hash: 'SHA-256',
      },
      keyMaterial,
      { name: 'AES-CBC', length: this.KEY_SIZE },
      true,
      ['encrypt', 'decrypt']
    );
  }

  /**
   * Generate a cryptographic salt
   */
  static generateSalt(length: number = 32): string {
    const salt = window.crypto.getRandomValues(new Uint8Array(length));
    return this.arrayBufferToBase64(salt);
  }

  /**
   * Bulk encrypt multiple messages for better performance
   */
  static async bulkEncryptMessages(
    messages: Array<{ id: string; content: string }>,
    symmetricKey: CryptoKey,
    options: EncryptionOptions = {}
  ): Promise<Array<{ id: string; encrypted: EncryptedMessageData | null; error?: string }>> {
    const results = [];

    for (const message of messages) {
      try {
        const encrypted = await this.encryptMessage(message.content, symmetricKey, options);
        results.push({ id: message.id, encrypted });
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Encryption failed';
        console.error(`Failed to encrypt message ${message.id}:`, error);
        results.push({ id: message.id, encrypted: null, error: errorMessage });
      }
    }

    return results;
  }

  /**
   * Bulk decrypt multiple messages for better performance
   */
  static async bulkDecryptMessages(
    messages: Array<{ id: string; encrypted: EncryptedMessageData }>,
    symmetricKey: CryptoKey,
    options: EncryptionOptions = {}
  ): Promise<Array<{ id: string; decrypted: DecryptionResult | null; error?: string }>> {
    const results = [];

    for (const message of messages) {
      try {
        const decrypted = await this.decryptMessage(message.encrypted, symmetricKey, options);
        results.push({ id: message.id, decrypted });
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : 'Decryption failed';
        console.error(`Failed to decrypt message ${message.id}:`, error);
        results.push({ id: message.id, decrypted: null, error: errorMessage });
      }
    }

    return results;
  }

  /**
   * Create encrypted backup of key data
   */
  static async createEncryptedBackup(
    keyData: any,
    password: string
  ): Promise<string> {
    if (password.length < 8) {
      throw new Error('Backup password must be at least 8 characters long');
    }

    const salt = this.generateSalt();
    const derivedKey = await this.deriveKeyFromPassword(password, salt);

    const backupData = {
      version: '2.0',
      created_at: new Date().toISOString(),
      key_data: keyData,
      checksum: await this.calculateSHA256(JSON.stringify(keyData))
    };

    const encrypted = await this.encryptMessage(JSON.stringify(backupData), derivedKey);

    return btoa(JSON.stringify({
      salt,
      encrypted_data: encrypted,
      version: '2.0'
    }));
  }

  /**
   * Restore keys from encrypted backup
   */
  static async restoreFromBackup(
    encryptedBackup: string,
    password: string
  ): Promise<any> {
    try {
      const backupData = JSON.parse(atob(encryptedBackup));
      if (!backupData.salt || !backupData.encrypted_data) {
        throw new Error('Invalid backup format');
      }

      const derivedKey = await this.deriveKeyFromPassword(password, backupData.salt);
      const decrypted = await this.decryptMessage(backupData.encrypted_data, derivedKey);

      if (!decrypted.verified) {
        throw new Error('Backup integrity verification failed');
      }

      const restoredData = JSON.parse(decrypted.content);

      // Verify checksum
      const expectedChecksum = await this.calculateSHA256(JSON.stringify(restoredData.key_data));
      if (restoredData.checksum && restoredData.checksum !== expectedChecksum) {
        throw new Error('Backup integrity check failed');
      }

      return restoredData.key_data;
    } catch (error) {
      console.error('Failed to restore backup:', error);
      throw new Error(`Backup restoration failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
  }

  /**
   * Validate encryption system health
   */
  static async validateHealth(): Promise<{
    status: 'healthy' | 'unhealthy';
    checks: Record<string, any>;
    warnings: string[];
    errors: string[];
  }> {
    const health = {
      status: 'healthy' as const,
      checks: {} as Record<string, any>,
      warnings: [] as string[],
      errors: [] as string[]
    };

    try {
      // Test key generation
      const startTime = performance.now();
      const keyPair = await this.generateKeyPair();
      const keyGenTime = performance.now() - startTime;

      health.checks.key_generation = {
        status: 'pass',
        duration_ms: Math.round(keyGenTime * 100) / 100
      };

      if (keyGenTime > 5000) { // 5 seconds
        health.warnings.push(`Key generation is slow (${keyGenTime}ms)`);
      }

      // Test symmetric encryption
      const encStartTime = performance.now();
      const symmetricKey = await this.generateSymmetricKey();
      const testMessage = 'Health check test message';
      const encrypted = await this.encryptMessage(testMessage, symmetricKey);
      const decrypted = await this.decryptMessage(encrypted, symmetricKey);
      const encryptionTime = performance.now() - encStartTime;

      if (decrypted.content !== testMessage || !decrypted.verified) {
        health.errors.push('Symmetric encryption test failed');
        health.status = 'unhealthy';
      } else {
        health.checks.symmetric_encryption = {
          status: 'pass',
          duration_ms: Math.round(encryptionTime * 100) / 100
        };
      }

      // Test key integrity
      const integrityValid = await this.verifyKeyIntegrity(
        keyPair.public_key,
        keyPair.private_key
      );

      health.checks.key_integrity = {
        status: integrityValid ? 'pass' : 'fail'
      };

      if (!integrityValid) {
        health.errors.push('Key integrity verification failed');
        health.status = 'unhealthy';
      }

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error';
      health.errors.push(`Health check exception: ${errorMessage}`);
      health.status = 'unhealthy';
    }

    return health;
  }

  /**
   * Calculate password entropy (basic implementation)
   */
  static calculatePasswordEntropy(password: string): number {
    if (password.length === 0) return 0;

    let charset = 0;
    if (/[a-z]/.test(password)) charset += 26;
    if (/[A-Z]/.test(password)) charset += 26;
    if (/\d/.test(password)) charset += 10;
    if (/[^a-zA-Z0-9]/.test(password)) charset += 32;

    return charset > 0 ? password.length * Math.log2(charset) : 0;
  }

  /**
   * Verify key pair integrity
   */
  static async verifyKeyIntegrity(
    publicKeyPem: string,
    privateKeyPem: string
  ): Promise<boolean> {
    try {
      const testData = 'integrity-test-' + this.generateNonce();
      const symmetricKey = await this.generateSymmetricKey();

      const encryptedSymKey = await this.encryptSymmetricKey(symmetricKey, publicKeyPem);
      const decryptedSymKey = await this.decryptSymmetricKey(encryptedSymKey, privateKeyPem);

      const encrypted = await this.encryptMessage(testData, decryptedSymKey);
      const decrypted = await this.decryptMessage(encrypted, decryptedSymKey);

      return decrypted.content === testData && decrypted.verified;
    } catch (error: unknown) {
      console.warn('Key integrity verification failed:', error);
      return false;
    }
  }

  // Private utility methods

  private static async exportPublicKeyToPem(key: CryptoKey): Promise<string> {
    const exported = await window.crypto.subtle.exportKey('spki', key);
    const base64 = this.arrayBufferToBase64(exported);
    return `-----BEGIN PUBLIC KEY-----\n${this.formatPemBody(base64)}\n-----END PUBLIC KEY-----`;
  }

  private static async exportPrivateKeyToPem(key: CryptoKey): Promise<string> {
    const exported = await window.crypto.subtle.exportKey('pkcs8', key);
    const base64 = this.arrayBufferToBase64(exported);
    return `-----BEGIN PRIVATE KEY-----\n${this.formatPemBody(base64)}\n-----END PRIVATE KEY-----`;
  }

  private static async importPublicKeyFromPem(pem: string): Promise<CryptoKey> {
    const base64 = pem
      .replace('-----BEGIN PUBLIC KEY-----', '')
      .replace('-----END PUBLIC KEY-----', '')
      .replace(/\s/g, '');
    const keyData = this.base64ToArrayBuffer(base64);

    return await window.crypto.subtle.importKey(
      'spki',
      keyData,
      {
        name: 'RSA-OAEP',
        hash: 'SHA-512',
      },
      true,
      ['encrypt']
    );
  }

  private static async importPrivateKeyFromPem(pem: string): Promise<CryptoKey> {
    const base64 = pem
      .replace('-----BEGIN PRIVATE KEY-----', '')
      .replace('-----END PRIVATE KEY-----', '')
      .replace(/\s/g, '');
    const keyData = this.base64ToArrayBuffer(base64);

    return await window.crypto.subtle.importKey(
      'pkcs8',
      keyData,
      {
        name: 'RSA-OAEP',
        hash: 'SHA-512',
      },
      true,
      ['decrypt']
    );
  }

  private static formatPemBody(base64: string): string {
    return base64.match(/.{1,64}/g)?.join('\n') || base64;
  }

  private static arrayBufferToBase64(buffer: ArrayBuffer | Uint8Array): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  }

  private static base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  private static generateNonce(): string {
    const nonce = window.crypto.getRandomValues(new Uint8Array(8));
    return Array.from(nonce, byte => byte.toString(16).padStart(2, '0')).join('');
  }

  private static async calculateSHA256(message: string): Promise<string> {
    const encoder = new TextEncoder();
    const data = encoder.encode(message);
    const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  }

  private static async deriveHmacKey(symmetricKey: CryptoKey): Promise<CryptoKey> {
    const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);
    return await window.crypto.subtle.importKey(
      'raw',
      keyBytes,
      { name: 'HMAC', hash: 'SHA-256' },
      false,
      ['sign', 'verify']
    );
  }

  private static async calculateHMAC(key: CryptoKey, data: string): Promise<string> {
    const encoder = new TextEncoder();
    const dataBytes = encoder.encode(data);
    const signature = await window.crypto.subtle.sign('HMAC', key, dataBytes);
    const signatureArray = Array.from(new Uint8Array(signature));
    return signatureArray.map(b => b.toString(16).padStart(2, '0')).join('');
  }
}

// Storage utilities for managing keys securely
export class SecureStorage {
  private static readonly STORAGE_PREFIX = 'chat_e2ee_';

  /**
   * Store private key securely (consider using IndexedDB for production)
   */
  static storePrivateKey(userId: string, privateKey: string): void {
    // TODO: In production, consider using IndexedDB with encryption
    sessionStorage.setItem(`${this.STORAGE_PREFIX}private_key_${userId}`, privateKey);
  }

  /**
   * Retrieve private key
   */
  static getPrivateKey(userId: string): string | null {
    return sessionStorage.getItem(`${this.STORAGE_PREFIX}private_key_${userId}`);
  }

  /**
   * Clear stored keys
   */
  static clearKeys(userId: string): void {
    sessionStorage.removeItem(`${this.STORAGE_PREFIX}private_key_${userId}`);
  }

  /**
   * Store conversation symmetric key temporarily
   */
  static storeConversationKey(conversationId: string, key: string): void {
    sessionStorage.setItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`, key);
  }

  /**
   * Retrieve conversation symmetric key
   */
  static getConversationKey(conversationId: string): string | null {
    return sessionStorage.getItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`);
  }

  /**
   * Clear conversation keys
   */
  static clearConversationKey(conversationId: string): void {
    sessionStorage.removeItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`);
  }
}
