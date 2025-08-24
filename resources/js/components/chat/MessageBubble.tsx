import React from 'react';
import { Message, ReactionSummary } from '@/types/chat';
import { format } from 'date-fns';
import { CheckCircleIcon, ClockIcon, XCircleIcon, LockClosedIcon, ArrowUturnLeftIcon } from '@heroicons/react/24/outline';
import { ExclamationTriangleIcon, ClockIcon as ClockSolid } from '@heroicons/react/24/solid';
import { Shield, ShieldCheck, ShieldAlert } from 'lucide-react';
import MessageReactions from './MessageReactions';
import VoiceMessagePlayer from './VoiceMessagePlayer';
import { E2EEStatusIndicator } from './E2EEStatusIndicator';

interface MessageBubbleProps {
  message: Message;
  isOwnMessage: boolean;
  showSender: boolean;
  isEncrypted?: boolean;
  encryptionVerified?: boolean;
  currentUserId: string;
  onReactionToggle?: (messageId: string, emoji: string) => void;
  onReplyClick?: (message: Message) => void;
}

export default function MessageBubble({
  message,
  isOwnMessage,
  showSender,
  isEncrypted = false,
  encryptionVerified = false,
  currentUserId,
  onReactionToggle,
  onReplyClick
}: MessageBubbleProps) {
  const getStatusIcon = () => {
    switch (message.status) {
      case 'sent':
        return <ClockIcon className="h-4 w-4 text-gray-400" />;
      case 'delivered':
        return <CheckCircleIcon className="h-4 w-4 text-gray-400" />;
      case 'read':
        return <CheckCircleIcon className="h-4 w-4 text-blue-500" />;
      case 'failed':
        return <XCircleIcon className="h-4 w-4 text-red-500" />;
      case 'scheduled':
        return <ClockSolid className="h-4 w-4 text-orange-500" />;
      default:
        return null;
    }
  };

  const getPriorityIndicator = () => {
    switch (message.message_priority) {
      case 'high':
        return <ExclamationTriangleIcon className="h-3 w-3 text-orange-500" title="High Priority" />;
      case 'urgent':
        return <ExclamationTriangleIcon className="h-3 w-3 text-red-500 animate-pulse" title="Urgent" />;
      default:
        return null;
    }
  };

  const getEncryptionIndicator = () => {
    if (!isEncrypted) {
      return (
        <ShieldAlert 
          className="h-3 w-3 text-yellow-500" 
          title="This message is not encrypted"
        />
      );
    }

    if (encryptionVerified) {
      return (
        <ShieldCheck 
          className="h-3 w-3 text-green-500" 
          title="Message verified with end-to-end encryption"
        />
      );
    }

    return (
      <Shield 
        className="h-3 w-3 text-blue-500" 
        title="End-to-end encrypted"
      />
    );
  };

  const handleReactionToggle = (messageId: string, emoji: string) => {
    onReactionToggle?.(messageId, emoji);
  };

  const isScheduledMessage = message.status === 'scheduled';
  const isVoiceMessage = message.type === 'voice';
  
  // Mock reactions data - in real app this would come from the message
  const mockReactions: ReactionSummary[] = message.reactions ? 
    Object.entries(
      message.reactions.reduce((acc, reaction) => {
        if (!acc[reaction.emoji]) {
          acc[reaction.emoji] = { emoji: reaction.emoji, count: 0, users: [] };
        }
        acc[reaction.emoji].count++;
        acc[reaction.emoji].users.push({
          id: reaction.user?.id || reaction.user_id,
          name: reaction.user?.name || 'Unknown',
          reacted_at: reaction.created_at
        });
        return acc;
      }, {} as Record<string, ReactionSummary>)
    ).map(([, reaction]) => reaction) : [];

  return (
    <div className={`flex ${isOwnMessage ? 'justify-end' : 'justify-start'} mb-1`} data-testid="message-bubble">
      <div className={`max-w-xs lg:max-w-md ${isOwnMessage ? 'order-2' : 'order-1'}`}>
        {/* Reply indicator */}
        {message.reply_to && (
          <div className={`text-xs px-3 mb-1 ${isOwnMessage ? 'text-blue-200' : 'text-gray-500'}`}>
            <div className="flex items-center space-x-1">
              <ArrowUturnLeftIcon className="h-3 w-3" />
              <span>Replying to {message.reply_to.sender?.name || 'Unknown'}</span>
            </div>
            <div className={`truncate mt-1 italic ${isOwnMessage ? 'text-blue-100' : 'text-gray-400'}`}>
              {message.reply_to.content || '[Message could not be decrypted]'}
            </div>
          </div>
        )}

        {/* Sender name */}
        {showSender && !isOwnMessage && (
          <div className="text-xs text-gray-500 mb-1 px-3 flex items-center space-x-1">
            <span>{message.sender?.name || 'Unknown User'}</span>
            {getPriorityIndicator()}
          </div>
        )}
        
        {/* Message bubble */}
        <div
          className={`px-4 py-2 rounded-2xl ${
            isScheduledMessage
              ? 'bg-orange-100 text-orange-900 border border-orange-200'
              : isOwnMessage
                ? 'bg-blue-500 text-white rounded-br-md'
                : 'bg-white text-gray-900 border border-gray-200 rounded-bl-md'
          } shadow-sm relative group`}
        >
          {/* Priority indicator for own messages */}
          {isOwnMessage && getPriorityIndicator() && (
            <div className="absolute -top-1 -right-1">
              {getPriorityIndicator()}
            </div>
          )}

          {/* Scheduled message indicator */}
          {isScheduledMessage && (
            <div className="flex items-center space-x-1 text-orange-600 text-xs mb-2">
              <ClockSolid className="h-3 w-3" />
              <span>Scheduled for {format(new Date(message.scheduled_at!), 'MMM d, h:mm a')}</span>
            </div>
          )}

          {/* Message content */}
          {isVoiceMessage ? (
            <VoiceMessagePlayer message={message} />
          ) : (
            <div className="break-words">
              {message.content || '[Message could not be decrypted]'}
            </div>
          )}

          {/* Reply button (appears on hover) */}
          {!isScheduledMessage && onReplyClick && (
            <button
              onClick={() => onReplyClick(message)}
              className={`
                absolute -top-2 ${isOwnMessage ? '-left-8' : '-right-8'}
                opacity-0 group-hover:opacity-100 transition-opacity duration-200
                bg-white border border-gray-200 rounded-full p-1.5 shadow-md
                hover:bg-gray-50 active:scale-95
              `}
              title="Reply to message"
            >
              <ArrowUturnLeftIcon className="h-3 w-3 text-gray-500" />
            </button>
          )}
          
          {/* Message metadata */}
          <div className={`flex items-center justify-between mt-1 text-xs ${
            isOwnMessage ? 'text-blue-100' : 'text-gray-500'
          }`}>
            <span>{format(new Date(message.created_at), 'HH:mm')}</span>
            
            <div className="flex items-center space-x-1">
              {getEncryptionIndicator()}
              {message.is_edited && (
                <span className="italic">edited</span>
              )}
              {isOwnMessage && getStatusIcon()}
            </div>
          </div>
        </div>
        
        {/* Message reactions */}
        {onReactionToggle && (
          <MessageReactions
            messageId={message.id}
            reactions={mockReactions}
            currentUserId={currentUserId}
            onReactionToggle={handleReactionToggle}
            className={`${isOwnMessage ? 'justify-end' : 'justify-start'}`}
          />
        )}
      </div>
    </div>
  );
}