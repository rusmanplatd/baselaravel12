import { useState, useCallback } from 'react';
import { apiService } from '@/services/ApiService';

export interface ScheduledMessage {
  id: string;
  conversation_id: string;
  content: string;
  content_type: 'text' | 'markdown' | 'html';
  scheduled_for: string;
  timezone: string;
  status: 'scheduled' | 'sending' | 'sent' | 'failed' | 'cancelled';
  retry_count: number;
  max_retries: number;
  error_message?: string;
  can_retry: boolean;
  can_cancel: boolean;
  time_until_send?: number;
  sent_at?: string;
  cancelled_at?: string;
  created_at: string;
  updated_at: string;
  metadata?: Record<string, any>;
  sent_message?: {
    id: string;
    created_at: string;
  };
}

export interface SchedulingStatistics {
  total: number;
  scheduled: number;
  sending: number;
  sent: number;
  failed: number;
  cancelled: number;
  ready_to_send: number;
  failed_retryable: number;
  due_next_hour: number;
  due_next_day: number;
}

interface ScheduledMessagesResponse {
  scheduled_messages: ScheduledMessage[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const useMessageScheduling = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(endpoint: string, options: RequestInit = {}): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const url = `/api/v1/scheduled-messages/${endpoint}`;
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

      return data as T;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'An error occurred';
      setError(errorMessage);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const fetchScheduledMessages = useCallback(async (params: {
    conversation_id?: string;
    status?: string[];
    page?: number;
    limit?: number;
  } = {}): Promise<ScheduledMessagesResponse> => {
    const queryParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        if (Array.isArray(value)) {
          value.forEach(v => queryParams.append(`${key}[]`, String(v)));
        } else {
          queryParams.append(key, String(value));
        }
      }
    });

    const endpoint = queryParams.toString() ? `?${queryParams.toString()}` : '';
    return makeApiCall<ScheduledMessagesResponse>(endpoint);
  }, []);

  const scheduleMessage = useCallback(async (data: {
    conversation_id: string;
    content: string;
    content_type?: 'text' | 'markdown' | 'html';
    scheduled_for: string; // ISO string
    timezone?: string;
    metadata?: Record<string, any>;
  }): Promise<ScheduledMessage> => {
    const result = await makeApiCall<{ scheduled_message: ScheduledMessage; message: string }>('', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    return result.scheduled_message;
  }, []);

  const getScheduledMessage = useCallback(async (id: string): Promise<ScheduledMessage> => {
    const result = await makeApiCall<{ scheduled_message: ScheduledMessage }>(id);
    return result.scheduled_message;
  }, []);

  const updateScheduledMessage = useCallback(async (
    id: string,
    data: Partial<{
      content: string;
      content_type: 'text' | 'markdown' | 'html';
      scheduled_for: string;
      timezone: string;
      metadata: Record<string, any>;
    }>
  ): Promise<ScheduledMessage> => {
    const result = await makeApiCall<{ scheduled_message: ScheduledMessage; message: string }>(id, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
    return result.scheduled_message;
  }, []);

  const cancelScheduledMessage = useCallback(async (id: string): Promise<ScheduledMessage> => {
    const result = await makeApiCall<{ scheduled_message: ScheduledMessage; message: string }>(`${id}/cancel`, {
      method: 'POST',
    });
    return result.scheduled_message;
  }, []);

  const retryScheduledMessage = useCallback(async (
    id: string,
    newScheduleTime?: string,
    timezone?: string
  ): Promise<ScheduledMessage> => {
    const body: any = {};
    if (newScheduleTime) {
      body.scheduled_for = newScheduleTime;
    }
    if (timezone) {
      body.timezone = timezone;
    }

    const result = await makeApiCall<{ scheduled_message: ScheduledMessage; message: string }>(`${id}/retry`, {
      method: 'POST',
      body: JSON.stringify(body),
    });
    return result.scheduled_message;
  }, []);

  const deleteScheduledMessage = useCallback(async (id: string): Promise<void> => {
    await makeApiCall<{ message: string }>(id, {
      method: 'DELETE',
    });
  }, []);

  const getSchedulingStatistics = useCallback(async (conversationId?: string): Promise<SchedulingStatistics> => {
    const params = conversationId ? `?conversation_id=${conversationId}` : '';
    const result = await makeApiCall<{ statistics: SchedulingStatistics }>(`statistics${params}`);
    return result.statistics;
  }, []);

  const bulkAction = useCallback(async (
    action: 'cancel' | 'retry' | 'delete',
    messageIds: string[]
  ): Promise<{
    results: Record<string, { success: boolean; error?: string }>;
    summary: { total: number; successful: number; failed: number };
  }> => {
    const result = await makeApiCall<{
      results: Record<string, { success: boolean; error?: string }>;
      summary: { total: number; successful: number; failed: number };
      message: string;
    }>('bulk-action', {
      method: 'POST',
      body: JSON.stringify({
        action,
        message_ids: messageIds,
      }),
    });

    return {
      results: result.results,
      summary: result.summary,
    };
  }, []);

  // Helper functions
  const getStatusColor = useCallback((status: string): string => {
    switch (status) {
      case 'scheduled':
        return 'text-blue-600';
      case 'sending':
        return 'text-yellow-600';
      case 'sent':
        return 'text-green-600';
      case 'failed':
        return 'text-red-600';
      case 'cancelled':
        return 'text-gray-600';
      default:
        return 'text-gray-600';
    }
  }, []);

  const getStatusBadgeColor = useCallback((status: string): string => {
    switch (status) {
      case 'scheduled':
        return 'bg-blue-100 text-blue-800';
      case 'sending':
        return 'bg-yellow-100 text-yellow-800';
      case 'sent':
        return 'bg-green-100 text-green-800';
      case 'failed':
        return 'bg-red-100 text-red-800';
      case 'cancelled':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  }, []);

  const formatScheduleTime = useCallback((scheduledFor: string, timezone?: string): string => {
    const date = new Date(scheduledFor);
    return date.toLocaleString('en-US', {
      timeZone: timezone || 'UTC',
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZoneName: 'short',
    });
  }, []);

  const getTimeUntilSend = useCallback((scheduledFor: string): string => {
    const now = new Date();
    const scheduled = new Date(scheduledFor);
    const diff = scheduled.getTime() - now.getTime();

    if (diff <= 0) {
      return 'Past due';
    }

    const minutes = Math.floor(diff / (1000 * 60));
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) {
      return `in ${days} day${days !== 1 ? 's' : ''}`;
    } else if (hours > 0) {
      return `in ${hours} hour${hours !== 1 ? 's' : ''}`;
    } else if (minutes > 0) {
      return `in ${minutes} minute${minutes !== 1 ? 's' : ''}`;
    } else {
      return 'in less than a minute';
    }
  }, []);

  const isScheduledInFuture = useCallback((scheduledFor: string): boolean => {
    return new Date(scheduledFor) > new Date();
  }, []);

  const canScheduleForTime = useCallback((scheduledFor: string): { valid: boolean; error?: string } => {
    const now = new Date();
    const scheduled = new Date(scheduledFor);

    if (isNaN(scheduled.getTime())) {
      return { valid: false, error: 'Invalid date format' };
    }

    if (scheduled <= now) {
      return { valid: false, error: 'Schedule time must be in the future' };
    }

    const maxFuture = new Date();
    maxFuture.setDate(maxFuture.getDate() + 365); // 1 year max

    if (scheduled > maxFuture) {
      return { valid: false, error: 'Cannot schedule more than 1 year in advance' };
    }

    return { valid: true };
  }, []);

  return {
    loading,
    error,
    fetchScheduledMessages,
    scheduleMessage,
    getScheduledMessage,
    updateScheduledMessage,
    cancelScheduledMessage,
    retryScheduledMessage,
    deleteScheduledMessage,
    getSchedulingStatistics,
    bulkAction,
    // Helper functions
    getStatusColor,
    getStatusBadgeColor,
    formatScheduleTime,
    getTimeUntilSend,
    isScheduledInFuture,
    canScheduleForTime,
  };
};
