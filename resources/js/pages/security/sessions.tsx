import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { SessionCard } from '@/components/security/SessionCard';
import { SecurityAlertCard } from '@/components/security/SecurityAlertCard';
import { SecurityStatsCard } from '@/components/security/SecurityStatsCard';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type UserSession, type SessionStats, type SecurityAlert } from '@/types';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Activity, LogOut } from 'lucide-react';
import { toast } from 'sonner';

interface SessionsProps {
    sessions: UserSession[];
    stats: SessionStats;
    alerts: SecurityAlert[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security',
        href: '/settings/security',
    },
    {
        title: 'Active Sessions',
        href: '/security/sessions',
    },
];

export default function Sessions({ sessions, stats, alerts }: SessionsProps) {
    const terminateAllOthers = () => {
        router.post(route('security.sessions.terminate-others'), {}, {
            onSuccess: () => {
                toast.success('All other sessions terminated');
            },
            onError: () => {
                toast.error('Failed to terminate sessions');
            },
        });
    };

    const activeSessions = sessions.filter(session => session.is_active);
    const inactiveSessions = sessions.filter(session => !session.is_active);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Active Sessions" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Active Sessions</h1>
                        <p className="text-muted-foreground">
                            Monitor and manage your active login sessions across all devices.
                        </p>
                    </div>
                    {activeSessions.length > 1 && (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive" size="sm">
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
                                    <AlertDialogAction onClick={terminateAllOthers}>
                                        End All Other Sessions
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}
                </div>

                {/* Security Alerts */}
                {alerts.length > 0 && (
                    <div className="space-y-3">
                        {alerts.map((alert, index) => (
                            <SecurityAlertCard key={index} alert={alert} />
                        ))}
                    </div>
                )}

                {/* Statistics Cards */}
                <SecurityStatsCard stats={stats} />

                {/* Active Sessions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Active Sessions</CardTitle>
                        <CardDescription>
                            These are your currently active login sessions across all devices.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {activeSessions.length > 0 ? (
                            <div className="space-y-3">
                                {activeSessions.map((session) => (
                                    <SessionCard key={session.id} session={session} />
                                ))}
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

                {/* Recent Terminated Sessions */}
                {inactiveSessions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Terminated Sessions</CardTitle>
                            <CardDescription>
                                These are recently terminated or expired sessions.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {inactiveSessions.slice(0, 5).map((session) => (
                                    <SessionCard key={session.id} session={session} />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}