import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Shield, Smartphone, Key } from 'lucide-react';
import { router } from '@inertiajs/react';

export default function MfaChallenge() {
    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [useBackupCode, setUseBackupCode] = useState(false);

    const verifyCode = async () => {
        if (!code) {
            setError('Please enter a verification code');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const response = await fetch(route('mfa.verify'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    code: code,
                }),
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to intended page or dashboard
                router.visit('/dashboard');
            } else {
                setError(result.error || 'Invalid verification code');
            }
        } catch (err) {
            setError('Verification failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            verifyCode();
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <CardTitle className="flex items-center justify-center gap-2">
                        <Shield className="h-6 w-6 text-blue-600" />
                        Two-Factor Authentication
                    </CardTitle>
                    <CardDescription>
                        Enter the verification code to complete your sign in
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {error && (
                        <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
                            {error}
                        </div>
                    )}

                    <div className="space-y-4">
                        {!useBackupCode ? (
                            <>
                                <div className="text-center">
                                    <Smartphone className="h-12 w-12 text-blue-600 mx-auto mb-2" />
                                    <p className="text-sm text-muted-foreground">
                                        Open your authenticator app and enter the 6-digit code
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="code">Verification Code</Label>
                                    <Input
                                        id="code"
                                        value={code}
                                        onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                        placeholder="000000"
                                        className="text-center text-2xl font-mono tracking-widest"
                                        onKeyPress={handleKeyPress}
                                        autoComplete="one-time-code"
                                        autoFocus
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="text-center">
                                    <Key className="h-12 w-12 text-blue-600 mx-auto mb-2" />
                                    <p className="text-sm text-muted-foreground">
                                        Enter one of your 8-digit backup codes
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="backup-code">Backup Code</Label>
                                    <Input
                                        id="backup-code"
                                        value={code}
                                        onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 8))}
                                        placeholder="00000000"
                                        className="text-center text-xl font-mono tracking-widest"
                                        onKeyPress={handleKeyPress}
                                        autoComplete="one-time-code"
                                        autoFocus
                                    />
                                </div>
                            </>
                        )}

                        <Button 
                            onClick={verifyCode} 
                            disabled={loading || !code || (!useBackupCode && code.length !== 6) || (useBackupCode && code.length !== 8)}
                            className="w-full"
                        >
                            {loading ? 'Verifying...' : 'Verify'}
                        </Button>

                        <Separator />

                        <div className="text-center">
                            <Button
                                variant="link"
                                onClick={() => {
                                    setUseBackupCode(!useBackupCode);
                                    setCode('');
                                    setError('');
                                }}
                                className="text-sm"
                            >
                                {useBackupCode 
                                    ? 'Use authenticator app instead' 
                                    : 'Use backup code instead'
                                }
                            </Button>
                        </div>

                        <div className="text-center">
                            <Button
                                variant="link"
                                onClick={() => router.post(route('logout'))}
                                className="text-sm text-muted-foreground"
                            >
                                Sign out
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}