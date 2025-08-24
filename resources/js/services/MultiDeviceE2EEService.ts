/**
 * Multi-Device End-to-End Encryption Service
 * Handles device registration, key management, and encrypted messaging
 */

import { E2EEError, E2EEErrorHandler, E2EEErrorCode } from './E2EEErrors';
import { securityMonitor, SecurityEventType } from './SecurityMonitoringService';

export interface DeviceInfo {
  id?: string;
  name: string;
  type: 'mobile' | 'desktop' | 'web' | 'tablet';
  platform: string;
  userAgent: string;
  fingerprint: string;
  publicKey: string;
  privateKey: string;
  capabilities: string[];
  securityLevel: 'low' | 'medium' | 'high' | 'maximum';
  isTrusted: boolean;
  isActive: boolean;
  lastUsed?: Date;
  verifiedAt?: Date;
}

export interface EncryptedMessage {
  data: string;
  iv: string;
  hash: string;
  hmac: string;
  authData: string;
  timestamp: number;
  nonce: string;
  keyVersion: number;
  deviceId: string;
}

export interface ConversationKey {
  keyId: string;
  encryptedKey: string;
  publicKey: string;
  keyVersion: number;
  deviceFingerprint: string;
  isActive: boolean;
}

export interface DeviceVerificationChallenge {
  challengeId: string;
  deviceId: string;
  timestamp: number;
  nonce: string;
  verificationType: 'security_key' | 'verification_code' | 'passkey' | 'biometric';
  expiresAt: Date;
  verificationMethods: string[];
}

export interface SecurityReport {
  deviceId: string;
  deviceName: string;
  integrityReport: {
    securityScore: number;
    status: 'healthy' | 'warning' | 'critical';
    issues: Array<{
      type: string;
      severity: 'low' | 'medium' | 'high';
      message: string;
      [key: string]: any;
    }>;
    recommendations: string[];
  };
  encryptionSummary: {
    deviceId: string;
    deviceName: string;
    isTrusted: boolean;
    securityLevel: string;
    securityScore: number;
    encryptionVersion: number;
    activeConversationKeys: number;
    pendingKeyShares: number;
    requiresKeyRotation: boolean;
    lastUsed: string | null;
    lastKeyRotation: string | null;
  };
  generatedAt: Date;
}

export interface KeyBackupData {
  version: number;
  timestamp: number;
  deviceId: string;
  encryptedPrivateKey: string;
  salt: string;
  iv: string;
  iterations: number;
  conversationKeys: Array<{
    conversationId: string;
    encryptedKey: string;
    keyVersion: number;
  }>;
  deviceFingerprint: string;
  backupId: string;
}

export interface DeviceVerificationOptions {
  method: 'passkey' | 'security_key' | 'verification_code' | 'qr_code' | 'manual';
  timeout?: number;
  requiresBiometric?: boolean;
}

export interface MessageSyncData {
  messageId: string;
  conversationId: string;
  encryptedContent: EncryptedMessage;
  timestamp: number;
  deviceId: string;
  syncStatus: 'pending' | 'synced' | 'failed';
  retryCount: number;
}

export interface SyncReport {
  totalMessages: number;
  syncedMessages: number;
  failedMessages: number;
  pendingMessages: number;
  lastSyncAt: Date;
  syncErrors: Array<{
    messageId: string;
    error: string;
    conversationId: string;
  }>;
}

class MultiDeviceE2EEService {
  private currentDevice: DeviceInfo | null = null;
  private conversationKeys = new Map<string, ConversationKey>();
  private symmetricKeys = new Map<string, string>();
  private keyCache = new Map<string, CryptoKey>();
  private pendingSyncMessages = new Map<string, MessageSyncData>();
  private syncQueue: MessageSyncData[] = [];
  private isSyncing = false;
  
  private readonly STORAGE_PREFIX = 'e2ee_';
  private readonly KEY_CACHE_TTL = 30 * 60 * 1000; // 30 minutes
  
  constructor() {
    this.initializeDevice();
  }

  /**
   * Initialize the current device
   */
  private async initializeDevice(): Promise<void> {
    try {
      // Try to load existing device info from secure storage
      try {
        const storedDevice = this.getFromSecureStorage('current_device');
        if (storedDevice) {
          this.currentDevice = storedDevice;
          return;
        }
      } catch (error) {
        // If we can't read from storage, continue with new device initialization
        console.warn('Could not load existing device from storage, creating new device', error);
      }

      // Generate new device info
      const keyPair = await this.generateKeyPair();
      this.currentDevice = {
        name: this.getDeviceName(),
        type: this.getDeviceType(),
        platform: this.getPlatform(),
        userAgent: navigator.userAgent,
        fingerprint: await this.generateDeviceFingerprint(),
        publicKey: keyPair.publicKey,
        privateKey: keyPair.privateKey,
        capabilities: this.getDeviceCapabilities(),
        securityLevel: this.getDeviceSecurityLevel(),
        isTrusted: false,
        isActive: true,
      };

      // Store device info securely
      this.setSecureStorage('current_device', this.currentDevice);

      // Load pending sync data
      this.loadPendingSyncData();
    } catch (error) {
      console.error('Failed to initialize device:', error);
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Register this device with the server
   */
  async registerDevice(): Promise<{ device: any; verification?: DeviceVerificationChallenge }> {
    if (!this.currentDevice) {
      throw E2EEError.deviceNotInitialized();
    }

    return await E2EEErrorHandler.withRetry(async () => {
      const response = await fetch('/api/v1/chat/devices', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Device-Fingerprint': this.currentDevice!.fingerprint,
          'X-CSRF-TOKEN': this.getCSRFToken(),
        },
        body: JSON.stringify({
          device_name: this.currentDevice!.name,
          device_type: this.currentDevice!.type,
          public_key: this.currentDevice!.publicKey,
          device_fingerprint: this.currentDevice!.fingerprint,
          hardware_fingerprint: await this.generateHardwareFingerprint(),
          platform: this.currentDevice!.platform,
          user_agent: this.currentDevice!.userAgent,
          device_capabilities: this.currentDevice!.capabilities,
          security_level: this.currentDevice!.securityLevel,
          device_info: {
            screen: `${screen.width}x${screen.height}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
          },
        }),
      });

      const result = await E2EEErrorHandler.handleApiResponse(response);
      
      // Update current device with server info
      if (this.currentDevice) {
        this.currentDevice.id = result.device.id;
        this.currentDevice.isTrusted = result.device.is_trusted;
        this.setSecureStorage('current_device', this.currentDevice);
      }

      return result;
    }).then(result => {
      // Log successful device registration
      if (this.currentDevice?.id) {
        securityMonitor.logEvent(
          SecurityEventType.DEVICE_REGISTRATION,
          'low',
          this.currentDevice.id,
          {
            deviceName: this.currentDevice.name,
            deviceType: this.currentDevice.type,
            securityLevel: this.currentDevice.securityLevel,
            isTrusted: result.device.is_trusted,
          }
        );
      }
      return result;
    }).catch(error => {
      // Log failed device registration
      if (this.currentDevice?.id) {
        securityMonitor.logEvent(
          SecurityEventType.DEVICE_REGISTRATION,
          'medium',
          this.currentDevice.id,
          {
            error: error.message,
            deviceName: this.currentDevice.name,
            deviceType: this.currentDevice.type,
          }
        );
      }
      throw error;
    });
  }

  /**
   * Complete device verification
   */
  async verifyDevice(challengeId: string, response: any): Promise<boolean> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    const verificationResponse = await fetch(`/api/v1/chat/devices/${this.currentDevice.id}/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        challenge_id: challengeId,
        response,
      }),
    });

    try {
      const result = await E2EEErrorHandler.handleApiResponse(verificationResponse);
      
      // Update device trust status
      if (this.currentDevice) {
        this.currentDevice.isTrusted = result.device.is_trusted;
        this.currentDevice.verifiedAt = new Date(result.device.verified_at);
        this.setSecureStorage('current_device', this.currentDevice);
      }

      // Log successful verification
      securityMonitor.logEvent(
        SecurityEventType.DEVICE_VERIFICATION_FAILED,
        'low',
        this.currentDevice.id,
        {
          challengeId,
          verified: true,
          method: 'challenge_response'
        }
      );

      return true;
    } catch (error) {
      // Log failed verification
      securityMonitor.logEvent(
        SecurityEventType.DEVICE_VERIFICATION_FAILED,
        'medium',
        this.currentDevice.id,
        {
          challengeId,
          error: error instanceof Error ? error.message : 'Unknown error',
          verified: false,
        }
      );

      if (error instanceof E2EEError && error.details.code === E2EEErrorCode.API_ERROR) {
        throw E2EEError.deviceVerificationFailed(this.currentDevice.id);
      }
      throw error;
    }
  }

  /**
   * Get list of user devices
   */
  async getUserDevices(): Promise<DeviceInfo[]> {
    const response = await fetch('/api/v1/chat/devices', {
      headers: {
        'X-Device-Fingerprint': this.currentDevice?.fingerprint || '',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
    });

    if (!response.ok) {
      throw new Error('Failed to fetch devices');
    }

    const result = await response.json();
    return result.devices.map((device: any) => ({
      id: device.id,
      name: device.device_name,
      type: device.device_type,
      platform: device.platform,
      fingerprint: device.short_fingerprint,
      isTrusted: device.is_trusted,
      isActive: true,
      lastUsed: device.last_used_at ? new Date(device.last_used_at) : undefined,
      // Other fields would be filled from detailed device info if needed
    }));
  }

  /**
   * Encrypt a message for a conversation
   */
  async encryptMessage(conversationId: string, message: string): Promise<EncryptedMessage> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    if (!this.currentDevice.isTrusted) {
      throw E2EEError.deviceNotTrusted(this.currentDevice.id);
    }

    try {
      // Get or generate conversation key
      let conversationKey = this.conversationKeys.get(conversationId);
      if (!conversationKey) {
        conversationKey = await this.getConversationKey(conversationId);
      }

      // Decrypt the symmetric key for this conversation
      const symmetricKey = await this.decryptSymmetricKey(conversationKey.encryptedKey);
      
      // Encrypt the message
      const encrypted = await this.encryptWithSymmetricKey(message, symmetricKey);
      
      const encryptedMessage = {
        ...encrypted,
        keyVersion: conversationKey.keyVersion,
        deviceId: this.currentDevice.id,
      };

      // Queue message for cross-device sync
      await this.queueMessageForSync(conversationId, encryptedMessage);

      return encryptedMessage;
    } catch (error) {
      console.error('Encryption failed:', error);
      throw E2EEError.encryptionFailed(conversationId, error instanceof Error ? error : undefined);
    }
  }

  /**
   * Decrypt a message
   */
  async decryptMessage(conversationId: string, encryptedMessage: EncryptedMessage): Promise<string> {
    try {
      // Verify message timestamp (prevent replay attacks)
      const messageAge = Date.now() - encryptedMessage.timestamp;
      const maxAge = 24 * 60 * 60 * 1000; // 24 hours
      
      if (messageAge > maxAge) {
        throw E2EEError.messageTooOld(encryptedMessage.timestamp);
      }

      // Get conversation key for the specific version
      const conversationKey = await this.getConversationKey(conversationId, encryptedMessage.keyVersion);
      
      // Decrypt the symmetric key
      const symmetricKey = await this.decryptSymmetricKey(conversationKey.encryptedKey);
      
      // Decrypt the message
      return await this.decryptWithSymmetricKey(encryptedMessage, symmetricKey);
    } catch (error) {
      // Log decryption failure
      if (this.currentDevice?.id) {
        securityMonitor.logEvent(
          SecurityEventType.MESSAGE_DECRYPTION_FAILED,
          'medium',
          this.currentDevice.id,
          {
            conversationId,
            keyVersion: encryptedMessage.keyVersion,
            error: error instanceof Error ? error.message : 'Unknown decryption error',
            messageAge: Date.now() - encryptedMessage.timestamp,
          },
          { conversationId }
        );
      }

      if (error instanceof E2EEError) {
        throw error;
      }
      console.error('Decryption failed:', error);
      throw E2EEError.decryptionFailed(
        conversationId, 
        encryptedMessage.keyVersion, 
        error instanceof Error ? error : undefined
      );
    }
  }

  /**
   * Set up encryption for a new conversation
   */
  async setupConversationEncryption(conversationId: string, participantDevices: string[]): Promise<void> {
    if (!this.currentDevice?.id) {
      throw new Error('Device not registered');
    }

    const response = await fetch(`/api/v1/chat/conversations/${conversationId}/setup-encryption-multidevice`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        device_keys: participantDevices.map(deviceId => ({ device_id: deviceId })),
        initiating_device_id: this.currentDevice.id,
      }),
    });

    if (!response.ok) {
      throw new Error('Failed to setup conversation encryption');
    }

    const result = await response.json();
    
    // Cache the conversation key
    await this.getConversationKey(conversationId);
  }

  /**
   * Rotate conversation keys
   */
  async rotateConversationKeys(conversationId: string, reason?: string): Promise<void> {
    if (!this.currentDevice?.id) {
      throw new Error('Device not registered');
    }

    const response = await fetch(`/api/v1/chat/conversations/${conversationId}/rotate-key-multidevice`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        initiating_device_id: this.currentDevice.id,
        reason,
      }),
    });

    if (!response.ok) {
      throw new Error('Failed to rotate conversation keys');
    }

    // Clear cached keys to force reload
    this.conversationKeys.delete(conversationId);
    this.symmetricKeys.clear();
    this.keyCache.clear();
  }

  /**
   * Get device security report
   */
  async getDeviceSecurityReport(): Promise<SecurityReport> {
    if (!this.currentDevice?.id) {
      throw new Error('Device not registered');
    }

    const response = await fetch(`/api/v1/chat/devices/${this.currentDevice.id}/security-report`, {
      headers: {
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
    });

    if (!response.ok) {
      throw new Error('Failed to get security report');
    }

    const result = await response.json();
    return {
      ...result,
      generatedAt: new Date(result.generated_at),
    };
  }

  /**
   * Share keys with a new device
   */
  async shareKeysWithDevice(targetDeviceId: string): Promise<void> {
    if (!this.currentDevice?.id || !this.currentDevice.fingerprint) {
      throw new Error('Device not registered or trusted');
    }

    const response = await fetch(`/api/v1/chat/devices/${targetDeviceId}/share-keys`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        from_device_fingerprint: this.currentDevice.fingerprint,
      }),
    });

    if (!response.ok) {
      throw new Error('Failed to share keys with device');
    }
  }

  /**
   * Trust a device
   */
  async trustDevice(deviceId: string): Promise<void> {
    const response = await fetch(`/api/v1/chat/devices/${deviceId}/trust`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({}),
    });

    if (!response.ok) {
      throw new Error('Failed to trust device');
    }
  }

  /**
   * Remove a device
   */
  async removeDevice(deviceId: string): Promise<void> {
    const response = await fetch(`/api/v1/chat/devices/${deviceId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
    });

    if (!response.ok) {
      throw new Error('Failed to remove device');
    }
  }

  // Private helper methods
  
  private async getConversationKey(conversationId: string, keyVersion?: number): Promise<ConversationKey> {
    if (!this.currentDevice?.id) {
      throw new Error('Device not registered');
    }

    const cacheKey = `${conversationId}_${keyVersion || 'latest'}`;
    const cachedKey = this.conversationKeys.get(cacheKey);
    if (cachedKey) {
      return cachedKey;
    }

    const response = await fetch(`/api/v1/chat/conversations/${conversationId}/device-key`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        device_id: this.currentDevice.id,
        key_version: keyVersion,
      }),
    });

    if (!response.ok) {
      throw new Error('Failed to get conversation key');
    }

    const result = await response.json();
    const conversationKey: ConversationKey = {
      keyId: result.key_id,
      encryptedKey: result.encrypted_key,
      publicKey: result.public_key,
      keyVersion: result.key_version,
      deviceFingerprint: result.device_fingerprint,
      isActive: true,
    };

    this.conversationKeys.set(cacheKey, conversationKey);
    return conversationKey;
  }

  private async decryptSymmetricKey(encryptedKey: string): Promise<string> {
    if (!this.currentDevice?.privateKey) {
      throw E2EEError.keyGenerationFailed();
    }

    // Import private key
    const privateKeyObj = await this.importPrivateKey(this.currentDevice.privateKey);
    
    // Decrypt the symmetric key
    const encryptedBuffer = this.base64ToArrayBuffer(encryptedKey);
    const decryptedBuffer = await crypto.subtle.decrypt(
      { name: 'RSA-OAEP' },
      privateKeyObj,
      encryptedBuffer
    );

    return this.arrayBufferToBase64(decryptedBuffer);
  }

  private async encryptWithSymmetricKey(message: string, symmetricKeyBase64: string): Promise<Omit<EncryptedMessage, 'keyVersion' | 'deviceId'>> {
    const symmetricKey = await this.importSymmetricKey(symmetricKeyBase64);
    
    // Generate IV and nonce
    const iv = crypto.getRandomValues(new Uint8Array(16));
    const nonce = crypto.getRandomValues(new Uint8Array(8));
    const timestamp = Date.now();
    
    // Create auth data
    const authData = {
      timestamp,
      nonce: this.arrayBufferToBase64(nonce),
    };
    
    // Encrypt message
    const encoder = new TextEncoder();
    const messageBuffer = encoder.encode(message);
    
    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      symmetricKey,
      messageBuffer
    );

    const encryptedData = this.arrayBufferToBase64(encryptedBuffer);
    const ivBase64 = this.arrayBufferToBase64(iv);
    const authDataBase64 = btoa(JSON.stringify(authData));
    
    // Calculate HMAC
    const hmacKey = await crypto.subtle.importKey(
      'raw',
      this.base64ToArrayBuffer(symmetricKeyBase64),
      { name: 'HMAC', hash: 'SHA-256' },
      false,
      ['sign']
    );
    
    const hmacData = encoder.encode(encryptedData + ivBase64 + authDataBase64);
    const hmacBuffer = await crypto.subtle.sign('HMAC', hmacKey, hmacData);
    const hmac = this.arrayBufferToBase64(hmacBuffer);
    
    // Calculate content hash
    const hashBuffer = await crypto.subtle.digest('SHA-256', messageBuffer);
    const hash = this.arrayBufferToBase64(hashBuffer);

    return {
      data: encryptedData,
      iv: ivBase64,
      hash,
      hmac,
      authData: authDataBase64,
      timestamp,
      nonce: this.arrayBufferToBase64(nonce),
    };
  }

  private async decryptWithSymmetricKey(encryptedMessage: EncryptedMessage, symmetricKeyBase64: string): Promise<string> {
    const symmetricKey = await this.importSymmetricKey(symmetricKeyBase64);
    
    // Verify HMAC
    const hmacKey = await crypto.subtle.importKey(
      'raw',
      this.base64ToArrayBuffer(symmetricKeyBase64),
      { name: 'HMAC', hash: 'SHA-256' },
      false,
      ['verify']
    );
    
    const encoder = new TextEncoder();
    const hmacData = encoder.encode(encryptedMessage.data + encryptedMessage.iv + encryptedMessage.authData);
    const hmacBuffer = this.base64ToArrayBuffer(encryptedMessage.hmac);
    
    const hmacValid = await crypto.subtle.verify(
      'HMAC',
      hmacKey,
      hmacBuffer,
      hmacData
    );
    
    if (!hmacValid) {
      throw new Error('Message authentication failed');
    }
    
    // Check timestamp (replay protection)
    const authData = JSON.parse(atob(encryptedMessage.authData));
    const messageAge = Date.now() - authData.timestamp;
    if (messageAge > 3600000) { // 1 hour
      throw new Error('Message too old');
    }
    
    // Decrypt message
    const encryptedBuffer = this.base64ToArrayBuffer(encryptedMessage.data);
    const iv = this.base64ToArrayBuffer(encryptedMessage.iv);
    
    const decryptedBuffer = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      symmetricKey,
      encryptedBuffer
    );
    
    const decoder = new TextDecoder();
    const decryptedMessage = decoder.decode(decryptedBuffer);
    
    // Verify content hash
    const hashBuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(decryptedMessage));
    const calculatedHash = this.arrayBufferToBase64(hashBuffer);
    
    if (calculatedHash !== encryptedMessage.hash) {
      throw new Error('Message integrity check failed');
    }
    
    return decryptedMessage;
  }

  private async generateKeyPair(): Promise<{ publicKey: string; privateKey: string }> {
    const keyPair = await crypto.subtle.generateKey(
      {
        name: 'RSA-OAEP',
        modulusLength: 4096,
        publicExponent: new Uint8Array([65537]),
        hash: 'SHA-256',
      },
      true,
      ['encrypt', 'decrypt']
    );

    const publicKey = await crypto.subtle.exportKey('spki', keyPair.publicKey);
    const privateKey = await crypto.subtle.exportKey('pkcs8', keyPair.privateKey);

    return {
      publicKey: this.arrayBufferToPEM(publicKey, 'PUBLIC KEY'),
      privateKey: this.arrayBufferToPEM(privateKey, 'PRIVATE KEY'),
    };
  }

  private async importPrivateKey(pemPrivateKey: string): Promise<CryptoKey> {
    const binaryDer = this.pemToArrayBuffer(pemPrivateKey);
    return await crypto.subtle.importKey(
      'pkcs8',
      binaryDer,
      {
        name: 'RSA-OAEP',
        hash: 'SHA-256',
      },
      false,
      ['decrypt']
    );
  }

  private async importSymmetricKey(base64Key: string): Promise<CryptoKey> {
    const keyBuffer = this.base64ToArrayBuffer(base64Key);
    return await crypto.subtle.importKey(
      'raw',
      keyBuffer,
      { name: 'AES-GCM' },
      false,
      ['encrypt', 'decrypt']
    );
  }

  private async generateDeviceFingerprint(): Promise<string> {
    const components = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      new Date().getTimezoneOffset().toString(),
      navigator.hardwareConcurrency?.toString() || '0',
      // Add more stable device characteristics
    ].join('|');

    const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(components));
    return this.arrayBufferToBase64(hash);
  }

  private async generateHardwareFingerprint(): Promise<string> {
    try {
      // Try to get more hardware-specific info
      const components = [
        navigator.userAgent,
        navigator.platform,
        screen.width + 'x' + screen.height + 'x' + screen.colorDepth,
        navigator.hardwareConcurrency?.toString() || '0',
        navigator.deviceMemory?.toString() || '0',
        // WebGL renderer info if available
        ...(await this.getWebGLInfo()),
      ].join('|');

      const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(components));
      return this.arrayBufferToBase64(hash);
    } catch {
      // Fallback to basic fingerprint
      return this.generateDeviceFingerprint();
    }
  }

  private async getWebGLInfo(): Promise<string[]> {
    try {
      const canvas = document.createElement('canvas');
      const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (!gl) return [];

      return [
        gl.getParameter(gl.VENDOR) || '',
        gl.getParameter(gl.RENDERER) || '',
      ];
    } catch {
      return [];
    }
  }

  private getDeviceName(): string {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android Device';
    if (/Macintosh/.test(ua)) return 'Mac';
    if (/Windows/.test(ua)) return 'Windows PC';
    if (/Linux/.test(ua)) return 'Linux Device';
    return 'Web Browser';
  }

  private getDeviceType(): 'mobile' | 'desktop' | 'web' | 'tablet' {
    const ua = navigator.userAgent;
    if (/iPhone|Android.*Mobile/.test(ua)) return 'mobile';
    if (/iPad|Android(?!.*Mobile)/.test(ua)) return 'tablet';
    if (/Macintosh|Windows|Linux/.test(ua)) return 'desktop';
    return 'web';
  }

  private getPlatform(): string {
    return navigator.platform || 'Unknown';
  }

  private getDeviceCapabilities(): string[] {
    const capabilities = ['messaging', 'encryption'];
    
    // Check for additional capabilities
    if ('credentials' in navigator) capabilities.push('passkey');
    if ('getUserMedia' in navigator.mediaDevices) capabilities.push('video_call');
    if ('serviceWorker' in navigator) capabilities.push('offline');
    
    return capabilities;
  }

  private getDeviceSecurityLevel(): 'low' | 'medium' | 'high' | 'maximum' {
    let score = 0;
    
    // Check for security features
    if (location.protocol === 'https:') score += 1;
    if ('credentials' in navigator) score += 1;
    if (crypto.subtle) score += 1;
    if ('serviceWorker' in navigator) score += 1;
    
    if (score >= 4) return 'maximum';
    if (score >= 3) return 'high';
    if (score >= 2) return 'medium';
    return 'low';
  }

  // Utility methods for data conversion
  
  private arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    const binary = String.fromCharCode(...bytes);
    return btoa(binary);
  }

  private base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  private arrayBufferToPEM(buffer: ArrayBuffer, type: string): string {
    const base64 = this.arrayBufferToBase64(buffer);
    const lines = base64.match(/.{1,64}/g) || [];
    return `-----BEGIN ${type}-----\n${lines.join('\n')}\n-----END ${type}-----`;
  }

  private pemToArrayBuffer(pem: string): ArrayBuffer {
    const base64 = pem
      .replace(/-----BEGIN [^-]+-----/, '')
      .replace(/-----END [^-]+-----/, '')
      .replace(/\s/g, '');
    return this.base64ToArrayBuffer(base64);
  }

  /**
   * Queue message for cross-device synchronization
   */
  async queueMessageForSync(conversationId: string, encryptedMessage: EncryptedMessage): Promise<void> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    const messageId = crypto.randomUUID();
    const syncData: MessageSyncData = {
      messageId,
      conversationId,
      encryptedContent: encryptedMessage,
      timestamp: Date.now(),
      deviceId: this.currentDevice.id,
      syncStatus: 'pending',
      retryCount: 0,
    };

    this.pendingSyncMessages.set(messageId, syncData);
    this.syncQueue.push(syncData);
    
    // Store pending sync data
    this.setSecureStorage('pending_sync', Array.from(this.pendingSyncMessages.values()));
    
    // Start sync process if not already running
    if (!this.isSyncing) {
      this.processSyncQueue();
    }
  }

  /**
   * Sync messages across all user devices
   */
  async syncMessagesAcrossDevices(conversationId?: string): Promise<SyncReport> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    const report: SyncReport = {
      totalMessages: 0,
      syncedMessages: 0,
      failedMessages: 0,
      pendingMessages: 0,
      lastSyncAt: new Date(),
      syncErrors: [],
    };

    try {
      this.isSyncing = true;

      // Get all user devices
      const devices = await this.getDevices();
      const targetDevices = devices.filter(d => 
        d.id !== this.currentDevice!.id && 
        d.is_trusted && 
        d.is_active
      );

      // Get messages to sync
      const messagesToSync = conversationId 
        ? Array.from(this.pendingSyncMessages.values()).filter(m => m.conversationId === conversationId)
        : Array.from(this.pendingSyncMessages.values());

      report.totalMessages = messagesToSync.length;

      // Sync each message to each target device
      for (const messageData of messagesToSync) {
        let syncedToAnyDevice = false;
        
        for (const device of targetDevices) {
          try {
            await this.syncMessageToDevice(messageData, device);
            syncedToAnyDevice = true;
          } catch (error) {
            console.error(`Failed to sync message ${messageData.messageId} to device ${device.id}:`, error);
            report.syncErrors.push({
              messageId: messageData.messageId,
              error: error instanceof Error ? error.message : 'Unknown sync error',
              conversationId: messageData.conversationId,
            });
          }
        }

        if (syncedToAnyDevice) {
          messageData.syncStatus = 'synced';
          report.syncedMessages++;
          this.pendingSyncMessages.delete(messageData.messageId);
        } else {
          messageData.syncStatus = 'failed';
          messageData.retryCount++;
          report.failedMessages++;
        }
      }

      report.pendingMessages = this.pendingSyncMessages.size;

      // Update stored sync data
      this.setSecureStorage('pending_sync', Array.from(this.pendingSyncMessages.values()));
      this.setSecureStorage('last_sync_report', report);

      return report;

    } finally {
      this.isSyncing = false;
    }
  }

  /**
   * Recover missing messages from other devices
   */
  async recoverMissingMessages(conversationId: string, fromTimestamp?: number): Promise<{
    recovered: EncryptedMessage[];
    failed: string[];
  }> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    const result = {
      recovered: [] as EncryptedMessage[],
      failed: [] as string[],
    };

    try {
      // Get trusted devices
      const devices = await this.getDevices();
      const trustedDevices = devices.filter(d => 
        d.id !== this.currentDevice!.id && 
        d.is_trusted && 
        d.is_active
      );

      const timestamp = fromTimestamp || (Date.now() - (24 * 60 * 60 * 1000)); // Last 24 hours

      // Request missing messages from each device
      for (const device of trustedDevices) {
        try {
          const messages = await this.requestMessagesFromDevice(device, conversationId, timestamp);
          
          for (const message of messages) {
            try {
              // Verify we can decrypt the message
              await this.decryptMessage(conversationId, message);
              result.recovered.push(message);
            } catch (decryptError) {
              console.warn(`Could not decrypt recovered message from device ${device.id}:`, decryptError);
              result.failed.push(message.data);
            }
          }
        } catch (error) {
          console.error(`Failed to recover messages from device ${device.id}:`, error);
        }
      }

      // Remove duplicates based on message hash
      result.recovered = this.deduplicateMessages(result.recovered);

      return result;
    } catch (error) {
      console.error('Message recovery failed:', error);
      throw E2EEError.networkError(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Get sync status for conversations
   */
  getSyncStatus(conversationId?: string): SyncReport {
    const pendingMessages = conversationId
      ? Array.from(this.pendingSyncMessages.values()).filter(m => m.conversationId === conversationId)
      : Array.from(this.pendingSyncMessages.values());

    const lastReport = this.getFromSecureStorage('last_sync_report');
    
    return {
      totalMessages: pendingMessages.length,
      syncedMessages: 0,
      failedMessages: pendingMessages.filter(m => m.syncStatus === 'failed').length,
      pendingMessages: pendingMessages.filter(m => m.syncStatus === 'pending').length,
      lastSyncAt: lastReport?.lastSyncAt ? new Date(lastReport.lastSyncAt) : new Date(0),
      syncErrors: lastReport?.syncErrors || [],
    };
  }

  /**
   * Clear sync queue and retry failed syncs
   */
  async retrySyncQueue(): Promise<void> {
    // Reset failed messages to pending
    for (const [messageId, messageData] of this.pendingSyncMessages) {
      if (messageData.syncStatus === 'failed' && messageData.retryCount < 3) {
        messageData.syncStatus = 'pending';
        this.syncQueue.push(messageData);
      } else if (messageData.retryCount >= 3) {
        // Remove messages that have failed too many times
        this.pendingSyncMessages.delete(messageId);
      }
    }

    // Process the queue
    if (!this.isSyncing) {
      await this.processSyncQueue();
    }
  }

  private async processSyncQueue(): Promise<void> {
    if (this.isSyncing || this.syncQueue.length === 0) {
      return;
    }

    this.isSyncing = true;

    try {
      while (this.syncQueue.length > 0) {
        const messageData = this.syncQueue.shift()!;
        
        try {
          const devices = await this.getDevices();
          const targetDevices = devices.filter(d => 
            d.id !== this.currentDevice!.id && 
            d.is_trusted && 
            d.is_active
          );

          let syncedToAny = false;
          for (const device of targetDevices) {
            try {
              await this.syncMessageToDevice(messageData, device);
              syncedToAny = true;
            } catch (error) {
              console.error(`Sync to device ${device.id} failed:`, error);
            }
          }

          if (syncedToAny) {
            messageData.syncStatus = 'synced';
            this.pendingSyncMessages.delete(messageData.messageId);
          } else {
            messageData.syncStatus = 'failed';
            messageData.retryCount++;
            
            // Re-queue for retry if under limit
            if (messageData.retryCount < 3) {
              setTimeout(() => {
                messageData.syncStatus = 'pending';
                this.syncQueue.push(messageData);
              }, 5000 * messageData.retryCount); // Exponential backoff
            }
          }
        } catch (error) {
          console.error('Sync queue processing error:', error);
          messageData.syncStatus = 'failed';
          messageData.retryCount++;
        }

        // Small delay between sync operations
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    } finally {
      this.isSyncing = false;
      
      // Update stored data
      this.setSecureStorage('pending_sync', Array.from(this.pendingSyncMessages.values()));
    }
  }

  private async syncMessageToDevice(messageData: MessageSyncData, targetDevice: any): Promise<void> {
    const response = await fetch(`/api/v1/chat/devices/${targetDevice.id}/sync-message`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        message_id: messageData.messageId,
        conversation_id: messageData.conversationId,
        encrypted_content: messageData.encryptedContent,
        source_device_id: this.currentDevice!.id,
        timestamp: messageData.timestamp,
      }),
    });

    await E2EEErrorHandler.handleApiResponse(response);
  }

  private async requestMessagesFromDevice(device: any, conversationId: string, fromTimestamp: number): Promise<EncryptedMessage[]> {
    const response = await fetch(`/api/v1/chat/devices/${device.id}/request-messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        conversation_id: conversationId,
        from_timestamp: fromTimestamp,
        requesting_device_id: this.currentDevice!.id,
      }),
    });

    const result = await E2EEErrorHandler.handleApiResponse(response);
    return result.messages || [];
  }

  private deduplicateMessages(messages: EncryptedMessage[]): EncryptedMessage[] {
    const seen = new Set<string>();
    return messages.filter(message => {
      const hash = message.hash || message.data;
      if (seen.has(hash)) {
        return false;
      }
      seen.add(hash);
      return true;
    });
  }

  /**
   * Load pending sync data from storage on initialization
   */
  private loadPendingSyncData(): void {
    try {
      const pendingSync = this.getFromSecureStorage('pending_sync');
      if (Array.isArray(pendingSync)) {
        this.pendingSyncMessages.clear();
        for (const messageData of pendingSync) {
          if (messageData.syncStatus === 'pending' || messageData.syncStatus === 'failed') {
            this.pendingSyncMessages.set(messageData.messageId, messageData);
            if (messageData.syncStatus === 'pending') {
              this.syncQueue.push(messageData);
            }
          }
        }
      }
    } catch (error) {
      console.warn('Could not load pending sync data:', error);
    }
  }

  /**
   * Create an encrypted backup of device keys
   */
  async createKeyBackup(passphrase: string): Promise<KeyBackupData> {
    if (!this.currentDevice) {
      throw E2EEError.deviceNotInitialized();
    }

    if (!this.currentDevice.isTrusted) {
      throw E2EEError.deviceNotTrusted(this.currentDevice.id);
    }

    try {
      // Generate salt and IV
      const salt = crypto.getRandomValues(new Uint8Array(16));
      const iv = crypto.getRandomValues(new Uint8Array(16));
      const iterations = 100000;

      // Derive encryption key from passphrase
      const passphraseKey = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
      );

      const backupKey = await crypto.subtle.deriveKey(
        {
          name: 'PBKDF2',
          salt,
          iterations,
          hash: 'SHA-256',
        },
        passphraseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt']
      );

      // Encrypt private key
      const privateKeyBuffer = new TextEncoder().encode(this.currentDevice.privateKey);
      const encryptedPrivateKey = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        backupKey,
        privateKeyBuffer
      );

      // Collect conversation keys
      const conversationKeys: Array<{
        conversationId: string;
        encryptedKey: string;
        keyVersion: number;
      }> = [];

      for (const [conversationId, keyData] of this.conversationKeys) {
        conversationKeys.push({
          conversationId,
          encryptedKey: keyData.encryptedKey,
          keyVersion: keyData.keyVersion,
        });
      }

      const backupData: KeyBackupData = {
        version: 1,
        timestamp: Date.now(),
        deviceId: this.currentDevice.id!,
        encryptedPrivateKey: this.arrayBufferToBase64(encryptedPrivateKey),
        salt: this.arrayBufferToBase64(salt),
        iv: this.arrayBufferToBase64(iv),
        iterations,
        conversationKeys,
        deviceFingerprint: this.currentDevice.fingerprint,
        backupId: crypto.randomUUID(),
      };

      // Store backup locally as well
      this.setSecureStorage(`backup_${backupData.backupId}`, backupData);

      return backupData;
    } catch (error) {
      console.error('Key backup creation failed:', error);
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Restore device keys from encrypted backup
   */
  async restoreKeyBackup(backupData: KeyBackupData, passphrase: string): Promise<void> {
    try {
      // Derive decryption key from passphrase
      const passphraseKey = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(passphrase),
        'PBKDF2',
        false,
        ['deriveKey']
      );

      const backupKey = await crypto.subtle.deriveKey(
        {
          name: 'PBKDF2',
          salt: this.base64ToArrayBuffer(backupData.salt),
          iterations: backupData.iterations,
          hash: 'SHA-256',
        },
        passphraseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['decrypt']
      );

      // Decrypt private key
      const encryptedPrivateKeyBuffer = this.base64ToArrayBuffer(backupData.encryptedPrivateKey);
      const iv = this.base64ToArrayBuffer(backupData.iv);
      
      const decryptedPrivateKeyBuffer = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        backupKey,
        encryptedPrivateKeyBuffer
      );

      const privateKey = new TextDecoder().decode(decryptedPrivateKeyBuffer);

      // Restore device info (this would need additional device registration)
      if (!this.currentDevice) {
        throw E2EEError.deviceNotInitialized();
      }

      // Update current device with restored private key
      this.currentDevice.privateKey = privateKey;
      this.currentDevice.fingerprint = backupData.deviceFingerprint;

      // Restore conversation keys
      this.conversationKeys.clear();
      for (const keyData of backupData.conversationKeys) {
        const conversationKey: ConversationKey = {
          keyId: `${keyData.conversationId}_v${keyData.keyVersion}`,
          encryptedKey: keyData.encryptedKey,
          publicKey: this.currentDevice.publicKey,
          keyVersion: keyData.keyVersion,
          deviceFingerprint: backupData.deviceFingerprint,
          isActive: true,
        };
        this.conversationKeys.set(keyData.conversationId, conversationKey);
      }

      // Update stored device
      this.setSecureStorage('current_device', this.currentDevice);

      console.log('Key backup restored successfully');
    } catch (error) {
      console.error('Key backup restoration failed:', error);
      throw E2EEError.keyGenerationFailed(error instanceof Error ? error : undefined);
    }
  }

  /**
   * Initiate device verification with advanced options
   */
  async initiateDeviceVerification(options: DeviceVerificationOptions = { method: 'security_key' }): Promise<DeviceVerificationChallenge> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    return await E2EEErrorHandler.withRetry(async () => {
      const response = await fetch(`/api/v1/chat/devices/${this.currentDevice!.id}/verification/initiate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCSRFToken(),
        },
        body: JSON.stringify({
          verification_type: options.method,
          timeout: options.timeout || 300, // 5 minutes default
          requires_biometric: options.requiresBiometric || false,
        }),
      });

      const result = await E2EEErrorHandler.handleApiResponse(response);
      
      return {
        challengeId: result.challenge.challenge_id,
        deviceId: this.currentDevice!.id!,
        timestamp: result.challenge.timestamp,
        nonce: result.challenge.nonce,
        verificationType: result.challenge.verification_type,
        expiresAt: new Date(result.expires_at),
      };
    });
  }

  /**
   * Complete device verification with challenge response
   */
  async completeDeviceVerification(
    challengeId: string, 
    response: any, 
    options?: { 
      trustDevice?: boolean;
      rememberDevice?: boolean;
    }
  ): Promise<boolean> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    try {
      const verificationResponse = await fetch(`/api/v1/chat/devices/${this.currentDevice.id}/verification/complete`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.getCSRFToken(),
        },
        body: JSON.stringify({
          challenge_id: challengeId,
          response,
          trust_device: options?.trustDevice !== false,
          remember_device: options?.rememberDevice !== false,
        }),
      });

      const result = await E2EEErrorHandler.handleApiResponse(verificationResponse);
      
      // Update device trust status
      if (this.currentDevice && result.device) {
        this.currentDevice.isTrusted = result.device.is_trusted;
        this.currentDevice.verifiedAt = new Date(result.device.verified_at);
        this.setSecureStorage('current_device', this.currentDevice);
      }

      return result.success;
    } catch (error) {
      if (error instanceof E2EEError && error.details.code === E2EEErrorCode.API_ERROR) {
        throw E2EEError.deviceVerificationFailed(this.currentDevice.id);
      }
      throw error;
    }
  }

  /**
   * Generate QR code for device verification
   */
  async generateVerificationQRCode(): Promise<{ qrCode: string; verificationUrl: string }> {
    if (!this.currentDevice?.id) {
      throw E2EEError.deviceNotInitialized();
    }

    const response = await fetch(`/api/v1/chat/devices/${this.currentDevice.id}/verification/qr`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
    });

    const result = await E2EEErrorHandler.handleApiResponse(response);
    return {
      qrCode: result.qr_code,
      verificationUrl: result.verification_url,
    };
  }

  private async getDevices(): Promise<any[]> {
    const response = await fetch('/api/v1/chat/devices', {
      headers: {
        'X-Device-Fingerprint': this.currentDevice?.fingerprint || '',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
    });

    if (!response.ok) {
      throw new Error('Failed to fetch devices');
    }

    const result = await response.json();
    return result.devices || [];
  }

  private getCSRFToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  private getFromSecureStorage(key: string): any {
    try {
      const data = localStorage.getItem(this.STORAGE_PREFIX + key);
      return data ? JSON.parse(data) : null;
    } catch (error) {
      console.error(`Failed to read from secure storage: ${key}`, error);
      throw E2EEError.storageError(`read ${key}`, error instanceof Error ? error : undefined);
    }
  }

  private setSecureStorage(key: string, value: any): void {
    try {
      localStorage.setItem(this.STORAGE_PREFIX + key, JSON.stringify(value));
    } catch (error) {
      console.error(`Failed to write to secure storage: ${key}`, error);
      throw E2EEError.storageError(`write ${key}`, error instanceof Error ? error : undefined);
    }
  }
}

// Singleton instance
export const multiDeviceE2EEService = new MultiDeviceE2EEService();
export default MultiDeviceE2EEService;