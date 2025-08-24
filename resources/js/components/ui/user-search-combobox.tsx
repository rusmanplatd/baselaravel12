import React, { useState, useCallback, useEffect } from 'react';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { cn } from '@/lib/utils';

interface UserSearchComboboxProps {
  value?: string;
  onSelect: (email: string) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
}

interface SearchResult {
  id: number;
  name: string;
  email: string;
  avatar?: string;
}

// User search cache to avoid repeated API calls
const userCache = new Map<string, { users: SearchResult[]; timestamp: number }>();
const CACHE_DURATION = 30000; // 30 seconds

export function UserSearchCombobox({
  value,
  onSelect,
  placeholder = "Search users...",
  className,
  disabled = false,
}: UserSearchComboboxProps) {
  const [open, setOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(false);
  
  const [users, setUsers] = useState<SearchResult[]>([]);
  const [allUsers, setAllUsers] = useState<SearchResult[]>([]); // Keep all found users for selected user lookup
  
  // Search users via API
  const searchUsers = useCallback(async (query: string) => {
    if (!query || query.length < 2) {
      setUsers([]);
      return;
    }

    // Check cache first
    const cacheKey = query.toLowerCase();
    const cachedResult = userCache.get(cacheKey);
    const now = Date.now();
    
    if (cachedResult && (now - cachedResult.timestamp) < CACHE_DURATION) {
      setUsers(cachedResult.users);
      return;
    }

    try {
      setLoading(true);
      
      const response = await fetch(`/api/v1/users/suggestions?q=${encodeURIComponent(query)}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error('Failed to search users');
      }

      const data: SearchResult[] = await response.json();
      
      // Transform avatar paths to full URLs
      const transformedData = data.map(user => ({
        ...user,
        avatar: user.avatar ? `/storage/${user.avatar}` : undefined
      }));
      
      // Cache the results
      userCache.set(cacheKey, { users: transformedData, timestamp: now });
      
      setUsers(transformedData);
      // Add to allUsers for lookup (avoid duplicates)
      setAllUsers(prev => {
        const existingEmails = new Set(prev.map(u => u.email));
        const newUsers = transformedData.filter(u => !existingEmails.has(u.email));
        return [...prev, ...newUsers];
      });
    } catch (error) {
      console.error('Error searching users:', error);
      setUsers([]);
    } finally {
      setLoading(false);
    }
  }, []);

  // Handle search input changes
  const handleSearch = useCallback((query: string) => {
    setSearchQuery(query);
  }, []);

  // Debounce the actual search
  useEffect(() => {
    if (searchQuery.length >= 2) {
      const timeoutId = setTimeout(() => {
        searchUsers(searchQuery);
      }, 300);
      
      return () => clearTimeout(timeoutId);
    } else {
      setUsers([]);
      setLoading(false);
    }
  }, [searchQuery, searchUsers]);
  
  const handleSelect = useCallback((userEmail: string) => {
    onSelect(userEmail);
    setOpen(false);
    setSearchQuery('');
  }, [onSelect]);
  
  const selectedUser = allUsers.find(user => user.email === value);
  
  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(part => part.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };
  
  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className={cn("w-full justify-between h-auto px-3 py-2", className)}
          disabled={disabled}
        >
          <div className="flex items-center space-x-2 flex-1 min-w-0">
            {selectedUser ? (
              <>
                <Avatar className="h-6 w-6">
                  <AvatarImage src={selectedUser.avatar} alt={selectedUser.name} />
                  <AvatarFallback className="text-xs bg-blue-500 text-white">
                    {getInitials(selectedUser.name)}
                  </AvatarFallback>
                </Avatar>
                <div className="flex-1 min-w-0 text-left">
                  <div className="font-medium text-sm truncate">
                    {selectedUser.name}
                  </div>
                  <div className="text-xs text-muted-foreground truncate">
                    {selectedUser.email}
                  </div>
                </div>
              </>
            ) : (
              <span className="text-muted-foreground">{placeholder}</span>
            )}
          </div>
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput
            placeholder="Search users by name or email..."
            value={searchQuery}
            onValueChange={handleSearch}
            className="h-9"
          />
          <CommandList>
            {loading && (
              <div className="flex items-center justify-center p-4 text-sm text-muted-foreground">
                <Search className="mr-2 h-4 w-4 animate-spin" />
                Searching...
              </div>
            )}
            
            {!loading && (
              <>
                <CommandEmpty>
                  {searchQuery.length < 2 
                    ? "Type at least 2 characters to search"
                    : "No users found."
                  }
                </CommandEmpty>
                
                {users.length > 0 && (
                  <CommandGroup>
                    {users.map((user) => (
                      <CommandItem
                        key={user.id}
                        value={user.email}
                        onSelect={() => handleSelect(user.email)}
                        className="flex items-center space-x-3 p-3 cursor-pointer"
                      >
                        <Avatar className="h-8 w-8">
                          <AvatarImage src={user.avatar} alt={user.name} />
                          <AvatarFallback className="bg-blue-500 text-white text-sm">
                            {getInitials(user.name)}
                          </AvatarFallback>
                        </Avatar>
                        <div className="flex-1 min-w-0">
                          <div className="font-medium text-sm truncate">
                            {user.name}
                          </div>
                          <div className="text-xs text-muted-foreground truncate">
                            {user.email}
                          </div>
                        </div>
                        <Check
                          className={cn(
                            "h-4 w-4",
                            value === user.email ? "opacity-100" : "opacity-0"
                          )}
                        />
                      </CommandItem>
                    ))}
                  </CommandGroup>
                )}
              </>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}