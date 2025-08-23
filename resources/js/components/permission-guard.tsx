import { type ReactNode } from 'react';
import { usePermissions } from '@/hooks/use-permissions';

interface PermissionGuardProps {
    children: ReactNode;
    permission?: string;
    role?: string;
    permissions?: string[];
    roles?: string[];
    requireAll?: boolean;
    fallback?: ReactNode;
}

export function PermissionGuard({
    children,
    permission,
    role,
    permissions = [],
    roles = [],
    requireAll = true,
    fallback = null,
}: PermissionGuardProps) {
    const { hasPermission, hasRole, hasAnyPermission, hasAllPermissions, hasAnyRole, hasAllRoles } = usePermissions();
    
    let hasAccess = true;
    
    // Check single permission
    if (permission && !hasPermission(permission)) {
        hasAccess = false;
    }
    
    // Check single role
    if (role && !hasRole(role)) {
        hasAccess = false;
    }
    
    // Check multiple permissions
    if (permissions.length > 0) {
        if (requireAll && !hasAllPermissions(permissions)) {
            hasAccess = false;
        } else if (!requireAll && !hasAnyPermission(permissions)) {
            hasAccess = false;
        }
    }
    
    // Check multiple roles
    if (roles.length > 0) {
        if (requireAll && !hasAllRoles(roles)) {
            hasAccess = false;
        } else if (!requireAll && !hasAnyRole(roles)) {
            hasAccess = false;
        }
    }
    
    return hasAccess ? <>{children}</> : <>{fallback}</>;
}