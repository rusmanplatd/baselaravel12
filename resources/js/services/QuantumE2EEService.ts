/**
 * Quantum-Resistant End-to-End Encryption Service
 * Handles ML-KEM and hybrid cryptography for the chat system
 */

interface QuantumAlgorithm {
  name: string;
  keySize: number;
  quantumResistant: boolean;
  performance: 'fast' | 'medium' | 'slow';
  description: string;
}

interface DeviceCapabilities {
  deviceId: string;
  deviceName: string;
  deviceType: string;
  encryptionVersion: number;
  quantumReady: boolean;
  quantumCapabilities: string[];
  supportedAlgorithms: string[];
  securityLevel: string;
  isTrusted: boolean;
  lastUsedAt: string | null;
}

interface ConversationAlgorithm {
  algorithm: string;
  algorithmInfo: {
    type: string;
    quantumResistant: boolean;
    version: number;
    securityLevel?: number;
  };
  quantumResistant: boolean;
  participants: number;
  compatibleDevices: number;
}

interface QuantumHealthStatus {
  status: 'healthy' | 'degraded' | 'unhealthy';
  timestamp: string;
  quantum_support: {
    ml_kem_available: boolean;
    provider?: string;
    supported_algorithms: string[];
    supported_levels?: number[];
  };
  system_info: {
    php_version: string;
    laravel_version: string;
    extensions: {
      openssl: boolean;
      liboqs: boolean;
      sodium: boolean;
    };
  };
  algorithms: Record<string, {
    available: boolean;
    quantum_resistant: boolean;
    info: any;
  }>;
}

export class QuantumE2EEService {
  // Ordered by strength: strongest quantum-resistant first, then fallback to available
  private supportedAlgorithms: QuantumAlgorithm[] = [
    { 
      name: 'ML-KEM-1024', 
      keySize: 1024, 
      quantumResistant: true, 
      performance: 'fast',
      description: 'NIST-approved post-quantum key encapsulation (highest security)'
    },
    { 
      name: 'ML-KEM-768', 
      keySize: 768, 
      quantumResistant: true, 
      performance: 'fast',
      description: 'NIST-approved post-quantum key encapsulation (recommended)'
    },
    { 
      name: 'ML-KEM-512', 
      keySize: 512, 
      quantumResistant: true, 
      performance: 'fast',
      description: 'NIST-approved post-quantum key encapsulation (basic)'
    },
    { 
      name: 'HYBRID-RSA4096-MLKEM768', 
      keySize: 768, 
      quantumResistant: true, 
      performance: 'medium',
      description: 'Hybrid classical + quantum encryption for transition'
    },
    { 
      name: 'RSA-4096-OAEP', 
      keySize: 4096, 
      quantumResistant: false, 
      performance: 'slow',
      description: 'Classical RSA encryption (legacy fallback)'
    }
  ];

  private static instance: QuantumE2EEService;
  private healthStatus: QuantumHealthStatus | null = null;
  private deviceCapabilities: DeviceCapabilities[] = [];

  public static getInstance(): QuantumE2EEService {
    if (!QuantumE2EEService.instance) {
      QuantumE2EEService.instance = new QuantumE2EEService();
    }
    return QuantumE2EEService.instance;
  }

  /**
   * Initialize quantum service and check system health
   */
  async initialize(): Promise<void> {
    try {
      await this.checkSystemHealth();
      await this.loadDeviceCapabilities();
      console.log('Quantum E2EE Service initialized successfully');
    } catch (error) {
      console.warn('Quantum E2EE Service initialization failed:', error);
    }
  }

  /**
   * Check quantum cryptography system health
   */
  async checkSystemHealth(): Promise<QuantumHealthStatus> {
    try {
      const response = await fetch('/api/v1/quantum/health', {
        method: 'GET',
        headers: { 
          'Accept': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        }
      });
      
      if (!response.ok) {
        throw new Error(`Health check failed: ${response.statusText}`);
      }
      
      this.healthStatus = await response.json();
      return this.healthStatus;
    } catch (error) {
      console.error('Quantum health check failed:', error);
      throw error;
    }
  }

  /**
   * Generate quantum-resistant key pair
   */
  async generateQuantumKeyPair(algorithm: string = 'ML-KEM-768') {
    try {
      const response = await fetch('/api/v1/quantum/generate-keypair', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        },
        body: JSON.stringify({ algorithm })
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Key generation failed');
      }
      
      const keyPair = await response.json();
      
      console.log('Quantum key pair generated:', {
        algorithm: keyPair.algorithm,
        quantum_resistant: keyPair.quantum_resistant,
        key_strength: keyPair.key_strength
      });
      
      return keyPair;
    } catch (error) {
      console.error('Quantum key generation failed:', error);
      throw error;
    }
  }

  /**
   * Register quantum-capable device
   */
  async registerQuantumDevice(capabilities: string[] = ['ml-kem-768']): Promise<DeviceCapabilities> {
    try {
      // Generate device key pair first
      const keyPair = await this.generateQuantumKeyPair('ML-KEM-768');
      
      const deviceInfo = {
        device_name: this.getDeviceName(),
        device_type: this.getDeviceType(),
        public_key: keyPair.public_key,
        device_fingerprint: await this.getDeviceFingerprint(),
        quantum_capabilities: capabilities,
        device_info: {
          user_agent: navigator.userAgent,
          quantum_ready: true,
          supported_algorithms: this.supportedAlgorithms.map(a => a.name),
          browser: this.getBrowserInfo(),
          platform: navigator.platform,
          language: navigator.language,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        }
      };
      
      const response = await fetch('/api/v1/quantum/devices/register', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        },
        body: JSON.stringify(deviceInfo)
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Device registration failed');
      }
      
      const result = await response.json();
      
      // Store private key securely
      await this.storePrivateKey(keyPair.private_key, result.device.id);
      
      console.log('Quantum device registered:', {
        device_id: result.device.id,
        quantum_ready: result.quantum_ready,
        encryption_version: result.encryption_version
      });
      
      // Refresh device capabilities
      await this.loadDeviceCapabilities();
      
      return result.device;
    } catch (error) {
      console.error('Quantum device registration failed:', error);
      throw error;
    }
  }

  /**
   * Load current device capabilities
   */
  async loadDeviceCapabilities(): Promise<DeviceCapabilities[]> {
    try {
      const response = await fetch('/api/v1/quantum/devices/capabilities', {
        method: 'GET',
        headers: { 
          'Accept': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        }
      });
      
      if (!response.ok) {
        throw new Error(`Failed to load device capabilities: ${response.statusText}`);
      }
      
      const data = await response.json();
      this.deviceCapabilities = data.devices;
      
      return this.deviceCapabilities;
    } catch (error) {
      console.error('Failed to load device capabilities:', error);
      throw error;
    }
  }

  /**
   * Negotiate algorithm for conversation
   */
  async negotiateConversationAlgorithm(conversationId: string): Promise<ConversationAlgorithm> {
    try {
      const response = await fetch(`/api/v1/quantum/conversations/${conversationId}/negotiate-algorithm`, {
        method: 'POST',
        headers: { 
          'Accept': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        }
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Algorithm negotiation failed');
      }
      
      const result = await response.json();
      
      console.log('Algorithm negotiated for conversation:', {
        conversation_id: conversationId,
        algorithm: result.algorithm,
        quantum_resistant: result.quantum_resistant,
        participants: result.participants
      });
      
      return result;
    } catch (error) {
      console.error('Algorithm negotiation failed:', error);
      throw error;
    }
  }

  /**
   * Update device quantum capabilities
   */
  async updateDeviceCapabilities(deviceId: string, capabilities: string[]): Promise<DeviceCapabilities> {
    try {
      const response = await fetch(`/api/v1/quantum/devices/${deviceId}/capabilities`, {
        method: 'PUT',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.getAuthToken()}`
        },
        body: JSON.stringify({ quantum_capabilities: capabilities })
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to update device capabilities');
      }
      
      const result = await response.json();
      
      // Refresh device capabilities
      await this.loadDeviceCapabilities();
      
      return result.device;
    } catch (error) {
      console.error('Failed to update device capabilities:', error);
      throw error;
    }
  }

  /**
   * Get algorithm information
   */
  getAlgorithmInfo(algorithm: string): QuantumAlgorithm | null {
    return this.supportedAlgorithms.find(a => a.name === algorithm) || null;
  }

  /**
   * Check if algorithm is quantum-resistant
   */
  isQuantumResistant(algorithm: string): boolean {
    const info = this.getAlgorithmInfo(algorithm);
    return info?.quantumResistant || false;
  }

  /**
   * Get recommended algorithm based on device capabilities - uses strongest available first
   */
  getRecommendedAlgorithm(): string {
    if (!this.deviceCapabilities.length) {
      return 'RSA-4096-OAEP'; // Fallback when no devices
    }
    
    const quantumReadyDevices = this.deviceCapabilities.filter(d => d.quantumReady);
    const allQuantumReady = quantumReadyDevices.length === this.deviceCapabilities.length;
    
    if (allQuantumReady && quantumReadyDevices.length > 0) {
      // All devices support quantum - try strongest first
      const allCapabilities = this.deviceCapabilities.flatMap(d => d.quantumCapabilities);
      
      // Check for strongest algorithms in order
      if (allCapabilities.includes('ml-kem-1024')) return 'ML-KEM-1024';
      if (allCapabilities.includes('ml-kem-768')) return 'ML-KEM-768';
      if (allCapabilities.includes('ml-kem-512')) return 'ML-KEM-512';
      
      return 'ML-KEM-768'; // Default quantum
    }
    
    if (quantumReadyDevices.length > 0) {
      return 'HYBRID-RSA4096-MLKEM768'; // Mixed environment
    }
    
    return 'RSA-4096-OAEP'; // Legacy devices only
  }

  /**
   * Get system health status
   */
  getHealthStatus(): QuantumHealthStatus | null {
    return this.healthStatus;
  }

  /**
   * Get current device capabilities
   */
  getDeviceCapabilities(): DeviceCapabilities[] {
    return this.deviceCapabilities;
  }

  /**
   * Get quantum-ready device count
   */
  getQuantumReadyDeviceCount(): number {
    return this.deviceCapabilities.filter(d => d.quantumReady).length;
  }

  /**
   * Get quantum readiness percentage
   */
  getQuantumReadinessPercentage(): number {
    if (this.deviceCapabilities.length === 0) return 0;
    return (this.getQuantumReadyDeviceCount() / this.deviceCapabilities.length) * 100;
  }

  /**
   * Check if quantum cryptography is available
   */
  isQuantumAvailable(): boolean {
    return this.healthStatus?.quantum_support?.ml_kem_available || false;
  }

  /**
   * Get supported algorithms list
   */
  getSupportedAlgorithms(): QuantumAlgorithm[] {
    return this.supportedAlgorithms;
  }

  // Private helper methods

  private getAuthToken(): string {
    // Get token from localStorage, cookie, or auth context
    return localStorage.getItem('auth_token') || '';
  }

  private getDeviceName(): string {
    const browserInfo = this.getBrowserInfo();
    const date = new Date().toISOString().slice(0, 10);
    return `${browserInfo.name} on ${navigator.platform} - ${date}`;
  }

  private getDeviceType(): string {
    const ua = navigator.userAgent;
    if (/mobile|android|iphone/i.test(ua)) return 'mobile';
    if (/tablet|ipad/i.test(ua)) return 'tablet';
    if (/electron/i.test(ua)) return 'desktop';
    return 'web';
  }

  private getBrowserInfo(): { name: string; version: string } {
    const ua = navigator.userAgent;
    let name = 'Unknown';
    let version = 'Unknown';
    
    if (ua.includes('Chrome')) {
      name = 'Chrome';
      const match = ua.match(/Chrome\/(\d+)/);
      version = match ? match[1] : 'Unknown';
    } else if (ua.includes('Firefox')) {
      name = 'Firefox';
      const match = ua.match(/Firefox\/(\d+)/);
      version = match ? match[1] : 'Unknown';
    } else if (ua.includes('Safari') && !ua.includes('Chrome')) {
      name = 'Safari';
      const match = ua.match(/Version\/(\d+)/);
      version = match ? match[1] : 'Unknown';
    } else if (ua.includes('Edge')) {
      name = 'Edge';
      const match = ua.match(/Edge\/(\d+)/);
      version = match ? match[1] : 'Unknown';
    }
    
    return { name, version };
  }

  private async getDeviceFingerprint(): Promise<string> {
    // Create device fingerprint from various browser characteristics
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.textBaseline = 'top';
      ctx.font = '14px Arial';
      ctx.fillText('Quantum Device Fingerprint', 2, 2);
    }
    
    const fingerprint = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      screen.colorDepth,
      new Date().getTimezoneOffset(),
      navigator.hardwareConcurrency || 'unknown',
      navigator.maxTouchPoints || 0,
      canvas.toDataURL()
    ].join('|');
    
    // Hash the fingerprint
    const encoder = new TextEncoder();
    const data = encoder.encode(fingerprint);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  }

  private async storePrivateKey(privateKey: string, deviceId: string): Promise<void> {
    // Store private key in IndexedDB for persistence
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('quantum-keys', 1);
      
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains('keys')) {
          db.createObjectStore('keys', { keyPath: 'id' });
        }
      };
      
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(['keys'], 'readwrite');
        const store = transaction.objectStore('keys');
        
        store.put({
          id: `device-private-key-${deviceId}`,
          key: privateKey,
          deviceId: deviceId,
          createdAt: new Date().toISOString()
        });
        
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
      };
      
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Retrieve private key for device
   */
  async getPrivateKey(deviceId: string): Promise<string | null> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('quantum-keys', 1);
      
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(['keys'], 'readonly');
        const store = transaction.objectStore('keys');
        const getRequest = store.get(`device-private-key-${deviceId}`);
        
        getRequest.onsuccess = () => {
          const result = getRequest.result;
          resolve(result ? result.key : null);
        };
        
        getRequest.onerror = () => reject(getRequest.error);
      };
      
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Clear stored private keys (for logout/security)
   */
  async clearStoredKeys(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open('quantum-keys', 1);
      
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(['keys'], 'readwrite');
        const store = transaction.objectStore('keys');
        
        const clearRequest = store.clear();
        clearRequest.onsuccess = () => resolve();
        clearRequest.onerror = () => reject(clearRequest.error);
      };
      
      request.onerror = () => reject(request.error);
    });
  }
}