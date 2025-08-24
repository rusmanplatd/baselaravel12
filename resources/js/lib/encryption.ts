export interface KeyPair {
  publicKey: string;
  privateKey: string;
}

export interface EncryptedMessage {
  data: string;
  iv: string;
  hash: string;
}

export class ChatEncryption {
  private static readonly STORAGE_KEYS = {
    PRIVATE_KEY: 'chat_private_key',
    PUBLIC_KEY: 'chat_public_key',
    CONVERSATION_KEYS: 'chat_conversation_keys',
  };

  private static async generateKeyPair(): Promise<CryptoKeyPair> {
    return await window.crypto.subtle.generateKey(
      {
        name: 'RSA-OAEP',
        modulusLength: 2048,
        publicExponent: new Uint8Array([1, 0, 1]),
        hash: 'SHA-256',
      },
      true,
      ['encrypt', 'decrypt']
    );
  }

  private static async generateSymmetricKey(): Promise<CryptoKey> {
    return await window.crypto.subtle.generateKey(
      {
        name: 'AES-GCM',
        length: 256,
      },
      true,
      ['encrypt', 'decrypt']
    );
  }

  public static async createKeyPair(): Promise<KeyPair> {
    const keyPair = await this.generateKeyPair();
    
    const publicKeySpki = await window.crypto.subtle.exportKey('spki', keyPair.publicKey);
    const privateKeyPkcs8 = await window.crypto.subtle.exportKey('pkcs8', keyPair.privateKey);
    
    return {
      publicKey: this.arrayBufferToBase64(publicKeySpki),
      privateKey: this.arrayBufferToBase64(privateKeyPkcs8),
    };
  }

  public static async encryptMessage(
    message: string,
    symmetricKeyBase64: string
  ): Promise<EncryptedMessage> {
    const encoder = new TextEncoder();
    const data = encoder.encode(message);
    
    const symmetricKey = await this.importSymmetricKey(symmetricKeyBase64);
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    
    const encrypted = await window.crypto.subtle.encrypt(
      {
        name: 'AES-GCM',
        iv: iv,
      },
      symmetricKey,
      data
    );

    const hash = await window.crypto.subtle.digest('SHA-256', data);
    
    return {
      data: this.arrayBufferToBase64(encrypted),
      iv: this.arrayBufferToBase64(iv.buffer),
      hash: this.arrayBufferToBase64(hash),
    };
  }

  public static async decryptMessage(
    encryptedData: string,
    iv: string,
    symmetricKeyBase64: string
  ): Promise<string> {
    const symmetricKey = await this.importSymmetricKey(symmetricKeyBase64);
    
    const encrypted = this.base64ToArrayBuffer(encryptedData);
    const ivBytes = this.base64ToArrayBuffer(iv);
    
    const decrypted = await window.crypto.subtle.decrypt(
      {
        name: 'AES-GCM',
        iv: new Uint8Array(ivBytes),
      },
      symmetricKey,
      encrypted
    );
    
    const decoder = new TextDecoder();
    return decoder.decode(decrypted);
  }

  public static async encryptSymmetricKey(
    symmetricKeyBase64: string,
    publicKeyPem: string
  ): Promise<string> {
    const publicKey = await this.importPublicKey(publicKeyPem);
    const symmetricKeyBytes = this.base64ToArrayBuffer(symmetricKeyBase64);
    
    const encrypted = await window.crypto.subtle.encrypt(
      {
        name: 'RSA-OAEP',
      },
      publicKey,
      symmetricKeyBytes
    );
    
    return this.arrayBufferToBase64(encrypted);
  }

  public static async decryptSymmetricKey(
    encryptedKeyBase64: string,
    privateKeyPem: string
  ): Promise<string> {
    const privateKey = await this.importPrivateKey(privateKeyPem);
    const encryptedKey = this.base64ToArrayBuffer(encryptedKeyBase64);
    
    const decrypted = await window.crypto.subtle.decrypt(
      {
        name: 'RSA-OAEP',
      },
      privateKey,
      encryptedKey
    );
    
    return this.arrayBufferToBase64(decrypted);
  }

  public static async generateSymmetricKeyBase64(): Promise<string> {
    const key = await this.generateSymmetricKey();
    const exported = await window.crypto.subtle.exportKey('raw', key);
    return this.arrayBufferToBase64(exported);
  }

  public static async verifyMessageHash(
    message: string,
    hashBase64: string
  ): Promise<boolean> {
    const encoder = new TextEncoder();
    const data = encoder.encode(message);
    const hash = await window.crypto.subtle.digest('SHA-256', data);
    const computedHash = this.arrayBufferToBase64(hash);
    
    return computedHash === hashBase64;
  }

  private static async importPublicKey(publicKeyPem: string): Promise<CryptoKey> {
    const binaryDer = this.base64ToArrayBuffer(publicKeyPem);
    
    return await window.crypto.subtle.importKey(
      'spki',
      binaryDer,
      {
        name: 'RSA-OAEP',
        hash: 'SHA-256',
      },
      false,
      ['encrypt']
    );
  }

  private static async importPrivateKey(privateKeyPem: string): Promise<CryptoKey> {
    const binaryDer = this.base64ToArrayBuffer(privateKeyPem);
    
    return await window.crypto.subtle.importKey(
      'pkcs8',
      binaryDer,
      {
        name: 'RSA-OAEP',
        hash: 'SHA-256',
      },
      false,
      ['decrypt']
    );
  }

  private static async importSymmetricKey(keyBase64: string): Promise<CryptoKey> {
    const keyBytes = this.base64ToArrayBuffer(keyBase64);
    
    return await window.crypto.subtle.importKey(
      'raw',
      keyBytes,
      {
        name: 'AES-GCM',
      },
      false,
      ['encrypt', 'decrypt']
    );
  }

  private static arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    bytes.forEach(byte => binary += String.fromCharCode(byte));
    return window.btoa(binary);
  }

  private static base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  // Key Storage and Management
  public static async initializeKeys(): Promise<KeyPair | null> {
    try {
      const storedPrivateKey = localStorage.getItem(this.STORAGE_KEYS.PRIVATE_KEY);
      const storedPublicKey = localStorage.getItem(this.STORAGE_KEYS.PUBLIC_KEY);

      if (storedPrivateKey && storedPublicKey) {
        return {
          privateKey: storedPrivateKey,
          publicKey: storedPublicKey,
        };
      }

      // Generate new keys if none exist
      const keyPair = await this.createKeyPair();
      await this.storeKeyPair(keyPair);
      return keyPair;
    } catch (error) {
      console.error('Failed to initialize encryption keys:', error);
      return null;
    }
  }

  public static async storeKeyPair(keyPair: KeyPair): Promise<void> {
    try {
      localStorage.setItem(this.STORAGE_KEYS.PRIVATE_KEY, keyPair.privateKey);
      localStorage.setItem(this.STORAGE_KEYS.PUBLIC_KEY, keyPair.publicKey);
    } catch (error) {
      console.error('Failed to store key pair:', error);
      throw new Error('Could not store encryption keys');
    }
  }

  public static getStoredPublicKey(): string | null {
    return localStorage.getItem(this.STORAGE_KEYS.PUBLIC_KEY);
  }

  public static getStoredPrivateKey(): string | null {
    return localStorage.getItem(this.STORAGE_KEYS.PRIVATE_KEY);
  }

  public static clearStoredKeys(): void {
    localStorage.removeItem(this.STORAGE_KEYS.PRIVATE_KEY);
    localStorage.removeItem(this.STORAGE_KEYS.PUBLIC_KEY);
    localStorage.removeItem(this.STORAGE_KEYS.CONVERSATION_KEYS);
  }

  // Conversation Key Management
  public static storeConversationKey(conversationId: string, symmetricKey: string): void {
    try {
      const keys = this.getStoredConversationKeys();
      keys[conversationId] = symmetricKey;
      localStorage.setItem(this.STORAGE_KEYS.CONVERSATION_KEYS, JSON.stringify(keys));
    } catch (error) {
      console.error('Failed to store conversation key:', error);
    }
  }

  public static getConversationKey(conversationId: string): string | null {
    try {
      const keys = this.getStoredConversationKeys();
      return keys[conversationId] || null;
    } catch (error) {
      console.error('Failed to retrieve conversation key:', error);
      return null;
    }
  }

  private static getStoredConversationKeys(): Record<string, string> {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEYS.CONVERSATION_KEYS);
      return stored ? JSON.parse(stored) : {};
    } catch (error) {
      console.error('Failed to parse stored conversation keys:', error);
      return {};
    }
  }

  public static removeConversationKey(conversationId: string): void {
    try {
      const keys = this.getStoredConversationKeys();
      delete keys[conversationId];
      localStorage.setItem(this.STORAGE_KEYS.CONVERSATION_KEYS, JSON.stringify(keys));
    } catch (error) {
      console.error('Failed to remove conversation key:', error);
    }
  }

  // Helper method to prepare encrypted message for API
  public static async prepareEncryptedMessage(
    message: string,
    conversationId: string
  ): Promise<{ encrypted_content: string; content_hash: string } | null> {
    try {
      const symmetricKey = this.getConversationKey(conversationId);
      if (!symmetricKey) {
        console.error('No symmetric key found for conversation:', conversationId);
        return null;
      }

      const encrypted = await this.encryptMessage(message, symmetricKey);
      
      return {
        encrypted_content: JSON.stringify({
          data: encrypted.data,
          iv: encrypted.iv,
        }),
        content_hash: encrypted.hash,
      };
    } catch (error) {
      console.error('Failed to prepare encrypted message:', error);
      return null;
    }
  }

  // Helper method to decrypt message from API response
  public static async decryptReceivedMessage(
    encryptedContent: string,
    conversationId: string,
    contentHash: string
  ): Promise<string | null> {
    try {
      const symmetricKey = this.getConversationKey(conversationId);
      if (!symmetricKey) {
        console.error('No symmetric key found for conversation:', conversationId);
        return null;
      }

      const encryptedData = JSON.parse(encryptedContent);
      const decryptedMessage = await this.decryptMessage(
        encryptedData.data,
        encryptedData.iv,
        symmetricKey
      );

      // Verify message integrity
      const isValid = await this.verifyMessageHash(decryptedMessage, contentHash);
      if (!isValid) {
        console.error('Message integrity check failed');
        return null;
      }

      return decryptedMessage;
    } catch (error) {
      console.error('Failed to decrypt received message:', error);
      return null;
    }
  }

  // Setup encryption for a new conversation
  public static async setupConversationEncryption(
    conversationId: string,
    participantPublicKeys: string[]
  ): Promise<boolean> {
    try {
      const symmetricKey = await this.generateSymmetricKeyBase64();
      
      // Store the symmetric key locally
      this.storeConversationKey(conversationId, symmetricKey);

      // For each participant, encrypt the symmetric key with their public key
      const encryptedKeysForParticipants = [];
      
      for (const publicKey of participantPublicKeys) {
        const encryptedKey = await this.encryptSymmetricKey(symmetricKey, publicKey);
        encryptedKeysForParticipants.push({
          publicKey,
          encryptedKey,
        });
      }

      // This would typically be sent to the server to store the encrypted keys
      console.log('Encrypted keys for participants:', encryptedKeysForParticipants);
      
      return true;
    } catch (error) {
      console.error('Failed to setup conversation encryption:', error);
      return false;
    }
  }

  // Initialize encryption from server-provided encrypted key
  public static async initializeConversationFromEncryptedKey(
    conversationId: string,
    encryptedSymmetricKey: string
  ): Promise<boolean> {
    try {
      const privateKey = this.getStoredPrivateKey();
      if (!privateKey) {
        console.error('No private key available for decryption');
        return false;
      }

      const symmetricKey = await this.decryptSymmetricKey(encryptedSymmetricKey, privateKey);
      this.storeConversationKey(conversationId, symmetricKey);
      
      return true;
    } catch (error) {
      console.error('Failed to initialize conversation encryption:', error);
      return false;
    }
  }
}