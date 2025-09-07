import { useState, useEffect, useCallback } from 'react';
import { apiService } from '@/services/ApiService';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';

interface MentionUser {
  id: string;
  name: string;
  avatar?: string;
  email?: string;
  online?: boolean;
  device_status?: {
    quantum_ready: boolean;
    last_seen: string;
    encryption_preference: string;
  };
  roles?: string[];
  permissions?: string[];
}

interface MentionChannel {
  id: string;
  name: string;
  type: 'public' | 'private';
  memberCount?: number;
  encryption_enabled?: boolean;
  quantum_ready?: boolean;
}

interface MentionGroup {
  id: string;
  name: string;
  description?: string;
  memberCount: number;
  type: 'team' | 'department' | 'project';
}

interface EncryptedMention {
  mention_id: string;
  user_id: string;
  encrypted_content: string;
  content_hash: string;
  notification_sent: boolean;
  quantum_encrypted: boolean;
}

interface UseMentionsOptions {
  conversationId?: string;
  organizationId?: string;
  enableQuantumEncryption?: boolean;
}

export const useMentions = ({
  conversationId,
  organizationId,
  enableQuantumEncryption = true
}: UseMentionsOptions = {}) => {
  const [users, setUsers] = useState<MentionUser[]>([]);
  const [channels, setChannels] = useState<MentionChannel[]>([]);
  const [groups, setGroups] = useState<MentionGroup[]>([]);
  const [loading, setLoading] = useState(false);
  const [recentMentions, setRecentMentions] = useState<MentionUser[]>([]);

  useEffect(() => {
    fetchMentionData();
  }, [conversationId, organizationId]);

  // API call wrapper with device fingerprint
  const apiCall = useCallback(async (url: string, options: RequestInit = {}): Promise<any> => {
    const deviceFingerprint = getDeviceFingerprint();
    const method = (options.method || 'GET').toUpperCase();
    const headers = {
      'X-Device-Fingerprint': deviceFingerprint,
      ...(options.headers as Record<string, string> | undefined),
    };

    switch (method) {
      case 'GET':
        return await apiService.get(url, { headers });
      case 'POST':
        return await apiService.post(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
      case 'PUT':
        return await apiService.put(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
      case 'PATCH':
        return await apiService.patch(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
      case 'DELETE':
        return await apiService.delete(url, { headers });
      default:
        return await apiService.request(url, { ...options, headers });
    }
  }, []);

  const fetchMentionData = useCallback(async () => {
    setLoading(true);

    try {
      const params = new URLSearchParams();
      if (conversationId) params.set('conversation_id', conversationId);
      if (organizationId) params.set('organization_id', organizationId);
      if (enableQuantumEncryption) params.set('include_quantum_status', 'true');

      // Fetch mentionable users
      const usersResponse = await apiCall(`/api/v1/mentions/users?${params.toString()}`);
      setUsers(usersResponse.users || []);

      // Fetch mentionable channels
      const channelsResponse = await apiCall(`/api/v1/mentions/channels?${params.toString()}`);
      setChannels(channelsResponse.channels || []);

      // Fetch mentionable groups
      const groupsResponse = await apiCall(`/api/v1/mentions/groups?${params.toString()}`);
      setGroups(groupsResponse.groups || []);

      // Fetch recent mentions for this user
      const recentResponse = await apiCall(`/api/v1/mentions/recent?${params.toString()}`);
      setRecentMentions(recentResponse.recent_mentions || []);

    } catch (error) {
      console.error('Failed to fetch mention data:', error);
      // Fallback data
      setUsers([
        {
          id: '1',
          name: 'John Doe',
          email: 'john@example.com',
          online: true,
          device_status: {
            quantum_ready: true,
            last_seen: new Date().toISOString(),
            encryption_preference: 'quantum'
          }
        },
        {
          id: '2',
          name: 'Jane Smith',
          email: 'jane@example.com',
          online: false,
          device_status: {
            quantum_ready: false,
            last_seen: new Date().toISOString(),
            encryption_preference: 'classical'
          }
        },
      ]);
      setChannels([
        { id: '1', name: 'general', type: 'public', memberCount: 150, encryption_enabled: true, quantum_ready: true },
        { id: '2', name: 'development', type: 'public', memberCount: 25, encryption_enabled: true, quantum_ready: false },
      ]);
    } finally {
      setLoading(false);
    }
  }, [conversationId, organizationId, enableQuantumEncryption, apiCall]);

  // Create encrypted mention notification
  const createEncryptedMention = useCallback(async (
    userId: string,
    messageContent: string,
    mentionType: 'user' | 'channel' | 'group' = 'user'
  ): Promise<EncryptedMention | null> => {
    try {
      const response = await apiCall('/api/v1/mentions/create', {
        method: 'POST',
        body: JSON.stringify({
          user_id: userId,
          conversation_id: conversationId,
          message_content: messageContent,
          mention_type: mentionType,
          enable_quantum: enableQuantumEncryption,
        }),
      });

      return response.encrypted_mention;
    } catch (error) {
      console.error('Failed to create encrypted mention:', error);
      return null;
    }
  }, [conversationId, enableQuantumEncryption, apiCall]);

  // Search mentions with encryption support
  const searchMentions = useCallback(async (
    query: string,
    type: 'users' | 'channels' | 'groups' | 'all' = 'all'
  ): Promise<{
    users: MentionUser[];
    channels: MentionChannel[];
    groups: MentionGroup[];
  }> => {
    try {
      const params = new URLSearchParams();
      params.set('q', query);
      params.set('type', type);
      if (conversationId) params.set('conversation_id', conversationId);
      if (organizationId) params.set('organization_id', organizationId);
      if (enableQuantumEncryption) params.set('include_quantum_status', 'true');

      const response = await apiCall(`/api/v1/mentions/search?${params.toString()}`);

      return {
        users: response.users || [],
        channels: response.channels || [],
        groups: response.groups || [],
      };
    } catch (error) {
      console.error('Failed to search mentions:', error);
      return { users: [], channels: [], groups: [] };
    }
  }, [conversationId, organizationId, enableQuantumEncryption, apiCall]);

  // Mark mention as read with encryption
  const markMentionAsRead = useCallback(async (mentionId: string): Promise<void> => {
    try {
      await apiCall(`/api/v1/mentions/${mentionId}/read`, {
        method: 'POST',
      });
    } catch (error) {
      console.error('Failed to mark mention as read:', error);
    }
  }, [apiCall]);

  // Get quantum-ready users for secure mentions
  const getQuantumReadyUsers = useCallback((): MentionUser[] => {
    return users.filter(user => user.device_status?.quantum_ready === true);
  }, [users]);

  // Get mention statistics
  const getMentionStats = useCallback(async (): Promise<{
    total_mentions: number;
    unread_mentions: number;
    quantum_mentions: number;
    recent_activity: Array<{
      user: MentionUser;
      timestamp: string;
      message_preview: string;
    }>;
  }> => {
    try {
      const params = new URLSearchParams();
      if (conversationId) params.set('conversation_id', conversationId);
      if (organizationId) params.set('organization_id', organizationId);

      const response = await apiCall(`/api/v1/mentions/stats?${params.toString()}`);
      return response.stats;
    } catch (error) {
      console.error('Failed to get mention stats:', error);
      return {
        total_mentions: 0,
        unread_mentions: 0,
        quantum_mentions: 0,
        recent_activity: [],
      };
    }
  }, [conversationId, organizationId, apiCall]);

  return {
    // Data
    users,
    channels,
    groups,
    recentMentions,
    loading,

    // Actions
    refresh: fetchMentionData,
    createEncryptedMention,
    searchMentions,
    markMentionAsRead,
    getMentionStats,

    // Quantum-specific
    getQuantumReadyUsers,
    quantumEnabled: enableQuantumEncryption,
  };
};

// Helper functions
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
