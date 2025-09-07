import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeLocalStorage, onUserLogin } from './utils/localStorage';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

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
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
        },
    },
    authorizer: (channel: any, options: any) => {
        return {
            authorize: (socketId: string, callback: Function) => {
                console.log('Attempting to authorize channel:', channel.name, 'with socket ID:', socketId);
                console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
                
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                .then(response => {
                    console.log('Broadcasting auth response status:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Broadcasting auth failed with response:', text);
                            throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Broadcasting auth success:', data);
                    callback(null, data);
                })
                .catch(error => {
                    console.error('Broadcasting auth error:', error);
                    callback(error, null);
                });
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
    const inertiaPage = (window as any).page;
    if (inertiaPage?.props?.auth?.user?.id) {
        // User is authenticated - run login cleanup to migrate guest data
        onUserLogin(inertiaPage.props.auth.user.id);
    }
} catch (error) {
    // Silently continue if we can't access user data
}

initializeTheme();
