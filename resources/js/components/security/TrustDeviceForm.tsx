import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Shield, Monitor } from 'lucide-react';
import { toast } from 'sonner';

interface TrustDeviceFormProps {
    currentDevice?: {
        user_agent: string;
        ip_address: string;
    };
    onSuccess?: () => void;
}

export function TrustDeviceForm({ currentDevice, onSuccess }: TrustDeviceFormProps) {
    const form = useForm({
        device_name: '',
        remember_duration: 30,
        trust_device: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!form.data.trust_device) {
            toast.error('Please confirm that you want to trust this device');
            return;
        }

        form.post(route('security.trusted-devices.store'), {
            onSuccess: () => {
                toast.success('Device marked as trusted successfully');
                form.reset();
                onSuccess?.();
            },
            onError: (errors) => {
                if (errors.device_name) {
                    toast.error(errors.device_name);
                } else {
                    toast.error('Failed to mark device as trusted');
                }
            },
        });
    };

    const durationOptions = [
        { value: 7, label: '1 week' },
        { value: 30, label: '1 month (recommended)' },
        { value: 60, label: '2 months' },
        { value: 90, label: '3 months' },
        { value: 180, label: '6 months' },
        { value: 365, label: '1 year' },
    ];

    return (
        <Card className="max-w-2xl mx-auto">
            <CardHeader className="text-center">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                    <Shield className="h-8 w-8 text-blue-600 dark:text-blue-300" />
                </div>
                <CardTitle className="text-xl">Trust This Device</CardTitle>
                <CardDescription>
                    Mark this device as trusted to skip additional verification steps when signing in from this device in the future.
                </CardDescription>
            </CardHeader>
            
            <CardContent>
                {/* Current Device Info */}
                {currentDevice && (
                    <div className="mb-6 p-4 bg-muted/50 rounded-lg">
                        <div className="flex items-center gap-3 mb-3">
                            <Monitor className="h-5 w-5 text-muted-foreground" />
                            <h3 className="font-medium">Current Device Information</h3>
                        </div>
                        <div className="space-y-1 text-sm text-muted-foreground">
                            <p><span className="font-medium">IP Address:</span> {currentDevice.ip_address}</p>
                            <p><span className="font-medium">Browser:</span> {currentDevice.user_agent}</p>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <Label htmlFor="device_name">Device Name</Label>
                        <Input
                            id="device_name"
                            type="text"
                            value={form.data.device_name}
                            onChange={(e) => form.setData('device_name', e.target.value)}
                            placeholder="e.g., My MacBook Pro, Work Laptop, Home PC"
                            className="mt-1"
                            required
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            Give this device a memorable name to help you identify it later.
                        </p>
                        {form.errors.device_name && (
                            <p className="text-sm text-destructive mt-1">{form.errors.device_name}</p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="remember_duration">Trust Duration</Label>
                        <Select 
                            value={form.data.remember_duration.toString()}
                            onValueChange={(value) => form.setData('remember_duration', parseInt(value))}
                        >
                            <SelectTrigger className="mt-1">
                                <SelectValue placeholder="Select duration" />
                            </SelectTrigger>
                            <SelectContent>
                                {durationOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value.toString()}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground mt-1">
                            How long should this device be trusted? You can extend this later.
                        </p>
                        {form.errors.remember_duration && (
                            <p className="text-sm text-destructive mt-1">{form.errors.remember_duration}</p>
                        )}
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="trust_device"
                            checked={form.data.trust_device}
                            onCheckedChange={(checked) => form.setData('trust_device', checked === true)}
                        />
                        <Label 
                            htmlFor="trust_device" 
                            className="text-sm leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                            I trust this device and want to skip additional verification when signing in from it
                        </Label>
                    </div>

                    <div className="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg">
                        <div className="flex gap-3">
                            <Shield className="h-5 w-5 text-blue-600 dark:text-blue-300 mt-0.5 shrink-0" />
                            <div className="text-sm">
                                <p className="font-medium text-blue-900 dark:text-blue-100 mb-1">
                                    Security Information
                                </p>
                                <ul className="space-y-1 text-blue-700 dark:text-blue-300">
                                    <li>• Only trust devices that you personally own and control</li>
                                    <li>• Don't trust shared or public computers</li>
                                    <li>• You can revoke trust at any time from your security settings</li>
                                    <li>• Trusted devices will still require your password for sensitive actions</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Button 
                            type="submit" 
                            disabled={form.processing || !form.data.device_name.trim() || !form.data.trust_device}
                            className="flex-1"
                        >
                            {form.processing ? 'Trusting Device...' : 'Trust This Device'}
                        </Button>
                        
                        <Button 
                            type="button" 
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={form.processing}
                        >
                            Skip
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}