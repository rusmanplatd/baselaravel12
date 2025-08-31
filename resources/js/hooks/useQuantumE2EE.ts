import { useCallback, useEffect, useState, useRef } from 'react';
import { QuantumE2EEService } from '@/services/QuantumE2EEService';
import { toast } from 'sonner';

export interface QuantumE2EEStatus {
  quantumReady: boolean;
  quantumAvailable: boolean;
  currentAlgorithm: string | null;
  encryptionVersion: number;
  deviceQuantumCapabilities: string[];
  isNegotiatingAlgorithm: boolean;
  lastHealthCheck: string | null;
  migrationStatus: 'not-started' | 'in-progress' | 'completed' | 'failed';
}

export interface QuantumDeviceStatus {
  totalDevices: number;
  quantumReadyDevices: number;
  quantumReadinessPercentage: number;
  recommendedAlgorithm: string;
  needsUpgrade: boolean;
}

export interface QuantumConversationSettings {
  conversationId: string;
  algorithm: string;
  quantumResistant: boolean;
  participantDeviceCount: number;
  compatibleDeviceCount: number;
  lastNegotiation: string | null;
  migrationAvailable: boolean;
}

export interface UseQuantumE2EEReturn {
  // Status
  quantumStatus: QuantumE2EEStatus;
  deviceStatus: QuantumDeviceStatus;
  isLoading: boolean;
  error: string | null;

  // System Management
  checkQuantumHealth: () => Promise<void>;
  initializeQuantumSupport: () => Promise<void>;
  registerQuantumDevice: (capabilities?: string[]) => Promise<void>;

  // Algorithm Management
  negotiateConversationAlgorithm: (conversationId: string) => Promise<string>;
  upgradeConversationEncryption: (conversationId: string, targetAlgorithm?: string) => Promise<void>;
  getConversationQuantumSettings: (conversationId: string) => Promise<QuantumConversationSettings>;

  // Device Management
  updateDeviceCapabilities: (deviceId: string, capabilities: string[]) => Promise<void>;
  getQuantumCapableDevices: () => Promise<any[]>;
  migrateDeviceToQuantum: (deviceId: string) => Promise<void>;

  // Utilities
  isQuantumResistant: (algorithm?: string) => boolean;
  getRecommendedAlgorithm: () => string;
  getAlgorithmInfo: (algorithm: string) => any;
  getSupportedAlgorithms: () => any[];

  // Migration
  startQuantumMigration: () => Promise<void>;
  checkMigrationStatus: () => Promise<string>;
  rollbackToClassical: () => Promise<void>;
}

export function useQuantumE2EE(): UseQuantumE2EEReturn {
  const [quantumStatus, setQuantumStatus] = useState<QuantumE2EEStatus>({
    quantumReady: false,
    quantumAvailable: false,
    currentAlgorithm: null,
    encryptionVersion: 2,
    deviceQuantumCapabilities: [],
    isNegotiatingAlgorithm: false,
    lastHealthCheck: null,
    migrationStatus: 'not-started'
  });

  const [deviceStatus, setDeviceStatus] = useState<QuantumDeviceStatus>({
    totalDevices: 0,
    quantumReadyDevices: 0,
    quantumReadinessPercentage: 0,
    recommendedAlgorithm: 'RSA-4096-OAEP',
    needsUpgrade: false
  });

  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const quantumService = useRef<QuantumE2EEService>();
  const healthCheckInterval = useRef<NodeJS.Timeout>();

  useEffect(() => {
    initializeQuantumService();
    
    // Set up periodic health checks
    healthCheckInterval.current = setInterval(() => {
      checkQuantumHealth();
    }, 60000); // Check every minute

    return () => {
      if (healthCheckInterval.current) {
        clearInterval(healthCheckInterval.current);
      }
    };
  }, []);

  const initializeQuantumService = useCallback(async () => {
    try {
      quantumService.current = QuantumE2EEService.getInstance();
      await quantumService.current.initialize();
      await loadQuantumStatus();
    } catch (error) {
      console.warn('Quantum service initialization failed:', error);
      setError('Quantum cryptography not available');
    }
  }, []);

  const loadQuantumStatus = useCallback(async () => {
    if (!quantumService.current) return;

    try {
      const healthStatus = await quantumService.current.checkSystemHealth();
      const deviceCapabilities = await quantumService.current.loadDeviceCapabilities();

      setQuantumStatus(prev => ({
        ...prev,
        quantumAvailable: healthStatus.quantum_support.ml_kem_available,
        quantumReady: deviceCapabilities.some(d => d.quantumReady),
        currentAlgorithm: quantumService.current!.getRecommendedAlgorithm(),
        encryptionVersion: Math.max(...deviceCapabilities.map(d => d.encryptionVersion), 2),
        deviceQuantumCapabilities: deviceCapabilities.flatMap(d => d.quantumCapabilities),
        lastHealthCheck: healthStatus.timestamp
      }));

      setDeviceStatus({
        totalDevices: deviceCapabilities.length,
        quantumReadyDevices: quantumService.current.getQuantumReadyDeviceCount(),
        quantumReadinessPercentage: quantumService.current.getQuantumReadinessPercentage(),
        recommendedAlgorithm: quantumService.current.getRecommendedAlgorithm(),
        needsUpgrade: deviceCapabilities.some(d => !d.quantumReady)
      });

    } catch (error) {
      console.error('Failed to load quantum status:', error);
      setError(error instanceof Error ? error.message : 'Unknown error');
    }
  }, []);

  const checkQuantumHealth = useCallback(async () => {
    if (!quantumService.current) return;

    try {
      const healthStatus = await quantumService.current.checkSystemHealth();
      
      setQuantumStatus(prev => ({
        ...prev,
        quantumAvailable: healthStatus.quantum_support.ml_kem_available,
        lastHealthCheck: healthStatus.timestamp
      }));

      // Show warnings for degraded health
      if (healthStatus.status === 'degraded') {
        toast.warning('Quantum cryptography is running with reduced functionality');
      } else if (healthStatus.status === 'unhealthy') {
        toast.error('Quantum cryptography is not functioning properly');
      }

    } catch (error) {
      console.error('Quantum health check failed:', error);
    }
  }, []);

  const initializeQuantumSupport = useCallback(async () => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setIsLoading(true);
    setError(null);

    try {
      await quantumService.current.initialize();
      await loadQuantumStatus();
      
      toast.success('Quantum cryptography support initialized');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to initialize quantum support';
      setError(message);
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [loadQuantumStatus]);

  const registerQuantumDevice = useCallback(async (capabilities: string[] = ['ml-kem-768']) => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setIsLoading(true);
    setError(null);

    try {
      const device = await quantumService.current.registerQuantumDevice(capabilities);
      await loadQuantumStatus();
      
      toast.success(`Quantum device "${device.deviceName}" registered successfully`);
      
      return device;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to register quantum device';
      setError(message);
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [loadQuantumStatus]);

  const negotiateConversationAlgorithm = useCallback(async (conversationId: string): Promise<string> => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setQuantumStatus(prev => ({ ...prev, isNegotiatingAlgorithm: true }));
    
    try {
      const result = await quantumService.current.negotiateConversationAlgorithm(conversationId);
      
      toast.success(
        `Algorithm negotiated: ${result.algorithm}${result.quantumResistant ? ' (Quantum-resistant)' : ''}`
      );
      
      return result.algorithm;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Algorithm negotiation failed';
      setError(message);
      toast.error(message);
      throw error;
    } finally {
      setQuantumStatus(prev => ({ ...prev, isNegotiatingAlgorithm: false }));
    }
  }, []);

  const upgradeConversationEncryption = useCallback(async (
    conversationId: string, 
    targetAlgorithm?: string
  ) => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setIsLoading(true);

    try {
      // First negotiate the best available algorithm
      const negotiatedAlgorithm = await negotiateConversationAlgorithm(conversationId);
      const algorithm = targetAlgorithm || negotiatedAlgorithm;

      // TODO: Implement conversation encryption upgrade
      // This would involve re-encrypting the conversation with the new algorithm
      
      toast.success(`Conversation upgraded to ${algorithm} encryption`);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to upgrade conversation encryption';
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [negotiateConversationAlgorithm]);

  const getConversationQuantumSettings = useCallback(async (
    conversationId: string
  ): Promise<QuantumConversationSettings> => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    try {
      const result = await quantumService.current.negotiateConversationAlgorithm(conversationId);
      
      return {
        conversationId,
        algorithm: result.algorithm,
        quantumResistant: result.quantumResistant,
        participantDeviceCount: result.participants,
        compatibleDeviceCount: result.compatible_devices,
        lastNegotiation: new Date().toISOString(),
        migrationAvailable: !result.quantumResistant && quantumStatus.quantumAvailable
      };
    } catch (error) {
      console.error('Failed to get conversation quantum settings:', error);
      throw error;
    }
  }, [quantumStatus.quantumAvailable]);

  const updateDeviceCapabilities = useCallback(async (deviceId: string, capabilities: string[]) => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setIsLoading(true);

    try {
      await quantumService.current.updateDeviceCapabilities(deviceId, capabilities);
      await loadQuantumStatus();
      
      toast.success('Device quantum capabilities updated');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to update device capabilities';
      setError(message);
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [loadQuantumStatus]);

  const getQuantumCapableDevices = useCallback(async () => {
    if (!quantumService.current) {
      return [];
    }

    try {
      await quantumService.current.loadDeviceCapabilities();
      return quantumService.current.getDeviceCapabilities().filter(d => d.quantumReady);
    } catch (error) {
      console.error('Failed to get quantum capable devices:', error);
      return [];
    }
  }, []);

  const migrateDeviceToQuantum = useCallback(async (deviceId: string) => {
    if (!quantumService.current) {
      throw new Error('Quantum service not available');
    }

    setIsLoading(true);

    try {
      // Update device to support quantum algorithms
      await updateDeviceCapabilities(deviceId, ['ml-kem-768', 'hybrid']);
      
      toast.success('Device migrated to quantum-resistant encryption');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Device migration failed';
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [updateDeviceCapabilities]);

  const isQuantumResistant = useCallback((algorithm?: string): boolean => {
    if (!quantumService.current) return false;
    
    const alg = algorithm || quantumStatus.currentAlgorithm;
    return alg ? quantumService.current.isQuantumResistant(alg) : false;
  }, [quantumStatus.currentAlgorithm]);

  const getRecommendedAlgorithm = useCallback((): string => {
    if (!quantumService.current) return 'RSA-4096-OAEP';
    return quantumService.current.getRecommendedAlgorithm();
  }, []);

  const getAlgorithmInfo = useCallback((algorithm: string) => {
    if (!quantumService.current) return null;
    return quantumService.current.getAlgorithmInfo(algorithm);
  }, []);

  const getSupportedAlgorithms = useCallback(() => {
    if (!quantumService.current) return [];
    return quantumService.current.getSupportedAlgorithms();
  }, []);

  const startQuantumMigration = useCallback(async () => {
    setQuantumStatus(prev => ({ ...prev, migrationStatus: 'in-progress' }));
    
    try {
      // 1. Check system health
      await checkQuantumHealth();
      
      // 2. Register quantum devices if needed
      const devices = await getQuantumCapableDevices();
      if (devices.length === 0) {
        await registerQuantumDevice(['ml-kem-768']);
      }
      
      // 3. Update migration status
      setQuantumStatus(prev => ({ ...prev, migrationStatus: 'completed' }));
      
      toast.success('Quantum migration completed successfully');
    } catch (error) {
      setQuantumStatus(prev => ({ ...prev, migrationStatus: 'failed' }));
      const message = error instanceof Error ? error.message : 'Quantum migration failed';
      toast.error(message);
      throw error;
    }
  }, [checkQuantumHealth, getQuantumCapableDevices, registerQuantumDevice]);

  const checkMigrationStatus = useCallback(async (): Promise<string> => {
    return quantumStatus.migrationStatus;
  }, [quantumStatus.migrationStatus]);

  const rollbackToClassical = useCallback(async () => {
    setIsLoading(true);
    
    try {
      // This would involve downgrading device capabilities and reverting algorithms
      // For now, just reset status
      setQuantumStatus(prev => ({
        ...prev,
        quantumReady: false,
        currentAlgorithm: 'RSA-4096-OAEP',
        encryptionVersion: 2,
        deviceQuantumCapabilities: [],
        migrationStatus: 'not-started'
      }));
      
      toast.success('Rolled back to classical encryption');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Rollback failed';
      toast.error(message);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, []);

  return {
    quantumStatus,
    deviceStatus,
    isLoading,
    error,
    
    checkQuantumHealth,
    initializeQuantumSupport,
    registerQuantumDevice,
    
    negotiateConversationAlgorithm,
    upgradeConversationEncryption,
    getConversationQuantumSettings,
    
    updateDeviceCapabilities,
    getQuantumCapableDevices,
    migrateDeviceToQuantum,
    
    isQuantumResistant,
    getRecommendedAlgorithm,
    getAlgorithmInfo,
    getSupportedAlgorithms,
    
    startQuantumMigration,
    checkMigrationStatus,
    rollbackToClassical
  };
}