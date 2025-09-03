import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Bell, Check, X } from 'lucide-react';
import { useState } from 'react';

export interface Notification {
    id: string;
    title: string;
    message: string;
    type: 'info' | 'success' | 'warning' | 'error';
    read: boolean;
    createdAt: string;
    actionUrl?: string;
}

interface NotificationPanelProps {
    readonly notifications: Notification[];
    readonly onMarkAsRead: (id: string) => void;
    readonly onMarkAllAsRead: () => void;
    readonly onRemove: (id: string) => void;
    readonly className?: string;
}

export function NotificationPanel({
    notifications = [],
    onMarkAsRead,
    onMarkAllAsRead,
    onRemove,
    className
}: NotificationPanelProps) {
    const [isOpen, setIsOpen] = useState(false);
    
    const unreadCount = notifications.filter(n => !n.read).length;

    const getNotificationBadgeVariant = (type: Notification['type']) => {
        switch (type) {
            case 'error':
                return 'destructive';
            case 'warning':
                return 'secondary';
            case 'success':
                return 'default';
            default:
                return 'outline';
        }
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));
        
        if (diffInMinutes < 1) return 'Just now';
        if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
        if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
        return date.toLocaleDateString();
    };

    return (
        <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn("group relative h-9 w-9", className)}
                >
                    <Bell className="!size-5 opacity-80 group-hover:opacity-100" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -right-1 -top-1 h-5 w-5 rounded-full p-0 text-xs"
                        >
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-96 p-0"
                sideOffset={8}
            >
                <Card className="border-0 shadow-none">
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-base font-medium">
                                Notifications
                            </CardTitle>
                            {unreadCount > 0 && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={onMarkAllAsRead}
                                    className="h-8 px-2 text-xs"
                                >
                                    Mark all read
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                                No notifications yet
                            </div>
                        ) : (
                            <ScrollArea className="h-96">
                                <div className="space-y-0">
                                    {notifications.map((notification, index) => (
                                        <div key={notification.id}>
                                            <div
                                                className={cn(
                                                    "group relative flex items-start gap-3 p-4 transition-colors hover:bg-muted/50",
                                                    !notification.read && "bg-blue-50/50 dark:bg-blue-950/20"
                                                )}
                                            >
                                                {!notification.read && (
                                                    <div className="absolute left-2 top-6 h-2 w-2 rounded-full bg-blue-500" />
                                                )}
                                                
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="text-sm font-medium leading-none">
                                                            {notification.title}
                                                        </p>
                                                        <Badge
                                                            variant={getNotificationBadgeVariant(notification.type)}
                                                            className="h-4 px-1 text-xs"
                                                        >
                                                            {notification.type}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {notification.message}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatTime(notification.createdAt)}
                                                    </p>
                                                </div>

                                                <div className="flex gap-1 opacity-0 group-hover:opacity-100">
                                                    {!notification.read && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() => onMarkAsRead(notification.id)}
                                                        >
                                                            <Check className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        onClick={() => onRemove(notification.id)}
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                            {index < notifications.length - 1 && <Separator />}
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        )}
                    </CardContent>
                </Card>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}