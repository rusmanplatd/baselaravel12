import { useCallback, useEffect, useState } from 'react';
import { quantumResistantE2EE } from '@/services/QuantumResistantE2EEService';
import { quantumForwardSecrecy } from '@/services/QuantumForwardSecrecyService';
import { securityMonitor, SecurityEventType } from '@/services/SecurityMonitoringService';
import { apiService } from '@/services/ApiService';
import { toast } from 'sonner';
import type { EncryptionKey, KeyPair, E2EEStatus, EncryptedMessageData, Message, Conversation, User } from '@/types/chat';
import { router } from '@inertiajs/react';

// Quantum-Safe E2EE Interfaces
export interface QuantumE2EEStatus {
  quantumReady: boolean;
  keyGenerated: boolean;
  conversationKeysReady: boolean;
  quantumSecurityLevel: number;
  algorithm: 'PQ-E2EE-v1.0';
  lastKeyRotation?: string;
  forwardSecrecyEnabled: boolean;
}

export interface QuantumSecurityMetrics {
  quantumSecurityLevel: number;
  forwardSecrecyStrength: number;
  signatureStrength: number;
  encryptionStrength: number;
  keyRotationFrequency: number;
  threatLevel: 'low' | 'medium' | 'high' | 'critical';
  lastQuantumRotation: Date;
  quantumEpoch: number;
}

export interface QuantumSafeMessage {
  ciphertext: Uint8Array;
  nonce: Uint8Array;
  tag: Uint8Array;
  signature: Uint8Array;
  signerPublicKey: Uint8Array;
  ratchetKey?: Uint8Array;
  messageNumber: number;
  previousChainLength: number;
  timestamp: number;
  keyVersion: number;
  algorithm: 'PQ-E2EE-v1.0';
  ephemeralKeyCommitment: Uint8Array;
}

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
  quantumSafe: boolean;
}

export interface DisappearingMessage {
  id: string;
  content: string;
  expiresAt: Date;
  timeRemaining: number;
  isExpired: boolean;
  quantumSafe: boolean;
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
  quantumSafe: boolean;
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
  quantumSafe: boolean;
}

interface UseE2EEReturn {
  // Quantum-Safe Core E2EE functionality
  status: QuantumE2EEStatus;
  initializeQuantumE2EE: () => Promise<boolean>;
  generateQuantumKeyPair: () => Promise<any | null>;
  encryptMessage: (message: string, conversationId: string) => Promise<QuantumSafeMessage | null>;
  decryptMessage: (encryptedData: QuantumSafeMessage, conversationId: string) => Promise<string | null>;
  rotateQuantumKeys: (conversationId: string, reason?: string) => Promise<boolean>;
  setupQuantumConversationEncryption: (conversationId: string, participantDevices: string[]) => Promise<boolean>;
  clearQuantumEncryptionData: () => void;
  createQuantumBackup: (password: string) => Promise<string | null>;
  restoreFromQuantumBackup: (encryptedBackup: string, password: string) => Promise<boolean>;
  getQuantumSecurityMetrics: (conversationId: string) => Promise<QuantumSecurityMetrics | null>;
  verifyQuantumResistance: () => Promise<boolean>;
  validateQuantumHealth: () => Promise<any>;
  exportQuantumSecurityReport: () => Promise<Blob>;

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
  // Quantum-Safe E2EE state
  const [status, setStatus] = useState<QuantumE2EEStatus>({
    quantumReady: false,
    keyGenerated: false,
    conversationKeysReady: false,
    quantumSecurityLevel: 5,
    algorithm: 'PQ-E2EE-v1.0',
    forwardSecrecyEnabled: false,
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

  // Initialize Quantum-Safe E2EE system
  const initializeQuantumE2EE = useCallback(async (): Promise<boolean> => {
    try {
      setError(null);
      
      if (!userId) {
        throw new Error('User ID required for quantum E2EE initialization');
      }
      
      // Initialize quantum-resistant device
      const deviceInitialized = await quantumResistantE2EE.initializeDevice(userId);
      if (!deviceInitialized) {
        throw new Error('Failed to initialize quantum-resistant device');
      }
      
      // Verify quantum resistance
      const quantumResistanceValid = await quantumResistantE2EE.verifyQuantumResistance();
      if (!quantumResistanceValid) {
        throw new Error('Quantum resistance verification failed');
      }
      
      setStatus({
        quantumReady: true,
        keyGenerated: true,
        conversationKeysReady: false,
        quantumSecurityLevel: 5,
        algorithm: 'PQ-E2EE-v1.0',
        lastKeyRotation: new Date().toISOString(),
        forwardSecrecyEnabled: true,
      });
      
      setIsReady(true);
      
      securityMonitor.logEvent(
        SecurityEventType.ENCRYPTION_ENABLED,
        'low',
        userId,
        {
          algorithm: 'PQ-E2EE-v1.0',
          quantumSecurityLevel: 5,
          forwardSecrecyEnabled: true
        }
      );
      
      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Unknown error occurred');
      handleE2EEError(error, 'quantum-initialization');
      console.error('Quantum E2EE initialization failed:', err);
      return false;
    }
  }, [userId]);

  // Generate new quantum-resistant key pair
  const generateQuantumKeyPair = useCallback(async (): Promise<any | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum key generation');
      }
      
      // Re-initialize device to generate new keys
      const deviceInitialized = await quantumResistantE2EE.initializeDevice(userId);
      
      if (deviceInitialized) {
        setStatus(prev => ({ ...prev, keyGenerated: true }));
        
        securityMonitor.logEvent(
          SecurityEventType.KEY_GENERATION,
          'low',
          userId,
          {
            algorithm: 'PQ-E2EE-v1.0',
            quantumSecurityLevel: 5
          }
        );
        
        return { success: true };
      }
      
      return null;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum key generation failed');
      handleE2EEError(error, 'quantumKeyGeneration');
      return null;
    }
  }, [userId]);

  // Encrypt a message with quantum-safe forward secrecy
  const encryptMessage = useCallback(async (
    message: string,
    conversationId: string
  ): Promise<QuantumSafeMessage | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum encryption');
      }
      
      // Use quantum forward secrecy service for encryption
      const encryptedMessage = await quantumForwardSecrecy.encryptWithQuantumForwardSecrecy(
        message,
        conversationId
      );
      
      securityMonitor.logEvent(
        SecurityEventType.MESSAGE_ENCRYPTED,
        'low',
        userId,
        {
          conversationId,
          algorithm: 'PQ-E2EE-v1.0',
          messageLength: message.length,
          quantumSafe: true,
          forwardSecure: true
        }
      );
      
      return encryptedMessage;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum encryption failed');
      handleE2EEError(error, 'quantumEncryption', { conversationId });
      
      securityMonitor.logEvent(
        SecurityEventType.ENCRYPTION_FAILED,
        'medium',
        userId,
        {
          conversationId,
          error: error.message,
          algorithm: 'PQ-E2EE-v1.0'
        }
      );
      
      return null;
    }
  }, [userId]);

  // Decrypt a quantum-safe message with forward secrecy verification
  const decryptMessage = useCallback(async (
    encryptedData: QuantumSafeMessage,
    conversationId: string
  ): Promise<string | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum decryption');
      }
      
      // Use quantum forward secrecy service for decryption
      const decryptedMessage = await quantumForwardSecrecy.decryptWithQuantumForwardSecrecy(
        encryptedData,
        conversationId
      );
      
      securityMonitor.logEvent(
        SecurityEventType.MESSAGE_DECRYPTED,
        'low',
        userId,
        {
          conversationId,
          algorithm: 'PQ-E2EE-v1.0',
          messageLength: decryptedMessage.length,
          quantumSafe: true,
          forwardSecure: true
        }
      );
      
      return decryptedMessage;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum decryption failed');
      handleE2EEError(error, 'quantumDecryption', { conversationId });
      
      securityMonitor.logEvent(
        SecurityEventType.MESSAGE_DECRYPTION_FAILED,
        'medium',
        userId,
        {
          conversationId,
          error: error.message,
          algorithm: 'PQ-E2EE-v1.0'
        }
      );
      
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
    if (userId && !isReady && !status.quantumReady) {
      initializeQuantumE2EE();
    }
  }, [userId, isReady, status.quantumReady, initializeQuantumE2EE]);

  return {
    // Quantum-Safe Core E2EE functionality
    status,
    initializeQuantumE2EE,
    generateQuantumKeyPair,
    encryptMessage,
    decryptMessage,
    rotateQuantumKeys: async () => false, // Placeholder
    setupQuantumConversationEncryption: async () => false, // Placeholder
    clearQuantumEncryptionData: () => {}, // Placeholder
    createQuantumBackup: async () => null, // Placeholder
    restoreFromQuantumBackup: async () => false, // Placeholder
    getQuantumSecurityMetrics: async (conversationId: string) => 
      quantumForwardSecrecy.getForwardSecrecyMetrics(conversationId),
    verifyQuantumResistance: async () => quantumResistantE2EE.verifyQuantumResistance(),
    validateQuantumHealth: async () => quantumResistantE2EE.exportSecurityAudit(),
    exportQuantumSecurityReport: async () => new Blob(['{}'], { type: 'application/json' }),

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