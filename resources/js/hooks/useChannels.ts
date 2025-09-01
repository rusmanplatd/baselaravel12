import { useState, useEffect, useCallback } from 'react';
import { Channel, ChannelInviteResponse, ChannelMember } from '@/types/chat';
import { apiService } from '@/services/ApiService';

interface UseChannelsOptions {
  organizationId?: string;
  autoFetch?: boolean;
}

interface UseChannelsReturn {
  channels: Channel[];
  loading: boolean;
  error: string | null;
  searchResults: Channel[];
  searchLoading: boolean;
  fetchChannels: () => Promise<void>;
  searchChannels: (query: string, organizationId?: string) => Promise<void>;
  createChannel: (data: {
    name: string;
    description?: string;
    visibility: 'public' | 'private';
    organization_id?: string;
  }) => Promise<Channel>;
  updateChannel: (channelId: string, data: {
    name?: string;
    description?: string;
    visibility?: 'public' | 'private';
  }) => Promise<Channel>;
  deleteChannel: (channelId: string) => Promise<void>;
  joinChannel: (channelId: string) => Promise<void>;
  leaveChannel: (channelId: string) => Promise<void>;
  inviteUsers: (channelId: string, userIds: string[]) => Promise<ChannelInviteResponse>;
  getChannelMembers: (channelId: string) => Promise<ChannelMember[]>;
  clearError: () => void;
}

export function useChannels(options: UseChannelsOptions = {}): UseChannelsReturn {
  const { organizationId, autoFetch = true } = options;
  const [channels, setChannels] = useState<Channel[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searchResults, setSearchResults] = useState<Channel[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const fetchChannels = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const params = new URLSearchParams();
      if (organizationId) {
        params.append('organization_id', organizationId);
      }
      params.append('user_channels', 'true');

      const response = await apiService.get<{data: Channel[]}>(`/api/v1/chat/channels?${params}`);
      setChannels(response.data || []);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch channels');
      console.error('Failed to fetch channels:', err);
    } finally {
      setLoading(false);
    }
  }, [organizationId]);

  const searchChannels = useCallback(async (query: string, orgId?: string) => {
    if (!query.trim()) {
      setSearchResults([]);
      return;
    }

    try {
      setSearchLoading(true);
      setError(null);
      
      const params = new URLSearchParams({
        query: query.trim(),
      });
      
      if (orgId || organizationId) {
        params.append('organization_id', orgId || organizationId!);
      }

      const response = await apiService.get<Channel[]>(`/api/v1/chat/channels/search?${params}`);
      setSearchResults(response || []);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to search channels');
      console.error('Failed to search channels:', err);
    } finally {
      setSearchLoading(false);
    }
  }, [organizationId]);

  const createChannel = useCallback(async (data: {
    name: string;
    description?: string;
    visibility: 'public' | 'private';
    organization_id?: string;
  }): Promise<Channel> => {
    try {
      setError(null);
      const newChannel = await apiService.post('/api/v1/chat/channels', {
        ...data,
        organization_id: data.organization_id || organizationId,
      });
      setChannels(prev => [newChannel, ...prev]);
      return newChannel;
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to create channel';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, [organizationId]);

  const updateChannel = useCallback(async (channelId: string, data: {
    name?: string;
    description?: string;
    visibility?: 'public' | 'private';
  }): Promise<Channel> => {
    try {
      setError(null);
      const updatedChannel = await apiService.put(`/api/v1/chat/channels/${channelId}`, data);
      setChannels(prev => prev.map(channel => 
        channel.id === channelId ? updatedChannel : channel
      ));
      
      return updatedChannel;
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to update channel';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, []);

  const deleteChannel = useCallback(async (channelId: string): Promise<void> => {
    try {
      setError(null);
      await apiService.delete(`/api/v1/chat/channels/${channelId}`);
      setChannels(prev => prev.filter(channel => channel.id !== channelId));
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to delete channel';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, []);

  const joinChannel = useCallback(async (channelId: string): Promise<void> => {
    try {
      setError(null);
      await apiService.post(`/api/v1/chat/channels/${channelId}/join`);
      // Refresh channels to update membership status
      await fetchChannels();
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to join channel';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, [fetchChannels]);

  const leaveChannel = useCallback(async (channelId: string): Promise<void> => {
    try {
      setError(null);
      await apiService.post(`/api/v1/chat/channels/${channelId}/leave`);
      // Remove channel from list after leaving
      setChannels(prev => prev.filter(channel => channel.id !== channelId));
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to leave channel';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, []);

  const inviteUsers = useCallback(async (channelId: string, userIds: string[]): Promise<ChannelInviteResponse> => {
    try {
      setError(null);
      return await apiService.post(`/api/v1/chat/channels/${channelId}/invite`, {
        user_ids: userIds,
      });
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to invite users';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, []);

  const getChannelMembers = useCallback(async (channelId: string): Promise<ChannelMember[]> => {
    try {
      setError(null);
      const response = await apiService.get<ChannelMember[]>(`/api/v1/chat/channels/${channelId}/members`);
      return response || [];
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || 'Failed to fetch channel members';
      setError(errorMessage);
      throw new Error(errorMessage);
    }
  }, []);

  useEffect(() => {
    if (autoFetch) {
      fetchChannels();
    }
  }, [fetchChannels, autoFetch]);

  return {
    channels,
    loading,
    error,
    searchResults,
    searchLoading,
    fetchChannels,
    searchChannels,
    createChannel,
    updateChannel,
    deleteChannel,
    joinChannel,
    leaveChannel,
    inviteUsers,
    getChannelMembers,
    clearError,
  };
}