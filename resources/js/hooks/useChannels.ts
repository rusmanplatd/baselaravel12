import { useState, useCallback } from 'react';
import apiService, { ApiError } from '@/services/ApiService';

interface Channel {
    id: string;
    name: string;
    username: string;
    description: string;
    category: string;
    is_verified: boolean;
    is_broadcast: boolean;
    subscriber_count: number;
    view_count: number;
    privacy: string;
    avatar_url?: string;
    created_at: string;
    is_subscribed: boolean;
    subscription_status?: string;
    unread_count?: number;
    last_message_at?: string;
    creator: {
        id: string;
        name: string;
    };
    category_info?: {
        name: string;
        icon: string;
        color: string;
    };
}

interface ChannelCategory {
    id: string;
    name: string;
    slug: string;
    description: string;
    icon: string;
    color: string;
    is_active: boolean;
    sort_order: number;
    active_channels_count: number;
    public_channels_count: number;
}

interface ChannelFilters {
    search?: string;
    category?: string;
    verified?: boolean;
    popular?: boolean;
    new?: boolean;
    subscribed?: boolean;
    owned?: boolean;
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
}

interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export function useChannels() {
    const [channels, setChannels] = useState<Channel[]>([]);
    const [categories, setCategories] = useState<ChannelCategory[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });

    const handleError = useCallback((err: unknown) => {
        console.error('Channel operation error:', err);
        const message = err instanceof ApiError 
            ? err.message 
            : 'An error occurred';
        setError(message);
    }, []);

    // Load user's channels (subscribed/owned)
    const loadUserChannels = useCallback(async (filters: ChannelFilters = {}) => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.append(key, value.toString());
                }
            });

            const data = await apiService.get<PaginatedResponse<Channel>>(`/api/v1/chat/channels?${params}`);
            
            setChannels(data.data);
            setPagination(data.meta);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [handleError]);

    // Discover public channels
    const discoverChannels = useCallback(async (filters: ChannelFilters = {}) => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.append(key, value.toString());
                }
            });

            const data = await apiService.get<PaginatedResponse<Channel>>(`/api/v1/chat/channels/discover?${params}`);
            
            setChannels(data.data);
            setPagination(data.meta);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [handleError]);

    // Load channel categories
    const loadCategories = useCallback(async () => {
        try {
            const data = await apiService.get<ChannelCategory[]>('/api/v1/chat/channels/categories');
            setCategories(data);
        } catch (err) {
            console.error('Failed to load categories:', err);
        }
    }, []);

    // Get single channel
    const getChannel = useCallback(async (channelId: string): Promise<Channel | null> => {
        try {
            return await apiService.get<Channel>(`/api/v1/chat/channels/${channelId}`);
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [handleError]);

    // Create new channel
    const createChannel = useCallback(async (channelData: {
        name: string;
        username?: string;
        description?: string;
        category?: string;
        privacy: 'public' | 'private' | 'invite_only';
        is_broadcast?: boolean;
        allow_anonymous_posts?: boolean;
        show_subscriber_count?: boolean;
        require_join_approval?: boolean;
        avatar_url?: string;
        welcome_message?: string;
        channel_settings?: Record<string, any>;
    }): Promise<Channel | null> => {
        setError(null);
        
        try {
            const response = await apiService.post<{channel: Channel}>('/api/v1/chat/channels', channelData);
            const newChannel = response.channel;
            
            // Add to channels list if it's currently loaded
            setChannels(prev => [newChannel, ...prev]);
            
            return newChannel;
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [handleError]);

    // Update channel
    const updateChannel = useCallback(async (channelId: string, updates: Partial<{
        name: string;
        username: string;
        description: string;
        category: string;
        privacy: string;
        is_broadcast: boolean;
        allow_anonymous_posts: boolean;
        show_subscriber_count: boolean;
        require_join_approval: boolean;
        avatar_url: string;
        welcome_message: string;
        channel_settings: Record<string, any>;
    }>): Promise<boolean> => {
        setError(null);
        
        try {
            const response = await apiService.patch<{channel: Channel}>(`/api/v1/chat/channels/${channelId}`, updates);
            const updatedChannel = response.channel;
            
            // Update in channels list
            setChannels(prev => 
                prev.map(ch => ch.id === channelId ? { ...ch, ...updatedChannel } : ch)
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [handleError]);

    // Subscribe to channel
    const subscribeToChannel = useCallback(async (channelId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.post(`/api/v1/chat/channels/${channelId}/subscribe`);
            
            // Update subscription status in channels list
            setChannels(prev => 
                prev.map(ch => 
                    ch.id === channelId 
                        ? { ...ch, is_subscribed: true, subscriber_count: ch.subscriber_count + 1 }
                        : ch
                )
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [handleError]);

    // Unsubscribe from channel
    const unsubscribeFromChannel = useCallback(async (channelId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.delete(`/api/v1/chat/channels/${channelId}/unsubscribe`);
            
            // Update subscription status in channels list
            setChannels(prev => 
                prev.map(ch => 
                    ch.id === channelId 
                        ? { ...ch, is_subscribed: false, subscriber_count: Math.max(0, ch.subscriber_count - 1) }
                        : ch
                )
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [handleError]);

    // Update notification settings
    const updateNotificationSettings = useCallback(async (channelId: string, settings?: {
        has_notifications?: boolean;
        is_muted?: boolean;
    }): Promise<boolean> => {
        setError(null);
        
        try {
            // This would need to be implemented in the backend
            // For now, we'll just toggle the mute status
            const channel = channels.find(ch => ch.id === channelId);
            const isMuted = channel?.subscription_status === 'muted';
            
            // Update local state immediately for better UX
            setChannels(prev => 
                prev.map(ch => 
                    ch.id === channelId 
                        ? { ...ch, subscription_status: isMuted ? 'subscribed' : 'muted' }
                        : ch
                )
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channels, handleError]);

    // Get channel statistics (admin only)
    const getChannelStatistics = useCallback(async (channelId: string, period: string = 'week'): Promise<any> => {
        try {
            const params = new URLSearchParams({ period });
            return await apiService.get(`/api/v1/chat/channels/${channelId}/statistics?${params}`);
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [handleError]);

    // Load more channels (pagination)
    const loadMoreChannels = useCallback(async (page: number = 2) => {
        if (page > pagination.last_page || isLoading) return;
        
        setIsLoading(true);
        
        try {
            const params = new URLSearchParams({ page: page.toString(), per_page: pagination.per_page.toString() });
            const data = await apiService.get<PaginatedResponse<Channel>>(`/api/v1/chat/channels/discover?${params}`);
            
            setChannels(prev => [...prev, ...data.data]);
            setPagination(data.meta);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [pagination, isLoading, handleError]);

    return {
        // State
        channels,
        categories,
        isLoading,
        error,
        pagination,
        
        // Actions
        loadUserChannels,
        discoverChannels,
        loadCategories,
        getChannel,
        createChannel,
        updateChannel,
        subscribeToChannel,
        unsubscribeFromChannel,
        updateNotificationSettings,
        getChannelStatistics,
        loadMoreChannels,
        
        // Utilities
        clearError: () => setError(null),
    };
}