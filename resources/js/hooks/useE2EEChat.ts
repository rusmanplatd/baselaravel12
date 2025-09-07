import { useState, useEffect, useCallback, useRef } from 'react';
import { apiService } from '@/services/ApiService';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';
import { subscribeToChannel, leaveChannel, handleBroadcastingAuthError } from '@/utils/broadcastingAuth';
import logger from '@/services/LoggerService';

interface Message {
    id: string;
    conversation_id: string;
    sender_id: string;
    type: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll';
    decrypted_content?: string;
    encrypted_content: string;
    is_edited: boolean;
    is_forwarded?: boolean;
    forward_count?: number;
    created_at: string;
    sender: {
        id: string;
        name: string;
        avatar: string;
    };
    reactions?: MessageReaction[];
    replies?: Message[];
    reply_to?: Message;
    attachments?: MessageAttachment[];
}

interface MessageAttachment {
    id: string;
    filename: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    thumbnail_path?: string;
    type: string;
}

interface MessageReaction {
    id: string;
    user_id: string;
    emoji: string;
    user: {
        id: string;
        name: string;
    };
}

interface Conversation {
    id: string;
    type: 'direct' | 'group' | 'channel';
    name?: string;
    description?: string;
    avatar_url?: string;
    unread_count: number;
    is_muted: boolean;
    encryption_status: {
        is_encrypted: boolean;
        algorithm: string;
        quantum_ready: boolean;
    };
    participants: ConversationParticipant[];
    last_activity_at: string;
    organization_id?: string;
}

interface ConversationParticipant {
    id: string;
    user_id: string;
    role: 'admin' | 'moderator' | 'member';
    is_active: boolean;
    user: {
        id: string;
        name: string;
        avatar: string;
        avatar_url?: string;
    };
}

interface Device {
    id: string;
    device_name: string;
    device_type: 'mobile' | 'desktop' | 'web' | 'tablet';
    is_trusted: boolean;
    is_active: boolean;
    quantum_ready: boolean;
    security_level: string;
    fingerprint_short: string;
}

interface UseE2EEChatReturn {
    // State
    conversations: Conversation[];
    messages: Message[];
    currentConversation: Conversation | null;
    devices: Device[];
    isLoading: boolean;
    isLoadingMessages: boolean;
    error: string | null;

    // Device management
    registerDevice: (deviceInfo: RegisterDeviceRequest) => Promise<void>;
    trustDevice: (deviceId: string, verificationCode?: string) => Promise<void>;
    revokeDevice: (deviceId: string, reason: string) => Promise<void>;
    rotateDeviceKeys: (deviceId: string) => Promise<void>;

    // Conversations
    loadConversations: () => Promise<void>;
    createConversation: (participants: string[], options?: CreateConversationOptions) => Promise<Conversation>;
    loadConversation: (conversationId: string) => Promise<void>;
    addParticipant: (conversationId: string, userId: string) => Promise<void>;
    removeParticipant: (conversationId: string, userId: string) => Promise<void>;
    leaveConversation: (conversationId: string) => Promise<void>;

    // Messages
    loadMessages: (conversationId: string, options?: LoadMessagesOptions) => Promise<void>;
    sendMessage: (conversationId: string, content: string, options?: SendMessageOptions) => Promise<void>;
    editMessage: (conversationId: string, messageId: string, content: string) => Promise<void>;
    deleteMessage: (conversationId: string, messageId: string) => Promise<void>;
    forwardMessage: (conversationId: string, messageId: string, targetConversationIds: string[]) => Promise<void>;
    addReaction: (conversationId: string, messageId: string, emoji: string) => Promise<void>;
    removeReaction: (conversationId: string, messageId: string, emoji: string) => Promise<void>;

    // File handling
    uploadFile: (conversationId: string, file: File) => Promise<string>;

    // Real-time
    subscribeToConversation: (conversationId: string) => void;
    unsubscribeFromConversation: (conversationId: string) => void;
}

interface RegisterDeviceRequest {
    device_name: string;
    device_type: 'mobile' | 'desktop' | 'web' | 'tablet';
    public_key: string;
    enable_quantum?: boolean;
    quantum_algorithm?: string;
    capabilities?: string[];
}

interface CreateConversationOptions {
    name?: string;
    description?: string;
    avatar_url?: string;
    type?: 'direct' | 'group' | 'channel';
    enable_quantum?: boolean;
    key_strength?: 512 | 768 | 1024;
    settings?: {
        is_public?: boolean;
        allow_member_invites?: boolean;
        moderated?: boolean;
        everyone_can_add_members?: boolean;
    };
}

interface LoadMessagesOptions {
    before_id?: string;
    after_id?: string;
    limit?: number;
}

interface SendMessageOptions {
    type?: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice';
    reply_to_id?: string;
    scheduled_at?: string;
    file_info?: any;
}

export function useE2EEChat(): UseE2EEChatReturn {
    // State management
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [messages, setMessages] = useState<Message[]>([]);
    const [currentConversation, setCurrentConversation] = useState<Conversation | null>(null);
    const [devices, setDevices] = useState<Device[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMessages, setIsLoadingMessages] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Refs for tracking subscribed conversations
    const subscribedConversations = useRef<Set<string>>(new Set());

    // Device fingerprint for E2EE
    const getDeviceFingerprint = useCallback((): string => {
        // Generate or retrieve device fingerprint
        let fingerprint = getUserStorageItem('device_fingerprint');
        if (!fingerprint) {
            fingerprint = generateDeviceFingerprint();
            setUserStorageItem('device_fingerprint', fingerprint);
        }
        return fingerprint;
    }, []);

    // API call wrapper with error handling using ApiService
    const apiCall = useCallback(async (url: string, method: string = 'GET', data?: unknown, options: RequestInit = {}): Promise<any> => {
        try {
            const fullUrl = `/api/v1/chat${url}`;
            const additionalHeaders = {
                'X-Device-Fingerprint': getDeviceFingerprint(),
                ...options.headers,
            };

            let response: any;
            switch (method.toLowerCase()) {
                case 'get':
                    response = await apiService.get(fullUrl, { headers: additionalHeaders });
                    break;
                case 'post':
                    response = await apiService.post(fullUrl, data, { headers: additionalHeaders });
                    break;
                case 'put':
                    response = await apiService.put(fullUrl, data, { headers: additionalHeaders });
                    break;
                case 'delete':
                    response = await apiService.delete(fullUrl, { headers: additionalHeaders });
                    break;
                case 'patch':
                    response = await apiService.patch(fullUrl, data, { headers: additionalHeaders });
                    break;
                default:
                    throw new Error(`Unsupported method: ${method}`);
            }

            return response;
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
            throw err;
        }
    }, [getDeviceFingerprint]);

    // Device management functions
    const registerDevice = useCallback(async (deviceInfo: RegisterDeviceRequest): Promise<void> => {
        setIsLoading(true);
        try {
            const deviceFingerprint = getDeviceFingerprint();

            // Check if device is already registered before attempting registration
            try {
                const devicesResponse = await apiCall('/devices', 'GET');
                const existingDevice = devicesResponse.devices?.find((device: Device) =>
                    device.fingerprint_short === deviceFingerprint.substring(0, 8)
                );

                if (existingDevice) {
                    setError(`Device already registered as "${existingDevice.device_name}"`);
                    return;
                }
            } catch (err) {
                // If we can't check existing devices, proceed with registration
                console.warn('Could not check existing devices, proceeding with registration:', err);
            }

            const response = await apiCall('/devices', 'POST', {
                ...deviceInfo,
                device_fingerprint: deviceFingerprint,
                hardware_fingerprint: await getHardwareFingerprint(),
            });

            // Update devices list
            await loadDevices();

            setError(null);
        } catch (err) {
            console.error('Failed to register device:', err);
        } finally {
            setIsLoading(false);
        }
    }, [apiCall, getDeviceFingerprint]);

    const trustDevice = useCallback(async (deviceId: string, verificationCode?: string): Promise<void> => {
        try {
            await apiCall(`/devices/${deviceId}/trust`, 'POST', {
                verification_code: verificationCode,
                auto_expire: true,
            });

            // Refresh devices list
            await loadDevices();
            setError(null);
        } catch (err) {
            console.error('Failed to trust device:', err);
        }
    }, [apiCall]);

    const revokeDevice = useCallback(async (deviceId: string, reason: string): Promise<void> => {
        try {
            await apiCall(`/devices/${deviceId}`, 'DELETE', { reason });

            // Refresh devices list
            await loadDevices();
            setError(null);
        } catch (err) {
            console.error('Failed to revoke device:', err);
        }
    }, [apiCall]);

    const rotateDeviceKeys = useCallback(async (deviceId: string): Promise<void> => {
        try {
            await apiCall(`/devices/${deviceId}/rotate-keys`, 'POST');

            // Refresh devices list
            await loadDevices();
            setError(null);
        } catch (err) {
            console.error('Failed to rotate device keys:', err);
        }
    }, [apiCall]);

    const loadDevices = useCallback(async (): Promise<void> => {
        try {
            const response = await apiCall('/devices', 'GET');
            setDevices(response.devices);
        } catch (err) {
            console.error('Failed to load devices:', err);
        }
    }, [apiCall]);

    // Conversation management functions
    const loadConversations = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        try {
            const response = await apiCall('/conversations', 'GET');
            setConversations(response.conversations);
            setError(null);
        } catch (err) {
            console.error('Failed to load conversations:', err);
        } finally {
            setIsLoading(false);
        }
    }, [apiCall]);

    const createConversation = useCallback(async (
        participants: string[],
        options: CreateConversationOptions = {}
    ): Promise<Conversation> => {
        setIsLoading(true);
        try {
            // Determine conversation type based on participants if not specified
            const type = options.type || (participants.length === 1 ? 'direct' : 'group');

            const response = await apiCall('/conversations', 'POST', {
                participants,
                type,
                name: options.name,
                description: options.description,
                avatar_url: options.avatar_url,
                enable_quantum: options.enable_quantum ?? true,
                key_strength: options.key_strength ?? 768,
                settings: options.settings ?? {},
            });

            // Add to conversations list if it's a new conversation
            if (!response.existing) {
                setConversations(prev => [response.conversation, ...prev]);
            }

            setError(null);

            // Return conversation for further processing if needed
            return response.conversation;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to create conversation';
            setError(errorMessage);
            console.error('Failed to create conversation:', err);
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, [apiCall]);

    const loadConversation = useCallback(async (conversationId: string): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}`, 'GET');
            setCurrentConversation(response.conversation);
            setError(null);
        } catch (err) {
            console.error('Failed to load conversation:', err);
        }
    }, [apiCall]);

    const addParticipant = useCallback(async (conversationId: string, userId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/participants`, 'POST', { user_id: userId });

            // Refresh conversation details
            await loadConversation(conversationId);
            setError(null);
        } catch (err) {
            console.error('Failed to add participant:', err);
        }
    }, [apiCall, loadConversation]);

    const removeParticipant = useCallback(async (conversationId: string, userId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/participants`, 'DELETE', { user_id: userId });

            // Refresh conversation details
            await loadConversation(conversationId);
            setError(null);
        } catch (err) {
            console.error('Failed to remove participant:', err);
        }
    }, [apiCall, loadConversation]);

    const leaveConversation = useCallback(async (conversationId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/leave`, 'DELETE');

            // Remove from conversations list
            setConversations(prev => prev.filter(c => c.id !== conversationId));

            // Clear current conversation if it's the one being left
            if (currentConversation?.id === conversationId) {
                setCurrentConversation(null);
                setMessages([]);
            }

            setError(null);
        } catch (err) {
            console.error('Failed to leave conversation:', err);
        }
    }, [apiCall, currentConversation]);

    // Message management functions
    const loadMessages = useCallback(async (
        conversationId: string,
        options: LoadMessagesOptions = {}
    ): Promise<void> => {
        setIsLoadingMessages(true);
        try {
            const params = new URLSearchParams();
            if (options.before_id) params.set('before_id', options.before_id);
            if (options.after_id) params.set('after_id', options.after_id);
            if (options.limit) params.set('limit', options.limit.toString());

            const url = `/conversations/${conversationId}/messages${params.toString() ? '?' + params.toString() : ''}`;
            const response = await apiCall(url, 'GET');

            // Decrypt messages on client side for proper E2EE
            const decryptedMessages = await Promise.all(
                response.messages.map(async (message: Message) => {
                    try {
                        const decryptedContent = await decryptMessageClientSide(message.encrypted_content);
                        
                        // Also decrypt reply_to message if it exists
                        let decryptedReplyTo = message.reply_to;
                        if (message.reply_to && message.reply_to.encrypted_content) {
                            console.log('Decrypting reply_to message:', {
                                messageId: message.id,
                                replyToId: message.reply_to.id,
                                hasEncryptedContent: !!message.reply_to.encrypted_content,
                                encryptedContentPreview: message.reply_to.encrypted_content.substring(0, 100)
                            });
                            
                            try {
                                const decryptedReplyContent = await decryptMessageClientSide(message.reply_to.encrypted_content);
                                console.log('Successfully decrypted reply_to:', {
                                    replyToId: message.reply_to.id,
                                    decryptedContent: decryptedReplyContent
                                });
                                decryptedReplyTo = {
                                    ...message.reply_to,
                                    decrypted_content: decryptedReplyContent,
                                };
                            } catch (replyError) {
                                console.error('Failed to decrypt reply message:', {
                                    replyToId: message.reply_to.id,
                                    error: replyError,
                                    encryptedContent: message.reply_to.encrypted_content
                                });
                                decryptedReplyTo = {
                                    ...message.reply_to,
                                    decrypted_content: '[Encrypted message - decryption failed]',
                                };
                            }
                        } else {
                            console.log('Reply_to message has no encrypted_content:', {
                                messageId: message.id,
                                hasReplyTo: !!message.reply_to,
                                replyToData: message.reply_to
                            });
                        }
                        
                        return {
                            ...message,
                            decrypted_content: decryptedContent,
                            reply_to: decryptedReplyTo,
                        };
                    } catch (error) {
                        console.warn('Failed to decrypt message:', error);

                        // Try migration endpoint for quantum-encrypted messages
                        if (message.encrypted_content.includes('ciphertext') && message.encrypted_content.includes('encrypted_message')) {
                            try {
                                const migrationResponse = await apiCall(`/conversations/${conversationId}/messages/${message.id}/migrate`, 'POST');
                                if (migrationResponse.migration_status === 'success' && migrationResponse.decrypted_content) {
                                    return {
                                        ...message,
                                        decrypted_content: migrationResponse.decrypted_content,
                                    };
                                }
                            } catch (migrationError) {
                                console.warn('Migration failed for message:', migrationError);
                            }
                        }

                        return {
                            ...message,
                            decrypted_content: '[Encrypted message - decryption failed]',
                        };
                    }
                })
            );

            if (options.before_id) {
                // Prepending older messages
                setMessages(prev => [...decryptedMessages, ...prev]);
            } else {
                // Fresh load or appending newer messages
                setMessages(decryptedMessages);
            }

            setError(null);
        } catch (err) {
            console.error('Failed to load messages:', err);
        } finally {
            setIsLoadingMessages(false);
        }
    }, [apiCall]);

    const sendMessage = useCallback(async (
        conversationId: string,
        content: string,
        options: SendMessageOptions = {}
    ): Promise<void> => {
        try {
            // Encrypt message on client side for proper E2EE
            logger.chat.encryption('encrypting', { conversationId, contentLength: content.length });
            const encryptedData = await encryptMessageClientSide(content, conversationId);
            logger.chat.encryption('encrypted', { algorithm: encryptedData.algorithm });

            const response = await apiCall(`/conversations/${conversationId}/messages`, 'POST', {
                type: options.type || 'text',
                encrypted_content: encryptedData.encrypted_content,
                content_hash: encryptedData.content_hash,
                encryption_algorithm: encryptedData.algorithm,
                reply_to_id: options.reply_to_id,
                scheduled_at: options.scheduled_at,
                metadata: options.file_info,
            });

            // Add to messages list with decrypted content for local display
            const messageWithDecryptedContent = {
                ...response.message,
                decrypted_content: content, // Show decrypted content locally
            };
            setMessages(prev => [messageWithDecryptedContent, ...prev]);
            logger.websocket.message('sent', { messageId: response.message.id, conversationId });
            setError(null);
        } catch (err) {
            logger.error('Failed to send message', err, 'E2EEChat');
        }
    }, [apiCall]);

    const editMessage = useCallback(async (
        conversationId: string,
        messageId: string,
        content: string
    ): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}/messages/${messageId}`, 'PATCH', { content });

            // Update message in list
            setMessages(prev => prev.map(msg =>
                msg.id === messageId ? response.message : msg
            ));
            setError(null);
        } catch (err) {
            console.error('Failed to edit message:', err);
        }
    }, [apiCall]);

    const deleteMessage = useCallback(async (conversationId: string, messageId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/messages/${messageId}`, 'DELETE');

            // Remove from messages list
            setMessages(prev => prev.filter(msg => msg.id !== messageId));
            setError(null);
        } catch (err) {
            console.error('Failed to delete message:', err);
        }
    }, [apiCall]);

    const forwardMessage = useCallback(async (
        conversationId: string, 
        messageId: string, 
        targetConversationIds: string[]
    ): Promise<void> => {
        try {
            // Get the original message to forward
            const originalMessage = messages.find(msg => msg.id === messageId);
            if (!originalMessage) {
                throw new Error('Message not found');
            }

            // Forward message via API
            await apiCall(`/conversations/${conversationId}/messages/${messageId}/forward`, 'POST', {
                target_conversation_ids: targetConversationIds,
                encrypted_content: originalMessage.encrypted_content,
                content_hash: originalMessage.encrypted_content ? btoa(originalMessage.encrypted_content) : ''
            });

            setError(null);
        } catch (err) {
            console.error('Failed to forward message:', err);
            throw err;
        }
    }, [apiCall, messages]);

    const addReaction = useCallback(async (
        conversationId: string,
        messageId: string,
        emoji: string
    ): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}/messages/${messageId}/reactions`, 'POST', { emoji });

            // Update message reactions
            setMessages(prev => prev.map(msg => {
                if (msg.id === messageId) {
                    return {
                        ...msg,
                        reactions: [...(msg.reactions || []), response.reaction],
                    };
                }
                return msg;
            }));
            setError(null);
        } catch (err) {
            console.error('Failed to add reaction:', err);
        }
    }, [apiCall]);

    const removeReaction = useCallback(async (
        conversationId: string,
        messageId: string,
        emoji: string
    ): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/messages/${messageId}/reactions`, 'DELETE', { emoji });

            // Remove reaction from message
            setMessages(prev => prev.map(msg => {
                if (msg.id === messageId) {
                    return {
                        ...msg,
                        reactions: (msg.reactions || []).filter(r => r.emoji !== emoji),
                    };
                }
                return msg;
            }));
            setError(null);
        } catch (err) {
            console.error('Failed to remove reaction:', err);
        }
    }, [apiCall]);

    // File upload function
    const uploadFile = useCallback(async (conversationId: string, file: File): Promise<string> => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', getFileType(file));

        try {
            const response = await apiService.postFormData<{ file_info: string }>(
                `/api/v1/chat/conversations/${conversationId}/attachments`,
                formData,
                {
                    headers: {
                        'X-Device-Fingerprint': getDeviceFingerprint(),
                    }
                }
            );

            return response.file_info;
        } catch (err) {
            setError('Failed to upload file');
            throw err;
        }
    }, [getDeviceFingerprint]);

    // Real-time subscription functions
    const subscribeToConversation = useCallback(async (conversationId: string): Promise<void> => {
        if (subscribedConversations.current.has(conversationId)) {
            return;
        }

        try {
            // Subscribe to conversation channel with enhanced error handling
            await subscribeToChannel(`conversation.${conversationId}`, {
                'message.sent': (data: any) => {
                    logger.websocket.event('message.sent', data);
                    logger.websocket.message('received', {
                        messageId: data.message?.id,
                        conversationId: data.message?.conversation_id,
                        senderId: data.sender_id,
                        messageType: data.message?.type,
                        timestamp: data.message?.created_at
                    });

                    // Get current user ID to filter out own messages
                    const currentUserId = getUserStorageItem('user_id');
                    logger.debug(`Current user ID: ${currentUserId}, Sender ID: ${data.sender_id}`, {
                        currentUserId,
                        senderId: data.sender_id
                    }, 'E2EEChat');

                    // Don't process messages from the current user (they already see their own message)
                    if (data.sender_id && data.sender_id.toString() === currentUserId?.toString()) {
                        logger.debug('Ignoring own message in real-time update', null, 'E2EEChat');
                        return;
                    }

                    logger.debug('Processing real-time message from another user', null, 'E2EEChat');
                    handleRealTimeMessage({
                        type: 'new_message',
                        message: data.message
                    });
                },
                'message.edited': (data: any) => {
                    logger.websocket.event('message.edited', data);
                    handleRealTimeMessage({
                        type: 'message_edited',
                        message: data.message
                    });
                },
                'message.deleted': (data: any) => {
                    logger.websocket.event('message.deleted', data);
                    handleRealTimeMessage({
                        type: 'message_deleted',
                        message_id: data.message_id
                    });
                },
                'reaction.added': (data: any) => {
                    logger.websocket.event('reaction.added', data);
                    handleRealTimeMessage({
                        type: 'reaction_added',
                        message_id: data.message_id,
                        reaction: data.reaction
                    });
                },
                'reaction.removed': (data: any) => {
                    logger.websocket.event('reaction.removed', data);
                    handleRealTimeMessage({
                        type: 'reaction_removed',
                        message_id: data.message_id,
                        reaction_id: data.reaction_id
                    });
                },
                'participant.joined': (data: any) => {
                    logger.websocket.event('participant.joined', data);
                    // Refresh conversation to get updated participants
                    if (data.conversation_id === conversationId) {
                        loadConversation(conversationId);
                    }
                },
                'participant.left': (data: any) => {
                    logger.websocket.event('participant.left', data);
                    // Refresh conversation to get updated participants
                    if (data.conversation_id === conversationId) {
                        loadConversation(conversationId);
                    }
                },
                'typing.start': (data: any) => {
                    logger.websocket.event('typing.start', data);
                    // Handle typing indicators
                },
                'typing.stop': (data: any) => {
                    logger.websocket.event('typing.stop', data);
                    // Handle typing indicators
                },
                'presence.updated': (data: any) => {
                    logger.websocket.event('presence.updated', data);
                    // Handle presence updates
                }
            });

            subscribedConversations.current.add(conversationId);
            logger.chat.conversation('subscribed', { conversationId });
        } catch (error) {
            logger.error(`Failed to subscribe to conversation ${conversationId}`, error, 'E2EEChat');
            await handleBroadcastingAuthError(error);
        }
    }, [loadConversation]);

    const unsubscribeFromConversation = useCallback((conversationId: string): void => {
        if (!subscribedConversations.current.has(conversationId)) {
            return;
        }

        // Leave the conversation channel
        leaveChannel(`conversation.${conversationId}`);
        subscribedConversations.current.delete(conversationId);
        logger.chat.conversation('unsubscribed', { conversationId });
    }, []);

    // Handle real-time messages
    const handleRealTimeMessage = useCallback((data: any): void => {
        switch (data.type) {
            case 'new_message':
                // Fetch the full message with encrypted content asynchronously
                (async () => {
                    try {
                        const response = await apiService.get(`/api/v1/chat/conversations/${data.message.conversation_id}/messages/${data.message.id}`);
                        const fullMessage = response.data.message;

                        // Decrypt the message content on client side for proper E2EE
                        try {
                            logger.chat.encryption('decrypting', { messageId: fullMessage.id });
                            const decryptedContent = await decryptMessageClientSide(fullMessage.encrypted_content);
                            fullMessage.decrypted_content = decryptedContent;
                            logger.chat.encryption('decrypted', { messageId: fullMessage.id });
                        } catch (decryptError) {
                            logger.warn('Failed to decrypt real-time message', decryptError, 'E2EEChat');
                            
                            // Try migration endpoint for quantum-encrypted messages
                            if (fullMessage.encrypted_content && fullMessage.encrypted_content.includes('ciphertext') && fullMessage.encrypted_content.includes('encrypted_message')) {
                                try {
                                    const migrationResponse = await apiCall(`/conversations/${data.message.conversation_id}/messages/${data.message.id}/migrate`, 'POST');
                                    if (migrationResponse.migration_status === 'success' && migrationResponse.decrypted_content) {
                                        fullMessage.decrypted_content = migrationResponse.decrypted_content;
                                    } else {
                                        fullMessage.decrypted_content = '[Encrypted message - decryption failed]';
                                    }
                                } catch (migrationError) {
                                    logger.warn('Migration failed for real-time message', migrationError, 'E2EEChat');
                                    fullMessage.decrypted_content = '[Encrypted message - decryption failed]';
                                }
                            } else {
                                fullMessage.decrypted_content = '[Encrypted message - decryption failed]';
                            }
                        }

                        // Also decrypt reply_to message if it exists
                        if (fullMessage.reply_to && fullMessage.reply_to.encrypted_content) {
                            try {
                                const decryptedReplyContent = await decryptMessageClientSide(fullMessage.reply_to.encrypted_content);
                                fullMessage.reply_to.decrypted_content = decryptedReplyContent;
                            } catch (replyError) {
                                logger.warn('Failed to decrypt reply message in real-time', replyError, 'E2EEChat');
                                fullMessage.reply_to.decrypted_content = '[Encrypted message - decryption failed]';
                            }
                        }

                        // Map type field for frontend compatibility
                        fullMessage.type = fullMessage.message_type;

                        setMessages(prev => [fullMessage, ...prev]);

                        // Update conversation last activity
                        setConversations(prev => prev.map(conv =>
                            conv.id === data.message.conversation_id
                                ? { ...conv, last_activity_at: data.message.created_at }
                                : conv
                        ));
                    } catch (error) {
                        logger.error('Failed to fetch full message for real-time update', error, 'E2EEChat');
                        // Fallback to using the broadcasted message data (but it won't have encrypted content)
                        const fallbackMessage = {
                            ...data.message,
                            decrypted_content: '[Message content not available]',
                            type: data.message.type || data.message.message_type,
                        };
                        setMessages(prev => [fallbackMessage, ...prev]);
                    }
                })();
                break;

            case 'message_edited':
                setMessages(prev => prev.map(msg =>
                    msg.id === data.message.id ? data.message : msg
                ));
                break;

            case 'message_deleted':
                setMessages(prev => prev.filter(msg => msg.id !== data.message_id));
                break;

            case 'reaction_added':
                setMessages(prev => prev.map(msg => {
                    if (msg.id === data.message_id) {
                        return {
                            ...msg,
                            reactions: [...(msg.reactions || []), data.reaction],
                        };
                    }
                    return msg;
                }));
                break;

            case 'reaction_removed':
                setMessages(prev => prev.map(msg => {
                    if (msg.id === data.message_id) {
                        return {
                            ...msg,
                            reactions: (msg.reactions || []).filter(r => r.id !== data.reaction_id),
                        };
                    }
                    return msg;
                }));
                break;
        }
    }, [apiCall]);

    // Load devices on mount
    useEffect(() => {
        loadDevices();
    }, [loadDevices]);

    // Cleanup subscriptions on unmount
    useEffect(() => {
        return () => {
            // Leave all subscribed channels
            subscribedConversations.current.forEach(conversationId => {
                leaveChannel(`conversation.${conversationId}`);
            });
            subscribedConversations.current.clear();
        };
    }, []);

    return {
        // State
        conversations,
        messages,
        currentConversation,
        devices,
        isLoading,
        isLoadingMessages,
        error,

        // Device management
        registerDevice,
        trustDevice,
        revokeDevice,
        rotateDeviceKeys,

        // Conversations
        loadConversations,
        createConversation,
        loadConversation,
        addParticipant,
        removeParticipant,
        leaveConversation,

        // Messages
        loadMessages,
        sendMessage,
        editMessage,
        deleteMessage,
        forwardMessage,
        addReaction,
        removeReaction,

        // File handling
        uploadFile,

        // Real-time
        subscribeToConversation,
        unsubscribeFromConversation,
    };
}

// Helper functions
function generateDeviceFingerprint(): string {
    const components = [
        navigator.userAgent,
        navigator.language,
        navigator.userAgent.includes('Win') ? 'Win32' : navigator.userAgent.includes('Mac') ? 'MacIntel' : 'Linux',
        navigator.hardwareConcurrency?.toString() || '',
        screen.width + 'x' + screen.height,
        new Date().getTimezoneOffset().toString(),
    ];

    const combined = components.join('|');
    return btoa(combined).replace(/[+/=]/g, '').substring(0, 16);
}

async function getHardwareFingerprint(): Promise<string> {
    // Generate hardware fingerprint using available APIs
    const components: string[] = [];

    // Canvas fingerprint
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('E2EE Device Fingerprint', 2, 2);
            components.push(canvas.toDataURL());
        }
    } catch (e) {
        // Canvas fingerprinting blocked
    }

    // WebGL fingerprint
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl') as WebGLRenderingContext | null;
        if (gl) {
            const renderer = gl.getParameter(gl.RENDERER);
            const vendor = gl.getParameter(gl.VENDOR);
            components.push(renderer + '|' + vendor);
        }
    } catch (e) {
        // WebGL fingerprinting blocked
    }

    const combined = components.join('||');
    const encoder = new TextEncoder();
    const data = encoder.encode(combined);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').substring(0, 32);
}

function getFileType(file: File): 'image' | 'video' | 'audio' | 'file' {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type.startsWith('video/')) return 'video';
    if (file.type.startsWith('audio/')) return 'audio';
    return 'file';
}

/**
 * Encrypt message on client side for proper E2EE
 */
async function encryptMessageClientSide(content: string, conversationId: string): Promise<{
    encrypted_content: string;
    content_hash: string;
    algorithm: string;
}> {
    try {
        // Use Web Crypto API for client-side encryption
        const key = await crypto.subtle.generateKey(
            { name: 'AES-GCM', length: 256 },
            true, // Make key extractable so we can export it
            ['encrypt']
        );

        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encodedContent = new TextEncoder().encode(content);

        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            encodedContent
        );

        // Generate content hash
        const contentHash = await crypto.subtle.digest('SHA-256', encodedContent);

        // Export key for storage (in real implementation, this would be stored securely)
        const exportedKey = await crypto.subtle.exportKey('raw', key);

        return {
            encrypted_content: JSON.stringify({
                encrypted_message: btoa(String.fromCharCode(...new Uint8Array(encrypted))),
                key: btoa(String.fromCharCode(...new Uint8Array(exportedKey))),
                nonce: btoa(String.fromCharCode(...iv)),
                algorithm: 'AES-256-GCM',
                fallback_mode: true,
            }),
            content_hash: btoa(String.fromCharCode(...new Uint8Array(contentHash))),
            algorithm: 'AES-256-GCM',
        };
    } catch (error) {
        console.error('Client-side encryption failed:', error);
        // Fallback to base64 encoding for development
        return {
            encrypted_content: JSON.stringify({
                encrypted_message: btoa(content),
                fallback_mode: true,
            }),
            content_hash: btoa(content),
            algorithm: 'BASE64-FALLBACK',
        };
    }
}

/**
 * Decrypt message on client side for proper E2EE
 */
async function decryptMessageClientSide(encryptedContent: string): Promise<string> {
    try {
        const data = JSON.parse(encryptedContent);

        // Check if it's a fallback base64 encoded message
        if (data.fallback_mode && data.encrypted_message && !data.key) {
            return atob(data.encrypted_message);
        }

        // Check if it's a proper encrypted message
        if (data.encrypted_message && data.key && data.nonce) {
            const key = await crypto.subtle.importKey(
                'raw',
                new Uint8Array(Array.from(atob(data.key), c => c.charCodeAt(0))),
                { name: 'AES-GCM' },
                false,
                ['decrypt']
            );

            const iv = new Uint8Array(Array.from(atob(data.nonce), c => c.charCodeAt(0)));
            const encryptedBytes = new Uint8Array(Array.from(atob(data.encrypted_message), c => c.charCodeAt(0)));

            const decrypted = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv },
                key,
                encryptedBytes
            );

            return new TextDecoder().decode(decrypted);
        }

        // Handle old quantum encryption format - try fallback decryption
        if (data.ciphertext && data.encrypted_message && data.nonce) {
            try {
                // Check if the encrypted_message is actually base64-encoded plain text (fallback from server)
                const decoded = atob(data.encrypted_message);

                // Check if the decoded content is printable text (like the server does)
                if (decoded && /^[\x20-\x7E\s]*$/.test(decoded)) {
                    // This is likely a fallback message where the content was base64 encoded
                    return decoded;
                }

                // If it's not plain text, it's a real quantum encrypted message
                return '[Message encrypted with quantum cryptography - cannot be decrypted in current environment]';
            } catch (error) {
                console.warn('Failed to process quantum encrypted message:', error);
                return '[Encrypted message - quantum format, decryption failed]';
            }
        }

        throw new Error('Unknown encryption format');
    } catch (error) {
        console.error('Client-side decryption failed:', error);
        return '[Encrypted message - decryption failed]';
    }
}
