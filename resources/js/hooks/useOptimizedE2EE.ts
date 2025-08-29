import { useCallback, useEffect, useState } from 'react';
import { optimizedE2EEService } from '@/services/OptimizedE2EEService';
import { e2eePerformanceMonitor } from '@/utils/E2EEPerformanceMonitor';
import { e2eeErrorRecovery } from '@/services/E2EEErrorRecovery';
import type { EncryptedMessageData } from '@/types/chat';

interface UseOptimizedE2EEReturn {
  encryptMessage: (message: string, conversationId: string) => Promise<EncryptedMessageData | null>;
  decryptMessage: (encryptedData: EncryptedMessageData, conversationId: string) => Promise<string | null>;
  isOptimizationEnabled: boolean;
  setOptimizationEnabled: (enabled: boolean) => void;
  performanceStats: ReturnType<typeof e2eePerformanceMonitor.getStats>;
  cacheStats: ReturnType<typeof optimizedE2EEService.getCacheStats>;
  realtimeMetrics: ReturnType<typeof e2eePerformanceMonitor.getRealTimeSummary>;
  optimizationSuggestions: string[];
  clearPerformanceData: () => void;
  exportPerformanceData: () => string;
  configureOptimizations: (config: {
    keyCacheTTL?: number;
    batchSize?: number;
    compressionThreshold?: number;
    chunkThreshold?: number;
  }) => void;
}

export function useOptimizedE2EE(userId?: string): UseOptimizedE2EEReturn {
  const [isOptimizationEnabled, setIsOptimizationEnabledState] = useState(true);
  const [performanceStats, setPerformanceStats] = useState(() => e2eePerformanceMonitor.getStats());
  const [cacheStats, setCacheStats] = useState(() => optimizedE2EEService.getCacheStats());
  const [realtimeMetrics, setRealtimeMetrics] = useState(() => e2eePerformanceMonitor.getRealTimeSummary());
  const [optimizationSuggestions, setOptimizationSuggestions] = useState<string[]>([]);

  // Enhanced encrypt message with performance monitoring and error recovery
  const encryptMessage = useCallback(async (
    message: string, 
    conversationId: string
  ): Promise<EncryptedMessageData | null> => {
    const stopTimer = e2eePerformanceMonitor.startOperation('encrypt_with_optimization');
    
    try {
      let result: EncryptedMessageData | null = null;
      
      if (isOptimizationEnabled) {
        // Use optimized service with caching, batching, and compression
        result = await optimizedE2EEService.encryptMessage(message, conversationId);
      } else {
        // Fallback to standard encryption
        // This would use the regular ChatEncryption service
        throw new Error('Standard encryption not implemented in this hook');
      }

      if (result) {
        stopTimer(true, {
          dataSize: message.length,
          keyType: 'optimized'
        });
      } else {
        throw new Error('Encryption returned null result');
      }

      return result;
    } catch (error) {
      stopTimer(false, {
        error: error instanceof Error ? error.message : 'Unknown encryption error'
      });

      // Process error through recovery system
      const e2eeError = e2eeErrorRecovery.processError(
        error instanceof Error ? error : new Error('Unknown encryption error'),
        {
          userId,
          conversationId,
          operation: 'encrypt'
        }
      );

      console.error('Optimized encryption failed:', e2eeError);
      return null;
    }
  }, [isOptimizationEnabled, userId]);

  // Enhanced decrypt message with performance monitoring and error recovery
  const decryptMessage = useCallback(async (
    encryptedData: EncryptedMessageData, 
    conversationId: string
  ): Promise<string | null> => {
    const stopTimer = e2eePerformanceMonitor.startOperation('decrypt_with_optimization');
    
    try {
      let result: string | null = null;
      
      if (isOptimizationEnabled) {
        // Use optimized service with caching and decompression
        result = await optimizedE2EEService.decryptMessage(encryptedData, conversationId);
      } else {
        // Fallback to standard decryption
        throw new Error('Standard decryption not implemented in this hook');
      }

      if (result !== null) {
        stopTimer(true, {
          dataSize: encryptedData.data.length,
          keyType: 'optimized'
        });
      } else {
        throw new Error('Decryption returned null result');
      }

      return result;
    } catch (error) {
      stopTimer(false, {
        error: error instanceof Error ? error.message : 'Unknown decryption error'
      });

      // Process error through recovery system
      const e2eeError = e2eeErrorRecovery.processError(
        error instanceof Error ? error : new Error('Unknown decryption error'),
        {
          userId,
          conversationId,
          operation: 'decrypt'
        }
      );

      console.error('Optimized decryption failed:', e2eeError);
      return null;
    }
  }, [isOptimizationEnabled, userId]);

  // Set optimization enabled state with persistence
  const setOptimizationEnabled = useCallback((enabled: boolean) => {
    setIsOptimizationEnabledState(enabled);
    localStorage.setItem('e2ee_optimization_enabled', JSON.stringify(enabled));
  }, []);

  // Clear performance data
  const clearPerformanceData = useCallback(() => {
    e2eePerformanceMonitor.clearMetrics();
    optimizedE2EEService.clearCaches();
    updateStats();
  }, []);

  // Export performance data
  const exportPerformanceData = useCallback(() => {
    return e2eePerformanceMonitor.exportMetrics();
  }, []);

  // Configure optimizations
  const configureOptimizations = useCallback((config: {
    keyCacheTTL?: number;
    batchSize?: number;
    compressionThreshold?: number;
    chunkThreshold?: number;
  }) => {
    optimizedE2EEService.configure(config);
    updateStats();
  }, []);

  // Update performance statistics
  const updateStats = useCallback(() => {
    setPerformanceStats(e2eePerformanceMonitor.getStats());
    setCacheStats(optimizedE2EEService.getCacheStats());
    setRealtimeMetrics(e2eePerformanceMonitor.getRealTimeSummary());
    
    // Generate optimization suggestions
    const stats = e2eePerformanceMonitor.getStats();
    const optimizationCheck = e2eePerformanceMonitor.needsOptimization();
    
    const suggestions: string[] = [];
    
    // Add performance-based suggestions
    suggestions.push(...stats.recommendations);
    
    // Add optimization check reasons
    if (optimizationCheck.needed) {
      suggestions.push(...optimizationCheck.reasons);
    }
    
    // Add cache-specific suggestions
    const cache = optimizedE2EEService.getCacheStats();
    if (cache.hitRate < 50) {
      suggestions.push('Cache hit rate is low. Consider increasing cache TTL or reviewing key access patterns.');
    }
    if (cache.size === 0) {
      suggestions.push('No keys are cached. Enable key caching for better performance.');
    }
    
    // Add realtime suggestions
    const realtime = e2eePerformanceMonitor.getRealTimeSummary();
    if (realtime.currentLoad === 'high') {
      suggestions.push('System load is high. Consider enabling batching or reducing operation frequency.');
    }
    if (realtime.errorRate > 5) {
      suggestions.push('Error rate is elevated. Review error logs and consider enabling automatic recovery.');
    }
    
    setOptimizationSuggestions([...new Set(suggestions)]); // Remove duplicates
  }, []);

  // Auto-optimization based on performance metrics
  const performAutoOptimization = useCallback(() => {
    const realtime = e2eePerformanceMonitor.getRealTimeSummary();
    const cache = optimizedE2EEService.getCacheStats();
    const optimizationCheck = e2eePerformanceMonitor.needsOptimization();
    
    const autoOptimizations: Parameters<typeof configureOptimizations>[0] = {};
    
    // Adjust cache TTL based on hit rate
    if (cache.hitRate < 30) {
      autoOptimizations.keyCacheTTL = 10 * 60 * 1000; // Increase to 10 minutes
    } else if (cache.hitRate > 90) {
      autoOptimizations.keyCacheTTL = 2 * 60 * 1000; // Decrease to 2 minutes
    }
    
    // Adjust batch size based on load
    if (realtime.currentLoad === 'high' && realtime.recentOperations > 50) {
      autoOptimizations.batchSize = 20; // Increase batch size
    } else if (realtime.currentLoad === 'low') {
      autoOptimizations.batchSize = 5; // Decrease batch size for lower latency
    }
    
    // Adjust compression threshold based on average latency
    if (realtime.averageLatency > 1000) {
      autoOptimizations.compressionThreshold = 500; // More aggressive compression
    } else if (realtime.averageLatency < 100) {
      autoOptimizations.compressionThreshold = 2000; // Less compression overhead
    }
    
    // Apply auto-optimizations if any were determined
    if (Object.keys(autoOptimizations).length > 0) {
      console.log('Applying auto-optimizations:', autoOptimizations);
      configureOptimizations(autoOptimizations);
    }
  }, [configureOptimizations]);

  // Initialize optimization state from localStorage
  useEffect(() => {
    const savedEnabled = localStorage.getItem('e2ee_optimization_enabled');
    if (savedEnabled !== null) {
      setIsOptimizationEnabledState(JSON.parse(savedEnabled));
    }
  }, []);

  // Performance monitoring and auto-optimization interval
  useEffect(() => {
    const updateInterval = setInterval(() => {
      updateStats();
    }, 5000); // Update every 5 seconds

    const autoOptimizeInterval = setInterval(() => {
      if (isOptimizationEnabled) {
        performAutoOptimization();
      }
    }, 30000); // Auto-optimize every 30 seconds

    return () => {
      clearInterval(updateInterval);
      clearInterval(autoOptimizeInterval);
    };
  }, [isOptimizationEnabled, updateStats, performAutoOptimization]);

  // Initial stats load
  useEffect(() => {
    updateStats();
  }, [updateStats]);

  // Monitor for performance degradation and suggest enabling optimizations
  useEffect(() => {
    const checkPerformanceDegradation = () => {
      const realtime = e2eePerformanceMonitor.getRealTimeSummary();
      const optimizationCheck = e2eePerformanceMonitor.needsOptimization();
      
      if (!isOptimizationEnabled && optimizationCheck.needed && optimizationCheck.priority === 'high') {
        console.warn('Performance degradation detected. Consider enabling optimizations.');
        // Could trigger a notification or UI prompt here
      }
    };

    const checkInterval = setInterval(checkPerformanceDegradation, 10000);
    return () => clearInterval(checkInterval);
  }, [isOptimizationEnabled]);

  return {
    encryptMessage,
    decryptMessage,
    isOptimizationEnabled,
    setOptimizationEnabled,
    performanceStats,
    cacheStats,
    realtimeMetrics,
    optimizationSuggestions,
    clearPerformanceData,
    exportPerformanceData,
    configureOptimizations
  };
}