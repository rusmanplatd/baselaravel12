import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeLocalStorage, onUserLogin } from './utils/localStorage';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

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
