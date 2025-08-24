import React, { useState, useEffect, useCallback } from 'react';
import { Message, ThreadNavigation } from '@/types/chat';
import MessageBubble from './MessageBubble';
import { ChevronRightIcon, ChevronDownIcon, ChatBubbleLeftRightIcon } from '@heroicons/react/24/outline';
import { format } from 'date-fns';

interface MessageThreadProps {
  rootMessage: Message;
  currentUserId: string;
  isExpanded?: boolean;
  onToggleExpanded?: () => void;
  onLoadThread?: (messageId: string) => Promise<Message[]>;
  onReactionToggle?: (messageId: string, emoji: string) => void;
  onReplyClick?: (message: Message) => void;
  onNavigateToMessage?: (navigation: ThreadNavigation) => void;
}

export default function MessageThread({
  rootMessage,
  currentUserId,
  isExpanded = false,
  onToggleExpanded,
  onLoadThread,
  onReactionToggle,
  onReplyClick,
  onNavigateToMessage
}: MessageThreadProps) {
  const [threadMessages, setThreadMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const threadRepliesCount = rootMessage.thread_replies_count || 0;
  const hasReplies = threadRepliesCount > 0;

  const loadThreadMessages = useCallback(async () => {
    if (!onLoadThread) return;
    
    setLoading(true);
    setError(null);
    try {
      const messages = await onLoadThread(rootMessage.id);
      setThreadMessages(messages.filter(m => m.id !== rootMessage.id));
    } catch (err) {
      setError('Failed to load thread messages');
      console.error('Error loading thread:', err);
    } finally {
      setLoading(false);
    }
  }, [onLoadThread, rootMessage.id]);

  useEffect(() => {
    if (isExpanded && hasReplies && threadMessages.length === 0 && onLoadThread) {
      loadThreadMessages();
    }
  }, [isExpanded, hasReplies, threadMessages.length, onLoadThread, loadThreadMessages]);

  const handleNavigateToMessage = (message: Message) => {
    if (onNavigateToMessage) {
      onNavigateToMessage({
        threadId: rootMessage.id,
        messageId: message.id,
        position: message.id === rootMessage.id ? 'root' : 'reply'
      });
    }
  };

  const getThreadPreview = () => {
    if (threadMessages.length === 0) return null;
    
    const latestReply = threadMessages[threadMessages.length - 1];
    const previewContent = latestReply.content || '[Unable to decrypt message]';
    const truncatedContent = previewContent.length > 50 
      ? previewContent.substring(0, 50) + '...' 
      : previewContent;
    
    return (
      <div className="text-xs text-gray-500 mt-1">
        <span className="font-medium">{latestReply.sender?.name}</span>: {truncatedContent}
      </div>
    );
  };

  return (
    <div className="thread-container">
      {/* Root Message */}
      <div className="relative">
        <MessageBubble
          message={rootMessage}
          isOwnMessage={rootMessage.sender_id === currentUserId}
          showSender={true}
          currentUserId={currentUserId}
          onReactionToggle={onReactionToggle}
          onReplyClick={onReplyClick}
        />
        
        {/* Thread Indicator and Toggle */}
        {hasReplies && (
          <div className="mt-2 ml-12">
            <button
              onClick={onToggleExpanded}
              className="flex items-center space-x-2 text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200 group"
            >
              {isExpanded ? (
                <ChevronDownIcon className="h-4 w-4 transition-transform duration-200" />
              ) : (
                <ChevronRightIcon className="h-4 w-4 transition-transform duration-200" />
              )}
              <ChatBubbleLeftRightIcon className="h-4 w-4" />
              <span className="font-medium">
                {threadRepliesCount} {threadRepliesCount === 1 ? 'reply' : 'replies'}
              </span>
              {!isExpanded && threadMessages.length === 0 && (
                <span className="text-gray-500">• View thread</span>
              )}
            </button>
            
            {/* Thread Preview (when collapsed) */}
            {!isExpanded && threadMessages.length > 0 && (
              <div className="ml-6 mt-1">
                {getThreadPreview()}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Thread Replies (when expanded) */}
      {isExpanded && hasReplies && (
        <div className="thread-replies mt-3 ml-8 border-l-2 border-gray-200 pl-4">
          {loading ? (
            <div className="flex items-center justify-center py-4">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
              <span className="ml-2 text-sm text-gray-500">Loading thread...</span>
            </div>
          ) : error ? (
            <div className="text-red-500 text-sm py-2">
              {error}
              <button 
                onClick={loadThreadMessages}
                className="ml-2 text-blue-600 hover:text-blue-800 underline"
              >
                Retry
              </button>
            </div>
          ) : (
            <div className="space-y-3">
              {threadMessages.map((message, index) => {
                const prevMessage = index > 0 ? threadMessages[index - 1] : null;
                const showSender = !prevMessage || prevMessage.sender_id !== message.sender_id;
                
                return (
                  <div key={message.id} className="thread-reply">
                    <MessageBubble
                      message={message}
                      isOwnMessage={message.sender_id === currentUserId}
                      showSender={showSender}
                      currentUserId={currentUserId}
                      onReactionToggle={onReactionToggle}
                      onReplyClick={onReplyClick}
                    />
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Thread Actions Bar (when expanded) */}
      {isExpanded && (
        <div className="thread-actions mt-2 ml-12 flex items-center space-x-4 text-xs text-gray-500">
          <button
            onClick={() => handleNavigateToMessage(rootMessage)}
            className="hover:text-blue-600 transition-colors duration-200"
          >
            Jump to thread start
          </button>
          {threadMessages.length > 0 && (
            <button
              onClick={() => handleNavigateToMessage(threadMessages[threadMessages.length - 1])}
              className="hover:text-blue-600 transition-colors duration-200"
            >
              Latest reply
            </button>
          )}
          <span>
            Started {format(new Date(rootMessage.created_at), 'MMM d, h:mm a')}
          </span>
        </div>
      )}
    </div>
  );
}