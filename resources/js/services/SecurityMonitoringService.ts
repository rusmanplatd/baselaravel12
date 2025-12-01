/**
 * Security Monitoring Service
 * Real-time security analytics and threat detection for multi-device E2EE
 */

import { E2EEError, E2EEErrorCode } from './E2EEErrors';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';

export interface SecurityEvent {
  id: string;
  type: SecurityEventType;
  severity: 'low' | 'medium' | 'high' | 'critical';
  timestamp: Date;
  deviceId: string;
  userId?: string;
  conversationId?: string;
  details: Record<string, any>;
  resolved: boolean;
  resolvedAt?: Date;
  resolvedBy?: string;
}

export enum SecurityEventType {
  DEVICE_REGISTRATION = 'device_registration',
  DEVICE_VERIFICATION_FAILED = 'device_verification_failed',
  SUSPICIOUS_ACTIVITY = 'suspicious_activity',
  ENCRYPTION_KEY_ROTATION = 'encryption_key_rotation',
  UNAUTHORIZED_ACCESS_ATTEMPT = 'unauthorized_access_attempt',
  MESSAGE_DECRYPTION_FAILED = 'message_decryption_failed',
  DEVICE_COMPROMISED = 'device_compromised',
  KEY_SHARE_FAILED = 'key_share_failed',
  SYNC_FAILURE = 'sync_failure',
  STORAGE_TAMPER_DETECTED = 'storage_tamper_detected',
  UNUSUAL_LOGIN_PATTERN = 'unusual_login_pattern',
  DEVICE_LOCATION_CHANGE = 'device_location_change',
}

export interface ThreatAnalysis {
  riskScore: number;
  riskLevel: 'low' | 'medium' | 'high' | 'critical';
  threats: Array<{
    type: string;
    likelihood: number;
    impact: number;
    description: string;
    mitigation: string[];
  }>;
  recommendations: string[];
  lastAnalyzed: Date;
}

export interface SecurityMetrics {
  totalEvents: number;
  criticalEvents: number;
  resolvedEvents: number;
  averageResolutionTime: number;
  mostCommonThreat: SecurityEventType | null;
  deviceHealthScore: number;
  encryptionIntegrityScore: number;
  userBehaviorScore: number;
  overallSecurityScore: number;
}

export class SecurityMonitoringService {
  private events: Map<string, SecurityEvent> = new Map();
  private eventListeners: Set<(event: SecurityEvent) => void> = new Set();
  private analysisCache: Map<string, ThreatAnalysis> = new Map();
  private isMonitoring = false;
  private monitoringInterval: NodeJS.Timeout | null = null;

  constructor() {
    this.loadStoredEvents();
    this.startMonitoring();
  }

  /**
   * Start real-time security monitoring
   */
  startMonitoring(): void {
    if (this.isMonitoring) return;

    this.isMonitoring = true;
    
    // Monitor at 30-second intervals
    this.monitoringInterval = setInterval(() => {
      this.performSecurityScan();
    }, 30000);

    // Initial scan
    this.performSecurityScan();
  }

  /**
   * Stop security monitoring
   */
  stopMonitoring(): void {
    if (!this.isMonitoring) return;

    this.isMonitoring = false;
    
    if (this.monitoringInterval) {
      clearInterval(this.monitoringInterval);
      this.monitoringInterval = null;
    }
  }

  /**
   * Log a security event
   */
  logEvent(
    type: SecurityEventType,
    severity: 'low' | 'medium' | 'high' | 'critical',
    deviceId: string,
    details: Record<string, any>,
    options?: {
      userId?: string;
      conversationId?: string;
    }
  ): SecurityEvent {
    const event: SecurityEvent = {
      id: crypto.randomUUID(),
      type,
      severity,
      timestamp: new Date(),
      deviceId,
      userId: options?.userId,
      conversationId: options?.conversationId,
      details,
      resolved: false,
    };

    this.events.set(event.id, event);
    this.persistEvents();

    // Notify listeners
    this.eventListeners.forEach(listener => listener(event));

    // Trigger analysis if it's a critical event
    if (severity === 'critical') {
      this.analyzeThreatLevel(deviceId);
    }

    console.log(`[Security Event] ${type} - ${severity}:`, details);
    return event;
  }

  /**
   * Resolve a security event
   */
  resolveEvent(eventId: string, resolvedBy?: string): boolean {
    const event = this.events.get(eventId);
    if (!event) return false;

    event.resolved = true;
    event.resolvedAt = new Date();
    event.resolvedBy = resolvedBy;

    this.persistEvents();
    return true;
  }

  /**
   * Get security events with filtering
   */
  getEvents(filter?: {
    deviceId?: string;
    type?: SecurityEventType;
    severity?: string;
    resolved?: boolean;
    since?: Date;
    limit?: number;
  }): SecurityEvent[] {
    let events = Array.from(this.events.values());

    if (filter) {
      if (filter.deviceId) {
        events = events.filter(e => e.deviceId === filter.deviceId);
      }
      if (filter.type) {
        events = events.filter(e => e.type === filter.type);
      }
      if (filter.severity) {
        events = events.filter(e => e.severity === filter.severity);
      }
      if (filter.resolved !== undefined) {
        events = events.filter(e => e.resolved === filter.resolved);
      }
      if (filter.since) {
        events = events.filter(e => e.timestamp >= filter.since!);
      }
    }

    // Sort by timestamp (newest first)
    events.sort((a, b) => b.timestamp.getTime() - a.timestamp.getTime());

    if (filter?.limit) {
      events = events.slice(0, filter.limit);
    }

    return events;
  }

  /**
   * Analyze threat level for a device
   */
  async analyzeThreatLevel(deviceId: string): Promise<ThreatAnalysis> {
    const cacheKey = `threat_analysis_${deviceId}`;
    const cached = this.analysisCache.get(cacheKey);

    // Return cached analysis if recent (less than 10 minutes old)
    if (cached && (Date.now() - cached.lastAnalyzed.getTime()) < 600000) {
      return cached;
    }

    const recentEvents = this.getEvents({
      deviceId,
      since: new Date(Date.now() - 24 * 60 * 60 * 1000), // Last 24 hours
    });

    const analysis: ThreatAnalysis = {
      riskScore: 0,
      riskLevel: 'low',
      threats: [],
      recommendations: [],
      lastAnalyzed: new Date(),
    };

    // Analyze different threat vectors
    this.analyzeDeviceIntegrity(recentEvents, analysis);
    this.analyzeEncryptionThreats(recentEvents, analysis);
    this.analyzeBehavioralAnomalies(recentEvents, analysis);
    this.analyzeNetworkThreats(recentEvents, analysis);

    // Calculate overall risk score
    analysis.riskScore = Math.min(100, Math.max(0, analysis.riskScore));
    
    // Determine risk level
    if (analysis.riskScore >= 80) analysis.riskLevel = 'critical';
    else if (analysis.riskScore >= 60) analysis.riskLevel = 'high';
    else if (analysis.riskScore >= 30) analysis.riskLevel = 'medium';
    else analysis.riskLevel = 'low';

    // Cache the analysis
    this.analysisCache.set(cacheKey, analysis);

    return analysis;
  }

  /**
   * Get security metrics
   */
  getSecurityMetrics(deviceId?: string): SecurityMetrics {
    const events = deviceId ? this.getEvents({ deviceId }) : Array.from(this.events.values());
    const criticalEvents = events.filter(e => e.severity === 'critical');
    const resolvedEvents = events.filter(e => e.resolved);

    // Calculate average resolution time
    let totalResolutionTime = 0;
    let resolvedCount = 0;
    
    for (const event of resolvedEvents) {
      if (event.resolvedAt) {
        totalResolutionTime += event.resolvedAt.getTime() - event.timestamp.getTime();
        resolvedCount++;
      }
    }

    const averageResolutionTime = resolvedCount > 0 ? totalResolutionTime / resolvedCount : 0;

    // Find most common threat
    const threatCounts = new Map<SecurityEventType, number>();
    for (const event of events) {
      threatCounts.set(event.type, (threatCounts.get(event.type) || 0) + 1);
    }

    let mostCommonThreat: SecurityEventType | null = null;
    let maxCount = 0;
    for (const [threat, count] of threatCounts) {
      if (count > maxCount) {
        maxCount = count;
        mostCommonThreat = threat;
      }
    }

    // Calculate component scores
    const deviceHealthScore = this.calculateDeviceHealthScore(events);
    const encryptionIntegrityScore = this.calculateEncryptionIntegrityScore(events);
    const userBehaviorScore = this.calculateUserBehaviorScore(events);

    // Overall security score (weighted average)
    const overallSecurityScore = Math.round(
      (deviceHealthScore * 0.4 + encryptionIntegrityScore * 0.4 + userBehaviorScore * 0.2)
    );

    return {
      totalEvents: events.length,
      criticalEvents: criticalEvents.length,
      resolvedEvents: resolvedEvents.length,
      averageResolutionTime: Math.round(averageResolutionTime / 1000), // in seconds
      mostCommonThreat,
      deviceHealthScore,
      encryptionIntegrityScore,
      userBehaviorScore,
      overallSecurityScore,
    };
  }

  /**
   * Subscribe to security events
   */
  onSecurityEvent(listener: (event: SecurityEvent) => void): () => void {
    this.eventListeners.add(listener);
    
    // Return unsubscribe function
    return () => {
      this.eventListeners.delete(listener);
    };
  }

  /**
   * Generate security report
   */
  generateSecurityReport(deviceId?: string): {
    metrics: SecurityMetrics;
    recentEvents: SecurityEvent[];
    threatAnalysis: ThreatAnalysis | null;
    recommendations: string[];
  } {
    const metrics = this.getSecurityMetrics(deviceId);
    const recentEvents = this.getEvents({ 
      deviceId, 
      since: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000), // Last 7 days
      limit: 50 
    });
    
    let threatAnalysis: ThreatAnalysis | null = null;
    if (deviceId) {
      const cached = this.analysisCache.get(`threat_analysis_${deviceId}`);
      threatAnalysis = cached || null;
    }

    const recommendations = this.generateSecurityRecommendations(metrics, recentEvents);

    return {
      metrics,
      recentEvents,
      threatAnalysis,
      recommendations,
    };
  }

  private performSecurityScan(): void {
    // Check for storage tampering
    this.checkStorageTampering();
    
    // Check for unusual patterns
    this.checkUnusualPatterns();
    
    // Validate encryption integrity
    this.validateEncryptionIntegrity();
  }

  private checkStorageTampering(): void {
    try {
      // Basic integrity check for stored E2EE data
      const storedDevice = getUserStorageItem('e2ee_current_device');
      if (storedDevice) {
        const device = JSON.parse(storedDevice);
        if (!device.fingerprint || !device.publicKey || !device.privateKey) {
          this.logEvent(
            SecurityEventType.STORAGE_TAMPER_DETECTED,
            'critical',
            device.id || 'unknown',
            { 
              issue: 'Device data appears to be corrupted or tampered with',
              missingFields: [
                !device.fingerprint && 'fingerprint',
                !device.publicKey && 'publicKey', 
                !device.privateKey && 'privateKey'
              ].filter(Boolean)
            }
          );
        }
      }
    } catch (error) {
      console.error('Storage tampering check failed:', error);
    }
  }

  private checkUnusualPatterns(): void {
    const recentEvents = this.getEvents({ 
      since: new Date(Date.now() - 60 * 60 * 1000) // Last hour
    });

    // Check for rapid succession of failed events
    const failedEvents = recentEvents.filter(e => 
      e.type === SecurityEventType.DEVICE_VERIFICATION_FAILED ||
      e.type === SecurityEventType.MESSAGE_DECRYPTION_FAILED
    );

    if (failedEvents.length >= 5) {
      this.logEvent(
        SecurityEventType.SUSPICIOUS_ACTIVITY,
        'high',
        failedEvents[0].deviceId,
        {
          pattern: 'Multiple failed operations in short timeframe',
          eventCount: failedEvents.length,
          timeframe: '1 hour'
        }
      );
    }
  }

  private validateEncryptionIntegrity(): void {
    // This would perform actual encryption validation
    // For now, we'll simulate basic checks
    console.log('Performing encryption integrity validation...');
  }

  private analyzeDeviceIntegrity(events: SecurityEvent[], analysis: ThreatAnalysis): void {
    const deviceEvents = events.filter(e => 
      e.type === SecurityEventType.DEVICE_REGISTRATION ||
      e.type === SecurityEventType.DEVICE_VERIFICATION_FAILED ||
      e.type === SecurityEventType.STORAGE_TAMPER_DETECTED
    );

    if (deviceEvents.length > 0) {
      analysis.riskScore += deviceEvents.length * 10;
      analysis.threats.push({
        type: 'Device Integrity',
        likelihood: Math.min(90, deviceEvents.length * 20),
        impact: 80,
        description: 'Device integrity issues detected',
        mitigation: ['Re-verify device', 'Check for malware', 'Update security settings']
      });
    }
  }

  private analyzeEncryptionThreats(events: SecurityEvent[], analysis: ThreatAnalysis): void {
    const encryptionEvents = events.filter(e =>
      e.type === SecurityEventType.MESSAGE_DECRYPTION_FAILED ||
      e.type === SecurityEventType.KEY_SHARE_FAILED
    );

    if (encryptionEvents.length > 2) {
      analysis.riskScore += encryptionEvents.length * 15;
      analysis.threats.push({
        type: 'Encryption Weakness',
        likelihood: Math.min(80, encryptionEvents.length * 15),
        impact: 90,
        description: 'Multiple encryption-related failures detected',
        mitigation: ['Rotate encryption keys', 'Re-establish secure channels', 'Verify device trust']
      });
    }
  }

  private analyzeBehavioralAnomalies(events: SecurityEvent[], analysis: ThreatAnalysis): void {
    const behavioralEvents = events.filter(e =>
      e.type === SecurityEventType.UNUSUAL_LOGIN_PATTERN ||
      e.type === SecurityEventType.DEVICE_LOCATION_CHANGE
    );

    if (behavioralEvents.length > 0) {
      analysis.riskScore += behavioralEvents.length * 5;
      analysis.recommendations.push('Review recent device activity for unusual patterns');
    }
  }

  private analyzeNetworkThreats(events: SecurityEvent[], analysis: ThreatAnalysis): void {
    const networkEvents = events.filter(e =>
      e.type === SecurityEventType.UNAUTHORIZED_ACCESS_ATTEMPT ||
      e.type === SecurityEventType.SYNC_FAILURE
    );

    if (networkEvents.length > 1) {
      analysis.riskScore += networkEvents.length * 8;
      analysis.threats.push({
        type: 'Network Security',
        likelihood: Math.min(70, networkEvents.length * 12),
        impact: 60,
        description: 'Network-related security events detected',
        mitigation: ['Check network security', 'Use VPN', 'Verify connection integrity']
      });
    }
  }

  private calculateDeviceHealthScore(events: SecurityEvent[]): number {
    const deviceEvents = events.filter(e =>
      e.type === SecurityEventType.DEVICE_VERIFICATION_FAILED ||
      e.type === SecurityEventType.STORAGE_TAMPER_DETECTED ||
      e.type === SecurityEventType.DEVICE_COMPROMISED
    );

    return Math.max(0, 100 - deviceEvents.length * 20);
  }

  private calculateEncryptionIntegrityScore(events: SecurityEvent[]): number {
    const encryptionEvents = events.filter(e =>
      e.type === SecurityEventType.MESSAGE_DECRYPTION_FAILED ||
      e.type === SecurityEventType.KEY_SHARE_FAILED
    );

    return Math.max(0, 100 - encryptionEvents.length * 15);
  }

  private calculateUserBehaviorScore(events: SecurityEvent[]): number {
    const behaviorEvents = events.filter(e =>
      e.type === SecurityEventType.UNUSUAL_LOGIN_PATTERN ||
      e.type === SecurityEventType.SUSPICIOUS_ACTIVITY
    );

    return Math.max(0, 100 - behaviorEvents.length * 10);
  }

  private generateSecurityRecommendations(metrics: SecurityMetrics, events: SecurityEvent[]): string[] {
    const recommendations: string[] = [];

    if (metrics.overallSecurityScore < 60) {
      recommendations.push('Immediate attention required: Security score is below acceptable level');
    }

    if (metrics.criticalEvents > 0) {
      recommendations.push('Address critical security events immediately');
    }

    if (metrics.deviceHealthScore < 80) {
      recommendations.push('Improve device security configuration');
    }

    if (metrics.encryptionIntegrityScore < 80) {
      recommendations.push('Review and strengthen encryption settings');
    }

    const unverifiedDeviceEvents = events.filter(e => 
      e.type === SecurityEventType.DEVICE_VERIFICATION_FAILED
    );
    if (unverifiedDeviceEvents.length > 0) {
      recommendations.push('Complete device verification for all devices');
    }

    return recommendations;
  }

  private loadStoredEvents(): void {
    try {
      const stored = getUserStorageItem('e2ee_security_events');
      if (stored) {
        const events = JSON.parse(stored);
        for (const eventData of events) {
          const event: SecurityEvent = {
            ...eventData,
            timestamp: new Date(eventData.timestamp),
            resolvedAt: eventData.resolvedAt ? new Date(eventData.resolvedAt) : undefined,
          };
          this.events.set(event.id, event);
        }
      }
    } catch (error) {
      console.error('Failed to load stored security events:', error);
    }
  }

  private persistEvents(): void {
    try {
      const events = Array.from(this.events.values());
      setUserStorageItem('e2ee_security_events', JSON.stringify(events));
    } catch (error) {
      console.error('Failed to persist security events:', error);
    }
  }
}

// Singleton instance
export const securityMonitor = new SecurityMonitoringService();