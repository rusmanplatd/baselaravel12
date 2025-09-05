import { useState } from 'react';

export interface Webhook {
  id: string;
  name: string;
  url: string;
  secret?: string;
  events: string[];
  status: 'active' | 'inactive' | 'disabled';
  retry_attempts: number;
  timeout: number;
  headers?: Record<string, string>;
  success_rate?: number;
  total_deliveries?: number;
  successful_deliveries?: number;
  created_at: string;
  updated_at: string;
  creator?: {
    id: string;
    name: string;
  };
}

export interface WebhookDelivery {
  id: string;
  webhook_id: string;
  event_type: string;
  payload: any;
  headers?: Record<string, string>;
  status: 'pending' | 'success' | 'failed';
  http_status?: number;
  response_body?: string;
  attempt: number;
  delivered_at?: string;
  next_retry_at?: string;
  error_message?: string;
  created_at: string;
  updated_at: string;
}

export interface WebhookEvent {
  event: string;
  category: string;
  action: string;
  detail?: string;
  description: string;
}

interface WebhooksResponse {
  webhooks: Webhook[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface DeliveriesResponse {
  deliveries: WebhookDelivery[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface EventsResponse {
  events: Record<string, WebhookEvent[]>;
}

export const useWebhookApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/webhooks/${endpoint}`, {
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

  const fetchWebhooks = async (params: {
    page?: number;
    limit?: number;
  } = {}): Promise<WebhooksResponse> => {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `?${queryParams.toString()}` : '';
    return makeApiCall<WebhooksResponse>(endpoint);
  };

  const fetchWebhook = async (id: string): Promise<{ webhook: Webhook }> => {
    return makeApiCall<{ webhook: Webhook }>(id);
  };

  const createWebhook = async (webhookData: Partial<Webhook>): Promise<{ webhook: Webhook }> => {
    return makeApiCall<{ webhook: Webhook }>('', {
      method: 'POST',
      body: JSON.stringify(webhookData),
    });
  };

  const updateWebhook = async (
    id: string, 
    webhookData: Partial<Webhook>
  ): Promise<{ webhook: Webhook }> => {
    return makeApiCall<{ webhook: Webhook }>(id, {
      method: 'PATCH',
      body: JSON.stringify(webhookData),
    });
  };

  const deleteWebhook = async (id: string): Promise<{ message: string }> => {
    return makeApiCall<{ message: string }>(id, {
      method: 'DELETE',
    });
  };

  const regenerateSecret = async (id: string): Promise<{ secret: string; message: string }> => {
    return makeApiCall<{ secret: string; message: string }>(`${id}/regenerate-secret`, {
      method: 'POST',
    });
  };

  const testWebhook = async (id: string): Promise<{ delivery: WebhookDelivery; message: string }> => {
    return makeApiCall<{ delivery: WebhookDelivery; message: string }>(`${id}/test`, {
      method: 'POST',
    });
  };

  const fetchWebhookDeliveries = async (
    id: string,
    params: {
      page?: number;
      limit?: number;
      status?: string;
      event_type?: string;
    } = {}
  ): Promise<DeliveriesResponse> => {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() 
      ? `${id}/deliveries?${queryParams.toString()}`
      : `${id}/deliveries`;

    return makeApiCall<DeliveriesResponse>(endpoint);
  };

  const retryDelivery = async (
    webhookId: string,
    deliveryId: string
  ): Promise<{ delivery: WebhookDelivery; success: boolean; message: string }> => {
    return makeApiCall<{ delivery: WebhookDelivery; success: boolean; message: string }>(
      `${webhookId}/deliveries/${deliveryId}/retry`,
      {
        method: 'POST',
      }
    );
  };

  const fetchEvents = async (): Promise<EventsResponse> => {
    return makeApiCall<EventsResponse>('events');
  };

  return {
    loading,
    error,
    fetchWebhooks,
    fetchWebhook,
    createWebhook,
    updateWebhook,
    deleteWebhook,
    regenerateSecret,
    testWebhook,
    fetchWebhookDeliveries,
    retryDelivery,
    fetchEvents,
  };
};

export type { WebhookEvent };