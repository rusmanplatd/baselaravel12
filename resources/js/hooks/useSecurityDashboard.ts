import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { type UserSession, type SessionStats, type SecurityAlert, type TrustedDevice } from '@/types';
import { securityApiCall, SecurityApiError, isNetworkError } from '@/utils/apiErrorHandler';

interface SecurityDashboardData {
    sessions: UserSession[];
    stats: SessionStats;
    alerts: SecurityAlert[];
    devices: TrustedDevice[];
    loading: boolean;
    error: string | null;
}

interface UseSecurityDashboardOptions {
    autoRefresh?: boolean;
    refreshInterval?: number;
}

export function useSecurityDashboard(options: UseSecurityDashboardOptions = {}) {
    const { autoRefresh = false, refreshInterval = 30000 } = options;
    
    const [data, setData] = useState<SecurityDashboardData>({
        sessions: [],
        stats: {
            active_sessions: 0,
            total_sessions: 0,
            trusted_device_sessions: 0,
            recent_logins: 0,
            unique_ips: 0,
            device_types: {},
            current_session_id: '',
        },
        alerts: [],
        devices: [],
        loading: true,
        error: null,
    });

    const fetchSecurityData = useCallback(async () => {
        try {
            const [sessionsData, statsData, alertsData, devicesData] = await Promise.all([
                securityApiCall(route('security.sessions')),
                securityApiCall(route('security.sessions.stats')),
                securityApiCall(route('security.sessions.alerts')),
                securityApiCall(route('security.trusted-devices')),
            ]);

            setData({
                sessions: sessionsData?.sessions || sessionsData?.props?.sessions || [],
                stats: statsData?.stats || statsData || {
                    active_sessions: 0,
                    total_sessions: 0,
                    trusted_device_sessions: 0,
                    recent_logins: 0,
                    unique_ips: 0,
                    device_types: {},
                    current_session_id: '',
                },
                alerts: alertsData?.alerts || [],
                devices: devicesData?.devices || devicesData?.props?.devices || [],
                loading: false,
                error: null,
            });
        } catch (error) {
            console.error('Failed to fetch security data:', error);
            
            let errorMessage = 'Unknown error occurred';
            if (error instanceof SecurityApiError) {
                errorMessage = error.message;
            } else if (isNetworkError(error)) {
                errorMessage = 'Network error. Please check your connection.';
            }

            setData(prev => ({
                ...prev,
                loading: false,
                error: errorMessage,
            }));
        }
    }, []);

    const refresh = useCallback(() => {
        setData(prev => ({ ...prev, loading: true, error: null }));
        fetchSecurityData();
    }, [fetchSecurityData]);

    const terminateSession = useCallback(async (sessionId: string) => {
        try {
            await securityApiCall(route('security.sessions.destroy', sessionId), {
                method: 'DELETE',
            });

            // Refresh data after successful termination
            refresh();
            return true;
        } catch (error) {
            console.error('Failed to terminate session:', error);
            return false;
        }
    }, [refresh]);

    const terminateAllOtherSessions = useCallback(async () => {
        try {
            const result = await securityApiCall(route('security.sessions.terminate-others'), {
                method: 'POST',
            });

            // Refresh data after successful termination
            refresh();
            return result?.terminated_count || 0;
        } catch (error) {
            console.error('Failed to terminate other sessions:', error);
            return 0;
        }
    }, [refresh]);

    const revokeDevice = useCallback(async (deviceId: string) => {
        try {
            await securityApiCall(route('security.trusted-devices.destroy', deviceId), {
                method: 'DELETE',
            });

            // Refresh data after successful revocation
            refresh();
            return true;
        } catch (error) {
            console.error('Failed to revoke device:', error);
            return false;
        }
    }, [refresh]);

    const revokeAllDevices = useCallback(async () => {
        try {
            const result = await securityApiCall(route('security.trusted-devices.revoke-all'), {
                method: 'POST',
            });

            // Refresh data after successful revocation
            refresh();
            return result?.revoked_count || 0;
        } catch (error) {
            console.error('Failed to revoke devices:', error);
            return 0;
        }
    }, [refresh]);

    // Initial data fetch
    useEffect(() => {
        fetchSecurityData();
    }, [fetchSecurityData]);

    // Auto-refresh setup
    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(fetchSecurityData, refreshInterval);
        return () => clearInterval(interval);
    }, [autoRefresh, refreshInterval, fetchSecurityData]);

    return {
        ...data,
        refresh,
        terminateSession,
        terminateAllOtherSessions,
        revokeDevice,
        revokeAllDevices,
    };
}