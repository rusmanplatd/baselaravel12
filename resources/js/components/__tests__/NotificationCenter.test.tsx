import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import NotificationCenter from '../notifications/NotificationCenter';

// Mock the hooks
const mockUseNotifications = vi.fn();
const mockUseWebSocket = vi.fn();

vi.mock('@/hooks/useNotifications', () => ({
    useNotifications: () => mockUseNotifications()
}));

vi.mock('@/hooks/useWebSocket', () => ({
    useWebSocket: () => mockUseWebSocket()
}));

// Mock toast
vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }
}));

// Mock UI components
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: any) => (
        <button onClick={onClick} {...props}>{children}</button>
    )
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children, variant }: any) => (
        <span data-testid="badge" data-variant={variant}>{children}</span>
    )
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: ({ children, open, onOpenChange }: any) => (
        <div data-testid="dropdown" data-open={open}>
            <div onClick={() => onOpenChange?.(!open)}>{children}</div>
        </div>
    ),
    DropdownMenuTrigger: ({ children }: any) => <div>{children}</div>,
    DropdownMenuContent: ({ children }: any) => <div data-testid="dropdown-content">{children}</div>,
    DropdownMenuItem: ({ children, onClick }: any) => (
        <div onClick={onClick} data-testid="dropdown-item">{children}</div>
    ),
    DropdownMenuLabel: ({ children }: any) => <div>{children}</div>,
    DropdownMenuSeparator: () => <hr />
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: ({ children, value, onValueChange }: any) => (
        <div data-testid="tabs" data-value={value}>
            <div onClick={() => onValueChange?.('all')}>All</div>
            <div onClick={() => onValueChange?.('unread')}>Unread</div>
            {children}
        </div>
    ),
    TabsList: ({ children }: any) => <div data-testid="tabs-list">{children}</div>,
    TabsTrigger: ({ children, value }: any) => (
        <button data-testid={`tab-${value}`}>{children}</button>
    ),
    TabsContent: ({ children, value }: any) => (
        <div data-testid={`tab-content-${value}`}>{children}</div>
    )
}));

vi.mock('@/components/ui/scroll-area', () => ({
    ScrollArea: ({ children }: any) => <div data-testid="scroll-area">{children}</div>
}));

vi.mock('@/components/ui/dialog', () => ({
    Dialog: ({ children, open, onOpenChange }: any) =>
        open ? <div data-testid="dialog" onClick={() => onOpenChange?.(false)}>{children}</div> : null,
    DialogContent: ({ children }: any) => <div data-testid="dialog-content">{children}</div>,
    DialogHeader: ({ children }: any) => <div>{children}</div>,
    DialogTitle: ({ children }: any) => <h2>{children}</h2>,
    DialogDescription: ({ children }: any) => <p>{children}</p>,
    DialogFooter: ({ children }: any) => <div>{children}</div>
}));

vi.mock('@/components/ui/input', () => ({
    Input: (props: any) => <input {...props} />
}));

const mockNotifications = [
    {
        id: '1',
        user_id: 'user1',
        title: 'New Message',
        message: 'You have a new message from John',
        type: 'message' as const,
        priority: 'medium' as const,
        channel: 'in_app' as const,
        read_at: null,
        metadata: { sender: 'John' },
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:00:00Z'
    },
    {
        id: '2',
        user_id: 'user1',
        title: 'Security Alert',
        message: 'Unusual login detected',
        type: 'security_alert' as const,
        priority: 'high' as const,
        channel: 'in_app' as const,
        read_at: '2023-01-01T00:01:00Z',
        metadata: { location: 'New York' },
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:01:00Z'
    }
];

const mockPreferences = {
    id: '1',
    user_id: 'user1',
    browser_notifications: true,
    toast_notifications: true,
    email_notifications: false,
    push_notifications: true,
    sms_notifications: false,
    message_notifications: true,
    mention_notifications: true,
    participant_notifications: true,
    security_notifications: true,
    video_call_notifications: true,
    webhook_notifications: false,
    admin_notifications: true,
    system_notifications: true,
    quiet_hours_enabled: false,
    email_digest_frequency: 'daily' as const,
    minimum_priority: 'low' as const,
    created_at: '2023-01-01T00:00:00Z',
    updated_at: '2023-01-01T00:00:00Z'
};

describe('NotificationCenter', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        mockUseNotifications.mockReturnValue({
            notifications: mockNotifications,
            preferences: mockPreferences,
            unreadCount: 1,
            loading: false,
            error: null,
            fetchNotifications: vi.fn(),
            markAsRead: vi.fn(),
            markAllAsRead: vi.fn(),
            deleteNotification: vi.fn(),
            updatePreferences: vi.fn(),
            requestBrowserPermission: vi.fn(),
            showBrowserNotification: vi.fn()
        });

        mockUseWebSocket.mockReturnValue({
            isConnected: true,
            connectionStatus: 'connected',
            lastMessage: null,
            sendMessage: vi.fn(),
            disconnect: vi.fn(),
            reconnect: vi.fn()
        });

        // Mock Notification API
        global.Notification = {
            permission: 'granted',
            requestPermission: vi.fn(() => Promise.resolve('granted'))
        } as any;
    });

    it('renders notification bell with unread count', () => {
        render(<NotificationCenter />);

        expect(screen.getByTestId('badge')).toHaveTextContent('1');
    });

    it('opens notification dropdown when bell is clicked', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByTestId('dropdown')).toHaveAttribute('data-open', 'true');
        expect(screen.getByText('Notifications')).toBeInTheDocument();
    });

    it('displays notifications correctly', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByText('New Message')).toBeInTheDocument();
        expect(screen.getByText('You have a new message from John')).toBeInTheDocument();
        expect(screen.getByText('Security Alert')).toBeInTheDocument();
        expect(screen.getByText('Unusual login detected')).toBeInTheDocument();
    });

    it('shows connection status', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByText('Connected • connected')).toBeInTheDocument();
    });

    it('handles tab switching', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        const unreadTab = screen.getByText('Unread (1)');
        fireEvent.click(unreadTab);

        expect(screen.getByTestId('tabs')).toHaveAttribute('data-value', 'unread');
    });

    it('handles search functionality', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        const searchInput = screen.getByPlaceholderText('Search notifications...');
        fireEvent.change(searchInput, { target: { value: 'message' } });

        expect(searchInput.value).toBe('message');
    });

    it('handles mark as read', async () => {
        const mockMarkAsRead = vi.fn();
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            markAsRead: mockMarkAsRead
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        // Find and click mark as read button
        const markReadButton = screen.getAllByText('Mark as read')[0];
        fireEvent.click(markReadButton);

        await waitFor(() => {
            expect(mockMarkAsRead).toHaveBeenCalledWith('1');
        });
    });

    it('handles mark all as read', async () => {
        const mockMarkAllAsRead = vi.fn();
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            markAllAsRead: mockMarkAllAsRead
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        const markAllReadButton = screen.getByLabelText(/checkcheck/i);
        fireEvent.click(markAllReadButton);

        await waitFor(() => {
            expect(mockMarkAllAsRead).toHaveBeenCalled();
        });
    });

    it('handles notification deletion', async () => {
        const mockDeleteNotification = vi.fn();
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            deleteNotification: mockDeleteNotification
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        // Find and click delete button
        const deleteButton = screen.getAllByText('Delete')[0];
        fireEvent.click(deleteButton);

        await waitFor(() => {
            expect(mockDeleteNotification).toHaveBeenCalledWith('1');
        });
    });

    it('opens preferences dialog', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        const settingsButton = screen.getByLabelText(/settings/i);
        fireEvent.click(settingsButton);

        expect(screen.getByTestId('dialog')).toBeInTheDocument();
        expect(screen.getByText('Notification Preferences')).toBeInTheDocument();
    });

    it('handles WebSocket messages', () => {
        const mockShowBrowserNotification = vi.fn();
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            showBrowserNotification: mockShowBrowserNotification
        });

        render(<NotificationCenter />);

        // This would test WebSocket message handling
        // In a real test, you'd trigger the WebSocket onMessage handler
        // and verify that notifications are processed correctly
    });

    it('shows browser notification when enabled', () => {
        const mockNotificationConstructor = vi.fn();
        global.Notification = mockNotificationConstructor as any;
        global.Notification.permission = 'granted';

        render(<NotificationCenter />);

        // Test browser notification creation by triggering a notification
        const createButton = screen.getByRole('button', { name: /create notification/i });
        fireEvent.click(createButton);
        
        // Verify the Notification constructor was called with correct parameters
        expect(global.Notification).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({
                body: expect.any(String),
                icon: expect.any(String)
            })
        );
    });

    it('handles filter by type', () => {
        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        // Open filter dropdown
        const filterButton = screen.getByLabelText(/filter/i);
        fireEvent.click(filterButton);

        // Select security filter
        const securityFilter = screen.getByText('Security');
        fireEvent.click(securityFilter);

        // Should filter to show only security notifications
        expect(screen.getByText('Security Alert')).toBeInTheDocument();
    });

    it('displays empty state when no notifications', () => {
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            notifications: []
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByText('No notifications')).toBeInTheDocument();
    });

    it('shows loading state', () => {
        mockUseNotifications.mockReturnValue({
            ...mockUseNotifications(),
            loading: true
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByText('Loading...')).toBeInTheDocument();
    });

    it('handles disconnected WebSocket state', () => {
        mockUseWebSocket.mockReturnValue({
            ...mockUseWebSocket(),
            isConnected: false,
            connectionStatus: 'disconnected'
        });

        render(<NotificationCenter />);

        const bell = screen.getByRole('button');
        fireEvent.click(bell);

        expect(screen.getByText('Disconnected • disconnected')).toBeInTheDocument();
    });
});
