import { useState, useCallback, useEffect } from 'react';
import { apiService } from '@/services/ApiService';
import { router } from '@inertiajs/react';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';

export interface Thread {
    id: string;
    conversation_id: string;
    parent_message_id: string;
    creator_id: string;
    title?: string;
    decrypted_title?: string;
    is_active: boolean;
    participant_count: number;
    message_count: number;
    last_message_at: string;
    last_message_id?: string;
    creator: {
        id: string;
        name: string;
        avatar: string;
    };
    parent_message: {
        id: string;
        sender: {
            id: string;
            name: string;
        };
        decrypted_content?: string;
        created_at: string;
    };
    participants?: ThreadParticipant[];
}

export interface ThreadParticipant {
    id: string;
    user_id: string;
    joined_at: string;
    left_at?: string;
    last_read_message_id?: string;
    notification_settings: {
        mentions: boolean;
        replies: boolean;
        all_messages: boolean;
    };
    user: {
        id: string;
        name: string;
        avatar: string;
    };
}

export interface ThreadMessage {
    id: string;
    conversation_id: string;
    thread_id: string;
    sender_id: string;
    reply_to_id: string;
    type: string;
    decrypted_content?: string;
    encrypted_content: string;
    created_at: string;
    sender: {
        id: string;
        name: string;
        avatar: string;
    };
    reactions?: Array<{
        id: string;
        user_id: string;
        emoji: string;
        user: {
            id: string;
            name: string;
        };
    }>;
}

interface UseThreadingOptions {
    conversationId: string;
}

interface UseThreadingReturn {
    // State
    threads: Thread[];
    currentThread: Thread | null;
    threadMessages: ThreadMessage[];
    isLoading: boolean;
    isLoadingMessages: boolean;
    error: string | null;

    // Thread management
    loadThreads: () => Promise<void>;
    createThread: (parentMessageId: string, options?: CreateThreadOptions) => Promise<Thread>;
    loadThread: (threadId: string) => Promise<void>;
    updateThread: (threadId: string, updates: Partial<Thread>) => Promise<void>;
    deleteThread: (threadId: string) => Promise<void>;

    // Thread participation
    joinThread: (threadId: string) => Promise<void>;
    leaveThread: (threadId: string) => Promise<void>;
    updateNotificationSettings: (threadId: string, settings: Partial<ThreadParticipant['notification_settings']>) => Promise<void>;

    // Messages in thread
    loadThreadMessages: (threadId: string, options?: LoadMessagesOptions) => Promise<void>;
    sendThreadMessage: (threadId: string, content: string, type?: string) => Promise<void>;
    markThreadAsRead: (threadId: string) => Promise<void>;

    // Thread summary
    getThreadSummary: (parentMessageId: string) => Promise<ThreadSummary>;
}

interface CreateThreadOptions {
    title?: string;
    initialMessage?: string;
}

interface LoadMessagesOptions {
    limit?: number;
    before_id?: string;
    after_id?: string;
}

interface ThreadSummary {
    thread_stats: {
        total_replies: number;
        unique_participants: number;
        last_reply_at: string;
        unread_count: number;
    };
    recent_participants: Array<{
        id: string;
        name: string;
        avatar: string;
    }>;
    latest_replies_preview: Array<{
        id: string;
        sender: {
            id: string;
            name: string;
            avatar: string;
        };
        type: string;
        created_at: string;
    }>;
}

export const useThreading = ({ conversationId }: UseThreadingOptions): UseThreadingReturn => {
    const [threads, setThreads] = useState<Thread[]>([]);
    const [currentThread, setCurrentThread] = useState<Thread | null>(null);
    const [threadMessages, setThreadMessages] = useState<ThreadMessage[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMessages, setIsLoadingMessages] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // API call wrapper
    const apiCall = useCallback(async (url: string, options: RequestInit = {}): Promise<any> => {
        try {
            const fullUrl = `/api/v1/chat/conversations/${conversationId}${url}`;
            const method = (options.method || 'GET').toUpperCase();
            const headers = {
                'X-Device-Fingerprint': getDeviceFingerprint(),
                ...(options.headers as Record<string, string> | undefined),
            };

            switch (method) {
                case 'GET':
                    return await apiService.get(fullUrl, { headers });
                case 'POST':
                    return await apiService.post(fullUrl, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
                case 'PUT':
                    return await apiService.put(fullUrl, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
                case 'PATCH':
                    return await apiService.patch(fullUrl, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
                case 'DELETE':
                    return await apiService.delete(fullUrl, { headers });
                default:
                    return await apiService.request(fullUrl, { ...options, headers });
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
            throw err;
        }
    }, [conversationId]);

    // Load all threads in conversation
    const loadThreads = useCallback(async (): Promise<void> => {
        setIsLoading(true);
        try {
            const response = await apiCall('/threads');
            setThreads(response.threads);
            setError(null);
        } catch (err) {
            console.error('Failed to load threads:', err);
        } finally {
            setIsLoading(false);
        }
    }, [apiCall]);

    // Create new thread
    const createThread = useCallback(async (
        parentMessageId: string,
        options: CreateThreadOptions = {}
    ): Promise<Thread> => {
        setIsLoading(true);
        try {
            const response = await apiCall('/threads', {
                method: 'POST',
                body: JSON.stringify({
                    parent_message_id: parentMessageId,
                    title: options.title,
                    initial_message: options.initialMessage,
                }),
            });

            const newThread = response.thread;
            setThreads(prev => [newThread, ...prev]);
            setError(null);

            return newThread;
        } catch (err) {
            console.error('Failed to create thread:', err);
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, [apiCall]);

    // Load specific thread with messages
    const loadThread = useCallback(async (threadId: string): Promise<void> => {
        setIsLoadingMessages(true);
        try {
            const response = await apiCall(`/threads/${threadId}`);
            setCurrentThread(response.thread);
            setThreadMessages(response.messages);
            setError(null);
        } catch (err) {
            console.error('Failed to load thread:', err);
        } finally {
            setIsLoadingMessages(false);
        }
    }, [apiCall]);

    // Update thread
    const updateThread = useCallback(async (
        threadId: string,
        updates: Partial<Thread>
    ): Promise<void> => {
        try {
            const response = await apiCall(`/threads/${threadId}`, {
                method: 'PATCH',
                body: JSON.stringify(updates),
            });

            const updatedThread = response.thread;
            setThreads(prev => prev.map(t => t.id === threadId ? updatedThread : t));

            if (currentThread?.id === threadId) {
                setCurrentThread(updatedThread);
            }

            setError(null);
        } catch (err) {
            console.error('Failed to update thread:', err);
            throw err;
        }
    }, [apiCall, currentThread]);

    // Delete thread
    const deleteThread = useCallback(async (threadId: string): Promise<void> => {
        try {
            await apiCall(`/threads/${threadId}`, {
                method: 'DELETE',
            });

            setThreads(prev => prev.filter(t => t.id !== threadId));

            if (currentThread?.id === threadId) {
                setCurrentThread(null);
                setThreadMessages([]);
            }

            setError(null);
        } catch (err) {
            console.error('Failed to delete thread:', err);
            throw err;
        }
    }, [apiCall, currentThread]);

    // Join thread
    const joinThread = useCallback(async (threadId: string): Promise<void> => {
        try {
            const response = await apiCall(`/threads/${threadId}/join`, {
                method: 'POST',
            });

            // Update thread participant count
            setThreads(prev => prev.map(t =>
                t.id === threadId
                    ? { ...t, participant_count: response.participant_count }
                    : t
            ));

            setError(null);
        } catch (err) {
            console.error('Failed to join thread:', err);
            throw err;
        }
    }, [apiCall]);

    // Leave thread
    const leaveThread = useCallback(async (threadId: string): Promise<void> => {
        try {
            const response = await apiCall(`/threads/${threadId}/leave`, {
                method: 'POST',
            });

            setThreads(prev => prev.map(t =>
                t.id === threadId
                    ? { ...t, participant_count: response.participant_count }
                    : t
            ));

            setError(null);
        } catch (err) {
            console.error('Failed to leave thread:', err);
            throw err;
        }
    }, [apiCall]);

    // Update notification settings
    const updateNotificationSettings = useCallback(async (
        threadId: string,
        settings: Partial<ThreadParticipant['notification_settings']>
    ): Promise<void> => {
        try {
            const response = await apiCall(`/threads/${threadId}/notifications`, {
                method: 'PATCH',
                body: JSON.stringify(settings),
            });

            setError(null);
        } catch (err) {
            console.error('Failed to update notification settings:', err);
            throw err;
        }
    }, [apiCall]);

    // Load thread messages
    const loadThreadMessages = useCallback(async (
        threadId: string,
        options: LoadMessagesOptions = {}
    ): Promise<void> => {
        setIsLoadingMessages(true);
        try {
            const params = new URLSearchParams();
            if (options.limit) params.set('limit', options.limit.toString());
            if (options.before_id) params.set('before_id', options.before_id);
            if (options.after_id) params.set('after_id', options.after_id);

            const url = `/threads/${threadId}${params.toString() ? '?' + params.toString() : ''}`;
            const response = await apiCall(url);

            if (options.before_id) {
                // Prepending older messages
                setThreadMessages(prev => [...response.messages, ...prev]);
            } else {
                setThreadMessages(response.messages);
            }

            setError(null);
        } catch (err) {
            console.error('Failed to load thread messages:', err);
        } finally {
            setIsLoadingMessages(false);
        }
    }, [apiCall]);

    // Send message to thread
    const sendThreadMessage = useCallback(async (
        threadId: string,
        content: string,
        type: string = 'text'
    ): Promise<void> => {
        try {
            const response = await apiCall(`/threads/${threadId}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    content,
                    type,
                }),
            });

            const newMessage = response.message;
            setThreadMessages(prev => [...prev, newMessage]);

            // Update thread stats
            setThreads(prev => prev.map(t =>
                t.id === threadId
                    ? {
                        ...t,
                        message_count: t.message_count + 1,
                        last_message_at: newMessage.created_at,
                        last_message_id: newMessage.id
                    }
                    : t
            ));

            setError(null);
        } catch (err) {
            console.error('Failed to send thread message:', err);
            throw err;
        }
    }, [apiCall]);

    // Mark thread as read
    const markThreadAsRead = useCallback(async (threadId: string): Promise<void> => {
        try {
            await apiCall(`/threads/${threadId}/read`, {
                method: 'POST',
            });

            setError(null);
        } catch (err) {
            console.error('Failed to mark thread as read:', err);
            throw err;
        }
    }, [apiCall]);

    // Get thread summary
    const getThreadSummary = useCallback(async (parentMessageId: string): Promise<ThreadSummary> => {
        try {
            const response = await apiCall(`/messages/${parentMessageId}/thread/summary`);
            return response;
        } catch (err) {
            console.error('Failed to get thread summary:', err);
            throw err;
        }
    }, [apiCall]);

    return {
        // State
        threads,
        currentThread,
        threadMessages,
        isLoading,
        isLoadingMessages,
        error,

        // Thread management
        loadThreads,
        createThread,
        loadThread,
        updateThread,
        deleteThread,

        // Thread participation
        joinThread,
        leaveThread,
        updateNotificationSettings,

        // Messages
        loadThreadMessages,
        sendThreadMessage,
        markThreadAsRead,

        // Summary
        getThreadSummary,
    };
};

// Helper function to get device fingerprint
function getDeviceFingerprint(): string {
    let fingerprint = getUserStorageItem('device_fingerprint');
    if (!fingerprint) {
        fingerprint = generateDeviceFingerprint();
        setUserStorageItem('device_fingerprint', fingerprint);
    }
    return fingerprint;
}

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
