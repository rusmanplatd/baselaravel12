import { useState, useEffect } from 'react';
import apiService from '@/services/ApiService';

interface User {
    id: string;
    name: string;
    email: string;
}

interface Permission {
    name: string;
    description?: string;
}

interface ProjectPermissions {
    user: User;
    permissions: string[];
    roles: string[];
    canView: boolean;
    canEdit: boolean;
    canAdmin: boolean;
    canCreateItems: boolean;
    canEditItems: boolean;
    canDeleteItems: boolean;
    canManageMembers: boolean;
    canManageFields: boolean;
    canManageViews: boolean;
    canManageWorkflows: boolean;
    canManageIterations: boolean;
}

interface UseProjectPermissionsReturn {
    permissions: ProjectPermissions | null;
    loading: boolean;
    error: string | null;
    hasPermission: (permission: string) => boolean;
    hasRole: (role: string) => boolean;
    canPerformAction: (action: string) => boolean;
    refresh: () => Promise<void>;
}

/**
 * Hook for managing and checking project-level permissions
 */
export function useProjectPermissions(projectId: string): UseProjectPermissionsReturn {
    const [permissions, setPermissions] = useState<ProjectPermissions | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchPermissions = async () => {
        if (!projectId) return;

        try {
            setLoading(true);
            setError(null);
            
            const response = await apiService.get<{
                user: User;
                permissions: string[];
                roles: string[];
            }>(`/api/v1/projects/${projectId}/permissions/current-user`);

            const data = response;
            const userPermissions: ProjectPermissions = {
                user: data.user,
                permissions: data.permissions,
                roles: data.roles,
                canView: hasPermissionInList(data.permissions, 'project.view'),
                canEdit: hasPermissionInList(data.permissions, 'project.edit'),
                canAdmin: hasPermissionInList(data.permissions, ['project.delete', 'project.settings']),
                canCreateItems: hasPermissionInList(data.permissions, 'project.item.create'),
                canEditItems: hasPermissionInList(data.permissions, 'project.item.edit'),
                canDeleteItems: hasPermissionInList(data.permissions, 'project.item.delete'),
                canManageMembers: hasPermissionInList(data.permissions, ['project.member.invite', 'project.member.edit']),
                canManageFields: hasPermissionInList(data.permissions, ['project.field.create', 'project.field.edit']),
                canManageViews: hasPermissionInList(data.permissions, ['project.view.create', 'project.view.edit']),
                canManageWorkflows: hasPermissionInList(data.permissions, ['project.workflow.create', 'project.workflow.edit']),
                canManageIterations: hasPermissionInList(data.permissions, ['project.edit', 'project.settings']),
            };

            setPermissions(userPermissions);
        } catch (err: any) {
            console.error('Failed to fetch project permissions:', err);
            setError(err.message || 'Failed to load permissions');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPermissions();
    }, [projectId]);

    const hasPermissionInList = (userPermissions: string[], requiredPermissions: string | string[]): boolean => {
        const required = Array.isArray(requiredPermissions) ? requiredPermissions : [requiredPermissions];
        return required.some(permission => userPermissions.includes(permission));
    };

    const hasPermission = (permission: string): boolean => {
        return permissions?.permissions.includes(permission) || false;
    };

    const hasRole = (role: string): boolean => {
        return permissions?.roles.includes(role) || false;
    };

    const canPerformAction = (action: string): boolean => {
        if (!permissions) return false;

        const actionPermissions: Record<string, string[]> = {
            'create-item': ['project.item.create'],
            'edit-item': ['project.item.edit'],
            'delete-item': ['project.item.delete'],
            'archive-item': ['project.item.archive'],
            'assign-item': ['project.item.assign'],
            'change-item-status': ['project.item.status'],
            'convert-item': ['project.item.convert'],
            'create-field': ['project.field.create'],
            'edit-field': ['project.field.edit'],
            'delete-field': ['project.field.delete'],
            'create-view': ['project.view.create'],
            'edit-view': ['project.view.edit'],
            'delete-view': ['project.view.delete'],
            'share-view': ['project.view.share'],
            'invite-member': ['project.member.invite'],
            'edit-member': ['project.member.edit'],
            'remove-member': ['project.member.remove'],
            'create-workflow': ['project.workflow.create'],
            'edit-workflow': ['project.workflow.edit'],
            'delete-workflow': ['project.workflow.delete'],
            'trigger-workflow': ['project.workflow.trigger'],
            'create-iteration': ['project.edit', 'project.settings'],
            'edit-iteration': ['project.edit', 'project.settings'],
            'delete-iteration': ['project.delete', 'project.settings'],
            'manage-settings': ['project.settings'],
            'export-data': ['project.export'],
        };

        const requiredPerms = actionPermissions[action];
        if (!requiredPerms) return false;

        return hasPermissionInList(permissions.permissions, requiredPerms);
    };

    const refresh = async () => {
        await fetchPermissions();
    };

    return {
        permissions,
        loading,
        error,
        hasPermission,
        hasRole,
        canPerformAction,
        refresh,
    };
}