import React, { useState } from 'react';
import { Message, ThreadViewMode, ThreadNavigation } from '@/types/chat';
import MessageThread from './MessageThread';
import { QueueListIcon, ChatBubbleLeftRightIcon, XMarkIcon } from '@heroicons/react/24/outline';

interface ThreadViewProps {
  messages: Message[];
  currentUserId: string;
  threadMode: ThreadViewMode;
  onThreadModeChange: (mode: ThreadViewMode) => void;
  onLoadThread: (messageId: string) => Promise<Message[]>;
  onReactionToggle?: (messageId: string, emoji: string) => void;
  onReplyClick?: (message: Message) => void;
  onNavigateToMessage?: (navigation: ThreadNavigation) => void;
}

export default function ThreadView({
  messages,
  currentUserId,
  threadMode,
  onThreadModeChange,
  onLoadThread,
  onReactionToggle,
  onReplyClick,
  onNavigateToMessage
}: ThreadViewProps) {
  const [selectedThreadMessages, setSelectedThreadMessages] = useState<Message[]>([]);
  const [loadingThread, setLoadingThread] = useState(false);

  // Filter messages to show only thread roots when in threads-only mode
  const displayMessages = threadMode.showThreadsOnly 
    ? messages.filter(msg => !msg.reply_to_id && (msg.thread_replies_count || 0) > 0)
    : messages;

  const handleToggleThreadsOnly = () => {
    onThreadModeChange({
      ...threadMode,
      showThreadsOnly: !threadMode.showThreadsOnly,
      selectedThread: undefined
    });
  };

  const handleExpandThread = (threadId: string) => {
    const newExpandedThreads = new Set(threadMode.expandedThreads);
    
    if (newExpandedThreads.has(threadId)) {
      newExpandedThreads.delete(threadId);
    } else {
      newExpandedThreads.add(threadId);
    }

    onThreadModeChange({
      ...threadMode,
      expandedThreads: newExpandedThreads
    });
  };

  const handleSelectThread = async (threadId: string) => {
    if (threadMode.selectedThread === threadId) {
      // Deselect thread
      onThreadModeChange({
        ...threadMode,
        selectedThread: undefined
      });
      setSelectedThreadMessages([]);
      return;
    }

    setLoadingThread(true);
    try {
      const threadMessages = await onLoadThread(threadId);
      setSelectedThreadMessages(threadMessages);
      onThreadModeChange({
        ...threadMode,
        selectedThread: threadId
      });
    } catch (error) {
      console.error('Error loading thread:', error);
    } finally {
      setLoadingThread(false);
    }
  };

  const getThreadStats = () => {
    const totalThreads = messages.filter(msg => !msg.reply_to_id && (msg.thread_replies_count || 0) > 0).length;
    const totalReplies = messages.reduce((sum, msg) => sum + (msg.thread_replies_count || 0), 0);
    
    return { totalThreads, totalReplies };
  };

  const { totalThreads, totalReplies } = getThreadStats();

  if (threadMode.selectedThread) {
    // Show detailed thread view
    const rootMessage = selectedThreadMessages.find(msg => !msg.reply_to_id) || selectedThreadMessages[0];
    const replies = selectedThreadMessages.filter(msg => msg.reply_to_id);

    return (
      <div className="thread-detail-view h-full flex flex-col">
        {/* Thread Header */}
        <div className="thread-header p-4 border-b border-gray-200 bg-white">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <ChatBubbleLeftRightIcon className="h-5 w-5 text-blue-500" />
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Thread</h3>
                <p className="text-sm text-gray-500">
                  {replies.length} {replies.length === 1 ? 'reply' : 'replies'}
                </p>
              </div>
            </div>
            <button
              onClick={() => handleSelectThread(threadMode.selectedThread!)}
              className="p-1 hover:bg-gray-100 rounded-full transition-colors duration-200"
            >
              <XMarkIcon className="h-5 w-5 text-gray-500" />
            </button>
          </div>
        </div>

        {/* Thread Messages */}
        <div className="thread-messages flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
          {loadingThread ? (
            <div className="flex items-center justify-center h-full">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
          ) : (
            <>
              {/* Root Message */}
              {rootMessage && (
                <div className="root-message">
                  <div className="text-xs text-gray-500 mb-2 font-medium">Thread started</div>
                  <MessageThread
                    rootMessage={rootMessage}
                    currentUserId={currentUserId}
                    isExpanded={false}
                    onReactionToggle={onReactionToggle}
                    onReplyClick={onReplyClick}
                    onNavigateToMessage={onNavigateToMessage}
                  />
                </div>
              )}

              {/* Replies */}
              {replies.length > 0 && (
                <div className="thread-replies space-y-3 ml-4 border-l-2 border-blue-200 pl-4">
                  <div className="text-xs text-gray-500 font-medium">
                    {replies.length} {replies.length === 1 ? 'Reply' : 'Replies'}
                  </div>
                  {replies.map((message) => {
                    return (
                      <MessageThread
                        key={message.id}
                        rootMessage={message}
                        currentUserId={currentUserId}
                        isExpanded={false}
                        onReactionToggle={onReactionToggle}
                        onReplyClick={onReplyClick}
                        onNavigateToMessage={onNavigateToMessage}
                      />
                    );
                  })}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="thread-overview h-full flex flex-col">
      {/* Thread Controls */}
      <div className="thread-controls p-3 border-b border-gray-200 bg-white">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <button
              onClick={handleToggleThreadsOnly}
              className={`flex items-center space-x-2 px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-200 ${
                threadMode.showThreadsOnly
                  ? 'bg-blue-100 text-blue-700 border border-blue-200'
                  : 'text-gray-600 hover:bg-gray-100 border border-gray-200'
              }`}
            >
              <QueueListIcon className="h-4 w-4" />
              <span>Threads Only</span>
            </button>

            {totalThreads > 0 && (
              <div className="text-sm text-gray-500">
                {totalThreads} {totalThreads === 1 ? 'thread' : 'threads'} • {totalReplies} {totalReplies === 1 ? 'reply' : 'replies'}
              </div>
            )}
          </div>

          {threadMode.showThreadsOnly && (
            <div className="text-xs text-gray-500">
              Click a thread to view details
            </div>
          )}
        </div>
      </div>

      {/* Messages/Threads List */}
      <div className="messages-list flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
        {displayMessages.length === 0 ? (
          <div className="flex items-center justify-center h-full">
            <div className="text-center text-gray-500">
              <ChatBubbleLeftRightIcon className="mx-auto h-12 w-12 text-gray-400" />
              <h3 className="mt-4 text-lg font-medium text-gray-900">
                {threadMode.showThreadsOnly ? 'No threads found' : 'No messages'}
              </h3>
              <p className="mt-2 text-sm text-gray-500">
                {threadMode.showThreadsOnly 
                  ? 'Threads appear when messages have replies'
                  : 'Start a conversation to see messages here'
                }
              </p>
            </div>
          </div>
        ) : (
          displayMessages.map((message) => (
            <div 
              key={message.id} 
              className={threadMode.showThreadsOnly ? 'cursor-pointer' : ''}
              onClick={threadMode.showThreadsOnly ? () => handleSelectThread(message.id) : undefined}
            >
              <MessageThread
                rootMessage={message}
                currentUserId={currentUserId}
                isExpanded={threadMode.expandedThreads.has(message.id)}
                onToggleExpanded={() => handleExpandThread(message.id)}
                onLoadThread={onLoadThread}
                onReactionToggle={onReactionToggle}
                onReplyClick={onReplyClick}
                onNavigateToMessage={onNavigateToMessage}
              />
            </div>
          ))
        )}
      </div>
    </div>
  );
}