import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type TrustedDevice } from '@/types';
import { useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Smartphone, Monitor, Tablet, MapPin, Clock, Settings, Trash2, AlertTriangle, Shield } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { formatDistanceToNow } from 'date-fns';

interface TrustedDeviceCardProps {
    device: TrustedDevice;
}

export function TrustedDeviceCard({ device }: TrustedDeviceCardProps) {
    const [showEditDialog, setShowEditDialog] = useState(false);
    
    const editForm = useForm({
        device_name: device.device_name,
        extend_days: 30,
    });

    const getDeviceIcon = (deviceType: TrustedDevice['device_type']) => {
        switch (deviceType) {
            case 'mobile':
                return <Smartphone className="h-5 w-5" />;
            case 'tablet':
                return <Tablet className="h-5 w-5" />;
            default:
                return <Monitor className="h-5 w-5" />;
        }
    };

    const getDeviceTypeColor = (deviceType: TrustedDevice['device_type']) => {
        switch (deviceType) {
            case 'mobile':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
            case 'tablet':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
        }
    };

    const isExpired = new Date(device.expires_at) < new Date();
    const isExpiringSoon = new Date(device.expires_at) < new Date(Date.now() + 7 * 24 * 60 * 60 * 1000); // 7 days

    const updateDevice = () => {
        editForm.put(route('security.trusted-devices.update', device.id), {
            onSuccess: () => {
                setShowEditDialog(false);
                toast.success('Device updated successfully');
            },
            onError: () => {
                toast.error('Failed to update device');
            },
        });
    };

    const revokeDevice = () => {
        router.delete(route('security.trusted-devices.destroy', device.id), {
            onSuccess: () => {
                toast.success('Device revoked successfully');
            },
            onError: () => {
                toast.error('Failed to revoke device');
            },
        });
    };

    return (
        <>
            <Card className={`${device.is_current ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : ''} ${isExpired ? 'opacity-60' : ''}`}>
                <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                        <div className="flex items-start gap-3 flex-1">
                            <div className="mt-1">
                                {getDeviceIcon(device.device_type)}
                            </div>
                            
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-2 flex-wrap">
                                    <h4 className="font-medium text-sm truncate">{device.device_name}</h4>
                                    
                                    {device.is_current && (
                                        <Badge variant="secondary" className="text-xs shrink-0">
                                            <Shield className="h-3 w-3 mr-1" />
                                            Current
                                        </Badge>
                                    )}
                                    
                                    <Badge 
                                        variant="secondary" 
                                        className={`text-xs shrink-0 ${getDeviceTypeColor(device.device_type)}`}
                                    >
                                        {device.device_type}
                                    </Badge>
                                    
                                    {isExpired && (
                                        <Badge variant="destructive" className="text-xs shrink-0">
                                            <AlertTriangle className="h-3 w-3 mr-1" />
                                            Expired
                                        </Badge>
                                    )}
                                    
                                    {!isExpired && isExpiringSoon && (
                                        <Badge variant="outline" className="text-xs shrink-0 text-orange-600 border-orange-200">
                                            <AlertTriangle className="h-3 w-3 mr-1" />
                                            Expiring Soon
                                        </Badge>
                                    )}
                                </div>
                                
                                <div className="space-y-1 text-xs text-muted-foreground">
                                    <p className="truncate">{device.browser || 'Unknown Browser'} on {device.platform || 'Unknown OS'}</p>
                                    
                                    <div className="flex items-center gap-3 flex-wrap">
                                        <span className="flex items-center gap-1 shrink-0">
                                            <MapPin className="h-3 w-3" />
                                            {device.ip_address || 'Unknown'}
                                            {device.location && ` • ${device.location}`}
                                        </span>
                                        
                                        <span className="flex items-center gap-1 shrink-0">
                                            <Clock className="h-3 w-3" />
                                            {device.last_used_at ? formatDistanceToNow(new Date(device.last_used_at), { addSuffix: true }) : 'Never'}
                                        </span>
                                    </div>
                                    
                                    <p className="text-xs">
                                        {device.expires_at ? (
                                            isExpired 
                                                ? `Expired ${formatDistanceToNow(new Date(device.expires_at), { addSuffix: true })}`
                                                : `Expires ${formatDistanceToNow(new Date(device.expires_at), { addSuffix: true })}`
                                        ) : 'No expiration set'}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div className="flex gap-1 shrink-0">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowEditDialog(true)}
                                disabled={isExpired}
                            >
                                <Settings className="h-3 w-3" />
                            </Button>
                            
                            {!device.is_current && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="outline" size="sm">
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Revoke Trusted Device</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Are you sure you want to revoke trust for "{device.device_name}"? 
                                                You'll need to verify this device again when signing in from it.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                            <AlertDialogAction onClick={revokeDevice}>
                                                Revoke Trust
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Edit Dialog */}
            <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Trusted Device</DialogTitle>
                        <DialogDescription>
                            Update the device name or extend its expiration period.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="device-name">Device Name</Label>
                            <Input
                                id="device-name"
                                value={editForm.data.device_name}
                                onChange={(e) => editForm.setData('device_name', e.target.value)}
                                placeholder="Enter device name"
                                className="mt-1"
                            />
                            {editForm.errors.device_name && (
                                <p className="text-sm text-destructive mt-1">{editForm.errors.device_name}</p>
                            )}
                        </div>
                        
                        <div>
                            <Label htmlFor="extend-days">Extend Expiration (Days)</Label>
                            <Input
                                id="extend-days"
                                type="number"
                                min="1"
                                max="365"
                                value={editForm.data.extend_days}
                                onChange={(e) => editForm.setData('extend_days', parseInt(e.target.value) || 30)}
                                className="mt-1"
                            />
                            <p className="text-xs text-muted-foreground mt-1">
                                Current expiration will be extended by this many days.
                            </p>
                            {editForm.errors.extend_days && (
                                <p className="text-sm text-destructive mt-1">{editForm.errors.extend_days}</p>
                            )}
                        </div>
                    </div>
                    
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowEditDialog(false)}>
                            Cancel
                        </Button>
                        <Button 
                            onClick={updateDevice}
                            disabled={editForm.processing || !editForm.data.device_name.trim()}
                        >
                            {editForm.processing ? 'Updating...' : 'Update Device'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}