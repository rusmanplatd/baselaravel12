import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Plus, X } from 'lucide-react';
import { EmojiPicker } from './EmojiPicker';

interface MessageUser {
    id: string;
    name: string;
    avatar?: string;
}

interface MessageReaction {
    id: string;
    emoji: string;
    user_id: string;
    user: MessageUser;
}

interface MessageReactionsProps {
    reactions: MessageReaction[];
    currentUserId: string;
    onAddReaction: (emoji: string) => void;
    onRemoveReaction: (emoji: string) => void;
    className?: string;
    maxDisplay?: number;
    isLoading?: boolean;
    loadingEmoji?: string;
}

export function MessageReactions({
    reactions = [],
    currentUserId,
    onAddReaction,
    onRemoveReaction,
    className = "",
    maxDisplay = 5,
    isLoading = false,
    loadingEmoji = ""
}: MessageReactionsProps) {
    const [showAllReactions, setShowAllReactions] = useState(false);

    // Group reactions by emoji
    const groupedReactions = reactions.reduce((acc, reaction) => {
        if (!acc[reaction.emoji]) {
            acc[reaction.emoji] = [];
        }
        acc[reaction.emoji].push(reaction);
        return acc;
    }, {} as Record<string, MessageReaction[]>);

    const reactionEntries = Object.entries(groupedReactions);
    const totalReactions = reactions.length;

    if (totalReactions === 0) {
        return null;
    }

    const displayedReactions = showAllReactions 
        ? reactionEntries 
        : reactionEntries.slice(0, maxDisplay);
    
    const hiddenCount = reactionEntries.length - maxDisplay;

    const handleReactionClick = (emoji: string, userReactions: MessageReaction[]) => {
        const userHasReacted = userReactions.some(reaction => reaction.user_id === currentUserId);
        
        if (userHasReacted) {
            onRemoveReaction(emoji);
        } else {
            onAddReaction(emoji);
        }
    };

    const ReactionButton = ({ 
        emoji, 
        userReactions 
    }: { 
        emoji: string; 
        userReactions: MessageReaction[] 
    }) => {
        const count = userReactions.length;
        const userHasReacted = userReactions.some(reaction => reaction.user_id === currentUserId);
        const otherUsers = userReactions.filter(reaction => reaction.user_id !== currentUserId);
        
        const tooltipContent = (
            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <span className="text-lg">{emoji}</span>
                    <span className="text-sm font-medium">
                        {count} {count === 1 ? 'reaction' : 'reactions'}
                    </span>
                </div>
                <div className="space-y-1">
                    {userHasReacted && (
                        <p className="text-xs text-primary font-medium">You reacted with {emoji}</p>
                    )}
                    {otherUsers.length > 0 && (
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground">Others who reacted:</p>
                            <div className="flex flex-wrap gap-1">
                                {otherUsers.slice(0, 8).map((reaction) => (
                                    <div key={reaction.id} className="flex items-center gap-1">
                                        <Avatar className="h-4 w-4">
                                            <AvatarImage src={reaction.user.avatar} />
                                            <AvatarFallback className="text-xs">
                                                {reaction.user.name.charAt(0)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="text-xs">{reaction.user.name}</span>
                                    </div>
                                ))}
                                {otherUsers.length > 8 && (
                                    <span className="text-xs text-muted-foreground">
                                        +{otherUsers.length - 8} more
                                    </span>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );

        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant={userHasReacted ? "default" : "secondary"}
                            size="sm"
                            className={`h-7 px-2 transition-all hover:scale-105 ${
                                userHasReacted 
                                    ? 'bg-primary/20 border-primary/30 text-primary hover:bg-primary/30' 
                                    : 'hover:bg-accent'
                            }`}
                            onClick={() => handleReactionClick(emoji, userReactions)}
                            disabled={isLoading && loadingEmoji === emoji}
                        >
                            {isLoading && loadingEmoji === emoji ? (
                                <>
                                    <div className="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin mr-1" />
                                    <span className="text-xs font-medium">{count}</span>
                                </>
                            ) : (
                                <>
                                    <span className="mr-1 text-sm">{emoji}</span>
                                    <span className="text-xs font-medium">{count}</span>
                                </>
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="top" className="max-w-xs">
                        {tooltipContent}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    };

    return (
        <div className={`flex flex-wrap items-center gap-1 mt-2 ${className}`}>
            {displayedReactions.map(([emoji, userReactions]) => (
                <ReactionButton 
                    key={emoji} 
                    emoji={emoji} 
                    userReactions={userReactions} 
                />
            ))}

            {/* Show more button if there are hidden reactions */}
            {!showAllReactions && hiddenCount > 0 && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                    onClick={() => setShowAllReactions(true)}
                >
                    +{hiddenCount} more
                </Button>
            )}

            {/* Show less button when all reactions are displayed */}
            {showAllReactions && reactionEntries.length > maxDisplay && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                    onClick={() => setShowAllReactions(false)}
                >
                    Show less
                </Button>
            )}

            {/* Add reaction button */}
            <EmojiPicker 
                onEmojiSelect={onAddReaction}
                trigger={
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 w-7 p-0 rounded-full opacity-60 hover:opacity-100 hover:bg-accent transition-all"
                    >
                        <Plus className="w-3 h-3" />
                    </Button>
                }
            />

            {/* Reaction summary for large numbers */}
            {totalReactions > 10 && (
                <HoverCard>
                    <HoverCardTrigger asChild>
                        <Badge 
                            variant="secondary" 
                            className="ml-2 text-xs cursor-pointer hover:bg-accent"
                        >
                            {totalReactions} total reactions
                        </Badge>
                    </HoverCardTrigger>
                    <HoverCardContent className="w-80" side="top">
                        <div className="space-y-3">
                            <h4 className="text-sm font-medium">All Reactions</h4>
                            <div className="space-y-2">
                                {reactionEntries.map(([emoji, userReactions]) => (
                                    <div key={emoji} className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="text-lg">{emoji}</span>
                                            <span className="text-sm">{userReactions.length}</span>
                                        </div>
                                        <div className="flex -space-x-1">
                                            {userReactions.slice(0, 5).map((reaction) => (
                                                <Avatar key={reaction.id} className="h-6 w-6 border border-background">
                                                    <AvatarImage src={reaction.user.avatar} />
                                                    <AvatarFallback className="text-xs">
                                                        {reaction.user.name.charAt(0)}
                                                    </AvatarFallback>
                                                </Avatar>
                                            ))}
                                            {userReactions.length > 5 && (
                                                <div className="h-6 w-6 rounded-full bg-muted border border-background flex items-center justify-center">
                                                    <span className="text-xs">+{userReactions.length - 5}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </HoverCardContent>
                </HoverCard>
            )}
        </div>
    );
}