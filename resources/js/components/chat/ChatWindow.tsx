import React, { useRef, useEffect, useState } from 'react';
import { User } from '@/types';
import { Conversation, Message, ThreadViewMode, ThreadNavigation } from '@/types/chat';
import MessageBubble from './MessageBubble';
import TiptapMessageEditor from './TiptapMessageEditor';
import TypingIndicator from './TypingIndicator';
import ThreadView from './ThreadView';
import { format } from 'date-fns';
import { ShieldCheckIcon, UserIcon, ChatBubbleLeftRightIcon, CogIcon } from '@heroicons/react/24/outline';
import GroupSettings from './GroupSettings';
import SignalMessageComposer from '@/components/ui/signal-message-composer';
import SignalProtocolStatus from '@/components/ui/signal-protocol-status';
import IdentityVerificationDialog from '@/components/ui/identity-verification-dialog';
import { useE2EE } from '@/hooks/useE2EE';
import type { MessageDeliveryOptions } from '@/services/SignalSessionManager';

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

  // Signal Protocol integration
  const {
    isSignalEnabled,
    sessionInfo,
    signalStatistics,
    healthScore,
    establishSignalSession,
    verifyUserIdentity,
    rotateKeys,
    refreshStatistics
  } = useE2EE();

  const [showSignalStatus, setShowSignalStatus] = useState(false);
  const [showIdentityVerification, setShowIdentityVerification] = useState(false);
  const [useSignalComposer, setUseSignalComposer] = useState(false);

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

  // Signal Protocol message sending
  const handleSignalMessage = async (content: string, options: MessageDeliveryOptions & { useSignal: boolean }) => {
    try {
      if (options.useSignal && isSignalEnabled) {
        // Establish session if needed
        const otherParticipant = conversation.participants?.find(p => p.user_id !== currentUser.id);
        if (otherParticipant) {
          await establishSignalSession(otherParticipant.user_id.toString(), otherParticipant.user_id.toString());
        }
      }
      
      await onSendMessage(content, {
        ...options,
        signalProtocol: options.useSignal,
        messageId: crypto.randomUUID()
      });
    } catch (error) {
      console.error('Failed to send Signal message:', error);
      throw error;
    }
  };

  // Check if we should automatically enable Signal Protocol
  useEffect(() => {
    if (conversation && isSignalEnabled && conversation.type === 'direct') {
      setUseSignalComposer(true);
    }
  }, [conversation, isSignalEnabled]);

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
                {isSignalEnabled && sessionInfo ? 'Signal Protocol' : encryptionReady ? 'End-to-end encrypted' : 'Encryption not available'} • {getConversationSubtitle()}
                {isSignalEnabled && sessionInfo && (
                  <button
                    onClick={() => setShowSignalStatus(true)}
                    className="ml-2 text-xs text-blue-600 hover:text-blue-800 underline"
                  >
                    View Status
                  </button>
                )}
              </p>
            </div>
          </div>
          
          <div className="flex items-center space-x-3">
            {isSignalEnabled && sessionInfo?.verificationStatus === 'unverified' && (
              <button
                onClick={() => setShowIdentityVerification(true)}
                className="px-3 py-1 bg-yellow-100 text-yellow-800 border border-yellow-200 rounded-full text-xs font-medium hover:bg-yellow-200"
                title="Verify identity for enhanced security"
              >
                Verify Identity
              </button>
            )}
            
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
      {useSignalComposer && isSignalEnabled ? (
        <SignalMessageComposer
          conversationId={conversation.id}
          recipientUserId={conversation.type === 'direct' ? 
            conversation.participants?.find(p => p.user_id !== currentUser.id)?.user_id.toString() 
            : undefined
          }
          isSignalEnabled={isSignalEnabled}
          sessionVerified={sessionInfo?.verificationStatus === 'verified'}
          onSendMessage={handleSignalMessage}
          placeholder="Type a message..."
          disabled={!encryptionReady}
        />
      ) : (
        <TiptapMessageEditor 
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
      )}

      {/* Signal Protocol Status Dialog */}
      {showSignalStatus && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onClick={() => setShowSignalStatus(false)}>
          <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold">Signal Protocol Status</h3>
              <button
                onClick={() => setShowSignalStatus(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                ×
              </button>
            </div>
            <SignalProtocolStatus
              sessionInfo={sessionInfo}
              statistics={signalStatistics}
              healthScore={healthScore}
              onVerifyIdentity={() => {
                setShowSignalStatus(false);
                setShowIdentityVerification(true);
              }}
              onRotateKeys={rotateKeys}
              onRefreshStats={refreshStatistics}
            />
          </div>
        </div>
      )}

      {/* Identity Verification Dialog */}
      {showIdentityVerification && sessionInfo && (
        <IdentityVerificationDialog
          isOpen={showIdentityVerification}
          onOpenChange={setShowIdentityVerification}
          sessionInfo={sessionInfo}
          remoteUserName={
            conversation.type === 'direct'
              ? conversation.participants?.find(p => p.user_id !== currentUser.id)?.user?.name || 'Unknown'
              : 'Group Member'
          }
          localFingerprint={sessionInfo.localFingerprint || ''}
          remoteFingerprint={sessionInfo.remoteFingerprint || ''}
          onVerifyIdentity={async (fingerprint: string, method: string) => {
            try {
              const otherParticipant = conversation.participants?.find(p => p.user_id !== currentUser.id);
              if (otherParticipant) {
                const result = await verifyUserIdentity(otherParticipant.user_id.toString(), fingerprint);
                return result;
              }
              return false;
            } catch (error) {
              console.error('Identity verification failed:', error);
              return false;
            }
          }}
        />
      )}
    </div>
  );
}