import { useState, useCallback, useEffect } from 'react';
import { useToast } from './useToast';
import { apiService } from '@/services/ApiService';

export interface Group {
    id: string;
    name: string;
    type: 'group' | 'channel';
    privacy: 'private' | 'public' | 'invite_only';
    username?: string;
    description?: string;
    welcome_message?: string;
    avatar_url?: string;
    member_limit?: number;
    can_members_add_others: boolean;
    require_approval_to_join: boolean;
    show_member_count: boolean;
    allow_anonymous_viewing: boolean;
    created_by_user_id: string;
    organization_id?: string;
    is_active: boolean;
    last_activity_at: string;
    last_message_at?: string;
    created_at: string;
    updated_at: string;
    creator?: any;
    activeParticipants?: GroupMember[];
    member_count?: number;
}

export interface GroupMember {
    id: string;
    conversation_id: string;
    user_id: string;
    role: 'admin' | 'moderator' | 'member';
    permissions?: string[];
    is_muted: boolean;
    has_notifications: boolean;
    joined_at: string;
    last_read_at?: string;
    user?: any;
}

export interface GroupInvitation {
    id: string;
    conversation_id: string;
    invited_by_user_id: string;
    invited_user_id?: string;
    email?: string;
    phone_number?: string;
    invitation_type: 'direct' | 'email' | 'phone' | 'link';
    status: 'pending' | 'accepted' | 'rejected' | 'expired' | 'revoked';
    role: 'admin' | 'moderator' | 'member';
    permissions?: string[];
    invitation_message?: string;
    expires_at?: string;
    created_at: string;
}

export interface GroupInviteLink {
    id: string;
    conversation_id: string;
    created_by_user_id: string;
    link_token: string;
    name?: string;
    role: 'admin' | 'moderator' | 'member';
    permissions?: string[];
    usage_limit?: number;
    usage_count: number;
    is_active: boolean;
    expires_at?: string;
    last_used_at?: string;
    created_at: string;
}

export interface CreateGroupData {
    name: string;
    type: 'group' | 'channel';
    privacy: 'private' | 'public' | 'invite_only';
    username?: string;
    description?: string;
    welcome_message?: string;
    member_limit?: number;
    can_members_add_others?: boolean;
    require_approval_to_join?: boolean;
    show_member_count?: boolean;
    allow_anonymous_viewing?: boolean;
    avatar_url?: string;
    organization_id?: string;
}

export function useGroupChat() {
    const [groups, setGroups] = useState<Group[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { toast } = useToast();

    // Fetch groups
    const fetchGroups = useCallback(async (params?: {
        privacy?: string;
        search?: string;
        organization_id?: string;
        per_page?: number;
    }) => {
        setLoading(true);
        setError(null);

        try {
            const queryParams = new URLSearchParams();
            if (params?.privacy) queryParams.append('privacy', params.privacy);
            if (params?.search) queryParams.append('search', params.search);
            if (params?.organization_id) queryParams.append('organization_id', params.organization_id);
            if (params?.per_page) queryParams.append('per_page', params.per_page.toString());

            const data = await apiService.get(`/api/v1/chat/groups?${queryParams}`);
            setGroups(data.data || data);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to fetch groups';
            setError(message);
            toast.error(message);
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Create group
    const createGroup = useCallback(async (groupData: CreateGroupData): Promise<Group | null> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post('/api/v1/chat/groups', groupData);
            const newGroup = data.group;

            setGroups(prev => [newGroup, ...prev]);
            toast.success(data.message || 'Group created successfully');

            return newGroup;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to create group';
            setError(message);
            toast.error(message);
            return null;
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Update group
    const updateGroup = useCallback(async (groupId: string, updates: Partial<CreateGroupData>): Promise<boolean> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.patch(`/api/v1/chat/groups/${groupId}`, updates);
            const updatedGroup = data.group;

            setGroups(prev => prev.map(group =>
                group.id === groupId ? updatedGroup : group
            ));

            toast.success(data.message || 'Group updated successfully');
            return true;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to update group';
            setError(message);
            toast.error(message);
            return false;
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Join group
    const joinGroup = useCallback(async (groupId: string, message?: string): Promise<boolean> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post(`/api/v1/chat/groups/${groupId}/join`, { message });
            toast.success(data.message || 'Joined group successfully');

            // Refresh groups to update membership status
            fetchGroups();
            return true;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to join group';
            setError(message);
            toast.error(message);
            return false;
        } finally {
            setLoading(false);
        }
    }, [toast, fetchGroups]);

    // Leave group
    const leaveGroup = useCallback(async (groupId: string): Promise<boolean> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post(`/api/v1/chat/groups/${groupId}/leave`);
            toast.success(data.message || 'Left group successfully');

            // Remove group from local state
            setGroups(prev => prev.filter(group => group.id !== groupId));
            return true;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to leave group';
            setError(message);
            toast.error(message);
            return false;
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Create invite link
    const createInviteLink = useCallback(async (groupId: string, linkData: {
        name?: string;
        role?: 'admin' | 'moderator' | 'member';
        permissions?: string[];
        usage_limit?: number;
        expires_at?: string;
    }): Promise<GroupInviteLink | null> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post(`/api/v1/chat/groups/${groupId}/invitations/links`, linkData);
            toast.success(data.message || 'Invite link created successfully');

            return data.invite_link;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to create invite link';
            setError(message);
            toast.error(message);
            return null;
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Join via invite link
    const joinViaInviteLink = useCallback(async (token: string): Promise<boolean> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post(`/api/v1/invite-links/join/${token}`);
            toast.success(data.message || 'Joined group successfully');

            // Refresh groups to show new membership
            fetchGroups();
            return true;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to join group via invite link';
            setError(message);
            toast.error(message);
            return false;
        } finally {
            setLoading(false);
        }
    }, [toast, fetchGroups]);

    // Fetch group members
    const fetchGroupMembers = useCallback(async (groupId: string, params?: {
        role?: string;
        search?: string;
        per_page?: number;
    }): Promise<GroupMember[]> => {
        setLoading(true);
        setError(null);

        try {
            const queryParams = new URLSearchParams();
            if (params?.role) queryParams.append('role', params.role);
            if (params?.search) queryParams.append('search', params.search);
            if (params?.per_page) queryParams.append('per_page', params.per_page.toString());

            const data = await apiService.get(`/api/v1/chat/groups/${groupId}/members?${queryParams}`);
            return data.data || data;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to fetch group members';
            setError(message);
            toast.error(message);
            return [];
        } finally {
            setLoading(false);
        }
    }, [toast]);

    // Add member to group
    const addMember = useCallback(async (groupId: string, memberData: {
        user_id: string;
        role?: 'admin' | 'moderator' | 'member';
        permissions?: string[];
    }): Promise<boolean> => {
        setLoading(true);
        setError(null);

        try {
            const data = await apiService.post(`/api/v1/chat/groups/${groupId}/members`, memberData);
            toast.success(data.message || 'Member added successfully');
            return true;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to add member';
            setError(message);
            toast.error(message);
            return false;
        } finally {
            setLoading(false);
        }
    }, [toast]);

    return {
        // State
        groups,
        loading,
        error,

        // Group operations
        fetchGroups,
        createGroup,
        updateGroup,
        joinGroup,
        leaveGroup,

        // Invite operations
        createInviteLink,
        joinViaInviteLink,

        // Member operations
        fetchGroupMembers,
        addMember,
    };
}
