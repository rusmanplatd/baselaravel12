import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Wifi, WifiOff, RefreshCw, AlertCircle } from 'lucide-react';

interface WebSocketStatusProps {
    readonly className?: string;
}

export function WebSocketStatus({ className }: WebSocketStatusProps) {
    const [connectionState, setConnectionState] = useState<string>('unknown');
    const [lastError, setLastError] = useState<string | null>(null);
    const [isReconnecting, setIsReconnecting] = useState(false);

    useEffect(() => {
        if (!window.Echo) {
            setConnectionState('not_initialized');
            return;
        }

        const pusher = window.Echo.connector.pusher;

        const handleConnected = () => {
            setConnectionState('connected');
            setLastError(null);
            setIsReconnecting(false);
        };

        const handleDisconnected = () => {
            setConnectionState('disconnected');
        };

        const handleError = (error: any) => {
            setConnectionState('error');
            setLastError(error.message || 'Unknown error');
        };

        const handleStateChange = (states: any) => {
            console.log('WebSocket state change:', states);
            setConnectionState(states.current);
        };

        // Bind event listeners
        pusher.connection.bind('connected', handleConnected);
        pusher.connection.bind('disconnected', handleDisconnected);
        pusher.connection.bind('error', handleError);
        pusher.connection.bind('state_change', handleStateChange);

        // Set initial state
        setConnectionState(pusher.connection.state);

        // Cleanup
        return () => {
            pusher.connection.unbind('connected', handleConnected);
            pusher.connection.unbind('disconnected', handleDisconnected);
            pusher.connection.unbind('error', handleError);
            pusher.connection.unbind('state_change', handleStateChange);
        };
    }, []);

    const handleReconnect = () => {
        if (!window.Echo) return;

        setIsReconnecting(true);
        window.Echo.disconnect();

        // Reconnect after a short delay
        setTimeout(() => {
            window.Echo.connect();
        }, 1000);
    };

    const getStatusColor = (state: string) => {
        switch (state) {
            case 'connected':
                return 'bg-green-500';
            case 'connecting':
                return 'bg-yellow-500';
            case 'disconnected':
            case 'error':
                return 'bg-red-500';
            default:
                return 'bg-gray-500';
        }
    };

    const getStatusIcon = (state: string) => {
        switch (state) {
            case 'connected':
                return <Wifi className="h-4 w-4" />;
            case 'connecting':
                return <RefreshCw className="h-4 w-4 animate-spin" />;
            case 'disconnected':
            case 'error':
                return <WifiOff className="h-4 w-4" />;
            default:
                return <AlertCircle className="h-4 w-4" />;
        }
    };

    const getStatusText = (state: string) => {
        switch (state) {
            case 'connected':
                return 'Connected';
            case 'connecting':
                return 'Connecting...';
            case 'disconnected':
                return 'Disconnected';
            case 'error':
                return 'Error';
            case 'not_initialized':
                return 'Not Initialized';
            default:
                return 'Unknown';
        }
    };

    return (
        <Card className={className}>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium flex items-center gap-2">
                    WebSocket Status
                    <Badge
                        variant="secondary"
                        className={`${getStatusColor(connectionState)} text-white`}
                    >
                        {getStatusIcon(connectionState)}
                        <span className="ml-1">{getStatusText(connectionState)}</span>
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <div className="space-y-2">
                    <div className="text-xs text-muted-foreground">
                        State: <code className="bg-muted px-1 rounded">{connectionState}</code>
                    </div>

                    {lastError && (
                        <div className="text-xs text-red-600 bg-red-50 p-2 rounded">
                            <strong>Error:</strong> {lastError}
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleReconnect}
                            disabled={isReconnecting}
                            className="text-xs"
                        >
                            {isReconnecting ? (
                                <RefreshCw className="h-3 w-3 animate-spin mr-1" />
                            ) : (
                                <RefreshCw className="h-3 w-3 mr-1" />
                            )}
                            Reconnect
                        </Button>

                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                console.log('Echo instance:', window.Echo);
                                console.log('Pusher connection:', window.Echo?.connector?.pusher?.connection);
                                console.log('Connection state:', window.Echo?.connector?.pusher?.connection?.state);
                            }}
                            className="text-xs"
                        >
                            Debug
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
