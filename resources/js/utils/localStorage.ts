/**
 * Utility functions for user-specific localStorage operations
 */

import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Get the current user ID from Inertia props
 */
function getCurrentUserId(): string {
    try {
        // This is a bit of a hack since hooks can't be used in utility functions
        // We'll access the global window object that Inertia sets up
        const inertiaPage = (window as Record<string, any>).page;
        if (inertiaPage?.props?.auth?.user?.id) {
            return inertiaPage.props.auth.user.id;
        }
        
        // Fallback - throw error if no user ID available
        throw new Error('User ID not available');
    } catch (error) {
        console.error('Failed to get user ID for localStorage key:', error);
        // Return a fallback that won't conflict with user-specific keys
        return 'anonymous';
    }
}

/**
 * Generate a user-specific localStorage key
 */
export function getUserStorageKey(key: string): string {
    const userId = getCurrentUserId();
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
 * Hook-based utility for components that can use React hooks
 */
export function useUserStorage() {
    const { auth } = usePage<PageProps>().props;
    const userId = auth.user?.id;

    const getUserKey = (key: string): string => {
        if (!userId) {
            console.warn('No user ID available for localStorage key');
            return `anonymous_${key}`;
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