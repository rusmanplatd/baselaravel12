import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { Conversation, Message, User, VoiceRecording, ReactionSummary, E2EEStatus } from '@/types/chat';
import { multiDeviceE2EEService } from '@/services/MultiDeviceE2EEService';

interface UseChatReturn {
  conversations: Conversation[];
  activeConversation: Conversation | null;
  messages: Message[];
  loading: boolean;
  error: string | null;
  sendMessage: (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
  }) => Promise<void>;
  loadConversations: () => Promise<void>;
  loadMessages: (conversationId: string) => Promise<void>;
  createConversation: (participants: string[], name?: string) => Promise<void>;
  setActiveConversation: (conversation: Conversation | null) => void;
  encryptionReady: boolean;
  initializeEncryption: () => Promise<void>;
  // Multi-device E2EE
  deviceRegistered: boolean;
  initializeDevice: () => Promise<void>;
  registerDevice: (deviceInfo: any) => Promise<void>;
  deviceSecurityStatus: any;
  e2eeStatus: E2EEStatus;
  // New features
  replyingTo: Message | null;
  setReplyingTo: (message: Message | null) => void;
  typingUsers: Array<{ id: string; name: string }>;
  toggleReaction: (messageId: string, emoji: string) => Promise<void>;
  markAsRead: (messageId: string) => Promise<void>;
  setTyping: (isTyping: boolean) => Promise<void>;
  searchMessages: (query: string) => Promise<Message[]>;
  // Group management functions
  createGroup: (groupData: { name: string; description?: string; participants: string[] }) => Promise<void>;
  updateGroupSettings: (settings: any) => Promise<void>;
  updateParticipantRole: (userId: string, role: 'admin' | 'member') => Promise<void>;
  removeParticipant: (userId: string) => Promise<void>;
  generateInviteLink: (options: { expires_at?: string; max_uses?: number }) => Promise<{ invite_url: string }>;
  joinByInvite: (inviteCode: string) => Promise<void>;
}

export function useChat(user: any): UseChatReturn {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [activeConversation, setActiveConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [encryptionReady, setEncryptionReady] = useState(false);
  const [deviceRegistered, setDeviceRegistered] = useState(false);
  const [deviceSecurityStatus, setDeviceSecurityStatus] = useState(null);
  const [e2eeStatus, setE2eeStatus] = useState<E2EEStatus>({
    enabled: false,
    keyGenerated: false,
    conversationKeysReady: false,
    version: '2.0'
  });
  const [replyingTo, setReplyingTo] = useState<Message | null>(null);
  const [typingUsers, setTypingUsers] = useState<Array<{ id: string; name: string }>>([]);

  const handleError = useCallback((err: any) => {
    console.error('Chat error:', err);
    setError(err.response?.data?.message || err.message || 'An error occurred');
  }, []);

  const initializeEncryption = useCallback(async () => {
    try {
      // Check if device is already registered
      const deviceId = await multiDeviceE2EEService.getDeviceId();
      if (deviceId) {
        setDeviceRegistered(true);
        setEncryptionReady(true);
        setE2eeStatus({
          enabled: true,
          keyGenerated: true,
          conversationKeysReady: true,
          version: '2.0'
        });
        
        // Load security status
        try {
          const securityStatus = await multiDeviceE2EEService.getDeviceSecurityReport();
          setDeviceSecurityStatus(securityStatus);
        } catch (err) {
          console.warn('Could not load device security status:', err);
        }
      } else {
        // Device needs registration
        setDeviceRegistered(false);
        setEncryptionReady(false);
        setE2eeStatus({
          enabled: false,
          keyGenerated: false,
          conversationKeysReady: false,
          version: '2.0'
        });
      }
    } catch (error) {
      console.error('Failed to initialize encryption:', error);
      setError('Failed to initialize encryption');
    }
  }, []);

  const initializeDevice = useCallback(async () => {
    try {
      // This method is for initializing the device from the UI
      // For now, it will trigger the encryption initialization
      await initializeEncryption();
    } catch (error) {
      console.error('Failed to initialize device:', error);
      throw error;
    }
  }, [initializeEncryption]);

  const registerDevice = useCallback(async (deviceInfo: any) => {
    try {
      const result = await multiDeviceE2EEService.registerDevice(deviceInfo);
      if (result.success) {
        setDeviceRegistered(true);
        setEncryptionReady(true);
        setE2eeStatus({
          enabled: true,
          keyGenerated: true,
          conversationKeysReady: true,
          version: '2.0'
        });
      }
      return result;
    } catch (error) {
      console.error('Failed to register device:', error);
      throw error;
    }
  }, []);

  const setupConversationEncryption = useCallback(async (conversationId: string) => {
    try {
      // Use multi-device service to setup conversation encryption
      await multiDeviceE2EEService.setupConversationEncryption(conversationId);
    } catch (error) {
      console.error('Failed to setup conversation encryption:', error);
    }
  }, []);

  const decryptMessages = useCallback(async (rawMessages: any[]) => {
    if (!encryptionReady || !deviceRegistered) return rawMessages;

    const decryptedMessages = await Promise.all(
      rawMessages.map(async (message) => {
        if (message.encrypted_content) {
          try {
            const decryptedContent = await multiDeviceE2EEService.decryptMessage(
              message.encrypted_content,
              message.conversation_id
            );
            
            return {
              ...message,
              content: decryptedContent || '[Decryption failed]',
            };
          } catch (error) {
            console.error('Failed to decrypt message:', error);
            return {
              ...message,
              content: '[Message could not be decrypted]',
            };
          }
        }
        return message;
      })
    );

    return decryptedMessages;
  }, [encryptionReady, deviceRegistered]);

  const loadConversations = useCallback(async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/v1/chat/conversations', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      
      if (!response.ok) throw new Error('Failed to load conversations');
      
      const data = await response.json();
      setConversations(data.data || data);
    } catch (err) {
      handleError(err);
    } finally {
      setLoading(false);
    }
  }, [handleError]);

  const loadMessages = useCallback(async (conversationId: string) => {
    try {
      setLoading(true);
      
      // Setup conversation encryption first
      await setupConversationEncryption(conversationId);
      
      const response = await fetch(`/api/v1/chat/conversations/${conversationId}/messages`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      
      if (!response.ok) throw new Error('Failed to load messages');
      
      const rawMessages = await response.json();
      const decryptedMessages = await decryptMessages(rawMessages.reverse() || []);
      setMessages(decryptedMessages);
    } catch (err) {
      handleError(err);
    } finally {
      setLoading(false);
    }
  }, [handleError, setupConversationEncryption, decryptMessages]);

  const sendMessage = useCallback(async (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
  }) => {
    if (!activeConversation) return;
    
    try {
      let messageData: any = {
        type: options?.type || 'text',
        message_priority: options?.priority || 'normal',
      };

      // Handle reply
      if (options?.replyToId) {
        messageData.reply_to_id = options.replyToId;
      }

      // Handle scheduling
      if (options?.scheduledAt) {
        messageData.scheduled_at = options.scheduledAt.toISOString();
      }

      // Handle voice message
      if (options?.type === 'voice' && options?.voiceData) {
        messageData.voice_duration_seconds = options.voiceData.duration;
        messageData.voice_waveform_data = options.voiceData.waveformData.join(',');
        // In a real app, you'd upload the blob to storage first
        // messageData.voice_file_url = await uploadVoiceFile(options.voiceData.blob);
      }

      // Handle encryption
      if (encryptionReady && deviceRegistered && content) {
        try {
          const encryptedData = await multiDeviceE2EEService.encryptMessage(
            content,
            activeConversation.id
          );
          
          if (encryptedData) {
            messageData = {
              ...messageData,
              encrypted_content: encryptedData.encryptedContent,
              content_hash: encryptedData.contentHash,
            };
          } else {
            messageData.content = content;
          }
        } catch (error) {
          console.error('Failed to encrypt message, sending plaintext:', error);
          messageData.content = content;
        }
      } else if (content) {
        messageData.content = content;
      }

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(messageData),
      });
      
      if (!response.ok) throw new Error('Failed to send message');
      
      const rawMessage = await response.json();
      
      // Decrypt the returned message for display
      const decryptedMessage = encryptionReady && deviceRegistered && rawMessage.encrypted_content
        ? {
            ...rawMessage,
            content: await multiDeviceE2EEService.decryptMessage(
              rawMessage.encrypted_content,
              rawMessage.conversation_id
            ) || content
          }
        : rawMessage;
      
      setMessages(prev => [...prev, decryptedMessage]);
      
      // Update conversation's last message timestamp
      setConversations(prev => 
        prev.map(conv => 
          conv.id === activeConversation.id 
            ? { ...conv, last_message_at: decryptedMessage.created_at }
            : conv
        )
      );

      // Clear reply state
      setReplyingTo(null);
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError, encryptionReady]);

  const createConversation = useCallback(async (participants: string[], name?: string) => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch('/api/v1/chat/conversations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          type: participants.length === 1 ? 'direct' : 'group',
          participants,
          name,
        }),
      });
      
      if (!response.ok) throw new Error('Failed to create conversation');
      
      const newConversation = await response.json();
      setConversations(prev => [newConversation, ...prev]);
      setActiveConversation(newConversation);
    } catch (err) {
      handleError(err);
    }
  }, [handleError]);

  // New feature methods
  const toggleReaction = useCallback(async (messageId: string, emoji: string) => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/messages/${messageId}/reactions/toggle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ emoji }),
      });
      
      if (!response.ok) throw new Error('Failed to toggle reaction');
      
      // In a real app, you'd update the message reactions in state
      // For now, we'll reload messages to see the change
      if (activeConversation) {
        loadMessages(activeConversation.id);
      }
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError, loadMessages]);

  const markAsRead = useCallback(async (messageId: string) => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      await fetch(`/api/v1/chat/messages/${messageId}/read-receipts`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
      });
    } catch (err) {
      handleError(err);
    }
  }, [handleError]);

  const setTyping = useCallback(async (isTyping: boolean) => {
    if (!activeConversation) return;
    
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      
      if (isTyping) {
        await fetch(`/api/v1/chat/conversations/${activeConversation.id}/typing`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken || '',
          },
          credentials: 'same-origin',
        });
      } else {
        await fetch(`/api/v1/chat/conversations/${activeConversation.id}/typing`, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken || '',
          },
          credentials: 'same-origin',
        });
      }
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError]);

  const searchMessages = useCallback(async (query: string): Promise<Message[]> => {
    if (!activeConversation || !query.trim()) return [];
    
    try {
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/messages/search?q=${encodeURIComponent(query)}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      
      if (!response.ok) throw new Error('Failed to search messages');
      
      const results = await response.json();
      return await decryptMessages(results);
    } catch (err) {
      handleError(err);
      return [];
    }
  }, [activeConversation, handleError, decryptMessages]);

  // Group management functions
  const createGroup = useCallback(async (groupData: { name: string; description?: string; participants: string[] }) => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch('/api/v1/chat/conversations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          type: 'group',
          name: groupData.name,
          description: groupData.description,
          participants: groupData.participants,
        }),
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to create group');
      }
      
      const newGroup = await response.json();
      setConversations(prev => [newGroup, ...prev]);
      setActiveConversation(newGroup);
    } catch (err: any) {
      handleError(err);
      throw err; // Re-throw so components can handle the error
    }
  }, [handleError]);

  const updateGroupSettings = useCallback(async (settings: any) => {
    if (!activeConversation) return;
    
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/settings`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(settings),
      });
      
      if (!response.ok) throw new Error('Failed to update group settings');
      
      const updatedConversation = await response.json();
      setActiveConversation(updatedConversation.conversation);
      setConversations(prev => 
        prev.map(conv => 
          conv.id === activeConversation.id ? updatedConversation.conversation : conv
        )
      );
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError]);

  const updateParticipantRole = useCallback(async (userId: string, role: 'admin' | 'member') => {
    if (!activeConversation) return;
    
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/participants/role`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          user_id: userId,
          role: role,
        }),
      });
      
      if (!response.ok) throw new Error('Failed to update participant role');
      
      // Update the conversation participants in state
      setActiveConversation(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          participants: prev.participants?.map(p => 
            p.user_id === userId ? { ...p, role } : p
          ) || []
        };
      });
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError]);

  const removeParticipant = useCallback(async (userId: string) => {
    if (!activeConversation) return;
    
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/participants`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          user_id: userId,
        }),
      });
      
      if (!response.ok) throw new Error('Failed to remove participant');
      
      // Update the conversation participants in state
      setActiveConversation(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          participants: prev.participants?.filter(p => p.user_id !== userId) || []
        };
      });
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError]);

  const generateInviteLink = useCallback(async (options: { expires_at?: string; max_uses?: number }) => {
    if (!activeConversation) {
      throw new Error('No active conversation');
    }
    
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/invite-link`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(options),
      });
      
      if (!response.ok) throw new Error('Failed to generate invite link');
      
      return await response.json();
    } catch (err) {
      handleError(err);
      throw err;
    }
  }, [activeConversation, handleError]);

  const joinByInvite = useCallback(async (inviteCode: string) => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/join/${inviteCode}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          invite_code: inviteCode,
        }),
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Failed to join group');
      }
      
      const result = await response.json();
      const conversation = result.conversation;
      
      // Add to conversations list and set as active
      setConversations(prev => {
        const exists = prev.some(conv => conv.id === conversation.id);
        return exists ? prev : [conversation, ...prev];
      });
      setActiveConversation(conversation);
    } catch (err: any) {
      handleError(err);
      throw err;
    }
  }, [handleError]);

  // Poll for typing users
  useEffect(() => {
    if (!activeConversation) return;
    
    const pollTypingUsers = async () => {
      try {
        const response = await fetch(`/api/v1/chat/conversations/${activeConversation.id}/typing`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });
        
        if (response.ok) {
          const data = await response.json();
          setTypingUsers(data.typing_users || []);
        }
      } catch (err) {
        // Silent fail for typing status
      }
    };
    
    const interval = setInterval(pollTypingUsers, 2000);
    return () => clearInterval(interval);
  }, [activeConversation]);

  useEffect(() => {
    initializeEncryption();
  }, [initializeEncryption]);

  useEffect(() => {
    loadConversations();
  }, [loadConversations]);

  useEffect(() => {
    if (activeConversation) {
      loadMessages(activeConversation.id);
    }
  }, [activeConversation, loadMessages]);

  return {
    conversations,
    activeConversation,
    messages,
    loading,
    error,
    sendMessage,
    loadConversations,
    loadMessages,
    createConversation,
    setActiveConversation,
    encryptionReady,
    initializeEncryption,
    // Multi-device E2EE
    deviceRegistered,
    initializeDevice,
    registerDevice,
    deviceSecurityStatus,
    e2eeStatus,
    // New features
    replyingTo,
    setReplyingTo,
    typingUsers,
    toggleReaction,
    markAsRead,
    setTyping,
    searchMessages,
    // Group management
    createGroup,
    updateGroupSettings,
    updateParticipantRole,
    removeParticipant,
    generateInviteLink,
    joinByInvite,
  };
}