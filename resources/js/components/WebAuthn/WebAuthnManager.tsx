import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Trash2, Key, Plus } from 'lucide-react';
import { startRegistration, startAuthentication } from '@simplewebauthn/browser';
import { router } from '@inertiajs/react';
import { apiService } from '@/services/ApiService';

interface Passkey {
    id: string;
    name: string;
    created_at: string;
}

export default function WebAuthnManager() {
    const [passkeys, setPasskeys] = useState<Passkey[]>([]);
    const [loading, setLoading] = useState(false);
    const [registering, setRegistering] = useState(false);
    const [newKeyName, setNewKeyName] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        loadPasskeys();
    }, []);

    const loadPasskeys = async () => {
        try {
            const data = await apiService.get(route('webauthn.list'));
            if (data.passkeys) {
                setPasskeys(data.passkeys);
            }
        } catch (err) {
            setError('Failed to load passkeys');
        }
    };

    const registerPasskey = async () => {
        if (!newKeyName.trim()) {
            setError('Please enter a name for your security key');
            return;
        }

        setRegistering(true);
        setError('');

        try {
            // Get registration options
            const options = await apiService.get(route('webauthn.register.options'));

            // Start registration
            const credential = await startRegistration(options);

            // Send credential to server
            const result = await apiService.post(route('webauthn.register'), {
                ...credential,
                name: newKeyName,
            });

            if (result.success) {
                setNewKeyName('');
                await loadPasskeys();
            } else {
                setError(result.error || 'Failed to register passkey');
            }
        } catch (err) {
            if (err instanceof Error && err.name === 'NotAllowedError') {
                setError('Registration was cancelled or not allowed');
            } else {
                setError('Failed to register passkey');
            }
        } finally {
            setRegistering(false);
        }
    };

    const deletePasskey = async (passkeyId: string) => {
        if (!confirm('Are you sure you want to remove this security key?')) {
            return;
        }

        setLoading(true);
        try {
            const result = await apiService.delete(route('webauthn.delete', passkeyId));
            if (result.success) {
                await loadPasskeys();
            } else {
                setError('Failed to remove passkey');
            }
        } catch (err) {
            setError('Failed to remove passkey');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Key className="h-5 w-5" />
                    Security Keys (WebAuthn)
                </CardTitle>
                <CardDescription>
                    Add security keys for passwordless authentication. These can be hardware keys, 
                    your device's biometric authentication, or platform authenticators.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {error && (
                    <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
                        {error}
                    </div>
                )}

                {/* Register new passkey */}
                <div className="space-y-3 p-4 border rounded-md">
                    <Label htmlFor="keyName">Add New Security Key</Label>
                    <div className="flex gap-2">
                        <Input
                            id="keyName"
                            value={newKeyName}
                            onChange={(e) => setNewKeyName(e.target.value)}
                            placeholder="e.g., YubiKey, Touch ID, Face ID"
                            disabled={registering}
                        />
                        <Button 
                            onClick={registerPasskey} 
                            disabled={registering || !newKeyName.trim()}
                            size="sm"
                        >
                            <Plus className="h-4 w-4 mr-1" />
                            {registering ? 'Adding...' : 'Add Key'}
                        </Button>
                    </div>
                </div>

                {/* Existing passkeys */}
                <div className="space-y-2">
                    <Label>Your Security Keys</Label>
                    {passkeys.length === 0 ? (
                        <div className="text-sm text-muted-foreground p-4 text-center border rounded-md">
                            No security keys registered yet. Add one above to get started.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {passkeys.map((passkey) => (
                                <div
                                    key={passkey.id}
                                    className="flex items-center justify-between p-3 border rounded-md"
                                >
                                    <div>
                                        <div className="font-medium">{passkey.name}</div>
                                        <div className="text-sm text-muted-foreground">
                                            Added {new Date(passkey.created_at).toLocaleDateString()}
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => deletePasskey(passkey.id)}
                                        disabled={loading}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}