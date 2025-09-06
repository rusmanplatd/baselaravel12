import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertCircle, Loader2, Shield, Smartphone, Monitor, Tablet } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface DeviceSetupDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onRegisterDevice: (deviceInfo: RegisterDeviceRequest) => Promise<void>;
    isRegistering: boolean;
    error?: string | null;
}

interface RegisterDeviceRequest {
    device_name: string;
    device_type: 'mobile' | 'desktop' | 'web' | 'tablet';
    public_key: string;
    enable_quantum?: boolean;
    quantum_algorithm?: string;
    capabilities?: string[];
}

export default function DeviceSetupDialog({
    open,
    onOpenChange,
    onRegisterDevice,
    isRegistering,
    error
}: DeviceSetupDialogProps) {
    const [deviceName, setDeviceName] = useState('');
    const [deviceType, setDeviceType] = useState<'mobile' | 'desktop' | 'web' | 'tablet'>('web');
    const [enableQuantum, setEnableQuantum] = useState(true);

    // Generate a random public key for this device (in a real app, this would be from crypto.subtle)
    const generatePublicKey = async (): Promise<string> => {
        // For demo purposes, generate a simple base64 key
        // In production, this would be a real cryptographic public key
        const keyData = new Uint8Array(32);
        crypto.getRandomValues(keyData);
        return btoa(String.fromCharCode.apply(null, Array.from(keyData)));
    };

    // Detect device type automatically
    const detectDeviceType = (): 'mobile' | 'desktop' | 'web' | 'tablet' => {
        const userAgent = navigator.userAgent.toLowerCase();
        if (/mobile|android|iphone|ipad|phone/.test(userAgent)) {
            if (/ipad|tablet/.test(userAgent)) return 'tablet';
            return 'mobile';
        }
        return 'web';
    };

    // Set default device name based on type
    const getDefaultDeviceName = (type: string): string => {
        const platform = navigator.platform;
        const userAgent = navigator.userAgent;
        
        if (type === 'web') {
            if (userAgent.includes('Chrome')) return `Chrome on ${platform}`;
            if (userAgent.includes('Firefox')) return `Firefox on ${platform}`;
            if (userAgent.includes('Safari')) return `Safari on ${platform}`;
            return `Web Browser on ${platform}`;
        }
        
        return `My ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    };

    // Initialize default values when dialog opens
    React.useEffect(() => {
        if (open && !deviceName) {
            const detectedType = detectDeviceType();
            setDeviceType(detectedType);
            setDeviceName(getDefaultDeviceName(detectedType));
        }
    }, [open, deviceName]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!deviceName.trim()) {
            return;
        }

        try {
            const publicKey = await generatePublicKey();
            const capabilities = enableQuantum ? ['ML-KEM-768', 'rsa-4096-oaep'] : ['rsa-4096-oaep'];
            
            // Generate device fingerprint (should match the one from useE2EEChat)
            const deviceFingerprint = generateDeviceFingerprint();
            const hardwareFingerprint = await generateHardwareFingerprint();
            
            await onRegisterDevice({
                device_name: deviceName.trim(),
                device_type: deviceType,
                device_fingerprint: deviceFingerprint,
                platform: navigator.platform || 'Unknown',
                user_agent: navigator.userAgent,
                public_key: publicKey,
                enable_quantum: enableQuantum,
                quantum_algorithm: enableQuantum ? 'ML-KEM-768' : undefined,
                capabilities,
                hardware_fingerprint: hardwareFingerprint
            });
            
            // Reset form on success
            setDeviceName('');
            setDeviceType('web');
            setEnableQuantum(true);
        } catch (err) {
            // Error is handled by parent component
            console.error('Device registration failed:', err);
        }
    };

    const getDeviceIcon = (type: string) => {
        switch (type) {
            case 'mobile': return <Smartphone className="h-4 w-4" />;
            case 'tablet': return <Tablet className="h-4 w-4" />;
            case 'desktop': return <Monitor className="h-4 w-4" />;
            default: return <Monitor className="h-4 w-4" />;
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-blue-500" />
                        Device Setup Required
                    </DialogTitle>
                    <DialogDescription>
                        Register this device to enable end-to-end encrypted messaging. 
                        This creates unique encryption keys for your device.
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="device-name">Device Name</Label>
                        <Input
                            id="device-name"
                            placeholder="My Device"
                            value={deviceName}
                            onChange={(e) => setDeviceName(e.target.value)}
                            required
                            disabled={isRegistering}
                        />
                        <p className="text-xs text-muted-foreground">
                            Choose a name to identify this device in your device list
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="device-type">Device Type</Label>
                        <Select 
                            value={deviceType} 
                            onValueChange={(value: 'mobile' | 'desktop' | 'web' | 'tablet') => setDeviceType(value)}
                            disabled={isRegistering}
                        >
                            <SelectTrigger>
                                <SelectValue>
                                    <div className="flex items-center gap-2">
                                        {getDeviceIcon(deviceType)}
                                        {deviceType.charAt(0).toUpperCase() + deviceType.slice(1)}
                                    </div>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="web">
                                    <div className="flex items-center gap-2">
                                        <Monitor className="h-4 w-4" />
                                        Web Browser
                                    </div>
                                </SelectItem>
                                <SelectItem value="desktop">
                                    <div className="flex items-center gap-2">
                                        <Monitor className="h-4 w-4" />
                                        Desktop
                                    </div>
                                </SelectItem>
                                <SelectItem value="mobile">
                                    <div className="flex items-center gap-2">
                                        <Smartphone className="h-4 w-4" />
                                        Mobile
                                    </div>
                                </SelectItem>
                                <SelectItem value="tablet">
                                    <div className="flex items-center gap-2">
                                        <Tablet className="h-4 w-4" />
                                        Tablet
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium">Security Features</Label>
                        </div>
                        
                        <div className="flex items-center gap-3 p-3 rounded-lg border bg-muted/30">
                            <input
                                type="checkbox"
                                id="enable-quantum"
                                checked={enableQuantum}
                                onChange={(e) => setEnableQuantum(e.target.checked)}
                                disabled={isRegistering}
                                className="h-4 w-4"
                            />
                            <div className="flex-1">
                                <Label htmlFor="enable-quantum" className="text-sm font-medium cursor-pointer">
                                    Enable Quantum-Resistant Encryption
                                </Label>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Uses ML-KEM-768 algorithm for future-proof security against quantum computers
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button 
                            type="button" 
                            variant="outline" 
                            onClick={() => onOpenChange(false)}
                            disabled={isRegistering}
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            disabled={!deviceName.trim() || isRegistering}
                            className="min-w-[100px]"
                        >
                            {isRegistering ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Registering...
                                </>
                            ) : (
                                <>
                                    <Shield className="h-4 w-4 mr-2" />
                                    Register Device
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Helper functions for device fingerprinting (matching useE2EEChat.ts)
function generateDeviceFingerprint(): string {
    const components = [
        navigator.userAgent,
        navigator.language,
        navigator.userAgent.includes('Win') ? 'Win32' : navigator.userAgent.includes('Mac') ? 'MacIntel' : 'Linux',
        navigator.hardwareConcurrency?.toString() || '',
        screen.width + 'x' + screen.height,
        new Date().getTimezoneOffset().toString(),
    ];
    
    const combined = components.join('|');
    return btoa(combined).replace(/[+/=]/g, '').substring(0, 16);
}

async function generateHardwareFingerprint(): Promise<string> {
    // Generate hardware fingerprint using available APIs
    const components: string[] = [];
    
    // Canvas fingerprint
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('E2EE Device Fingerprint', 2, 2);
            components.push(canvas.toDataURL());
        }
    } catch (e) {
        // Canvas fingerprinting blocked
    }
    
    // WebGL fingerprint
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl') as WebGLRenderingContext | null;
        if (gl) {
            const renderer = gl.getParameter(gl.RENDERER);
            const vendor = gl.getParameter(gl.VENDOR);
            components.push(renderer + '|' + vendor);
        }
    } catch (e) {
        // WebGL fingerprinting blocked
    }
    
    const combined = components.join('||');
    const encoder = new TextEncoder();
    const data = encoder.encode(combined);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').substring(0, 32);
}