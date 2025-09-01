import {
  EncryptedMessageData,
  KeyPair,
  EncryptionOptions,
  DecryptionResult
} from '@/types/chat';
import { apiService } from '@/services/ApiService';

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
  private static readonly VERSION = '3.0'; // Updated for quantum support
  private static readonly QUANTUM_ALGORITHMS = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'];

  /**
   * Generate a key pair - quantum-resistant if algorithm specified
   */
  static async generateKeyPair(algorithm?: string): Promise<KeyPair> {
    if (algorithm && this.QUANTUM_ALGORITHMS.includes(algorithm)) {
      return this.generateQuantumKeyPair(algorithm);
    }
    
    return this.generateRSAKeyPair();
  }

  /**
   * Generate quantum-resistant key pair
   */
  static async generateQuantumKeyPair(algorithm: string): Promise<KeyPair> {
    try {
      // Call backend API for quantum key generation
      const keyPair = await apiService.post('/api/v1/quantum/generate-keypair', { algorithm });
      return {
        public_key: keyPair.public_key,
        private_key: keyPair.private_key,
        algorithm: algorithm
      };
    } catch (error) {
      console.error('Quantum key generation failed:', error);
      // Fallback to RSA
      return this.generateRSAKeyPair();
    }
  }

  /**
   * Generate an RSA key pair for asymmetric encryption (fallback)
   */
  static async generateRSAKeyPair(): Promise<KeyPair> {
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
   * Encrypt a symmetric key with public key (supports quantum algorithms)
   */
  static async encryptSymmetricKey(
    symmetricKey: CryptoKey,
    publicKeyPem: string,
    algorithm?: string
  ): Promise<string> {
    if (algorithm && this.QUANTUM_ALGORITHMS.includes(algorithm)) {
      return this.encryptSymmetricKeyQuantum(symmetricKey, publicKeyPem, algorithm);
    }
    
    return this.encryptSymmetricKeyRSA(symmetricKey, publicKeyPem);
  }

  /**
   * Encrypt symmetric key with quantum-resistant algorithm
   */
  static async encryptSymmetricKeyQuantum(
    symmetricKey: CryptoKey,
    publicKeyPem: string,
    algorithm: string
  ): Promise<string> {
    try {
      const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);
      
      const { ciphertext } = await apiService.post('/api/v1/quantum/encapsulate', {
        public_key: publicKeyPem,
        algorithm: algorithm,
        data: this.arrayBufferToBase64(keyBytes)
      });
      return ciphertext;
    } catch (error) {
      console.error('Quantum symmetric key encryption failed:', error);
      // Fallback to RSA
      return this.encryptSymmetricKeyRSA(symmetricKey, publicKeyPem);
    }
  }

  /**
   * Encrypt a symmetric key with RSA public key (fallback)
   */
  static async encryptSymmetricKeyRSA(
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
   * Decrypt a symmetric key with private key (supports quantum algorithms)
   */
  static async decryptSymmetricKey(
    encryptedKey: string,
    privateKeyPem: string,
    algorithm?: string
  ): Promise<CryptoKey> {
    if (algorithm && this.QUANTUM_ALGORITHMS.includes(algorithm)) {
      return this.decryptSymmetricKeyQuantum(encryptedKey, privateKeyPem, algorithm);
    }
    
    return this.decryptSymmetricKeyRSA(encryptedKey, privateKeyPem);
  }

  /**
   * Decrypt symmetric key with quantum-resistant algorithm
   */
  static async decryptSymmetricKeyQuantum(
    encryptedKey: string,
    privateKeyPem: string,
    algorithm: string
  ): Promise<CryptoKey> {
    try {
      const { shared_secret } = await apiService.post('/api/v1/quantum/decapsulate', {
        ciphertext: encryptedKey,
        private_key: privateKeyPem,
        algorithm: algorithm
      });
      const keyBytes = this.base64ToArrayBuffer(shared_secret);

      return await window.crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-GCM' }, // Use GCM for quantum-resistant encryption
        true,
        ['encrypt', 'decrypt']
      );
    } catch (error) {
      console.error('Quantum symmetric key decryption failed:', error);
      // Fallback to RSA
      return this.decryptSymmetricKeyRSA(encryptedKey, privateKeyPem);
    }
  }

  /**
   * Decrypt a symmetric key with RSA private key (fallback)
   */
  static async decryptSymmetricKeyRSA(
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
        version: version as '1.0' | '2.0' | '3.0',
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

  /**
   * Check if an algorithm is quantum-resistant
   */
  static isQuantumResistant(algorithm: string): boolean {
    return this.QUANTUM_ALGORITHMS.includes(algorithm);
  }

  /**
   * Get recommended quantum algorithm based on device capabilities
   */
  static getRecommendedQuantumAlgorithm(deviceCapabilities: string[]): string {
    const priorityOrder = ['ML-KEM-1024', 'ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'ML-KEM-512'];
    
    for (const algorithm of priorityOrder) {
      if (deviceCapabilities.includes(algorithm)) {
        return algorithm;
      }
    }
    
    return 'RSA-4096-OAEP'; // Fallback to classical
  }

  /**
   * Negotiate algorithm between multiple device capability sets
   */
  static negotiateAlgorithm(deviceCapabilitySets: string[][]): string {
    // Find intersection of all device capabilities
    let commonAlgorithms = deviceCapabilitySets[0] || [];
    
    for (let i = 1; i < deviceCapabilitySets.length; i++) {
      commonAlgorithms = commonAlgorithms.filter(alg => 
        deviceCapabilitySets[i].includes(alg)
      );
    }
    
    if (commonAlgorithms.length === 0) {
      return 'RSA-4096-OAEP'; // Fallback
    }
    
    return this.getRecommendedQuantumAlgorithm(commonAlgorithms);
  }

  /**
   * Get algorithm information
   */
  static getAlgorithmInfo(algorithm: string): {
    name: string;
    type: 'quantum' | 'hybrid' | 'classical';
    securityLevel: number;
    quantumResistant: boolean;
    version: string;
  } {
    const algorithmMap = {
      'ML-KEM-512': {
        name: 'ML-KEM-512',
        type: 'quantum' as const,
        securityLevel: 512,
        quantumResistant: true,
        version: '3.0'
      },
      'ML-KEM-768': {
        name: 'ML-KEM-768',
        type: 'quantum' as const,
        securityLevel: 768,
        quantumResistant: true,
        version: '3.0'
      },
      'ML-KEM-1024': {
        name: 'ML-KEM-1024',
        type: 'quantum' as const,
        securityLevel: 1024,
        quantumResistant: true,
        version: '3.0'
      },
      'HYBRID-RSA4096-MLKEM768': {
        name: 'Hybrid RSA-4096 + ML-KEM-768',
        type: 'hybrid' as const,
        securityLevel: 768,
        quantumResistant: true,
        version: '3.0'
      },
      'RSA-4096-OAEP': {
        name: 'RSA-4096-OAEP',
        type: 'classical' as const,
        securityLevel: 4096,
        quantumResistant: false,
        version: '2.0'
      }
    };

    return algorithmMap[algorithm] || algorithmMap['RSA-4096-OAEP'];
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

// Storage utilities for managing keys securely with IndexedDB and encryption
export class SecureStorage {
  private static readonly STORAGE_PREFIX = 'chat_e2ee_';
  private static readonly DB_NAME = 'chat_encryption_db';
  private static readonly DB_VERSION = 1;
  private static readonly STORE_NAME = 'encrypted_keys';
  private static db: IDBDatabase | null = null;
  private static encryptionKey: CryptoKey | null = null;

  /**
   * Initialize the IndexedDB database and encryption key
   */
  private static async initializeDB(): Promise<void> {
    if (this.db) return;

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);

      request.onerror = () => reject(new Error('Failed to open IndexedDB'));
      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };

      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        if (!db.objectStoreNames.contains(this.STORE_NAME)) {
          const store = db.createObjectStore(this.STORE_NAME, { keyPath: 'id' });
          store.createIndex('userId', 'userId', { unique: false });
          store.createIndex('type', 'type', { unique: false });
        }
      };
    });
  }

  /**
   * Generate or retrieve the master encryption key for local storage
   */
  private static async getMasterKey(): Promise<CryptoKey> {
    if (this.encryptionKey) return this.encryptionKey;

    // Try to get existing key from localStorage
    const storedKeyData = localStorage.getItem(`${this.STORAGE_PREFIX}master_key`);
    
    if (storedKeyData) {
      try {
        const keyData = JSON.parse(storedKeyData);
        this.encryptionKey = await window.crypto.subtle.importKey(
          'jwk',
          keyData,
          { name: 'AES-GCM' },
          false,
          ['encrypt', 'decrypt']
        );
        return this.encryptionKey;
      } catch (error) {
        console.warn('Failed to import existing master key, generating new one');
      }
    }

    // Generate new master key
    this.encryptionKey = await window.crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );

    // Export and store the key
    const exportedKey = await window.crypto.subtle.exportKey('jwk', this.encryptionKey);
    localStorage.setItem(`${this.STORAGE_PREFIX}master_key`, JSON.stringify(exportedKey));

    return this.encryptionKey;
  }

  /**
   * Encrypt data using the master key
   */
  private static async encryptData(data: string): Promise<{ encrypted: ArrayBuffer; iv: Uint8Array }> {
    const masterKey = await this.getMasterKey();
    const encoder = new TextEncoder();
    const dataBytes = encoder.encode(data);
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    
    const encrypted = await window.crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      masterKey,
      dataBytes
    );

    return { encrypted, iv };
  }

  /**
   * Decrypt data using the master key
   */
  private static async decryptData(encryptedData: ArrayBuffer, iv: Uint8Array): Promise<string> {
    const masterKey = await this.getMasterKey();
    
    const decrypted = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: iv as Uint8Array },
      masterKey,
      encryptedData
    );

    const decoder = new TextDecoder();
    return decoder.decode(decrypted);
  }

  /**
   * Store private key securely using IndexedDB with encryption
   */
  static async storePrivateKey(userId: string, privateKey: string): Promise<void> {
    try {
      await this.initializeDB();
      const { encrypted, iv } = await this.encryptData(privateKey);
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.STORE_NAME);

      const keyData = {
        id: `private_key_${userId}`,
        userId,
        type: 'private_key',
        encryptedData: Array.from(new Uint8Array(encrypted)),
        iv: Array.from(iv),
        createdAt: new Date().toISOString(),
      };

      await new Promise<void>((resolve, reject) => {
        const request = store.put(keyData);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(new Error('Failed to store private key'));
      });
    } catch (error) {
      console.error('Failed to store private key:', error);
      // Fallback to sessionStorage for compatibility
      sessionStorage.setItem(`${this.STORAGE_PREFIX}private_key_${userId}`, privateKey);
    }
  }

  /**
   * Retrieve private key
   */
  static async getPrivateKey(userId: string): Promise<string | null> {
    try {
      await this.initializeDB();
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.STORE_NAME);

      const keyData = await new Promise<any>((resolve, reject) => {
        const request = store.get(`private_key_${userId}`);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Failed to retrieve private key'));
      });

      if (!keyData) return null;

      const encryptedArray = new Uint8Array(keyData.encryptedData);
      const iv = new Uint8Array(keyData.iv);
      
      return await this.decryptData(encryptedArray.buffer, iv);
    } catch (error) {
      console.error('Failed to retrieve private key from IndexedDB:', error);
      // Fallback to sessionStorage
      return sessionStorage.getItem(`${this.STORAGE_PREFIX}private_key_${userId}`);
    }
  }

  /**
   * Clear stored keys
   */
  static async clearKeys(userId: string): Promise<void> {
    try {
      await this.initializeDB();
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.STORE_NAME);
      const index = store.index('userId');

      await new Promise<void>((resolve, reject) => {
        const request = index.openCursor(IDBKeyRange.only(userId));
        request.onsuccess = (event) => {
          const cursor = (event.target as IDBRequest).result;
          if (cursor) {
            cursor.delete();
            cursor.continue();
          } else {
            resolve();
          }
        };
        request.onerror = () => reject(new Error('Failed to clear keys'));
      });
    } catch (error) {
      console.error('Failed to clear keys from IndexedDB:', error);
    }

    // Also clear sessionStorage fallback
    sessionStorage.removeItem(`${this.STORAGE_PREFIX}private_key_${userId}`);
    sessionStorage.removeItem(`${this.STORAGE_PREFIX}conv_key_${userId}`);
  }

  /**
   * Store conversation symmetric key temporarily
   */
  static async storeConversationKey(conversationId: string, key: string): Promise<void> {
    try {
      await this.initializeDB();
      const { encrypted, iv } = await this.encryptData(key);
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.STORE_NAME);

      const keyData = {
        id: `conv_key_${conversationId}`,
        conversationId,
        type: 'conversation_key',
        encryptedData: Array.from(new Uint8Array(encrypted)),
        iv: Array.from(iv),
        createdAt: new Date().toISOString(),
        // Auto-expire after 24 hours for security
        expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
      };

      await new Promise<void>((resolve, reject) => {
        const request = store.put(keyData);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(new Error('Failed to store conversation key'));
      });
    } catch (error) {
      console.error('Failed to store conversation key:', error);
      // Fallback to sessionStorage
      sessionStorage.setItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`, key);
    }
  }

  /**
   * Retrieve conversation symmetric key
   */
  static async getConversationKey(conversationId: string): Promise<string | null> {
    try {
      await this.initializeDB();
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.STORE_NAME);

      const keyData = await new Promise<any>((resolve, reject) => {
        const request = store.get(`conv_key_${conversationId}`);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Failed to retrieve conversation key'));
      });

      if (!keyData) return null;

      // Check expiration
      if (new Date(keyData.expiresAt) < new Date()) {
        // Key expired, delete it
        store.delete(`conv_key_${conversationId}`);
        return null;
      }

      const encryptedArray = new Uint8Array(keyData.encryptedData);
      const iv = new Uint8Array(keyData.iv);
      
      return await this.decryptData(encryptedArray.buffer, iv);
    } catch (error) {
      console.error('Failed to retrieve conversation key from IndexedDB:', error);
      // Fallback to sessionStorage
      return sessionStorage.getItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`);
    }
  }

  /**
   * Clear conversation keys
   */
  static async clearConversationKey(conversationId: string): Promise<void> {
    try {
      await this.initializeDB();
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.STORE_NAME);

      await new Promise<void>((resolve, reject) => {
        const request = store.delete(`conv_key_${conversationId}`);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(new Error('Failed to clear conversation key'));
      });
    } catch (error) {
      console.error('Failed to clear conversation key from IndexedDB:', error);
    }

    // Also clear sessionStorage fallback
    sessionStorage.removeItem(`${this.STORAGE_PREFIX}conv_key_${conversationId}`);
  }

  /**
   * Clean up expired keys (maintenance function)
   */
  static async cleanupExpiredKeys(): Promise<void> {
    try {
      await this.initializeDB();
      
      if (!this.db) throw new Error('Database not initialized');

      const transaction = this.db.transaction([this.STORE_NAME], 'readwrite');
      const store = transaction.objectStore(this.STORE_NAME);
      const now = new Date();

      await new Promise<void>((resolve, reject) => {
        const request = store.openCursor();
        request.onsuccess = (event) => {
          const cursor = (event.target as IDBRequest).result;
          if (cursor) {
            const record = cursor.value;
            if (record.expiresAt && new Date(record.expiresAt) < now) {
              cursor.delete();
            }
            cursor.continue();
          } else {
            resolve();
          }
        };
        request.onerror = () => reject(new Error('Failed to cleanup expired keys'));
      });
    } catch (error) {
      console.error('Failed to cleanup expired keys:', error);
    }
  }

  /**
   * Get storage statistics for debugging
   */
  static async getStorageStats(): Promise<{ totalKeys: number; privateKeys: number; conversationKeys: number }> {
    try {
      await this.initializeDB();
      
      if (!this.db) return { totalKeys: 0, privateKeys: 0, conversationKeys: 0 };

      const transaction = this.db.transaction([this.STORE_NAME], 'readonly');
      const store = transaction.objectStore(this.STORE_NAME);

      return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => {
          const records = request.result;
          const stats = {
            totalKeys: records.length,
            privateKeys: records.filter(r => r.type === 'private_key').length,
            conversationKeys: records.filter(r => r.type === 'conversation_key').length,
          };
          resolve(stats);
        };
        request.onerror = () => reject(new Error('Failed to get storage stats'));
      });
    } catch (error) {
      console.error('Failed to get storage stats:', error);
      return { totalKeys: 0, privateKeys: 0, conversationKeys: 0 };
    }
  }
}
