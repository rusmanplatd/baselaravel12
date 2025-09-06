import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Shield, Copy, Check, Download, Eye, EyeOff } from 'lucide-react';
import QRCode from 'qrcode';
import { apiService } from '@/services/ApiService';

interface TotpSetupProps {
    mfaEnabled: boolean;
    hasBackupCodes: boolean;
    onMfaStatusChange: () => void;
}

export default function TotpSetup({ mfaEnabled, hasBackupCodes, onMfaStatusChange }: TotpSetupProps) {
    const [setupStep, setSetupStep] = useState<'start' | 'qr' | 'verify' | 'backup'>('start');
    const [qrCodeUrl, setQrCodeUrl] = useState('');
    const [secret, setSecret] = useState('');
    const [qrImageData, setQrImageData] = useState('');
    const [verificationCode, setVerificationCode] = useState('');
    const [password, setPassword] = useState('');
    const [backupCodes, setBackupCodes] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [copiedSecret, setCopiedSecret] = useState(false);
    const [copiedCodes, setCopiedCodes] = useState<{ [key: string]: boolean }>({});
    const [showBackupCodes, setShowBackupCodes] = useState(false);

    useEffect(() => {
        if (mfaEnabled) {
            setSetupStep('start');
        }
    }, [mfaEnabled]);

    const enableMfa = async () => {
        setLoading(true);
        setError('');

        try {
            const result = await apiService.post(route('mfa.enable')) as {
                success: boolean;
                secret: string;
                qr_code_url: string;
                error?: string;
            };

            if (result.success) {
                setSecret(result.secret);
                setQrCodeUrl(result.qr_code_url);
                
                // Generate QR code image
                const qrImage = await QRCode.toDataURL(result.qr_code_url);
                setQrImageData(qrImage);
                
                setSetupStep('qr');
            } else {
                setError(result.error || 'Failed to enable MFA');
            }
        } catch (err) {
            setError('Failed to enable MFA');
        } finally {
            setLoading(false);
        }
    };

    const confirmMfa = async () => {
        if (!verificationCode || !password) {
            setError('Please enter both your password and verification code');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const result = await apiService.post(route('mfa.confirm'), {
                code: verificationCode,
                password: password,
            }) as {
                success: boolean;
                backup_codes?: string[];
                error?: string;
                message?: string;
            };

            if (result.success) {
                setBackupCodes(result.backup_codes || []);
                setSetupStep('backup');
                onMfaStatusChange();
            } else {
                setError(result.error || result.message || 'Failed to confirm MFA');
            }
        } catch (err) {
            setError('Failed to confirm MFA');
        } finally {
            setLoading(false);
        }
    };

    const disableMfa = async () => {
        if (!password) {
            setError('Please enter your password');
            return;
        }

        if (!confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')) {
            return;
        }

        setLoading(true);
        setError('');

        try {
            const result = await apiService.post(route('mfa.disable'), {
                password: password,
            }) as {
                success: boolean;
                error?: string;
            };

            if (result.success) {
                setPassword('');
                onMfaStatusChange();
            } else {
                setError(result.error || 'Failed to disable MFA');
            }
        } catch (err) {
            setError('Failed to disable MFA');
        } finally {
            setLoading(false);
        }
    };

    const regenerateBackupCodes = async () => {
        if (!password) {
            setError('Please enter your password');
            return;
        }

        setLoading(true);
        setError('');

        try {
            const result = await apiService.post(route('mfa.backup-codes.regenerate'), {
                password: password,
            }) as {
                success: boolean;
                backup_codes?: string[];
                error?: string;
            };

            if (result.success) {
                setBackupCodes(result.backup_codes || []);
                setPassword('');
                setShowBackupCodes(true);
            } else {
                setError(result.error || 'Failed to regenerate backup codes');
            }
        } catch (err) {
            setError('Failed to regenerate backup codes');
        } finally {
            setLoading(false);
        }
    };

    const copyToClipboard = (text: string, type: 'secret' | string) => {
        navigator.clipboard.writeText(text);
        if (type === 'secret') {
            setCopiedSecret(true);
            setTimeout(() => setCopiedSecret(false), 2000);
        } else {
            setCopiedCodes({ ...copiedCodes, [type]: true });
            setTimeout(() => setCopiedCodes({ ...copiedCodes, [type]: false }), 2000);
        }
    };

    const downloadBackupCodes = () => {
        const content = `Backup Codes for ${window.location.hostname}\n\n${backupCodes.join('\n')}\n\nKeep these codes safe and secure.`;
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'mfa-backup-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    };

    if (mfaEnabled && setupStep === 'start') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-green-600">
                        <Shield className="h-5 w-5" />
                        Two-Factor Authentication Enabled
                    </CardTitle>
                    <CardDescription>
                        Your account is protected with two-factor authentication.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {error && (
                        <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
                            {error}
                        </div>
                    )}

                    <div className="space-y-3">
                        <Label htmlFor="password">Current Password</Label>
                        <Input
                            id="password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Enter your current password"
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button 
                            onClick={regenerateBackupCodes}
                            disabled={loading || !password}
                            variant="outline"
                        >
                            {showBackupCodes ? 'Hide' : 'Show'} Backup Codes
                        </Button>
                        <Button 
                            onClick={disableMfa}
                            disabled={loading || !password}
                            variant="destructive"
                        >
                            {loading ? 'Disabling...' : 'Disable MFA'}
                        </Button>
                    </div>

                    {showBackupCodes && backupCodes.length > 0 && (
                        <div className="mt-4 p-4 border rounded-md space-y-3">
                            <div className="flex items-center justify-between">
                                <Label>Backup Codes</Label>
                                <Button
                                    onClick={downloadBackupCodes}
                                    variant="outline"
                                    size="sm"
                                >
                                    <Download className="h-4 w-4 mr-1" />
                                    Download
                                </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {backupCodes.map((code, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <code className="flex-1 p-2 bg-gray-50 rounded text-sm font-mono">
                                            {code}
                                        </code>
                                        <Button
                                            onClick={() => copyToClipboard(code, `code-${index}`)}
                                            variant="ghost"
                                            size="sm"
                                        >
                                            {copiedCodes[`code-${index}`] ? (
                                                <Check className="h-4 w-4 text-green-500" />
                                            ) : (
                                                <Copy className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Two-Factor Authentication
                </CardTitle>
                <CardDescription>
                    Add an extra layer of security to your account with TOTP authentication.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {error && (
                    <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
                        {error}
                    </div>
                )}

                {setupStep === 'start' && (
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Two-factor authentication adds an extra layer of security to your account 
                            by requiring a verification code from your authenticator app in addition to your password.
                        </p>
                        <Button onClick={enableMfa} disabled={loading}>
                            {loading ? 'Setting up...' : 'Enable Two-Factor Authentication'}
                        </Button>
                    </div>
                )}

                {setupStep === 'qr' && (
                    <div className="space-y-4">
                        <div className="text-center">
                            <h3 className="text-lg font-medium mb-2">Scan QR Code</h3>
                            <p className="text-sm text-muted-foreground mb-4">
                                Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                            </p>
                            {qrImageData && (
                                <img src={qrImageData} alt="QR Code" className="mx-auto mb-4" />
                            )}
                        </div>

                        <Separator />

                        <div className="space-y-2">
                            <Label>Or enter this secret manually:</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    value={secret}
                                    readOnly
                                    className="font-mono text-sm"
                                />
                                <Button
                                    onClick={() => copyToClipboard(secret, 'secret')}
                                    variant="outline"
                                    size="sm"
                                >
                                    {copiedSecret ? (
                                        <Check className="h-4 w-4 text-green-500" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                </Button>
                            </div>
                        </div>

                        <Button onClick={() => setSetupStep('verify')} className="w-full">
                            I've Added the Secret
                        </Button>
                    </div>
                )}

                {setupStep === 'verify' && (
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-lg font-medium mb-2">Verify Setup</h3>
                            <p className="text-sm text-muted-foreground">
                                Enter the 6-digit code from your authenticator app to complete setup.
                            </p>
                        </div>

                        <div className="space-y-3">
                            <Label htmlFor="password">Current Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Enter your current password"
                            />
                        </div>

                        <div className="space-y-3">
                            <Label htmlFor="code">Verification Code</Label>
                            <Input
                                id="code"
                                value={verificationCode}
                                onChange={(e) => setVerificationCode(e.target.value)}
                                placeholder="Enter 6-digit code"
                                maxLength={6}
                            />
                        </div>

                        <div className="flex gap-2">
                            <Button 
                                onClick={() => setSetupStep('qr')} 
                                variant="outline"
                                disabled={loading}
                            >
                                Back
                            </Button>
                            <Button 
                                onClick={confirmMfa} 
                                disabled={loading || !verificationCode || !password}
                                className="flex-1"
                            >
                                {loading ? 'Verifying...' : 'Enable Two-Factor Authentication'}
                            </Button>
                        </div>
                    </div>
                )}

                {setupStep === 'backup' && (
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-lg font-medium mb-2 text-green-600">Setup Complete!</h3>
                            <p className="text-sm text-muted-foreground">
                                Two-factor authentication is now enabled. Save these backup codes in a safe place.
                            </p>
                        </div>

                        <div className="p-4 border rounded-md space-y-3">
                            <div className="flex items-center justify-between">
                                <Label>Backup Codes</Label>
                                <Button
                                    onClick={downloadBackupCodes}
                                    variant="outline"
                                    size="sm"
                                >
                                    <Download className="h-4 w-4 mr-1" />
                                    Download
                                </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {backupCodes.map((code, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <code className="flex-1 p-2 bg-gray-50 rounded text-sm font-mono">
                                            {code}
                                        </code>
                                        <Button
                                            onClick={() => copyToClipboard(code, `code-${index}`)}
                                            variant="ghost"
                                            size="sm"
                                        >
                                            {copiedCodes[`code-${index}`] ? (
                                                <Check className="h-4 w-4 text-green-500" />
                                            ) : (
                                                <Copy className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Each backup code can only be used once. Store them in a safe place.
                            </p>
                        </div>

                        <Button onClick={() => setSetupStep('start')} className="w-full">
                            Complete Setup
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}