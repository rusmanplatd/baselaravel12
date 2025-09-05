import React, { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Bell,
  BellOff,
  Settings,
  Check,
  CheckCheck,
  MessageSquare,
  Users,
  Shield,
  Video,
  AlertTriangle,
  Info,
  X,
  Filter,
  Search,
  MoreVertical
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { useNotifications } from '@/hooks/useNotifications';
import { useWebSocket } from '@/hooks/useWebSocket';
import { Notification, NotificationPreferences } from '@/types/notification';
import { toast } from 'sonner';

interface NotificationCenterProps {
  className?: string;
}

const NotificationCenter: React.FC<NotificationCenterProps> = ({ className }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedTab, setSelectedTab] = useState<'all' | 'unread' | 'mentions'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [showPreferences, setShowPreferences] = useState(false);
  const [filterType, setFilterType] = useState<string>('all');
  
  const {
    notifications,
    unreadCount,
    preferences,
    loading,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    updatePreferences
  } = useNotifications();

  const { 
    isConnected, 
    lastMessage, 
    connectionStatus 
  } = useWebSocket({
    url: `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws`,
    options: {
      onMessage: handleWebSocketMessage,
      onError: (error) => {
        console.error('WebSocket error:', error);
        toast.error('Connection error occurred');
      },
      onClose: () => {
        toast.warning('Real-time connection lost');
      },
      onOpen: () => {
        toast.success('Real-time connection established');
      }
    }
  });

  const bellRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  useEffect(() => {
    // Request permission for browser notifications
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  }, []);

  function handleWebSocketMessage(event: MessageEvent) {
    try {
      const data = JSON.parse(event.data);
      
      if (data.type === 'notification') {
        const notification: Notification = data.payload;
        
        // Show browser notification if enabled
        if (preferences?.browser_notifications && Notification.permission === 'granted') {
          new Notification(notification.title, {
            body: notification.message,
            icon: '/favicon.ico',
            tag: notification.id,
          });
        }
        
        // Show toast notification
        if (preferences?.toast_notifications) {
          toast(notification.title, {
            description: notification.message,
            action: {
              label: 'View',
              onClick: () => {
                setSelectedNotification(notification);
                setIsOpen(true);
              }
            }
          });
        }
        
        // Refresh notifications list
        fetchNotifications();
      }
    } catch (error) {
      console.error('Failed to parse WebSocket message:', error);
    }
  }

  const handleMarkAsRead = async (notificationId: string) => {
    try {
      await markAsRead(notificationId);
      fetchNotifications();
    } catch (error) {
      toast.error('Failed to mark notification as read');
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await markAllAsRead();
      fetchNotifications();
      toast.success('All notifications marked as read');
    } catch (error) {
      toast.error('Failed to mark all notifications as read');
    }
  };

  const handleDeleteNotification = async (notificationId: string) => {
    try {
      await deleteNotification(notificationId);
      fetchNotifications();
      toast.success('Notification deleted');
    } catch (error) {
      toast.error('Failed to delete notification');
    }
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'message':
        return <MessageSquare className="h-4 w-4" />;
      case 'mention':
        return <MessageSquare className="h-4 w-4 text-blue-500" />;
      case 'participant_joined':
      case 'participant_left':
        return <Users className="h-4 w-4" />;
      case 'security_alert':
        return <Shield className="h-4 w-4 text-red-500" />;
      case 'video_call_started':
      case 'video_call_ended':
        return <Video className="h-4 w-4" />;
      case 'system_alert':
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      default:
        return <Info className="h-4 w-4" />;
    }
  };

  const getNotificationPriority = (priority: string) => {
    switch (priority) {
      case 'high':
        return 'destructive';
      case 'medium':
        return 'default';
      case 'low':
        return 'secondary';
      default:
        return 'outline';
    }
  };

  const filteredNotifications = notifications.filter(notification => {
    // Tab filter
    if (selectedTab === 'unread' && notification.read_at) return false;
    if (selectedTab === 'mentions' && notification.type !== 'mention') return false;
    
    // Type filter
    if (filterType !== 'all' && notification.type !== filterType) return false;
    
    // Search filter
    if (searchQuery && !notification.title.toLowerCase().includes(searchQuery.toLowerCase()) &&
        !notification.message.toLowerCase().includes(searchQuery.toLowerCase())) {
      return false;
    }
    
    return true;
  });

  const handlePreferencesUpdate = async (newPreferences: Partial<NotificationPreferences>) => {
    try {
      await updatePreferences(newPreferences);
      toast.success('Notification preferences updated');
      setShowPreferences(false);
    } catch (error) {
      toast.error('Failed to update preferences');
    }
  };

  return (
    <div className={className}>
      {/* Notification Bell */}
      <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
        <DropdownMenuTrigger asChild>
          <Button
            ref={bellRef}
            variant="ghost"
            size="sm"
            className="relative h-9 w-9 rounded-full"
          >
            <Bell className="h-4 w-4" />
            {unreadCount > 0 && (
              <Badge
                variant="destructive"
                className="absolute -right-1 -top-1 h-5 w-5 rounded-full p-0 text-xs"
              >
                {unreadCount > 99 ? '99+' : unreadCount}
              </Badge>
            )}
          </Button>
        </DropdownMenuTrigger>
        
        <DropdownMenuContent 
          className="w-96 p-0" 
          align="end"
          onCloseAutoFocus={(e) => e.preventDefault()}
        >
          <div className="flex items-center justify-between p-4 border-b">
            <div className="flex items-center gap-2">
              <Bell className="h-5 w-5" />
              <span className="font-semibold">Notifications</span>
              {unreadCount > 0 && (
                <Badge variant="secondary">{unreadCount}</Badge>
              )}
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setShowPreferences(true)}
              >
                <Settings className="h-4 w-4" />
              </Button>
              {unreadCount > 0 && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleMarkAllAsRead}
                >
                  <CheckCheck className="h-4 w-4" />
                </Button>
              )}
            </div>
          </div>

          {/* Connection Status */}
          <div className="px-4 py-2 bg-muted/50">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <div className={`w-2 h-2 rounded-full ${
                isConnected ? 'bg-green-500' : 'bg-red-500'
              }`} />
              {isConnected ? 'Connected' : 'Disconnected'} • {connectionStatus}
            </div>
          </div>

          <Tabs value={selectedTab} onValueChange={(value: any) => setSelectedTab(value)}>
            <div className="px-4 pt-2">
              <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="unread">
                  Unread {unreadCount > 0 && `(${unreadCount})`}
                </TabsTrigger>
                <TabsTrigger value="mentions">Mentions</TabsTrigger>
              </TabsList>
              
              {/* Search and Filter */}
              <div className="flex items-center gap-2 mt-2">
                <div className="relative flex-1">
                  <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search notifications..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-8 h-9"
                  />
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                      <Filter className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Filter by type</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={() => setFilterType('all')}>
                      All Types
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterType('message')}>
                      Messages
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterType('mention')}>
                      Mentions
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterType('security_alert')}>
                      Security
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setFilterType('video_call_started')}>
                      Video Calls
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <TabsContent value={selectedTab} className="mt-2">
              <ScrollArea className="h-96">
                <div className="space-y-1 p-2">
                  {loading ? (
                    <div className="flex items-center justify-center h-32">
                      <div className="text-sm text-muted-foreground">Loading...</div>
                    </div>
                  ) : filteredNotifications.length === 0 ? (
                    <div className="flex items-center justify-center h-32">
                      <div className="text-sm text-muted-foreground">No notifications</div>
                    </div>
                  ) : (
                    filteredNotifications.map((notification) => (
                      <div
                        key={notification.id}
                        className={`p-3 rounded-lg border transition-colors cursor-pointer hover:bg-muted/50 ${
                          !notification.read_at ? 'bg-blue-50 border-blue-200' : 'bg-background'
                        }`}
                        onClick={() => setSelectedNotification(notification)}
                      >
                        <div className="flex items-start gap-3">
                          <div className="mt-0.5">
                            {getNotificationIcon(notification.type)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between">
                              <p className="text-sm font-medium truncate">
                                {notification.title}
                              </p>
                              <div className="flex items-center gap-1">
                                <Badge variant={getNotificationPriority(notification.priority)} className="text-xs">
                                  {notification.priority}
                                </Badge>
                                <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                                      <MoreVertical className="h-3 w-3" />
                                    </Button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent align="end">
                                    {!notification.read_at && (
                                      <DropdownMenuItem
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          handleMarkAsRead(notification.id);
                                        }}
                                      >
                                        <Check className="h-4 w-4 mr-2" />
                                        Mark as read
                                      </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        handleDeleteNotification(notification.id);
                                      }}
                                      className="text-red-600"
                                    >
                                      <X className="h-4 w-4 mr-2" />
                                      Delete
                                    </DropdownMenuItem>
                                  </DropdownMenuContent>
                                </DropdownMenu>
                              </div>
                            </div>
                            <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                              {notification.message}
                            </p>
                            <div className="flex items-center justify-between mt-2">
                              <span className="text-xs text-muted-foreground">
                                {new Date(notification.created_at).toLocaleString()}
                              </span>
                              {!notification.read_at && (
                                <div className="w-2 h-2 bg-blue-500 rounded-full" />
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </ScrollArea>
            </TabsContent>
          </Tabs>
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Notification Detail Dialog */}
      <Dialog 
        open={!!selectedNotification} 
        onOpenChange={() => setSelectedNotification(null)}
      >
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              {selectedNotification && getNotificationIcon(selectedNotification.type)}
              {selectedNotification?.title}
            </DialogTitle>
            <DialogDescription>
              {selectedNotification && new Date(selectedNotification.created_at).toLocaleString()}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <p className="text-sm">{selectedNotification?.message}</p>
            {selectedNotification?.metadata && (
              <div className="bg-muted p-3 rounded-lg">
                <h4 className="text-sm font-medium mb-2">Additional Details</h4>
                <pre className="text-xs text-muted-foreground overflow-auto">
                  {JSON.stringify(selectedNotification.metadata, null, 2)}
                </pre>
              </div>
            )}
            <div className="flex justify-end gap-2">
              {selectedNotification && !selectedNotification.read_at && (
                <Button
                  variant="outline"
                  onClick={() => {
                    handleMarkAsRead(selectedNotification.id);
                    setSelectedNotification(null);
                  }}
                >
                  Mark as Read
                </Button>
              )}
              <Button
                onClick={() => setSelectedNotification(null)}
              >
                Close
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Preferences Dialog */}
      <NotificationPreferencesDialog
        open={showPreferences}
        onOpenChange={setShowPreferences}
        preferences={preferences}
        onUpdate={handlePreferencesUpdate}
      />
    </div>
  );
};

// Notification Preferences Dialog Component
interface NotificationPreferencesDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  preferences: NotificationPreferences | null;
  onUpdate: (preferences: Partial<NotificationPreferences>) => Promise<void>;
}

const NotificationPreferencesDialog: React.FC<NotificationPreferencesDialogProps> = ({
  open,
  onOpenChange,
  preferences,
  onUpdate
}) => {
  const [localPreferences, setLocalPreferences] = useState<NotificationPreferences | null>(null);

  useEffect(() => {
    if (preferences) {
      setLocalPreferences({ ...preferences });
    }
  }, [preferences]);

  const handleSave = async () => {
    if (localPreferences) {
      await onUpdate(localPreferences);
    }
  };

  if (!localPreferences) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Notification Preferences</DialogTitle>
          <DialogDescription>
            Configure how you receive notifications
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-6">
          {/* General Settings */}
          <div className="space-y-4">
            <h4 className="text-sm font-medium">General</h4>
            <div className="space-y-3">
              <label className="flex items-center justify-between">
                <span className="text-sm">Browser notifications</span>
                <input
                  type="checkbox"
                  checked={localPreferences.browser_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    browser_notifications: e.target.checked
                  })}
                />
              </label>
              <label className="flex items-center justify-between">
                <span className="text-sm">Toast notifications</span>
                <input
                  type="checkbox"
                  checked={localPreferences.toast_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    toast_notifications: e.target.checked
                  })}
                />
              </label>
              <label className="flex items-center justify-between">
                <span className="text-sm">Email notifications</span>
                <input
                  type="checkbox"
                  checked={localPreferences.email_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    email_notifications: e.target.checked
                  })}
                />
              </label>
            </div>
          </div>

          {/* Notification Types */}
          <div className="space-y-4">
            <h4 className="text-sm font-medium">Notification Types</h4>
            <div className="space-y-3">
              <label className="flex items-center justify-between">
                <span className="text-sm">New messages</span>
                <input
                  type="checkbox"
                  checked={localPreferences.message_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    message_notifications: e.target.checked
                  })}
                />
              </label>
              <label className="flex items-center justify-between">
                <span className="text-sm">Mentions</span>
                <input
                  type="checkbox"
                  checked={localPreferences.mention_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    mention_notifications: e.target.checked
                  })}
                />
              </label>
              <label className="flex items-center justify-between">
                <span className="text-sm">Security alerts</span>
                <input
                  type="checkbox"
                  checked={localPreferences.security_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    security_notifications: e.target.checked
                  })}
                />
              </label>
              <label className="flex items-center justify-between">
                <span className="text-sm">Video calls</span>
                <input
                  type="checkbox"
                  checked={localPreferences.video_call_notifications}
                  onChange={(e) => setLocalPreferences({
                    ...localPreferences,
                    video_call_notifications: e.target.checked
                  })}
                />
              </label>
            </div>
          </div>

          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave}>
              Save Changes
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default NotificationCenter;