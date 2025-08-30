import React from 'react';
import { User, MessageMention, Participant } from '@/types/chat';

export interface ParsedMention {
  start: number;
  end: number;
  userId: string;
  displayName: string;
}

export interface MentionSuggestion {
  user: User;
  participant: Participant;
  relevanceScore: number;
}

/**
 * Parse mentions from text content
 * Supports formats: @username, @"Display Name", @user:id
 */
export function parseMentions(content: string): ParsedMention[] {
  const mentions: ParsedMention[] = [];
  
  // Pattern to match @mentions in various formats
  const mentionRegex = /@(?:([a-zA-Z0-9_.-]+)|"([^"]+)"|user:([a-zA-Z0-9-]+))/g;
  
  let match;
  while ((match = mentionRegex.exec(content)) !== null) {
    const [fullMatch, username, displayName, userId] = match;
    const start = match.index;
    const end = start + fullMatch.length;
    
    mentions.push({
      start,
      end,
      userId: userId || extractUserIdFromUsername(username || displayName),
      displayName: displayName || username || userId || 'Unknown User'
    });
  }
  
  return mentions;
}

/**
 * Convert parsed mentions to MessageMention format
 */
export function convertToMessageMentions(
  parsedMentions: ParsedMention[], 
  participants: Participant[]
): MessageMention[] {
  return parsedMentions.map((mention, index) => {
    const participant = participants.find(p => 
      p.user?.id === mention.userId || 
      p.user?.name === mention.displayName
    );
    
    return {
      id: `mention-${index}`,
      user_id: participant?.user?.id || mention.userId,
      start_position: mention.start,
      end_position: mention.end,
      display_name: participant?.user?.name || mention.displayName,
      user: participant?.user
    };
  });
}

/**
 * Render message content with highlighted mentions
 */
export function renderMessageWithMentions(
  content: string, 
  mentions?: MessageMention[],
  currentUserId?: string
): (string | React.ReactElement)[] {
  if (!mentions || mentions.length === 0) {
    return [content];
  }

  const result: (string | React.ReactElement)[] = [];
  let currentIndex = 0;

  // Sort mentions by position to process in order
  const sortedMentions = [...mentions].sort((a, b) => a.start_position - b.start_position);

  sortedMentions.forEach((mention, index) => {
    // Add text before mention
    if (currentIndex < mention.start_position) {
      result.push(content.slice(currentIndex, mention.start_position));
    }

    // Add mention element
    const isMentioningCurrentUser = mention.user_id === currentUserId;
    const mentionText = content.slice(mention.start_position, mention.end_position);
    
    result.push(
      <span
        key={`mention-${index}`}
        className={`px-1 py-0.5 rounded text-sm font-medium ${
          isMentioningCurrentUser
            ? 'bg-blue-100 text-blue-800 border border-blue-300'
            : 'bg-gray-100 text-gray-800 border border-gray-300'
        }`}
        title={mention.user?.name || mention.display_name}
      >
        {mentionText}
      </span>
    );

    currentIndex = mention.end_position;
  });

  // Add remaining text after last mention
  if (currentIndex < content.length) {
    result.push(content.slice(currentIndex));
  }

  return result;
}

/**
 * Get mention suggestions based on input
 */
export function getMentionSuggestions(
  query: string, 
  participants: Participant[], 
  currentUserId: string
): MentionSuggestion[] {
  const normalizedQuery = query.toLowerCase();
  
  const suggestions = participants
    .filter(p => p.user && p.user_id !== currentUserId)
    .map(participant => {
      if (!participant.user) return null;
      
      const user = participant.user;
      const nameMatch = user.name.toLowerCase().includes(normalizedQuery);
      const emailMatch = user.email?.toLowerCase().includes(normalizedQuery);
      
      let relevanceScore = 0;
      if (nameMatch) {
        relevanceScore += user.name.toLowerCase().startsWith(normalizedQuery) ? 100 : 50;
      }
      if (emailMatch) {
        relevanceScore += 25;
      }
      
      if (relevanceScore > 0) {
        return {
          user,
          participant,
          relevanceScore
        };
      }
      return null;
    })
    .filter((suggestion): suggestion is MentionSuggestion => suggestion !== null)
    .sort((a, b) => b.relevanceScore! - a.relevanceScore!)
    .slice(0, 10); // Limit to top 10 suggestions
    
  return suggestions;
}

/**
 * Insert mention into text at cursor position
 */
export function insertMention(
  text: string, 
  cursorPosition: number, 
  user: User, 
  queryStart: number
): { newText: string; newCursorPosition: number } {
  const mentionText = `@"${user.name}"`;
  const beforeMention = text.slice(0, queryStart);
  const afterCursor = text.slice(cursorPosition);
  
  const newText = beforeMention + mentionText + afterCursor;
  const newCursorPosition = queryStart + mentionText.length;
  
  return { newText, newCursorPosition };
}

/**
 * Find current mention query at cursor position
 */
export function getCurrentMentionQuery(
  text: string, 
  cursorPosition: number
): { query: string; start: number } | null {
  // Find the last @ before cursor position
  let atPosition = -1;
  for (let i = cursorPosition - 1; i >= 0; i--) {
    if (text[i] === '@') {
      atPosition = i;
      break;
    }
    if (text[i] === ' ' || text[i] === '\n') {
      break; // Stop if we hit whitespace before @
    }
  }
  
  if (atPosition === -1) return null;
  
  // Extract query from @ to cursor
  const query = text.slice(atPosition + 1, cursorPosition);
  
  // Validate query (no spaces unless quoted)
  if (query.includes(' ') && !query.startsWith('"')) {
    return null;
  }
  
  return {
    query: query.replace(/^"/, '').replace(/"$/, ''), // Remove quotes for matching
    start: atPosition
  };
}

/**
 * Extract user ID from username (placeholder - would integrate with user lookup)
 */
function extractUserIdFromUsername(username: string): string {
  // In a real implementation, this would lookup the user by username
  // For now, return the username as ID
  return username;
}

/**
 * Check if a message mentions the current user
 */
export function messageHasMentionForUser(message: { mentions?: MessageMention[] }, userId: string): boolean {
  return message.mentions?.some(mention => mention.user_id === userId) || false;
}

/**
 * Get users mentioned in a message
 */
export function getMentionedUsers(message: { mentions?: MessageMention[] }): User[] {
  return message.mentions?.map(mention => mention.user).filter(Boolean) as User[] || [];
}