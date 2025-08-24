import { useCallback, useEffect, useState } from 'react';
import { ChatEncryption, SecureStorage } from '@/utils/encryption';
import { secureKeyManager } from '@/lib/SecureKeyManager';
import { e2eeErrorRecovery } from '@/services/E2EEErrorRecovery';
import type { EncryptionKey, KeyPair, E2EEStatus, EncryptedMessageData } from '@/types/chat';
import { router } from '@inertiajs/react';

interface UseE2EEReturn {
  status: E2EEStatus;
  initializeE2EE: () => Promise<boolean>;
  generateKeyPair: () => Promise<KeyPair | null>;
  encryptMessage: (message: string, conversationId: string) => Promise<EncryptedMessageData | null>;
  decryptMessage: (encryptedData: EncryptedMessageData, conversationId: string) => Promise<string | null>;
  rotateConversationKey: (conversationId: string, reason?: string) => Promise<boolean>;
  setupConversationEncryption: (conversationId: string, participantPublicKeys: string[]) => Promise<boolean>;
  clearEncryptionData: () => void;
  createBackup: (password: string) => Promise<string | null>;
  restoreFromBackup: (encryptedBackup: string, password: string) => Promise<boolean>;
  bulkEncryptMessages: (messages: Array<{ id: string; content: string }>, conversationId: string) => Promise<Array<{ id: string; encrypted: any | null; error?: string }>>;
  bulkDecryptMessages: (messages: Array<{ id: string; encrypted: any }>, conversationId: string) => Promise<Array<{ id: string; decrypted: any | null; error?: string }>>;
  getKeyStatistics: (conversationId: string) => Promise<any>;
  verifyKeyIntegrity: () => Promise<boolean>;
  validateHealth: () => Promise<any>;
  isReady: boolean;
  error: string | null;
}

export function useE2EE(userId?: string): UseE2EEReturn {
  const [status, setStatus] = useState<E2EEStatus>({
    enabled: false,
    keyGenerated: false,
    conversationKeysReady: false,
    version: '2.0',
  });

  const [isReady, setIsReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Enhanced error handling wrapper
  const handleE2EEError = useCallback((error: Error, operation: string, context?: { conversationId?: string }) => {
    const e2eeError = e2eeErrorRecovery.processError(error, {
      userId,
      operation,
      ...context
    });
    setError(e2eeError.message);
    return e2eeError;
  }, [userId]);

  // Initialize E2EE system
  const initializeE2EE = useCallback(async (): Promise<boolean> => {
    try {
      setError(null);
      
      // Check if keys already exist
      const storedPrivateKey = userId ? SecureStorage.getPrivateKey(userId) : null;
      
      if (!storedPrivateKey) {
        // Generate new key pair
        const keyPair = await ChatEncryption.generateKeyPair();
        
        if (!keyPair || !userId) {
          throw new Error('Failed to generate key pair or user ID not provided');
        }

        // Store private key securely
        SecureStorage.storePrivateKey(userId, keyPair.private_key);

        // Register public key with server
        try {
          await fetch('/api/chat/encryption/register-key', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
              public_key: keyPair.public_key,
            }),
          });
        } catch (serverError) {
          console.warn('Failed to register public key with server:', serverError);
          // Continue anyway as keys are generated locally
        }
      }

      // Test encryption/decryption
      try {
        const testKeyPair = await ChatEncryption.generateKeyPair();
        const symmetricKey = await ChatEncryption.generateSymmetricKey();
        const testMessage = 'E2EE test message';

        const encryptedSymKey = await ChatEncryption.encryptSymmetricKey(symmetricKey, testKeyPair.public_key);
        const decryptedSymKey = await ChatEncryption.decryptSymmetricKey(encryptedSymKey, testKeyPair.private_key);
        
        const encrypted = await ChatEncryption.encryptMessage(testMessage, decryptedSymKey);
        const decrypted = await ChatEncryption.decryptMessage(encrypted, decryptedSymKey);

        if (decrypted.content !== testMessage || !decrypted.verified) {
          throw new Error('E2EE verification failed');
        }
      } catch (testError) {
        throw new Error(`E2EE test failed: ${testError}`);
      }

      setStatus({
        enabled: true,
        keyGenerated: true,
        conversationKeysReady: false,
        version: '2.0',
        lastKeyRotation: new Date().toISOString(),
      });

      setIsReady(true);
      return true;

    } catch (err) {
      const error = err instanceof Error ? err : new Error('Unknown error occurred');
      handleE2EEError(error, 'initialization');
      console.error('E2EE initialization failed:', err);
      return false;
    }
  }, [userId]);

  // Generate new key pair
  const generateKeyPair = useCallback(async (): Promise<KeyPair | null> => {
    try {
      const keyPair = await ChatEncryption.generateKeyPair();
      
      if (keyPair && userId) {
        SecureStorage.storePrivateKey(userId, keyPair.private_key);
        setStatus(prev => ({ ...prev, keyGenerated: true }));
      }
      
      return keyPair;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Key generation failed');
      handleE2EEError(error, 'keyGeneration');
      return null;
    }
  }, [userId]);

  // Encrypt a message for a conversation
  const encryptMessage = useCallback(async (
    message: string,
    conversationId: string
  ): Promise<EncryptedMessageData | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for encryption');
      }

      // Get conversation symmetric key
      let conversationKey = SecureStorage.getConversationKey(conversationId);
      
      if (!conversationKey) {
        // Fetch encrypted key from server
        const response = await fetch(`/api/chat/conversations/${conversationId}/encryption-key`);
        const keyData = await response.json();

        if (keyData.encrypted_key) {
          const privateKey = SecureStorage.getPrivateKey(userId);
          if (!privateKey) {
            throw new Error('No private key available');
          }

          const symmetricKeyCrypto = await ChatEncryption.decryptSymmetricKey(
            keyData.encrypted_key,
            privateKey
          );
          
          // Export the key as base64 string for storage
          const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKeyCrypto);
          conversationKey = btoa(String.fromCharCode(...new Uint8Array(keyBytes)));
          
          SecureStorage.storeConversationKey(conversationId, conversationKey);
        } else {
          throw new Error('No encryption key found for conversation');
        }
      }

      // Import the key back to CryptoKey format
      const keyBytes = new Uint8Array(atob(conversationKey).split('').map(char => char.charCodeAt(0)));
      const symmetricKeyCrypto = await window.crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-CBC' },
        true,
        ['encrypt', 'decrypt']
      );

      return await ChatEncryption.encryptMessage(message, symmetricKeyCrypto, { version: '2.0' });
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Encryption failed');
      handleE2EEError(error, 'encryption', { conversationId });
      return null;
    }
  }, [userId]);

  // Decrypt a message from a conversation
  const decryptMessage = useCallback(async (
    encryptedData: EncryptedMessageData,
    conversationId: string
  ): Promise<string | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for decryption');
      }

      const conversationKey = SecureStorage.getConversationKey(conversationId);
      if (!conversationKey) {
        throw new Error('No conversation key available');
      }

      // Import the key back to CryptoKey format
      const keyBytes = new Uint8Array(atob(conversationKey).split('').map(char => char.charCodeAt(0)));
      const symmetricKeyCrypto = await window.crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-CBC' },
        true,
        ['encrypt', 'decrypt']
      );

      const decrypted = await ChatEncryption.decryptMessage(encryptedData, symmetricKeyCrypto);
      return decrypted.content;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Decryption failed');
      handleE2EEError(error, 'decryption', { conversationId });
      return null;
    }
  }, [userId]);

  // Rotate conversation key
  const rotateConversationKey = useCallback(async (
    conversationId: string,
    reason?: string
  ): Promise<boolean> => {
    try {
      const response = await fetch(`/api/chat/conversations/${conversationId}/rotate-key`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          reason: reason || 'Manual key rotation',
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to rotate conversation key');
      }

      // Clear old conversation key
      SecureStorage.clearConversationKey(conversationId);
      
      setStatus(prev => ({
        ...prev,
        lastKeyRotation: new Date().toISOString(),
      }));

      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Key rotation failed');
      handleE2EEError(error, 'keyRotation', { conversationId });
      return false;
    }
  }, []);

  // Setup encryption for new conversation
  const setupConversationEncryption = useCallback(async (
    conversationId: string,
    participantPublicKeys: string[]
  ): Promise<boolean> => {
    try {
      const symmetricKey = await ChatEncryption.generateSymmetricKey();
      
      // Store locally first
      const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);
      const keyBase64 = btoa(String.fromCharCode(...new Uint8Array(keyBytes)));
      SecureStorage.storeConversationKey(conversationId, keyBase64);

      // Encrypt the symmetric key for each participant and send to server
      const encryptedKeysForParticipants: Array<{
        publicKey: string;
        encryptedKey: string;
        userId?: string;
      }> = [];
      
      for (const publicKey of participantPublicKeys) {
        try {
          const encryptedKey = await ChatEncryption.encryptSymmetricKey(symmetricKey, publicKey);
          encryptedKeysForParticipants.push({
            publicKey,
            encryptedKey,
          });
        } catch (keyError) {
          console.warn('Failed to encrypt key for participant:', keyError);
          // Continue with other participants
        }
      }

      // Send encrypted keys to server
      if (encryptedKeysForParticipants.length > 0) {
        try {
          const response = await fetch(`/api/chat/conversations/${conversationId}/setup-encryption`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
              encrypted_keys: encryptedKeysForParticipants,
              conversation_id: conversationId,
            }),
          });

          if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to setup server-side encryption');
          }

          const result = await response.json();
          console.log('Server-side encryption setup completed:', result);
          
        } catch (serverError) {
          console.error('Server-side encryption setup failed:', serverError);
          // Don't fail the entire setup - client-side encryption can still work
          // The server will handle key distribution when participants join
        }
      }
      
      setStatus(prev => ({ ...prev, conversationKeysReady: true }));
      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Conversation encryption setup failed');
      handleE2EEError(error, 'conversationSetup', { conversationId });
      return false;
    }
  }, []);

  // Clear all encryption data
  const clearEncryptionData = useCallback(async () => {
    try {
      await secureKeyManager.clearAllKeys();
      setStatus({
        enabled: false,
        keyGenerated: false,
        conversationKeysReady: false,
        version: '2.0',
      });
      setIsReady(false);
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Failed to clear encryption data');
      handleE2EEError(error, 'clearData');
    }
  }, []);

  // Create encrypted backup of keys
  const createBackup = useCallback(async (password: string): Promise<string | null> => {
    try {
      setError(null);
      
      if (password.length < 8) {
        throw new Error('Backup password must be at least 8 characters long');
      }

      // Get user's key data
      const keyData = {
        user_id: userId,
        private_key: userId ? SecureStorage.getPrivateKey(userId) : null,
        conversation_keys: JSON.parse(sessionStorage.getItem('chat_e2ee_conv_keys_all') || '{}')
      };

      const backup = await ChatEncryption.createEncryptedBackup(keyData, password);
      return backup;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Backup creation failed');
      handleE2EEError(error, 'createBackup');
      return null;
    }
  }, [userId]);

  // Restore from backup
  const restoreFromBackup = useCallback(async (encryptedBackup: string, password: string): Promise<boolean> => {
    try {
      setError(null);
      
      const keyData = await ChatEncryption.restoreFromBackup(encryptedBackup, password);
      
      if (userId && keyData.user_id === userId) {
        // Restore private key
        if (keyData.private_key) {
          SecureStorage.storePrivateKey(userId, keyData.private_key);
        }
        
        // Restore conversation keys
        if (keyData.conversation_keys) {
          sessionStorage.setItem('chat_e2ee_conv_keys_all', JSON.stringify(keyData.conversation_keys));
          
          // Store individual conversation keys
          for (const [conversationId, key] of Object.entries(keyData.conversation_keys)) {
            SecureStorage.storeConversationKey(conversationId, key as string);
          }
        }
        
        setStatus(prev => ({ ...prev, keyGenerated: true, conversationKeysReady: true }));
        return true;
      }
      
      throw new Error('Backup user ID does not match current user');
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Backup restoration failed');
      handleE2EEError(error, 'restoreBackup');
      return false;
    }
  }, [userId]);

  // Bulk encrypt messages
  const bulkEncryptMessages = useCallback(async (
    messages: Array<{ id: string; content: string }>,
    conversationId: string
  ): Promise<Array<{ id: string; encrypted: any | null; error?: string }>> => {
    try {
      if (!userId) {
        throw new Error('User ID required for encryption');
      }

      const conversationKey = SecureStorage.getConversationKey(conversationId);
      if (!conversationKey) {
        throw new Error('No conversation key available');
      }

      // Import the key back to CryptoKey format
      const keyBytes = new Uint8Array(atob(conversationKey).split('').map(char => char.charCodeAt(0)));
      const symmetricKeyCrypto = await window.crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-CBC' },
        true,
        ['encrypt', 'decrypt']
      );

      return await ChatEncryption.bulkEncryptMessages(messages, symmetricKeyCrypto, { version: '2.0' });
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Bulk encryption failed');
      handleE2EEError(error, 'bulkEncryption', { conversationId });
      return messages.map(msg => ({ id: msg.id, encrypted: null, error: error.message }));
    }
  }, [userId]);

  // Bulk decrypt messages
  const bulkDecryptMessages = useCallback(async (
    messages: Array<{ id: string; encrypted: any }>,
    conversationId: string
  ): Promise<Array<{ id: string; decrypted: any | null; error?: string }>> => {
    try {
      if (!userId) {
        throw new Error('User ID required for decryption');
      }

      const conversationKey = SecureStorage.getConversationKey(conversationId);
      if (!conversationKey) {
        throw new Error('No conversation key available');
      }

      // Import the key back to CryptoKey format
      const keyBytes = new Uint8Array(atob(conversationKey).split('').map(char => char.charCodeAt(0)));
      const symmetricKeyCrypto = await window.crypto.subtle.importKey(
        'raw',
        keyBytes,
        { name: 'AES-CBC' },
        true,
        ['encrypt', 'decrypt']
      );

      return await ChatEncryption.bulkDecryptMessages(messages, symmetricKeyCrypto);
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Bulk decryption failed');
      handleE2EEError(error, 'bulkDecryption', { conversationId });
      return messages.map(msg => ({ id: msg.id, decrypted: null, error: error.message }));
    }
  }, [userId]);

  // Validate encryption health
  const validateHealth = useCallback(async () => {
    try {
      const health = await ChatEncryption.validateHealth();
      
      if (health.status === 'unhealthy') {
        setError(`Encryption system unhealthy: ${health.errors.join(', ')}`);
      } else if (health.warnings.length > 0) {
        console.warn('E2EE health warnings:', health.warnings);
      }
      
      return health;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Health validation failed');
      handleE2EEError(error, 'healthValidation');
      return { status: 'unhealthy' as const, checks: {}, warnings: [], errors: [error.message] };
    }
  }, []);

  // Get conversation key statistics
  const getKeyStatistics = useCallback(async (conversationId: string) => {
    try {
      const stats = await secureKeyManager.getKeyStatistics(conversationId);
      return stats;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Failed to get key statistics');
      handleE2EEError(error, 'getStatistics', { conversationId });
      return null;
    }
  }, []);

  // Verify key integrity
  const verifyKeyIntegrity = useCallback(async (): Promise<boolean> => {
    try {
      if (!userId) return false;
      
      const isValid = await secureKeyManager.verifyKeyIntegrity(userId);
      
      if (!isValid) {
        setError('Key integrity check failed - keys may be corrupted');
      }
      
      return isValid;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Key integrity verification failed');
      handleE2EEError(error, 'keyIntegrityCheck');
      return false;
    }
  }, [userId]);

  // Initialize on mount
  useEffect(() => {
    if (userId && !isReady && !status.enabled) {
      initializeE2EE();
    }
  }, [userId, isReady, status.enabled, initializeE2EE]);

  return {
    status,
    initializeE2EE,
    generateKeyPair,
    encryptMessage,
    decryptMessage,
    rotateConversationKey,
    setupConversationEncryption,
    clearEncryptionData,
    createBackup,
    restoreFromBackup,
    bulkEncryptMessages,
    bulkDecryptMessages,
    getKeyStatistics,
    verifyKeyIntegrity,
    validateHealth,
    isReady,
    error,
  };
}