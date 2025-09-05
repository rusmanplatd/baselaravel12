export interface Notification {
  id: string;
  user_id: string;
  title: string;
  message: string;
  type: 'message' | 'mention' | 'participant_joined' | 'participant_left' | 'security_alert' | 'video_call_started' | 'video_call_ended' | 'system_alert' | 'webhook_delivery' | 'admin_action';
  priority: 'low' | 'medium' | 'high' | 'critical';
  channel: 'in_app' | 'email' | 'push' | 'sms';
  read_at?: string;
  clicked_at?: string;
  dismissed_at?: string;
  expires_at?: string;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email?: string;
  };
  
  // Related entities based on type
  conversation_id?: string;
  message_id?: string;
  user_device_id?: string;
  organization_id?: string;
}

export interface NotificationPreferences {
  id: string;
  user_id: string;
  
  // Delivery methods
  browser_notifications: boolean;
  toast_notifications: boolean;
  email_notifications: boolean;
  push_notifications: boolean;
  sms_notifications: boolean;
  
  // Notification types
  message_notifications: boolean;
  mention_notifications: boolean;
  participant_notifications: boolean;
  security_notifications: boolean;
  video_call_notifications: boolean;
  webhook_notifications: boolean;
  admin_notifications: boolean;
  system_notifications: boolean;
  
  // Timing settings
  quiet_hours_enabled: boolean;
  quiet_hours_start?: string; // HH:mm format
  quiet_hours_end?: string;   // HH:mm format
  quiet_hours_timezone?: string;
  
  // Email digest settings
  email_digest_frequency: 'never' | 'instant' | 'hourly' | 'daily' | 'weekly';
  email_digest_time?: string; // HH:mm format for daily/weekly
  
  // Priority filtering
  minimum_priority: 'low' | 'medium' | 'high' | 'critical';
  
  created_at: string;
  updated_at: string;
}

export interface NotificationTemplate {
  id: string;
  name: string;
  type: string;
  title_template: string;
  message_template: string;
  default_priority: string;
  enabled: boolean;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
}

export interface NotificationBatch {
  id: string;
  name: string;
  template_id?: string;
  recipients_count: number;
  sent_count: number;
  failed_count: number;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
  scheduled_at?: string;
  started_at?: string;
  completed_at?: string;
  error_message?: string;
  metadata?: Record<string, any>;
  created_by: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  template?: NotificationTemplate;
  creator?: {
    id: string;
    name: string;
    email: string;
  };
}

export interface NotificationDelivery {
  id: string;
  notification_id: string;
  batch_id?: string;
  channel: 'in_app' | 'email' | 'push' | 'sms';
  status: 'pending' | 'sent' | 'delivered' | 'failed' | 'bounced' | 'cancelled';
  external_id?: string; // Provider-specific ID
  sent_at?: string;
  delivered_at?: string;
  failed_at?: string;
  error_message?: string;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  
  // Relationships
  notification?: Notification;
  batch?: NotificationBatch;
}

export interface NotificationStats {
  total_sent: number;
  total_delivered: number;
  total_read: number;
  total_clicked: number;
  delivery_rate: number;
  read_rate: number;
  click_rate: number;
  bounce_rate: number;
  unsubscribe_rate: number;
  
  // By channel
  by_channel: {
    [channel: string]: {
      sent: number;
      delivered: number;
      failed: number;
      delivery_rate: number;
    };
  };
  
  // By type
  by_type: {
    [type: string]: {
      sent: number;
      delivered: number;
      read: number;
      clicked: number;
    };
  };
  
  // Recent trends
  recent_trends: {
    date: string;
    sent: number;
    delivered: number;
    read: number;
    clicked: number;
  }[];
}

export interface WebSocketMessage {
  type: 'notification' | 'presence_update' | 'message' | 'call_signal' | 'system_alert';
  payload: any;
  timestamp: string;
  user_id?: string;
  conversation_id?: string;
  organization_id?: string;
}

export interface NotificationSound {
  id: string;
  name: string;
  file_path: string;
  duration_ms: number;
  created_at: string;
}

export interface NotificationRule {
  id: string;
  user_id: string;
  name: string;
  description?: string;
  conditions: {
    type?: string[];
    priority?: string[];
    keywords?: string[];
    sender_ids?: string[];
    conversation_ids?: string[];
    time_range?: {
      start: string;
      end: string;
    };
  };
  actions: {
    suppress?: boolean;
    change_priority?: 'low' | 'medium' | 'high' | 'critical';
    forward_to?: string; // email address
    custom_sound?: string;
    auto_dismiss_after?: number; // minutes
  };
  enabled: boolean;
  priority: number; // Rule execution priority
  created_at: string;
  updated_at: string;
}

export interface UnsubscribeToken {
  id: string;
  user_id: string;
  email: string;
  token: string;
  notification_types: string[];
  expires_at: string;
  used_at?: string;
  created_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email: string;
  };
}

// API Response types
export interface NotificationListResponse {
  notifications: Notification[];
  unread_count: number;
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface NotificationCreateData {
  user_ids?: string[];
  organization_id?: string;
  title: string;
  message: string;
  type: string;
  priority?: 'low' | 'medium' | 'high' | 'critical';
  channels?: string[];
  metadata?: Record<string, any>;
  expires_at?: string;
  scheduled_at?: string;
}

export interface NotificationUpdateData {
  read_at?: string;
  clicked_at?: string;
  dismissed_at?: string;
}

// WebSocket connection states
export type WebSocketConnectionStatus = 
  | 'connecting' 
  | 'connected' 
  | 'disconnected' 
  | 'reconnecting' 
  | 'error' 
  | 'closed';

export interface WebSocketOptions {
  onOpen?: (event: Event) => void;
  onMessage?: (event: MessageEvent) => void;
  onClose?: (event: CloseEvent) => void;
  onError?: (event: Event) => void;
  reconnectInterval?: number;
  maxReconnectAttempts?: number;
  heartbeatInterval?: number;
}