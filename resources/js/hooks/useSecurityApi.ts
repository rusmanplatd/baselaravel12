import { useState } from 'react';
import { router } from '@inertiajs/react';

interface SecurityDashboardData {
  metrics: {
    total_events: number;
    high_risk_events: number;
    unresolved_events: number;
  };
  recent_events: SecurityEvent[];
  user_anomalies: UserAnomaly[];
  security_score: number;
}

interface SecurityEvent {
  id: string;
  event_type: string;
  severity: 'critical' | 'high' | 'medium' | 'low' | 'info';
  risk_score: number;
  status: 'normal' | 'pending' | 'investigating' | 'resolved' | 'false_positive';
  user?: {
    id: string;
    name: string;
  };
  device?: {
    id: string;
    device_name: string;
  };
  created_at: string;
  metadata?: any;
}

interface UserAnomaly {
  type: string;
  severity: 'low' | 'medium' | 'high';
  description: string;
  [key: string]: any;
}

interface AuditLogsResponse {
  audit_logs: SecurityEvent[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface ReportParams {
  from: string;
  to: string;
}

export const useSecurityApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string, 
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/security/${endpoint}`, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...options.headers,
        },
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      return data;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred';
      setError(errorMessage);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const fetchDashboard = async (): Promise<SecurityDashboardData> => {
    return makeApiCall<SecurityDashboardData>('dashboard');
  };

  const fetchAuditLogs = async (params: {
    page?: number;
    limit?: number;
    event_type?: string;
    severity?: string;
    status?: string;
    user_id?: string;
    risk_score_min?: number;
    risk_score_max?: number;
    from?: string;
    to?: string;
  } = {}): Promise<AuditLogsResponse> => {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() 
      ? `audit-logs?${queryParams.toString()}`
      : 'audit-logs';

    return makeApiCall<AuditLogsResponse>(endpoint);
  };

  const fetchAuditLog = async (id: string): Promise<{ audit_log: SecurityEvent }> => {
    return makeApiCall<{ audit_log: SecurityEvent }>(`audit-logs/${id}`);
  };

  const investigateEvent = async (id: string): Promise<{ audit_log: SecurityEvent }> => {
    return makeApiCall<{ audit_log: SecurityEvent }>(`audit-logs/${id}/investigate`, {
      method: 'PATCH',
    });
  };

  const resolveEvent = async (
    id: string, 
    resolution: 'resolved' | 'false_positive',
    notes?: string
  ): Promise<{ audit_log: SecurityEvent }> => {
    return makeApiCall<{ audit_log: SecurityEvent }>(`audit-logs/${id}/resolve`, {
      method: 'PATCH',
      body: JSON.stringify({
        resolution,
        notes,
      }),
    });
  };

  const exportReport = async (params: ReportParams): Promise<any> => {
    const queryParams = new URLSearchParams(params);
    return makeApiCall<any>(`report?${queryParams.toString()}`);
  };

  const fetchEventTypes = async (): Promise<{ event_types: any }> => {
    return makeApiCall<{ event_types: any }>('event-types');
  };

  return {
    loading,
    error,
    fetchDashboard,
    fetchAuditLogs,
    fetchAuditLog,
    investigateEvent,
    resolveEvent,
    exportReport,
    fetchEventTypes,
  };
};

export type { SecurityEvent, UserAnomaly, SecurityDashboardData, AuditLogsResponse };