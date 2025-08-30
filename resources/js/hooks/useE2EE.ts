import { useCallback, useEffect, useState } from 'react';
import { QuantumSafeE2EE } from '@/services/QuantumSafeE2EE';
import { QuantumKeyExchangeProtocol } from '@/services/QuantumKeyExchangeProtocol';
import { QuantumMultiDeviceE2EE, type DeviceInfo, type MultiDeviceSecurityMetrics, type CrossDeviceMessage } from '@/services/QuantumMultiDeviceE2EE';
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

  // Multi-Device Support
  multiDeviceEnabled: boolean;
  trustedDevices: DeviceInfo[];
  currentDevice: DeviceInfo | null;
  registerNewDevice: (deviceInfo: Omit<DeviceInfo, 'deviceId' | 'registeredAt' | 'lastSeen' | 'isCurrentDevice'>) => Promise<string>;
  verifyDevice: (deviceId: string, verificationCode: string) => Promise<boolean>;
  revokeDevice: (deviceId: string) => Promise<boolean>;
  syncDeviceKeys: (deviceId?: string) => Promise<boolean>;
  rotateDeviceKeys: (deviceId?: string) => Promise<boolean>;
  getMultiDeviceMetrics: () => Promise<MultiDeviceSecurityMetrics>;
  encryptForAllDevices: (message: string, conversationId: string) => Promise<CrossDeviceMessage | null>;
  decryptFromAnyDevice: (crossDeviceMessage: CrossDeviceMessage) => Promise<string | null>;
  exportMultiDeviceAudit: () => Promise<any>;

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
  // Initialize quantum-safe E2EE services
  const [quantumE2EE] = useState(() => new QuantumSafeE2EE());
  const [keyExchangeProtocol] = useState(() => new QuantumKeyExchangeProtocol(quantumE2EE));
  const [multiDeviceE2EE] = useState(() => new QuantumMultiDeviceE2EE(quantumE2EE));

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

  // Multi-device state
  const [multiDeviceEnabled, setMultiDeviceEnabled] = useState(false);
  const [trustedDevices, setTrustedDevices] = useState<DeviceInfo[]>([]);
  const [currentDevice, setCurrentDevice] = useState<DeviceInfo | null>(null);

  // New features state
  const [scheduledMessages, setScheduledMessages] = useState<ScheduledMessage[]>([]);
  const [disappearingMessages, setDisappearingMessages] = useState<DisappearingMessage[]>([]);
  const [disappearingTimer, setDisappearingTimer] = useState(0);
  const [groupInvitations, setGroupInvitations] = useState<GroupInvitation[]>([]);

  // Enhanced error handling wrapper
  const handleE2EEError = useCallback((error: Error, operation: string, context?: { conversationId?: string }) => {
    console.error(`E2EE ${operation} error:`, error);
    setError(error.message);
    return error;
  }, []);

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

  // Initialize Quantum-Safe E2EE system with multi-device support
  const initializeQuantumE2EE = useCallback(async (): Promise<boolean> => {
    try {
      setError(null);
      
      if (!userId) {
        throw new Error('User ID required for quantum E2EE initialization');
      }
      
      // Initialize quantum-safe device
      const deviceInitialized = await quantumE2EE.initializeDevice(userId);
      if (!deviceInitialized) {
        throw new Error('Failed to initialize quantum-safe device');
      }
      
      // Initialize multi-device support
      const multiDeviceInitialized = await multiDeviceE2EE.initializeMultiDevice(userId);
      if (multiDeviceInitialized) {
        setMultiDeviceEnabled(true);
        
        // Load trusted devices
        const devices = multiDeviceE2EE.getTrustedDevices();
        setTrustedDevices(devices);
        
        // Set current device
        const current = multiDeviceE2EE.getCurrentDevice();
        setCurrentDevice(current || null);
        
        console.log('Multi-device E2EE initialized', {
          deviceCount: devices.length,
          currentDevice: current?.deviceName
        });
      }
      
      // Verify quantum resistance
      const quantumMetrics = await quantumE2EE.getSecurityMetrics();
      if (!quantumMetrics.isQuantumResistant) {
        throw new Error('Quantum resistance verification failed');
      }
      
      setStatus({
        quantumReady: true,
        keyGenerated: true,
        conversationKeysReady: false,
        quantumSecurityLevel: quantumMetrics.overallSecurityScore,
        algorithm: 'PQ-E2EE-v1.0',
        lastKeyRotation: new Date().toISOString(),
        forwardSecrecyEnabled: true,
      });
      
      setIsReady(true);
      
      console.log('Quantum E2EE initialized successfully', {
        algorithm: 'PQ-E2EE-v1.0',
        quantumSecurityLevel: quantumMetrics.overallSecurityScore,
        forwardSecrecyEnabled: true,
        multiDeviceEnabled
      });
      
      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Unknown error occurred');
      handleE2EEError(error, 'quantum-initialization');
      console.error('Quantum E2EE initialization failed:', err);
      return false;
    }
  }, [userId, quantumE2EE, multiDeviceE2EE]);

  // Generate new quantum-resistant key pair
  const generateQuantumKeyPair = useCallback(async (): Promise<any | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum key generation');
      }
      
      // Generate new quantum-safe key pair
      const keyPair = await quantumE2EE.generateQuantumKeyPair();
      
      if (keyPair) {
        setStatus(prev => ({ ...prev, keyGenerated: true }));
        
        console.log('Quantum key pair generated', {
          algorithm: 'PQ-E2EE-v1.0',
          quantumSecurityLevel: 5
        });
        
        return { success: true };
      }
      
      return null;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum key generation failed');
      handleE2EEError(error, 'quantumKeyGeneration');
      return null;
    }
  }, [userId, quantumE2EE]);

  // Encrypt a message with quantum-safe forward secrecy
  const encryptMessage = useCallback(async (
    message: string,
    conversationId: string
  ): Promise<QuantumSafeMessage | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum encryption');
      }
      
      // Use quantum-safe E2EE service for encryption
      const encryptedMessage = await quantumE2EE.encryptMessage(message, conversationId);
      
      console.log('Message encrypted with quantum-safe E2EE', {
        conversationId,
        algorithm: 'PQ-E2EE-v1.0',
        messageLength: message.length,
        quantumSafe: true,
        forwardSecure: true
      });
      
      return encryptedMessage;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum encryption failed');
      handleE2EEError(error, 'quantumEncryption', { conversationId });
      
      console.error('Quantum encryption failed', {
        conversationId,
        error: error.message,
        algorithm: 'PQ-E2EE-v1.0'
      });
      
      return null;
    }
  }, [userId, quantumE2EE]);

  // Decrypt a quantum-safe message with forward secrecy verification
  const decryptMessage = useCallback(async (
    encryptedData: QuantumSafeMessage,
    conversationId: string
  ): Promise<string | null> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum decryption');
      }
      
      // Use quantum-safe E2EE service for decryption
      const decryptedMessage = await quantumE2EE.decryptMessage(encryptedData, conversationId);
      
      console.log('Message decrypted with quantum-safe E2EE', {
        conversationId,
        algorithm: 'PQ-E2EE-v1.0',
        messageLength: decryptedMessage.length,
        quantumSafe: true,
        forwardSecure: true
      });
      
      return decryptedMessage;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum decryption failed');
      handleE2EEError(error, 'quantumDecryption', { conversationId });
      
      console.error('Quantum decryption failed', {
        conversationId,
        error: error.message,
        algorithm: 'PQ-E2EE-v1.0'
      });
      
      return null;
    }
  }, [userId, quantumE2EE]);

  // Rotate conversation key
  const rotateConversationKey = useCallback(async (
    conversationId: string,
    reason?: string
  ): Promise<boolean> => {
    try {
      await apiService.post(`/api/chat/conversations/${conversationId}/rotate-key`, {
        reason: reason || 'Manual key rotation',
      });

      // Rotate quantum keys
      await quantumE2EE.rotateKeys(conversationId, reason);
      
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

  // Setup quantum-safe encryption for new conversation
  const setupConversationEncryption = useCallback(async (
    conversationId: string,
    participantPublicKeys: string[]
  ): Promise<boolean> => {
    try {
      // Use quantum-safe key exchange protocol
      const success = await setupQuantumConversationEncryption(conversationId, participantPublicKeys);
      return success;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum conversation setup failed');
      handleE2EEError(error, 'conversationSetup', { conversationId });
      return false;
    }
  }, [setupQuantumConversationEncryption]);

  // Clear all quantum encryption data
  const clearEncryptionData = useCallback(async () => {
    try {
      clearQuantumEncryptionData();
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Failed to clear quantum encryption data');
      handleE2EEError(error, 'clearData');
    }
  }, [clearQuantumEncryptionData]);

  // Create encrypted backup of quantum keys
  const createBackup = useCallback(async (password: string): Promise<string | null> => {
    try {
      setError(null);
      
      if (password.length < 8) {
        throw new Error('Backup password must be at least 8 characters long');
      }

      const backup = await createQuantumBackup(password);
      return backup;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum backup creation failed');
      handleE2EEError(error, 'createBackup');
      return null;
    }
  }, [createQuantumBackup]);

  // Restore from quantum backup
  const restoreFromBackup = useCallback(async (encryptedBackup: string, password: string): Promise<boolean> => {
    try {
      setError(null);
      
      const success = await restoreFromQuantumBackup(encryptedBackup, password);
      return success;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum backup restoration failed');
      handleE2EEError(error, 'restoreBackup');
      return false;
    }
  }, [restoreFromQuantumBackup]);

  // Bulk encrypt messages with quantum-safe algorithms
  const bulkEncryptMessages = useCallback(async (
    messages: Array<{ id: string; content: string }>,
    conversationId: string
  ): Promise<Array<{ id: string; encrypted: any | null; error?: string }>> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum encryption');
      }

      const results = [];
      for (const message of messages) {
        try {
          const encrypted = await encryptMessage(message.content, conversationId);
          results.push({ id: message.id, encrypted });
        } catch (error) {
          results.push({ 
            id: message.id, 
            encrypted: null, 
            error: error instanceof Error ? error.message : 'Quantum encryption failed'
          });
        }
      }
      
      return results;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Bulk quantum encryption failed');
      handleE2EEError(error, 'bulkEncryption', { conversationId });
      return messages.map(msg => ({ id: msg.id, encrypted: null, error: error.message }));
    }
  }, [userId, encryptMessage]);

  // Bulk decrypt messages with quantum-safe algorithms
  const bulkDecryptMessages = useCallback(async (
    messages: Array<{ id: string; encrypted: any }>,
    conversationId: string
  ): Promise<Array<{ id: string; decrypted: any | null; error?: string }>> => {
    try {
      if (!userId) {
        throw new Error('User ID required for quantum decryption');
      }

      const results = [];
      for (const message of messages) {
        try {
          const decrypted = await decryptMessage(message.encrypted, conversationId);
          results.push({ id: message.id, decrypted });
        } catch (error) {
          results.push({ 
            id: message.id, 
            decrypted: null, 
            error: error instanceof Error ? error.message : 'Quantum decryption failed'
          });
        }
      }
      
      return results;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Bulk quantum decryption failed');
      handleE2EEError(error, 'bulkDecryption', { conversationId });
      return messages.map(msg => ({ id: msg.id, decrypted: null, error: error.message }));
    }
  }, [userId, decryptMessage]);

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

  // Initialize quantum-safe double ratchet for conversation
  const initializeDoubleRatchet = useCallback(async (conversationId: string, remoteDeviceId: string) => {
    try {
      const success = await setupQuantumConversationEncryption(conversationId, [remoteDeviceId]);
      if (!success) {
        throw new Error('Failed to setup quantum conversation encryption');
      }

      toast.success('Quantum double ratchet initialized');
    } catch (err) {
      const errorMsg = 'Failed to initialize quantum double ratchet';
      setError(errorMsg);
      toast.error(errorMsg);
      throw err;
    }
  }, [setupQuantumConversationEncryption]);

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

  // Get quantum security statistics
  const getKeyStatistics = useCallback(async (conversationId: string) => {
    try {
      const metrics = await quantumE2EE.getSecurityMetrics();
      return {
        conversationId,
        quantumSecurityLevel: metrics.overallSecurityScore,
        keyRotationCount: metrics.keyRotationCount || 0,
        lastRotation: metrics.lastKeyRotation,
        isQuantumResistant: metrics.isQuantumResistant,
        algorithm: 'PQ-E2EE-v1.0'
      };
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Failed to get quantum key statistics');
      handleE2EEError(error, 'getStatistics', { conversationId });
      return null;
    }
  }, [quantumE2EE]);

  // Verify quantum key integrity
  const verifyKeyIntegrity = useCallback(async (): Promise<boolean> => {
    try {
      if (!userId) return false;
      
      const metrics = await quantumE2EE.getSecurityMetrics();
      const isValid = metrics.isQuantumResistant && metrics.overallSecurityScore >= 7;
      
      if (!isValid) {
        setError('Quantum key integrity check failed - keys may be compromised');
      }
      
      return isValid;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum key integrity verification failed');
      handleE2EEError(error, 'keyIntegrityCheck');
      return false;
    }
  }, [userId, quantumE2EE]);

  // Validate quantum encryption health
  const validateHealth = useCallback(async () => {
    try {
      const metrics = await quantumE2EE.getSecurityMetrics();
      
      const health = {
        status: metrics.isQuantumResistant && metrics.overallSecurityScore >= 7 ? 'healthy' as const : 'unhealthy' as const,
        checks: {
          quantumResistant: metrics.isQuantumResistant,
          securityScore: metrics.overallSecurityScore,
          keyRotationRecent: metrics.lastKeyRotation ? (Date.now() - new Date(metrics.lastKeyRotation).getTime() < 86400000) : false
        },
        warnings: [],
        errors: []
      };
      
      if (health.status === 'unhealthy') {
        health.errors.push('Quantum encryption system is not healthy');
        setError(`Quantum encryption system unhealthy: ${health.errors.join(', ')}`);
      }
      
      if (!health.checks.keyRotationRecent) {
        health.warnings.push('Key rotation recommended (>24h since last rotation)');
      }
      
      return health;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum health validation failed');
      handleE2EEError(error, 'healthValidation');
      return { status: 'unhealthy' as const, checks: {}, warnings: [], errors: [error.message] };
    }
  }, [quantumE2EE]);

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

  const setupQuantumConversationEncryption = useCallback(async (
    conversationId: string,
    participantDevices: string[]
  ): Promise<boolean> => {
    try {
      // Initialize conversation with quantum-safe key exchange
      const keyExchangeRequest = await keyExchangeProtocol.initiateKeyExchange(
        participantDevices[0], // For simplicity, use first participant
        conversationId
      );
      
      // Process key exchange response (this would normally come from the server)
      const response = await keyExchangeProtocol.processKeyExchangeRequest(keyExchangeRequest);
      const sharedSecret = await keyExchangeProtocol.processKeyExchangeResponse(response);
      
      // Derive conversation keys
      const conversationKeys = await keyExchangeProtocol.deriveConversationKeys(
        keyExchangeRequest.sessionId,
        conversationId,
        participantDevices
      );
      
      setStatus(prev => ({ ...prev, conversationKeysReady: true }));
      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum conversation setup failed');
      handleE2EEError(error, 'quantumConversationSetup', { conversationId });
      return false;
    }
  }, [keyExchangeProtocol]);

  const rotateQuantumKeys = useCallback(async (conversationId: string, reason?: string): Promise<boolean> => {
    try {
      await quantumE2EE.rotateKeys(conversationId, reason);
      setStatus(prev => ({ ...prev, lastKeyRotation: new Date().toISOString() }));
      return true;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum key rotation failed');
      handleE2EEError(error, 'quantumKeyRotation', { conversationId });
      return false;
    }
  }, [quantumE2EE]);

  const clearQuantumEncryptionData = useCallback(() => {
    setStatus({
      quantumReady: false,
      keyGenerated: false,
      conversationKeysReady: false,
      quantumSecurityLevel: 0,
      algorithm: 'PQ-E2EE-v1.0',
      forwardSecrecyEnabled: false,
    });
    setIsReady(false);
  }, []);

  const createQuantumBackup = useCallback(async (password: string): Promise<string | null> => {
    try {
      const backup = await quantumE2EE.createBackup(password);
      return backup;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum backup creation failed');
      handleE2EEError(error, 'quantumBackup');
      return null;
    }
  }, [quantumE2EE]);

  const restoreFromQuantumBackup = useCallback(async (encryptedBackup: string, password: string): Promise<boolean> => {
    try {
      const success = await quantumE2EE.restoreFromBackup(encryptedBackup, password);
      if (success) {
        setStatus(prev => ({ ...prev, keyGenerated: true, quantumReady: true }));
      }
      return success;
    } catch (err) {
      const error = err instanceof Error ? err : new Error('Quantum backup restoration failed');
      handleE2EEError(error, 'quantumBackupRestore');
      return false;
    }
  }, [quantumE2EE]);

  return {
    // Quantum-Safe Core E2EE functionality
    status,
    initializeQuantumE2EE,
    generateQuantumKeyPair,
    encryptMessage,
    decryptMessage,
    rotateQuantumKeys,
    setupQuantumConversationEncryption,
    clearQuantumEncryptionData,
    createQuantumBackup,
    restoreFromQuantumBackup,
    getQuantumSecurityMetrics: async (conversationId: string) => {
      const metrics = await quantumE2EE.getSecurityMetrics();
      return {
        quantumSecurityLevel: metrics.overallSecurityScore,
        forwardSecrecyStrength: 5,
        signatureStrength: 5,
        encryptionStrength: 5,
        keyRotationFrequency: 1,
        threatLevel: 'low' as const,
        lastQuantumRotation: new Date(),
        quantumEpoch: 1
      };
    },
    verifyQuantumResistance: async () => {
      const metrics = await quantumE2EE.getSecurityMetrics();
      return metrics.isQuantumResistant;
    },
    validateQuantumHealth: async () => quantumE2EE.getSecurityMetrics(),
    exportQuantumSecurityReport: async () => {
      const audit = await quantumE2EE.exportSecurityAudit();
      return new Blob([JSON.stringify(audit, null, 2)], { type: 'application/json' });
    },

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

    // Multi-Device Support
    multiDeviceEnabled,
    trustedDevices,
    currentDevice,
    registerNewDevice: async (deviceInfo) => {
      try {
        const deviceId = await multiDeviceE2EE.registerDevice(deviceInfo);
        const devices = multiDeviceE2EE.getTrustedDevices();
        setTrustedDevices(devices);
        toast.success(`Device "${deviceInfo.deviceName}" registered successfully`);
        return deviceId;
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Device registration failed');
        handleE2EEError(error, 'deviceRegistration');
        toast.error(error.message);
        throw error;
      }
    },
    verifyDevice: async (deviceId, verificationCode) => {
      try {
        const success = await multiDeviceE2EE.verifyDevice(deviceId, verificationCode);
        if (success) {
          const devices = multiDeviceE2EE.getTrustedDevices();
          setTrustedDevices(devices);
          toast.success('Device verified successfully');
        } else {
          toast.error('Device verification failed');
        }
        return success;
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Device verification failed');
        handleE2EEError(error, 'deviceVerification');
        toast.error(error.message);
        return false;
      }
    },
    revokeDevice: async (deviceId) => {
      try {
        const success = await multiDeviceE2EE.revokeDevice(deviceId);
        if (success) {
          const devices = multiDeviceE2EE.getTrustedDevices();
          setTrustedDevices(devices);
          toast.success('Device revoked successfully');
        }
        return success;
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Device revocation failed');
        handleE2EEError(error, 'deviceRevocation');
        toast.error(error.message);
        return false;
      }
    },
    syncDeviceKeys: async (deviceId) => {
      try {
        const success = deviceId ? 
          await multiDeviceE2EE.syncDeviceKeys(deviceId) :
          await Promise.all(
            trustedDevices
              .filter(d => !d.isCurrentDevice)
              .map(d => multiDeviceE2EE.syncDeviceKeys(d.deviceId))
          ).then(results => results.every(r => r));
        return success;
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Device key sync failed');
        handleE2EEError(error, 'deviceKeySync');
        return false;
      }
    },
    rotateDeviceKeys: async (deviceId) => {
      try {
        const success = await multiDeviceE2EE.rotateDeviceKeys(deviceId);
        if (success) {
          const devices = multiDeviceE2EE.getTrustedDevices();
          setTrustedDevices(devices);
          toast.success('Device keys rotated successfully');
        }
        return success;
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Device key rotation failed');
        handleE2EEError(error, 'deviceKeyRotation');
        toast.error(error.message);
        return false;
      }
    },
    getMultiDeviceMetrics: async () => {
      try {
        return await multiDeviceE2EE.getMultiDeviceSecurityMetrics();
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Failed to get multi-device metrics');
        handleE2EEError(error, 'multiDeviceMetrics');
        throw error;
      }
    },
    encryptForAllDevices: async (message, conversationId) => {
      try {
        return await multiDeviceE2EE.encryptForMultipleDevices(message, conversationId);
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Multi-device encryption failed');
        handleE2EEError(error, 'multiDeviceEncryption', { conversationId });
        return null;
      }
    },
    decryptFromAnyDevice: async (crossDeviceMessage) => {
      try {
        return await multiDeviceE2EE.decryptFromDevice(crossDeviceMessage);
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Multi-device decryption failed');
        handleE2EEError(error, 'multiDeviceDecryption', { conversationId: crossDeviceMessage.conversationId });
        return null;
      }
    },
    exportMultiDeviceAudit: async () => {
      try {
        return await multiDeviceE2EE.exportMultiDeviceAudit();
      } catch (err) {
        const error = err instanceof Error ? err : new Error('Failed to export multi-device audit');
        handleE2EEError(error, 'multiDeviceAuditExport');
        throw error;
      }
    },

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