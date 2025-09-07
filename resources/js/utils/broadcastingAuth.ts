/**
 * Broadcasting authentication utilities for API token support
 */

import { apiService } from '@/services/ApiService';

/**
 * Reconnect broadcasting with a fresh API token
 * Useful when tokens expire and need to re-authenticate
 */
export const reconnectBroadcasting = async (): Promise<void> => {
    if (!window.Echo) {
        console.warn('Echo is not initialized');
        return;
    }

    try {
        console.log('Reconnecting broadcasting with fresh token...');

        // Clear the API service token cache to force a refresh
        apiService.refreshAuth();

        // Disconnect current connection
        window.Echo.disconnect();

        // Note: Echo will automatically reconnect with the new authorizer
        // when channels are subscribed again

        console.log('Broadcasting disconnected, will reconnect on next subscription');
    } catch (error) {
        console.error('Error reconnecting broadcasting:', error);
        throw error;
    }
};

/**
 * Handle broadcasting authentication errors
 * Automatically attempts to reconnect with fresh token
 */
export const handleBroadcastingAuthError = async (error: any): Promise<void> => {
    console.error('Broadcasting authentication error:', error);

    // Check if it's an authentication error (401)
    if (error?.message?.includes('401') || error?.message?.includes('Unauthorized')) {
        console.log('Attempting to reconnect with fresh token...');

        try {
            await reconnectBroadcasting();
        } catch (reconnectError) {
            console.error('Failed to reconnect broadcasting:', reconnectError);
            // Could emit an event here for the UI to handle
        }
    } else if (error?.message?.includes('403') || error?.message?.includes('Forbidden')) {
        // Handle forbidden access (403) - user doesn't have permission to access the conversation
        console.warn('Access denied to conversation channel. User may not have permission to access this conversation.');

        // Don't throw the error, just log it and let the frontend handle it gracefully
        // This prevents the error from bubbling up and breaking the UI
        return;
    } else {
        // For other errors, just throw them
        throw error;
    }
};

/**
 * Enhanced channel subscription with automatic token refresh on auth errors
 */
export const subscribeToChannel = async (channelName: string, callbacks: {
    [eventName: string]: (data: any) => void;
}): Promise<any> => {
    if (!window.Echo) {
        throw new Error('Echo is not initialized');
    }

    try {
        const channel = window.Echo.private(channelName);

        // Add event listeners
        Object.entries(callbacks).forEach(([eventName, callback]) => {
            channel.listen(eventName, callback);
        });

        // Add error handling for authentication issues
        channel.error((error: any) => {
            handleBroadcastingAuthError(error);
        });

        return channel;
    } catch (error) {
        await handleBroadcastingAuthError(error);
        throw error;
    }
};

/**
 * Leave a channel safely
 */
export const leaveChannel = (channelName: string): void => {
    if (window.Echo) {
        window.Echo.leave(channelName);
        console.log(`Left channel: ${channelName}`);
    }
};
