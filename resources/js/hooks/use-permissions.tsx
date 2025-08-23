import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

export function usePermissions() {
    const { auth } = usePage<SharedData>().props;
    
    const hasPermission = (permission: string): boolean => {
        if (!auth.user) return false;
        return auth.permissions.includes(permission);
    };
    
    const hasRole = (role: string): boolean => {
        if (!auth.user) return false;
        return auth.roles.includes(role);
    };
    
    const hasAnyPermission = (permissions: string[]): boolean => {
        if (!auth.user || !permissions.length) return false;
        return permissions.some(permission => auth.permissions.includes(permission));
    };
    
    const hasAllPermissions = (permissions: string[]): boolean => {
        if (!auth.user || !permissions.length) return false;
        return permissions.every(permission => auth.permissions.includes(permission));
    };
    
    const hasAnyRole = (roles: string[]): boolean => {
        if (!auth.user || !roles.length) return false;
        return roles.some(role => auth.roles.includes(role));
    };
    
    const hasAllRoles = (roles: string[]): boolean => {
        if (!auth.user || !roles.length) return false;
        return roles.every(role => auth.roles.includes(role));
    };
    
    const canAccess = (item: {
        permission?: string;
        role?: string;
        permissions?: string[];
        roles?: string[];
    }): boolean => {
        if (!auth.user) return false;
        
        // If no permissions/roles are defined, allow access
        if (!item.permission && !item.role && !item.permissions?.length && !item.roles?.length) {
            return true;
        }
        
        // Check single permission
        if (item.permission && !hasPermission(item.permission)) {
            return false;
        }
        
        // Check single role
        if (item.role && !hasRole(item.role)) {
            return false;
        }
        
        // Check multiple permissions (must have all)
        if (item.permissions?.length && !hasAllPermissions(item.permissions)) {
            return false;
        }
        
        // Check multiple roles (must have all)
        if (item.roles?.length && !hasAllRoles(item.roles)) {
            return false;
        }
        
        return true;
    };
    
    return {
        hasPermission,
        hasRole,
        hasAnyPermission,
        hasAllPermissions,
        hasAnyRole,
        hasAllRoles,
        canAccess,
        permissions: auth.permissions,
        roles: auth.roles,
    };
}