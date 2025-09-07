import { useState, useCallback } from 'react';
import apiService, { ApiError } from '@/services/ApiService';

interface Broadcast {
    id: string;
    channel_id: string;
    created_by_user_id: string;
    message_id?: string;
    title?: string;
    content: string;
    media_attachments?: string[];
    status: 'draft' | 'scheduled' | 'sent' | 'failed';
    scheduled_at?: string;
    sent_at?: string;
    recipient_count: number;
    delivered_count: number;
    read_count: number;
    broadcast_settings?: Record<string, any>;
    created_at: string;
    updated_at: string;
    creator: {
        id: string;
        name: string;
    };
    message?: {
        id: string;
        content: string;
        created_at: string;
    };
}

interface BroadcastFilters {
    status?: string;
    from_date?: string;
    to_date?: string;
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

export function useChannelBroadcasts(channelId: string) {
    const [broadcasts, setBroadcasts] = useState<Broadcast[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });

    const handleError = useCallback((err: unknown) => {
        console.error('Broadcast operation error:', err);
        const message = err instanceof ApiError 
            ? err.message 
            : 'An error occurred';
        setError(message);
    }, []);

    // Load broadcasts for the channel
    const loadBroadcasts = useCallback(async (filters: BroadcastFilters = {}) => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams();
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.append(key, value.toString());
                }
            });

            const data = await apiService.get<PaginatedResponse<Broadcast>>(`/api/v1/chat/channels/${channelId}/broadcasts?${params}`);
            
            setBroadcasts(data.data);
            setPagination(data.meta);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [channelId, handleError]);

    // Create new broadcast
    const createBroadcast = useCallback(async (broadcastData: {
        title?: string;
        content: string;
        media_attachments?: string[];
        scheduled_at?: string;
        broadcast_settings?: Record<string, any>;
    }): Promise<Broadcast | null> => {
        setError(null);
        
        try {
            const response = await apiService.post<{broadcast: Broadcast}>(`/api/v1/chat/channels/${channelId}/broadcasts`, broadcastData);
            const newBroadcast = response.broadcast;
            
            // Add to broadcasts list if it's currently loaded
            setBroadcasts(prev => [newBroadcast, ...prev]);
            
            return newBroadcast;
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [channelId, handleError]);

    // Get single broadcast
    const getBroadcast = useCallback(async (broadcastId: string): Promise<Broadcast | null> => {
        try {
            return await apiService.get<Broadcast>(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}`);
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [channelId, handleError]);

    // Update broadcast
    const updateBroadcast = useCallback(async (broadcastId: string, updates: Partial<{
        title: string;
        content: string;
        media_attachments: string[];
        scheduled_at: string;
        broadcast_settings: Record<string, any>;
    }>): Promise<boolean> => {
        setError(null);
        
        try {
            const response = await apiService.patch<{broadcast: Broadcast}>(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}`, updates);
            const updatedBroadcast = response.broadcast;
            
            // Update in broadcasts list
            setBroadcasts(prev => 
                prev.map(broadcast => broadcast.id === broadcastId ? { ...broadcast, ...updatedBroadcast } : broadcast)
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Send broadcast immediately
    const sendBroadcast = useCallback(async (broadcastId: string): Promise<boolean> => {
        setError(null);
        
        try {
            const response = await apiService.post<{broadcast: Broadcast}>(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}/send`);
            const sentBroadcast = response.broadcast;
            
            // Update in broadcasts list
            setBroadcasts(prev => 
                prev.map(broadcast => 
                    broadcast.id === broadcastId ? { ...broadcast, ...sentBroadcast } : broadcast
                )
            );
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Duplicate broadcast
    const duplicateBroadcast = useCallback(async (broadcastId: string): Promise<Broadcast | null> => {
        setError(null);
        
        try {
            const response = await apiService.post<{broadcast: Broadcast}>(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}/duplicate`);
            const duplicatedBroadcast = response.broadcast;
            
            // Add to broadcasts list
            setBroadcasts(prev => [duplicatedBroadcast, ...prev]);
            
            return duplicatedBroadcast;
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [channelId, handleError]);

    // Delete broadcast
    const deleteBroadcast = useCallback(async (broadcastId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.delete(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}`);
            
            // Remove from broadcasts list
            setBroadcasts(prev => prev.filter(broadcast => broadcast.id !== broadcastId));
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Get broadcast analytics
    const getBroadcastAnalytics = useCallback(async (broadcastId: string): Promise<any> => {
        try {
            return await apiService.get(`/api/v1/chat/channels/${channelId}/broadcasts/${broadcastId}/analytics`);
        } catch (err) {
            handleError(err);
            return null;
        }
    }, [channelId, handleError]);

    // Load more broadcasts (pagination)
    const loadMoreBroadcasts = useCallback(async (page: number = 2) => {
        if (page > pagination.last_page || isLoading) return;
        
        setIsLoading(true);
        
        try {
            const params = new URLSearchParams({ page: page.toString(), per_page: pagination.per_page.toString() });
            const data = await apiService.get<PaginatedResponse<Broadcast>>(`/api/v1/chat/channels/${channelId}/broadcasts?${params}`);
            
            setBroadcasts(prev => [...prev, ...data.data]);
            setPagination(data.meta);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [channelId, pagination, isLoading, handleError]);

    // Get broadcasts by status
    const getBroadcastsByStatus = useCallback((status: string) => {
        return broadcasts.filter(broadcast => broadcast.status === status);
    }, [broadcasts]);

    // Get scheduled broadcasts
    const getScheduledBroadcasts = useCallback(() => {
        return broadcasts
            .filter(broadcast => broadcast.status === 'scheduled')
            .sort((a, b) => new Date(a.scheduled_at!).getTime() - new Date(b.scheduled_at!).getTime());
    }, [broadcasts]);

    // Get draft broadcasts
    const getDraftBroadcasts = useCallback(() => {
        return broadcasts.filter(broadcast => broadcast.status === 'draft');
    }, [broadcasts]);

    // Get sent broadcasts
    const getSentBroadcasts = useCallback(() => {
        return broadcasts
            .filter(broadcast => broadcast.status === 'sent')
            .sort((a, b) => new Date(b.sent_at!).getTime() - new Date(a.sent_at!).getTime());
    }, [broadcasts]);

    return {
        // State
        broadcasts,
        isLoading,
        error,
        pagination,
        
        // Actions
        loadBroadcasts,
        createBroadcast,
        getBroadcast,
        updateBroadcast,
        sendBroadcast,
        duplicateBroadcast,
        deleteBroadcast,
        getBroadcastAnalytics,
        loadMoreBroadcasts,
        
        // Filters
        getBroadcastsByStatus,
        getScheduledBroadcasts,
        getDraftBroadcasts,
        getSentBroadcasts,
        
        // Utilities
        clearError: () => setError(null),
    };
}