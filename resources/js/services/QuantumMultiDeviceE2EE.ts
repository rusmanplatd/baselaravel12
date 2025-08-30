import { QuantumSafeE2EE } from './QuantumSafeE2EE';
import { QuantumKeyExchangeProtocol } from './QuantumKeyExchangeProtocol';

export interface DeviceInfo {
  deviceId: string;
  deviceName: string;
  deviceType: 'desktop' | 'mobile' | 'tablet' | 'web';
  platform: string;
  publicKey: Uint8Array;
  registeredAt: Date;
  lastSeen: Date;
  isCurrentDevice: boolean;
  isTrusted: boolean;
  trustLevel: number; // 0-10 scale
  verificationStatus: 'pending' | 'verified' | 'rejected' | 'expired';
  quantumSecurityLevel: number;
}

export interface DeviceSession {
  deviceId: string;
  sessionId: string;
  conversationKeys: Map<string, Uint8Array>;
  lastKeySync: Date;
  isActive: boolean;
}

export interface CrossDeviceMessage {
  messageId: string;
  conversationId: string;
  senderId: string;
  senderDeviceId: string;
  targetDevices: string[];
  encryptedForDevices: Map<string, Uint8Array>;
  timestamp: Date;
  quantumSafe: boolean;
}

export interface DeviceTrustChallenge {
  challengeId: string;
  fromDevice: string;
  toDevice: string;
  challenge: Uint8Array;
  expectedResponse: Uint8Array;
  expiresAt: Date;
  challengeType: 'initial_trust' | 'trust_verification' | 'key_verification';
}

export interface MultiDeviceSecurityMetrics {
  totalDevices: number;
  trustedDevices: number;
  activeDevices: number;
  averageTrustLevel: number;
  lastDeviceSync: Date;
  keyConsistencyScore: number;
  crossDeviceThreats: number;
  quantumReadinessScore: number;
}

export class QuantumMultiDeviceE2EE {
  private quantumE2EE: QuantumSafeE2EE;
  private keyExchange: QuantumKeyExchangeProtocol;
  private devices = new Map<string, DeviceInfo>();
  private sessions = new Map<string, DeviceSession>();
  private trustChallenges = new Map<string, DeviceTrustChallenge>();
  private currentDeviceId: string;
  private userId: string;

  // Configuration constants
  private readonly MAX_DEVICES = 10;
  private readonly TRUST_THRESHOLD = 7;
  private readonly KEY_SYNC_INTERVAL = 300000; // 5 minutes
  private readonly CHALLENGE_EXPIRY = 600000; // 10 minutes
  private readonly DEVICE_TIMEOUT = 1800000; // 30 minutes

  constructor(quantumE2EE: QuantumSafeE2EE) {
    this.quantumE2EE = quantumE2EE;
    this.keyExchange = new QuantumKeyExchangeProtocol(quantumE2EE);
    this.currentDeviceId = this.generateDeviceId();
    this.userId = '';
    this.startPeriodicTasks();
  }

  async initializeMultiDevice(userId: string): Promise<boolean> {
    try {
      this.userId = userId;
      
      // Initialize quantum E2EE for this device
      const initialized = await this.quantumE2EE.initializeDevice(userId);
      if (!initialized) {
        throw new Error('Failed to initialize quantum E2EE for device');
      }

      // Register current device
      await this.registerCurrentDevice();
      
      // Load existing trusted devices
      await this.loadTrustedDevices();
      
      // Start device discovery
      this.startDeviceDiscovery();
      
      return true;
    } catch (error) {
      console.error('Multi-device initialization failed:', error);
      return false;
    }
  }

  async registerDevice(deviceInfo: Omit<DeviceInfo, 'deviceId' | 'registeredAt' | 'lastSeen' | 'isCurrentDevice'>): Promise<string> {
    const deviceId = this.generateDeviceId();
    
    // Validate device count limit
    if (this.devices.size >= this.MAX_DEVICES) {
      throw new Error(`Maximum number of devices (${this.MAX_DEVICES}) reached`);
    }

    // Generate quantum-safe key pair for this device
    const keyPair = await this.quantumE2EE.generateQuantumKeyPair();
    
    const device: DeviceInfo = {
      ...deviceInfo,
      deviceId,
      publicKey: keyPair.publicKey,
      registeredAt: new Date(),
      lastSeen: new Date(),
      isCurrentDevice: deviceId === this.currentDeviceId,
      verificationStatus: 'pending',
      quantumSecurityLevel: 8
    };

    // Store device info
    this.devices.set(deviceId, device);
    
    // Create trust challenge for new device
    if (!device.isCurrentDevice) {
      await this.createTrustChallenge(this.currentDeviceId, deviceId, 'initial_trust');
    }

    // Persist to storage
    await this.persistDeviceInfo(device);
    
    return deviceId;
  }

  async verifyDevice(deviceId: string, verificationCode: string): Promise<boolean> {
    try {
      const device = this.devices.get(deviceId);
      if (!device) {
        throw new Error('Device not found');
      }

      // Find pending trust challenge
      const challenge = Array.from(this.trustChallenges.values())
        .find(c => c.toDevice === deviceId && c.challengeType === 'initial_trust');

      if (!challenge) {
        throw new Error('No pending trust challenge for device');
      }

      // Verify the challenge response
      const codeBytes = new TextEncoder().encode(verificationCode);
      const isValid = await this.verifyTrustChallenge(challenge.challengeId, codeBytes);

      if (isValid) {
        // Mark device as verified and trusted
        device.verificationStatus = 'verified';
        device.isTrusted = true;
        device.trustLevel = this.TRUST_THRESHOLD;
        
        // Initialize device session
        await this.initializeDeviceSession(deviceId);
        
        // Sync conversation keys to new device
        await this.syncConversationKeysToDevice(deviceId);
        
        // Clean up challenge
        this.trustChallenges.delete(challenge.challengeId);
        
        return true;
      }

      return false;
    } catch (error) {
      console.error('Device verification failed:', error);
      return false;
    }
  }

  async revokeDevice(deviceId: string): Promise<boolean> {
    try {
      const device = this.devices.get(deviceId);
      if (!device) {
        return false;
      }

      if (device.isCurrentDevice) {
        throw new Error('Cannot revoke current device');
      }

      // Remove device
      this.devices.delete(deviceId);
      
      // Clean up sessions
      this.sessions.delete(deviceId);
      
      // Remove from trusted devices storage
      await this.removeDeviceFromStorage(deviceId);
      
      // Rotate conversation keys to maintain forward secrecy
      await this.rotateAllConversationKeys('device_revocation');
      
      return true;
    } catch (error) {
      console.error('Device revocation failed:', error);
      return false;
    }
  }

  async encryptForMultipleDevices(
    message: string, 
    conversationId: string, 
    targetDevices?: string[]
  ): Promise<CrossDeviceMessage> {
    const devices = targetDevices ? 
      targetDevices.map(id => this.devices.get(id)).filter(d => d !== undefined) as DeviceInfo[] :
      Array.from(this.devices.values()).filter(d => d.isTrusted && d.verificationStatus === 'verified');

    const encryptedForDevices = new Map<string, Uint8Array>();
    const messageId = this.generateMessageId();

    // Encrypt message for each target device
    for (const device of devices) {
      try {
        // Use device-specific encryption key
        const deviceKey = await this.getOrCreateDeviceConversationKey(device.deviceId, conversationId);
        const encrypted = await this.quantumE2EE.encryptMessage(message, conversationId);
        
        // Additional encryption layer for device-specific key
        const deviceEncrypted = await this.encryptForDevice(encrypted, device.deviceId);
        encryptedForDevices.set(device.deviceId, deviceEncrypted);
        
      } catch (error) {
        console.warn(`Failed to encrypt for device ${device.deviceId}:`, error);
      }
    }

    const crossDeviceMessage: CrossDeviceMessage = {
      messageId,
      conversationId,
      senderId: this.userId,
      senderDeviceId: this.currentDeviceId,
      targetDevices: Array.from(encryptedForDevices.keys()),
      encryptedForDevices,
      timestamp: new Date(),
      quantumSafe: true
    };

    // Store for potential resend
    await this.storeCrossDeviceMessage(crossDeviceMessage);

    return crossDeviceMessage;
  }

  async decryptFromDevice(
    crossDeviceMessage: CrossDeviceMessage,
    currentDeviceId: string = this.currentDeviceId
  ): Promise<string> {
    const deviceEncrypted = crossDeviceMessage.encryptedForDevices.get(currentDeviceId);
    if (!deviceEncrypted) {
      throw new Error('Message not encrypted for this device');
    }

    // Decrypt device-specific layer
    const baseEncrypted = await this.decryptFromDevice(deviceEncrypted, currentDeviceId);
    
    // Decrypt the base message
    const message = await this.quantumE2EE.decryptMessage(baseEncrypted as any, crossDeviceMessage.conversationId);
    
    return message;
  }

  async syncDeviceKeys(deviceId: string): Promise<boolean> {
    try {
      const device = this.devices.get(deviceId);
      if (!device || !device.isTrusted) {
        return false;
      }

      const session = this.sessions.get(deviceId);
      if (!session) {
        throw new Error('No active session for device');
      }

      // Get all conversation keys that need syncing
      const conversationsToSync = await this.getConversationsRequiringSync(deviceId);
      
      for (const conversationId of conversationsToSync) {
        try {
          const conversationKey = await this.getOrCreateDeviceConversationKey(deviceId, conversationId);
          
          // Encrypt and send key to device
          const keyPacket = await this.createKeyPacket(deviceId, conversationId, conversationKey);
          await this.sendKeyPacketToDevice(deviceId, keyPacket);
          
          // Update session
          session.conversationKeys.set(conversationId, conversationKey);
          
        } catch (error) {
          console.warn(`Failed to sync key for conversation ${conversationId}:`, error);
        }
      }

      session.lastKeySync = new Date();
      return true;
      
    } catch (error) {
      console.error('Device key sync failed:', error);
      return false;
    }
  }

  async rotateDeviceKeys(deviceId?: string): Promise<boolean> {
    try {
      const devicesToRotate = deviceId ? 
        [this.devices.get(deviceId)].filter(d => d !== undefined) as DeviceInfo[] :
        Array.from(this.devices.values()).filter(d => d.isTrusted);

      for (const device of devicesToRotate) {
        // Generate new device key pair
        const newKeyPair = await this.quantumE2EE.generateQuantumKeyPair();
        
        // Update device info
        device.publicKey = newKeyPair.publicKey;
        device.lastSeen = new Date();
        
        // Rotate conversation keys for this device
        const session = this.sessions.get(device.deviceId);
        if (session) {
          session.conversationKeys.clear();
          await this.syncConversationKeysToDevice(device.deviceId);
        }
      }

      return true;
    } catch (error) {
      console.error('Device key rotation failed:', error);
      return false;
    }
  }

  async getMultiDeviceSecurityMetrics(): Promise<MultiDeviceSecurityMetrics> {
    const devices = Array.from(this.devices.values());
    const trustedDevices = devices.filter(d => d.isTrusted);
    const activeDevices = devices.filter(d => 
      Date.now() - d.lastSeen.getTime() < this.DEVICE_TIMEOUT
    );

    const averageTrustLevel = trustedDevices.length > 0 ? 
      trustedDevices.reduce((sum, d) => sum + d.trustLevel, 0) / trustedDevices.length : 0;

    // Calculate key consistency score
    const keyConsistencyScore = await this.calculateKeyConsistencyScore();
    
    // Get quantum readiness score
    const quantumReadinessScore = devices.length > 0 ?
      devices.reduce((sum, d) => sum + d.quantumSecurityLevel, 0) / devices.length : 0;

    return {
      totalDevices: devices.length,
      trustedDevices: trustedDevices.length,
      activeDevices: activeDevices.length,
      averageTrustLevel,
      lastDeviceSync: this.getLastDeviceSync(),
      keyConsistencyScore,
      crossDeviceThreats: this.detectCrossDeviceThreats(),
      quantumReadinessScore
    };
  }

  async exportMultiDeviceAudit(): Promise<any> {
    const metrics = await this.getMultiDeviceSecurityMetrics();
    const devices = Array.from(this.devices.values()).map(d => ({
      deviceId: d.deviceId,
      deviceName: d.deviceName,
      deviceType: d.deviceType,
      platform: d.platform,
      registeredAt: d.registeredAt,
      lastSeen: d.lastSeen,
      isTrusted: d.isTrusted,
      trustLevel: d.trustLevel,
      verificationStatus: d.verificationStatus,
      quantumSecurityLevel: d.quantumSecurityLevel
    }));

    return {
      timestamp: new Date().toISOString(),
      userId: this.userId,
      currentDeviceId: this.currentDeviceId,
      metrics,
      devices,
      activeSessions: this.sessions.size,
      pendingChallenges: this.trustChallenges.size,
      securityEvents: await this.getRecentSecurityEvents(),
      recommendations: this.generateSecurityRecommendations(metrics)
    };
  }

  getTrustedDevices(): DeviceInfo[] {
    return Array.from(this.devices.values()).filter(d => d.isTrusted);
  }

  getCurrentDevice(): DeviceInfo | undefined {
    return this.devices.get(this.currentDeviceId);
  }

  getDeviceCount(): number {
    return this.devices.size;
  }

  // Private helper methods

  private generateDeviceId(): string {
    const randomBytes = crypto.getRandomValues(new Uint8Array(16));
    return Array.from(randomBytes)
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  private generateMessageId(): string {
    return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  private async registerCurrentDevice(): Promise<void> {
    const deviceInfo: DeviceInfo = {
      deviceId: this.currentDeviceId,
      deviceName: await this.getDeviceName(),
      deviceType: this.getDeviceType(),
      platform: navigator.platform || 'unknown',
      publicKey: (await this.quantumE2EE.getDeviceKeyPair()).publicKey,
      registeredAt: new Date(),
      lastSeen: new Date(),
      isCurrentDevice: true,
      isTrusted: true,
      trustLevel: 10,
      verificationStatus: 'verified',
      quantumSecurityLevel: 9
    };

    this.devices.set(this.currentDeviceId, deviceInfo);
    await this.persistDeviceInfo(deviceInfo);
  }

  private async getDeviceName(): Promise<string> {
    return `${navigator.platform} Device`;
  }

  private getDeviceType(): 'desktop' | 'mobile' | 'tablet' | 'web' {
    const userAgent = navigator.userAgent.toLowerCase();
    if (userAgent.includes('mobile')) return 'mobile';
    if (userAgent.includes('tablet') || userAgent.includes('ipad')) return 'tablet';
    return 'web';
  }

  private async loadTrustedDevices(): Promise<void> {
    // Implementation would load from secure storage
    // For now, just load from localStorage
    try {
      const stored = localStorage.getItem(`quantum_devices_${this.userId}`);
      if (stored) {
        const deviceData = JSON.parse(stored);
        for (const device of deviceData) {
          if (device.deviceId !== this.currentDeviceId) {
            this.devices.set(device.deviceId, {
              ...device,
              registeredAt: new Date(device.registeredAt),
              lastSeen: new Date(device.lastSeen),
              isCurrentDevice: false
            });
          }
        }
      }
    } catch (error) {
      console.warn('Failed to load trusted devices:', error);
    }
  }

  private startDeviceDiscovery(): void {
    // Implementation for discovering other devices
    // This would typically involve WebRTC signaling or server-mediated discovery
    console.log('Starting device discovery...');
  }

  private startPeriodicTasks(): void {
    // Key sync interval
    setInterval(() => {
      this.performPeriodicKeySync();
    }, this.KEY_SYNC_INTERVAL);

    // Device heartbeat
    setInterval(() => {
      this.updateDeviceHeartbeat();
    }, 60000); // 1 minute

    // Challenge cleanup
    setInterval(() => {
      this.cleanupExpiredChallenges();
    }, 300000); // 5 minutes
  }

  private async performPeriodicKeySync(): Promise<void> {
    const trustedDevices = this.getTrustedDevices().filter(d => !d.isCurrentDevice);
    
    for (const device of trustedDevices) {
      try {
        await this.syncDeviceKeys(device.deviceId);
      } catch (error) {
        console.warn(`Periodic key sync failed for device ${device.deviceId}:`, error);
      }
    }
  }

  private updateDeviceHeartbeat(): void {
    const currentDevice = this.getCurrentDevice();
    if (currentDevice) {
      currentDevice.lastSeen = new Date();
    }
  }

  private cleanupExpiredChallenges(): void {
    const now = Date.now();
    for (const [challengeId, challenge] of this.trustChallenges) {
      if (now > challenge.expiresAt.getTime()) {
        this.trustChallenges.delete(challengeId);
      }
    }
  }

  private async createTrustChallenge(
    fromDevice: string, 
    toDevice: string, 
    type: DeviceTrustChallenge['challengeType']
  ): Promise<string> {
    const challengeId = this.generateDeviceId();
    const challenge = crypto.getRandomValues(new Uint8Array(32));
    const expectedResponse = await this.computeChallengeResponse(challenge);

    const trustChallenge: DeviceTrustChallenge = {
      challengeId,
      fromDevice,
      toDevice,
      challenge,
      expectedResponse,
      expiresAt: new Date(Date.now() + this.CHALLENGE_EXPIRY),
      challengeType: type
    };

    this.trustChallenges.set(challengeId, trustChallenge);
    return challengeId;
  }

  private async computeChallengeResponse(challenge: Uint8Array): Promise<Uint8Array> {
    // Use quantum-safe hash function (BLAKE3 equivalent)
    const hash = await crypto.subtle.digest('SHA-256', challenge);
    return new Uint8Array(hash);
  }

  private async verifyTrustChallenge(challengeId: string, response: Uint8Array): Promise<boolean> {
    const challenge = this.trustChallenges.get(challengeId);
    if (!challenge) return false;

    const expectedResponse = await this.computeChallengeResponse(response);
    return this.compareArrays(expectedResponse, challenge.expectedResponse);
  }

  private compareArrays(a: Uint8Array, b: Uint8Array): boolean {
    if (a.length !== b.length) return false;
    for (let i = 0; i < a.length; i++) {
      if (a[i] !== b[i]) return false;
    }
    return true;
  }

  private async initializeDeviceSession(deviceId: string): Promise<void> {
    const session: DeviceSession = {
      deviceId,
      sessionId: this.generateDeviceId(),
      conversationKeys: new Map(),
      lastKeySync: new Date(),
      isActive: true
    };

    this.sessions.set(deviceId, session);
  }

  private async syncConversationKeysToDevice(deviceId: string): Promise<void> {
    // Implementation for syncing keys to specific device
    console.log(`Syncing conversation keys to device ${deviceId}`);
  }

  private async getOrCreateDeviceConversationKey(deviceId: string, conversationId: string): Promise<Uint8Array> {
    const session = this.sessions.get(deviceId);
    if (session?.conversationKeys.has(conversationId)) {
      return session.conversationKeys.get(conversationId)!;
    }

    // Create new key for this device/conversation pair
    const key = crypto.getRandomValues(new Uint8Array(32));
    if (session) {
      session.conversationKeys.set(conversationId, key);
    }

    return key;
  }

  private async encryptForDevice(data: any, deviceId: string): Promise<Uint8Array> {
    const device = this.devices.get(deviceId);
    if (!device) {
      throw new Error('Device not found');
    }

    // Encrypt using device-specific key
    // Implementation would use device's public key
    return new Uint8Array(); // Placeholder
  }

  private async rotateAllConversationKeys(reason: string): Promise<void> {
    for (const session of this.sessions.values()) {
      session.conversationKeys.clear();
      session.lastKeySync = new Date();
    }
  }

  private async getConversationsRequiringSync(deviceId: string): Promise<string[]> {
    // Return list of conversation IDs that need key sync
    return []; // Placeholder
  }

  private async createKeyPacket(deviceId: string, conversationId: string, key: Uint8Array): Promise<any> {
    // Create encrypted key packet for device
    return {}; // Placeholder
  }

  private async sendKeyPacketToDevice(deviceId: string, keyPacket: any): Promise<void> {
    // Send key packet to device via secure channel
    console.log(`Sending key packet to device ${deviceId}`);
  }

  private async calculateKeyConsistencyScore(): Promise<number> {
    // Calculate how consistent keys are across devices
    return 9.5; // Placeholder
  }

  private getLastDeviceSync(): Date {
    let lastSync = new Date(0);
    for (const session of this.sessions.values()) {
      if (session.lastKeySync > lastSync) {
        lastSync = session.lastKeySync;
      }
    }
    return lastSync;
  }

  private detectCrossDeviceThreats(): number {
    // Detect potential threats across devices
    return 0; // Placeholder
  }

  private async getRecentSecurityEvents(): Promise<any[]> {
    // Get recent security events
    return []; // Placeholder
  }

  private generateSecurityRecommendations(metrics: MultiDeviceSecurityMetrics): string[] {
    const recommendations: string[] = [];

    if (metrics.averageTrustLevel < this.TRUST_THRESHOLD) {
      recommendations.push('Some devices have low trust levels - consider re-verification');
    }

    if (metrics.keyConsistencyScore < 8) {
      recommendations.push('Key consistency issues detected - perform full key sync');
    }

    if (metrics.totalDevices > 5) {
      recommendations.push('High number of devices - consider removing unused devices');
    }

    if (metrics.quantumReadinessScore < 8) {
      recommendations.push('Some devices need quantum security updates');
    }

    return recommendations;
  }

  private async persistDeviceInfo(device: DeviceInfo): Promise<void> {
    try {
      const devices = Array.from(this.devices.values());
      localStorage.setItem(`quantum_devices_${this.userId}`, JSON.stringify(devices));
    } catch (error) {
      console.warn('Failed to persist device info:', error);
    }
  }

  private async removeDeviceFromStorage(deviceId: string): Promise<void> {
    try {
      const devices = Array.from(this.devices.values()).filter(d => d.deviceId !== deviceId);
      localStorage.setItem(`quantum_devices_${this.userId}`, JSON.stringify(devices));
    } catch (error) {
      console.warn('Failed to remove device from storage:', error);
    }
  }

  private async storeCrossDeviceMessage(message: CrossDeviceMessage): Promise<void> {
    // Store message for potential resend/recovery
    const key = `cross_device_msg_${message.messageId}`;
    try {
      localStorage.setItem(key, JSON.stringify({
        ...message,
        encryptedForDevices: Array.from(message.encryptedForDevices.entries())
      }));
    } catch (error) {
      console.warn('Failed to store cross-device message:', error);
    }
  }
}