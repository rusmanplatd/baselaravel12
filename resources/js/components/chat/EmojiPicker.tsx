import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { 
    Popover, 
    PopoverContent, 
    PopoverTrigger 
} from '@/components/ui/popover';
import { 
    Tabs, 
    TabsContent, 
    TabsList, 
    TabsTrigger 
} from '@/components/ui/tabs';
import { Search, Smile, Heart, ThumbsUp, Zap } from 'lucide-react';

interface EmojiCategory {
    name: string;
    icon: React.ReactNode;
    emojis: string[];
}

const emojiCategories: EmojiCategory[] = [
    {
        name: 'Recent',
        icon: <Zap className="w-4 h-4" />,
        emojis: ['👍', '❤️', '😂', '😮', '😢', '😡', '👎', '🔥']
    },
    {
        name: 'Smileys',
        icon: <Smile className="w-4 h-4" />,
        emojis: [
            '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣',
            '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰',
            '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜',
            '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏',
            '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
            '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠',
            '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨',
            '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥',
            '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧',
            '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐'
        ]
    },
    {
        name: 'Hearts',
        icon: <Heart className="w-4 h-4" />,
        emojis: [
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
            '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
            '💘', '💝', '💟', '♥️', '💌', '💋', '😍', '🥰',
            '😘', '😗', '😙', '😚', '🤗', '💑', '👨‍❤️‍👨', '👩‍❤️‍👩'
        ]
    },
    {
        name: 'Gestures',
        icon: <ThumbsUp className="w-4 h-4" />,
        emojis: [
            '👍', '👎', '👌', '🤏', '✌️', '🤞', '🤟', '🤘',
            '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👋',
            '🤚', '🖐️', '✋', '🖖', '👏', '🙌', '🤲', '🤝',
            '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦿', '🦵'
        ]
    }
];

interface EmojiPickerProps {
    onEmojiSelect: (emoji: string) => void;
    trigger?: React.ReactNode;
    className?: string;
}

export function EmojiPicker({ 
    onEmojiSelect, 
    trigger,
    className = ""
}: EmojiPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [recentEmojis, setRecentEmojis] = useState<string[]>(() => {
        const saved = localStorage.getItem('chat-recent-emojis');
        return saved ? JSON.parse(saved) : ['👍', '❤️', '😂', '😮', '😢', '😡', '👎', '🔥'];
    });

    const searchInputRef = useRef<HTMLInputElement>(null);

    // Filter emojis based on search query
    const filteredCategories = emojiCategories.map(category => {
        if (category.name === 'Recent') {
            return {
                ...category,
                emojis: searchQuery 
                    ? recentEmojis.filter(emoji => 
                        emojiCategories.some(cat => 
                            cat.emojis.includes(emoji) && 
                            cat.name.toLowerCase().includes(searchQuery.toLowerCase())
                        )
                    )
                    : recentEmojis
            };
        }
        
        return searchQuery 
            ? {
                ...category,
                emojis: category.emojis.filter(() => 
                    category.name.toLowerCase().includes(searchQuery.toLowerCase())
                )
            }
            : category;
    }).filter(category => category.emojis.length > 0);

    // Handle emoji selection
    const handleEmojiClick = (emoji: string) => {
        // Add to recent emojis
        const newRecentEmojis = [
            emoji,
            ...recentEmojis.filter(e => e !== emoji)
        ].slice(0, 16); // Keep only 16 recent emojis
        
        setRecentEmojis(newRecentEmojis);
        localStorage.setItem('chat-recent-emojis', JSON.stringify(newRecentEmojis));
        
        onEmojiSelect(emoji);
        setIsOpen(false);
        setSearchQuery('');
    };

    // Focus search input when popover opens
    useEffect(() => {
        if (isOpen && searchInputRef.current) {
            setTimeout(() => {
                searchInputRef.current?.focus();
            }, 100);
        }
    }, [isOpen]);

    // Keyboard navigation
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!isOpen) return;
            
            if (e.key === 'Escape') {
                setIsOpen(false);
                setSearchQuery('');
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen]);

    const defaultTrigger = (
        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
            <Smile className="w-4 h-4" />
        </Button>
    );

    return (
        <Popover open={isOpen} onOpenChange={(open) => {
            setIsOpen(open);
            if (!open) {
                setSearchQuery('');
            }
        }}>
            <PopoverTrigger asChild>
                {trigger || defaultTrigger}
            </PopoverTrigger>
            <PopoverContent 
                className="w-80 p-0"
                align="start"
                side="top"
                sideOffset={8}
            >
                <div className="flex flex-col h-96">
                    {/* Header with search */}
                    <div className="p-3 border-b space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="font-medium text-sm flex items-center gap-2">
                                <Smile className="w-4 h-4" />
                                Add Reaction
                            </h4>
                            <Badge variant="secondary" className="text-xs">
                                {filteredCategories.reduce((acc, cat) => acc + cat.emojis.length, 0)} emojis
                            </Badge>
                        </div>
                        <div className="relative">
                            <Search className="absolute left-2 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
                            <Input
                                ref={searchInputRef}
                                placeholder="Search emojis..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-8 h-8"
                            />
                        </div>
                    </div>

                    {/* Emoji categories */}
                    <div className="flex-1 overflow-hidden">
                        <Tabs defaultValue="Recent" className="h-full flex flex-col">
                            <TabsList className="grid w-full grid-cols-4 h-auto p-1 m-1">
                                {emojiCategories.map((category) => {
                                    const filteredCategory = filteredCategories.find(c => c.name === category.name);
                                    const count = filteredCategory?.emojis.length || 0;
                                    
                                    return (
                                        <TabsTrigger 
                                            key={category.name} 
                                            value={category.name}
                                            className="flex flex-col gap-1 h-auto py-2"
                                            disabled={count === 0}
                                        >
                                            {category.icon}
                                            <span className="text-xs">{category.name}</span>
                                            {count > 0 && (
                                                <Badge variant="secondary" className="text-xs h-4 px-1">
                                                    {count}
                                                </Badge>
                                            )}
                                        </TabsTrigger>
                                    );
                                })}
                            </TabsList>

                            <div className="flex-1 overflow-hidden">
                                {filteredCategories.map((category) => (
                                    <TabsContent 
                                        key={category.name} 
                                        value={category.name}
                                        className="h-full m-0 p-0"
                                    >
                                        <ScrollArea className="h-full">
                                            <div className="grid grid-cols-8 gap-1 p-3">
                                                {category.emojis.map((emoji, index) => (
                                                    <Button
                                                        key={`${emoji}-${index}`}
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8 w-8 p-0 hover:bg-accent transition-colors text-lg"
                                                        onClick={() => handleEmojiClick(emoji)}
                                                        title={emoji}
                                                    >
                                                        {emoji}
                                                    </Button>
                                                ))}
                                            </div>
                                        </ScrollArea>
                                    </TabsContent>
                                ))}
                            </div>
                        </Tabs>
                    </div>

                    {searchQuery && filteredCategories.length === 0 && (
                        <div className="flex-1 flex items-center justify-center text-muted-foreground">
                            <div className="text-center">
                                <Search className="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p className="text-sm">No emojis found</p>
                                <p className="text-xs mt-1">Try a different search term</p>
                            </div>
                        </div>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}