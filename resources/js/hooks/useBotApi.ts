import { useState } from 'react';

export interface Bot {
  id: string;
  name: string;
  description?: string;
  avatar?: string;
  webhook_url?: string;
  capabilities: string[];
  configuration: Record<string, any>;
  rate_limit_per_minute: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  creator?: {
    id: string;
    name: string;
  };
  active_conversations_count?: number;
  total_messages_count?: number;
}

export interface BotConversation {
  id: string;
  bot_id: string;
  conversation_id: string;
  status: 'active' | 'paused' | 'removed';
  permissions: string[];
  context: Record<string, any>;
  last_message_at?: string;
  created_at: string;
  updated_at: string;
}

export interface BotCapability {
  capability: string;
  description: string;
  requires_quantum: boolean;
}

interface BotsResponse {
  bots: Bot[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface BotCreateResponse {
  bot: Bot;
  api_token: string;
  webhook_secret?: string;
  message: string;
}

export const useBotApi = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const makeApiCall = async <T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/bots/${endpoint}`, {
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

  const fetchBots = async (params: {
    page?: number;
    limit?: number;
  } = {}): Promise<BotsResponse> => {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        queryParams.append(key, String(value));
      }
    });

    const endpoint = queryParams.toString() ? `?${queryParams.toString()}` : '';
    return makeApiCall<BotsResponse>(endpoint);
  };

  const fetchBot = async (id: string): Promise<{ bot: Bot }> => {
    return makeApiCall<{ bot: Bot }>(id);
  };

  const createBot = async (botData: {
    name: string;
    description?: string;
    avatar?: string;
    webhook_url?: string;
    capabilities: string[];
    configuration?: Record<string, any>;
    rate_limit_per_minute?: number;
  }): Promise<BotCreateResponse> => {
    return makeApiCall<BotCreateResponse>('', {
      method: 'POST',
      body: JSON.stringify(botData),
    });
  };

  const updateBot = async (
    id: string, 
    botData: Partial<Bot>
  ): Promise<{ bot: Bot; message: string }> => {
    return makeApiCall<{ bot: Bot; message: string }>(id, {
      method: 'PATCH',
      body: JSON.stringify(botData),
    });
  };

  const deleteBot = async (id: string): Promise<{ message: string }> => {
    return makeApiCall<{ message: string }>(id, {
      method: 'DELETE',
    });
  };

  const regenerateToken = async (id: string): Promise<{ api_token: string; message: string }> => {
    return makeApiCall<{ api_token: string; message: string }>(`${id}/regenerate-token`, {
      method: 'POST',
    });
  };

  const regenerateWebhookSecret = async (id: string): Promise<{ webhook_secret: string; message: string }> => {
    return makeApiCall<{ webhook_secret: string; message: string }>(`${id}/regenerate-webhook-secret`, {
      method: 'POST',
    });
  };

  const addToConversation = async (
    botId: string,
    conversationId: string,
    permissions: string[] = []
  ): Promise<{ bot_conversation: BotConversation; message: string }> => {
    return makeApiCall<{ bot_conversation: BotConversation; message: string }>(`${botId}/add-to-conversation`, {
      method: 'POST',
      body: JSON.stringify({
        conversation_id: conversationId,
        permissions,
      }),
    });
  };

  const removeFromConversation = async (
    botId: string,
    conversationId: string
  ): Promise<{ message: string }> => {
    return makeApiCall<{ message: string }>(`${botId}/conversations/${conversationId}`, {
      method: 'DELETE',
    });
  };

  const fetchCapabilities = async (): Promise<{ capabilities: BotCapability[] }> => {
    return makeApiCall<{ capabilities: BotCapability[] }>('capabilities');
  };

  // Bot authentication methods (for bot-to-bot communication)
  const sendMessageAsBot = async (
    botId: string,
    conversationId: string,
    content: string,
    contentType: string = 'text',
    metadata: Record<string, any> = {},
    apiToken: string
  ): Promise<{ message: any; success: boolean }> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/bots/${botId}/send-message`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${apiToken}`,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          conversation_id: conversationId,
          content,
          content_type: contentType,
          metadata,
        }),
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

  // Webhook signature validation helper
  const validateWebhookSignature = (
    payload: string,
    signature: string,
    secret: string
  ): boolean => {
    try {
      // This would typically be done server-side
      // Client-side validation is for development/testing only
      const crypto = require('crypto');
      const expectedSignature = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');
      
      return crypto.timingSafeEqual(
        Buffer.from(expectedSignature),
        Buffer.from(signature)
      );
    } catch {
      return false;
    }
  };

  return {
    loading,
    error,
    fetchBots,
    fetchBot,
    createBot,
    updateBot,
    deleteBot,
    regenerateToken,
    regenerateWebhookSecret,
    addToConversation,
    removeFromConversation,
    fetchCapabilities,
    sendMessageAsBot,
    validateWebhookSignature,
  };
};

export type { BotConversation };