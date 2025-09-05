export interface UserDevice {
  id: string;
  user_id: string;
  name: string;
  device_type: 'mobile' | 'tablet' | 'desktop' | 'laptop' | 'watch' | 'tv' | 'other';
  os_name?: string;
  os_version?: string;
  browser_name?: string;
  browser_version?: string;
  user_agent?: string;
  ip_address?: string;
  device_fingerprint?: string;
  public_key?: string;
  public_key_fingerprint?: string;
  encryption_version?: string;
  is_trusted: boolean;
  is_suspended: boolean;
  is_primary: boolean;
  last_seen_at?: string;
  last_location?: DeviceLocation;
  session_count?: number;
  message_count?: number;
  keys_rotated_at?: string;
  trusted_at?: string;
  suspended_at?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email: string;
  };
  
  sessions?: DeviceSession[];
  security_events?: DeviceSecurityEvent[];
}

export interface DeviceLocation {
  country?: string;
  country_code?: string;
  region?: string;
  city?: string;
  latitude?: number;
  longitude?: number;
  timezone?: string;
  isp?: string;
  recorded_at: string;
}

export interface DeviceSession {
  id: string;
  device_id: string;
  session_token?: string;
  ip_address?: string;
  user_agent?: string;
  location?: DeviceLocation;
  is_active: boolean;
  started_at: string;
  last_activity_at?: string;
  ended_at?: string;
  created_at: string;
  updated_at: string;
}

export interface DeviceSecurityEvent {
  id: string;
  device_id: string;
  event_type: 'login_attempt' | 'login_success' | 'login_failed' | 'key_rotation' | 'trust_granted' | 'trust_revoked' | 'device_suspended' | 'suspicious_activity';
  severity: 'low' | 'medium' | 'high' | 'critical';
  description: string;
  metadata?: Record<string, any>;
  ip_address?: string;
  user_agent?: string;
  location?: DeviceLocation;
  resolved: boolean;
  resolved_at?: string;
  resolved_by?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  device?: UserDevice;
  resolver?: {
    id: string;
    name: string;
    email: string;
  };
}

export interface DeviceTrustRequest {
  id: string;
  device_id: string;
  request_method: 'email_verification' | 'sms_verification' | 'admin_approval' | 'biometric' | 'hardware_key';
  verification_code?: string;
  verification_token?: string;
  expires_at: string;
  verified_at?: string;
  approved_at?: string;
  rejected_at?: string;
  status: 'pending' | 'verified' | 'approved' | 'rejected' | 'expired';
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  
  // Relationships
  device?: UserDevice;
  approver?: {
    id: string;
    name: string;
    email: string;
  };
}

export interface DevicePairingCode {
  id: string;
  user_id: string;
  device_name: string;
  pairing_code: string;
  qr_code_data?: string;
  expires_at: string;
  used_at?: string;
  used_by_device_id?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email: string;
  };
  device?: UserDevice;
}

export interface DeviceCapabilities {
  supports_e2ee: boolean;
  supports_quantum_encryption: boolean;
  supports_biometric_auth: boolean;
  supports_hardware_keys: boolean;
  supports_push_notifications: boolean;
  supports_background_sync: boolean;
  max_file_size_mb: number;
  supported_file_types: string[];
  encryption_algorithms: string[];
}

export interface DeviceStats {
  total_devices: number;
  trusted_devices: number;
  untrusted_devices: number;
  suspended_devices: number;
  active_devices_24h: number;
  new_devices_7d: number;
  devices_by_type: {
    [type: string]: number;
  };
  devices_by_os: {
    [os: string]: number;
  };
  encryption_versions: {
    [version: string]: number;
  };
  security_events_24h: number;
  average_session_duration_minutes: number;
}

export interface DeviceSecurityPolicy {
  id: string;
  organization_id?: string;
  user_id?: string;
  name: string;
  description?: string;
  require_device_trust: boolean;
  require_biometric_auth: boolean;
  require_hardware_keys: boolean;
  max_inactive_days: number;
  force_encryption_version?: string;
  allowed_device_types: string[];
  blocked_countries: string[];
  require_vpn: boolean;
  max_concurrent_sessions: number;
  session_timeout_hours: number;
  auto_suspend_suspicious: boolean;
  notify_new_device: boolean;
  notify_location_change: boolean;
  is_default: boolean;
  priority: number;
  enabled: boolean;
  created_by: string;
  updated_by?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  organization?: {
    id: string;
    name: string;
  };
  user?: {
    id: string;
    name: string;
    email: string;
  };
  creator?: {
    id: string;
    name: string;
    email: string;
  };
}

export interface DeviceComplianceCheck {
  id: string;
  device_id: string;
  policy_id: string;
  check_type: 'manual' | 'automatic' | 'scheduled';
  status: 'passed' | 'failed' | 'warning' | 'pending';
  score: number; // 0-100
  violations: DeviceViolation[];
  recommendations: string[];
  checked_at: string;
  next_check_at?: string;
  
  // Relationships
  device?: UserDevice;
  policy?: DeviceSecurityPolicy;
}

export interface DeviceViolation {
  rule: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  description: string;
  current_value?: string;
  expected_value?: string;
  remediation?: string;
}

export interface DeviceBackup {
  id: string;
  device_id: string;
  backup_type: 'keys' | 'settings' | 'full';
  encryption_key?: string;
  backup_data: string; // Encrypted backup data
  file_size_bytes: number;
  created_at: string;
  expires_at?: string;
  restored_at?: string;
  
  // Relationships
  device?: UserDevice;
}

export interface DeviceRecovery {
  id: string;
  user_id: string;
  old_device_id?: string;
  new_device_id?: string;
  recovery_method: 'backup_restore' | 'key_sharing' | 'admin_recovery' | 'trusted_contact';
  recovery_data?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
  verification_code?: string;
  verified_at?: string;
  completed_at?: string;
  error_message?: string;
  created_at: string;
  updated_at: string;
  
  // Relationships
  user?: {
    id: string;
    name: string;
    email: string;
  };
  old_device?: UserDevice;
  new_device?: UserDevice;
}

// API Request/Response Types
export interface DeviceCreateData {
  name: string;
  device_type: string;
  os_name?: string;
  os_version?: string;
  browser_name?: string;
  browser_version?: string;
  user_agent?: string;
  device_fingerprint?: string;
  public_key?: string;
  capabilities?: Partial<DeviceCapabilities>;
}

export interface DeviceUpdateData {
  name?: string;
  is_trusted?: boolean;
  is_suspended?: boolean;
  is_primary?: boolean;
  encryption_version?: string;
  last_seen_at?: string;
}

export interface DeviceListResponse {
  devices: UserDevice[];
  stats?: DeviceStats;
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface PairingCodeResponse {
  pairing_code: string;
  qr_code_data: string;
  expires_at: string;
  instructions: string;
}

export interface DeviceTrustResponse {
  success: boolean;
  message: string;
  trust_request?: DeviceTrustRequest;
  verification_required?: boolean;
}

export interface DeviceKeyRotationResponse {
  success: boolean;
  message: string;
  new_public_key?: string;
  new_fingerprint?: string;
}

export interface DeviceLocationResponse {
  location: DeviceLocation;
  accuracy: 'high' | 'medium' | 'low';
  source: 'gps' | 'ip' | 'cell' | 'wifi';
}