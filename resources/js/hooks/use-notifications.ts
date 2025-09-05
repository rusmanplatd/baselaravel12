import { Notification } from '@/components/ui/notification-panel';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

interface UseNotificationsReturn {
    notifications: Notification[];
    unreadCount: number;
    addNotification: (notification: Omit<Notification, 'id' | 'createdAt'>) => void;
    markAsRead: (id: string) => void;
    markAllAsRead: () => void;
    removeNotification: (id: string) => void;
    clearAll: () => void;
}

// Mock data for demonstration - in production this would come from API
const mockNotifications: Notification[] = [
    {
        id: '1',
        title: 'Welcome to the platform!',
        message: 'Your account has been successfully created and verified.',
        type: 'success',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 5).toISOString(), // 5 minutes ago
    },
    {
        id: '2',
        title: 'Security Update',
        message: 'Please review your security settings and enable two-factor authentication.',
        type: 'warning',
        read: false,
        createdAt: new Date(Date.now() - 1000 * 60 * 30).toISOString(), // 30 minutes ago
    },
    {
        id: '3',
        title: 'System notification',
        message: 'Your profile information has been updated successfully.',
        type: 'info',
        read: true,
        createdAt: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(), // 2 hours ago
    },
];

export function useNotifications(): UseNotificationsReturn {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [isInitialized, setIsInitialized] = useState(false);

    // Initialize notifications from localStorage or mock data
    useEffect(() => {
        if (!isInitialized) {
            const stored = localStorage.getItem('notifications');
            if (stored) {
                try {
                    setNotifications(JSON.parse(stored));
                } catch {
                    setNotifications(mockNotifications);
                }
            } else {
                setNotifications(mockNotifications);
            }
            setIsInitialized(true);
        }
    }, [isInitialized]);

    // Save notifications to localStorage whenever they change
    useEffect(() => {
        if (isInitialized) {
            localStorage.setItem('notifications', JSON.stringify(notifications));
        }
    }, [notifications, isInitialized]);

    const unreadCount = notifications.filter(n => !n.read).length;

    const addNotification = useCallback((notification: Omit<Notification, 'id' | 'createdAt'>) => {
        const newNotification: Notification = {
            ...notification,
            id: Date.now().toString(),
            createdAt: new Date().toISOString(),
        };
        
        setNotifications(prev => [newNotification, ...prev]);
    }, []);

    const markAsRead = useCallback((id: string) => {
        setNotifications(prev =>
            prev.map(n => n.id === id ? { ...n, read: true } : n)
        );
    }, []);

    const markAllAsRead = useCallback(() => {
        setNotifications(prev =>
            prev.map(n => ({ ...n, read: true }))
        );
    }, []);

    const removeNotification = useCallback((id: string) => {
        setNotifications(prev => prev.filter(n => n.id !== id));
    }, []);

    const clearAll = useCallback(() => {
        setNotifications([]);
    }, []);

    // Listen for Inertia page visits to potentially fetch new notifications
    useEffect(() => {
        const handleVisit = () => {
            // In production, you might want to fetch new notifications here
            // For now, we'll just use the mock data
        };

        const removeListener = router.on('navigate', handleVisit);
        return removeListener;
    }, []);

    return {
        notifications,
        unreadCount,
        addNotification,
        markAsRead,
        markAllAsRead,
        removeNotification,
        clearAll,
    };
}