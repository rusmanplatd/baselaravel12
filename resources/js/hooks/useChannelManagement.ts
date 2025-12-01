import { useState, useCallback } from 'react';
import apiService, { ApiError } from '@/services/ApiService';

interface ChannelAdmin {
    id: string;
    name: string;
    email: string;
    role: string;
    permissions: string[];
    joined_at: string;
    is_owner: boolean;
}

interface BannedUser {
    id: string;
    user_id: string;
    banned_by_user_id: string;
    reason: string | null;
    banned_at: string;
    expires_at: string | null;
    is_permanent: boolean;
    user: {
        id: string;
        name: string;
        email: string;
    };
    banned_by: {
        id: string;
        name: string;
    };
}

interface ChannelStatistics {
    total_views: number;
    unique_views: number;
    new_subscribers: number;
    unsubscribes: number;
    shares: number;
    messages_sent: number;
    net_subscribers: number;
    engagement_rate: number;
    daily_breakdown: Array<{
        date: string;
        views: number;
        unique_views: number;
        new_subscribers: number;
        unsubscribes: number;
    }>;
}

export function useChannelManagement(channelId: string) {
    const [statistics, setStatistics] = useState<ChannelStatistics | null>(null);
    const [admins, setAdmins] = useState<ChannelAdmin[]>([]);
    const [bannedUsers, setBannedUsers] = useState<BannedUser[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleError = useCallback((err: unknown) => {
        console.error('Channel management error:', err);
        const message = err instanceof ApiError 
            ? err.message 
            : 'An error occurred';
        setError(message);
    }, []);

    // Load channel statistics
    const loadStatistics = useCallback(async (period: string = 'week') => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams({ period });
            const data = await apiService.get<ChannelStatistics>(`/api/v1/chat/channels/${channelId}/statistics?${params}`);
            setStatistics(data);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [channelId, handleError]);

    // Load channel admins
    const loadAdmins = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            const data = await apiService.get<ChannelAdmin[]>(`/api/v1/chat/channels/${channelId}/manage/admins`);
            setAdmins(data);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [channelId, handleError]);

    // Load banned users
    const loadBannedUsers = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            const response = await apiService.get<{data: BannedUser[]}>(`/api/v1/chat/channels/${channelId}/manage/banned-users`);
            setBannedUsers(response.data || []);
        } catch (err) {
            handleError(err);
        } finally {
            setIsLoading(false);
        }
    }, [channelId, handleError]);

    // Add admin
    const addAdmin = useCallback(async (userId: string, permissions?: string[]): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.post(`/api/v1/chat/channels/${channelId}/manage/add-admin`, {
                user_id: userId,
                permissions,
            });
            
            // Reload admins list
            await loadAdmins();
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError, loadAdmins]);

    // Remove admin
    const removeAdmin = useCallback(async (userId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.delete(`/api/v1/chat/channels/${channelId}/manage/remove-admin/${userId}`);
            
            // Reload admins list
            await loadAdmins();
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError, loadAdmins]);

    // Ban user
    const banUser = useCallback(async (
        userId: string, 
        reason?: string, 
        duration?: string
    ): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.post(`/api/v1/chat/channels/${channelId}/manage/ban-user`, {
                user_id: userId,
                reason,
                duration,
            });
            
            // Reload banned users list
            await loadBannedUsers();
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError, loadBannedUsers]);

    // Unban user
    const unbanUser = useCallback(async (userId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.delete(`/api/v1/chat/channels/${channelId}/manage/unban-user/${userId}`);
            
            // Reload banned users list
            await loadBannedUsers();
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError, loadBannedUsers]);

    // Update channel settings
    const updateChannelSettings = useCallback(async (settings: Record<string, unknown>): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.patch(`/api/v1/chat/channels/${channelId}/manage/settings`, {
                channel_settings: settings,
            });
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Transfer ownership
    const transferOwnership = useCallback(async (newOwnerId: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.post(`/api/v1/chat/channels/${channelId}/manage/transfer-ownership`, {
                new_owner_id: newOwnerId,
                confirmation: 'TRANSFER',
            });
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Delete channel
    const deleteChannel = useCallback(async (reason?: string): Promise<boolean> => {
        setError(null);
        
        try {
            await apiService.delete(`/api/v1/chat/channels/${channelId}/manage/delete`, {
                body: JSON.stringify({
                    confirmation: 'DELETE',
                    reason,
                }),
            });
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Export channel data
    const exportChannelData = useCallback(async (): Promise<boolean> => {
        setError(null);
        
        try {
            const data = await apiService.get(`/api/v1/chat/channels/${channelId}/manage/export`);
            
            // Create and download the export file
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json',
            });
            
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `channel-${channelId}-export-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            return true;
        } catch (err) {
            handleError(err);
            return false;
        }
    }, [channelId, handleError]);

    // Get channel subscribers
    const loadSubscribers = useCallback(async (page: number = 1) => {
        setIsLoading(true);
        setError(null);
        
        try {
            const params = new URLSearchParams({ page: page.toString(), per_page: '50' });
            return await apiService.get(`/api/v1/chat/channels/${channelId}/subscribers?${params}`);
        } catch (err) {
            handleError(err);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [channelId, handleError]);

    return {
        // State
        statistics,
        admins,
        bannedUsers,
        isLoading,
        error,
        
        // Actions
        loadStatistics,
        loadAdmins,
        loadBannedUsers,
        loadSubscribers,
        addAdmin,
        removeAdmin,
        banUser,
        unbanUser,
        updateChannelSettings,
        transferOwnership,
        deleteChannel,
        exportChannelData,
        
        // Utilities
        clearError: () => setError(null),
    };
}