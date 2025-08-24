import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { type SecurityAlert } from '@/types';
import { AlertTriangle, MapPin, Activity, Shield, X } from 'lucide-react';
import { useState } from 'react';

interface SecurityAlertCardProps {
    alert: SecurityAlert;
    onDismiss?: () => void;
}

export function SecurityAlertCard({ alert, onDismiss }: SecurityAlertCardProps) {
    const [dismissed, setDismissed] = useState(false);
    
    if (dismissed) return null;

    const getAlertIcon = (alertType: SecurityAlert['type']) => {
        switch (alertType) {
            case 'multiple_locations':
                return <MapPin className="h-4 w-4" />;
            case 'multiple_sessions':
                return <Activity className="h-4 w-4" />;
            case 'untrusted_devices':
                return <Shield className="h-4 w-4" />;
            default:
                return <AlertTriangle className="h-4 w-4" />;
        }
    };

    const getAlertVariant = (alertType: SecurityAlert['type']) => {
        switch (alertType) {
            case 'multiple_locations':
                return 'destructive' as const;
            case 'multiple_sessions':
                return 'default' as const;
            case 'untrusted_devices':
                return 'default' as const;
            default:
                return 'destructive' as const;
        }
    };

    const getAlertDetails = () => {
        switch (alert.type) {
            case 'multiple_locations':
                return {
                    title: 'Multiple Login Locations',
                    description: alert.message,
                    details: alert.data.ip_count ? `${alert.data.ip_count} different IP addresses detected` : undefined,
                };
            case 'multiple_sessions':
                return {
                    title: 'High Session Activity',
                    description: alert.message,
                    details: alert.data.session_count ? `${alert.data.session_count} active sessions` : undefined,
                };
            case 'untrusted_devices':
                return {
                    title: 'Untrusted Device Access',
                    description: alert.message,
                    details: alert.data.untrusted_count ? `${alert.data.untrusted_count} sessions from untrusted devices` : undefined,
                };
            default:
                return {
                    title: 'Security Alert',
                    description: alert.message,
                    details: undefined,
                };
        }
    };

    const alertDetails = getAlertDetails();
    const variant = getAlertVariant(alert.type);

    const handleDismiss = () => {
        setDismissed(true);
        onDismiss?.();
    };

    return (
        <Alert variant={variant} className="relative">
            <div className="flex items-start gap-2">
                {getAlertIcon(alert.type)}
                <div className="flex-1">
                    <div className="font-medium text-sm mb-1">
                        {alertDetails.title}
                    </div>
                    <AlertDescription className="text-sm">
                        {alertDetails.description}
                        {alertDetails.details && (
                            <div className="mt-1 text-xs opacity-80">
                                {alertDetails.details}
                            </div>
                        )}
                    </AlertDescription>
                </div>
                {onDismiss && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleDismiss}
                        className="h-6 w-6 p-0 hover:bg-transparent"
                    >
                        <X className="h-3 w-3" />
                    </Button>
                )}
            </div>
        </Alert>
    );
}