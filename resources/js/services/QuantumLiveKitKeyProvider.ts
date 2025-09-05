/**
 * Quantum-Enhanced LiveKit Key Provider
 * Provides quantum-resistant key exchange and management for WebRTC E2EE
 */

import { BaseKeyProvider } from 'livekit-client';

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
  private conversationId: string;
  private userId: string;
  private deviceId: string;
  private quantumService: any; // Will be injected

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
    // Set up message handlers for key exchange
    // This would integrate with your WebRTC data channel or signaling
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
      // This would typically come from your signaling server
      const response = await fetch(`/api/v1/livekit/participants/${participantId}/device-info`, {
        headers: {
          'X-Device-Fingerprint': this.getDeviceFingerprint(),
        },
      });
      
      if (!response.ok) throw new Error('Failed to get device info');
      
      const data = await response.json();
      return data.deviceInfo;
    } catch (error) {
      console.error('Failed to get participant device info:', error);
      // Return safe defaults
      return {
        deviceId: 'unknown',
        quantumCapable: false,
        supportedAlgorithms: ['ECDH-P384'],
      };
    }
  }

  private async getParticipantPublicKey(participantId: string): Promise<Uint8Array> {
    // This would be exchanged through your signaling mechanism
    // For now, return a placeholder
    return new Uint8Array(32);
  }

  private async sendKeyMaterial(participantId: string, keyData: any): Promise<void> {
    // This would send through your signaling mechanism
    console.log('Sending key material to participant:', participantId, keyData);
  }

  private getDeviceFingerprint(): string {
    let fingerprint = localStorage.getItem('device_fingerprint');
    if (!fingerprint) {
      fingerprint = this.generateDeviceFingerprint();
      localStorage.setItem('device_fingerprint', fingerprint);
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
}