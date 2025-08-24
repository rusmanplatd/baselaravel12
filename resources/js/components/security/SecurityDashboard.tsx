import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { TrustedDeviceCard } from './TrustedDeviceCard';
import { SessionCard } from './SessionCard';
import { SecurityAlertCard } from './SecurityAlertCard';
import { SecurityStatsCard } from './SecurityStatsCard';
import { useSecurityDashboard } from '@/hooks/useSecurityDashboard';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Shield, Activity, AlertTriangle, RefreshCw, Trash2, LogOut, ExternalLink } from 'lucide-react';
import { toast } from 'sonner';

interface SecurityDashboardProps {
    showActions?: boolean;
    showLinks?: boolean;
    maxItems?: number;
}

export function SecurityDashboard({ 
    showActions = true, 
    showLinks = false, 
    maxItems = 5 
}: SecurityDashboardProps) {
    const {
        sessions,
        stats,
        alerts,
        devices,
        loading,
        error,
        refresh,
        terminateAllOtherSessions,
        revokeAllDevices,
    } = useSecurityDashboard({ autoRefresh: true });

    const handleTerminateAllOthers = async () => {
        const count = await terminateAllOtherSessions();
        if (count > 0) {
            toast.success(`Successfully terminated ${count} other sessions`);
        } else {
            toast.error('Failed to terminate sessions');
        }
    };

    const handleRevokeAllDevices = async () => {
        const count = await revokeAllDevices();
        if (count > 0) {
            toast.success(`Successfully revoked ${count} trusted devices`);
        } else {
            toast.error('Failed to revoke devices');
        }
    };

    if (loading) {
        return (
            <div className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Card key={i}>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-24" />
                                        <Skeleton className="h-8 w-16" />
                                    </div>
                                    <Skeleton className="h-8 w-8 rounded-full" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <Skeleton className="h-6 w-32" />
                            <Skeleton className="h-4 w-48" />
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {Array.from({ length: 3 }).map((_, i) => (
                                    <Skeleton key={i} className="h-20 w-full" />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <Skeleton className="h-6 w-32" />
                            <Skeleton className="h-4 w-48" />
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {Array.from({ length: 3 }).map((_, i) => (
                                    <Skeleton key={i} className="h-20 w-full" />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <Card className="border-destructive">
                <CardContent className="p-6">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-5 w-5 text-destructive" />
                        <div className="flex-1">
                            <h3 className="font-medium">Failed to load security data</h3>
                            <p className="text-sm text-muted-foreground">{error}</p>
                        </div>
                        <Button variant="outline" size="sm" onClick={refresh}>
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Retry
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const activeSessions = sessions.filter(session => session.is_active);
    const activeDevices = devices.filter(device => device.is_active && new Date(device.expires_at) > new Date());
    const recentSessions = activeSessions.slice(0, maxItems);
    const recentDevices = activeDevices.slice(0, maxItems);

    return (
        <div className="space-y-6">
            {/* Security Alerts */}
            {alerts.length > 0 && (
                <div className="space-y-3">
                    {alerts.map((alert, index) => (
                        <SecurityAlertCard key={index} alert={alert} />
                    ))}
                </div>
            )}

            {/* Statistics */}
            <SecurityStatsCard stats={stats} />

            {/* Quick Actions */}
            {showActions && (activeSessions.length > 1 || activeDevices.length > 0) && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Quick Actions
                        </CardTitle>
                        <CardDescription>
                            Manage your security settings with one-click actions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2">
                            {activeSessions.length > 1 && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="outline" size="sm">
                                            <LogOut className="h-4 w-4 mr-2" />
                                            End All Other Sessions
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>End All Other Sessions</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This will terminate all your other active sessions. You'll remain logged in on this device, 
                                                but will need to log in again on other devices.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                            <AlertDialogAction onClick={handleTerminateAllOthers}>
                                                End All Other Sessions
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}

                            {activeDevices.length > 0 && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="outline" size="sm">
                                            <Trash2 className="h-4 w-4 mr-2" />
                                            Revoke All Devices
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
                                            <AlertDialogAction onClick={handleRevokeAllDevices}>
                                                Revoke All Devices
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}

                            <Button variant="outline" size="sm" onClick={refresh}>
                                <RefreshCw className="h-4 w-4 mr-2" />
                                Refresh Data
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Active Sessions */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="h-5 w-5" />
                                    Active Sessions
                                </CardTitle>
                                <CardDescription>
                                    Your currently active login sessions
                                </CardDescription>
                            </div>
                            {showLinks && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('security.sessions'))}
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    View All
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {recentSessions.length > 0 ? (
                            <div className="space-y-3">
                                {recentSessions.map((session) => (
                                    <SessionCard key={session.id} session={session} />
                                ))}
                                {sessions.length > maxItems && (
                                    <div className="text-center py-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => router.visit(route('security.sessions'))}
                                        >
                                            View {sessions.length - maxItems} more sessions
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Activity className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-muted-foreground">No Active Sessions</h3>
                                <p className="text-sm text-muted-foreground">
                                    Your active sessions will appear here.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Trusted Devices */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Trusted Devices
                                </CardTitle>
                                <CardDescription>
                                    Devices that don't require additional verification
                                </CardDescription>
                            </div>
                            {showLinks && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('security.trusted-devices'))}
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    View All
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {recentDevices.length > 0 ? (
                            <div className="space-y-3">
                                {recentDevices.map((device) => (
                                    <TrustedDeviceCard key={device.id} device={device} />
                                ))}
                                {devices.length > maxItems && (
                                    <div className="text-center py-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => router.visit(route('security.trusted-devices'))}
                                        >
                                            View {devices.length - maxItems} more devices
                                        </Button>
                                    </div>
                                )}
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
        </div>
    );
}