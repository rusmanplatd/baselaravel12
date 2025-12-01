import { useState, useCallback, useEffect } from 'react';
import { apiService } from '@/services/ApiService';
import {
  Notification,
  NotificationPreferences,
  NotificationListResponse,
  NotificationCreateData,
  NotificationUpdateData,
  NotificationStats
} from '@/types/notification';

export const useNotifications = () => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [preferences, setPreferences] = useState<NotificationPreferences | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const url = `/api/v1/notifications/${endpoint}`;
      const method = (options.method || 'GET').toUpperCase();
      const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers as Record<string, string> | undefined),
      };

      let data: any;
      switch (method) {
        case 'GET':
          data = await apiService.get(url, { headers });
          break;
        case 'POST':
          data = await apiService.post(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'PUT':
          data = await apiService.put(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'PATCH':
          data = await apiService.patch(url, options.body ? JSON.parse(String(options.body)) : undefined, { headers });
          break;
        case 'DELETE':
          data = await apiService.delete(url, { headers });
          break;
        default:
          data = await apiService.request(url, { ...options, headers });
      }

      return data;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred';
      setError(errorMessage);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  // Fetch notifications list
  const fetchNotifications = useCallback(async (params: {
    page?: number;
    limit?: number;
    unread_only?: boolean;
    type?: string;
  } = {}): Promise<void> => {
    try {
      const queryParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          queryParams.append(key, String(value));
        }
      });

      const endpoint = queryParams.toString() ? `?${queryParams.toString()}` : '';
      const response = await makeApiCall<NotificationListResponse>(endpoint);

      setNotifications(response.notifications);
      setUnreadCount(response.unread_count);
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
    }
  }, []);

  // Fetch user preferences
  const fetchPreferences = useCallback(async (): Promise<void> => {
    try {
      const response = await makeApiCall<{ preferences: NotificationPreferences }>('preferences');
      setPreferences(response.preferences);
    } catch (error) {
      console.error('Failed to fetch preferences:', error);
    }
  }, []);

  // Mark notification as read
  const markAsRead = useCallback(async (notificationId: string): Promise<void> => {
    await makeApiCall<{ success: boolean }>(`${notificationId}/read`, {
      method: 'PATCH',
      body: JSON.stringify({ read_at: new Date().toISOString() }),
    });
  }, []);

  // Mark all notifications as read
  const markAllAsRead = useCallback(async (): Promise<void> => {
    await makeApiCall<{ success: boolean }>('mark-all-read', {
      method: 'PATCH',
    });
  }, []);

  // Mark notification as clicked
  const markAsClicked = useCallback(async (notificationId: string): Promise<void> => {
    await makeApiCall<{ success: boolean }>(`${notificationId}/clicked`, {
      method: 'PATCH',
      body: JSON.stringify({ clicked_at: new Date().toISOString() }),
    });
  }, []);

  // Dismiss notification
  const dismissNotification = useCallback(async (notificationId: string): Promise<void> => {
    await makeApiCall<{ success: boolean }>(`${notificationId}/dismiss`, {
      method: 'PATCH',
      body: JSON.stringify({ dismissed_at: new Date().toISOString() }),
    });
  }, []);

  // Delete notification
  const deleteNotification = useCallback(async (notificationId: string): Promise<void> => {
    await makeApiCall<{ success: boolean }>(`${notificationId}`, {
      method: 'DELETE',
    });
  }, []);

  // Create notification (admin)
  const createNotification = useCallback(async (data: NotificationCreateData): Promise<Notification> => {
    const response = await makeApiCall<{ notification: Notification }>('', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    return response.notification;
  }, []);

  // Update notification preferences
  const updatePreferences = useCallback(async (data: Partial<NotificationPreferences>): Promise<void> => {
    const response = await makeApiCall<{ preferences: NotificationPreferences }>('preferences', {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
    setPreferences(response.preferences);
  }, []);

  // Get notification statistics
  const getStats = useCallback(async (params: {
    date_from?: string;
    date_to?: string;
    organization_id?: string;
  } = {}): Promise<NotificationStats> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `stats?${queryParams.toString()}` : 'stats';
    const response = await makeApiCall<{ stats: NotificationStats }>(endpoint);
    return response.stats;
  }, []);

  // Test notification
  const testNotification = useCallback(async (type: string): Promise<void> => {
    await makeApiCall<{ success: boolean }>('test', {
      method: 'POST',
      body: JSON.stringify({ type }),
    });
  }, []);

  // Request browser notification permission
  const requestBrowserPermission = useCallback(async (): Promise<NotificationPermission> => {
    if (!('Notification' in window)) {
      throw new Error('Browser notifications not supported');
    }

    if (Notification.permission === 'granted') {
      return 'granted';
    }

    if (Notification.permission === 'denied') {
      return 'denied';
    }

    const permission = await Notification.requestPermission();

    // Update preferences if permission granted
    if (permission === 'granted' && preferences) {
      await updatePreferences({
        ...preferences,
        browser_notifications: true
      });
    }

    return permission;
  }, [preferences, updatePreferences]);

  // Show browser notification
  const showBrowserNotification = useCallback((notification: Notification): void => {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    const browserNotification = new Notification(notification.title, {
      body: notification.message,
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      tag: notification.id,
      data: notification,
      requireInteraction: notification.priority === 'high' || notification.priority === 'critical',
      silent: notification.priority === 'low',
    });

    browserNotification.onclick = () => {
      window.focus();
      markAsClicked(notification.id);
      browserNotification.close();
    };

    // Auto-close after delay based on priority
    const autoCloseDelay = {
      'low': 3000,
      'medium': 5000,
      'high': 8000,
      'critical': 0, // Don't auto-close critical notifications
    }[notification.priority] || 5000;

    if (autoCloseDelay > 0) {
      setTimeout(() => {
        browserNotification.close();
      }, autoCloseDelay);
    }
  }, [markAsClicked]);

  // Initialize on mount
  useEffect(() => {
    fetchNotifications();
    fetchPreferences();
  }, [fetchNotifications, fetchPreferences]);

  // Update unread count when notifications change
  useEffect(() => {
    const unread = notifications.filter(n => !n.read_at).length;
    setUnreadCount(unread);
  }, [notifications]);

  return {
    notifications,
    preferences,
    unreadCount,
    loading,
    error,
    fetchNotifications,
    fetchPreferences,
    markAsRead,
    markAllAsRead,
    markAsClicked,
    dismissNotification,
    deleteNotification,
    createNotification,
    updatePreferences,
    getStats,
    testNotification,
    requestBrowserPermission,
    showBrowserNotification,
  };
};
