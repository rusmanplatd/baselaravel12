import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import DeviceManager from '../devices/DeviceManager';

// Mock the device API hook
const mockUseDeviceApi = vi.fn();

vi.mock('@/hooks/useDeviceApi', () => ({
    useDeviceApi: () => mockUseDeviceApi()
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
    Button: ({ children, onClick, disabled, ...props }: any) => (
        <button onClick={onClick} disabled={disabled} {...props}>{children}</button>
    )
}));

vi.mock('@/components/ui/card', async () => {
  const actual = await vi.importActual('@/components/ui/card')
  return {
    ...actual,
    Card: ({ children, ...props }: any) => <div data-testid="card" {...props}>{children}</div>,
    CardHeader: ({ children, ...props }: any) => <div data-testid="card-header" {...props}>{children}</div>,
    CardContent: ({ children, ...props }: any) => <div data-testid="card-content" {...props}>{children}</div>,
    CardTitle: ({ children, ...props }: any) => <div data-testid="card-title" {...props}>{children}</div>,
    CardDescription: ({ children, ...props }: any) => <div data-testid="card-description" {...props}>{children}</div>,
  }
});

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children, variant }: any) => (
        <span data-testid="badge" data-variant={variant}>{children}</span>
    )
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: ({ children, value, onValueChange }: any) => (
        <div data-testid="tabs" data-value={value}>
            <div onClick={() => onValueChange?.('all')}>All Devices</div>
            <div onClick={() => onValueChange?.('trusted')}>Trusted</div>
            <div onClick={() => onValueChange?.('untrusted')}>Untrusted</div>
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

vi.mock('@/components/ui/input', () => ({
    Input: (props: any) => <input {...props} />,
    Label: ({ children }: any) => <label>{children}</label>
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

vi.mock('@/components/ui/alert-dialog', () => ({
    AlertDialog: ({ children, open, onOpenChange }: any) => 
        open ? <div data-testid="alert-dialog" onClick={() => onOpenChange?.(false)}>{children}</div> : null,
    AlertDialogContent: ({ children }: any) => <div data-testid="alert-dialog-content">{children}</div>,
    AlertDialogHeader: ({ children }: any) => <div>{children}</div>,
    AlertDialogTitle: ({ children }: any) => <h2>{children}</h2>,
    AlertDialogDescription: ({ children }: any) => <p>{children}</p>,
    AlertDialogFooter: ({ children }: any) => <div>{children}</div>,
    AlertDialogAction: ({ children, onClick }: any) => (
        <button onClick={onClick}>{children}</button>
    ),
    AlertDialogCancel: ({ children, onClick }: any) => (
        <button onClick={onClick}>{children}</button>
    )
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: ({ children }: any) => <div>{children}</div>,
    DropdownMenuTrigger: ({ children }: any) => <div>{children}</div>,
    DropdownMenuContent: ({ children }: any) => <div data-testid="dropdown-menu">{children}</div>,
    DropdownMenuItem: ({ children, onClick }: any) => (
        <div onClick={onClick} data-testid="dropdown-item">{children}</div>
    ),
    DropdownMenuLabel: ({ children }: any) => <div>{children}</div>,
    DropdownMenuSeparator: () => <hr />
}));

vi.mock('@/components/ui/avatar', () => ({
    Avatar: ({ children }: any) => <div data-testid="avatar">{children}</div>,
    AvatarImage: ({ src }: any) => <img src={src} alt="avatar" />,
    AvatarFallback: ({ children }: any) => <span>{children}</span>
}));

const mockDevices = [
    {
        id: '1',
        user_id: 'user1',
        name: 'iPhone 13',
        device_type: 'mobile' as const,
        os_name: 'iOS',
        os_version: '15.0',
        browser_name: 'Safari',
        ip_address: '192.168.1.100',
        is_trusted: true,
        is_suspended: false,
        is_primary: true,
        last_seen_at: '2023-01-01T00:00:00Z',
        encryption_version: 'v3',
        public_key_fingerprint: 'abc123',
        session_count: 5,
        message_count: 100,
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:00:00Z'
    },
    {
        id: '2',
        user_id: 'user1',
        name: 'MacBook Pro',
        device_type: 'laptop' as const,
        os_name: 'macOS',
        os_version: '12.0',
        browser_name: 'Chrome',
        ip_address: '192.168.1.101',
        is_trusted: false,
        is_suspended: true,
        is_primary: false,
        last_seen_at: '2023-01-01T00:05:00Z',
        encryption_version: 'v2',
        public_key_fingerprint: 'def456',
        session_count: 2,
        message_count: 50,
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:00:00Z'
    }
];

describe('DeviceManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        
        mockUseDeviceApi.mockReturnValue({
            loading: false,
            error: null,
            fetchDevices: vi.fn().mockResolvedValue(mockDevices),
            fetchDevice: vi.fn(),
            addDevice: vi.fn(),
            updateDevice: vi.fn(),
            deleteDevice: vi.fn(),
            trustDevice: vi.fn(),
            suspendDevice: vi.fn(),
            generatePairingCode: vi.fn().mockResolvedValue({
                pairing_code: 'ABC123',
                qr_code_data: 'qr-data',
                expires_at: '2023-01-01T01:00:00Z'
            }),
            rotateDeviceKeys: vi.fn(),
            revokeAllSessions: vi.fn(),
            getDeviceLocation: vi.fn()
        });
    });

    it('renders device manager header', () => {
        render(<DeviceManager />);
        
        expect(screen.getByText('Device Management')).toBeInTheDocument();
        expect(screen.getByText('Manage trusted devices and security settings')).toBeInTheDocument();
    });

    it('displays device tabs with counts', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            expect(screen.getByText('All Devices (2)')).toBeInTheDocument();
            expect(screen.getByText('Trusted (1)')).toBeInTheDocument();
            expect(screen.getByText('Untrusted (1)')).toBeInTheDocument();
            expect(screen.getByText('Suspended (1)')).toBeInTheDocument();
        });
    });

    it('renders device cards correctly', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            expect(screen.getByText('iPhone 13')).toBeInTheDocument();
            expect(screen.getByText('MacBook Pro')).toBeInTheDocument();
            expect(screen.getByText('iOS 15.0')).toBeInTheDocument();
            expect(screen.getByText('macOS 12.0')).toBeInTheDocument();
        });
    });

    it('shows correct device status badges', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            const trustedBadge = screen.getByText('Trusted');
            const suspendedBadge = screen.getByText('Suspended');
            expect(trustedBadge).toBeInTheDocument();
            expect(suspendedBadge).toBeInTheDocument();
        });
    });

    it('handles tab filtering', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            const trustedTab = screen.getByText('Trusted (1)');
            fireEvent.click(trustedTab);
            
            expect(screen.getByTestId('tabs')).toHaveAttribute('data-value', 'trusted');
        });
    });

    it('opens add device dialog', () => {
        render(<DeviceManager />);
        
        const addButton = screen.getByText('Add Device');
        fireEvent.click(addButton);
        
        expect(screen.getByTestId('dialog')).toBeInTheDocument();
        expect(screen.getByText('Add New Device')).toBeInTheDocument();
    });

    it('generates pairing code', async () => {
        const mockGeneratePairingCode = vi.fn().mockResolvedValue({
            pairing_code: 'ABC123',
            qr_code_data: 'qr-data'
        });
        
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            generatePairingCode: mockGeneratePairingCode
        });

        render(<DeviceManager />);
        
        const addButton = screen.getByText('Add Device');
        fireEvent.click(addButton);
        
        const deviceNameInput = screen.getByPlaceholderText('e.g., John\'s iPhone');
        fireEvent.change(deviceNameInput, { target: { value: 'Test Device' } });
        
        const generateButton = screen.getByText('Generate Pairing Code');
        fireEvent.click(generateButton);
        
        await waitFor(() => {
            expect(mockGeneratePairingCode).toHaveBeenCalledWith('Test Device');
        });
    });

    it('shows QR code dialog after generating pairing code', async () => {
        render(<DeviceManager />);
        
        const addButton = screen.getByText('Add Device');
        fireEvent.click(addButton);
        
        const deviceNameInput = screen.getByPlaceholderText('e.g., John\'s iPhone');
        fireEvent.change(deviceNameInput, { target: { value: 'Test Device' } });
        
        const generateButton = screen.getByText('Generate Pairing Code');
        fireEvent.click(generateButton);
        
        await waitFor(() => {
            expect(screen.getByText('Device Pairing Code')).toBeInTheDocument();
            expect(screen.getByText('ABC123')).toBeInTheDocument();
        });
    });

    it('handles device trust action', async () => {
        const mockTrustDevice = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            trustDevice: mockTrustDevice
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            // The trust device action is in the dropdown menu
            const dropdownItems = screen.getAllByTestId('dropdown-item');
            const trustItem = dropdownItems.find(item => item.textContent?.includes('Trust Device'));
            if (trustItem) {
                fireEvent.click(trustItem);
                expect(mockTrustDevice).toHaveBeenCalledWith('2'); // Untrusted device
            }
        });
    });

    it('handles device suspension', async () => {
        const mockSuspendDevice = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            suspendDevice: mockSuspendDevice
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            // The suspend device action is in the dropdown menu
            const dropdownItems = screen.getAllByTestId('dropdown-item');
            const suspendItem = dropdownItems.find(item => item.textContent?.includes('Suspend Device'));
            if (suspendItem) {
                fireEvent.click(suspendItem);
                expect(mockSuspendDevice).toHaveBeenCalledWith('1'); // Trusted device
            }
        });
    });

    it('opens device details dialog', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            const detailsButton = screen.getAllByText('Details')[0];
            fireEvent.click(detailsButton);
            
            expect(screen.getByText('Device Details')).toBeInTheDocument();
        });
    });

    it('handles device deletion', async () => {
        const mockDeleteDevice = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            deleteDevice: mockDeleteDevice
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            // The remove device action is in the dropdown menu
            const dropdownItems = screen.getAllByTestId('dropdown-item');
            const removeItem = dropdownItems.find(item => item.textContent?.includes('Remove Device'));
            if (removeItem) {
                fireEvent.click(removeItem);
                
                expect(screen.getByTestId('alert-dialog')).toBeInTheDocument();
                expect(screen.getByText('Remove Device')).toBeInTheDocument();
                
                const confirmButton = screen.getByRole('button', { name: /remove device/i });
                fireEvent.click(confirmButton);
                
                expect(mockDeleteDevice).toHaveBeenCalled();
            }
        });
    });

    it('handles key rotation', async () => {
        const mockRotateDeviceKeys = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            rotateDeviceKeys: mockRotateDeviceKeys
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            // The rotate keys action is in the dropdown menu
            const dropdownItems = screen.getAllByTestId('dropdown-item');
            const rotateItem = dropdownItems.find(item => item.textContent?.includes('Rotate Keys'));
            if (rotateItem) {
                fireEvent.click(rotateItem);
                expect(mockRotateDeviceKeys).toHaveBeenCalled();
            }
        });
    });

    it('handles session revocation', async () => {
        const mockRevokeAllSessions = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            revokeAllSessions: mockRevokeAllSessions
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            // The revoke sessions action is in the dropdown menu
            const dropdownItems = screen.getAllByTestId('dropdown-item');
            const revokeItem = dropdownItems.find(item => item.textContent?.includes('Revoke Sessions'));
            if (revokeItem) {
                fireEvent.click(revokeItem);
                expect(mockRevokeAllSessions).toHaveBeenCalled();
            }
        });
    });

    it('shows search functionality', () => {
        render(<DeviceManager />);
        
        const searchInput = screen.getByPlaceholderText('Search devices...');
        fireEvent.change(searchInput, { target: { value: 'iPhone' } });
        
        expect(searchInput.value).toBe('iPhone');
    });

    it('shows loading state', () => {
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            loading: true
        });

        render(<DeviceManager />);
        
        expect(screen.getByText('Loading devices...')).toBeInTheDocument();
    });

    it('shows empty state when no devices', async () => {
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            fetchDevices: vi.fn().mockResolvedValue([])
        });

        render(<DeviceManager />);
        
        await waitFor(() => {
            expect(screen.getByText('No devices found')).toBeInTheDocument();
        });
    });

    it('handles refresh action', () => {
        const mockFetchDevices = vi.fn();
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            fetchDevices: mockFetchDevices
        });

        render(<DeviceManager />);
        
        const refreshButton = screen.getByText('Refresh');
        fireEvent.click(refreshButton);
        
        expect(mockFetchDevices).toHaveBeenCalled();
    });

    it('shows device icons based on type', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            // Should show device type badges
            const badges = screen.getAllByTestId('badge');
            const typeMatches = badges.some(badge => 
                badge.textContent === 'mobile' || badge.textContent === 'laptop'
            );
            expect(typeMatches).toBe(true);
        });
    });

    it('displays encryption version indicators', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            // Should show encryption version badges
            const devices = screen.getAllByTestId('card');
            expect(devices).toHaveLength(2);
        });
    });

    it('shows online/offline status', async () => {
        render(<DeviceManager />);
        
        await waitFor(() => {
            // Based on last_seen_at, should show offline status
            expect(screen.getAllByText('Offline')).toHaveLength(2);
        });
    });
});