/**
 * Automatic Key Exchange System for E2EE
 * Handles seamless key generation and distribution for new conversations
 */

import { ChatEncryption } from '@/utils/encryption';
import { secureKeyManager } from './SecureKeyManager';
import type { User, Conversation } from '@/types/chat';

export interface KeyExchangeOptions {
  conversationId: string;
  participants: User[];
  initiatorId: string;
  keyRotationEnabled?: boolean;
  backupToServer?: boolean;
}

export interface KeyExchangeResult {
  success: boolean;
  conversationId: string;
  symmetricKeyId: string;
  participantsWithKeys: string[];
  failedParticipants: { userId: string; error: string }[];
  backupCreated?: boolean;
}

export interface ParticipantKeyData {
  userId: string;
  publicKey: string;
  encryptedSymmetricKey: string;
  keyVersion: string;
}

class AutoKeyExchangeManager {
  private pendingExchanges = new Map<string, KeyExchangeOptions>();
  private exchangeCallbacks = new Map<string, (result: KeyExchangeResult) => void>();

  /**
   * Initialize key exchange for a new conversation
   */
  async initializeConversationKeys(options: KeyExchangeOptions): Promise<KeyExchangeResult> {
    const { conversationId, participants, initiatorId } = options;
    
    try {
      // Generate new symmetric key for the conversation
      const symmetricKey = await ChatEncryption.generateSymmetricKey();
      const symmetricKeyId = this.generateKeyId();
      
      // Export symmetric key as base64 for storage
      const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);
      const symmetricKeyBase64 = this.arrayBufferToBase64(keyBytes);

      const participantsWithKeys: string[] = [];
      const failedParticipants: { userId: string; error: string }[] = [];
      const participantKeys: ParticipantKeyData[] = [];

      // Process each participant
      for (const participant of participants) {
        try {
          const publicKey = await this.getParticipantPublicKey(participant.id);
          
          if (!publicKey) {
            // If participant doesn't have keys yet, initiate key generation for them
            if (participant.id === initiatorId) {
              // Generate keys for initiator if they don't exist
              await this.generateKeysForUser(participant.id);
              const newPublicKey = await this.getParticipantPublicKey(participant.id);
              if (newPublicKey) {
                const encryptedSymKey = await this.encryptSymmetricKeyForUser(
                  symmetricKeyBase64,
                  newPublicKey
                );
                participantKeys.push({
                  userId: participant.id,
                  publicKey: newPublicKey,
                  encryptedSymmetricKey: encryptedSymKey,
                  keyVersion: '2.0'
                });
                participantsWithKeys.push(participant.id);
              }
            } else {
              failedParticipants.push({
                userId: participant.id,
                error: 'No public key available - user needs to initialize E2EE'
              });
            }
            continue;
          }

          // Encrypt symmetric key for this participant
          const encryptedSymKey = await this.encryptSymmetricKeyForUser(
            symmetricKeyBase64,
            publicKey
          );

          participantKeys.push({
            userId: participant.id,
            publicKey,
            encryptedSymmetricKey: encryptedSymKey,
            keyVersion: '2.0'
          });

          participantsWithKeys.push(participant.id);

        } catch (error) {
          failedParticipants.push({
            userId: participant.id,
            error: error instanceof Error ? error.message : 'Key encryption failed'
          });
        }
      }

      // Store the symmetric key locally for the initiator
      await secureKeyManager.storeConversationKey(conversationId, symmetricKeyBase64);

      // Send encrypted keys to server for distribution
      const serverResult = await this.distributeKeysToParticipants(
        conversationId,
        participantKeys
      );

      // Create backup if requested
      let backupCreated = false;
      if (options.backupToServer && participantsWithKeys.length > 0) {
        backupCreated = await this.createServerBackup(conversationId, participantKeys);
      }

      const result: KeyExchangeResult = {
        success: participantsWithKeys.length > 0,
        conversationId,
        symmetricKeyId,
        participantsWithKeys,
        failedParticipants,
        backupCreated
      };

      // Trigger any waiting callbacks
      const callback = this.exchangeCallbacks.get(conversationId);
      if (callback) {
        callback(result);
        this.exchangeCallbacks.delete(conversationId);
      }

      return result;

    } catch (error) {
      return {
        success: false,
        conversationId,
        symmetricKeyId: '',
        participantsWithKeys: [],
        failedParticipants: participants.map(p => ({
          userId: p.id,
          error: error instanceof Error ? error.message : 'Key exchange failed'
        }))
      };
    }
  }

  /**
   * Handle joining an existing conversation
   */
  async joinConversation(
    conversationId: string,
    userId: string,
    encryptedSymmetricKey: string
  ): Promise<boolean> {
    try {
      // Get user's private key
      const keyPair = await secureKeyManager.getUserKeyPair(userId);
      if (!keyPair) {
        throw new Error('User private key not found');
      }

      // Decrypt the symmetric key
      const symmetricKey = await ChatEncryption.decryptSymmetricKey(
        encryptedSymmetricKey,
        keyPair.private_key
      );

      // Export and store the decrypted symmetric key
      const keyBytes = await window.crypto.subtle.exportKey('raw', symmetricKey);
      const symmetricKeyBase64 = this.arrayBufferToBase64(keyBytes);
      
      await secureKeyManager.storeConversationKey(conversationId, symmetricKeyBase64);

      return true;
    } catch (error) {
      console.error('Failed to join conversation:', error);
      return false;
    }
  }

  /**
   * Rotate keys for an existing conversation
   */
  async rotateConversationKeys(
    conversationId: string,
    participants: User[],
    reason = 'Manual rotation'
  ): Promise<KeyExchangeResult> {
    try {
      // This is similar to initialization but marks it as a rotation
      const result = await this.initializeConversationKeys({
        conversationId,
        participants,
        initiatorId: participants[0].id, // First participant initiates
        keyRotationEnabled: true
      });

      if (result.success) {
        // Store rotation metadata
        await this.recordKeyRotation(conversationId, reason);
      }

      return result;
    } catch (error) {
      return {
        success: false,
        conversationId,
        symmetricKeyId: '',
        participantsWithKeys: [],
        failedParticipants: [{
          userId: 'system',
          error: error instanceof Error ? error.message : 'Key rotation failed'
        }]
      };
    }
  }

  /**
   * Get participant's public key from server or cache
   */
  private async getParticipantPublicKey(userId: string): Promise<string | null> {
    try {
      // Try to get from local storage first
      const keyPair = await secureKeyManager.getUserKeyPair(userId);
      if (keyPair) {
        return keyPair.public_key;
      }

      // Fetch from server
      const response = await fetch(`/api/users/${userId}/public-key`);
      if (response.ok) {
        const data = await response.json();
        return data.public_key || null;
      }

      return null;
    } catch (error) {
      console.error(`Failed to get public key for user ${userId}:`, error);
      return null;
    }
  }

  /**
   * Generate keys for a user who doesn't have them
   */
  private async generateKeysForUser(userId: string): Promise<boolean> {
    try {
      const keyPair = await ChatEncryption.generateKeyPair();
      
      await secureKeyManager.storeUserKeyPair(userId, {
        ...keyPair,
        created_at: new Date().toISOString(),
        key_id: this.generateKeyId(),
        version: '2.0'
      });

      // Register public key with server
      await fetch('/api/chat/encryption/register-key', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          public_key: keyPair.public_key
        })
      });

      return true;
    } catch (error) {
      console.error('Failed to generate keys for user:', error);
      return false;
    }
  }

  /**
   * Encrypt symmetric key for a specific user
   */
  private async encryptSymmetricKeyForUser(
    symmetricKeyBase64: string,
    publicKeyPem: string
  ): Promise<string> {
    // Convert base64 symmetric key back to CryptoKey for encryption
    const keyBytes = this.base64ToArrayBuffer(symmetricKeyBase64);
    const symmetricKey = await window.crypto.subtle.importKey(
      'raw',
      keyBytes,
      { name: 'AES-CBC' },
      true,
      ['encrypt', 'decrypt']
    );

    return await ChatEncryption.encryptSymmetricKey(symmetricKey, publicKeyPem);
  }

  /**
   * Distribute encrypted keys to all participants via server
   */
  private async distributeKeysToParticipants(
    conversationId: string,
    participantKeys: ParticipantKeyData[]
  ): Promise<boolean> {
    try {
      const response = await fetch(`/api/chat/conversations/${conversationId}/distribute-keys`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          participant_keys: participantKeys
        })
      });

      return response.ok;
    } catch (error) {
      console.error('Failed to distribute keys:', error);
      return false;
    }
  }

  /**
   * Create server backup of conversation keys
   */
  private async createServerBackup(
    conversationId: string,
    participantKeys: ParticipantKeyData[]
  ): Promise<boolean> {
    try {
      const response = await fetch(`/api/chat/conversations/${conversationId}/backup-keys`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          backup_data: {
            conversation_id: conversationId,
            participant_keys: participantKeys,
            created_at: new Date().toISOString()
          }
        })
      });

      return response.ok;
    } catch (error) {
      console.error('Failed to create server backup:', error);
      return false;
    }
  }

  /**
   * Record key rotation event
   */
  private async recordKeyRotation(conversationId: string, reason: string): Promise<void> {
    try {
      await fetch(`/api/chat/conversations/${conversationId}/key-rotation`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          reason,
          timestamp: new Date().toISOString()
        })
      });
    } catch (error) {
      console.error('Failed to record key rotation:', error);
    }
  }

  /**
   * Set up automatic key exchange when conversation is created
   */
  async setupAutoExchange(
    conversation: Conversation,
    participants: User[],
    currentUserId: string
  ): Promise<KeyExchangeResult> {
    return this.initializeConversationKeys({
      conversationId: conversation.id,
      participants,
      initiatorId: currentUserId,
      keyRotationEnabled: true,
      backupToServer: true
    });
  }

  /**
   * Check if conversation needs key refresh
   */
  async checkKeyFreshness(conversationId: string): Promise<{
    needsRotation: boolean;
    daysSinceRotation: number;
    recommendation: string;
  }> {
    const stats = await secureKeyManager.getKeyStatistics(conversationId);
    
    if (!stats) {
      return {
        needsRotation: true,
        daysSinceRotation: 0,
        recommendation: 'No keys found - initialize encryption'
      };
    }

    const daysSinceRotation = stats.lastRotation 
      ? Math.floor((Date.now() - new Date(stats.lastRotation).getTime()) / (1000 * 60 * 60 * 24))
      : stats.keyAge;

    let needsRotation = false;
    let recommendation = 'Keys are fresh';

    if (daysSinceRotation > 90) {
      needsRotation = true;
      recommendation = 'Keys are over 90 days old - rotation recommended';
    } else if (daysSinceRotation > 30) {
      recommendation = 'Consider rotating keys soon';
    }

    return {
      needsRotation,
      daysSinceRotation,
      recommendation
    };
  }

  // Utility methods
  private generateKeyId(): string {
    return 'key_' + Date.now() + '_' + Math.random().toString(36).substring(2);
  }

  private arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
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

// Export singleton instance
export const autoKeyExchange = new AutoKeyExchangeManager();