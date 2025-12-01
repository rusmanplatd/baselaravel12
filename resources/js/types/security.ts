export interface SecurityEvent {
  id: string;
  event_type: string;
  severity: 'critical' | 'high' | 'medium' | 'low' | 'info';
  risk_score: number;
  status: 'normal' | 'pending' | 'investigating' | 'resolved' | 'false_positive';
  user_id?: string;
  device_id?: string;
  conversation_id?: string;
  ip_address?: string;
  user_agent?: string;
  location?: {
    country?: string;
    region?: string;
  };
  metadata?: Record<string, any>;
  resolved_at?: string;
  resolved_by?: string;
  organization_id?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email?: string;
  };
  device?: {
    id: string;
    device_name: string;
    device_type: string;
  };
  conversation?: {
    id: string;
    name?: string;
    type: string;
  };
  resolvedBy?: {
    id: string;
    name: string;
  };
}

export interface SecurityMetrics {
  total_events: number;
  critical_events: number;
  high_risk_events: number;
  medium_risk_events: number;
  low_risk_events: number;
  unresolved_events: number;
}

export interface UserAnomaly {
  type: string;
  severity: 'low' | 'medium' | 'high';
  description: string;
  locations?: string[];
  unusual_hours?: number[];
  count?: number;
}

export interface SecurityDashboardData {
  metrics: SecurityMetrics;
  recent_events: SecurityEvent[];
  user_anomalies: UserAnomaly[];
  security_score: number;
}

export interface SecurityReport {
  generated_at: string;
  period: {
    from: string;
    to: string;
  };
  summary: {
    total_events: number;
    high_risk_events: number;
    medium_risk_events: number;
    low_risk_events: number;
    unresolved_events: number;
  };
  event_breakdown: Record<string, number>;
  risk_trends: Record<string, {
    average_risk: number;
    total_events: number;
    high_risk_events: number;
  }>;
  top_users_by_risk: Array<{
    user_id: string;
    user_name: string;
    total_risk_score: number;
    average_risk_score: number;
    event_count: number;
  }>;
  geographic_distribution: Record<string, number>;
  recommendations: Array<{
    priority: 'high' | 'medium' | 'low';
    category: string;
    title: string;
    description: string;
  }>;
}

export interface EventType {
  event_type: string;
  category: string;
  action: string;
  detail?: string;
  description: string;
}

export interface AuditLogFilters {
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
}