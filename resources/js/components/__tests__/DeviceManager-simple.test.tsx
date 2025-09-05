import React from 'react';
import { render, screen } from '@testing-library/react';
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

describe('DeviceManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        
        mockUseDeviceApi.mockReturnValue({
            loading: false,
            error: null,
            fetchDevices: vi.fn().mockResolvedValue([]),
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

    it('shows loading state', () => {
        mockUseDeviceApi.mockReturnValue({
            ...mockUseDeviceApi(),
            loading: true
        });

        render(<DeviceManager />);
        
        expect(screen.getByText('Loading devices...')).toBeInTheDocument();
    });

    it('shows empty state when no devices', async () => {
        render(<DeviceManager />);
        
        // Should show empty state since fetchDevices returns []
        expect(screen.getByText('No devices found')).toBeInTheDocument();
    });
});