import React, { useState } from 'react';
import { ReactionSummary } from '@/types/chat';
import { PlusIcon } from '@heroicons/react/24/outline';

interface MessageReactionsProps {
  messageId: string;
  reactions: ReactionSummary[];
  currentUserId: string;
  onReactionToggle: (messageId: string, emoji: string) => void;
  className?: string;
}

const QUICK_REACTIONS = ['👍', '❤️', '😄', '😮', '😢', '😡'];

export default function MessageReactions({
  messageId,
  reactions,
  currentUserId,
  onReactionToggle,
  className = ''
}: MessageReactionsProps) {
  const [showEmojiPicker, setShowEmojiPicker] = useState(false);

  const handleReactionClick = (emoji: string) => {
    onReactionToggle(messageId, emoji);
  };

  const handleAddReaction = (emoji: string) => {
    handleReactionClick(emoji);
    setShowEmojiPicker(false);
  };

  const hasUserReacted = (reaction: ReactionSummary): boolean => {
    return reaction.users.some(user => user.id === currentUserId);
  };

  const getReactionTooltip = (reaction: ReactionSummary): string => {
    if (reaction.count === 1) {
      return reaction.users[0].name;
    }
    
    const names = reaction.users.slice(0, 3).map(user => user.name);
    if (reaction.count > 3) {
      return `${names.join(', ')} and ${reaction.count - 3} others`;
    }
    
    return names.join(', ');
  };

  if (reactions.length === 0) {
    return null;
  }

  return (
    <div className={`flex flex-wrap items-center gap-1 mt-1 ${className}`}>
      {reactions.map((reaction) => (
        <button
          key={reaction.emoji}
          onClick={() => handleReactionClick(reaction.emoji)}
          className={`
            inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
            transition-colors duration-200 hover:bg-gray-100 active:scale-95
            ${hasUserReacted(reaction)
              ? 'bg-blue-100 text-blue-700 border border-blue-200'
              : 'bg-gray-50 text-gray-700 border border-gray-200'
            }
          `}
          title={getReactionTooltip(reaction)}
        >
          <span className="text-sm mr-1">{reaction.emoji}</span>
          <span>{reaction.count}</span>
        </button>
      ))}
      
      <div className="relative">
        <button
          onClick={() => setShowEmojiPicker(!showEmojiPicker)}
          className="
            inline-flex items-center justify-center w-6 h-6 rounded-full
            bg-gray-50 hover:bg-gray-100 border border-gray-200
            transition-colors duration-200 active:scale-95
          "
          title="Add reaction"
        >
          <PlusIcon className="h-3 w-3 text-gray-500" />
        </button>
        
        {showEmojiPicker && (
          <>
            <div
              className="fixed inset-0 z-10"
              onClick={() => setShowEmojiPicker(false)}
            />
            <div className="
              absolute bottom-full left-0 mb-2 z-20
              bg-white border border-gray-200 rounded-lg shadow-lg p-2
              grid grid-cols-6 gap-1
            ">
              {QUICK_REACTIONS.map((emoji) => (
                <button
                  key={emoji}
                  onClick={() => handleAddReaction(emoji)}
                  className="
                    w-8 h-8 rounded hover:bg-gray-100
                    flex items-center justify-center text-lg
                    transition-colors duration-200 active:scale-95
                  "
                  title={`React with ${emoji}`}
                >
                  {emoji}
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}