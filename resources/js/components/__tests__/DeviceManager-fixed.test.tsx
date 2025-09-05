import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
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

    it('renders device manager header', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        expect(screen.getByText('Device Management')).toBeInTheDocument();
        expect(screen.getByText('Manage trusted devices and security settings')).toBeInTheDocument();
    });

    it('displays device tabs with counts', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        await waitFor(() => {
            expect(screen.getByText('All Devices (2)')).toBeInTheDocument();
            expect(screen.getByText('Trusted (1)')).toBeInTheDocument();
            expect(screen.getByText('Untrusted (1)')).toBeInTheDocument();
            expect(screen.getByText('Suspended (1)')).toBeInTheDocument();
        });
    });

    it('renders device cards correctly', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        await waitFor(() => {
            expect(screen.getByText('iPhone 13')).toBeInTheDocument();
            expect(screen.getByText('MacBook Pro')).toBeInTheDocument();
            expect(screen.getByText('iOS 15.0')).toBeInTheDocument();
            expect(screen.getByText('macOS 12.0')).toBeInTheDocument();
        });
    });

    it('shows correct device status badges', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        await waitFor(() => {
            const trustedBadge = screen.getByText('Trusted');
            const suspendedBadge = screen.getByText('Suspended');
            expect(trustedBadge).toBeInTheDocument();
            expect(suspendedBadge).toBeInTheDocument();
        });
    });

    it('opens add device dialog', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        const addButton = screen.getByText('Add Device');
        
        await act(async () => {
            fireEvent.click(addButton);
        });
        
        expect(screen.getByText('Add New Device')).toBeInTheDocument();
        expect(screen.getByText('Generate a pairing code to add a new device to your account')).toBeInTheDocument();
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

        await act(async () => {
            render(<DeviceManager />);
        });
        
        const addButton = screen.getByText('Add Device');
        await act(async () => {
            fireEvent.click(addButton);
        });
        
        const deviceNameInput = screen.getByPlaceholderText('e.g., John\'s iPhone');
        await act(async () => {
            fireEvent.change(deviceNameInput, { target: { value: 'Test Device' } });
        });
        
        const generateButton = screen.getByText('Generate Pairing Code');
        await act(async () => {
            fireEvent.click(generateButton);
        });
        
        await waitFor(() => {
            expect(mockGeneratePairingCode).toHaveBeenCalledWith('Test Device');
        });
    });

    it('shows QR code dialog after generating pairing code', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        const addButton = screen.getByText('Add Device');
        await act(async () => {
            fireEvent.click(addButton);
        });
        
        const deviceNameInput = screen.getByPlaceholderText('e.g., John\'s iPhone');
        await act(async () => {
            fireEvent.change(deviceNameInput, { target: { value: 'Test Device' } });
        });
        
        const generateButton = screen.getByText('Generate Pairing Code');
        await act(async () => {
            fireEvent.click(generateButton);
        });
        
        await waitFor(() => {
            expect(screen.getByText('Device Pairing Code')).toBeInTheDocument();
            expect(screen.getByText('ABC123')).toBeInTheDocument();
        });
    });

    it('shows search functionality', async () => {
        await act(async () => {
            render(<DeviceManager />);
        });
        
        const searchInput = screen.getByPlaceholderText('Search devices...');
        await act(async () => {
            fireEvent.change(searchInput, { target: { value: 'iPhone' } });
        });
        
        expect(searchInput.value).toBe('iPhone');
    });

    it('shows loading state', async () => {
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            loading: true
        });

        await act(async () => {
            render(<DeviceManager />);
        });
        
        expect(screen.getByText('Loading devices...')).toBeInTheDocument();
    });

    it('shows empty state when no devices', async () => {
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            fetchDevices: vi.fn().mockResolvedValue([])
        });

        await act(async () => {
            render(<DeviceManager />);
        });
        
        await waitFor(() => {
            expect(screen.getByText('No devices found')).toBeInTheDocument();
        });
    });

    it('handles refresh action', async () => {
        const mockFetchDevices = vi.fn().mockResolvedValue(mockDevices);
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            fetchDevices: mockFetchDevices
        });

        await act(async () => {
            render(<DeviceManager />);
        });
        
        // Wait for initial load
        await waitFor(() => {
            expect(screen.getByText('iPhone 13')).toBeInTheDocument();
        });
        
        const refreshButton = screen.getByText('Refresh');
        await act(async () => {
            fireEvent.click(refreshButton);
        });
        
        expect(mockFetchDevices).toHaveBeenCalledTimes(2); // Once on mount, once on refresh
    });
});