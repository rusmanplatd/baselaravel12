import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Fingerprint, Loader2 } from 'lucide-react';
import { startAuthentication } from '@simplewebauthn/browser';
import { router } from '@inertiajs/react';

interface WebAuthnLoginProps {
    onSuccess?: () => void;
}

export default function WebAuthnLogin({ onSuccess }: WebAuthnLoginProps) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const authenticate = async () => {
        setLoading(true);
        setError('');

        try {
            // Get authentication options
            const optionsResponse = await fetch(route('webauthn.authenticate.options'));
            const options = await optionsResponse.json();

            // Start authentication
            const credential = await startAuthentication(options);

            // Send credential to server
            const verificationResponse = await fetch(route('webauthn.authenticate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(credential),
            });

            const result = await verificationResponse.json();

            if (result.success) {
                if (onSuccess) {
                    onSuccess();
                } else {
                    // Check if MFA is required
                    if (result.requires_mfa) {
                        // User needs to complete MFA challenge
                        router.visit('/dashboard'); // This will be intercepted by MFA middleware
                    } else {
                        // Direct login to dashboard
                        router.visit(result.redirect_url || '/dashboard');
                    }
                }
            } else {
                setError(result.error || 'Authentication failed');
            }
        } catch (err) {
            if (err instanceof Error) {
                if (err.name === 'NotAllowedError') {
                    setError('Authentication was cancelled or not allowed');
                } else if (err.name === 'InvalidStateError') {
                    setError('No security key found. Please try again or use a different authentication method.');
                } else {
                    setError('Authentication failed. Please try again.');
                }
            } else {
                setError('Authentication failed. Please try again.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card>
            <CardHeader className="text-center">
                <CardTitle className="flex items-center justify-center gap-2">
                    <Fingerprint className="h-6 w-6" />
                    Sign in with Security Key
                </CardTitle>
                <CardDescription>
                    Use your security key, biometric authentication, or platform authenticator to sign in
                </CardDescription>
            </CardHeader>
            <CardContent>
                {error && (
                    <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md mb-4">
                        {error}
                    </div>
                )}

                <Button 
                    onClick={authenticate} 
                    disabled={loading}
                    className="w-full"
                    size="lg"
                >
                    {loading ? (
                        <>
                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                            Authenticating...
                        </>
                    ) : (
                        <>
                            <Fingerprint className="h-4 w-4 mr-2" />
                            Authenticate with Security Key
                        </>
                    )}
                </Button>

                <div className="text-center text-sm text-muted-foreground mt-4">
                    Touch your security key or use your device's biometric authentication when prompted
                </div>
            </CardContent>
        </Card>
    );
}