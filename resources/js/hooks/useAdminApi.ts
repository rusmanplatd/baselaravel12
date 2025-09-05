import { useState, useCallback } from 'react';
import {
  SystemStats,
  ServiceStatus,
  RecentActivity,
  AdminDashboardData,
  UserMetrics,
  ConversationMetrics,
  SecurityMetrics,
  SystemHealth,
  AdminAction,
  PerformanceMetrics,
  SystemAlert,
  AlertRule
} from '@/types/admin';

export const useAdminApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/admin/${endpoint}`, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...options.headers,
        },
        ...options,
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        throw new Error(errorData?.message || `HTTP error! status: ${response.status}`);
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

  // Dashboard Overview
  const fetchDashboardStats = useCallback(async (): Promise<SystemStats> => {
    const response = await makeApiCall<{ stats: SystemStats }>('dashboard/stats');
    return response.stats;
  }, []);

  const fetchServiceStatuses = useCallback(async (): Promise<ServiceStatus[]> => {
    const response = await makeApiCall<{ services: ServiceStatus[] }>('dashboard/services');
    return response.services;
  }, []);

  const fetchRecentActivity = useCallback(async (params: {
    limit?: number;
    severity?: string;
    type?: string;
  } = {}): Promise<RecentActivity[]> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `dashboard/activity?${queryParams.toString()}` : 'dashboard/activity';
    const response = await makeApiCall<{ activities: RecentActivity[] }>(endpoint);
    return response.activities;
  }, []);

  const fetchFullDashboard = useCallback(async (): Promise<AdminDashboardData> => {
    return makeApiCall<AdminDashboardData>('dashboard');
  }, []);

  // User Management
  const fetchUserMetrics = useCallback(async (): Promise<UserMetrics> => {
    const response = await makeApiCall<{ metrics: UserMetrics }>('users/metrics');
    return response.metrics;
  }, []);

  const suspendUser = useCallback(async (userId: string, reason: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`users/${userId}/suspend`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }, []);

  const activateUser = useCallback(async (userId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`users/${userId}/activate`, {
      method: 'POST',
    });
  }, []);

  // Conversation Management
  const fetchConversationMetrics = useCallback(async (): Promise<ConversationMetrics> => {
    const response = await makeApiCall<{ metrics: ConversationMetrics }>('conversations/metrics');
    return response.metrics;
  }, []);

  const deleteConversation = useCallback(async (conversationId: string, reason: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`conversations/${conversationId}`, {
      method: 'DELETE',
      body: JSON.stringify({ reason }),
    });
  }, []);

  // Security Management
  const fetchSecurityMetrics = useCallback(async (): Promise<SecurityMetrics> => {
    const response = await makeApiCall<{ metrics: SecurityMetrics }>('security/metrics');
    return response.metrics;
  }, []);

  const forceKeyRotation = useCallback(async (conversationId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`security/conversations/${conversationId}/rotate-keys`, {
      method: 'POST',
    });
  }, []);

  const quarantineMessage = useCallback(async (messageId: string, reason: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`security/messages/${messageId}/quarantine`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    });
  }, []);

  // System Health
  const fetchSystemHealth = useCallback(async (): Promise<SystemHealth> => {
    const response = await makeApiCall<{ health: SystemHealth }>('system/health');
    return response.health;
  }, []);

  const runHealthCheck = useCallback(async (service?: string): Promise<{ results: ServiceStatus[] }> => {
    const endpoint = service ? `system/health-check?service=${service}` : 'system/health-check';
    return makeApiCall<{ results: ServiceStatus[] }>(endpoint, {
      method: 'POST',
    });
  }, []);

  // Performance Monitoring
  const fetchPerformanceMetrics = useCallback(async (timeRange: '1h' | '24h' | '7d' | '30d' = '24h'): Promise<PerformanceMetrics> => {
    const response = await makeApiCall<{ metrics: PerformanceMetrics }>(`system/performance?range=${timeRange}`);
    return response.metrics;
  }, []);

  // Admin Actions
  const fetchAdminActions = useCallback(async (params: {
    page?: number;
    limit?: number;
    admin_user_id?: string;
    action_type?: string;
  } = {}): Promise<{
    actions: AdminAction[];
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  }> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `actions?${queryParams.toString()}` : 'actions';
    return makeApiCall<{
      actions: AdminAction[];
      pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      };
    }>(endpoint);
  }, []);

  // System Maintenance
  const enableMaintenanceMode = useCallback(async (reason: string, estimatedDuration?: number): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>('system/maintenance', {
      method: 'POST',
      body: JSON.stringify({ reason, estimated_duration: estimatedDuration }),
    });
  }, []);

  const disableMaintenanceMode = useCallback(async (): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>('system/maintenance', {
      method: 'DELETE',
    });
  }, []);

  // Alerts Management
  const fetchAlerts = useCallback(async (params: {
    severity?: string;
    resolved?: boolean;
    acknowledged?: boolean;
  } = {}): Promise<SystemAlert[]> => {
    const queryParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `alerts?${queryParams.toString()}` : 'alerts';
    const response = await makeApiCall<{ alerts: SystemAlert[] }>(endpoint);
    return response.alerts;
  }, []);

  const acknowledgeAlert = useCallback(async (alertId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`alerts/${alertId}/acknowledge`, {
      method: 'POST',
    });
  }, []);

  const resolveAlert = useCallback(async (alertId: string, resolution?: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`alerts/${alertId}/resolve`, {
      method: 'POST',
      body: JSON.stringify({ resolution }),
    });
  }, []);

  // Alert Rules Management
  const fetchAlertRules = useCallback(async (): Promise<AlertRule[]> => {
    const response = await makeApiCall<{ rules: AlertRule[] }>('alert-rules');
    return response.rules;
  }, []);

  const createAlertRule = useCallback(async (ruleData: Omit<AlertRule, 'id'>): Promise<AlertRule> => {
    const response = await makeApiCall<{ rule: AlertRule }>('alert-rules', {
      method: 'POST',
      body: JSON.stringify(ruleData),
    });
    return response.rule;
  }, []);

  const updateAlertRule = useCallback(async (ruleId: string, ruleData: Partial<AlertRule>): Promise<AlertRule> => {
    const response = await makeApiCall<{ rule: AlertRule }>(`alert-rules/${ruleId}`, {
      method: 'PATCH',
      body: JSON.stringify(ruleData),
    });
    return response.rule;
  }, []);

  const deleteAlertRule = useCallback(async (ruleId: string): Promise<{ success: boolean; message: string }> => {
    return makeApiCall<{ success: boolean; message: string }>(`alert-rules/${ruleId}`, {
      method: 'DELETE',
    });
  }, []);

  // Export Data
  const exportSystemReport = useCallback(async (params: {
    format: 'pdf' | 'csv' | 'json';
    sections?: string[];
    dateRange?: {
      start: string;
      end: string;
    };
  }): Promise<Blob> => {
    const response = await fetch('/api/v1/admin/reports/export', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': params.format === 'json' ? 'application/json' : 'application/octet-stream',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(params),
    });

    if (!response.ok) {
      throw new Error(`Export failed: ${response.status}`);
    }

    return response.blob();
  }, []);

  return {
    loading,
    error,
    // Dashboard
    fetchDashboardStats,
    fetchServiceStatuses,
    fetchRecentActivity,
    fetchFullDashboard,
    // Users
    fetchUserMetrics,
    suspendUser,
    activateUser,
    // Conversations
    fetchConversationMetrics,
    deleteConversation,
    // Security
    fetchSecurityMetrics,
    forceKeyRotation,
    quarantineMessage,
    // System Health
    fetchSystemHealth,
    runHealthCheck,
    fetchPerformanceMetrics,
    // Admin Actions
    fetchAdminActions,
    // Maintenance
    enableMaintenanceMode,
    disableMaintenanceMode,
    // Alerts
    fetchAlerts,
    acknowledgeAlert,
    resolveAlert,
    fetchAlertRules,
    createAlertRule,
    updateAlertRule,
    deleteAlertRule,
    // Export
    exportSystemReport,
  };
};