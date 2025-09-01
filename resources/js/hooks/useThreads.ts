import { useState, useCallback, useMemo } from 'react';
import { Message, ThreadViewMode, ThreadNavigation } from '@/types/chat';
import { apiService } from '@/services/ApiService';

interface UseThreadsOptions {
  conversationId: string;
  messages: Message[];
}

interface UseThreadsReturn {
  threadMode: ThreadViewMode;
  setThreadMode: (mode: ThreadViewMode) => void;
  loadThread: (messageId: string) => Promise<Message[]>;
  getThreadStats: () => { totalThreads: number; totalReplies: number };
  navigateToMessage: (navigation: ThreadNavigation) => void;
  expandThread: (threadId: string) => void;
  collapseThread: (threadId: string) => void;
  toggleThread: (threadId: string) => void;
  isThreadExpanded: (threadId: string) => boolean;
}

export function useThreads({ conversationId, messages }: UseThreadsOptions): UseThreadsReturn {
  const [threadMode, setThreadMode] = useState<ThreadViewMode>({
    showThreadsOnly: false,
    expandedThreads: new Set(),
    selectedThread: undefined,
  });

  const [threadCache, setThreadCache] = useState<Map<string, Message[]>>(new Map());
  const [loadingThreads, setLoadingThreads] = useState<Set<string>>(new Set());

  // Load thread messages from API
  const loadThread = useCallback(async (messageId: string): Promise<Message[]> => {
    // Check cache first
    if (threadCache.has(messageId)) {
      return threadCache.get(messageId)!;
    }

    // Check if already loading
    if (loadingThreads.has(messageId)) {
      // Return a promise that waits for the current loading to complete
      return new Promise((resolve) => {
        const checkCache = () => {
          if (threadCache.has(messageId)) {
            resolve(threadCache.get(messageId)!);
          } else if (!loadingThreads.has(messageId)) {
            // Loading failed, return empty array
            resolve([]);
          } else {
            // Still loading, check again in 100ms
            setTimeout(checkCache, 100);
          }
        };
        checkCache();
      });
    }

    // Start loading
    setLoadingThreads(prev => new Set(prev).add(messageId));

    try {
      const response = await apiService.get<{data: Message[]}>(`/api/chat/conversations/${conversationId}/messages/${messageId}/thread`);
      const threadMessages = response.data || [];
      
      // Cache the result
      setThreadCache(prev => new Map(prev).set(messageId, threadMessages));
      
      return threadMessages;
    } catch (error) {
      console.error('Error loading thread:', error);
      // Cache empty result to avoid repeated failed requests
      setThreadCache(prev => new Map(prev).set(messageId, []));
      return [];
    } finally {
      setLoadingThreads(prev => {
        const newSet = new Set(prev);
        newSet.delete(messageId);
        return newSet;
      });
    }
  }, [conversationId, threadCache, loadingThreads]);

  // Get thread statistics
  const getThreadStats = useCallback(() => {
    const totalThreads = messages.filter(msg => !msg.reply_to_id && (msg.thread_replies_count || 0) > 0).length;
    const totalReplies = messages.reduce((sum, msg) => sum + (msg.thread_replies_count || 0), 0);
    
    return { totalThreads, totalReplies };
  }, [messages]);

  // Navigate to a specific message in a thread
  const navigateToMessage = useCallback((navigation: ThreadNavigation) => {
    // This would typically scroll to or highlight the message
    // Implementation depends on how the parent component handles navigation
    console.log('Navigating to message:', navigation);
    
    // For now, just expand the thread and select it
    setThreadMode(prev => ({
      ...prev,
      expandedThreads: new Set(prev.expandedThreads).add(navigation.threadId),
      selectedThread: navigation.threadId
    }));
  }, []);

  // Thread expansion methods
  const expandThread = useCallback((threadId: string) => {
    setThreadMode(prev => ({
      ...prev,
      expandedThreads: new Set(prev.expandedThreads).add(threadId)
    }));
  }, []);

  const collapseThread = useCallback((threadId: string) => {
    setThreadMode(prev => {
      const newExpanded = new Set(prev.expandedThreads);
      newExpanded.delete(threadId);
      return {
        ...prev,
        expandedThreads: newExpanded
      };
    });
  }, []);

  const toggleThread = useCallback((threadId: string) => {
    setThreadMode(prev => {
      const newExpanded = new Set(prev.expandedThreads);
      if (newExpanded.has(threadId)) {
        newExpanded.delete(threadId);
      } else {
        newExpanded.add(threadId);
      }
      return {
        ...prev,
        expandedThreads: newExpanded
      };
    });
  }, []);

  const isThreadExpanded = useCallback((threadId: string) => {
    return threadMode.expandedThreads.has(threadId);
  }, [threadMode.expandedThreads]);

  // Memoized return object
  const returnValue = useMemo(() => ({
    threadMode,
    setThreadMode,
    loadThread,
    getThreadStats,
    navigateToMessage,
    expandThread,
    collapseThread,
    toggleThread,
    isThreadExpanded,
  }), [
    threadMode,
    setThreadMode,
    loadThread,
    getThreadStats,
    navigateToMessage,
    expandThread,
    collapseThread,
    toggleThread,
    isThreadExpanded,
  ]);

  return returnValue;
}

export default useThreads;