import { useState, useCallback } from 'react';
import { apiService } from '@/services/ApiService';
import {
  UserDevice,
  DeviceCreateData,
  DeviceUpdateData,
  DeviceListResponse,
  PairingCodeResponse,
  DeviceTrustResponse,
  DeviceKeyRotationResponse,
  DeviceLocationResponse,
  DeviceSession,
  DeviceSecurityEvent,
  DeviceStats,
  DeviceSecurityPolicy,
  DeviceComplianceCheck,
  DeviceBackup,
  DeviceRecovery,
} from '@/types/device';

export const useDeviceApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const url = `/api/v1/devices/${endpoint}`;
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

  // Device Management
  const fetchDevices = useCallback(async (userId?: string, params: {
    page?: number;
    limit?: number;
    trusted_only?: boolean;
    active_only?: boolean;
    device_type?: string;
  } = {}): Promise<UserDevice[]> => {
    const queryParams = new URLSearchParams();

    if (userId) queryParams.append('user_id', userId);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `?${queryParams.toString()}` : '';
    const response = await makeApiCall<DeviceListResponse>(endpoint);
    return response.devices;
  }, []);

  const fetchDevice = useCallback(async (deviceId: string): Promise<UserDevice> => {
    const response = await makeApiCall<{ device: UserDevice }>(deviceId);
    return response.device;
  }, []);

  const addDevice = useCallback(async (deviceData: DeviceCreateData): Promise<UserDevice> => {
    const response = await makeApiCall<{ device: UserDevice }>('', {
      method: 'POST',
      body: JSON.stringify(deviceData),
    });
    return response.device;
  }, []);

  const updateDevice = useCallback(async (deviceId: string, deviceData: DeviceUpdateData): Promise<UserDevice> => {
    const response = await makeApiCall<{ device: UserDevice }>(deviceId, {
      method: 'PATCH',
      body: JSON.stringify(deviceData),
    });
    return response.device;
  }, []);

  const deleteDevice = useCallback(async (deviceId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(deviceId, {
      method: 'DELETE',
    });
  }, []);

  // Device Trust Management
  const trustDevice = useCallback(async (deviceId: string, requireVerification = false): Promise<DeviceTrustResponse> => {
    return makeApiCall<DeviceTrustResponse>(`${deviceId}/trust`, {
      method: 'POST',
      body: JSON.stringify({ require_verification: requireVerification }),
    });
  }, []);

  const untrustDevice = useCallback(async (deviceId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(deviceId, {
      method: 'DELETE',
    });
  }, []);

  const suspendDevice = useCallback(async (deviceId: string, reason?: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/suspend`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }, []);

  const unsuspendDevice = useCallback(async (deviceId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/unsuspend`, {
      method: 'POST',
    });
  }, []);

  // Device Pairing
  const generatePairingCode = useCallback(async (deviceName: string, expiresIn = 300): Promise<PairingCodeResponse> => {
    return makeApiCall<PairingCodeResponse>('pairing-code', {
      method: 'POST',
      body: JSON.stringify({
        device_name: deviceName,
        expires_in: expiresIn,
      }),
    });
  }, []);

  const usePairingCode = useCallback(async (pairingCode: string, deviceData: DeviceCreateData): Promise<UserDevice> => {
    const response = await makeApiCall<{ device: UserDevice }>('pair', {
      method: 'POST',
      body: JSON.stringify({
        pairing_code: pairingCode,
        ...deviceData,
      }),
    });
    return response.device;
  }, []);

  // Security Operations
  const rotateDeviceKeys = useCallback(async (deviceId: string): Promise<DeviceKeyRotationResponse> => {
    return makeApiCall<DeviceKeyRotationResponse>(`${deviceId}/rotate-keys`, {
      method: 'POST',
    });
  }, []);

  const revokeAllSessions = useCallback(async (deviceId: string): Promise<{ success: boolean; message: string; revoked_count: number }> => {
    return makeApiCall<{ success: boolean; message: string; revoked_count: number }>(`${deviceId}/revoke-sessions`, {
      method: 'POST',
    });
  }, []);

  const fetchDeviceSessions = useCallback(async (deviceId: string): Promise<DeviceSession[]> => {
    const response = await makeApiCall<{ sessions: DeviceSession[] }>(`${deviceId}/sessions`);
    return response.sessions;
  }, []);

  const revokeSession = useCallback(async (deviceId: string, sessionId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/sessions/${sessionId}/revoke`, {
      method: 'POST',
    });
  }, []);

  // Location and Tracking
  const getDeviceLocation = useCallback(async (deviceId: string): Promise<DeviceLocationResponse> => {
    return makeApiCall<DeviceLocationResponse>(`${deviceId}/location`);
  }, []);

  const updateDeviceLocation = useCallback(async (deviceId: string, location: {
    latitude: number;
    longitude: number;
    accuracy?: number;
  }): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/location`, {
      method: 'POST',
      body: JSON.stringify(location),
    });
  }, []);

  // Security Events
  const fetchDeviceSecurityEvents = useCallback(async (deviceId: string, params: {
    severity?: string;
    resolved?: boolean;
    limit?: number;
  } = {}): Promise<DeviceSecurityEvent[]> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString()
      ? `${deviceId}/security-events?${queryParams.toString()}`
      : `${deviceId}/security-events`;

    const response = await makeApiCall<{ events: DeviceSecurityEvent[] }>(endpoint);
    return response.events;
  }, []);

  const resolveSecurityEvent = useCallback(async (deviceId: string, eventId: string, resolution?: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/security-events/${eventId}/resolve`, {
      method: 'POST',
      body: JSON.stringify({ resolution }),
    });
  }, []);

  // Device Statistics
  const fetchDeviceStats = useCallback(async (params: {
    user_id?: string;
    organization_id?: string;
    date_from?: string;
    date_to?: string;
  } = {}): Promise<DeviceStats> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `stats?${queryParams.toString()}` : 'stats';
    const response = await makeApiCall<{ stats: DeviceStats }>(endpoint);
    return response.stats;
  }, []);

  // Security Policies
  const fetchSecurityPolicies = useCallback(async (): Promise<DeviceSecurityPolicy[]> => {
    const response = await makeApiCall<{ policies: DeviceSecurityPolicy[] }>('security-policies');
    return response.policies;
  }, []);

  const createSecurityPolicy = useCallback(async (policyData: Omit<DeviceSecurityPolicy, 'id' | 'created_at' | 'updated_at' | 'created_by'>): Promise<DeviceSecurityPolicy> => {
    const response = await makeApiCall<{ policy: DeviceSecurityPolicy }>('security-policies', {
      method: 'POST',
      body: JSON.stringify(policyData),
    });
    return response.policy;
  }, []);

  const updateSecurityPolicy = useCallback(async (policyId: string, policyData: Partial<DeviceSecurityPolicy>): Promise<DeviceSecurityPolicy> => {
    const response = await makeApiCall<{ policy: DeviceSecurityPolicy }>(`security-policies/${policyId}`, {
      method: 'PATCH',
      body: JSON.stringify(policyData),
    });
    return response.policy;
  }, []);

  // Compliance Checking
  const runComplianceCheck = useCallback(async (deviceId: string, policyId?: string): Promise<DeviceComplianceCheck> => {
    const body = policyId ? JSON.stringify({ policy_id: policyId }) : undefined;
    const response = await makeApiCall<{ compliance_check: DeviceComplianceCheck }>(`${deviceId}/compliance-check`, {
      method: 'POST',
      body,
    });
    return response.compliance_check;
  }, []);

  const fetchComplianceHistory = useCallback(async (deviceId: string): Promise<DeviceComplianceCheck[]> => {
    const response = await makeApiCall<{ compliance_checks: DeviceComplianceCheck[] }>(`${deviceId}/compliance-history`);
    return response.compliance_checks;
  }, []);

  // Device Backup and Recovery
  const createDeviceBackup = useCallback(async (deviceId: string, backupType: 'keys' | 'settings' | 'full'): Promise<DeviceBackup> => {
    const response = await makeApiCall<{ backup: DeviceBackup }>(`${deviceId}/backup`, {
      method: 'POST',
      body: JSON.stringify({ backup_type: backupType }),
    });
    return response.backup;
  }, []);

  const restoreFromBackup = useCallback(async (deviceId: string, backupId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/restore`, {
      method: 'POST',
      body: JSON.stringify({ backup_id: backupId }),
    });
  }, []);

  const initiateDeviceRecovery = useCallback(async (recoveryMethod: string, oldDeviceId?: string): Promise<DeviceRecovery> => {
    const response = await makeApiCall<{ recovery: DeviceRecovery }>('recovery', {
      method: 'POST',
      body: JSON.stringify({
        recovery_method: recoveryMethod,
        old_device_id: oldDeviceId,
      }),
    });
    return response.recovery;
  }, []);

  // Bulk Operations
  const bulkTrustDevices = useCallback(async (deviceIds: string[]): Promise<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }> => {
    return makeApiCall<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }>('bulk-trust', {
      method: 'POST',
      body: JSON.stringify({ device_ids: deviceIds }),
    });
  }, []);

  const bulkSuspendDevices = useCallback(async (deviceIds: string[], reason?: string): Promise<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }> => {
    return makeApiCall<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }>('bulk-suspend', {
      method: 'POST',
      body: JSON.stringify({ device_ids: deviceIds, reason }),
    });
  }, []);

  const bulkDeleteDevices = useCallback(async (deviceIds: string[]): Promise<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }> => {
    return makeApiCall<{ success: number; failed: number; results: Array<{ device_id: string; success: boolean; message: string }> }>('bulk-delete', {
      method: 'POST',
      body: JSON.stringify({ device_ids: deviceIds }),
    });
  }, []);

  // Device Verification
  const sendVerificationCode = useCallback(async (deviceId: string, method: 'email' | 'sms'): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`${deviceId}/send-verification`, {
      method: 'POST',
      body: JSON.stringify({ method }),
    });
  }, []);

  const verifyDevice = useCallback(async (deviceId: string, verificationCode: string): Promise<{ success: boolean; message: string; device?: UserDevice }> => {
    return makeApiCall<{ success: boolean; message: string; device?: UserDevice }>(`${deviceId}/verify`, {
      method: 'POST',
      body: JSON.stringify({ verification_code: verificationCode }),
    });
  }, []);

  return {
    loading,
    error,

    // Device Management
    fetchDevices,
    fetchDevice,
    addDevice,
    updateDevice,
    deleteDevice,

    // Trust Management
    trustDevice,
    untrustDevice,
    suspendDevice,
    unsuspendDevice,

    // Pairing
    generatePairingCode,
    usePairingCode,

    // Security Operations
    rotateDeviceKeys,
    revokeAllSessions,
    fetchDeviceSessions,
    revokeSession,

    // Location
    getDeviceLocation,
    updateDeviceLocation,

    // Security Events
    fetchDeviceSecurityEvents,
    resolveSecurityEvent,

    // Statistics
    fetchDeviceStats,

    // Security Policies
    fetchSecurityPolicies,
    createSecurityPolicy,
    updateSecurityPolicy,

    // Compliance
    runComplianceCheck,
    fetchComplianceHistory,

    // Backup and Recovery
    createDeviceBackup,
    restoreFromBackup,
    initiateDeviceRecovery,

    // Bulk Operations
    bulkTrustDevices,
    bulkSuspendDevices,
    bulkDeleteDevices,

    // Verification
    sendVerificationCode,
    verifyDevice,
  };
};
