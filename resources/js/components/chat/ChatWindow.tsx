import React, { useRef, useEffect, useState } from 'react';
import { User } from '@/types';
import { Conversation, Message, ThreadViewMode, ThreadNavigation } from '@/types/chat';
import MessageBubble from './MessageBubble';
import MessageInput from './MessageInput';
import TypingIndicator from './TypingIndicator';
import ThreadView from './ThreadView';
import { format } from 'date-fns';
import { ShieldCheckIcon, UserIcon, ChatBubbleLeftRightIcon, CogIcon } from '@heroicons/react/24/outline';
import GroupSettings from './GroupSettings';

interface ChatWindowProps {
  conversation: Conversation;
  messages: Message[];
  onSendMessage: (content: string, options?: Record<string, unknown>) => Promise<void>;
  currentUser: User;
  loading: boolean;
  encryptionReady?: boolean;
  onReactionToggle?: (messageId: string, emoji: string) => void;
  onReplyClick?: (message: Message | null) => void;
  onLoadThread?: (messageId: string) => Promise<Message[]>;
  onNavigateToMessage?: (navigation: ThreadNavigation) => void;
  typingUsers?: Array<{ id: string; name: string }>;
  replyingTo?: Message | null;
  // Group management functions
  onUpdateGroupSettings?: (settings: any) => Promise<void>;
  onUpdateParticipantRole?: (userId: string, role: 'admin' | 'member') => Promise<void>;
  onRemoveParticipant?: (userId: string) => Promise<void>;
  onGenerateInviteLink?: (options: { expires_at?: string; max_uses?: number }) => Promise<{ invite_url: string }>;
}

export default function ChatWindow({
  conversation,
  messages,
  onSendMessage,
  currentUser,
  loading,
  encryptionReady = false,
  onReactionToggle,
  onReplyClick,
  onLoadThread,
  onNavigateToMessage,
  typingUsers = [],
  replyingTo = null,
  onUpdateGroupSettings,
  onUpdateParticipantRole,
  onRemoveParticipant,
  onGenerateInviteLink
}: ChatWindowProps) {
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  
  // Thread view state
  const [showThreadView, setShowThreadView] = useState(false);
  const [threadMode, setThreadMode] = useState<ThreadViewMode>({
    showThreadsOnly: false,
    expandedThreads: new Set(),
    selectedThread: undefined,
  });

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const getConversationName = () => {
    if (conversation.name) return conversation.name;
    
    if (conversation.type === 'direct') {
      const otherParticipant = conversation.participants?.find(
        p => p.user_id !== currentUser.id
      );
      return otherParticipant?.user?.name || 'Unknown User';
    }
    
    return `Group (${conversation.participants?.length || 0} members)`;
  };

  const getConversationSubtitle = () => {
    if (conversation.type === 'direct') {
      const otherParticipant = conversation.participants?.find(
        p => p.user_id !== currentUser.id
      );
      return otherParticipant?.user?.email || '';
    }
    
    const memberNames = conversation.participants
      ?.filter(p => p.user?.name)
      .map(p => p.user!.name)
      .join(', ');
    
    return memberNames || `${conversation.participants?.length || 0} members`;
  };

  // Group messages by date
  const groupedMessages = messages.reduce((groups, message) => {
    const date = format(new Date(message.created_at), 'yyyy-MM-dd');
    if (!groups[date]) {
      groups[date] = [];
    }
    groups[date].push(message);
    return groups;
  }, {} as Record<string, Message[]>);

  const hasThreads = messages.some(msg => !msg.reply_to_id && (msg.thread_replies_count || 0) > 0);

  const handleThreadModeChange = (mode: ThreadViewMode) => {
    setThreadMode(mode);
  };

  const handleToggleThreadView = () => {
    setShowThreadView(!showThreadView);
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="p-4 border-b border-gray-200 bg-white">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
              {conversation.type === 'direct' ? (
                conversation.participants?.find(p => p.user_id !== currentUser.id)?.user?.name?.charAt(0).toUpperCase() || '?'
              ) : (
                '#'
              )}
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">
                {getConversationName()}
              </h2>
              <p className="text-sm text-gray-500 flex items-center">
                <ShieldCheckIcon className={`h-4 w-4 mr-1 ${encryptionReady ? 'text-green-500' : 'text-gray-400'}`} />
                {encryptionReady ? 'End-to-end encrypted' : 'Encryption not available'} • {getConversationSubtitle()}
              </p>
            </div>
          </div>
          
          <div className="flex items-center space-x-3">
            {hasThreads && (
              <button
                onClick={handleToggleThreadView}
                className={`p-2 rounded-lg transition-colors duration-200 ${
                  showThreadView
                    ? 'bg-blue-100 text-blue-700 border border-blue-200'
                    : 'text-gray-600 hover:bg-gray-100 border border-gray-200'
                }`}
                title={showThreadView ? 'Show all messages' : 'Show threads'}
              >
                <ChatBubbleLeftRightIcon className="h-5 w-5" />
              </button>
            )}

            {conversation.type === 'group' && (
              <>
                <div className="flex -space-x-2">
                  {conversation.participants?.slice(0, 3).map((participant) => (
                    <div
                      key={participant.id}
                      className="h-8 w-8 bg-gray-300 rounded-full flex items-center justify-center text-xs font-medium border-2 border-white"
                      title={participant.user?.name}
                    >
                      {participant.user?.name?.charAt(0).toUpperCase() || '?'}
                    </div>
                  ))}
                  {(conversation.participants?.length || 0) > 3 && (
                    <div className="h-8 w-8 bg-gray-500 rounded-full flex items-center justify-center text-xs font-medium text-white border-2 border-white">
                      +{(conversation.participants?.length || 0) - 3}
                    </div>
                  )}
                </div>
                
                {onUpdateGroupSettings && onUpdateParticipantRole && onRemoveParticipant && onGenerateInviteLink && (
                  <GroupSettings
                    conversation={conversation}
                    participants={conversation.participants || []}
                    currentUser={currentUser}
                    onUpdateSettings={onUpdateGroupSettings}
                    onUpdateParticipantRole={onUpdateParticipantRole}
                    onRemoveParticipant={onRemoveParticipant}
                    onGenerateInviteLink={onGenerateInviteLink}
                    trigger={
                      <button className="p-2 rounded-lg text-gray-600 hover:bg-gray-100 border border-gray-200">
                        <CogIcon className="h-5 w-5" />
                      </button>
                    }
                  />
                )}
              </>
            )}
          </div>
        </div>
      </div>

      {/* Messages */}
      <div
        ref={messagesContainerRef}
        className="flex-1 overflow-y-auto bg-gray-50"
      >
        {showThreadView ? (
          <ThreadView
            messages={messages}
            currentUserId={currentUser.id}
            threadMode={threadMode}
            onThreadModeChange={handleThreadModeChange}
            onLoadThread={onLoadThread || (async () => [])}
            onReactionToggle={onReactionToggle}
            onReplyClick={onReplyClick}
            onNavigateToMessage={onNavigateToMessage}
          />
        ) : (
          <div className="p-4 space-y-4">
            {loading && messages.length === 0 ? (
              <div className="flex items-center justify-center h-full">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
              </div>
            ) : (
              Object.entries(groupedMessages).map(([date, dateMessages]) => (
                <div key={date}>
                  {/* Date separator */}
                  <div className="flex items-center justify-center py-2">
                    <div className="bg-white px-3 py-1 rounded-full text-xs text-gray-500 border">
                      {format(new Date(date), 'MMMM d, yyyy')}
                    </div>
                  </div>
                  
                  {/* Messages for this date */}
                  <div className="space-y-2">
                    {dateMessages.map((message, index) => {
                      const prevMessage = index > 0 ? dateMessages[index - 1] : null;
                      const showSender = !prevMessage || prevMessage.sender_id !== message.sender_id;
                      
                      return (
                        <MessageBubble
                          key={message.id}
                          message={message}
                          isOwnMessage={message.sender_id === currentUser.id}
                          showSender={showSender}
                          isEncrypted={!!message.encrypted_content}
                          encryptionVerified={encryptionReady && !!message.encrypted_content}
                          currentUserId={currentUser.id}
                          participants={conversation.participants || []}
                          onReactionToggle={onReactionToggle}
                          onReplyClick={onReplyClick}
                        />
                      );
                    })}
                  </div>
                </div>
              ))
            )}
            
            {messages.length === 0 && !loading && (
              <div className="flex items-center justify-center h-full">
                <div className="text-center text-gray-500">
                  <UserIcon className="mx-auto h-12 w-12 text-gray-400" />
                  <h3 className="mt-4 text-lg font-medium text-gray-900">Start the conversation</h3>
                  <p className="mt-2 text-sm text-gray-500">Send a message to begin this end-to-end encrypted conversation</p>
                </div>
              </div>
            )}
            
            {/* Typing Indicator */}
            {typingUsers.length > 0 && (
              <TypingIndicator users={typingUsers} />
            )}
            
            <div ref={messagesEndRef} />
          </div>
        )}
      </div>

      {/* Message Input */}
      <MessageInput 
        onSendMessage={onSendMessage}
        replyingTo={replyingTo}
        onCancelReply={() => onReplyClick?.(null)}
        onTyping={async (isTyping: boolean) => {
          console.log('Typing:', isTyping);
        }}
        encryptionReady={encryptionReady}
        disabled={!encryptionReady}
        participants={conversation.participants || []}
        currentUserId={currentUser.id}
      />
    </div>
  );
}