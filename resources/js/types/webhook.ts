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
  organization_id: string;
  created_by: string;
  success_rate?: number;
  total_deliveries?: number;
  successful_deliveries?: number;
  created_at: string;
  updated_at: string;
  
  // Relationships
  creator?: {
    id: string;
    name: string;
  };
}

export interface WebhookDelivery {
  id: string;
  webhook_id: string;
  event_type: string;
  payload: Record<string, any>;
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
  
  // Relationships
  webhook?: Webhook;
}

export interface WebhookEvent {
  event: string;
  category: string;
  action: string;
  detail?: string;
  description: string;
}

export interface WebhookCreateData {
  name: string;
  url: string;
  events: string[];
  retry_attempts?: number;
  timeout?: number;
  headers?: Record<string, string>;
}

export interface WebhookUpdateData {
  name?: string;
  url?: string;
  events?: string[];
  status?: 'active' | 'inactive' | 'disabled';
  retry_attempts?: number;
  timeout?: number;
  headers?: Record<string, string>;
}