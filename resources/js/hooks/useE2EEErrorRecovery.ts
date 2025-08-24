import { useCallback, useEffect, useState } from 'react';
import { e2eeErrorRecovery, type E2EEError, type RecoveryStrategy } from '@/services/E2EEErrorRecovery';

interface UseE2EEErrorRecoveryReturn {
  processError: (error: Error, context?: { conversationId?: string; userId?: string; operation?: string }) => E2EEError;
  getRecoveryStrategies: (error: E2EEError) => RecoveryStrategy[];
  executeRecovery: (strategy: RecoveryStrategy) => Promise<boolean>;
  errorHistory: E2EEError[];
  clearHistory: () => void;
  isRecovering: boolean;
  lastError: E2EEError | null;
  errorStatistics: ReturnType<typeof e2eeErrorRecovery.getErrorStatistics>;
  autoRecoveryEnabled: boolean;
  setAutoRecoveryEnabled: (enabled: boolean) => void;
}

export function useE2EEErrorRecovery(): UseE2EEErrorRecoveryReturn {
  const [errorHistory, setErrorHistory] = useState<E2EEError[]>([]);
  const [isRecovering, setIsRecovering] = useState(false);
  const [lastError, setLastError] = useState<E2EEError | null>(null);
  const [errorStatistics, setErrorStatistics] = useState(() => e2eeErrorRecovery.getErrorStatistics());
  const [autoRecoveryEnabled, setAutoRecoveryEnabledState] = useState(true);

  // Update error history periodically
  useEffect(() => {
    const updateHistory = () => {
      setErrorHistory(e2eeErrorRecovery.getErrorHistory());
      setErrorStatistics(e2eeErrorRecovery.getErrorStatistics());
    };

    updateHistory();
    const interval = setInterval(updateHistory, 5000); // Update every 5 seconds

    return () => clearInterval(interval);
  }, []);

  // Listen for error recovery events
  useEffect(() => {
    const handleRecoveryStart = () => setIsRecovering(true);
    const handleRecoveryEnd = () => setIsRecovering(false);
    
    window.addEventListener('e2ee:recovery-start', handleRecoveryStart);
    window.addEventListener('e2ee:recovery-end', handleRecoveryEnd);
    
    return () => {
      window.removeEventListener('e2ee:recovery-start', handleRecoveryStart);
      window.removeEventListener('e2ee:recovery-end', handleRecoveryEnd);
    };
  }, []);

  const processError = useCallback((
    error: Error, 
    context?: { conversationId?: string; userId?: string; operation?: string }
  ): E2EEError => {
    const e2eeError = e2eeErrorRecovery.processError(error, context);
    setLastError(e2eeError);
    setErrorHistory(e2eeErrorRecovery.getErrorHistory());
    setErrorStatistics(e2eeErrorRecovery.getErrorStatistics());
    return e2eeError;
  }, []);

  const getRecoveryStrategies = useCallback((error: E2EEError): RecoveryStrategy[] => {
    return e2eeErrorRecovery.getRecoveryStrategies(error);
  }, []);

  const executeRecovery = useCallback(async (strategy: RecoveryStrategy): Promise<boolean> => {
    setIsRecovering(true);
    window.dispatchEvent(new CustomEvent('e2ee:recovery-start'));
    
    try {
      const result = await strategy.execute();
      setErrorHistory(e2eeErrorRecovery.getErrorHistory());
      setErrorStatistics(e2eeErrorRecovery.getErrorStatistics());
      return result;
    } catch (error) {
      console.error('Recovery strategy execution failed:', error);
      return false;
    } finally {
      setIsRecovering(false);
      window.dispatchEvent(new CustomEvent('e2ee:recovery-end'));
    }
  }, []);

  const clearHistory = useCallback(() => {
    e2eeErrorRecovery.clearErrorHistory();
    setErrorHistory([]);
    setLastError(null);
    setErrorStatistics(e2eeErrorRecovery.getErrorStatistics());
  }, []);

  const setAutoRecoveryEnabled = useCallback((enabled: boolean) => {
    e2eeErrorRecovery.setAutoRecoveryEnabled(enabled);
    setAutoRecoveryEnabledState(enabled);
  }, []);

  return {
    processError,
    getRecoveryStrategies,
    executeRecovery,
    errorHistory,
    clearHistory,
    isRecovering,
    lastError,
    errorStatistics,
    autoRecoveryEnabled,
    setAutoRecoveryEnabled
  };
}