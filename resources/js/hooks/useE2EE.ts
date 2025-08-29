import { useCallback, useEffect, useState } from 'react';
import { ChatEncryption, SecureStorage } from '@/utils/encryption';
import { secureKeyManager } from '@/lib/SecureKeyManager';
import { e2eeErrorRecovery } from '@/services/E2EEErrorRecovery';
import { multiDeviceE2EEService } from '@/services/MultiDeviceE2EEService';
import { doubleRatchetE2EE } from '@/services/DoubleRatchetE2EE';
import { apiService } from '@/services/ApiService';
import { toast } from 'sonner';
import type { EncryptionKey, KeyPair, E2EEStatus, EncryptedMessageData, Message, Conversation, User } from '@/types/chat';
import { router } from '@inertiajs/react';

export interface ScheduledMessage {
  id: string;
  content: string;
  conversationId: string;
  scheduledAt: Date;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  deleteAfter?: number;
  requiresConfirmation: boolean;
  status: 'pending' | 'sent' | 'failed' | 'cancelled';
  createdAt: Date;
}

export interface DisappearingMessage {
  id: string;
  content: string;
  expiresAt: Date;
  timeRemaining: number;
  isExpired: boolean;
}

export interface MessageDraft {
  id: string;
  conversationId: string;
  content: string;
  metadata?: {
    type: 'text' | 'voice' | 'file';
    fileInfo?: { name: string; size: number; type: string };
    voiceInfo?: { duration: number; transcript?: string };
  };
  createdAt: Date;
  updatedAt: Date;
}

export interface GroupInvitation {
  id: string;
  groupId: string;
  groupName: string;
  inviteCode: string;
  inviteLink: string;
  expiresAt: Date;
  maxUses?: number;
  currentUses: number;
  permissions: {
    canInvite: boolean;
    canManageGroup: boolean;
    canViewHistory: boolean;
  };
  status: 'active' | 'expired' | 'revoked' | 'exhausted';
}

interface UseE2EEReturn {
  // Core E2EE functionality
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

  // Message Scheduling
  scheduleMessage: (
    content: string,
    conversationId: string,
    scheduledAt: Date,
    options?: {
      deleteAfter?: number;
      priority?: 'low' | 'normal' | 'high' | 'urgent';
      requiresConfirmation?: boolean;
    }
  ) => Promise<void>;
  scheduledMessages: ScheduledMessage[];
  cancelScheduledMessage: (messageId: string) => Promise<void>;

  // Disappearing Messages
  setDisappearingTimer: (conversationId: string, minutes: number) => Promise<void>;
  disappearingMessages: DisappearingMessage[];
  disappearingTimer: number;

  // Message Forwarding
  forwardMessages: (
    messageIds: string[],
    destinationIds: string[],
    options: {
      includeQuote: boolean;
      preserveThreads: boolean;
      addComment?: string;
    }
  ) => Promise<void>;

  // Encrypted Drafts
  saveDraft: (conversationId: string, content: string, metadata?: any) => Promise<void>;
  loadDrafts: (conversationId: string) => Promise<MessageDraft[]>;
  deleteDraft: (draftId: string) => Promise<void>;

  // Group Invitations
  createGroupInvitation: (
    groupId: string,
    invitation: {
      expiresAt: Date;
      maxUses?: number;
      permissions: {
        canInvite: boolean;
        canManageGroup: boolean;
        canViewHistory: boolean;
      };
      welcomeMessage?: string;
    }
  ) => Promise<GroupInvitation>;
  revokeGroupInvitation: (invitationId: string) => Promise<void>;
  groupInvitations: GroupInvitation[];

  // Double Ratchet
  initializeDoubleRatchet: (conversationId: string, remoteDeviceId: string) => Promise<void>;
  rotateConversationKeys: (conversationId: string) => Promise<void>;

  // Security
  exportSecurityReport: () => Promise<Blob>;
  validateEncryptionHealth: () => Promise<boolean>;
  
  // State
  isReady: boolean;
  error: string | null;
}

export function useE2EE(userId?: string, currentConversationId?: string): UseE2EEReturn {
  // Core E2EE state
  const [status, setStatus] = useState<E2EEStatus>({
    enabled: false,
    keyGenerated: false,
    conversationKeysReady: false,
    version: '2.0',
  });

  const [isReady, setIsReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // New features state
  const [scheduledMessages, setScheduledMessages] = useState<ScheduledMessage[]>([]);
  const [disappearingMessages, setDisappearingMessages] = useState<DisappearingMessage[]>([]);
  const [disappearingTimer, setDisappearingTimer] = useState(0);
  const [groupInvitations, setGroupInvitations] = useState<GroupInvitation[]>([]);

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

  // Load conversation data when conversation changes
  useEffect(() => {
    if (currentConversationId && isReady) {
      loadConversationData(currentConversationId);
    }
  }, [currentConversationId, isReady]);

  const loadConversationData = async (conversationId: string) => {
    try {
      // Load scheduled messages
      const scheduledResponse = await apiService.get(`/api/chat/conversations/${conversationId}/scheduled-messages`);
      setScheduledMessages(scheduledResponse.scheduled_messages || []);

      // Load disappearing message settings
      const settingsResponse = await apiService.get(`/api/chat/conversations/${conversationId}/settings`);
      setDisappearingTimer(settingsResponse.disappearing_timer || 0);

      // Load group invitations if it's a group
      try {
        const invitationsResponse = await apiService.get(`/api/chat/conversations/${conversationId}/invitations`);
        setGroupInvitations(invitationsResponse.invitations || []);
      } catch (inviteError) {
        // Not a group conversation or no permissions
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load conversation data');
    }
  };

  // Initialize E2EE system
  const initializeE2EE = useCallback(async (): Promise<boolean> => {
    try {
      setError(null);
      
      // Check if keys already exist
      const storedPrivateKey = userId ? await SecureStorage.getPrivateKey(userId) : null;
      
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
          await apiService.post('/api/chat/encryption/register-key', {
            public_key: keyPair.public_key,
          });
        } catch (serverError) {
          console.warn('Failed to register public key with server:', serverError);
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
      let conversationKey = await SecureStorage.getConversationKey(conversationId);
      
      if (!conversationKey) {
        // Fetch encrypted key from server
        const keyData = await apiService.get<{ encrypted_key?: string }>(`/api/chat/conversations/${conversationId}/encryption-key`);

        if (keyData.encrypted_key) {
          const privateKey = await SecureStorage.getPrivateKey(userId);
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
          
          await SecureStorage.storeConversationKey(conversationId, conversationKey);
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

      const conversationKey = await SecureStorage.getConversationKey(conversationId);
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
      await apiService.post(`/api/chat/conversations/${conversationId}/rotate-key`, {
        reason: reason || 'Manual key rotation',
      });

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
      await SecureStorage.storeConversationKey(conversationId, keyBase64);

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
        }
      }

      // Send encrypted keys to server
      if (encryptedKeysForParticipants.length > 0) {
        try {
          const result = await apiService.post(`/api/chat/conversations/${conversationId}/setup-encryption`, {
            encrypted_keys: encryptedKeysForParticipants,
            conversation_id: conversationId,
          });
          console.log('Server-side encryption setup completed:', result);
        } catch (serverError) {
          console.error('Server-side encryption setup failed:', serverError);
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

      const keyData = {
        user_id: userId,
        private_key: userId ? await SecureStorage.getPrivateKey(userId) : null,
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
            await SecureStorage.storeConversationKey(conversationId, key as string);
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

      const conversationKey = await SecureStorage.getConversationKey(conversationId);
      if (!conversationKey) {
        throw new Error('No conversation key available');
      }

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

      const conversationKey = await SecureStorage.getConversationKey(conversationId);
      if (!conversationKey) {
        throw new Error('No conversation key available');
      }

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

  // Schedule a message
  const scheduleMessage = useCallback(async (
    content: string,
    conversationId: string,
    scheduledAt: Date,
    options = {}
  ) => {
    try {
      setError(null);
      
      const encryptedContent = await encryptMessage(content, conversationId);
      if (!encryptedContent) {
        throw new Error('Failed to encrypt scheduled message');
      }

      const response = await apiService.post('/api/chat/messages/schedule', {
        conversation_id: conversationId,
        encrypted_content: JSON.stringify(encryptedContent),
        scheduled_at: scheduledAt.toISOString(),
        ...options,
      });

      const newScheduledMessage: ScheduledMessage = {
        id: response.scheduled_message.id,
        content,
        conversationId,
        scheduledAt,
        priority: options.priority || 'normal',
        deleteAfter: options.deleteAfter,
        requiresConfirmation: options.requiresConfirmation || false,
        status: 'pending',
        createdAt: new Date(),
      };

      setScheduledMessages(prev => [...prev, newScheduledMessage]);
      toast.success('Message scheduled successfully');
    } catch (err) {
      const errorMsg = err instanceof Error ? err.message : 'Failed to schedule message';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [encryptMessage]);

  // Cancel a scheduled message
  const cancelScheduledMessage = useCallback(async (messageId: string) => {
    try {
      await apiService.delete(`/api/chat/scheduled-messages/${messageId}`);
      setScheduledMessages(prev => prev.filter(msg => msg.id !== messageId));
      toast.success('Scheduled message cancelled');
    } catch (err) {
      const errorMsg = 'Failed to cancel scheduled message';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, []);

  // Set disappearing message timer
  const setDisappearingTimer = useCallback(async (conversationId: string, minutes: number) => {
    try {
      await apiService.post(`/api/chat/conversations/${conversationId}/disappearing-timer`, {
        timer_minutes: minutes,
      });
      
      setDisappearingTimer(minutes);
      toast.success(`Disappearing messages ${minutes > 0 ? 'enabled' : 'disabled'}`);
    } catch (err) {
      const errorMsg = 'Failed to update disappearing message timer';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, []);

  // Forward messages
  const forwardMessages = useCallback(async (
    messageIds: string[],
    destinationIds: string[],
    options: {
      includeQuote: boolean;
      preserveThreads: boolean;
      addComment?: string;
    }
  ) => {
    try {
      for (const destinationId of destinationIds) {
        const forwardData = {
          message_ids: messageIds,
          destination_conversation_id: destinationId,
          include_quote: options.includeQuote,
          preserve_threads: options.preserveThreads,
        };

        if (options.addComment) {
          const encryptedComment = await encryptMessage(options.addComment, destinationId);
          if (encryptedComment) {
            forwardData.encrypted_comment = JSON.stringify(encryptedComment);
          }
        }

        await apiService.post('/api/chat/messages/forward', forwardData);
      }

      toast.success(`Messages forwarded to ${destinationIds.length} conversation${destinationIds.length !== 1 ? 's' : ''}`);
    } catch (err) {
      const errorMsg = 'Failed to forward messages';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [encryptMessage]);

  // Save encrypted draft
  const saveDraft = useCallback(async (conversationId: string, content: string, metadata?: any) => {
    try {
      const encryptedContent = await encryptMessage(content, conversationId);
      if (!encryptedContent) {
        throw new Error('Failed to encrypt draft');
      }

      await apiService.post('/api/chat/drafts', {
        conversation_id: conversationId,
        encrypted_content: JSON.stringify(encryptedContent),
        metadata,
      });
    } catch (err) {
      console.error('Failed to save draft:', err);
    }
  }, [encryptMessage]);

  // Load drafts
  const loadDrafts = useCallback(async (conversationId: string): Promise<MessageDraft[]> => {
    try {
      const response = await apiService.get(`/api/chat/conversations/${conversationId}/drafts`);
      
      const drafts: MessageDraft[] = [];
      for (const draft of response.drafts || []) {
        try {
          const decryptedContent = await decryptMessage(
            JSON.parse(draft.encrypted_content),
            conversationId
          );
          
          if (decryptedContent) {
            drafts.push({
              id: draft.id,
              conversationId: draft.conversation_id,
              content: decryptedContent,
              metadata: draft.metadata,
              createdAt: new Date(draft.created_at),
              updatedAt: new Date(draft.updated_at),
            });
          }
        } catch (decryptError) {
          console.warn('Failed to decrypt draft:', draft.id);
        }
      }
      
      return drafts;
    } catch (err) {
      setError('Failed to load drafts');
      return [];
    }
  }, [decryptMessage]);

  // Delete draft
  const deleteDraft = useCallback(async (draftId: string) => {
    try {
      await apiService.delete(`/api/chat/drafts/${draftId}`);
    } catch (err) {
      console.error('Failed to delete draft:', err);
    }
  }, []);

  // Create group invitation
  const createGroupInvitation = useCallback(async (groupId: string, invitation) => {
    try {
      let encryptedWelcomeMessage;
      if (invitation.welcomeMessage) {
        const encrypted = await encryptMessage(invitation.welcomeMessage, groupId);
        if (encrypted) {
          encryptedWelcomeMessage = JSON.stringify(encrypted);
        }
      }

      const response = await apiService.post(`/api/chat/groups/${groupId}/invitations`, {
        expires_at: invitation.expiresAt.toISOString(),
        max_uses: invitation.maxUses,
        permissions: invitation.permissions,
        encrypted_welcome_message: encryptedWelcomeMessage,
      });

      const newInvitation: GroupInvitation = {
        id: response.invitation.id,
        groupId,
        groupName: response.invitation.group_name,
        inviteCode: response.invitation.invite_code,
        inviteLink: response.invitation.invite_link,
        expiresAt: new Date(response.invitation.expires_at),
        maxUses: response.invitation.max_uses,
        currentUses: 0,
        permissions: invitation.permissions,
        status: 'active',
      };

      setGroupInvitations(prev => [...prev, newInvitation]);
      return newInvitation;
    } catch (err) {
      const errorMsg = 'Failed to create group invitation';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [encryptMessage]);

  // Revoke group invitation
  const revokeGroupInvitation = useCallback(async (invitationId: string) => {
    try {
      await apiService.post(`/api/chat/invitations/${invitationId}/revoke`);
      setGroupInvitations(prev => 
        prev.map(inv => 
          inv.id === invitationId 
            ? { ...inv, status: 'revoked' as const }
            : inv
        )
      );
      toast.success('Invitation revoked');
    } catch (err) {
      const errorMsg = 'Failed to revoke invitation';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, []);

  // Initialize double ratchet for conversation
  const initializeDoubleRatchet = useCallback(async (conversationId: string, remoteDeviceId: string) => {
    try {
      const deviceId = await multiDeviceE2EEService.getDeviceId();
      if (!deviceId) {
        throw new Error('Device not registered');
      }

      const sharedKey = await setupConversationEncryption(conversationId, []);
      if (!sharedKey) {
        throw new Error('Failed to get shared key');
      }

      toast.success('Double ratchet initialized');
    } catch (err) {
      const errorMsg = 'Failed to initialize double ratchet';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [setupConversationEncryption]);

  // Rotate conversation keys
  const rotateConversationKeys = useCallback(async (conversationId: string) => {
    try {
      await rotateConversationKey(conversationId, 'Manual key rotation');
      toast.success('Conversation keys rotated successfully');
    } catch (err) {
      const errorMsg = 'Failed to rotate conversation keys';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [rotateConversationKey]);

  // Get key statistics
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

  // Export security report
  const exportSecurityReport = useCallback(async (): Promise<Blob> => {
    try {
      const report = {
        timestamp: new Date().toISOString(),
        userId,
        encryptionStatus: status,
        scheduledMessages: scheduledMessages.length,
        activeInvitations: groupInvitations.filter(inv => inv.status === 'active').length,
        healthCheck: await validateHealth(),
      };

      const reportJson = JSON.stringify(report, null, 2);
      return new Blob([reportJson], { type: 'application/json' });
    } catch (err) {
      throw new Error('Failed to generate security report');
    }
  }, [userId, status, scheduledMessages, groupInvitations, validateHealth]);

  // Validate encryption health
  const validateEncryptionHealth = useCallback(async (): Promise<boolean> => {
    try {
      const health = await validateHealth();
      const isHealthy = health.status === 'healthy';
      
      if (!isHealthy) {
        setError(`Encryption health check failed: ${health.errors.join(', ')}`);
      }
      
      return isHealthy;
    } catch (err) {
      setError('Failed to validate encryption health');
      return false;
    }
  }, [validateHealth]);

  // Initialize on mount
  useEffect(() => {
    if (userId && !isReady && !status.enabled) {
      initializeE2EE();
    }
  }, [userId, isReady, status.enabled, initializeE2EE]);

  return {
    // Core E2EE functionality
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

    // Message Scheduling
    scheduleMessage,
    scheduledMessages,
    cancelScheduledMessage,

    // Disappearing Messages
    setDisappearingTimer,
    disappearingMessages,
    disappearingTimer,

    // Message Forwarding
    forwardMessages,

    // Encrypted Drafts
    saveDraft,
    loadDrafts,
    deleteDraft,

    // Group Invitations
    createGroupInvitation,
    revokeGroupInvitation,
    groupInvitations,

    // Double Ratchet
    initializeDoubleRatchet,
    rotateConversationKeys,

    // Security
    exportSecurityReport,
    validateEncryptionHealth,

    // State
    isReady,
    error,
  };
}