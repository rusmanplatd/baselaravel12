/**
 * Signal Session Manager
 * Complete session management for Signal-like E2EE chat implementation
 * 
 * Features:
 * - Session lifecycle management
 * - Prekey bundle management and refresh
 * - Session establishment and maintenance  
 * - Message delivery and ordering
 * - Key rotation and forward secrecy
 * - Session recovery and error handling
 */

import { signalProtocolService, type SignalMessage, type SignalSession } from './SignalProtocolService';
import { x3dhKeyAgreement, type PreKeyBundle } from './X3DHKeyAgreement';
import { multiDeviceE2EEService } from './MultiDeviceE2EEService';
import { E2EEError } from './E2EEErrors';
import { apiService } from './ApiService';

export interface SessionInfo {
  sessionId: string;
  userId: string;
  isActive: boolean;
  established: Date;
  lastActivity: Date;
  messagesSent: number;
  messagesReceived: number;
  keyRotations: number;
  protocolVersion: string;
  verificationStatus: 'unverified' | 'verified' | 'trusted';
  deviceInfo?: {
    deviceId: string;
    deviceName: string;
    platform: string;
  };
}

export interface ConversationSessions {
  conversationId: string;
  sessions: Map<string, SessionInfo>; // deviceId -> SessionInfo
  activeSession?: string; // Current primary session
  groupInfo?: {
    isGroup: boolean;
    memberCount: number;
    adminDevices: string[];
  };
}

export interface MessageDeliveryOptions {
  priority: 'normal' | 'high' | 'urgent';
  requiresReceipt: boolean;
  expirationTime?: number;
  forwardSecrecy: boolean;
}

export interface SessionEstablishmentResult {
  sessionId: string;
  isNewSession: boolean;
  requiresVerification: boolean;
  fingerprintChanged: boolean;
}

export class SignalSessionManager {
  private conversationSessions = new Map<string, ConversationSessions>();
  private userToDeviceMapping = new Map<string, Set<string>>();
  private pendingSessionEstablishments = new Map<string, Promise<string>>();
  private maintenanceInterval: NodeJS.Timeout | null = null;
  
  private readonly STORAGE_KEY = 'signal_session_manager';
  private readonly MAINTENANCE_INTERVAL = 60 * 60 * 1000; // 1 hour
  private readonly SESSION_TIMEOUT = 30 * 24 * 60 * 60 * 1000; // 30 days
  private readonly PREKEY_REFRESH_THRESHOLD = 10; // Refresh when below 10 prekeys

  constructor() {
    this.loadState();
    this.startMaintenance();
  }

  /**
   * Initialize the session manager
   */
  async initialize(): Promise<void> {
    try {
      // Initialize Signal Protocol service
      await signalProtocolService.initialize();
      
      // Initialize X3DH
      await x3dhKeyAgreement.initialize();
      
      // Refresh prekeys if needed
      await this.refreshPreKeysIfNeeded();
      
      console.log('Signal Session Manager initialized');
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Establish a session with a user in a conversation
   */
  async establishSession(
    conversationId: string,
    userId: string,
    deviceId?: string
  ): Promise<SessionEstablishmentResult> {
    const cacheKey = `${conversationId}_${userId}_${deviceId || 'default'}`;
    
    // Prevent concurrent session establishment
    if (this.pendingSessionEstablishments.has(cacheKey)) {
      const existingSessionId = await this.pendingSessionEstablishments.get(cacheKey)!;
      return {
        sessionId: existingSessionId,
        isNewSession: false,
        requiresVerification: false,
        fingerprintChanged: false,
      };
    }

    const establishmentPromise = this.doEstablishSession(conversationId, userId, deviceId);
    this.pendingSessionEstablishments.set(cacheKey, establishmentPromise);

    try {
      const result = await establishmentPromise;
      this.pendingSessionEstablishments.delete(cacheKey);
      return result;
    } catch (error) {
      this.pendingSessionEstablishments.delete(cacheKey);
      throw error;
    }
  }

  private async doEstablishSession(
    conversationId: string,
    userId: string,
    deviceId?: string
  ): Promise<SessionEstablishmentResult> {
    try {
      // Check if session already exists
      const existingSession = this.findExistingSession(conversationId, userId, deviceId);
      if (existingSession && existingSession.isActive) {
        return {
          sessionId: existingSession.sessionId,
          isNewSession: false,
          requiresVerification: false,
          fingerprintChanged: false,
        };
      }

      // Start new session with Signal Protocol
      const sessionId = await signalProtocolService.startSession(userId);
      
      // Get device information
      const deviceInfo = deviceId ? await this.getDeviceInfo(deviceId) : undefined;
      
      // Create session info
      const sessionInfo: SessionInfo = {
        sessionId,
        userId,
        isActive: true,
        established: new Date(),
        lastActivity: new Date(),
        messagesSent: 0,
        messagesReceived: 0,
        keyRotations: 0,
        protocolVersion: '3.0',
        verificationStatus: 'unverified',
        deviceInfo,
      };

      // Store session
      this.storeSessionInfo(conversationId, userId, sessionInfo);
      
      // Map user to device
      if (deviceId) {
        this.addUserDeviceMapping(userId, deviceId);
      }

      await this.saveState();

      return {
        sessionId,
        isNewSession: true,
        requiresVerification: true,
        fingerprintChanged: false,
      };
    } catch (error) {
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Send an encrypted message using Signal Protocol
   */
  async sendMessage(
    conversationId: string,
    userId: string,
    message: string,
    options: MessageDeliveryOptions = {
      priority: 'normal',
      requiresReceipt: false,
      forwardSecrecy: true,
    }
  ): Promise<{ messageId: string; deliveryStatus: 'sent' | 'pending' | 'failed' }> {
    try {
      // Ensure session exists
      const sessionResult = await this.establishSession(conversationId, userId);
      const sessionInfo = this.getSessionInfo(conversationId, userId);
      
      if (!sessionInfo) {
        throw new Error('Failed to establish session');
      }

      // Determine if this is a prekey message (first message in session)
      const isPreKeyMessage = sessionInfo.messagesSent === 0;

      // Encrypt with Signal Protocol
      const signalMessage = await signalProtocolService.encryptMessage(
        sessionResult.sessionId,
        message,
        isPreKeyMessage
      );

      // Send to server
      const messageId = await this.deliverMessage(
        conversationId,
        userId,
        signalMessage,
        options
      );

      // Update session stats
      sessionInfo.messagesSent++;
      sessionInfo.lastActivity = new Date();
      
      // Rotate keys if forward secrecy is enabled and threshold reached
      if (options.forwardSecrecy && sessionInfo.messagesSent % 100 === 0) {
        await this.rotateSessionKeys(sessionResult.sessionId);
        sessionInfo.keyRotations++;
      }

      await this.saveState();

      return {
        messageId,
        deliveryStatus: 'sent',
      };
    } catch (error) {
      console.error('Failed to send message:', error);
      return {
        messageId: '',
        deliveryStatus: 'failed',
      };
    }
  }

  /**
   * Receive and decrypt an encrypted message
   */
  async receiveMessage(
    conversationId: string,
    userId: string,
    encryptedMessage: SignalMessage
  ): Promise<{ decryptedMessage: string; sessionId: string }> {
    try {
      let sessionId: string;
      let decryptedMessage: string;

      if (encryptedMessage.type === 'prekey') {
        // Handle prekey message (session establishment)
        const result = await signalProtocolService.processPreKeyMessage(
          userId,
          encryptedMessage
        );
        sessionId = result.sessionId;
        decryptedMessage = result.decryptedMessage;

        // Create session info for new session
        const sessionInfo: SessionInfo = {
          sessionId,
          userId,
          isActive: true,
          established: new Date(),
          lastActivity: new Date(),
          messagesSent: 0,
          messagesReceived: 1,
          keyRotations: 0,
          protocolVersion: '3.0',
          verificationStatus: 'unverified',
        };

        this.storeSessionInfo(conversationId, userId, sessionInfo);
      } else {
        // Handle normal message
        const sessionInfo = this.getSessionInfo(conversationId, userId);
        if (!sessionInfo) {
          throw new Error('No active session found for user');
        }

        sessionId = sessionInfo.sessionId;
        decryptedMessage = await signalProtocolService.decryptMessage(
          sessionId,
          encryptedMessage
        );

        // Update session stats
        sessionInfo.messagesReceived++;
        sessionInfo.lastActivity = new Date();
      }

      await this.saveState();

      return { decryptedMessage, sessionId };
    } catch (error) {
      throw E2EEError.decryptionFailed(
        conversationId,
        encryptedMessage.message.header.N,
        error instanceof Error ? error : undefined
      );
    }
  }

  /**
   * Rotate session keys for forward secrecy
   */
  async rotateSessionKeys(sessionId: string): Promise<void> {
    try {
      await signalProtocolService.rotateSessionKeys(sessionId);
      console.log(`Session keys rotated for session: ${sessionId}`);
    } catch (error) {
      console.error('Failed to rotate session keys:', error);
      throw error;
    }
  }

  /**
   * Verify a user's identity key
   */
  async verifyUserIdentity(
    conversationId: string,
    userId: string,
    expectedFingerprint: string
  ): Promise<boolean> {
    try {
      const sessionInfo = this.getSessionInfo(conversationId, userId);
      if (!sessionInfo) {
        throw new Error('No session found for user');
      }

      const session = signalProtocolService.getSession(sessionInfo.sessionId);
      if (!session) {
        throw new Error('Signal session not found');
      }

      // Calculate fingerprint of remote identity key
      const fingerprint = await this.calculateIdentityFingerprint(session.remoteIdentityKey);
      const verified = fingerprint === expectedFingerprint;

      if (verified) {
        sessionInfo.verificationStatus = 'verified';
        await this.saveState();
      }

      return verified;
    } catch (error) {
      console.error('Identity verification failed:', error);
      return false;
    }
  }

  /**
   * Get conversation session information
   */
  getConversationSessions(conversationId: string): ConversationSessions | null {
    return this.conversationSessions.get(conversationId) || null;
  }

  /**
   * List all active sessions
   */
  getActiveSessions(): SessionInfo[] {
    const allSessions: SessionInfo[] = [];
    
    for (const conversation of this.conversationSessions.values()) {
      for (const session of conversation.sessions.values()) {
        if (session.isActive) {
          allSessions.push(session);
        }
      }
    }
    
    return allSessions;
  }

  /**
   * Close a specific session
   */
  async closeSession(conversationId: string, userId: string): Promise<void> {
    const sessionInfo = this.getSessionInfo(conversationId, userId);
    if (sessionInfo) {
      sessionInfo.isActive = false;
      await signalProtocolService.closeSession(sessionInfo.sessionId);
      await this.saveState();
    }
  }

  /**
   * Get session statistics
   */
  getSessionStatistics(): {
    totalSessions: number;
    activeSessions: number;
    averageSessionAge: number;
    totalMessagesExchanged: number;
    keyRotationsPerformed: number;
    verifiedSessions: number;
  } {
    const allSessions = this.getAllSessions();
    const activeSessions = allSessions.filter(s => s.isActive);
    
    const now = Date.now();
    const averageAge = activeSessions.length > 0
      ? activeSessions.reduce((sum, s) => sum + (now - s.established.getTime()), 0) / activeSessions.length
      : 0;

    return {
      totalSessions: allSessions.length,
      activeSessions: activeSessions.length,
      averageSessionAge: averageAge,
      totalMessagesExchanged: allSessions.reduce((sum, s) => sum + s.messagesSent + s.messagesReceived, 0),
      keyRotationsPerformed: allSessions.reduce((sum, s) => sum + s.keyRotations, 0),
      verifiedSessions: allSessions.filter(s => s.verificationStatus === 'verified').length,
    };
  }

  /**
   * Perform maintenance tasks
   */
  async performMaintenance(): Promise<void> {
    try {
      console.log('Starting session maintenance...');

      // Clean up expired sessions
      await this.cleanupExpiredSessions();
      
      // Refresh prekeys if needed
      await this.refreshPreKeysIfNeeded();
      
      // Perform Signal Protocol maintenance
      await signalProtocolService.performMaintenance();
      
      // Update statistics
      const stats = this.getSessionStatistics();
      console.log('Session maintenance completed:', stats);
    } catch (error) {
      console.error('Session maintenance failed:', error);
    }
  }

  /**
   * Private helper methods
   */
  private findExistingSession(
    conversationId: string,
    userId: string,
    deviceId?: string
  ): SessionInfo | null {
    const conversation = this.conversationSessions.get(conversationId);
    if (!conversation) return null;

    // If deviceId specified, look for that specific session
    if (deviceId) {
      return conversation.sessions.get(deviceId) || null;
    }

    // Otherwise, find any active session for the user
    for (const session of conversation.sessions.values()) {
      if (session.userId === userId && session.isActive) {
        return session;
      }
    }

    return null;
  }

  private storeSessionInfo(
    conversationId: string,
    userId: string,
    sessionInfo: SessionInfo
  ): void {
    let conversation = this.conversationSessions.get(conversationId);
    if (!conversation) {
      conversation = {
        conversationId,
        sessions: new Map(),
      };
      this.conversationSessions.set(conversationId, conversation);
    }

    const deviceKey = sessionInfo.deviceInfo?.deviceId || `${userId}_default`;
    conversation.sessions.set(deviceKey, sessionInfo);
    
    // Set as active session if it's the first one
    if (!conversation.activeSession) {
      conversation.activeSession = deviceKey;
    }
  }

  private getSessionInfo(conversationId: string, userId: string): SessionInfo | null {
    const conversation = this.conversationSessions.get(conversationId);
    if (!conversation) return null;

    // Find session for user
    for (const session of conversation.sessions.values()) {
      if (session.userId === userId && session.isActive) {
        return session;
      }
    }

    return null;
  }

  private getAllSessions(): SessionInfo[] {
    const allSessions: SessionInfo[] = [];
    
    for (const conversation of this.conversationSessions.values()) {
      for (const session of conversation.sessions.values()) {
        allSessions.push(session);
      }
    }
    
    return allSessions;
  }

  private addUserDeviceMapping(userId: string, deviceId: string): void {
    let devices = this.userToDeviceMapping.get(userId);
    if (!devices) {
      devices = new Set();
      this.userToDeviceMapping.set(userId, devices);
    }
    devices.add(deviceId);
  }

  private async getDeviceInfo(deviceId: string): Promise<SessionInfo['deviceInfo']> {
    try {
      const deviceData = await multiDeviceE2EEService.getUserDevices();
      const device = deviceData.find(d => d.id === deviceId);
      
      if (device) {
        return {
          deviceId: device.id!,
          deviceName: device.name,
          platform: device.platform,
        };
      }
    } catch (error) {
      console.warn('Failed to get device info:', error);
    }
    
    return undefined;
  }

  private async deliverMessage(
    conversationId: string,
    userId: string,
    message: SignalMessage,
    options: MessageDeliveryOptions
  ): Promise<string> {
    try {
      const response = await apiService.post('/api/v1/chat/messages/signal', {
        conversation_id: conversationId,
        recipient_user_id: userId,
        signal_message: {
          type: message.type,
          version: message.version,
          registration_id: message.registrationId,
          prekey_id: message.preKeyId,
          signed_prekey_id: message.signedPreKeyId,
          base_key: message.baseKey,
          identity_key: message.identityKey,
          message: message.message,
          timestamp: message.timestamp,
        },
        delivery_options: options,
      });
      
      return response.message_id;
    } catch (error) {
      throw new Error('Failed to deliver message to server');
    }
  }

  private async calculateIdentityFingerprint(identityKey: ArrayBuffer): Promise<string> {
    const hash = await crypto.subtle.digest('SHA-256', identityKey);
    const hashArray = new Uint8Array(hash);
    return Array.from(hashArray)
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  private async refreshPreKeysIfNeeded(): Promise<void> {
    const stats = x3dhKeyAgreement.getPreKeyStatistics();
    
    if (stats.oneTimePreKeys < this.PREKEY_REFRESH_THRESHOLD) {
      console.log('Refreshing prekeys...');
      await x3dhKeyAgreement.refreshPreKeysIfNeeded();
    }
  }

  private async cleanupExpiredSessions(): Promise<number> {
    const now = Date.now();
    let cleanedCount = 0;

    for (const [conversationId, conversation] of this.conversationSessions) {
      const expiredSessions = Array.from(conversation.sessions.entries())
        .filter(([, session]) => {
          const age = now - session.lastActivity.getTime();
          return age > this.SESSION_TIMEOUT || !session.isActive;
        });

      for (const [deviceKey, session] of expiredSessions) {
        await signalProtocolService.closeSession(session.sessionId);
        conversation.sessions.delete(deviceKey);
        cleanedCount++;
      }

      // Remove empty conversations
      if (conversation.sessions.size === 0) {
        this.conversationSessions.delete(conversationId);
      }
    }

    if (cleanedCount > 0) {
      await this.saveState();
    }

    return cleanedCount;
  }

  private startMaintenance(): void {
    if (this.maintenanceInterval) return;

    this.maintenanceInterval = setInterval(
      () => this.performMaintenance(),
      this.MAINTENANCE_INTERVAL
    );
  }

  private stopMaintenance(): void {
    if (this.maintenanceInterval) {
      clearInterval(this.maintenanceInterval);
      this.maintenanceInterval = null;
    }
  }

  /**
   * State persistence
   */
  private async saveState(): Promise<void> {
    try {
      const stateData = {
        conversations: Array.from(this.conversationSessions.entries()).map(([id, conv]) => ({
          conversationId: id,
          activeSession: conv.activeSession,
          sessions: Array.from(conv.sessions.entries()).map(([deviceKey, session]) => ({
            deviceKey,
            sessionId: session.sessionId,
            userId: session.userId,
            isActive: session.isActive,
            established: session.established.toISOString(),
            lastActivity: session.lastActivity.toISOString(),
            messagesSent: session.messagesSent,
            messagesReceived: session.messagesReceived,
            keyRotations: session.keyRotations,
            protocolVersion: session.protocolVersion,
            verificationStatus: session.verificationStatus,
            deviceInfo: session.deviceInfo,
          })),
        })),
        userDeviceMappings: Array.from(this.userToDeviceMapping.entries()).map(([userId, devices]) => ({
          userId,
          devices: Array.from(devices),
        })),
      };

      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(stateData));
    } catch (error) {
      console.error('Failed to save session manager state:', error);
    }
  }

  private loadState(): void {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (!stored) return;

      const stateData = JSON.parse(stored);
      
      // Restore conversations and sessions
      for (const convData of stateData.conversations || []) {
        const conversation: ConversationSessions = {
          conversationId: convData.conversationId,
          sessions: new Map(),
          activeSession: convData.activeSession,
        };

        for (const sessionData of convData.sessions || []) {
          const session: SessionInfo = {
            sessionId: sessionData.sessionId,
            userId: sessionData.userId,
            isActive: sessionData.isActive,
            established: new Date(sessionData.established),
            lastActivity: new Date(sessionData.lastActivity),
            messagesSent: sessionData.messagesSent,
            messagesReceived: sessionData.messagesReceived,
            keyRotations: sessionData.keyRotations,
            protocolVersion: sessionData.protocolVersion,
            verificationStatus: sessionData.verificationStatus,
            deviceInfo: sessionData.deviceInfo,
          };

          conversation.sessions.set(sessionData.deviceKey, session);
        }

        this.conversationSessions.set(convData.conversationId, conversation);
      }

      // Restore user-device mappings
      for (const mapping of stateData.userDeviceMappings || []) {
        this.userToDeviceMapping.set(mapping.userId, new Set(mapping.devices));
      }
    } catch (error) {
      console.error('Failed to load session manager state:', error);
    }
  }

  /**
   * Cleanup on destruction
   */
  destroy(): void {
    this.stopMaintenance();
  }
}

// Singleton instance
export const signalSessionManager = new SignalSessionManager();
export default SignalSessionManager;