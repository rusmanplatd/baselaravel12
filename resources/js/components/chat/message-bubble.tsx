import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
    Check, 
    CheckCheck, 
    Clock, 
    Shield, 
    Lock,
    Reply,
    MoreHorizontal,
    Edit2
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface Message {
    id: string;
    conversation_id: string;
    sender_id: string;
    type: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll';
    decrypted_content?: string;
    encrypted_content: string;
    is_edited: boolean;
    created_at: string;
    sender: {
        id: string;
        name: string;
        avatar: string;
    };
    reactions?: Array<{
        id: string;
        user_id: string;
        emoji: string;
        user: {
            id: string;
            name: string;
        };
    }>;
    replies?: Message[];
    reply_to?: Message;
}

interface MessageBubbleProps {
    message: Message;
    isOwn: boolean;
    currentUser: any;
}

export default function MessageBubble({ message, isOwn, currentUser }: MessageBubbleProps) {
    const formatTime = (timestamp: string): string => {
        return new Date(timestamp).toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    };

    const getInitials = (name: string): string => {
        return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    };

    const renderMessageContent = () => {
        switch (message.type) {
            case 'text':
                return (
                    <div className="whitespace-pre-wrap break-words">
                        {message.decrypted_content || 'Decrypting...'}
                    </div>
                );
            
            case 'image':
                return (
                    <div className="space-y-2">
                        <div className="bg-muted rounded-lg p-4 text-center">
                            📷 Image
                            <div className="text-xs text-muted-foreground mt-1">
                                Encrypted image content
                            </div>
                        </div>
                        {message.decrypted_content && (
                            <div className="text-sm">{message.decrypted_content}</div>
                        )}
                    </div>
                );
            
            case 'file':
                return (
                    <div className="bg-muted rounded-lg p-4 flex items-center gap-3">
                        <div className="text-2xl">📎</div>
                        <div>
                            <div className="font-medium">Encrypted File</div>
                            <div className="text-xs text-muted-foreground">
                                File content is encrypted
                            </div>
                        </div>
                    </div>
                );
            
            case 'voice':
                return (
                    <div className="bg-muted rounded-lg p-4 flex items-center gap-3">
                        <div className="text-2xl">🎤</div>
                        <div>
                            <div className="font-medium">Voice Message</div>
                            <div className="text-xs text-muted-foreground">
                                Encrypted voice content
                            </div>
                        </div>
                    </div>
                );
            
            case 'poll':
                return (
                    <div className="bg-muted rounded-lg p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <div className="text-lg">📊</div>
                            <div className="font-medium">Poll</div>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            Encrypted poll content
                        </div>
                    </div>
                );
            
            default:
                return (
                    <div className="text-muted-foreground text-sm">
                        Unsupported message type: {message.type}
                    </div>
                );
        }
    };

    const renderReactions = () => {
        if (!message.reactions || message.reactions.length === 0) return null;

        // Group reactions by emoji
        const reactionGroups = message.reactions.reduce((acc, reaction) => {
            if (!acc[reaction.emoji]) {
                acc[reaction.emoji] = [];
            }
            acc[reaction.emoji].push(reaction);
            return acc;
        }, {} as Record<string, typeof message.reactions>);

        return (
            <div className="flex flex-wrap gap-1 mt-2">
                {Object.entries(reactionGroups).map(([emoji, reactions]) => (
                    <Badge 
                        key={emoji} 
                        variant="secondary" 
                        className="text-xs px-2 py-0.5 cursor-pointer hover:bg-secondary/80"
                        title={reactions.map(r => r.user.name).join(', ')}
                    >
                        {emoji} {reactions.length}
                    </Badge>
                ))}
            </div>
        );
    };

    return (
        <div className={cn(
            "flex gap-3 group",
            isOwn ? "flex-row-reverse" : "flex-row"
        )}>
            {/* Avatar */}
            {!isOwn && (
                <Avatar className="h-8 w-8 mt-1">
                    <AvatarImage src={message.sender.avatar} />
                    <AvatarFallback className="text-xs">
                        {getInitials(message.sender.name)}
                    </AvatarFallback>
                </Avatar>
            )}

            {/* Message Content */}
            <div className={cn(
                "flex flex-col max-w-[70%]",
                isOwn ? "items-end" : "items-start"
            )}>
                {/* Sender name (only for group chats and not own messages) */}
                {!isOwn && (
                    <div className="text-xs text-muted-foreground mb-1 px-1">
                        {message.sender.name}
                    </div>
                )}

                {/* Reply indicator */}
                {message.reply_to && (
                    <div className={cn(
                        "text-xs text-muted-foreground border-l-2 pl-2 mb-2 max-w-full",
                        isOwn ? "border-r-2 border-l-0 pr-2 pl-0 text-right" : "border-primary"
                    )}>
                        <div className="font-medium">
                            {message.reply_to.sender.name}
                        </div>
                        <div className="truncate">
                            {message.reply_to.decrypted_content?.slice(0, 50)}...
                        </div>
                    </div>
                )}

                {/* Message bubble */}
                <div className={cn(
                    "rounded-2xl px-4 py-2 relative",
                    isOwn 
                        ? "bg-primary text-primary-foreground rounded-tr-sm" 
                        : "bg-muted text-foreground rounded-tl-sm"
                )}>
                    {renderMessageContent()}
                    
                    {/* Encryption indicator for sensitive messages */}
                    {message.type !== 'text' && (
                        <div className="flex items-center gap-1 mt-2 text-xs opacity-70">
                            <Lock className="h-3 w-3" />
                            <span>End-to-end encrypted</span>
                        </div>
                    )}

                    {/* Message actions (show on hover) */}
                    <div className={cn(
                        "absolute top-0 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1",
                        isOwn ? "-left-12" : "-right-12"
                    )}>
                        <Button variant="ghost" size="icon" className="h-6 w-6">
                            <Reply className="h-3 w-3" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-6 w-6">
                            <MoreHorizontal className="h-3 w-3" />
                        </Button>
                    </div>
                </div>

                {/* Reactions */}
                {renderReactions()}

                {/* Message metadata */}
                <div className={cn(
                    "flex items-center gap-1 mt-1 text-xs text-muted-foreground",
                    isOwn ? "flex-row-reverse" : "flex-row"
                )}>
                    <span>{formatTime(message.created_at)}</span>
                    
                    {message.is_edited && (
                        <>
                            <span>•</span>
                            <Edit2 className="h-3 w-3" />
                            <span>edited</span>
                        </>
                    )}
                    
                    {isOwn && (
                        <>
                            <span>•</span>
                            <CheckCheck className="h-3 w-3 text-blue-500" />
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}