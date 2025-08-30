import { useState, useEffect, useRef } from 'react';
import { User, Participant } from '@/types/chat';
import { getMentionSuggestions, MentionSuggestion } from '@/utils/mentions';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

interface MentionAutocompleteProps {
  readonly query: string;
  readonly participants: Participant[];
  readonly currentUserId: string;
  readonly onSelect: (user: User) => void;
  readonly onClose: () => void;
  readonly position: { top: number; left: number };
  readonly isVisible: boolean;
}

export default function MentionAutocomplete({
  query,
  participants,
  currentUserId,
  onSelect,
  onClose,
  position,
  isVisible
}: MentionAutocompleteProps) {
  const [suggestions, setSuggestions] = useState<MentionSuggestion[]>([]);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (query.trim()) {
      const newSuggestions = getMentionSuggestions(query, participants, currentUserId);
      setSuggestions(newSuggestions);
      setSelectedIndex(0);
    } else {
      setSuggestions([]);
    }
  }, [query, participants, currentUserId]);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (!isVisible || suggestions.length === 0) return;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          setSelectedIndex(prev => 
            prev < suggestions.length - 1 ? prev + 1 : 0
          );
          break;
        case 'ArrowUp':
          event.preventDefault();
          setSelectedIndex(prev => 
            prev > 0 ? prev - 1 : suggestions.length - 1
          );
          break;
        case 'Enter':
        case 'Tab':
          event.preventDefault();
          if (suggestions[selectedIndex]) {
            onSelect(suggestions[selectedIndex].user);
          }
          break;
        case 'Escape':
          event.preventDefault();
          onClose();
          break;
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isVisible, suggestions, selectedIndex, onSelect, onClose]);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        onClose();
      }
    };

    if (isVisible) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isVisible, onClose]);

  if (!isVisible || suggestions.length === 0) {
    return null;
  }

  return (
    <div
      ref={containerRef}
      className="fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-48 max-w-64"
      style={{
        top: position.top - 8, // Offset above cursor
        left: position.left,
        maxHeight: '200px',
        overflowY: 'auto'
      }}
    >
      {suggestions.map((suggestion, index) => (
        <button
          key={suggestion.user.id}
          className={`w-full px-3 py-2 text-left flex items-center space-x-3 hover:bg-gray-50 transition-colors ${
            index === selectedIndex ? 'bg-blue-50 text-blue-900' : 'text-gray-900'
          }`}
          onClick={() => onSelect(suggestion.user)}
          onMouseEnter={() => setSelectedIndex(index)}
        >
          <Avatar className="h-6 w-6">
            <AvatarImage 
              src={(suggestion.user as any).avatar_url} 
              alt={suggestion.user.name} 
            />
            <AvatarFallback className="text-xs">
              {suggestion.user.name.charAt(0).toUpperCase()}
            </AvatarFallback>
          </Avatar>
          
          <div className="flex-1 min-w-0">
            <div className="text-sm font-medium truncate">
              {suggestion.user.name}
            </div>
            {suggestion.user.email && (
              <div className="text-xs text-gray-500 truncate">
                {suggestion.user.email}
              </div>
            )}
          </div>
          
          {suggestion.participant?.role === 'admin' && (
            <div className="text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">
              Admin
            </div>
          )}
          {suggestion.participant?.role === 'owner' && (
            <div className="text-xs bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded">
              Owner
            </div>
          )}
        </button>
      ))}
      
      <div className="px-3 py-1 border-t border-gray-100 mt-1">
        <div className="text-xs text-gray-500">
          Use ↑↓ to navigate • Enter to select • Esc to close
        </div>
      </div>
    </div>
  );
}