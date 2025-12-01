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

    // Debug logging to see what we're getting
    console.log('Reply message data:', {
        id: replyToMessage.id,
        sender: replyToMessage.sender?.name,
        decrypted_content: replyToMessage.decrypted_content,
        has_encrypted_content: !!(replyToMessage as any).encrypted_content,
        all_keys: Object.keys(replyToMessage)
    });

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
                    flex items-start gap-2 p-2 rounded border-l-4 border-primary/60
                    bg-muted/30 hover:bg-muted/50 transition-colors text-sm
                    ${onJumpToMessage ? 'cursor-pointer' : ''}
                `}
                onClick={onJumpToMessage ? handleJumpToMessage : undefined}
            >
                {/* Reply icon (smaller and positioned left) */}
                <div className="flex-shrink-0 mt-0.5">
                    <Reply className="w-3 h-3 text-primary/70" />
                </div>

                {/* Original message info - Telegram style */}
                <div className="flex-1 min-w-0">
                    {/* Sender name - more prominent like Telegram */}
                    <div className="text-sm font-medium text-primary mb-1 truncate">
                        {replyToMessage.sender.name}
                    </div>
                    
                    {/* Original message content - styled like Telegram */}
                    <div className="text-sm text-foreground/80 line-clamp-2 leading-tight">
                        {replyToMessage.decrypted_content || 
                         (replyToMessage as any).encrypted_content ? '[Decrypting...]' : '[No content available]'}
                    </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    {showClearButton && onClearReply && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-5 w-5 p-0 text-muted-foreground hover:text-destructive"
                            onClick={handleClearReply}
                            title="Clear reply"
                        >
                            <X className="w-3 h-3" />
                        </Button>
                    )}
                </div>
            </div>
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