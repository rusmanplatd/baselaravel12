import { useEffect, useState } from 'react';
import { User, Message } from '@/types/chat';
import { messageHasMentionForUser } from '@/utils/mentions';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { XMarkIcon, BellIcon } from '@heroicons/react/24/outline';

interface MentionNotification {
  id: string;
  message: Message;
  conversationName: string;
  timestamp: Date;
  isRead: boolean;
}

interface MentionNotificationProps {
  readonly messages: Message[];
  readonly currentUserId: string;
  readonly conversationName?: string;
  readonly onNotificationClick?: (message: Message) => void;
  readonly onMarkAsRead?: (notificationId: string) => void;
}

export default function MentionNotifications({
  messages,
  currentUserId,
  conversationName = 'Chat',
  onNotificationClick,
  onMarkAsRead
}: MentionNotificationProps) {
  const [notifications, setNotifications] = useState<MentionNotification[]>([]);
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    // Check for new mentions in recent messages
    const newMentions = messages
      .filter(message => {
        // Only show notifications for messages that mention current user
        // and are not sent by current user
        return message.sender_id !== currentUserId && 
               messageHasMentionForUser(message, currentUserId);
      })
      .slice(-5) // Only keep last 5 mentions
      .map(message => ({
        id: `mention-${message.id}`,
        message,
        conversationName,
        timestamp: new Date(message.created_at),
        isRead: false
      }));

    if (newMentions.length > 0) {
      setNotifications(prev => {
        // Merge new mentions with existing, avoiding duplicates
        const existing = prev.filter(n => !newMentions.some(nm => nm.id === n.id));
        return [...existing, ...newMentions];
      });
      setIsVisible(true);
      
      // Auto-hide after 10 seconds if not interacted with
      const timer = setTimeout(() => {
        setIsVisible(false);
      }, 10000);
      
      return () => clearTimeout(timer);
    }
  }, [messages, currentUserId, conversationName]);

  const handleNotificationClick = (notification: MentionNotification) => {
    onNotificationClick?.(notification.message);
    handleMarkAsRead(notification.id);
  };

  const handleMarkAsRead = (notificationId: string) => {
    setNotifications(prev => 
      prev.map(n => 
        n.id === notificationId ? { ...n, isRead: true } : n
      )
    );
    onMarkAsRead?.(notificationId);
  };

  const handleDismissAll = () => {
    setIsVisible(false);
    setNotifications([]);
  };

  const unreadCount = notifications.filter(n => !n.isRead).length;

  if (!isVisible || notifications.length === 0) {
    return null;
  }

  return (
    <div className="fixed top-4 right-4 z-50 w-80 max-w-sm">
      <div className="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
        {/* Header */}
        <div className="px-4 py-3 bg-blue-50 border-b border-blue-200 flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <BellIcon className="h-4 w-4 text-blue-600" />
            <span className="text-sm font-medium text-blue-900">
              {unreadCount} mention{unreadCount !== 1 ? 's' : ''}
            </span>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={handleDismissAll}
            className="h-6 w-6 p-0 text-gray-400 hover:text-gray-600"
          >
            <XMarkIcon className="h-4 w-4" />
          </Button>
        </div>

        {/* Notifications */}
        <div className="max-h-96 overflow-y-auto">
          {notifications.map((notification) => (
            <div
              key={notification.id}
              className={`px-4 py-3 border-b border-gray-100 cursor-pointer transition-colors hover:bg-gray-50 ${
                !notification.isRead ? 'bg-blue-50' : ''
              }`}
              onClick={() => handleNotificationClick(notification)}
            >
              <div className="flex items-start space-x-3">
                <Avatar className="h-8 w-8">
                  <AvatarImage 
                    src={(notification.message.sender as any)?.avatar_url} 
                    alt={notification.message.sender?.name || 'User'} 
                  />
                  <AvatarFallback className="text-xs">
                    {notification.message.sender?.name?.charAt(0).toUpperCase() || '?'}
                  </AvatarFallback>
                </Avatar>
                
                <div className="flex-1 min-w-0">
                  <div className="text-sm">
                    <span className="font-medium text-gray-900">
                      {notification.message.sender?.name || 'Unknown User'}
                    </span>
                    <span className="text-gray-500"> mentioned you in </span>
                    <span className="font-medium text-gray-900">
                      {notification.conversationName}
                    </span>
                  </div>
                  
                  <div className="mt-1 text-sm text-gray-600 truncate">
                    {notification.message.content || '[Message could not be decrypted]'}
                  </div>
                  
                  <div className="mt-1 text-xs text-gray-500">
                    {notification.timestamp.toLocaleTimeString([], { 
                      hour: '2-digit', 
                      minute: '2-digit' 
                    })}
                  </div>
                </div>
                
                {!notification.isRead && (
                  <div className="h-2 w-2 bg-blue-500 rounded-full"></div>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* Footer */}
        {notifications.length > 0 && (
          <div className="px-4 py-2 bg-gray-50 border-t border-gray-200">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleDismissAll}
              className="w-full text-xs text-gray-600 hover:text-gray-800"
            >
              Dismiss all
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}

// Export utility hook for mention notifications
export function useMentionNotifications(currentUserId: string) {
  const [hasUnreadMentions, setHasUnreadMentions] = useState(false);
  const [mentionCount, setMentionCount] = useState(0);

  const checkForMentions = (messages: Message[]) => {
    const mentions = messages.filter(message => 
      message.sender_id !== currentUserId && 
      messageHasMentionForUser(message, currentUserId)
    );
    
    setMentionCount(mentions.length);
    setHasUnreadMentions(mentions.length > 0);
  };

  return {
    hasUnreadMentions,
    mentionCount,
    checkForMentions
  };
}