import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { TrustedDeviceCard } from '@/components/security/TrustedDeviceCard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type TrustedDevice } from '@/types';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Shield, Trash2, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';

interface TrustedDevicesProps {
    devices: TrustedDevice[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security',
        href: '/settings/security',
    },
    {
        title: 'Trusted Devices',
        href: '/security/trusted-devices',
    },
];

export default function TrustedDevices({ devices }: TrustedDevicesProps) {
    const revokeAllDevices = () => {
        router.post(route('security.trusted-devices.revoke-all'), {}, {
            onSuccess: () => {
                toast.success('All devices revoked successfully');
            },
            onError: () => {
                toast.error('Failed to revoke devices');
            },
        });
    };

    const cleanupDevices = () => {
        router.post(route('security.trusted-devices.cleanup'), {}, {
            onSuccess: () => {
                toast.success('Expired devices cleaned up');
            },
            onError: () => {
                toast.error('Failed to cleanup devices');
            },
        });
    };

    const activeDevices = devices.filter(device => device.is_active && new Date(device.expires_at) > new Date());
    const expiredDevices = devices.filter(device => !device.is_active || new Date(device.expires_at) < new Date());

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trusted Devices" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Trusted Devices</h1>
                        <p className="text-muted-foreground">
                            Manage devices that you trust and don't require additional verification.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={cleanupDevices}
                            size="sm"
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Cleanup Expired
                        </Button>
                        {activeDevices.length > 0 && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        <Trash2 className="h-4 w-4 mr-2" />
                                        Revoke All
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Revoke All Trusted Devices</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This will revoke all trusted devices except your current device. 
                                            You'll need to verify other devices again when you sign in from them.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction onClick={revokeAllDevices}>
                                            Revoke All
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Total Devices</p>
                                    <p className="text-2xl font-bold">{devices.length}</p>
                                </div>
                                <Shield className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Active Devices</p>
                                    <p className="text-2xl font-bold text-green-600">{activeDevices.length}</p>
                                </div>
                                <Shield className="h-8 w-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Expired Devices</p>
                                    <p className="text-2xl font-bold text-red-600">{expiredDevices.length}</p>
                                </div>
                                <Shield className="h-8 w-8 text-red-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Active Devices */}
                <Card>
                    <CardHeader>
                        <CardTitle>Active Trusted Devices</CardTitle>
                        <CardDescription>
                            These devices are currently trusted and don't require additional verification.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activeDevices.length > 0 ? (
                            <div className="space-y-4">
                                {activeDevices.map((device) => (
                                    <TrustedDeviceCard key={device.id} device={device} />
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Shield className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-muted-foreground">No Trusted Devices</h3>
                                <p className="text-sm text-muted-foreground">
                                    When you mark devices as trusted, they'll appear here.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

            </div>
        </AppLayout>
    );
}