export interface SystemStats {
  total_users: number;
  active_users_24h: number;
  total_conversations: number;
  active_conversations_24h: number;
  total_messages: number;
  messages_24h: number;
  encrypted_messages_percentage: number;
  total_organizations: number;
  active_webhooks: number;
  failed_webhook_deliveries_24h: number;
  video_calls_24h: number;
  active_video_calls: number;
  system_health_score: number;
  storage_used_gb: number;
  storage_limit_gb: number;
  created_at: string;
  updated_at: string;
}

export interface ServiceStatus {
  service: string;
  status: 'healthy' | 'degraded' | 'down' | 'maintenance';
  response_time_ms: number;
  last_checked: string;
  uptime_percentage: number;
  error_message?: string;
  version?: string;
  dependencies?: ServiceDependency[];
}

export interface ServiceDependency {
  name: string;
  status: 'healthy' | 'degraded' | 'down';
  response_time_ms?: number;
}

export interface RecentActivity {
  id: string;
  type: 'user_registration' | 'conversation_created' | 'security_event' | 'webhook_created' | 'video_call_started' | 'system_alert' | 'admin_action';
  title: string;
  description: string;
  timestamp: string;
  severity: 'info' | 'warning' | 'error' | 'critical';
  metadata?: Record<string, any>;
  user?: {
    id: string;
    name: string;
    email?: string;
  };
  organization?: {
    id: string;
    name: string;
    slug?: string;
  };
}

export interface AdminDashboardData {
  stats: SystemStats;
  services: ServiceStatus[];
  activities: RecentActivity[];
  generated_at: string;
}

export interface UserMetrics {
  total_users: number;
  active_users: {
    daily: number;
    weekly: number;
    monthly: number;
  };
  new_registrations: {
    today: number;
    this_week: number;
    this_month: number;
  };
  user_retention: {
    day_1: number;
    day_7: number;
    day_30: number;
  };
  top_organizations: Array<{
    organization_id: string;
    organization_name: string;
    user_count: number;
  }>;
}

export interface ConversationMetrics {
  total_conversations: number;
  active_conversations: {
    daily: number;
    weekly: number;
    monthly: number;
  };
  conversation_types: {
    private: number;
    group: number;
    organization: number;
  };
  message_volume: {
    total_messages: number;
    messages_today: number;
    messages_this_week: number;
    avg_messages_per_conversation: number;
  };
  encryption_stats: {
    encrypted_percentage: number;
    quantum_encrypted_percentage: number;
    classical_encrypted_percentage: number;
    unencrypted_percentage: number;
  };
}

export interface SecurityMetrics {
  failed_login_attempts: {
    today: number;
    this_week: number;
  };
  successful_logins: {
    today: number;
    this_week: number;
  };
  security_events: {
    high_severity: number;
    medium_severity: number;
    low_severity: number;
  };
  encryption_health: {
    key_rotation_due: number;
    weak_keys_detected: number;
    quantum_ready_devices: number;
    total_devices: number;
  };
  anomalies_detected: {
    user_behavior: number;
    network: number;
    authentication: number;
  };
}

export interface SystemHealth {
  overall_score: number;
  components: {
    database: {
      status: 'healthy' | 'degraded' | 'down';
      response_time_ms: number;
      connection_pool_usage: number;
    };
    redis: {
      status: 'healthy' | 'degraded' | 'down';
      response_time_ms: number;
      memory_usage_percentage: number;
    };
    storage: {
      status: 'healthy' | 'degraded' | 'down';
      available_space_gb: number;
      used_space_percentage: number;
    };
    queue: {
      status: 'healthy' | 'degraded' | 'down';
      pending_jobs: number;
      failed_jobs_24h: number;
    };
    websocket: {
      status: 'healthy' | 'degraded' | 'down';
      active_connections: number;
      messages_per_second: number;
    };
    video_service: {
      status: 'healthy' | 'degraded' | 'down';
      active_rooms: number;
      participants_connected: number;
    };
  };
}

export interface AdminAction {
  id: string;
  admin_user_id: string;
  action_type: 'user_suspend' | 'user_activate' | 'conversation_delete' | 'webhook_disable' | 'system_maintenance' | 'security_override';
  target_type: 'user' | 'conversation' | 'webhook' | 'system' | 'organization';
  target_id?: string;
  reason: string;
  metadata?: Record<string, any>;
  timestamp: string;
  admin_user: {
    id: string;
    name: string;
    email: string;
  };
}

export interface PerformanceMetrics {
  avg_response_time_ms: number;
  requests_per_minute: number;
  error_rate_percentage: number;
  cpu_usage_percentage: number;
  memory_usage_percentage: number;
  disk_io_ops_per_second: number;
  network_throughput_mbps: number;
  cache_hit_rate_percentage: number;
}

export interface AlertRule {
  id: string;
  name: string;
  condition: string;
  threshold: number;
  severity: 'info' | 'warning' | 'error' | 'critical';
  enabled: boolean;
  last_triggered?: string;
  notification_channels: string[];
}

export interface SystemAlert {
  id: string;
  rule_id: string;
  severity: 'info' | 'warning' | 'error' | 'critical';
  title: string;
  message: string;
  triggered_at: string;
  resolved_at?: string;
  acknowledged_at?: string;
  acknowledged_by?: string;
  metadata?: Record<string, any>;
  rule: AlertRule;
}