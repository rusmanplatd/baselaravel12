import { useState, useEffect, useCallback } from 'react';
import { Conversation, Message, VoiceRecording, E2EEStatus } from '@/types/chat';
import { multiDeviceE2EEService, SecurityReport } from '@/services/MultiDeviceE2EEService';
import { apiService } from '@/services/ApiService';

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
    mentions?: any[];
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
  const [deviceSecurityStatus, setDeviceSecurityStatus] = useState<SecurityReport | null>(null);
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

    // Handle specific encryption key regeneration error
    if (err.response?.data?.code === 'ENCRYPTION_KEYS_REGENERATED') {
      setError('Encryption keys were regenerated. Please refresh the page to continue.');
      // Optionally trigger a page reload or re-initialization
      setTimeout(() => {
        window.location.reload();
      }, 3000);
      return;
    }

    // Handle other encryption errors
    if (err.response?.data?.code === 'ENCRYPTION_KEY_CORRUPTED') {
      setError('Encryption keys are corrupted. Please contact support.');
      return;
    }

    setError(err.response?.data?.message || err.message || 'An error occurred');
  }, []);

  // Remove the old token generation logic since we're using ApiService
  // const generateAccessToken = useCallback(async () => {
  //   // This is now handled by ApiService
  // }, []);


  const initializeEncryption = useCallback(async () => {
    try {
      // Check if device is already registered
      const deviceId = await multiDeviceE2EEService.getDeviceId();
      if (deviceId) {
        // Try to register device if not already registered
        try {
          const result = await multiDeviceE2EEService.registerDevice();
          if (result.device) {
            console.log('Device registered successfully:', result.device);
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
            return;
          }
        } catch (registrationError: any) {
          // If device is already registered, that's fine
          if (registrationError.message?.includes('already registered') ||
              registrationError.status === 409) {
            console.log('Device already registered');
            setDeviceRegistered(true);
            setEncryptionReady(true);
            setE2eeStatus({
              enabled: true,
              keyGenerated: true,
              conversationKeysReady: true,
              version: '2.0'
            });
            return;
          }
          console.warn('Device registration failed:', registrationError);
        }
      }

      // Device needs initialization/registration
      setDeviceRegistered(false);
      setEncryptionReady(false);
      setE2eeStatus({
        enabled: false,
        keyGenerated: false,
        conversationKeysReady: false,
        version: '2.0'
      });
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
      const result = await multiDeviceE2EEService.registerDevice();
      if (result.device) {
        console.log('Device registered successfully:', result.device);
        setDeviceRegistered(true);
        setEncryptionReady(true);
        setE2eeStatus({
          enabled: true,
          keyGenerated: true,
          conversationKeysReady: true,
          version: '2.0'
        });
      }
    } catch (error: any) {
      // If device is already registered, that's fine
      if (error.message?.includes('already registered') || error.status === 409) {
        console.log('Device already registered');
        setDeviceRegistered(true);
        setEncryptionReady(true);
        setE2eeStatus({
          enabled: true,
          keyGenerated: true,
          conversationKeysReady: true,
          version: '2.0'
        });
        return;
      }
      console.error('Failed to register device:', error);
      throw error;
    }
  }, []);

  const setupConversationEncryption = useCallback(async (conversationId: string) => {
    try {
      // Use multi-device service to setup conversation encryption
      await multiDeviceE2EEService.setupConversationEncryption(conversationId, []);
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
            // Parse encrypted content if it's a string
            const encryptedMessage = typeof message.encrypted_content === 'string'
              ? JSON.parse(message.encrypted_content)
              : message.encrypted_content;

            const decryptedContent = await multiDeviceE2EEService.decryptMessage(
              message.conversation_id,
              encryptedMessage
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
      setError(null);

      const data = await apiService.get<{ data?: Conversation[]; conversations?: Conversation[] }>('/api/v1/chat/conversations');
      setConversations(data.data || data.conversations || []);
    } catch (err) {
      handleError(err);
    } finally {
      setLoading(false);
    }
  }, [handleError]);

  const loadMessages = useCallback(async (conversationId: string) => {
    try {
      setLoading(true);

      // Setup conversation encryption first if encryption is ready
      if (encryptionReady && deviceRegistered) {
        try {
          await setupConversationEncryption(conversationId);
        } catch (error) {
          console.warn('Failed to setup conversation encryption:', error);
          // Continue loading messages even if encryption setup fails
        }
      }

      const response = await apiService.get<{ data?: Message[]; messages?: Message[] } | Message[]>(`/api/v1/chat/conversations/${conversationId}/messages`);
      const rawMessages = Array.isArray(response) ? response : (response.data || response.messages || []);
      const reversedMessages = rawMessages.toReversed();
      const decryptedMessages = await decryptMessages(reversedMessages);
      setMessages(decryptedMessages);
    } catch (err) {
      handleError(err);
    } finally {
      setLoading(false);
    }
  }, [handleError, setupConversationEncryption, decryptMessages, encryptionReady, deviceRegistered]);

  const sendMessage = useCallback(async (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
    mentions?: any[];
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

      // Handle mentions
      if (options?.mentions) {
        messageData.mentions = options.mentions;
      }

      // Handle scheduling
      if (options?.scheduledAt) {
        messageData.scheduled_at = options.scheduledAt.toISOString();
      }

      // Handle voice message
      if (options?.type === 'voice' && options?.voiceData) {
        messageData.voice_duration_seconds = options.voiceData.duration;
        messageData.voice_waveform_data = options.voiceData.waveformData.join(',');
        // TODO: In a real app, you'd upload the blob to storage first
        // messageData.voice_file_url = await uploadVoiceFile(options.voiceData.blob);
      }

      // Handle encryption
      if (encryptionReady && deviceRegistered && content) {
        try {
          // Convert HTML to plain text if needed
          const plainTextContent = content.replace(/<[^>]*>/g, '').trim();
          if (!plainTextContent) {
            console.warn('No plain text content to encrypt');
            return;
          }

          const encryptedData = await multiDeviceE2EEService.encryptMessage(
            activeConversation.id,
            plainTextContent
          );

          if (encryptedData?.data) {
            messageData = {
              ...messageData,
              encrypted_content: {
                data: encryptedData.data,
                iv: encryptedData.iv,
                hmac: encryptedData.hmac,
                auth_data: encryptedData.authData,
              },
              content_hash: encryptedData.hash,
            };
          } else {
            console.error('Encryption failed, no encrypted data returned');
            return;
          }
        } catch (error) {
          console.error('Failed to encrypt message:', error);

          // Handle specific encryption errors
          if (error instanceof Error) {
            if (error.message.includes('KEY_MISMATCH_NEED_SETUP')) {
              console.log('Key mismatch detected, attempting to reinitialize encryption...');
              // Try to reinitialize encryption
              try {
                await initializeEncryption();
                // Retry encryption after reinitialization
                const encryptedData = await multiDeviceE2EEService.encryptMessage(
                  activeConversation.id,
                  plainTextContent
                );

                if (encryptedData?.data) {
                  messageData = {
                    ...messageData,
                    encrypted_content: {
                      data: encryptedData.data,
                      iv: encryptedData.iv,
                      hmac: encryptedData.hmac,
                      auth_data: encryptedData.authData,
                    },
                    content_hash: encryptedData.hash,
                  };
                } else {
                  throw new Error('Encryption failed after reinitialization');
                }
              } catch (retryError) {
                console.error('Failed to reinitialize encryption:', retryError);
                setError('Encryption setup failed. Please refresh the page and try again.');
                return;
              }
            } else if (error.message.includes('device not registered') || error.message.includes('device not trusted')) {
              console.log('Device registration issue, attempting to reinitialize...');
              try {
                await initializeEncryption();
                // Retry encryption after reinitialization
                const encryptedData = await multiDeviceE2EEService.encryptMessage(
                  activeConversation.id,
                  plainTextContent
                );

                if (encryptedData?.data) {
                  messageData = {
                    ...messageData,
                    encrypted_content: {
                      data: encryptedData.data,
                      iv: encryptedData.iv,
                      hmac: encryptedData.hmac,
                      auth_data: encryptedData.authData,
                    },
                    content_hash: encryptedData.hash,
                  };
                } else {
                  throw new Error('Encryption failed after device reinitialization');
                }
              } catch (retryError) {
                console.error('Failed to reinitialize device:', retryError);
                setError('Device setup failed. Please refresh the page and try again.');
                return;
              }
            } else {
              // For other encryption errors, show a user-friendly message
              setError('Message encryption failed. Please try again.');
              return;
            }
          } else {
            setError('Message encryption failed. Please try again.');
            return;
          }
        }
      } else {
        if (!encryptionReady || !deviceRegistered) {
          setError('Device encryption is not ready. Please set up your device first.');
          return;
        }
        console.warn('No content provided');
        return;
      }

      const rawMessage = await apiService.post<Message>(`/api/v1/chat/conversations/${activeConversation.id}/messages`, messageData);

      // Decrypt the returned message for display
      let decryptedMessage = rawMessage;
      if (encryptionReady && deviceRegistered && rawMessage.encrypted_content) {
        try {
          // Parse encrypted content if it's a string
          const encryptedMessage = typeof rawMessage.encrypted_content === 'string'
            ? JSON.parse(rawMessage.encrypted_content)
            : rawMessage.encrypted_content;

          const decryptedContent = await multiDeviceE2EEService.decryptMessage(
            rawMessage.conversation_id,
            encryptedMessage
          );

          decryptedMessage = {
            ...rawMessage,
            content: decryptedContent || content
          };
        } catch (error) {
          console.error('Failed to decrypt returned message:', error);
          decryptedMessage = {
            ...rawMessage,
            content: content // Use original content as fallback
          };
        }
      }

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
      const newConversation = await apiService.post<Conversation>('/api/v1/chat/conversations', {
        type: participants.length === 1 ? 'direct' : 'group',
        participants,
        name,
      });

      setConversations(prev => [newConversation, ...prev]);
      setActiveConversation(newConversation);

      // Setup encryption for the new conversation if encryption is ready
      if (encryptionReady && deviceRegistered) {
        try {
          await setupConversationEncryption(newConversation.id);
        } catch (error) {
          console.warn('Failed to setup encryption for new conversation:', error);
          // Don't throw here as the conversation was created successfully
        }
      }
    } catch (err) {
      handleError(err);
    }
  }, [handleError, encryptionReady, deviceRegistered, setupConversationEncryption]);

  // New feature methods
  const toggleReaction = useCallback(async (messageId: string, emoji: string) => {
    try {
      await apiService.post(`/api/v1/chat/messages/${messageId}/reactions/toggle`, { emoji });

      // TODO: In a real app, you'd update the message reactions in state
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
      await apiService.post(`/api/v1/chat/messages/${messageId}/read-receipts`, {});
    } catch (err) {
      handleError(err);
    }
  }, [handleError]);

  const setTyping = useCallback(async (isTyping: boolean) => {
    if (!activeConversation) return;

    try {
      if (isTyping) {
        await apiService.post(`/api/v1/chat/conversations/${activeConversation.id}/typing`, {});
      } else {
        await apiService.delete(`/api/v1/chat/conversations/${activeConversation.id}/typing`);
      }
    } catch (err) {
      handleError(err);
    }
  }, [activeConversation, handleError]);

  const searchMessages = useCallback(async (query: string): Promise<Message[]> => {
    if (!activeConversation || !query.trim()) return [];

    try {
      const results = await apiService.get<Message[]>(`/api/v1/chat/conversations/${activeConversation.id}/messages/search?q=${encodeURIComponent(query)}`);
      return await decryptMessages(results);
    } catch (err) {
      handleError(err);
      return [];
    }
  }, [activeConversation, handleError, decryptMessages]);

  // Group management functions
  const createGroup = useCallback(async (groupData: { name: string; description?: string; participants: string[] }) => {
    try {
      const newGroup = await apiService.post<Conversation>('/api/v1/chat/conversations', {
        type: 'group',
        name: groupData.name,
        description: groupData.description,
        participants: groupData.participants,
      });

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
      const updatedConversation = await apiService.put<{ conversation: Conversation }>(`/api/v1/chat/conversations/${activeConversation.id}/settings`, settings);
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
      await apiService.put(`/api/v1/chat/conversations/${activeConversation.id}/participants/role`, {
        user_id: userId,
        role: role,
      });

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
      await apiService.delete(`/api/v1/chat/conversations/${activeConversation.id}/participants`, {
        user_id: userId,
      });

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
      return await apiService.post<{ invite_url: string }>(`/api/v1/chat/conversations/${activeConversation.id}/invite-link`, options);
    } catch (err) {
      handleError(err);
      throw err;
    }
  }, [activeConversation, handleError]);

  const joinByInvite = useCallback(async (inviteCode: string) => {
    try {
      const result = await apiService.post<{ conversation: Conversation }>(`/api/v1/chat/join/${inviteCode}`, {
        invite_code: inviteCode,
      });

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
        const data = await apiService.get<{ typing_users: Array<{ id: string; name: string }> }>(`/api/v1/chat/conversations/${activeConversation.id}/typing`);
        setTypingUsers(data.typing_users || []);
      } catch (err) {
        // Silent fail for typing status
      }
    };

    const interval = setInterval(pollTypingUsers, 2000);
    return () => clearInterval(interval);
  }, [activeConversation]);

  // ApiService handles authentication automatically, no manual token initialization needed

  useEffect(() => {
    initializeEncryption();
  }, [initializeEncryption]);

  useEffect(() => {
    // ApiService handles authentication, load conversations immediately
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
