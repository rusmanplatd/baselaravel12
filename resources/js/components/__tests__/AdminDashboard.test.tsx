import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import AdminDashboard from '../admin/AdminDashboard';

// Mock the admin API hook
const mockUseAdminApi = vi.fn();

vi.mock('@/hooks/useAdminApi', () => ({
    useAdminApi: () => mockUseAdminApi()
}));

// Mock toast
vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    }
}));

// Mock UI components
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, disabled }: any) => (
        <button onClick={onClick} disabled={disabled}>{children}</button>
    )
}));

vi.mock('@/components/ui/card', () => ({
    Card: ({ children }: any) => <div data-testid="card">{children}</div>,
    CardContent: ({ children }: any) => <div data-testid="card-content">{children}</div>,
    CardDescription: ({ children }: any) => <div data-testid="card-description">{children}</div>,
    CardHeader: ({ children }: any) => <div data-testid="card-header">{children}</div>,
    CardTitle: ({ children }: any) => <h3 data-testid="card-title">{children}</h3>
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: ({ children, value, onValueChange }: any) => (
        <div data-testid="tabs" data-value={value}>
            <div onClick={() => onValueChange?.('overview')}>Overview</div>
            <div onClick={() => onValueChange?.('users')}>Users</div>
            <div onClick={() => onValueChange?.('security')}>Security</div>
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

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children, variant }: any) => (
        <span data-testid="badge" data-variant={variant}>{children}</span>
    )
}));

vi.mock('@/components/ui/progress', () => ({
    Progress: ({ value }: any) => (
        <div data-testid="progress" data-value={value}>
            <div style={{ width: `${value}%` }} />
        </div>
    )
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: ({ children, className }: any) => (
        <div data-testid="alert" className={className}>{children}</div>
    ),
    AlertDescription: ({ children }: any) => <div>{children}</div>
}));

// Mock child components
vi.mock('@/components/security/SecurityDashboard', () => ({
    SecurityDashboard: ({ className }: any) => (
        <div data-testid="security-dashboard" className={className}>
            Security Dashboard Component
        </div>
    )
}));

vi.mock('@/components/webhooks/WebhookManager', () => ({
    WebhookManager: ({ className }: any) => (
        <div data-testid="webhook-manager" className={className}>
            Webhook Manager Component
        </div>
    )
}));

const mockStats = {
    total_users: 1500,
    active_users_24h: 250,
    total_conversations: 300,
    active_conversations_24h: 45,
    total_messages: 50000,
    messages_24h: 1200,
    encrypted_messages_percentage: 95,
    total_organizations: 10,
    active_webhooks: 5,
    failed_webhook_deliveries_24h: 2,
    video_calls_24h: 30,
    active_video_calls: 3,
    system_health_score: 85,
    storage_used_gb: 150,
    storage_limit_gb: 500,
    created_at: '2023-01-01T00:00:00Z',
    updated_at: '2023-01-01T00:00:00Z'
};

const mockServiceStatuses = [
    {
        service: 'database',
        status: 'healthy' as const,
        response_time_ms: 15,
        last_checked: '2023-01-01T00:00:00Z',
        uptime_percentage: 99.9
    },
    {
        service: 'redis',
        status: 'healthy' as const,
        response_time_ms: 8,
        last_checked: '2023-01-01T00:00:00Z',
        uptime_percentage: 99.8
    },
    {
        service: 'websocket',
        status: 'degraded' as const,
        response_time_ms: 120,
        last_checked: '2023-01-01T00:00:00Z',
        uptime_percentage: 98.5,
        error_message: 'High response time detected'
    }
];

const mockRecentActivity = [
    {
        id: '1',
        type: 'user_registration' as const,
        title: 'New user registered',
        description: 'John Doe created an account',
        timestamp: '2023-01-01T00:00:00Z',
        severity: 'info' as const,
        user: { id: '1', name: 'John Doe', email: 'john@example.com' },
        organization: { id: '1', name: 'Acme Corp' }
    },
    {
        id: '2',
        type: 'security_event' as const,
        title: 'Failed login attempt',
        description: 'Multiple failed login attempts detected',
        timestamp: '2023-01-01T00:01:00Z',
        severity: 'warning' as const,
        user: { id: '2', name: 'Jane Smith', email: 'jane@example.com' }
    }
];

describe('AdminDashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        
        // Mock fetch responses
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ stats: mockStats })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ services: mockServiceStatuses })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ activities: mockRecentActivity })
            });

        mockUseAdminApi.mockReturnValue({
            loading: false,
            error: null,
            fetchDashboardStats: vi.fn().mockResolvedValue(mockStats),
            fetchServiceStatuses: vi.fn().mockResolvedValue(mockServiceStatuses),
            fetchRecentActivity: vi.fn().mockResolvedValue(mockRecentActivity),
            fetchFullDashboard: vi.fn()
        });
    });

    it('renders dashboard header', () => {
        render(<AdminDashboard />);
        
        expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Monitor and manage your application\'s performance and security')).toBeInTheDocument();
    });

    it('displays system health alert when score is low', async () => {
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ 
                    stats: { ...mockStats, system_health_score: 75 }
                })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ services: mockServiceStatuses })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ activities: mockRecentActivity })
            });

        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText(/System health score is 75%/)).toBeInTheDocument();
        });
    });

    it('renders all dashboard tabs', () => {
        render(<AdminDashboard />);
        
        expect(screen.getByText('Overview')).toBeInTheDocument();
        expect(screen.getByText('Users')).toBeInTheDocument();
        expect(screen.getByText('Security')).toBeInTheDocument();
        expect(screen.getByText('Webhooks')).toBeInTheDocument();
        expect(screen.getByText('Services')).toBeInTheDocument();
        expect(screen.getByText('Activity')).toBeInTheDocument();
    });

    it('displays key metrics cards', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText('1,500')).toBeInTheDocument(); // Total Users
            expect(screen.getByText('50,000')).toBeInTheDocument(); // Total Messages
            expect(screen.getByText('95%')).toBeInTheDocument(); // Encryption Rate
            expect(screen.getByText('85%')).toBeInTheDocument(); // System Health
        });
    });

    it('shows storage usage information', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText('150GB / 500GB')).toBeInTheDocument();
        });
    });

    it('displays video call statistics', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText('3')).toBeInTheDocument(); // Active video calls
            expect(screen.getByText('30')).toBeInTheDocument(); // Video calls in 24h
        });
    });

    it('shows service status grid', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Redis')).toBeInTheDocument();
            expect(screen.getByText('Websocket')).toBeInTheDocument();
            expect(screen.getByText('healthy')).toBeInTheDocument();
            expect(screen.getByText('degraded')).toBeInTheDocument();
        });
    });

    it('handles tab switching', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const usersTab = screen.getByText('Users');
            fireEvent.click(usersTab);
            
            expect(screen.getByTestId('tabs')).toHaveAttribute('data-value', 'users');
        });
    });

    it('shows security dashboard when security tab is clicked', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const securityTab = screen.getByText('Security');
            fireEvent.click(securityTab);
            
            expect(screen.getByTestId('security-dashboard')).toBeInTheDocument();
        });
    });

    it('shows webhook manager when webhooks tab is clicked', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const webhooksTab = screen.getByText('Webhooks');
            fireEvent.click(webhooksTab);
            
            expect(screen.getByTestId('webhook-manager')).toBeInTheDocument();
        });
    });

    it('displays service details in services tab', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const servicesTab = screen.getByText('Services');
            fireEvent.click(servicesTab);
            
            // Should show detailed service information
            expect(screen.getByText('15ms')).toBeInTheDocument(); // Database response time
            expect(screen.getByText('99.9%')).toBeInTheDocument(); // Database uptime
        });
    });

    it('shows recent activity in activity tab', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const activityTab = screen.getByText('Activity');
            fireEvent.click(activityTab);
            
            expect(screen.getByText('New user registered')).toBeInTheDocument();
            expect(screen.getByText('Failed login attempt')).toBeInTheDocument();
            expect(screen.getByText('John Doe created an account')).toBeInTheDocument();
        });
    });

    it('handles refresh button click', async () => {
        const mockFetchStats = vi.fn();
        
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const refreshButton = screen.getByText('Refresh');
            fireEvent.click(refreshButton);
            
            // Should trigger data refresh
            expect(global.fetch).toHaveBeenCalled();
        });
    });

    it('shows loading state initially', () => {
        // Mock loading state
        global.fetch = vi.fn(() => new Promise(() => {})); // Never resolves
        
        render(<AdminDashboard />);
        
        expect(screen.getByText('Loading dashboard...')).toBeInTheDocument();
    });

    it('handles API errors gracefully', async () => {
        global.fetch = vi.fn().mockRejectedValue(new Error('API Error'));
        
        render(<AdminDashboard />);
        
        await waitFor(() => {
            // Should handle error without crashing
            expect(screen.getByText('Admin Dashboard')).toBeInTheDocument();
        });
    });

    it('shows error messages for degraded services', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const servicesTab = screen.getByText('Services');
            fireEvent.click(servicesTab);
            
            expect(screen.getByText('High response time detected')).toBeInTheDocument();
        });
    });

    it('displays user growth metrics in users tab', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const usersTab = screen.getByText('Users');
            fireEvent.click(usersTab);
            
            expect(screen.getByText('1,500')).toBeInTheDocument(); // Total users
            expect(screen.getByText('250')).toBeInTheDocument(); // Active users 24h
        });
    });

    it('shows conversation metrics', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            expect(screen.getByText('300')).toBeInTheDocument(); // Total conversations
            expect(screen.getByText('45')).toBeInTheDocument(); // Active conversations 24h
        });
    });

    it('displays system health progress bar', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const progressBar = screen.getByTestId('progress');
            expect(progressBar).toHaveAttribute('data-value', '85');
        });
    });

    it('handles service status colors correctly', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const healthyBadge = screen.getAllByTestId('badge').find(badge => 
                badge.textContent === 'healthy'
            );
            const degradedBadge = screen.getAllByTestId('badge').find(badge => 
                badge.textContent === 'degraded'
            );
            
            expect(healthyBadge).toHaveAttribute('data-variant', 'default');
            expect(degradedBadge).toHaveAttribute('data-variant', 'destructive');
        });
    });

    it('formats large numbers correctly', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            // Should format numbers with commas
            expect(screen.getByText('1,500')).toBeInTheDocument();
            expect(screen.getByText('50,000')).toBeInTheDocument();
        });
    });

    it('shows activity severity badges', async () => {
        render(<AdminDashboard />);
        
        await waitFor(() => {
            const activityTab = screen.getByText('Activity');
            fireEvent.click(activityTab);
            
            expect(screen.getByText('info')).toBeInTheDocument();
            expect(screen.getByText('warning')).toBeInTheDocument();
        });
    });
});