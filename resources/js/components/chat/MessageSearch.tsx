import React, { useState, useEffect, useRef } from 'react';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { Message } from '@/types/chat';

interface MessageSearchProps {
  isOpen: boolean;
  onClose: () => void;
  onSearch: (query: string) => Promise<Message[]>;
  onMessageSelect?: (message: Message) => void;
}

export default function MessageSearch({ isOpen, onClose, onSearch, onMessageSelect }: MessageSearchProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    if (isOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isOpen]);

  useEffect(() => {
    if (!query.trim()) {
      setResults([]);
      setError(null);
      return;
    }

    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    searchTimeoutRef.current = setTimeout(async () => {
      setLoading(true);
      setError(null);
      
      try {
        const searchResults = await onSearch(query);
        setResults(searchResults);
      } catch {
        setError('Failed to search messages');
        setResults([]);
      } finally {
        setLoading(false);
      }
    }, 300);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [query, onSearch]);

  const handleClose = () => {
    setQuery('');
    setResults([]);
    setError(null);
    onClose();
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      handleClose();
    }
  };

  const highlightText = (text: string, searchQuery: string) => {
    if (!searchQuery.trim()) return text;
    
    const regex = new RegExp(`(${searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    const parts = text.split(regex);
    
    return parts.map((part, index) =>
      regex.test(part) ? (
        <mark key={index} className="bg-yellow-200 px-1 rounded">
          {part}
        </mark>
      ) : (
        part
      )
    );
  };

  const formatMessageDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now.getTime() - date.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
      return `Yesterday at ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    } else if (diffDays < 7) {
      return date.toLocaleDateString([], { weekday: 'long', hour: '2-digit', minute: '2-digit' });
    } else {
      return date.toLocaleDateString([], { 
        month: 'short', 
        day: 'numeric', 
        year: diffDays > 365 ? 'numeric' : undefined,
        hour: '2-digit', 
        minute: '2-digit' 
      });
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-20">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[70vh] overflow-hidden">
        {/* Search Header */}
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center space-x-3">
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
              <input
                ref={searchInputRef}
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Search messages..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <button
              onClick={handleClose}
              className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
            >
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        {/* Search Results */}
        <div className="overflow-y-auto max-h-96">
          {loading && (
            <div className="flex items-center justify-center py-8">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
              <span className="ml-2 text-gray-600">Searching...</span>
            </div>
          )}

          {error && (
            <div className="p-4 text-center text-red-600">
              <p>{error}</p>
            </div>
          )}

          {!loading && !error && query && results.length === 0 && (
            <div className="p-8 text-center text-gray-500">
              <MagnifyingGlassIcon className="h-12 w-12 mx-auto text-gray-300 mb-2" />
              <p>No messages found for "{query}"</p>
              <p className="text-sm mt-1">Try different keywords</p>
            </div>
          )}

          {!loading && !error && !query && (
            <div className="p-8 text-center text-gray-500">
              <MagnifyingGlassIcon className="h-12 w-12 mx-auto text-gray-300 mb-2" />
              <p>Search through your messages</p>
              <p className="text-sm mt-1">Type to start searching...</p>
            </div>
          )}

          {results.map((message) => (
            <div
              key={message.id}
              onClick={() => {
                onMessageSelect?.(message);
                handleClose();
              }}
              className="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors"
            >
              <div className="flex items-start space-x-3">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                    {message.sender?.name?.charAt(0).toUpperCase() || '?'}
                  </div>
                </div>
                
                <div className="flex-1 min-w-0">
                  <div className="flex items-center space-x-2 mb-1">
                    <span className="font-medium text-sm text-gray-900">
                      {message.sender?.name || 'Unknown User'}
                    </span>
                    <span className="text-xs text-gray-500">
                      {formatMessageDate(message.created_at)}
                    </span>
                    {message.type === 'voice' && (
                      <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">
                        Voice
                      </span>
                    )}
                  </div>
                  
                  <div className="text-sm text-gray-700">
                    {message.type === 'voice' ? (
                      <div className="flex items-center space-x-2">
                        <span className="text-blue-600">🎵 Voice message</span>
                        {message.voice_transcript && (
                          <span className="italic">
                            "{highlightText(message.voice_transcript, query)}"
                          </span>
                        )}
                      </div>
                    ) : message.content ? (
                      <div className="line-clamp-2">
                        {highlightText(message.content, query)}
                      </div>
                    ) : (
                      <span className="text-gray-400 italic">
                        [Message could not be decrypted]
                      </span>
                    )}
                  </div>
                  
                  {message.reply_to && (
                    <div className="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-600 border-l-2 border-gray-300">
                      Replying to: {message.reply_to.content || '[Voice message]'}
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Results Footer */}
        {results.length > 0 && (
          <div className="p-3 border-t border-gray-200 bg-gray-50 text-center text-xs text-gray-500">
            Found {results.length} message{results.length === 1 ? '' : 's'}
          </div>
        )}
      </div>
    </div>
  );
}