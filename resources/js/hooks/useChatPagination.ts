import { useState, useCallback, useRef } from 'react';
import { Message } from '@/types/chat';

interface UseChatPaginationReturn {
  messages: Message[];
  loading: boolean;
  hasMore: boolean;
  loadMore: () => Promise<void>;
  addMessage: (message: Message) => void;
  updateMessage: (messageId: string, updates: Partial<Message>) => void;
  removeMessage: (messageId: string) => void;
  reset: () => void;
}

export function useChatPagination(conversationId: string): UseChatPaginationReturn {
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const loadingRef = useRef(false);

  const loadMore = useCallback(async () => {
    if (loadingRef.current || !hasMore || !conversationId) return;
    
    loadingRef.current = true;
    setLoading(true);

    try {
      const before = messages.length > 0 ? messages[0].created_at : undefined;
      const params = new URLSearchParams();
      if (before) params.set('before', before);
      params.set('limit', '50');

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(`/api/v1/chat/conversations/${conversationId}/messages?${params}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Failed to load messages');
      }

      const result = await response.json();
      const newMessages = result.data || [];

      setMessages(prev => [...newMessages.reverse(), ...prev]);
      setHasMore(result.meta?.has_more || false);
    } catch (error) {
      console.error('Error loading messages:', error);
    } finally {
      setLoading(false);
      loadingRef.current = false;
    }
  }, [conversationId, messages, hasMore]);

  const addMessage = useCallback((message: Message) => {
    setMessages(prev => {
      // Avoid duplicates
      if (prev.some(m => m.id === message.id)) {
        return prev;
      }
      return [...prev, message];
    });
  }, []);

  const updateMessage = useCallback((messageId: string, updates: Partial<Message>) => {
    setMessages(prev => 
      prev.map(message => 
        message.id === messageId 
          ? { ...message, ...updates }
          : message
      )
    );
  }, []);

  const removeMessage = useCallback((messageId: string) => {
    setMessages(prev => prev.filter(message => message.id !== messageId));
  }, []);

  const reset = useCallback(() => {
    setMessages([]);
    setHasMore(true);
    setLoading(false);
    loadingRef.current = false;
  }, []);

  return {
    messages,
    loading,
    hasMore,
    loadMore,
    addMessage,
    updateMessage,
    removeMessage,
    reset,
  };
}