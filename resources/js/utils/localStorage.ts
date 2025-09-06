/**
 * Utility functions for user-specific localStorage operations
 */

import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Get the current user ID from Inertia props
 */
function getCurrentUserId(): string | null {
    try {
        // This is a bit of a hack since hooks can't be used in utility functions
        // We'll access the global window object that Inertia sets up
        const inertiaPage = (window as unknown as Record<string, any>).page;
        if (inertiaPage?.props?.auth?.user?.id) {
            return inertiaPage.props.auth.user.id;
        }
        
        // Return null if no user ID is available (unauthenticated)
        return null;
    } catch {
        // Silently return null for unauthenticated state - this is normal
        return null;
    }
}

/**
 * Generate a user-specific localStorage key
 */
export function getUserStorageKey(key: string): string {
    const userId = getCurrentUserId();
    if (userId === null) {
        // For unauthenticated users, use a generic key without user prefix
        return `guest_${key}`;
    }
    return `user_${userId}_${key}`;
}

/**
 * Get item from localStorage with user-specific key
 */
export function getUserStorageItem(key: string): string | null {
    try {
        const userKey = getUserStorageKey(key);
        return localStorage.getItem(userKey);
    } catch (error) {
        console.error('Failed to get user storage item:', error);
        return null;
    }
}

/**
 * Set item in localStorage with user-specific key
 */
export function setUserStorageItem(key: string, value: string): void {
    try {
        const userKey = getUserStorageKey(key);
        localStorage.setItem(userKey, value);
    } catch (error) {
        console.error('Failed to set user storage item:', error);
    }
}

/**
 * Remove item from localStorage with user-specific key
 */
export function removeUserStorageItem(key: string): void {
    try {
        const userKey = getUserStorageKey(key);
        localStorage.removeItem(userKey);
    } catch (error) {
        console.error('Failed to remove user storage item:', error);
    }
}

/**
 * Clear all localStorage items for the current user
 */
export function clearUserStorage(): void {
    try {
        const userId = getCurrentUserId();
        if (userId === null) {
            // Clear guest storage for unauthenticated users
            const guestPrefix = 'guest_';
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (key && key.startsWith(guestPrefix)) {
                    localStorage.removeItem(key);
                }
            }
            return;
        }
        
        const userPrefix = `user_${userId}_`;
        
        // Get all localStorage keys and remove those that match the user prefix
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && key.startsWith(userPrefix)) {
                localStorage.removeItem(key);
            }
        }
    } catch (error) {
        console.error('Failed to clear user storage:', error);
    }
}

/**
 * Migrate data from guest storage to user storage (useful when user logs in)
 */
export function migrateGuestToUserStorage(): void {
    try {
        const userId = getCurrentUserId();
        if (userId === null) {
            // Can't migrate if user is not authenticated
            return;
        }
        
        const guestPrefix = 'guest_';
        const userPrefix = `user_${userId}_`;
        const keysToMigrate: string[] = [];
        
        // Find all guest keys
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(guestPrefix)) {
                keysToMigrate.push(key);
            }
        }
        
        // Migrate guest data to user-specific storage
        keysToMigrate.forEach(guestKey => {
            const baseKey = guestKey.substring(guestPrefix.length);
            const userKey = userPrefix + baseKey;
            const value = localStorage.getItem(guestKey);
            
            if (value !== null) {
                // Only migrate if user doesn't already have this key
                if (!localStorage.getItem(userKey)) {
                    localStorage.setItem(userKey, value);
                }
                // Remove the guest key
                localStorage.removeItem(guestKey);
            }
        });
    } catch (error) {
        console.error('Failed to migrate guest storage to user storage:', error);
    }
}

/**
 * Clean up problematic localStorage keys (legacy cleanup)
 */
export function cleanupLegacyLocalStorageKeys(): void {
    try {
        const keysToRemove: string[] = [];
        
        // Find problematic keys
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key) {
                // Remove keys with "anonymous" user ID
                if (key.includes('user_anonymous_')) {
                    keysToRemove.push(key);
                }
                // Remove legacy keys without prefixes (only specific ones we know are ours)
                else if (['device_fingerprint', 'api_token', 'notifications', 'appearance'].includes(key)) {
                    keysToRemove.push(key);
                }
            }
        }
        
        // Remove the problematic keys
        keysToRemove.forEach(key => {
            localStorage.removeItem(key);
        });
        
        if (keysToRemove.length > 0) {
            console.log(`Cleaned up ${keysToRemove.length} legacy localStorage keys:`, keysToRemove);
        }
    } catch (error) {
        console.error('Failed to clean up legacy localStorage keys:', error);
    }
}

/**
 * Initialize localStorage with proper cleanup and migration
 */
export function initializeLocalStorage(): void {
    try {
        // Clean up legacy keys first
        cleanupLegacyLocalStorageKeys();
        
        // Migrate guest data to user storage if user is authenticated
        migrateGuestToUserStorage();
    } catch (error) {
        console.error('Failed to initialize localStorage:', error);
    }
}

/**
 * Manual cleanup function - can be called from console if needed
 */
export function manualCleanupLocalStorage(): void {
    try {
        const beforeCount = localStorage.length;
        
        // Clean up problematic keys
        cleanupLegacyLocalStorageKeys();
        
        const afterCount = localStorage.length;
        const removedCount = beforeCount - afterCount;
        
        console.log(`Manual cleanup complete. Removed ${removedCount} keys. 
Before: ${beforeCount} keys, After: ${afterCount} keys`);
        
        // Show remaining keys for debugging
        const remainingKeys = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key) remainingKeys.push(key);
        }
        console.log('Remaining keys:', remainingKeys);
        
    } catch (error) {
        console.error('Failed to manually clean localStorage:', error);
    }
}

/**
 * Check if this is a fresh login session
 */
function isFreshLogin(userId: string): boolean {
    try {
        const sessionKey = `login_session_${userId}`;
        const hasSession = sessionStorage.getItem(sessionKey);
        
        if (!hasSession) {
            // Mark this as a known session
            sessionStorage.setItem(sessionKey, Date.now().toString());
            return true;
        }
        return false;
    } catch {
        return true; // Assume fresh login if we can't check
    }
}

/**
 * Cleanup function for when user logs in
 * Migrates guest data to user storage and removes old user data from other sessions
 */
export function onUserLogin(userId?: string, forceCleanup = false): void {
    try {
        // Only run full cleanup on fresh logins or when forced
        if (userId && !forceCleanup && !isFreshLogin(userId)) {
            return; // Skip cleanup for existing sessions
        }
        
        console.log('🔐 User login detected - cleaning up localStorage...');
        
        // Clean up legacy and problematic keys first
        cleanupLegacyLocalStorageKeys();
        
        // Migrate guest data to the new user's storage
        migrateGuestToUserStorage();
        
        // If we have a specific user ID, clean up any data from other users
        if (userId) {
            const currentUserPrefix = `user_${userId}_`;
            const keysToCheck: string[] = [];
            
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('user_') && !key.startsWith(currentUserPrefix)) {
                    keysToCheck.push(key);
                }
            }
            
            if (keysToCheck.length > 0) {
                console.log(`🧹 Found ${keysToCheck.length} keys from other users, cleaning up...`);
                keysToCheck.forEach(key => localStorage.removeItem(key));
            }
        }
        
        // Notify ApiService to refresh authentication
        try {
            // Dynamically import to avoid circular dependencies
            import('../services/ApiService').then(({ apiService }) => {
                apiService.refreshAuth();
            }).catch(() => {
                // Silently ignore import errors
            });
        } catch {
            // Silently ignore if ApiService is not available
        }
        
        console.log('✅ Login cleanup completed');
    } catch (error) {
        console.error('Failed to cleanup localStorage on login:', error);
    }
}

/**
 * Cleanup function for when user logs out  
 * Clears all user-specific data but preserves guest data structure
 */
export function onUserLogout(): void {
    try {
        console.log('🚪 User logout detected - clearing user data...');
        
        const userKeys: string[] = [];
        const guestKeys: string[] = [];
        const otherKeys: string[] = [];
        
        // Categorize all localStorage keys
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key) {
                if (key.startsWith('user_')) {
                    userKeys.push(key);
                } else if (key.startsWith('guest_')) {
                    guestKeys.push(key);
                } else {
                    otherKeys.push(key);
                }
            }
        }
        
        // Remove all user-specific keys
        userKeys.forEach(key => localStorage.removeItem(key));
        
        // Clean up any legacy keys that shouldn't be there
        cleanupLegacyLocalStorageKeys();
        
        // Clear session tracking to ensure fresh login detection works
        try {
            const sessionKeys = [];
            for (let i = sessionStorage.length - 1; i >= 0; i--) {
                const key = sessionStorage.key(i);
                if (key && key.startsWith('login_session_')) {
                    sessionKeys.push(key);
                }
            }
            sessionKeys.forEach(key => sessionStorage.removeItem(key));
            
            if (sessionKeys.length > 0) {
                console.log(`🗑️ Cleared ${sessionKeys.length} login session markers`);
            }
        } catch (error) {
            console.warn('Could not clear session tracking:', error);
        }
        
        // Notify ApiService to clear authentication
        try {
            // Dynamically import to avoid circular dependencies
            import('../services/ApiService').then(({ apiService }) => {
                apiService.clearAuth();
            }).catch(() => {
                // Silently ignore import errors
            });
        } catch {
            // Silently ignore if ApiService is not available
        }
        
        console.log(`🗑️ Removed ${userKeys.length} user keys, kept ${guestKeys.length} guest keys`);
        console.log('✅ Logout cleanup completed');
        
    } catch (error) {
        console.error('Failed to cleanup localStorage on logout:', error);
    }
}

/**
 * Complete localStorage reset - removes everything (use with caution)
 */
export function resetAllLocalStorage(): void {
    try {
        const beforeCount = localStorage.length;
        localStorage.clear();
        console.log(`🔄 Complete localStorage reset: removed ${beforeCount} keys`);
    } catch (error) {
        console.error('Failed to reset localStorage:', error);
    }
}

// Make cleanup functions available globally for debugging
(window as any).manualCleanupLocalStorage = manualCleanupLocalStorage;
(window as any).onUserLogin = onUserLogin;
(window as any).onUserLogout = onUserLogout;
(window as any).resetAllLocalStorage = resetAllLocalStorage;

/**
 * Hook-based utility for components that can use React hooks
 */
export function useUserStorage() {
    const { auth } = usePage<PageProps>().props;
    const userId = auth.user?.id;

    const getUserKey = (key: string): string => {
        if (!userId) {
            // For unauthenticated users, use guest prefix
            return `guest_${key}`;
        }
        return `user_${userId}_${key}`;
    };

    const getItem = (key: string): string | null => {
        try {
            const userKey = getUserKey(key);
            return localStorage.getItem(userKey);
        } catch (error) {
            console.error('Failed to get user storage item:', error);
            return null;
        }
    };

    const setItem = (key: string, value: string): void => {
        try {
            const userKey = getUserKey(key);
            localStorage.setItem(userKey, value);
        } catch (error) {
            console.error('Failed to set user storage item:', error);
        }
    };

    const removeItem = (key: string): void => {
        try {
            const userKey = getUserKey(key);
            localStorage.removeItem(userKey);
        } catch (error) {
            console.error('Failed to remove user storage item:', error);
        }
    };

    const clearUserItems = (): void => {
        try {
            if (!userId) return;
            
            const userPrefix = `user_${userId}_`;
            
            // Get all localStorage keys and remove those that match the user prefix
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (key && key.startsWith(userPrefix)) {
                    localStorage.removeItem(key);
                }
            }
        } catch (error) {
            console.error('Failed to clear user storage:', error);
        }
    };

    return {
        userId,
        getUserKey,
        getItem,
        setItem,
        removeItem,
        clearUserItems,
    };
}