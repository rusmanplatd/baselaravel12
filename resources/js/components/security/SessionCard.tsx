import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { type UserSession } from '@/types';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Smartphone, Monitor, Tablet, MapPin, Clock, Shield, Trash2, Activity } from 'lucide-react';
import { toast } from 'sonner';
import { formatDistanceToNow } from 'date-fns';

interface SessionCardProps {
    session: UserSession;
}

export function SessionCard({ session }: SessionCardProps) {
    const getDeviceIcon = (deviceType: UserSession['device_type']) => {
        switch (deviceType) {
            case 'mobile':
                return <Smartphone className="h-4 w-4" />;
            case 'tablet':
                return <Tablet className="h-4 w-4" />;
            default:
                return <Monitor className="h-4 w-4" />;
        }
    };

    const getDeviceTypeColor = (deviceType: UserSession['device_type']) => {
        switch (deviceType) {
            case 'mobile':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
            case 'tablet':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
        }
    };

    const terminateSession = () => {
        router.delete(route('security.sessions.destroy', session.id), {
            onSuccess: (response) => {
                toast.success('Session terminated successfully');
                
                // If the terminated session was the current session, redirect to login
                if (session.is_current) {
                    setTimeout(() => {
                        router.visit(route('login'), {
                            method: 'get',
                            onSuccess: () => {
                                toast.info('You have been logged out due to session termination');
                            }
                        });
                    }, 1000);
                }
            },
            onError: (errors) => {
                console.error('Session termination error:', errors);
                toast.error('Failed to terminate session');
            },
        });
    };

    const getSessionStatus = () => {
        if (!session.is_active) {
            return { label: 'Terminated', variant: 'secondary' as const, color: 'text-muted-foreground' };
        }
        if (session.is_current) {
            return { label: 'Current', variant: 'secondary' as const, color: 'text-green-600' };
        }
        return { label: 'Active', variant: 'outline' as const, color: 'text-green-600' };
    };

    const sessionStatus = getSessionStatus();
    const lastActivity = new Date(session.last_activity);
    const loginTime = session.login_at ? new Date(session.login_at) : null;

    return (
        <Card className={`${session.is_current ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : ''} ${!session.is_active ? 'opacity-60' : ''}`}>
            <CardContent className="p-4">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3 flex-1">
                        <div className="mt-0.5">
                            {getDeviceIcon(session.device_type)}
                        </div>
                        
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-2 flex-wrap">
                                <h4 className="font-medium text-sm truncate">
                                    {session.trusted_device?.device_name || `${session.browser || 'Unknown Browser'} on ${session.platform || 'Unknown OS'}`}
                                </h4>
                                
                                <Badge variant={sessionStatus.variant} className={`text-xs shrink-0 ${sessionStatus.color}`}>
                                    {session.is_current && <Activity className="h-3 w-3 mr-1" />}
                                    {sessionStatus.label}
                                </Badge>
                                
                                {session.trusted_device && (
                                    <Badge variant="outline" className="text-xs shrink-0">
                                        <Shield className="h-3 w-3 mr-1" />
                                        Trusted
                                    </Badge>
                                )}
                                
                                <Badge 
                                    variant="secondary" 
                                    className={`text-xs shrink-0 ${getDeviceTypeColor(session.device_type)}`}
                                >
                                    {session.device_type}
                                </Badge>
                            </div>
                            
                            <div className="space-y-1 text-xs text-muted-foreground">
                                <p className="truncate">{session.browser || 'Unknown Browser'} • {session.platform || 'Unknown OS'}</p>
                                
                                <div className="flex items-center gap-3 flex-wrap">
                                    <span className="flex items-center gap-1 shrink-0">
                                        <MapPin className="h-3 w-3" />
                                        {session.ip_address || 'Unknown'}
                                        {session.location && ` • ${session.location}`}
                                    </span>
                                    
                                    <span className="flex items-center gap-1 shrink-0">
                                        <Clock className="h-3 w-3" />
                                        Last active {session.last_activity ? formatDistanceToNow(new Date(session.last_activity), { addSuffix: true }) : 'Never'}
                                    </span>
                                </div>
                                
                                {loginTime && (
                                    <p className="text-xs">
                                        Signed in {formatDistanceToNow(loginTime, { addSuffix: true })}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                    
                    {session.is_active && !session.is_current && (
                        <div className="shrink-0">
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Trash2 className="h-3 w-3" />
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Terminate Session</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Are you sure you want to terminate this session? 
                                            The user will be signed out from that device.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction onClick={terminateSession}>
                                            Terminate Session
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}