import { useState, useRef, useEffect, useCallback } from 'react';
import { WebSocketConnectionStatus, WebSocketOptions, WebSocketMessage } from '@/types/notification';
import { getUserStorageItem } from '@/utils/localStorage';

interface UseWebSocketProps {
  url: string;
  options?: WebSocketOptions;
  enabled?: boolean;
}

export const useWebSocket = ({ url, options = {}, enabled = true }: UseWebSocketProps) => {
  const [isConnected, setIsConnected] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState<WebSocketConnectionStatus>('disconnected');
  const [lastMessage, setLastMessage] = useState<WebSocketMessage | null>(null);
  const [error, setError] = useState<string | null>(null);
  
  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const heartbeatIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const reconnectAttemptsRef = useRef(0);
  const isManuallyClosedRef = useRef(false);

  const {
    onOpen,
    onMessage,
    onClose,
    onError,
    reconnectInterval = 3000,
    maxReconnectAttempts = 5,
    heartbeatInterval = 30000,
  } = options;

  const clearTimeouts = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
      reconnectTimeoutRef.current = null;
    }
    if (heartbeatIntervalRef.current) {
      clearInterval(heartbeatIntervalRef.current);
      heartbeatIntervalRef.current = null;
    }
  }, []);

  const startHeartbeat = useCallback(() => {
    if (heartbeatInterval > 0) {
      heartbeatIntervalRef.current = setInterval(() => {
        if (wsRef.current?.readyState === WebSocket.OPEN) {
          wsRef.current.send(JSON.stringify({ type: 'ping' }));
        }
      }, heartbeatInterval);
    }
  }, [heartbeatInterval]);

  const connect = useCallback(() => {
    if (!enabled || wsRef.current?.readyState === WebSocket.OPEN) return;

    setConnectionStatus('connecting');
    setError(null);
    isManuallyClosedRef.current = false;

    try {
      const ws = new WebSocket(url);
      wsRef.current = ws;

      ws.onopen = (event) => {
        setIsConnected(true);
        setConnectionStatus('connected');
        setError(null);
        reconnectAttemptsRef.current = 0;
        startHeartbeat();
        onOpen?.(event);
      };

      ws.onmessage = (event) => {
        try {
          const message: WebSocketMessage = JSON.parse(event.data);
          
          // Handle pong response
          if (message.type === 'pong') {
            return; // Don't update lastMessage for heartbeat responses
          }
          
          setLastMessage(message);
          onMessage?.(event);
        } catch (err) {
          console.error('Failed to parse WebSocket message:', err);
        }
      };

      ws.onclose = (event) => {
        setIsConnected(false);
        clearTimeouts();
        
        if (!isManuallyClosedRef.current) {
          setConnectionStatus('disconnected');
          onClose?.(event);
          
          // Attempt reconnection if not manually closed
          if (reconnectAttemptsRef.current < maxReconnectAttempts) {
            setConnectionStatus('reconnecting');
            reconnectAttemptsRef.current += 1;
            
            reconnectTimeoutRef.current = setTimeout(() => {
              connect();
            }, reconnectInterval * reconnectAttemptsRef.current); // Exponential backoff
          } else {
            setConnectionStatus('error');
            setError('Maximum reconnection attempts reached');
          }
        } else {
          setConnectionStatus('closed');
        }
        
        wsRef.current = null;
      };

      ws.onerror = (event) => {
        setError('WebSocket connection error');
        setConnectionStatus('error');
        onError?.(event);
      };

    } catch (err) {
      setError('Failed to create WebSocket connection');
      setConnectionStatus('error');
    }
  }, [url, enabled, onOpen, onMessage, onClose, onError, reconnectInterval, maxReconnectAttempts, startHeartbeat, clearTimeouts]);

  const disconnect = useCallback(() => {
    isManuallyClosedRef.current = true;
    clearTimeouts();
    
    if (wsRef.current) {
      wsRef.current.close();
      wsRef.current = null;
    }
    
    setIsConnected(false);
    setConnectionStatus('closed');
    setError(null);
  }, [clearTimeouts]);

  const sendMessage = useCallback((message: WebSocketMessage | Record<string, any>) => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      const messageToSend = 'type' in message ? message : {
        ...message,
        timestamp: new Date().toISOString(),
      };
      
      wsRef.current.send(JSON.stringify(messageToSend));
      return true;
    }
    return false;
  }, []);

  const reconnect = useCallback(() => {
    reconnectAttemptsRef.current = 0;
    disconnect();
    setTimeout(connect, 100);
  }, [connect, disconnect]);

  // Initialize connection
  useEffect(() => {
    if (enabled) {
      connect();
    } else {
      disconnect();
    }

    return () => {
      disconnect();
    };
  }, [enabled, url]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      disconnect();
    };
  }, [disconnect]);

  // Handle visibility change to reconnect when tab becomes visible
  useEffect(() => {
    const handleVisibilityChange = () => {
      if (!document.hidden && !isConnected && enabled && connectionStatus === 'disconnected') {
        connect();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [isConnected, enabled, connectionStatus, connect]);

  // Handle online/offline events
  useEffect(() => {
    const handleOnline = () => {
      if (enabled && !isConnected) {
        connect();
      }
    };

    const handleOffline = () => {
      setConnectionStatus('disconnected');
      setError('Network connection lost');
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [enabled, isConnected, connect]);

  return {
    isConnected,
    connectionStatus,
    lastMessage,
    error,
    sendMessage,
    disconnect,
    reconnect,
    readyState: wsRef.current?.readyState,
  };
};

// Hook for specific WebSocket channels
export const useWebSocketChannel = (channel: string, options: WebSocketOptions = {}) => {
  const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws/${channel}`;
  
  return useWebSocket({
    url: wsUrl,
    options,
  });
};

// Hook for authenticated WebSocket connections
export const useAuthenticatedWebSocket = (options: WebSocketOptions = {}) => {
  const [authToken, setAuthToken] = useState<string | null>(null);

  useEffect(() => {
    // Get auth token from meta tag or local storage
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                  getUserStorageItem('auth_token');
    setAuthToken(token);
  }, []);

  const wsUrl = authToken 
    ? `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws?token=${authToken}`
    : '';

  return useWebSocket({
    url: wsUrl,
    options,
    enabled: !!authToken,
  });
};

// Hook for conversation-specific WebSocket connections
export const useConversationWebSocket = (conversationId: string, options: WebSocketOptions = {}) => {
  return useWebSocketChannel(`conversations/${conversationId}`, {
    ...options,
    onMessage: (event: MessageEvent) => {
      try {
        const message = JSON.parse(event.data);
        
        // Filter messages for this conversation
        if (message.conversation_id === conversationId || message.type === 'system') {
          options.onMessage?.(event);
        }
      } catch (error) {
        console.error('Failed to parse conversation WebSocket message:', error);
      }
    },
  });
};

// Hook for organization-wide WebSocket connections
export const useOrganizationWebSocket = (organizationId: string, options: WebSocketOptions = {}) => {
  return useWebSocketChannel(`organizations/${organizationId}`, {
    ...options,
    onMessage: (event: MessageEvent) => {
      try {
        const message = JSON.parse(event.data);
        
        // Filter messages for this organization
        if (message.organization_id === organizationId || message.type === 'system') {
          options.onMessage?.(event);
        }
      } catch (error) {
        console.error('Failed to parse organization WebSocket message:', error);
      }
    },
  });
};