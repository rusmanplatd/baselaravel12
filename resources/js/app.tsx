import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeLocalStorage, onUserLogin } from './utils/localStorage';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { apiService } from './services/ApiService';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    cluster: 'mt1',
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => {
        return {
            authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
                console.log('Attempting to authorize channel:', channel.name, 'with socket ID:', socketId);

                (async () => {
                    try {
                        // Get API token from ApiService
                        const token = await apiService.getAccessToken();
                        console.log('Using API token for broadcasting auth');

                        const response = await fetch('/broadcasting/auth', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new URLSearchParams({
                                socket_id: socketId,
                                channel_name: channel.name,
                            }),
                        });

                        console.log('Broadcasting auth response status:', response.status);

                        if (!response.ok) {
                            const text = await response.text();
                            console.error('Broadcasting auth failed with response:', text);
                            throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text}`);
                        }

                        const data = await response.json();
                        console.log('Broadcasting auth success:', data);
                        callback(null, data);
                    } catch (error) {
                        console.error('Broadcasting auth error:', error);
                        callback(error instanceof Error ? error : new Error(String(error)), null);
                    }
                })();
            }
        };
    },
});

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
