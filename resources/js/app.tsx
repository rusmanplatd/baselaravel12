import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeLocalStorage, onUserLogin } from './utils/localStorage';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { apiService } from './services/ApiService';
// import './utils/websocket-test';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

window.Pusher = Pusher;

console.log('🚀 Initializing Echo WebSocket connection...');
console.log('🔧 Echo configuration:', {
    broadcaster: 'pusher',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'mtcuqmpo8foqru6zke2c',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
});

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'mtcuqmpo8foqru6zke2c',
    cluster: 'mt1',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    authorizer: (channel: { name: string }) => {
        return {
            authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
                console.log('🔐 Attempting to authorize channel:', channel.name, 'with socket ID:', socketId);

                (async () => {
                    try {
                        // Get API token from ApiService
                        const token = await apiService.getAccessToken();
                        console.log('🔑 Using API token for broadcasting auth:', token ? 'Token available' : 'No token');

                        const response = await fetch('/broadcasting/auth', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                            credentials: 'same-origin',
                            body: new URLSearchParams({
                                socket_id: socketId,
                                channel_name: channel.name,
                            }),
                        });

                        console.log('📡 Broadcasting auth response status:', response.status);

                        if (!response.ok) {
                            const text = await response.text();
                            console.error('🚨 Broadcasting auth failed with response:', text);

                            // Handle 403 errors gracefully (user doesn't have access to conversation)
                            if (response.status === 403) {
                                console.warn('Access denied to conversation channel. User may not have permission to access this conversation.');
                                // Don't throw the error, just call the callback with null to prevent subscription
                                callback(null, null);
                                return;
                            }

                            throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text}`);
                        }

                        const data = await response.json();
                        console.log('✅ Broadcasting auth success:', data);
                        callback(null, data);
                    } catch (error) {
                        console.error('🚨 Broadcasting auth error:', error);
                        callback(error instanceof Error ? error : new Error(String(error)), null);
                    }
                })();
            }
        };
    },
});

// Add comprehensive connection status monitoring
setTimeout(() => {
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        const pusher = window.Echo.connector.pusher;

        console.log('🔌 Initializing WebSocket connection monitoring...');
        console.log('🔌 Current connection state:', pusher.connection.state);

        pusher.connection.bind('connected', () => {
            console.log('✅ WebSocket connected successfully!');
            console.log('🔌 Connection ID:', pusher.connection.socket_id);
        });

        pusher.connection.bind('disconnected', () => {
            console.log('❌ WebSocket disconnected');
        });

        pusher.connection.bind('error', (error: any) => {
            console.error('🚨 WebSocket connection error:', error);
        });

        pusher.connection.bind('state_change', (states: any) => {
            console.log('🔄 WebSocket state changed:', states.previous, '->', states.current);
        });

        pusher.connection.bind('unavailable', () => {
            console.warn('⚠️ WebSocket connection unavailable');
        });

        // Log when channels are subscribed/unsubscribed
        const originalSubscribe = pusher.subscribe.bind(pusher);
        pusher.subscribe = function(channelName: string) {
            console.log('📡 Subscribing to channel:', channelName);
            return originalSubscribe(channelName);
        };

        const originalUnsubscribe = pusher.unsubscribe.bind(pusher);
        pusher.unsubscribe = function(channelName: string) {
            console.log('📡 Unsubscribing from channel:', channelName);
            return originalUnsubscribe(channelName);
        };

        // Log when events are received (using global event binding)
        const originalBind = pusher.bind.bind(pusher);
        pusher.bind = function(eventName: string, callback: any, context?: any) {
            if (eventName !== 'pusher:ping' && eventName !== 'pusher:pong') {
                console.log('📨 WebSocket event binding:', eventName);
            }
            return originalBind(eventName, callback, context);
        };

        console.log('🔌 WebSocket monitoring initialized successfully');

        // Log WebSocket URL for Network tab debugging
        console.log('🌐 WebSocket URL:', pusher.connection.url);
        console.log('🔗 WebSocket Protocol:', pusher.connection.protocol);

        // Monitor Echo events
        const originalEchoListen = window.Echo.listen.bind(window.Echo);
        window.Echo.listen = function(channel: string, event: string, callback: any) {
            console.log('📡 Echo listening to:', channel, 'event:', event);
            return originalEchoListen(channel, event, callback);
        };

        const originalEchoPrivate = window.Echo.private.bind(window.Echo);
        window.Echo.private = function(channel: string) {
            console.log('🔐 Echo private channel:', channel);
            return originalEchoPrivate(channel);
        };

        // Test the connection by subscribing to a test channel
        setTimeout(() => {
            console.log('🧪 Testing WebSocket connection...');
            const testChannel = pusher.subscribe('test-channel');
            testChannel.bind('test-event', (data: any) => {
                console.log('🧪 Test event received:', data);
            });

            // Unsubscribe after 5 seconds
            setTimeout(() => {
                pusher.unsubscribe('test-channel');
                console.log('🧪 Test channel unsubscribed');
            }, 5000);
        }, 2000);

    } else {
        console.error('🚨 Failed to initialize WebSocket monitoring - Echo not available');
    }
}, 1000);

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// Initialize localStorage and theme on load...
initializeLocalStorage();

// Check if user is authenticated and run login cleanup if needed
try {
    const inertiaPage = (window as { page?: { props?: { auth?: { user?: { id: string } } } } }).page;
    if (inertiaPage?.props?.auth?.user?.id) {
        // User is authenticated - run login cleanup to migrate guest data
        onUserLogin(inertiaPage.props.auth.user.id);
    }
} catch (error) {
    console.error('Error checking user authentication status:', error);
    // Silently continue if we can't access user data
}

initializeTheme();
