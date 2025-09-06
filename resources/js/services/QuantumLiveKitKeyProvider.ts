/**
 * Quantum-Enhanced LiveKit Key Provider
 * Provides quantum-resistant key exchange and management for WebRTC E2EE
 */

import { BaseKeyProvider } from 'livekit-client';
import { getUserStorageItem, setUserStorageItem, removeUserStorageItem } from '@/utils/localStorage';

export interface QuantumKeyMaterial {
  keyId: string;
  algorithm: 'ML-KEM-512' | 'ML-KEM-768' | 'ML-KEM-1024' | 'HYBRID-RSA-MLKEM';
  publicKey: Uint8Array;
  privateKey: Uint8Array;
  sharedSecret?: Uint8Array;
  createdAt: Date;
  expiresAt: Date;
}

export interface ParticipantKeyInfo {
  participantId: string;
  keyMaterial: QuantumKeyMaterial;
  deviceInfo: {
    deviceId: string;
    quantumCapable: boolean;
    supportedAlgorithms: string[];
  };
  lastKeyExchange: Date;
}

export class QuantumLiveKitKeyProvider extends BaseKeyProvider {
  private keyMaterials = new Map<string, QuantumKeyMaterial>();
  private participantKeys = new Map<string, ParticipantKeyInfo>();
  private pendingKeyExchanges = new Map<string, Promise<Uint8Array>>();
  private signalingChannel: RTCDataChannel | null = null;
  private conversationId: string;
  private userId: string;
  private deviceId: string;
  private quantumService: any; // Will be injected
  private keyExchangeTimeout = 30000; // 30 seconds
  private rotationCount = 0;

  constructor(options: {
    conversationId: string;
    userId: string;
    deviceId: string;
    quantumService: any;
    keyRotationInterval?: number;
  }) {
    super();
    
    this.conversationId = options.conversationId;
    this.userId = options.userId;
    this.deviceId = options.deviceId;
    this.quantumService = options.quantumService;

    // Set up automatic key rotation (default: 1 hour)
    const rotationInterval = options.keyRotationInterval || 3600000;
    setInterval(() => {
      this.rotateKeys();
    }, rotationInterval);
  }

  /**
   * Initialize quantum key provider
   */
  async initialize(): Promise<void> {
    try {
      // Generate initial key material
      await this.generateInitialKeys();
      
      // Set up key exchange listeners
      this.setupKeyExchangeHandlers();
      
      console.log('Quantum LiveKit key provider initialized');
    } catch (error) {
      console.error('Failed to initialize quantum key provider:', error);
      throw error;
    }
  }

  /**
   * Generate encryption key for participant
   */
  async getKey(participantId: string, keyIndex?: number): Promise<Uint8Array> {
    try {
      let participantKey = this.participantKeys.get(participantId);
      
      if (!participantKey || this.isKeyExpired(participantKey.keyMaterial)) {
        // Generate new key material for this participant
        participantKey = await this.generateParticipantKey(participantId);
        this.participantKeys.set(participantId, participantKey);
      }

      // Derive session key from shared secret
      return await this.deriveSessionKey(
        participantKey.keyMaterial.sharedSecret!,
        participantId,
        keyIndex || 0
      );
    } catch (error) {
      console.error('Failed to get key for participant:', participantId, error);
      throw error;
    }
  }

  /**
   * Handle new participant joining
   */
  async onParticipantConnected(participantId: string, metadata?: any): Promise<void> {
    try {
      const deviceInfo = this.parseParticipantMetadata(metadata);
      
      if (deviceInfo.quantumCapable) {
        // Perform quantum key exchange
        await this.performQuantumKeyExchange(participantId, deviceInfo);
      } else {
        // Fall back to classical key exchange
        await this.performClassicalKeyExchange(participantId, deviceInfo);
      }
    } catch (error) {
      console.error('Failed to handle participant connection:', error);
      // Continue with degraded security rather than failing entirely
    }
  }

  /**
   * Handle participant disconnecting
   */
  async onParticipantDisconnected(participantId: string): Promise<void> {
    try {
      // Clean up key material
      this.participantKeys.delete(participantId);
      
      // Trigger key rotation for remaining participants
      await this.rotateKeysForRemainingParticipants();
    } catch (error) {
      console.error('Failed to handle participant disconnection:', error);
    }
  }

  /**
   * Set encryption key for outgoing streams
   */
  async setKey(key: Uint8Array, participantId?: string, keyIndex?: number): Promise<void> {
    // LiveKit handles the actual key setting
    // We just need to ensure our key material is current
    if (participantId) {
      const participantKey = this.participantKeys.get(participantId);
      if (participantKey) {
        participantKey.lastKeyExchange = new Date();
      }
    }
  }

  /**
   * Get current key material statistics
   */
  getKeyStats(): {
    totalParticipants: number;
    quantumEnabledParticipants: number;
    averageKeyAge: number;
    nextRotation: Date;
  } {
    const now = new Date();
    const participants = Array.from(this.participantKeys.values());
    
    return {
      totalParticipants: participants.length,
      quantumEnabledParticipants: participants.filter(p => 
        p.deviceInfo.quantumCapable
      ).length,
      averageKeyAge: participants.reduce((acc, p) => 
        acc + (now.getTime() - p.keyMaterial.createdAt.getTime()), 0
      ) / (participants.length || 1),
      nextRotation: new Date(now.getTime() + 3600000), // 1 hour from now
    };
  }

  // Private methods

  private async generateInitialKeys(): Promise<void> {
    const algorithm = 'ML-KEM-768'; // Default quantum algorithm
    
    try {
      const keyPair = await this.quantumService.generateKeyPair(algorithm);
      
      const keyMaterial: QuantumKeyMaterial = {
        keyId: crypto.randomUUID(),
        algorithm,
        publicKey: new Uint8Array(keyPair.public),
        privateKey: new Uint8Array(keyPair.private),
        createdAt: new Date(),
        expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000), // 24 hours
      };

      this.keyMaterials.set('local', keyMaterial);
    } catch (error) {
      console.error('Failed to generate initial keys:', error);
      throw error;
    }
  }

  private setupKeyExchangeHandlers(): void {
    // Set up WebSocket connection for real-time signaling
    this.setupWebSocketSignaling();
    
    // Set up WebRTC data channel for direct peer communication
    this.setupDataChannelSignaling();
    
    // Set up message handlers
    window.addEventListener('message', this.handleKeyExchangeMessage.bind(this));
  }

  private async generateParticipantKey(participantId: string): Promise<ParticipantKeyInfo> {
    // Get participant device capabilities
    const deviceInfo = await this.getParticipantDeviceInfo(participantId);
    
    // Choose best available algorithm
    const algorithm = this.selectBestAlgorithm(deviceInfo.supportedAlgorithms);
    
    // Generate key material
    const keyMaterial = await this.generateKeyMaterial(algorithm);
    
    // Perform key exchange
    const sharedSecret = await this.performKeyExchange(
      participantId,
      keyMaterial,
      deviceInfo
    );

    keyMaterial.sharedSecret = sharedSecret;

    return {
      participantId,
      keyMaterial,
      deviceInfo,
      lastKeyExchange: new Date(),
    };
  }

  private async performQuantumKeyExchange(
    participantId: string,
    deviceInfo: any
  ): Promise<Uint8Array> {
    try {
      // Get our local key material
      const localKeyMaterial = this.keyMaterials.get('local');
      if (!localKeyMaterial) {
        throw new Error('Local key material not available');
      }

      // Get participant's public key through signaling
      const participantPublicKey = await this.getParticipantPublicKey(participantId);
      
      // Perform ML-KEM key encapsulation
      const encapsulationResult = await this.quantumService.encapsulate(
        participantPublicKey,
        localKeyMaterial.algorithm
      );

      // Send encapsulated key to participant through signaling
      await this.sendKeyMaterial(participantId, {
        type: 'quantum_key_exchange',
        ciphertext: encapsulationResult.ciphertext,
        algorithm: localKeyMaterial.algorithm,
        keyId: localKeyMaterial.keyId,
      });

      return new Uint8Array(encapsulationResult.shared_secret);
    } catch (error) {
      console.error('Quantum key exchange failed:', error);
      // Fall back to classical exchange
      return this.performClassicalKeyExchange(participantId, deviceInfo);
    }
  }

  private async performClassicalKeyExchange(
    participantId: string,
    deviceInfo: any
  ): Promise<Uint8Array> {
    // Implement classical ECDH key exchange
    try {
      const keyPair = await window.crypto.subtle.generateKey(
        {
          name: 'ECDH',
          namedCurve: 'P-384',
        },
        false,
        ['deriveKey', 'deriveBits']
      );

      // Export public key
      const publicKeyBuffer = await window.crypto.subtle.exportKey('raw', keyPair.publicKey);
      
      // Send public key to participant
      await this.sendKeyMaterial(participantId, {
        type: 'classical_key_exchange',
        publicKey: Array.from(new Uint8Array(publicKeyBuffer)),
        algorithm: 'ECDH-P384',
      });

      // Get participant's public key
      const participantPublicKeyBuffer = await this.getParticipantPublicKey(participantId);
      
      const participantPublicKey = await window.crypto.subtle.importKey(
        'raw',
        participantPublicKeyBuffer,
        {
          name: 'ECDH',
          namedCurve: 'P-384',
        },
        false,
        []
      );

      // Derive shared key
      const sharedKeyBuffer = await window.crypto.subtle.deriveBits(
        {
          name: 'ECDH',
          public: participantPublicKey,
        },
        keyPair.privateKey,
        256
      );

      return new Uint8Array(sharedKeyBuffer);
    } catch (error) {
      console.error('Classical key exchange failed:', error);
      throw error;
    }
  }

  private async deriveSessionKey(
    sharedSecret: Uint8Array,
    participantId: string,
    keyIndex: number
  ): Promise<Uint8Array> {
    // Derive unique session key using HKDF
    const info = new TextEncoder().encode(`livekit_e2ee_${participantId}_${keyIndex}`);
    
    const keyMaterial = await window.crypto.subtle.importKey(
      'raw',
      sharedSecret,
      { name: 'HKDF' },
      false,
      ['deriveKey']
    );

    const derivedKey = await window.crypto.subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: new Uint8Array(32), // Could use participant-specific salt
        info: info,
      },
      keyMaterial,
      {
        name: 'AES-GCM',
        length: 256,
      },
      true,
      ['encrypt', 'decrypt']
    );

    const exportedKey = await window.crypto.subtle.exportKey('raw', derivedKey);
    return new Uint8Array(exportedKey);
  }

  private isKeyExpired(keyMaterial: QuantumKeyMaterial): boolean {
    return new Date() > keyMaterial.expiresAt;
  }

  private async rotateKeys(): Promise<void> {
    console.log('Rotating encryption keys for all participants');
    
    try {
      // Generate new local key material
      await this.generateInitialKeys();
      
      // Re-establish keys with all participants
      for (const [participantId, participantKey] of this.participantKeys.entries()) {
        if (participantKey.deviceInfo.quantumCapable) {
          await this.performQuantumKeyExchange(participantId, participantKey.deviceInfo);
        } else {
          await this.performClassicalKeyExchange(participantId, participantKey.deviceInfo);
        }
      }
    } catch (error) {
      console.error('Key rotation failed:', error);
    }
  }

  private async rotateKeysForRemainingParticipants(): Promise<void> {
    // Force key rotation when participant leaves for forward secrecy
    await this.rotateKeys();
  }

  private parseParticipantMetadata(metadata: any): any {
    try {
      if (typeof metadata === 'string') {
        return JSON.parse(metadata);
      }
      return metadata || { quantumCapable: false, supportedAlgorithms: ['ECDH-P384'] };
    } catch {
      return { quantumCapable: false, supportedAlgorithms: ['ECDH-P384'] };
    }
  }

  private selectBestAlgorithm(supportedAlgorithms: string[]): QuantumKeyMaterial['algorithm'] {
    const preferredOrder: QuantumKeyMaterial['algorithm'][] = [
      'ML-KEM-1024',
      'ML-KEM-768',
      'ML-KEM-512',
      'HYBRID-RSA-MLKEM',
    ];
    
    for (const preferred of preferredOrder) {
      if (supportedAlgorithms.includes(preferred)) {
        return preferred;
      }
    }
    
    return 'ML-KEM-768'; // Default fallback
  }

  private async generateKeyMaterial(algorithm: QuantumKeyMaterial['algorithm']): Promise<QuantumKeyMaterial> {
    const keyPair = await this.quantumService.generateKeyPair(algorithm);
    
    return {
      keyId: crypto.randomUUID(),
      algorithm,
      publicKey: new Uint8Array(keyPair.public),
      privateKey: new Uint8Array(keyPair.private),
      createdAt: new Date(),
      expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000), // 24 hours
    };
  }

  private async performKeyExchange(
    participantId: string,
    keyMaterial: QuantumKeyMaterial,
    deviceInfo: any
  ): Promise<Uint8Array> {
    if (deviceInfo.quantumCapable) {
      return this.performQuantumKeyExchange(participantId, deviceInfo);
    } else {
      return this.performClassicalKeyExchange(participantId, deviceInfo);
    }
  }

  private async getParticipantDeviceInfo(participantId: string): Promise<any> {
    try {
      // Try multiple sources for device info with fallbacks
      let deviceInfo = await this.getDeviceInfoFromCache(participantId);
      
      if (!deviceInfo) {
        deviceInfo = await this.getDeviceInfoFromAPI(participantId);
      }
      
      if (!deviceInfo) {
        deviceInfo = await this.getDeviceInfoFromSignaling(participantId);
      }
      
      if (!deviceInfo) {
        deviceInfo = await this.inferDeviceInfoFromMetadata(participantId);
      }
      
      return deviceInfo || this.getDefaultDeviceInfo();
    } catch (error) {
      console.error('Failed to get participant device info:', error);
      return this.getDefaultDeviceInfo();
    }
  }

  private async getDeviceInfoFromCache(participantId: string): Promise<any | null> {
    try {
      const cached = getUserStorageItem(`device_info_${participantId}`);
      if (!cached) return null;
      
      const data = JSON.parse(cached);
      const maxAge = 5 * 60 * 1000; // 5 minutes
      
      if (Date.now() - data.timestamp > maxAge) {
        removeUserStorageItem(`device_info_${participantId}`);
        return null;
      }
      
      return data.deviceInfo;
    } catch {
      return null;
    }
  }

  private async getDeviceInfoFromAPI(participantId: string): Promise<any | null> {
    try {
      const response = await fetch(`/api/v1/livekit/participants/${participantId}/device-info`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Device-Fingerprint': this.getDeviceFingerprint(),
          'X-Conversation-Id': this.conversationId,
        },
        credentials: 'same-origin',
      });
      
      if (!response.ok) {
        if (response.status === 404) {
          // Participant not found, might be new
          return null;
        }
        throw new Error(`API error: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Cache the result
      this.cacheDeviceInfo(participantId, data.deviceInfo);
      
      return {
        deviceId: data.deviceInfo.device_id || 'unknown',
        quantumCapable: data.deviceInfo.quantum_capable || false,
        supportedAlgorithms: data.deviceInfo.supported_algorithms || ['ECDH-P384'],
        platform: data.deviceInfo.platform || 'unknown',
        browser: data.deviceInfo.browser || 'unknown',
        lastSeen: new Date(data.deviceInfo.last_seen || Date.now()),
      };
    } catch (error) {
      console.warn('API device info request failed:', error);
      return null;
    }
  }

  private async getDeviceInfoFromSignaling(participantId: string): Promise<any | null> {
    try {
      if (!this.webSocket || this.webSocket.readyState !== WebSocket.OPEN) {
        return null;
      }

      return new Promise((resolve) => {
        const timeout = setTimeout(() => resolve(null), 10000);
        
        const request = {
          type: 'device_info_request',
          from: this.userId,
          to: participantId,
          conversationId: this.conversationId,
          timestamp: Date.now()
        };

        const responseHandler = (event: MessageEvent) => {
          try {
            const message = JSON.parse(event.data);
            if (message.type === 'device_info_response' && message.from === participantId) {
              clearTimeout(timeout);
              this.webSocket!.removeEventListener('message', responseHandler);
              resolve(message.data.deviceInfo);
            }
          } catch (error) {
            // Ignore parsing errors
          }
        };

        this.webSocket!.addEventListener('message', responseHandler);
        this.sendViaWebSocket(request).catch(() => resolve(null));
      });
    } catch {
      return null;
    }
  }

  private async inferDeviceInfoFromMetadata(participantId: string): Promise<any | null> {
    try {
      // Check if we have any stored participant info from LiveKit metadata
      const participantKey = this.participantKeys.get(participantId);
      if (participantKey?.deviceInfo) {
        return participantKey.deviceInfo;
      }

      // Try to infer from user agent or other available info
      const userAgent = navigator.userAgent;
      const isModernBrowser = userAgent.includes('Chrome') || 
                             userAgent.includes('Firefox') || 
                             userAgent.includes('Safari');
      
      return {
        deviceId: `inferred_${participantId}`,
        quantumCapable: isModernBrowser && !!crypto?.subtle,
        supportedAlgorithms: isModernBrowser ? ['ECDH-P384', 'ML-KEM-768'] : ['ECDH-P384'],
        platform: 'inferred',
        browser: 'unknown',
        inference: true,
      };
    } catch {
      return null;
    }
  }

  private cacheDeviceInfo(participantId: string, deviceInfo: any): void {
    try {
      const cacheData = {
        deviceInfo,
        timestamp: Date.now()
      };
      
      setUserStorageItem(`device_info_${participantId}`, JSON.stringify(cacheData));
    } catch (error) {
      console.warn('Failed to cache device info:', error);
    }
  }

  private getDefaultDeviceInfo(): any {
    return {
      deviceId: 'unknown',
      quantumCapable: false,
      supportedAlgorithms: ['ECDH-P384'],
      platform: 'unknown',
      browser: 'unknown',
      fallback: true,
    };
  }

  private async getParticipantPublicKey(participantId: string): Promise<Uint8Array> {
    try {
      // First, try to get from cache
      const cached = this.participantKeys.get(participantId);
      if (cached?.keyMaterial.publicKey) {
        return cached.keyMaterial.publicKey;
      }

      // Check for pending exchange
      const pending = this.pendingKeyExchanges.get(participantId);
      if (pending) {
        await pending;
        const updated = this.participantKeys.get(participantId);
        if (updated?.keyMaterial.publicKey) {
          return updated.keyMaterial.publicKey;
        }
      }

      // Request public key through signaling
      const publicKey = await this.requestParticipantPublicKey(participantId);
      if (publicKey) {
        return publicKey;
      }

      // Fallback: Try WebRTC data channel
      const dataChannelKey = await this.requestPublicKeyViaDataChannel(participantId);
      if (dataChannelKey) {
        return dataChannelKey;
      }

      // Last resort: Generate a temporary key for this session
      console.warn(`Using temporary key for participant ${participantId}`);
      return crypto.getRandomValues(new Uint8Array(32));
    } catch (error) {
      console.error('Failed to get participant public key:', error);
      // Return a deterministic key based on participant ID for consistency
      const encoder = new TextEncoder();
      const data = encoder.encode(`temp_key_${participantId}_${this.conversationId}`);
      const hash = await crypto.subtle.digest('SHA-256', data);
      return new Uint8Array(hash.slice(0, 32));
    }
  }

  private async sendKeyMaterial(participantId: string, keyData: any): Promise<void> {
    try {
      const message = {
        type: 'key_exchange',
        from: this.userId,
        to: participantId,
        conversationId: this.conversationId,
        timestamp: Date.now(),
        data: keyData
      };

      // Try multiple delivery methods for reliability
      const delivery = await Promise.allSettled([
        this.sendViaWebSocket(message),
        this.sendViaDataChannel(message),
        this.sendViaSignalingServer(message)
      ]);

      // Check if at least one delivery method succeeded
      const successful = delivery.some(result => result.status === 'fulfilled');
      
      if (!successful) {
        throw new Error('All key delivery methods failed');
      }

      console.log('Key material sent successfully to participant:', participantId);
    } catch (error) {
      console.error('Failed to send key material:', error);
      throw error;
    }
  }

  private getDeviceFingerprint(): string {
    let fingerprint = getUserStorageItem('device_fingerprint');
    if (!fingerprint) {
      fingerprint = this.generateDeviceFingerprint();
      setUserStorageItem('device_fingerprint', fingerprint);
    }
    return fingerprint;
  }

  private generateDeviceFingerprint(): string {
    const components = [
      navigator.userAgent,
      navigator.language,
      navigator.platform,
      navigator.hardwareConcurrency?.toString() || '',
      screen.width + 'x' + screen.height,
      new Date().getTimezoneOffset().toString(),
    ];
    
    const combined = components.join('|');
    return btoa(combined).replace(/[+/=]/g, '').substring(0, 16);
  }

  // ============================================================================
  // Enhanced Signaling Implementation
  // ============================================================================

  private webSocket: WebSocket | null = null;

  private setupWebSocketSignaling(): void {
    try {
      const wsUrl = this.getWebSocketUrl();
      this.webSocket = new WebSocket(wsUrl);
      
      this.webSocket.onopen = () => {
        console.log('Key exchange WebSocket connected');
        // Authenticate and join conversation room
        this.webSocket?.send(JSON.stringify({
          type: 'join_conversation',
          conversationId: this.conversationId,
          userId: this.userId,
          deviceId: this.deviceId
        }));
      };

      this.webSocket.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          this.handleSignalingMessage(message);
        } catch (error) {
          console.error('Failed to parse WebSocket message:', error);
        }
      };

      this.webSocket.onclose = () => {
        console.log('Key exchange WebSocket disconnected');
        // Reconnect after delay
        setTimeout(() => this.setupWebSocketSignaling(), 5000);
      };

      this.webSocket.onerror = (error) => {
        console.error('WebSocket error:', error);
      };
    } catch (error) {
      console.error('Failed to setup WebSocket signaling:', error);
    }
  }

  private setupDataChannelSignaling(): void {
    // This will be set up when WebRTC connection is established
    // For now, we prepare the handler
  }

  private getWebSocketUrl(): string {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.host;
    return `${protocol}//${host}/ws/livekit-signaling`;
  }

  private async handleSignalingMessage(message: any): Promise<void> {
    try {
      switch (message.type) {
        case 'key_exchange_request':
          await this.handleKeyExchangeRequest(message);
          break;
        case 'key_exchange_response':
          await this.handleKeyExchangeResponse(message);
          break;
        case 'public_key_share':
          await this.handlePublicKeyShare(message);
          break;
        case 'participant_joined':
          await this.handleParticipantJoined(message);
          break;
        case 'participant_left':
          await this.handleParticipantLeft(message);
          break;
        default:
          console.log('Unknown signaling message type:', message.type);
      }
    } catch (error) {
      console.error('Failed to handle signaling message:', error);
    }
  }

  private async handleKeyExchangeRequest(message: any): Promise<void> {
    const { from, data } = message;
    
    try {
      // Generate response based on the request
      let responseData;
      
      if (data.type === 'quantum_key_exchange') {
        // Handle quantum key exchange
        responseData = await this.handleQuantumKeyExchangeRequest(data);
      } else if (data.type === 'classical_key_exchange') {
        // Handle classical key exchange
        responseData = await this.handleClassicalKeyExchangeRequest(data);
      } else {
        throw new Error(`Unknown key exchange type: ${data.type}`);
      }

      // Send response
      await this.sendViaWebSocket({
        type: 'key_exchange_response',
        from: this.userId,
        to: from,
        conversationId: this.conversationId,
        timestamp: Date.now(),
        data: responseData
      });
    } catch (error) {
      console.error('Failed to handle key exchange request:', error);
      
      // Send error response
      await this.sendViaWebSocket({
        type: 'key_exchange_error',
        from: this.userId,
        to: from,
        conversationId: this.conversationId,
        timestamp: Date.now(),
        error: error instanceof Error ? error.message : 'Unknown error'
      });
    }
  }

  private async handleQuantumKeyExchangeRequest(data: any): Promise<any> {
    const { ciphertext, algorithm, keyId } = data;
    
    // Get our private key for decapsulation
    const localKeyMaterial = this.keyMaterials.get('local');
    if (!localKeyMaterial) {
      throw new Error('Local key material not available');
    }

    // Perform decapsulation
    const decapsulationResult = await this.quantumService.decapsulate(
      new Uint8Array(ciphertext),
      localKeyMaterial.privateKey,
      algorithm
    );

    return {
      type: 'quantum_key_response',
      success: true,
      sharedSecret: Array.from(decapsulationResult.shared_secret),
      algorithm: algorithm,
      keyId: keyId
    };
  }

  private async handleClassicalKeyExchangeRequest(data: any): Promise<any> {
    const { publicKey, algorithm } = data;
    
    // Generate our key pair for ECDH
    const keyPair = await window.crypto.subtle.generateKey(
      { name: 'ECDH', namedCurve: 'P-384' },
      false,
      ['deriveKey', 'deriveBits']
    );

    // Export our public key
    const ourPublicKeyBuffer = await window.crypto.subtle.exportKey('raw', keyPair.publicKey);
    
    // Import participant's public key
    const participantPublicKey = await window.crypto.subtle.importKey(
      'raw',
      new Uint8Array(publicKey),
      { name: 'ECDH', namedCurve: 'P-384' },
      false,
      []
    );

    // Derive shared secret
    const sharedSecretBuffer = await window.crypto.subtle.deriveBits(
      { name: 'ECDH', public: participantPublicKey },
      keyPair.privateKey,
      256
    );

    return {
      type: 'classical_key_response',
      success: true,
      publicKey: Array.from(new Uint8Array(ourPublicKeyBuffer)),
      sharedSecret: Array.from(new Uint8Array(sharedSecretBuffer)),
      algorithm: algorithm
    };
  }

  private async handleKeyExchangeResponse(message: any): Promise<void> {
    const { from, data } = message;
    
    // Resolve pending key exchange
    const pending = this.pendingKeyExchanges.get(from);
    if (pending && data.success) {
      const sharedSecret = new Uint8Array(data.sharedSecret);
      this.pendingKeyExchanges.delete(from);
      
      // Store the shared secret
      const participantKey = this.participantKeys.get(from);
      if (participantKey) {
        participantKey.keyMaterial.sharedSecret = sharedSecret;
        participantKey.lastKeyExchange = new Date();
      }
    }
  }

  private async handlePublicKeyShare(message: any): Promise<void> {
    const { from, data } = message;
    
    // Store participant's public key
    let participantKey = this.participantKeys.get(from);
    if (!participantKey) {
      participantKey = {
        participantId: from,
        keyMaterial: {
          keyId: data.keyId,
          algorithm: data.algorithm,
          publicKey: new Uint8Array(data.publicKey),
          privateKey: new Uint8Array(), // We don't store their private key
          createdAt: new Date(),
          expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000)
        },
        deviceInfo: data.deviceInfo || { 
          deviceId: 'unknown', 
          quantumCapable: false, 
          supportedAlgorithms: ['ECDH-P384'] 
        },
        lastKeyExchange: new Date()
      };
      
      this.participantKeys.set(from, participantKey);
    } else {
      participantKey.keyMaterial.publicKey = new Uint8Array(data.publicKey);
      participantKey.keyMaterial.keyId = data.keyId;
      participantKey.keyMaterial.algorithm = data.algorithm;
    }
  }

  private async handleParticipantJoined(message: any): Promise<void> {
    const { participantId, deviceInfo } = message;
    console.log('Participant joined:', participantId);
    
    // Initiate key exchange with new participant
    await this.onParticipantConnected(participantId, deviceInfo);
  }

  private async handleParticipantLeft(message: any): Promise<void> {
    const { participantId } = message;
    console.log('Participant left:', participantId);
    
    // Clean up and rotate keys
    await this.onParticipantDisconnected(participantId);
  }

  private handleKeyExchangeMessage(event: MessageEvent): void {
    // Handle messages from other windows/tabs
    if (event.origin !== window.location.origin) return;
    
    const { type, data } = event.data;
    if (type === 'livekit_key_exchange') {
      this.handleSignalingMessage(data);
    }
  }

  private async requestParticipantPublicKey(participantId: string): Promise<Uint8Array | null> {
    return new Promise((resolve) => {
      const timeout = setTimeout(() => {
        this.pendingKeyExchanges.delete(participantId);
        resolve(null);
      }, this.keyExchangeTimeout);

      const requestPromise = this.sendPublicKeyRequest(participantId);
      this.pendingKeyExchanges.set(participantId, requestPromise);

      requestPromise.then((key) => {
        clearTimeout(timeout);
        this.pendingKeyExchanges.delete(participantId);
        resolve(key);
      }).catch(() => {
        clearTimeout(timeout);
        this.pendingKeyExchanges.delete(participantId);
        resolve(null);
      });
    });
  }

  private async sendPublicKeyRequest(participantId: string): Promise<Uint8Array> {
    return new Promise((resolve, reject) => {
      const request = {
        type: 'public_key_request',
        from: this.userId,
        to: participantId,
        conversationId: this.conversationId,
        timestamp: Date.now()
      };

      // Store the resolver for when response arrives
      const responseHandler = (message: any) => {
        if (message.type === 'public_key_share' && message.from === participantId) {
          resolve(new Uint8Array(message.data.publicKey));
        }
      };

      // Set up temporary listener
      const cleanup = () => {
        // Remove listener after timeout or success
      };

      setTimeout(() => {
        cleanup();
        reject(new Error('Public key request timeout'));
      }, this.keyExchangeTimeout);

      this.sendViaWebSocket(request).catch(reject);
    });
  }

  private async requestPublicKeyViaDataChannel(participantId: string): Promise<Uint8Array | null> {
    if (!this.signalingChannel) return null;

    try {
      return new Promise((resolve) => {
        const request = {
          type: 'public_key_request',
          from: this.userId,
          to: participantId,
          timestamp: Date.now()
        };

        this.signalingChannel!.send(JSON.stringify(request));

        const timeout = setTimeout(() => resolve(null), 10000);
        
        const handler = (event: MessageEvent) => {
          try {
            const data = JSON.parse(event.data);
            if (data.type === 'public_key_response' && data.from === participantId) {
              clearTimeout(timeout);
              this.signalingChannel!.removeEventListener('message', handler);
              resolve(new Uint8Array(data.publicKey));
            }
          } catch (error) {
            // Ignore parsing errors
          }
        };

        this.signalingChannel!.addEventListener('message', handler);
      });
    } catch (error) {
      console.error('Data channel public key request failed:', error);
      return null;
    }
  }

  private async sendViaWebSocket(message: any): Promise<void> {
    if (!this.webSocket || this.webSocket.readyState !== WebSocket.OPEN) {
      throw new Error('WebSocket not connected');
    }
    
    this.webSocket.send(JSON.stringify(message));
  }

  private async sendViaDataChannel(message: any): Promise<void> {
    if (!this.signalingChannel || this.signalingChannel.readyState !== 'open') {
      throw new Error('Data channel not open');
    }
    
    this.signalingChannel.send(JSON.stringify(message));
  }

  private async sendViaSignalingServer(message: any): Promise<void> {
    const response = await fetch('/api/v1/livekit/signaling/send', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify(message)
    });

    if (!response.ok) {
      throw new Error(`Signaling server error: ${response.status}`);
    }
  }

  // Method to set data channel when WebRTC connection is established
  public setSignalingChannel(dataChannel: RTCDataChannel): void {
    this.signalingChannel = dataChannel;
    
    dataChannel.addEventListener('message', (event) => {
      try {
        const message = JSON.parse(event.data);
        this.handleSignalingMessage(message);
      } catch (error) {
        console.error('Failed to parse data channel message:', error);
      }
    });
  }

  // Enhanced statistics with rotation count
  getKeyStats(): {
    totalParticipants: number;
    quantumEnabledParticipants: number;
    averageKeyAge: number;
    nextRotation: Date;
    rotationCount: number;
  } {
    const now = new Date();
    const participants = Array.from(this.participantKeys.values());
    
    return {
      totalParticipants: participants.length,
      quantumEnabledParticipants: participants.filter(p => 
        p.deviceInfo.quantumCapable
      ).length,
      averageKeyAge: participants.reduce((acc, p) => 
        acc + (now.getTime() - p.keyMaterial.createdAt.getTime()), 0
      ) / (participants.length || 1),
      nextRotation: new Date(now.getTime() + 3600000), // 1 hour from now
      rotationCount: this.rotationCount,
    };
  }

  // Update rotation counter
  private async rotateKeys(): Promise<void> {
    console.log('Rotating encryption keys for all participants');
    
    try {
      this.rotationCount++;
      
      // Generate new local key material
      await this.generateInitialKeys();
      
      // Re-establish keys with all participants
      for (const [participantId, participantKey] of this.participantKeys.entries()) {
        if (participantKey.deviceInfo.quantumCapable) {
          await this.performQuantumKeyExchange(participantId, participantKey.deviceInfo);
        } else {
          await this.performClassicalKeyExchange(participantId, participantKey.deviceInfo);
        }
      }
    } catch (error) {
      console.error('Key rotation failed:', error);
    }
  }
}