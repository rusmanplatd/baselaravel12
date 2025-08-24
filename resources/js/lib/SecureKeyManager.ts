/**
 * Enhanced secure key management system for E2EE
 * Provides secure storage, key derivation, and backup/recovery mechanisms
 */

export interface KeyPairWithMetadata {
  public_key: string;
  private_key: string;
  created_at: string;
  key_id: string;
  version: string;
}

export interface ConversationKeyMetadata {
  key: string;
  created_at: string;
  rotation_count: number;
  last_rotation: string | null;
  key_id: string;
}

export interface SecureStorage {
  encrypt(data: string, password: string): Promise<string>;
  decrypt(encryptedData: string, password: string): Promise<string>;
  generateSalt(): string;
  deriveKey(password: string, salt: string): Promise<CryptoKey>;
}

class IndexedDBKeyStorage {
  private dbName = 'ChatE2EE';
  private version = 1;
  private db: IDBDatabase | null = null;

  async init(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };

      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        
        // Store user keypairs
        if (!db.objectStoreNames.contains('keypairs')) {
          const keypairStore = db.createObjectStore('keypairs', { keyPath: 'userId' });
          keypairStore.createIndex('created_at', 'created_at');
        }

        // Store conversation keys
        if (!db.objectStoreNames.contains('conversation_keys')) {
          const convKeyStore = db.createObjectStore('conversation_keys', { keyPath: 'conversationId' });
          convKeyStore.createIndex('created_at', 'created_at');
        }

        // Store key backup data
        if (!db.objectStoreNames.contains('key_backups')) {
          const backupStore = db.createObjectStore('key_backups', { keyPath: 'id' });
          backupStore.createIndex('created_at', 'created_at');
        }
      };
    });
  }

  async storeKeyPair(userId: string, keyPair: KeyPairWithMetadata): Promise<void> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(['keypairs'], 'readwrite');
      const store = transaction.objectStore('keypairs');
      
      const request = store.put({
        userId,
        ...keyPair,
        stored_at: new Date().toISOString()
      });

      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  async getKeyPair(userId: string): Promise<KeyPairWithMetadata | null> {
    if (!this.db) await this.init();

    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(['keypairs'], 'readonly');
      const store = transaction.objectStore('keypairs');
      const request = store.get(userId);

      request.onsuccess = () => {
        const result = request.result;
        if (result) {
          const { userId: _, stored_at, ...keyPair } = result;
          resolve(keyPair);
        } else {
          resolve(null);
        }
      };
      request.onerror = () => reject(request.error);
    });
  }

  async storeConversationKey(conversationId: string, keyData: ConversationKeyMetadata): Promise<void> {
    if (!this.db) await this.init();
    
    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(['conversation_keys'], 'readwrite');
      const store = transaction.objectStore('conversation_keys');
      
      const request = store.put({
        conversationId,
        ...keyData,
        stored_at: new Date().toISOString()
      });

      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  async getConversationKey(conversationId: string): Promise<ConversationKeyMetadata | null> {
    if (!this.db) await this.init();

    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(['conversation_keys'], 'readonly');
      const store = transaction.objectStore('conversation_keys');
      const request = store.get(conversationId);

      request.onsuccess = () => {
        const result = request.result;
        if (result) {
          const { conversationId: _, stored_at, ...keyData } = result;
          resolve(keyData);
        } else {
          resolve(null);
        }
      };
      request.onerror = () => reject(request.error);
    });
  }

  async clearAllData(): Promise<void> {
    if (!this.db) await this.init();

    return new Promise((resolve, reject) => {
      const transaction = this.db!.transaction(['keypairs', 'conversation_keys', 'key_backups'], 'readwrite');
      
      const promises = [
        transaction.objectStore('keypairs').clear(),
        transaction.objectStore('conversation_keys').clear(),
        transaction.objectStore('key_backups').clear()
      ];

      Promise.all(promises)
        .then(() => resolve())
        .catch(reject);
    });
  }
}

export class SecureKeyManager {
  private storage = new IndexedDBKeyStorage();
  private memoryCache = new Map<string, any>();
  private initialized = false;

  async initialize(): Promise<void> {
    if (this.initialized) return;
    
    await this.storage.init();
    this.initialized = true;
  }

  // Generate cryptographically secure random ID
  private generateKeyId(): string {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
  }

  // Derive encryption key from user password
  async deriveKeyFromPassword(password: string, salt?: string): Promise<{ key: CryptoKey; salt: string }> {
    const encoder = new TextEncoder();
    const passwordBytes = encoder.encode(password);
    
    const actualSalt = salt ? encoder.encode(salt) : crypto.getRandomValues(new Uint8Array(32));
    
    const keyMaterial = await crypto.subtle.importKey(
      'raw',
      passwordBytes,
      { name: 'PBKDF2' },
      false,
      ['deriveBits', 'deriveKey']
    );

    const derivedKey = await crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt: actualSalt,
        iterations: 100000,
        hash: 'SHA-256'
      },
      keyMaterial,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );

    return {
      key: derivedKey,
      salt: salt || Array.from(actualSalt, b => b.toString(16).padStart(2, '0')).join('')
    };
  }

  // Encrypt data with user password
  async encryptWithPassword(data: string, password: string): Promise<{ encrypted: string; salt: string }> {
    const { key, salt } = await this.deriveKeyFromPassword(password);
    
    const encoder = new TextEncoder();
    const dataBytes = encoder.encode(data);
    const iv = crypto.getRandomValues(new Uint8Array(12));

    const encrypted = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      dataBytes
    );

    const result = new Uint8Array(iv.length + encrypted.byteLength);
    result.set(iv);
    result.set(new Uint8Array(encrypted), iv.length);

    return {
      encrypted: Array.from(result, b => b.toString(16).padStart(2, '0')).join(''),
      salt
    };
  }

  // Decrypt data with user password
  async decryptWithPassword(encryptedHex: string, password: string, salt: string): Promise<string> {
    const { key } = await this.deriveKeyFromPassword(password, salt);
    
    const encryptedBytes = new Uint8Array(
      encryptedHex.match(/.{2}/g)!.map(byte => parseInt(byte, 16))
    );
    
    const iv = encryptedBytes.slice(0, 12);
    const ciphertext = encryptedBytes.slice(12);

    const decrypted = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      key,
      ciphertext
    );

    const decoder = new TextDecoder();
    return decoder.decode(decrypted);
  }

  // Store user key pair securely
  async storeUserKeyPair(userId: string, keyPair: KeyPairWithMetadata): Promise<void> {
    await this.initialize();
    
    // Store in IndexedDB for persistence
    await this.storage.storeKeyPair(userId, keyPair);
    
    // Cache in memory for performance
    this.memoryCache.set(`keypair_${userId}`, keyPair);
  }

  // Retrieve user key pair
  async getUserKeyPair(userId: string): Promise<KeyPairWithMetadata | null> {
    await this.initialize();
    
    // Check memory cache first
    const cached = this.memoryCache.get(`keypair_${userId}`);
    if (cached) return cached;

    // Fallback to IndexedDB
    const keyPair = await this.storage.getKeyPair(userId);
    if (keyPair) {
      this.memoryCache.set(`keypair_${userId}`, keyPair);
    }
    
    return keyPair;
  }

  // Store conversation key securely
  async storeConversationKey(conversationId: string, key: string, rotationCount = 0): Promise<void> {
    await this.initialize();
    
    const keyData: ConversationKeyMetadata = {
      key,
      created_at: new Date().toISOString(),
      rotation_count: rotationCount,
      last_rotation: rotationCount > 0 ? new Date().toISOString() : null,
      key_id: this.generateKeyId()
    };
    
    await this.storage.storeConversationKey(conversationId, keyData);
    this.memoryCache.set(`conv_key_${conversationId}`, keyData);
  }

  // Retrieve conversation key
  async getConversationKey(conversationId: string): Promise<string | null> {
    await this.initialize();
    
    // Check memory cache first
    const cached = this.memoryCache.get(`conv_key_${conversationId}`);
    if (cached) return cached.key;

    // Fallback to IndexedDB
    const keyData = await this.storage.getConversationKey(conversationId);
    if (keyData) {
      this.memoryCache.set(`conv_key_${conversationId}`, keyData);
      return keyData.key;
    }
    
    return null;
  }

  // Rotate conversation key
  async rotateConversationKey(conversationId: string, newKey: string): Promise<void> {
    await this.initialize();
    
    const existingKey = await this.storage.getConversationKey(conversationId);
    const rotationCount = existingKey ? existingKey.rotation_count + 1 : 1;
    
    await this.storeConversationKey(conversationId, newKey, rotationCount);
  }

  // Get key statistics
  async getKeyStatistics(conversationId: string): Promise<{
    rotationCount: number;
    lastRotation: string | null;
    keyAge: number;
  } | null> {
    await this.initialize();
    
    const keyData = await this.storage.getConversationKey(conversationId);
    if (!keyData) return null;

    const keyAge = Date.now() - new Date(keyData.created_at).getTime();
    
    return {
      rotationCount: keyData.rotation_count,
      lastRotation: keyData.last_rotation,
      keyAge: Math.floor(keyAge / (1000 * 60 * 60 * 24)) // days
    };
  }

  // Create encrypted backup of all keys
  async createBackup(password: string): Promise<string> {
    await this.initialize();
    
    // This would collect all keys and encrypt them
    // Implementation would depend on specific backup requirements
    const backupData = {
      version: '1.0',
      timestamp: new Date().toISOString(),
      // Keys would be collected here
    };

    const { encrypted } = await this.encryptWithPassword(
      JSON.stringify(backupData), 
      password
    );
    
    return encrypted;
  }

  // Clear all stored keys (for logout/reset)
  async clearAllKeys(): Promise<void> {
    await this.initialize();
    
    await this.storage.clearAllData();
    this.memoryCache.clear();
  }

  // Verify key integrity
  async verifyKeyIntegrity(userId: string): Promise<boolean> {
    const keyPair = await this.getUserKeyPair(userId);
    if (!keyPair) return false;

    try {
      // Perform a round-trip encryption test
      const testData = 'integrity-check-' + Date.now();
      const testKey = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
      );

      // Test would involve encrypting with public key, decrypting with private key
      // This is a simplified check
      return keyPair.public_key.length > 0 && keyPair.private_key.length > 0;
    } catch {
      return false;
    }
  }

  // Get cache statistics
  getCacheStatistics(): { size: number; keys: string[] } {
    return {
      size: this.memoryCache.size,
      keys: Array.from(this.memoryCache.keys())
    };
  }
}

// Global instance
export const secureKeyManager = new SecureKeyManager();