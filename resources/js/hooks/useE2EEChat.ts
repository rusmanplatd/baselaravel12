import { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';

interface Message {
    id: string;
    conversation_id: string;
    sender_id: string;
    type: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll';
    decrypted_content?: string;
    encrypted_content: string;
    is_edited: boolean;
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
    createConversation: (participants: string[], options?: CreateConversationOptions) => Promise<void>;
    loadConversation: (conversationId: string) => Promise<void>;
    addParticipant: (conversationId: string, userId: string) => Promise<void>;
    removeParticipant: (conversationId: string, userId: string) => Promise<void>;
    leaveConversation: (conversationId: string) => Promise<void>;
    
    // Messages
    loadMessages: (conversationId: string, options?: LoadMessagesOptions) => Promise<void>;
    sendMessage: (conversationId: string, content: string, options?: SendMessageOptions) => Promise<void>;
    editMessage: (conversationId: string, messageId: string, content: string) => Promise<void>;
    deleteMessage: (conversationId: string, messageId: string) => Promise<void>;
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
    type?: 'direct' | 'group' | 'channel';
    enable_quantum?: boolean;
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
    
    // Refs for WebSocket connections
    const wsConnection = useRef<WebSocket | null>(null);
    const subscribedConversations = useRef<Set<string>>(new Set());

    // Device fingerprint for E2EE
    const getDeviceFingerprint = useCallback((): string => {
        // Generate or retrieve device fingerprint
        let fingerprint = localStorage.getItem('device_fingerprint');
        if (!fingerprint) {
            fingerprint = generateDeviceFingerprint();
            localStorage.setItem('device_fingerprint', fingerprint);
        }
        return fingerprint;
    }, []);

    // API call wrapper with error handling
    const apiCall = useCallback(async (url: string, options: RequestInit = {}): Promise<any> => {
        try {
            const response = await fetch(`/api/v1/chat${url}`, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Device-Fingerprint': getDeviceFingerprint(),
                    ...options.headers,
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'API call failed');
            }

            return await response.json();
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
            
            const response = await apiCall('/devices', {
                method: 'POST',
                body: JSON.stringify({
                    ...deviceInfo,
                    device_fingerprint: deviceFingerprint,
                    hardware_fingerprint: await getHardwareFingerprint(),
                }),
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
            await apiCall(`/devices/${deviceId}/trust`, {
                method: 'POST',
                body: JSON.stringify({
                    verification_code: verificationCode,
                    auto_expire: true,
                }),
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
            await apiCall(`/devices/${deviceId}`, {
                method: 'DELETE',
                body: JSON.stringify({ reason }),
            });

            // Refresh devices list
            await loadDevices();
            setError(null);
        } catch (err) {
            console.error('Failed to revoke device:', err);
        }
    }, [apiCall]);

    const rotateDeviceKeys = useCallback(async (deviceId: string): Promise<void> => {
        try {
            await apiCall(`/devices/${deviceId}/rotate-keys`, {
                method: 'POST',
            });

            // Refresh devices list
            await loadDevices();
            setError(null);
        } catch (err) {
            console.error('Failed to rotate device keys:', err);
        }
    }, [apiCall]);

    const loadDevices = useCallback(async (): Promise<void> => {
        try {
            const response = await apiCall('/devices');
            setDevices(response.devices);
        } catch (err) {
            console.error('Failed to load devices:', err);
        }
    }, [apiCall]);

    // Conversation management functions
    const loadConversations = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        try {
            const response = await apiCall('/conversations');
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
    ): Promise<void> => {
        setIsLoading(true);
        try {
            const response = await apiCall('/conversations', {
                method: 'POST',
                body: JSON.stringify({
                    participants,
                    type: options.type || (participants.length > 1 ? 'group' : 'direct'),
                    name: options.name,
                    description: options.description,
                    enable_quantum: options.enable_quantum ?? true,
                }),
            });

            // Add to conversations list
            setConversations(prev => [response.conversation, ...prev]);
            setError(null);
        } catch (err) {
            console.error('Failed to create conversation:', err);
        } finally {
            setIsLoading(false);
        }
    }, [apiCall]);

    const loadConversation = useCallback(async (conversationId: string): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}`);
            setCurrentConversation(response.conversation);
            setError(null);
        } catch (err) {
            console.error('Failed to load conversation:', err);
        }
    }, [apiCall]);

    const addParticipant = useCallback(async (conversationId: string, userId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/participants`, {
                method: 'POST',
                body: JSON.stringify({ user_id: userId }),
            });

            // Refresh conversation details
            await loadConversation(conversationId);
            setError(null);
        } catch (err) {
            console.error('Failed to add participant:', err);
        }
    }, [apiCall, loadConversation]);

    const removeParticipant = useCallback(async (conversationId: string, userId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/participants`, {
                method: 'DELETE',
                body: JSON.stringify({ user_id: userId }),
            });

            // Refresh conversation details
            await loadConversation(conversationId);
            setError(null);
        } catch (err) {
            console.error('Failed to remove participant:', err);
        }
    }, [apiCall, loadConversation]);

    const leaveConversation = useCallback(async (conversationId: string): Promise<void> => {
        try {
            await apiCall(`/conversations/${conversationId}/leave`, {
                method: 'DELETE',
            });

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
            const response = await apiCall(url);
            
            if (options.before_id) {
                // Prepending older messages
                setMessages(prev => [...response.messages, ...prev]);
            } else {
                // Fresh load or appending newer messages
                setMessages(response.messages);
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
            const response = await apiCall(`/conversations/${conversationId}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    content,
                    type: options.type || 'text',
                    reply_to_id: options.reply_to_id,
                    scheduled_at: options.scheduled_at,
                    metadata: options.file_info,
                }),
            });

            // Add to messages list
            setMessages(prev => [response.message, ...prev]);
            setError(null);
        } catch (err) {
            console.error('Failed to send message:', err);
        }
    }, [apiCall]);

    const editMessage = useCallback(async (
        conversationId: string, 
        messageId: string, 
        content: string
    ): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}/messages/${messageId}`, {
                method: 'PATCH',
                body: JSON.stringify({ content }),
            });

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
            await apiCall(`/conversations/${conversationId}/messages/${messageId}`, {
                method: 'DELETE',
            });

            // Remove from messages list
            setMessages(prev => prev.filter(msg => msg.id !== messageId));
            setError(null);
        } catch (err) {
            console.error('Failed to delete message:', err);
        }
    }, [apiCall]);

    const addReaction = useCallback(async (
        conversationId: string, 
        messageId: string, 
        emoji: string
    ): Promise<void> => {
        try {
            const response = await apiCall(`/conversations/${conversationId}/messages/${messageId}/reactions`, {
                method: 'POST',
                body: JSON.stringify({ emoji }),
            });

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
            await apiCall(`/conversations/${conversationId}/messages/${messageId}/reactions`, {
                method: 'DELETE',
                body: JSON.stringify({ emoji }),
            });

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
            const response = await fetch(`/api/v1/chat/conversations/${conversationId}/attachments`, {
                method: 'POST',
                headers: {
                    'X-Device-Fingerprint': getDeviceFingerprint(),
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error('Failed to upload file');
            }

            const data = await response.json();
            return data.file_info;
        } catch (err) {
            setError('Failed to upload file');
            throw err;
        }
    }, [getDeviceFingerprint]);

    // Real-time subscription functions
    const subscribeToConversation = useCallback((conversationId: string): void => {
        if (subscribedConversations.current.has(conversationId)) {
            return;
        }

        // Initialize WebSocket connection if needed
        if (!wsConnection.current) {
            const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws/chat`;
            wsConnection.current = new WebSocket(wsUrl);

            wsConnection.current.onmessage = (event) => {
                const data = JSON.parse(event.data);
                handleRealTimeMessage(data);
            };
        }

        // Subscribe to conversation
        wsConnection.current.send(JSON.stringify({
            type: 'subscribe',
            conversation_id: conversationId,
        }));

        subscribedConversations.current.add(conversationId);
    }, []);

    const unsubscribeFromConversation = useCallback((conversationId: string): void => {
        if (!subscribedConversations.current.has(conversationId)) {
            return;
        }

        wsConnection.current?.send(JSON.stringify({
            type: 'unsubscribe',
            conversation_id: conversationId,
        }));

        subscribedConversations.current.delete(conversationId);
    }, []);

    // Handle real-time messages
    const handleRealTimeMessage = useCallback((data: any): void => {
        switch (data.type) {
            case 'new_message':
                setMessages(prev => [data.message, ...prev]);
                // Update conversation last activity
                setConversations(prev => prev.map(conv => 
                    conv.id === data.message.conversation_id 
                        ? { ...conv, last_activity_at: data.message.created_at }
                        : conv
                ));
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
    }, []);

    // Load devices on mount
    useEffect(() => {
        loadDevices();
    }, [loadDevices]);

    // Cleanup WebSocket on unmount
    useEffect(() => {
        return () => {
            wsConnection.current?.close();
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
        navigator.platform,
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
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
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