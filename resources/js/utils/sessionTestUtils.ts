import { route } from 'ziggy-js';

// Development utility functions for testing session management
// These should only be used for debugging and testing

export async function testSessionTermination(sessionId: string): Promise<boolean> {
    if (process.env.NODE_ENV === 'production') {
        console.warn('Session testing utilities should not be used in production');
        return false;
    }

    try {
        console.log('Testing session termination for session:', sessionId);
        
        const response = await fetch(route('security.sessions.destroy', sessionId), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        console.log('Session termination response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Session termination failed:', errorText);
            return false;
        }

        const result = await response.json();
        console.log('Session termination result:', result);
        
        return true;
    } catch (error) {
        console.error('Session termination test failed:', error);
        return false;
    }
}

export async function getCurrentSessionInfo(): Promise<any> {
    if (process.env.NODE_ENV === 'production') {
        console.warn('Session testing utilities should not be used in production');
        return null;
    }

    try {
        const response = await fetch(route('security.sessions.stats'), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            console.error('Failed to get session info:', response.status);
            return null;
        }

        const stats = await response.json();
        console.log('Current session stats:', stats);
        
        return stats;
    } catch (error) {
        console.error('Failed to get session info:', error);
        return null;
    }
}

export async function simulateSessionExpiry(): Promise<boolean> {
    if (process.env.NODE_ENV === 'production') {
        console.warn('Session testing utilities should not be used in production');
        return false;
    }

    console.log('Simulating session expiry...');
    
    // Clear CSRF token to simulate expired session
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        csrfToken.setAttribute('content', 'invalid-token');
    }

    // Try to make an API call that should fail with 419
    try {
        const response = await fetch(route('security.sessions.stats'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'invalid-token',
            },
        });

        console.log('Simulated expired session response:', response.status);
        return response.status === 419;
    } catch (error) {
        console.error('Failed to simulate session expiry:', error);
        return false;
    }
}

// Add these functions to window for easy testing in browser console
if (typeof window !== 'undefined' && process.env.NODE_ENV !== 'production') {
    (window as any).sessionTestUtils = {
        testSessionTermination,
        getCurrentSessionInfo,
        simulateSessionExpiry,
    };
    
    console.log('Session test utilities loaded. Use window.sessionTestUtils in console.');
}