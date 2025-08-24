import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Smartphone, Key, Copy, CheckCircle, AlertTriangle, Download, Shield, Activity, ExternalLink } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';

interface SecurityProps {
    mfaEnabled: boolean;
    hasBackupCodes: boolean;
    passkeys: Array<{
        id: string;
        name: string;
        created_at: string;
    }>;
}

interface MfaSetupData {
    secret?: string;
    qr_code_url?: string;
    qr_code_image?: string;
    backup_codes?: string[];
}

interface PasskeyData {
    id: string;
    name: string;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: '/settings/security',
    },
];

export default function Security({ mfaEnabled, hasBackupCodes, passkeys }: SecurityProps) {
    const { auth } = usePage<SharedData>().props;
    const [mfaSetupData, setMfaSetupData] = useState<MfaSetupData | null>(null);
    const [showBackupCodes, setShowBackupCodes] = useState(false);
    const [backupCodes, setBackupCodes] = useState<string[]>([]);
    const [passkeysList, setPasskeysList] = useState<PasskeyData[]>(passkeys || []);
    const [webAuthnSupported, setWebAuthnSupported] = useState(false);

    const mfaSetupForm = useForm({
        code: '',
        password: '',
    });

    const mfaDisableForm = useForm({
        password: '',
    });

    const passkeyForm = useForm({
        name: '',
    });

    const enableMfaForm = useForm({});

    useEffect(() => {
        setWebAuthnSupported(!!window.PublicKeyCredential);
    }, []);

    const enableMfa = async () => {
        try {
            const response = await fetch(route('mfa.enable'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (data.success) {
                setMfaSetupData({
                    secret: data.secret,
                    qr_code_url: data.qr_code_url,
                    qr_code_image: data.qr_code_image,
                });
            } else {
                toast.error(data.error || 'Failed to enable MFA');
            }
        } catch {
            toast.error('Failed to enable MFA');
        }
    };

    const confirmMfa = async () => {
        try {
            const response = await fetch(route('mfa.confirm'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    code: mfaSetupForm.data.code,
                    password: mfaSetupForm.data.password,
                }),
            });

            const data = await response.json();

            if (data.success) {
                if (data.backup_codes) {
                    setBackupCodes(data.backup_codes);
                    setShowBackupCodes(true);
                }
                setMfaSetupData(null);
                toast.success('MFA has been enabled successfully');
                router.reload({ only: ['mfaEnabled', 'hasBackupCodes'] });
            } else {
                toast.error(data.error || 'Failed to confirm MFA');
            }
        } catch (error: unknown) {
            if (error && typeof error === 'object' && 'response' in error) {
                const errorObj = error as { response?: { data?: { errors?: Record<string, string[]> } } };
                const errors = errorObj.response?.data?.errors;
                if (errors) {
                    toast.error(errors.code?.[0] || errors.password?.[0] || 'Failed to confirm MFA');
                } else {
                    toast.error('Failed to confirm MFA');
                }
            } else {
                toast.error('Failed to confirm MFA');
            }
        }
    };

    const disableMfa = () => {
        mfaDisableForm.post(route('mfa.disable'), {
            onSuccess: () => {
                toast.success('MFA has been disabled');
                router.reload({ only: ['mfaEnabled', 'hasBackupCodes'] });
            },
            onError: (errors) => {
                toast.error(errors.password || 'Failed to disable MFA');
            }
        });
    };

    const regenerateBackupCodes = async () => {
        try {
            const response = await fetch(route('mfa.backup-codes.regenerate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ password: mfaDisableForm.data.password }),
            });

            const data = await response.json();

            if (data.success) {
                setBackupCodes(data.backup_codes);
                setShowBackupCodes(true);
                toast.success('Backup codes regenerated successfully');
            } else {
                toast.error(data.error || 'Failed to regenerate backup codes');
            }
        } catch {
            toast.error('Failed to regenerate backup codes');
        }
    };

    const registerPasskey = async () => {
        if (!webAuthnSupported) {
            toast.error('WebAuthn is not supported in this browser');
            return;
        }

        try {
            // Get registration options
            const optionsResponse = await fetch(route('webauthn.register.options'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const options = await optionsResponse.json();

            // Create credential
            const credential = await navigator.credentials.create({
                publicKey: {
                    ...options,
                    challenge: new Uint8Array(Object.values(options.challenge)),
                    user: {
                        ...options.user,
                        id: new Uint8Array(Object.values(options.user.id)),
                    },
                    excludeCredentials: options.excludeCredentials?.map((cred: { id: Record<string, number>; [key: string]: unknown }) => ({
                        ...cred,
                        id: new Uint8Array(Object.values(cred.id)),
                    })),
                },
            }) as PublicKeyCredential;

            // Register the credential
            const response = await fetch(route('webauthn.register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    name: passkeyForm.data.name || 'Security Key',
                    id: credential.id,
                    rawId: Array.from(new Uint8Array(credential.rawId)),
                    response: {
                        clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON)),
                        attestationObject: Array.from(new Uint8Array((credential.response as AuthenticatorAttestationResponse).attestationObject)),
                    },
                    type: credential.type,
                }),
            });

            const result = await response.json();

            if (result.success) {
                setPasskeysList([...passkeysList, result.passkey]);
                passkeyForm.reset();
                toast.success('Passkey registered successfully');
            } else {
                toast.error(result.error || 'Failed to register passkey');
            }
        } catch {
            toast.error('Failed to register passkey');
        }
    };

    const deletePasskey = async (passkeyId: string) => {
        try {
            const response = await fetch(route('webauthn.delete', passkeyId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const result = await response.json();

            if (result.success) {
                setPasskeysList(passkeysList.filter(p => p.id !== passkeyId));
                toast.success('Passkey deleted successfully');
            } else {
                toast.error(result.error || 'Failed to delete passkey');
            }
        } catch {
            toast.error('Failed to delete passkey');
        }
    };

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Copied to clipboard');
        } catch (error) {
            // Fallback for older browsers or when clipboard API fails
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                toast.success('Copied to clipboard');
            } catch (fallbackError) {
                toast.error('Failed to copy to clipboard');
            }
            document.body.removeChild(textArea);
        }
    };

    const downloadBackupCodes = () => {
        try {
            const appName = 'Laravel'; // Could be made configurable if needed
            const codesText = backupCodes.map((code, index) => `${index + 1}. ${code}`).join('\n');
            const content = `${appName} - MFA Backup Codes\n` +
                          `Generated: ${new Date().toLocaleString()}\n` +
                          `User: ${auth.user.email}\n\n` +
                          `IMPORTANT: Keep these backup codes safe and secure.\n` +
                          `Each code can only be used once.\n\n` +
                          `Backup Codes:\n${codesText}\n\n` +
                          `Instructions:\n` +
                          `1. Keep these codes in a safe place\n` +
                          `2. Each code can only be used once\n` +
                          `3. Use these codes if you lose access to your authenticator app\n` +
                          `4. Generate new codes after using any of these`;

            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${appName}-backup-codes-${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            toast.success('Backup codes downloaded');
        } catch (error) {
            toast.error('Failed to download backup codes');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security settings" />
            
            <SettingsLayout>
            <div className="space-y-6">
                <div>
                    <h3 className="text-lg font-medium">Security</h3>
                    <p className="text-sm text-muted-foreground">
                        Manage your account security settings including multi-factor authentication and passkeys.
                    </p>
                </div>

                <Separator />

                {/* Multi-Factor Authentication */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Smartphone className="h-5 w-5" />
                            Multi-Factor Authentication
                        </CardTitle>
                        <CardDescription>
                            Add an extra layer of security to your account with TOTP-based authentication.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span>Status:</span>
                                <Badge variant={mfaEnabled ? "default" : "secondary"}>
                                    {mfaEnabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </div>

                        {!mfaEnabled && !mfaSetupData && (
                            <Button onClick={enableMfa} className="w-full">
                                Enable MFA
                            </Button>
                        )}

                        {mfaSetupData && (
                            <div className="space-y-4 p-4 border rounded-lg">
                                <div className="text-center">
                                    <h4 className="font-medium">Set up MFA</h4>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        Scan the QR code with your authenticator app
                                    </p>
                                </div>

                                {mfaSetupData.qr_code_image && (
                                    <div className="flex justify-center">
                                        <img 
                                            src={mfaSetupData.qr_code_image} 
                                            alt="MFA QR Code" 
                                            className="border rounded"
                                        />
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label>Manual Entry Key</Label>
                                    <div className="flex gap-2">
                                        <Input 
                                            value={mfaSetupData.secret} 
                                            readOnly 
                                            className="font-mono text-sm"
                                        />
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => copyToClipboard(mfaSetupData.secret || '')}
                                        >
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mfa-code">Verification Code</Label>
                                    <Input
                                        id="mfa-code"
                                        placeholder="000000"
                                        value={mfaSetupForm.data.code}
                                        onChange={(e) => mfaSetupForm.setData('code', e.target.value)}
                                        maxLength={6}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mfa-password">Password</Label>
                                    <Input
                                        id="mfa-password"
                                        type="password"
                                        value={mfaSetupForm.data.password}
                                        onChange={(e) => mfaSetupForm.setData('password', e.target.value)}
                                    />
                                </div>

                                <div className="flex gap-2">
                                    <Button 
                                        onClick={confirmMfa}
                                        disabled={mfaSetupForm.processing || !mfaSetupForm.data.code || !mfaSetupForm.data.password}
                                        className="flex-1"
                                    >
                                        Confirm Setup
                                    </Button>
                                    <Button 
                                        variant="outline"
                                        onClick={() => setMfaSetupData(null)}
                                        className="flex-1"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        )}

                        {mfaEnabled && (
                            <div className="space-y-4">
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="destructive" className="w-full">
                                            Disable MFA
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Disable Multi-Factor Authentication?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This will remove the additional security layer from your account.
                                                Enter your password to confirm.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <div className="py-4">
                                            <Input
                                                type="password"
                                                placeholder="Enter your password"
                                                value={mfaDisableForm.data.password}
                                                onChange={(e) => mfaDisableForm.setData('password', e.target.value)}
                                            />
                                        </div>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={disableMfa}
                                                disabled={!mfaDisableForm.data.password || mfaDisableForm.processing}
                                            >
                                                Disable MFA
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>

                                {hasBackupCodes && (
                                    <Button 
                                        variant="outline" 
                                        onClick={regenerateBackupCodes}
                                        className="w-full"
                                    >
                                        Regenerate Backup Codes
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Backup Codes Modal */}
                {showBackupCodes && backupCodes.length > 0 && (
                    <Card className="border-amber-200 bg-amber-50">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-amber-800">
                                <AlertTriangle className="h-5 w-5" />
                                Backup Codes
                            </CardTitle>
                            <CardDescription className="text-amber-700">
                                Save these backup codes in a safe place. You can use them to access your account if you lose your authenticator device.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-2 mb-4">
                                {backupCodes.map((code, index) => (
                                    <div key={index} className="font-mono text-sm p-2 bg-white rounded border">
                                        {code}
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <div className="flex gap-2">
                                    <Button 
                                        onClick={() => copyToClipboard(backupCodes.join('\n'))}
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        <Copy className="h-4 w-4 mr-2" />
                                        Copy All
                                    </Button>
                                    <Button 
                                        onClick={downloadBackupCodes}
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        <Download className="h-4 w-4 mr-2" />
                                        Download
                                    </Button>
                                </div>
                                <Button 
                                    onClick={() => setShowBackupCodes(false)}
                                    className="w-full"
                                >
                                    <CheckCircle className="h-4 w-4 mr-2" />
                                    I've Saved Them
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Device and Session Management */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Device & Session Management
                        </CardTitle>
                        <CardDescription>
                            Manage your trusted devices and monitor active sessions for enhanced security.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="p-4 border rounded-lg">
                                <div className="flex items-center gap-2 mb-2">
                                    <Shield className="h-4 w-4 text-blue-600" />
                                    <h4 className="font-medium">Trusted Devices</h4>
                                </div>
                                <p className="text-sm text-muted-foreground mb-3">
                                    Manage devices you trust and don't require additional verification.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('security.trusted-devices'))}
                                    className="w-full"
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    Manage Trusted Devices
                                </Button>
                            </div>

                            <div className="p-4 border rounded-lg">
                                <div className="flex items-center gap-2 mb-2">
                                    <Activity className="h-4 w-4 text-green-600" />
                                    <h4 className="font-medium">Active Sessions</h4>
                                </div>
                                <p className="text-sm text-muted-foreground mb-3">
                                    Monitor and manage your active login sessions across devices.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('security.sessions'))}
                                    className="w-full"
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    View Active Sessions
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* WebAuthn / Passkeys */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            Passkeys (WebAuthn)
                        </CardTitle>
                        <CardDescription>
                            Use biometric authentication or security keys for passwordless sign-in.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!webAuthnSupported && (
                            <div className="p-3 bg-amber-50 border border-amber-200 rounded-md">
                                <p className="text-sm text-amber-800">
                                    WebAuthn is not supported in this browser.
                                </p>
                            </div>
                        )}

                        {webAuthnSupported && (
                            <div className="space-y-4">
                                <div className="flex gap-2">
                                    <Input
                                        placeholder="Enter a name for this passkey"
                                        value={passkeyForm.data.name}
                                        onChange={(e) => passkeyForm.setData('name', e.target.value)}
                                        className="flex-1"
                                    />
                                    <Button 
                                        onClick={registerPasskey}
                                        disabled={passkeyForm.processing}
                                    >
                                        Add Passkey
                                    </Button>
                                </div>

                                {passkeysList.length > 0 && (
                                    <div className="space-y-2">
                                        <h5 className="font-medium">Your Passkeys</h5>
                                        {passkeysList.map((passkey) => (
                                            <div 
                                                key={passkey.id}
                                                className="flex items-center justify-between p-3 border rounded-lg"
                                            >
                                                <div>
                                                    <p className="font-medium">{passkey.name}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Added {new Date(passkey.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button variant="destructive" size="sm">
                                                            Delete
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Delete Passkey</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                Are you sure you want to delete "{passkey.name}"? 
                                                                This action cannot be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() => deletePasskey(passkey.id)}
                                                            >
                                                                Delete
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {passkeysList.length === 0 && (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        No passkeys registered yet.
                                    </p>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
            </SettingsLayout>
        </AppLayout>
    );
}