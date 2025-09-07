import React from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Reply, ArrowUp, MessageSquare, X } from 'lucide-react';
import { formatDistance } from 'date-fns';

interface MessageUser {
    id: string;
    name: string;
    avatar?: string;
}

interface ReplyMessage {
    id: string;
    sender: MessageUser;
    decrypted_content?: string;
    created_at: string;
    is_edited?: boolean;
    is_forwarded?: boolean;
}

interface MessageReplyIndicatorProps {
    replyToMessage?: ReplyMessage | null;
    onJumpToMessage?: (messageId: string) => void;
    onClearReply?: () => void;
    className?: string;
    showClearButton?: boolean;
}

export function MessageReplyIndicator({
    replyToMessage,
    onJumpToMessage,
    onClearReply,
    className = "",
    showClearButton = false
}: MessageReplyIndicatorProps) {
    if (!replyToMessage) {
        return null;
    }

    const handleJumpToMessage = () => {
        if (onJumpToMessage) {
            onJumpToMessage(replyToMessage.id);
        }
    };

    const handleClearReply = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (onClearReply) {
            onClearReply();
        }
    };

    return (
        <div className={`group relative ${className}`}>
            <div 
                className={`
                    flex items-start gap-3 p-3 rounded-lg border-l-4 border-primary/50 
                    bg-muted/50 hover:bg-muted/70 transition-colors
                    ${onJumpToMessage ? 'cursor-pointer' : ''}
                `}
                onClick={onJumpToMessage ? handleJumpToMessage : undefined}
            >
                {/* Reply icon */}
                <div className="flex-shrink-0 mt-0.5">
                    <div className="w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center">
                        <Reply className="w-3 h-3 text-primary" />
                    </div>
                </div>

                {/* Original message info */}
                <div className="flex-1 min-w-0 space-y-1">
                    <div className="flex items-center gap-2">
                        <Avatar className="h-5 w-5">
                            <AvatarImage src={replyToMessage.sender.avatar} />
                            <AvatarFallback className="text-xs">
                                {replyToMessage.sender.name.charAt(0)}
                            </AvatarFallback>
                        </Avatar>
                        <span className="text-sm font-medium text-muted-foreground">
                            {replyToMessage.sender.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatDistance(new Date(replyToMessage.created_at), new Date(), { addSuffix: true })}
                        </span>
                        {replyToMessage.is_edited && (
                            <Badge variant="outline" className="text-xs h-4">
                                edited
                            </Badge>
                        )}
                        {replyToMessage.is_forwarded && (
                            <Badge variant="secondary" className="text-xs h-4">
                                forwarded
                            </Badge>
                        )}
                    </div>
                    
                    <div className="text-sm text-muted-foreground line-clamp-2">
                        {replyToMessage.decrypted_content || '[Encrypted message]'}
                    </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {onJumpToMessage && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 w-6 p-0"
                            onClick={handleJumpToMessage}
                            title="Jump to original message"
                        >
                            <ArrowUp className="w-3 h-3" />
                        </Button>
                    )}
                    
                    {showClearButton && onClearReply && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                            onClick={handleClearReply}
                            title="Clear reply"
                        >
                            <X className="w-3 h-3" />
                        </Button>
                    )}
                </div>
            </div>

            {/* Connection line for visual thread indication */}
            {onJumpToMessage && (
                <div className="absolute -left-px top-0 bottom-0 w-px bg-gradient-to-b from-primary/50 via-primary/20 to-transparent" />
            )}
        </div>
    );
}

// Component for showing reply count and thread info
interface MessageThreadInfoProps {
    replyCount: number;
    lastReplyAt?: string;
    lastReplyUser?: MessageUser;
    onViewThread?: () => void;
    className?: string;
}

export function MessageThreadInfo({
    replyCount,
    lastReplyAt,
    lastReplyUser,
    onViewThread,
    className = ""
}: MessageThreadInfoProps) {
    if (replyCount === 0) {
        return null;
    }

    return (
        <div className={`mt-2 ${className}`}>
            <Button
                variant="ghost"
                size="sm"
                className="h-auto p-2 hover:bg-accent/50 transition-colors"
                onClick={onViewThread}
            >
                <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1 text-xs text-primary">
                        <MessageSquare className="w-3 h-3" />
                        <span className="font-medium">
                            {replyCount} {replyCount === 1 ? 'reply' : 'replies'}
                        </span>
                    </div>
                    
                    {lastReplyUser && lastReplyAt && (
                        <div className="flex items-center gap-1">
                            <Avatar className="h-4 w-4">
                                <AvatarImage src={lastReplyUser.avatar} />
                                <AvatarFallback className="text-xs">
                                    {lastReplyUser.name.charAt(0)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="text-xs text-muted-foreground">
                                Last reply {formatDistance(new Date(lastReplyAt), new Date(), { addSuffix: true })}
                            </span>
                        </div>
                    )}
                </div>
            </Button>
        </div>
    );
}