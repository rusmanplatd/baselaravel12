import React, { useState } from 'react';
import { 
    Eye, 
    MessageSquare, 
    Share, 
    MoreVertical, 
    Pin, 
    Copy, 
    Flag,
    ThumbsUp,
    ThumbsDown,
    Heart,
    Smile
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuSeparator, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import { formatDistanceToNow } from 'date-fns';

interface Message {
    id: string;
    content: string;
    message_type: string;
    created_at: string;
    is_pinned: boolean;
    views_count?: number;
    reactions?: Array<{
        type: string;
        count: number;
        users: string[];
    }>;
    attachments?: Array<{
        id: string;
        type: 'image' | 'video' | 'file';
        url: string;
        name: string;
        size?: number;
    }>;
    sender: {
        id: string;
        name: string;
        avatar_url?: string;
    };
    broadcast_id?: string;
}

interface ChannelMessageProps {
    message: Message;
    isChannel?: boolean;
    canReact?: boolean;
    canPin?: boolean;
    onReaction: (messageId: string, reaction: string) => void;
    onPin: (messageId: string) => void;
    onShare: (messageId: string) => void;
    onReport?: (messageId: string) => void;
    className?: string;
}

export default function ChannelMessage({ 
    message, 
    isChannel = false,
    canReact = true, 
    canPin = false,
    onReaction, 
    onPin, 
    onShare, 
    onReport,
    className 
}: ChannelMessageProps) {
    const [showReactions, setShowReactions] = useState(false);

    const formatViews = (views: number): string => {
        if (views >= 1000000) return `${(views / 1000000).toFixed(1)}M`;
        if (views >= 1000) return `${(views / 1000).toFixed(1)}K`;
        return views.toString();
    };

    const handleCopyMessage = async () => {
        try {
            await navigator.clipboard.writeText(message.content);
            toast.success('Message copied to clipboard');
        } catch (err) {
            toast.error('Failed to copy message');
        }
    };

    const handleCopyLink = async () => {
        const messageUrl = `${window.location.href}/${message.id}`;
        try {
            await navigator.clipboard.writeText(messageUrl);
            toast.success('Message link copied to clipboard');
        } catch (err) {
            toast.error('Failed to copy link');
        }
    };

    const quickReactions = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    const renderAttachment = (attachment: NonNullable<Message['attachments']>[0]) => {
        switch (attachment.type) {
            case 'image':
                return (
                    <img
                        key={attachment.id}
                        src={attachment.url}
                        alt={attachment.name}
                        className="max-w-sm max-h-64 rounded-lg object-cover cursor-pointer hover:opacity-90 transition-opacity"
                        onClick={() => window.open(attachment.url, '_blank')}
                    />
                );
            case 'video':
                return (
                    <video
                        key={attachment.id}
                        src={attachment.url}
                        controls
                        className="max-w-sm max-h-64 rounded-lg"
                        preload="metadata"
                    >
                        Your browser does not support the video tag.
                    </video>
                );
            case 'file':
                return (
                    <div key={attachment.id} className="flex items-center space-x-2 p-3 border rounded-lg bg-gray-50 hover:bg-gray-100 cursor-pointer">
                        <div className="flex-1">
                            <p className="font-medium text-sm">{attachment.name}</p>
                            {attachment.size && (
                                <p className="text-xs text-gray-500">
                                    {(attachment.size / 1024 / 1024).toFixed(2)} MB
                                </p>
                            )}
                        </div>
                        <Button size="sm" variant="outline" onClick={() => window.open(attachment.url, '_blank')}>
                            Download
                        </Button>
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <div className={`group relative ${className}`}>
            {/* Pinned Message Indicator */}
            {message.is_pinned && (
                <div className="flex items-center space-x-2 text-xs text-amber-600 mb-2 px-4">
                    <Pin className="h-3 w-3" />
                    <span>Pinned message</span>
                </div>
            )}

            <div className="flex space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                {/* Sender Avatar */}
                <Avatar className="h-10 w-10 flex-shrink-0">
                    <AvatarImage src={message.sender.avatar_url} alt={message.sender.name} />
                    <AvatarFallback>
                        {message.sender.name.slice(0, 2).toUpperCase()}
                    </AvatarFallback>
                </Avatar>

                {/* Message Content */}
                <div className="flex-1 min-w-0">
                    {/* Sender Info & Timestamp */}
                    <div className="flex items-center space-x-2 mb-1">
                        <span className="font-semibold text-sm text-gray-900">
                            {message.sender.name}
                        </span>
                        {message.message_type === 'broadcast' && (
                            <Badge variant="secondary" className="text-xs">
                                Broadcast
                            </Badge>
                        )}
                        <span className="text-xs text-gray-500">
                            {formatDistanceToNow(new Date(message.created_at), { addSuffix: true })}
                        </span>
                    </div>

                    {/* Message Text */}
                    <div className="text-gray-800 text-sm whitespace-pre-wrap mb-2">
                        {message.content}
                    </div>

                    {/* Attachments */}
                    {message.attachments && message.attachments.length > 0 && (
                        <div className="space-y-2 mb-3">
                            {message.attachments.map(renderAttachment)}
                        </div>
                    )}

                    {/* Reactions */}
                    {message.reactions && message.reactions.length > 0 && (
                        <div className="flex flex-wrap gap-1 mb-2">
                            {message.reactions.map((reaction, index) => (
                                <Button
                                    key={index}
                                    variant="outline"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() => onReaction(message.id, reaction.type)}
                                >
                                    <span className="mr-1">{reaction.type}</span>
                                    <span>{reaction.count}</span>
                                </Button>
                            ))}
                        </div>
                    )}

                    {/* Message Actions */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4 text-xs text-gray-500">
                            {/* View Count */}
                            {message.views_count !== undefined && (
                                <div className="flex items-center space-x-1">
                                    <Eye className="h-3 w-3" />
                                    <span>{formatViews(message.views_count)}</span>
                                </div>
                            )}
                        </div>

                        {/* Action Buttons */}
                        <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            {/* Quick Reactions */}
                            {canReact && (
                                <div className="flex items-center space-x-1">
                                    {quickReactions.slice(0, 3).map((emoji) => (
                                        <Button
                                            key={emoji}
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 w-7 p-0 text-sm hover:bg-gray-100"
                                            onClick={() => onReaction(message.id, emoji)}
                                        >
                                            {emoji}
                                        </Button>
                                    ))}
                                    
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                            >
                                                <Smile className="h-3 w-3" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent className="p-2">
                                            <div className="grid grid-cols-6 gap-1">
                                                {quickReactions.map((emoji) => (
                                                    <Button
                                                        key={emoji}
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8 w-8 p-0"
                                                        onClick={() => onReaction(message.id, emoji)}
                                                    >
                                                        {emoji}
                                                    </Button>
                                                ))}
                                            </div>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            )}

                            {/* Share Button */}
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 p-0"
                                onClick={() => onShare(message.id)}
                            >
                                <Share className="h-3 w-3" />
                            </Button>

                            {/* More Actions Menu */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                    >
                                        <MoreVertical className="h-3 w-3" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuItem onClick={handleCopyMessage}>
                                        <Copy className="h-4 w-4 mr-2" />
                                        Copy Message
                                    </DropdownMenuItem>
                                    
                                    <DropdownMenuItem onClick={handleCopyLink}>
                                        <Copy className="h-4 w-4 mr-2" />
                                        Copy Link
                                    </DropdownMenuItem>

                                    {canPin && (
                                        <DropdownMenuItem onClick={() => onPin(message.id)}>
                                            <Pin className="h-4 w-4 mr-2" />
                                            {message.is_pinned ? 'Unpin Message' : 'Pin Message'}
                                        </DropdownMenuItem>
                                    )}

                                    {onReport && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem 
                                                onClick={() => onReport(message.id)}
                                                className="text-red-600 focus:text-red-600"
                                            >
                                                <Flag className="h-4 w-4 mr-2" />
                                                Report Message
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}