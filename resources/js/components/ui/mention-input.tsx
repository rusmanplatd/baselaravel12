import React, { useState, useRef, useCallback, useEffect, useMemo } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
  AtSign,
  Hash,
  Users,
  Shield,
  ShieldCheck,
  Wifi,
  WifiOff,
  Clock,
  Crown,
  UserCheck
} from 'lucide-react';
import { useMentions } from '@/hooks/useMentions';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

interface MentionInputProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  conversationId?: string;
  organizationId?: string;
  onMention?: (userId: string, userName: string) => void;
  className?: string;
  disabled?: boolean;
  enableQuantumEncryption?: boolean;
  maxLength?: number;
}

interface MentionSuggestion {
  id: string;
  name: string;
  type: 'user' | 'channel' | 'group';
  avatar?: string;
  online?: boolean;
  quantum_ready?: boolean;
  memberCount?: number;
  roles?: string[];
  device_status?: {
    quantum_ready: boolean;
    last_seen: string;
    encryption_preference: string;
  };
}

export function MentionInput({
  value,
  onChange,
  placeholder = "Type a message...57",
  conversationId,
  organizationId,
  onMention,
  className,
  disabled,
  enableQuantumEncryption = true,
  maxLength = 10000,
}: MentionInputProps) {
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [currentMention, setCurrentMention] = useState<{
    trigger: string;
    query: string;
    start: number;
    end: number;
  } | null>(null);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [filteredSuggestions, setFilteredSuggestions] = useState<MentionSuggestion[]>([]);

  const inputRef = useRef<HTMLTextAreaElement>(null);
  const suggestionsRef = useRef<HTMLDivElement>(null);

  const {
    users,
    channels,
    groups,
    recentMentions,
    loading,
    searchMentions,
    createEncryptedMention,
    quantumEnabled,
    getQuantumReadyUsers,
  } = useMentions({
    conversationId,
    organizationId,
    enableQuantumEncryption,
  });

  // Combine all mention sources
  const allSuggestions = useMemo(() => {
    const suggestions: MentionSuggestion[] = [];

    // Add users
    users.forEach(user => {
      suggestions.push({
        id: user.id,
        name: user.name,
        type: 'user',
        avatar: user.avatar,
        online: user.online,
        quantum_ready: user.device_status?.quantum_ready,
        roles: user.roles,
        device_status: user.device_status,
      });
    });

    // Add channels
    channels.forEach(channel => {
      suggestions.push({
        id: channel.id,
        name: channel.name,
        type: 'channel',
        memberCount: channel.memberCount,
        quantum_ready: channel.quantum_ready,
      });
    });

    // Add groups
    groups.forEach(group => {
      suggestions.push({
        id: group.id,
        name: group.name,
        type: 'group',
        memberCount: group.memberCount,
      });
    });

    return suggestions;
  }, [users, channels, groups]);

  // Handle input change and mention detection
  const handleInputChange = useCallback(async (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newValue = e.target.value;
    const cursorPos = e.target.selectionStart;

    onChange(newValue);

    // Detect mention triggers (@, #)
    const beforeCursor = newValue.slice(0, cursorPos);
    const mentionMatch = beforeCursor.match(/(@|#)([^@#\s]*)$/);

    if (mentionMatch) {
      const [, trigger, query] = mentionMatch;
      const start = cursorPos - mentionMatch[0].length;
      const end = cursorPos;

      setCurrentMention({ trigger, query, start, end });
      setSelectedIndex(0);

      // Search for mentions based on query
      if (query.length >= 1) {
        try {
          const searchResults = await searchMentions(
            query,
            trigger === '@' ? 'users' : 'channels'
          );

          const results: MentionSuggestion[] = [];

          if (trigger === '@') {
            searchResults.users.forEach(user => {
              results.push({
                id: user.id,
                name: user.name,
                type: 'user',
                avatar: user.avatar,
                online: user.online,
                quantum_ready: user.device_status?.quantum_ready,
                roles: user.roles,
                device_status: user.device_status,
              });
            });
          } else if (trigger === '#') {
            searchResults.channels.forEach(channel => {
              results.push({
                id: channel.id,
                name: channel.name,
                type: 'channel',
                memberCount: channel.memberCount,
                quantum_ready: channel.quantum_ready,
              });
            });
          }

          setFilteredSuggestions(results);
          setShowSuggestions(results.length > 0);
        } catch (error) {
          console.error('Failed to search mentions:', error);
          setShowSuggestions(false);
        }
      } else {
        // Show all suggestions for the trigger type
        const typedSuggestions = allSuggestions.filter(s =>
          trigger === '@' ? s.type === 'user' : s.type !== 'user'
        );

        setFilteredSuggestions(typedSuggestions.slice(0, 10));
        setShowSuggestions(typedSuggestions.length > 0);
      }
    } else {
      setCurrentMention(null);
      setShowSuggestions(false);
      setFilteredSuggestions([]);
    }
  }, [onChange, searchMentions, allSuggestions]);

  // Handle mention selection
  const selectMention = useCallback(async (suggestion: MentionSuggestion) => {
    if (!currentMention || !inputRef.current) return;

    const { trigger, start, end } = currentMention;
    const beforeMention = value.slice(0, start);
    const afterMention = value.slice(end);

    const mentionText = trigger === '@'
      ? `@${suggestion.name}`
      : `#${suggestion.name}`;

    const newValue = `${beforeMention}${mentionText} ${afterMention}`;

    onChange(newValue);

    // Create encrypted mention if it's a user mention
    if (suggestion.type === 'user' && enableQuantumEncryption) {
      try {
        await createEncryptedMention(
          suggestion.id,
          `You were mentioned: ${mentionText}`,
          'user'
        );
      } catch (error) {
        console.error('Failed to create encrypted mention:', error);
      }
    }

    onMention?.(suggestion.id, suggestion.name);

    // Reset mention state
    setCurrentMention(null);
    setShowSuggestions(false);
    setFilteredSuggestions([]);

    // Focus back to input
    setTimeout(() => {
      const newCursorPos = start + mentionText.length + 1;
      inputRef.current?.setSelectionRange(newCursorPos, newCursorPos);
      inputRef.current?.focus();
    }, 0);
  }, [currentMention, value, onChange, createEncryptedMention, enableQuantumEncryption, onMention]);

  // Handle keyboard navigation
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!showSuggestions) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev =>
          prev < filteredSuggestions.length - 1 ? prev + 1 : 0
        );
        break;

      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev =>
          prev > 0 ? prev - 1 : filteredSuggestions.length - 1
        );
        break;

      case 'Enter':
      case 'Tab':
        e.preventDefault();
        if (filteredSuggestions[selectedIndex]) {
          selectMention(filteredSuggestions[selectedIndex]);
        }
        break;

      case 'Escape':
        setShowSuggestions(false);
        setCurrentMention(null);
        break;
    }
  }, [showSuggestions, filteredSuggestions, selectedIndex, selectMention]);

  // Auto-resize textarea
  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.style.height = 'auto';
      inputRef.current.style.height = `${Math.min(inputRef.current.scrollHeight, 150)}px`;
    }
  }, [value]);

  // Scroll selected item into view
  useEffect(() => {
    if (suggestionsRef.current && showSuggestions) {
      const selectedElement = suggestionsRef.current.children[selectedIndex] as HTMLElement;
      if (selectedElement) {
        selectedElement.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
        });
      }
    }
  }, [selectedIndex, showSuggestions]);

  return (
    <div className={cn("relative", className)}>
      {/* Main Input */}
      <div className="relative">
        <textarea
          ref={inputRef}
          value={value}
          onChange={handleInputChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          maxLength={maxLength}
          className={cn(
            "flex min-h-[40px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm",
            "ring-offset-background placeholder:text-muted-foreground",
            "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
            "disabled:cursor-not-allowed disabled:opacity-50",
            "resize-none overflow-hidden transition-all duration-200"
          )}
          style={{
            minHeight: '40px',
            maxHeight: '150px',
          }}
        />

        {/* Character count */}
        {maxLength && (
          <div className="absolute bottom-2 right-2 text-xs text-muted-foreground">
            {value.length}/{maxLength}
          </div>
        )}

        {/* Quantum encryption indicator */}
        {quantumEnabled && (
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <div className="absolute top-2 right-2">
                  <ShieldCheck className="h-4 w-4 text-green-500" />
                </div>
              </TooltipTrigger>
              <TooltipContent>
                <p>Quantum-resistant encryption enabled</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        )}
      </div>

      {/* Mention Suggestions */}
      {showSuggestions && (
        <Card className="absolute top-full left-0 right-0 z-50 mt-1 shadow-lg border">
          <CardContent className="p-0">
            <ScrollArea className="max-h-64" ref={suggestionsRef}>
              <div className="p-2 space-y-1">
                {/* Recent mentions header */}
                {currentMention?.query === '' && recentMentions.length > 0 && (
                  <>
                    <div className="px-2 py-1 text-xs font-medium text-muted-foreground flex items-center">
                      <Clock className="h-3 w-3 mr-1" />
                      Recent mentions
                    </div>
                    {recentMentions.slice(0, 3).map((user, index) => (
                      <MentionSuggestionItem
                        key={`recent-${user.id}`}
                        suggestion={{
                          id: user.id,
                          name: user.name,
                          type: 'user',
                          avatar: user.avatar,
                          online: user.online,
                          quantum_ready: user.device_status?.quantum_ready,
                          device_status: user.device_status,
                        }}
                        isSelected={false}
                        onClick={() => selectMention({
                          id: user.id,
                          name: user.name,
                          type: 'user',
                          avatar: user.avatar,
                          online: user.online,
                          quantum_ready: user.device_status?.quantum_ready,
                          device_status: user.device_status,
                        })}
                        quantumEnabled={quantumEnabled}
                      />
                    ))}
                    <Separator className="my-2" />
                  </>
                )}

                {/* Filtered suggestions */}
                {filteredSuggestions.length > 0 ? (
                  filteredSuggestions.map((suggestion, index) => (
                    <MentionSuggestionItem
                      key={suggestion.id}
                      suggestion={suggestion}
                      isSelected={index === selectedIndex}
                      onClick={() => selectMention(suggestion)}
                      quantumEnabled={quantumEnabled}
                    />
                  ))
                ) : loading ? (
                  <div className="flex items-center justify-center py-4">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
                    <span className="ml-2 text-sm text-muted-foreground">Searching...</span>
                  </div>
                ) : (
                  <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                    No suggestions found
                  </div>
                )}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

interface MentionSuggestionItemProps {
  suggestion: MentionSuggestion;
  isSelected: boolean;
  onClick: () => void;
  quantumEnabled: boolean;
}

function MentionSuggestionItem({
  suggestion,
  isSelected,
  onClick,
  quantumEnabled,
}: MentionSuggestionItemProps) {
  const getTypeIcon = () => {
    switch (suggestion.type) {
      case 'user':
        return <AtSign className="h-3 w-3" />;
      case 'channel':
        return <Hash className="h-3 w-3" />;
      case 'group':
        return <Users className="h-3 w-3" />;
    }
  };

  const getStatusBadges = () => {
    const badges = [];

    if (suggestion.type === 'user') {
      if (suggestion.online) {
        badges.push(
          <TooltipProvider key="online">
            <Tooltip>
              <TooltipTrigger asChild>
                <Wifi className="h-3 w-3 text-green-500" />
              </TooltipTrigger>
              <TooltipContent>
                <p>Online</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        );
      }

      if (quantumEnabled && suggestion.quantum_ready) {
        badges.push(
          <TooltipProvider key="quantum">
            <Tooltip>
              <TooltipTrigger asChild>
                <ShieldCheck className="h-3 w-3 text-blue-500" />
              </TooltipTrigger>
              <TooltipContent>
                <p>Quantum-ready device</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        );
      }

      if (suggestion.roles?.includes('admin')) {
        badges.push(
          <TooltipProvider key="admin">
            <Tooltip>
              <TooltipTrigger asChild>
                <Crown className="h-3 w-3 text-yellow-500" />
              </TooltipTrigger>
              <TooltipContent>
                <p>Administrator</p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        );
      }
    }

    return badges;
  };

  return (
    <div
      className={cn(
        "flex items-center space-x-3 rounded-md px-2 py-2 cursor-pointer transition-colors",
        isSelected ? "bg-accent text-accent-foreground" : "hover:bg-muted"
      )}
      onClick={onClick}
    >
      {/* Icon/Avatar */}
      {suggestion.type === 'user' ? (
        <Avatar className="h-6 w-6">
          <AvatarImage src={suggestion.avatar} />
          <AvatarFallback className="text-xs">
            {suggestion.name.charAt(0)}
          </AvatarFallback>
        </Avatar>
      ) : (
        <div className="flex items-center justify-center h-6 w-6 rounded bg-muted">
          {getTypeIcon()}
        </div>
      )}

      {/* Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center space-x-2">
          <span className="font-medium text-sm truncate">
            {suggestion.name}
          </span>

          <div className="flex items-center space-x-1">
            {getStatusBadges()}
          </div>
        </div>

        {/* Additional info */}
        <div className="text-xs text-muted-foreground">
          {suggestion.type === 'user' && suggestion.device_status?.last_seen && (
            <span>
              Last seen {formatDistanceToNow(new Date(suggestion.device_status.last_seen), { addSuffix: true })}
            </span>
          )}
          {(suggestion.type === 'channel' || suggestion.type === 'group') && suggestion.memberCount && (
            <span>
              {suggestion.memberCount} member{suggestion.memberCount !== 1 ? 's' : ''}
            </span>
          )}
        </div>
      </div>

      {/* Type badge */}
      <Badge variant="secondary" className="text-xs">
        {suggestion.type}
      </Badge>
    </div>
  );
}

export default MentionInput;
