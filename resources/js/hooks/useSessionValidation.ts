import { useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { toast } from 'sonner';

interface UseSessionValidationOptions {
    checkInterval?: number;
    enabled?: boolean;
}

export function useSessionValidation(options: UseSessionValidationOptions = {}) {
    const { checkInterval = 60000, enabled = true } = options; // Check every minute by default

    const validateSession = useCallback(async () => {
        if (!enabled) return true;

        try {
            const response = await fetch(route('security.sessions.stats'), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            // If we get a 401 or 419 (session expired), redirect to login
            if (response.status === 401 || response.status === 419) {
                toast.error('Your session has expired. Please log in again.');
                router.visit(route('login'), { 
                    method: 'get',
                    replace: true 
                });
                return false;
            }

            // If the response is not ok but not unauthorized, there might be another issue
            if (!response.ok) {
                console.warn('Session validation failed with status:', response.status);
                return false;
            }

            const data = await response.json();
            
            // Check if the current session is still active
            if (data.current_session_id) {
                return true;
            }

            // If no current session ID, the session might have been terminated
            console.warn('No current session ID found, session might have been terminated');
            return false;

        } catch (error) {
            console.error('Session validation error:', error);
            
            // If it's a network error, don't force logout
            if (error instanceof TypeError && error.message.includes('fetch')) {
                console.warn('Network error during session validation, skipping logout');
                return false;
            }
            
            return false;
        }
    }, [enabled]);

    const handleSessionTerminated = useCallback(() => {
        toast.error('Your session has been terminated from another device.');
        router.visit(route('login'), {
            method: 'get',
            replace: true,
            onSuccess: () => {
                toast.info('Please log in again to continue.');
            }
        });
    }, []);

    // Periodic session validation
    useEffect(() => {
        if (!enabled) return;

        const interval = setInterval(async () => {
            const isValid = await validateSession();
            if (!isValid) {
                // Session is no longer valid, but let's be conservative
                // and not automatically log out unless we're sure
                console.warn('Session validation failed');
            }
        }, checkInterval);

        return () => clearInterval(interval);
    }, [validateSession, checkInterval, enabled]);

    // Listen for visibility change to validate session when page becomes visible
    useEffect(() => {
        if (!enabled) return;

        const handleVisibilityChange = () => {
            if (!document.hidden) {
                validateSession();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, [validateSession, enabled]);

    // Listen for focus events to validate session
    useEffect(() => {
        if (!enabled) return;

        const handleFocus = () => {
            validateSession();
        };

        window.addEventListener('focus', handleFocus);
        return () => window.removeEventListener('focus', handleFocus);
    }, [validateSession, enabled]);

    return {
        validateSession,
        handleSessionTerminated,
    };
}