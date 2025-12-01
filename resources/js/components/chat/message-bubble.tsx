import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
    CheckCheck, 
    Lock,
    Reply,
    Edit2,
    Phone,
    PhoneCall,
    FileText,
    Image,
    Video,
    Music,
    Mic,
    Download,
    Play,
    AlertCircle,
    Loader2
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { MessageContextMenu } from './MessageContextMenu';
import type { Message, Conversation } from '@/types/chat';

interface MessageBubbleProps {
    message: Message;
    isOwn: boolean;
    currentUser: {
        id: string;
        name: string;
        [key: string]: unknown;
    };
    conversations?: Conversation[];
    onReply?: (message: Message) => void;
    onEdit?: (messageId: string, content: string) => void;
    onDelete?: (messageId: string) => void;
    onForward?: (messageId: string, conversationIds: string[]) => void;
    onAddReaction?: (messageId: string, emoji: string) => void;
    onPin?: (messageId: string) => void;
    onUnpin?: (messageId: string) => void;
    onBookmark?: (messageId: string) => void;
    onUnbookmark?: (messageId: string) => void;
    onFlag?: (messageId: string) => void;
    onUnflag?: (messageId: string) => void;
    onDownload?: (messageId: string) => void;
    onQuote?: (message: Message) => void;
}

export default function MessageBubble({ 
    message, 
    isOwn, 
    currentUser,
    conversations = [],
    onReply = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onForward = () => {},
    onAddReaction = () => {},
    onPin,
    onUnpin,
    onBookmark,
    onUnbookmark,
    onFlag,
    onUnflag,
    onDownload,
    onQuote,
}: MessageBubbleProps) {
    const formatTime = (timestamp: string): string => {
        return new Date(timestamp).toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    };

    const getInitials = (name: string): string => {
        return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    };

    const renderDecryptionStatus = () => {
        if (message.decryption_failed) {
            return (
                <div className="flex items-center gap-2 text-destructive text-sm">
                    <AlertCircle className="h-4 w-4" />
                    <span>Decryption failed</span>
                    {message.decryption_error && (
                        <span className="text-xs">({message.decryption_error})</span>
                    )}
                </div>
            );
        }

        if (!message.decrypted_content && (message.type === 'text' || !message.type)) {
            return (
                <div className="flex items-center gap-2 text-muted-foreground text-sm">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>Decrypting...</span>
                </div>
            );
        }

        return null;
    };

    const formatFileSize = (bytes: number): string => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${Math.round(size * 10) / 10} ${units[unitIndex]}`;
    };

    const formatDuration = (seconds: number): string => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const renderFileContent = () => {
        const attachment = message.attachments?.[0];
        const metadata = message.metadata;
        
        return (
            <div className="bg-muted rounded-lg p-4">
                <div className="flex items-start gap-3">
                    <div className="flex-shrink-0">
                        <FileText className="h-8 w-8 text-muted-foreground" />
                    </div>
                    <div className="flex-1 min-w-0">
                        <div className="font-medium truncate">
                            {attachment?.filename || metadata?.filename || 'Encrypted File'}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {metadata?.file_size && formatFileSize(metadata.file_size)}
                            {metadata?.mime_type && ` • ${metadata.mime_type}`}
                        </div>
                        {message.decrypted_content && (
                            <div className="text-sm mt-2">{message.decrypted_content}</div>
                        )}
                    </div>
                    <Button variant="ghost" size="icon" className="flex-shrink-0">
                        <Download className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        );
    };

    const renderImageContent = () => {
        const attachment = message.attachments?.[0];
        
        return (
            <div className="space-y-2">
                <div className="bg-muted rounded-lg overflow-hidden">
                    {attachment?.thumbnail_path ? (
                        <div className="relative">
                            <img 
                                src={attachment.thumbnail_path} 
                                alt={attachment.filename || 'Image'}
                                className="w-full max-w-sm rounded-lg"
                            />
                            <div className="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                <Lock className="h-6 w-6 text-white" />
                            </div>
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <Image className="h-12 w-12 mx-auto text-muted-foreground mb-2" />
                            <div className="font-medium">Encrypted Image</div>
                            <div className="text-sm text-muted-foreground">
                                {message.metadata?.filename || 'Loading preview...'}
                            </div>
                        </div>
                    )}
                </div>
                {message.decrypted_content && (
                    <div className="text-sm">{message.decrypted_content}</div>
                )}
            </div>
        );
    };

    const renderVideoContent = () => {
        const attachment = message.attachments?.[0];
        const metadata = message.metadata;
        
        return (
            <div className="space-y-2">
                <div className="bg-muted rounded-lg p-6 text-center">
                    <Video className="h-12 w-12 mx-auto text-muted-foreground mb-2" />
                    <div className="font-medium">
                        {attachment?.filename || metadata?.filename || 'Encrypted Video'}
                    </div>
                    <div className="text-sm text-muted-foreground space-x-2">
                        {metadata?.duration && <span>{formatDuration(metadata.duration)}</span>}
                        {metadata?.file_size && <span>• {formatFileSize(metadata.file_size)}</span>}
                    </div>
                </div>
                {message.decrypted_content && (
                    <div className="text-sm">{message.decrypted_content}</div>
                )}
            </div>
        );
    };

    const renderAudioContent = () => {
        const metadata = message.metadata;
        
        return (
            <div className="bg-muted rounded-lg p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" className="flex-shrink-0">
                        <Play className="h-4 w-4" />
                    </Button>
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <Music className="h-4 w-4 text-muted-foreground" />
                            <span className="font-medium">Audio Message</span>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {metadata?.duration && formatDuration(metadata.duration)}
                            {metadata?.file_size && ` • ${formatFileSize(metadata.file_size)}`}
                        </div>
                    </div>
                </div>
                {message.decrypted_content && (
                    <div className="text-sm mt-2">{message.decrypted_content}</div>
                )}
            </div>
        );
    };

    const renderVoiceContent = () => {
        const metadata = message.metadata;
        
        return (
            <div className="bg-muted rounded-lg p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" className="flex-shrink-0">
                        <Play className="h-4 w-4" />
                    </Button>
                    <div className="flex-1 flex items-center gap-2">
                        <Mic className="h-4 w-4 text-primary" />
                        <div className="flex-1 bg-primary/20 h-2 rounded-full">
                            <div className="bg-primary h-full w-1/3 rounded-full"></div>
                        </div>
                        <span className="text-sm font-mono">
                            {metadata?.voice_duration_seconds ? 
                                formatDuration(metadata.voice_duration_seconds) : '0:00'}
                        </span>
                    </div>
                </div>
            </div>
        );
    };

    const renderPollContent = () => {
        const pollOptions = message.metadata?.poll_options || [];
        
        return (
            <div className="bg-muted rounded-lg p-4">
                <div className="flex items-center gap-2 mb-3">
                    <div className="text-lg">📊</div>
                    <div className="font-medium">Poll</div>
                </div>
                
                {message.decrypted_content ? (
                    <div>
                        <div className="text-sm mb-3">{message.decrypted_content}</div>
                        {pollOptions.length > 0 && (
                            <div className="space-y-2">
                                {pollOptions.map((option, index) => (
                                    <div key={option.id || index} className="bg-background rounded p-2">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm">{option.text}</span>
                                            <Badge variant="secondary" className="text-xs">
                                                {option.votes} votes
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-sm text-muted-foreground">
                        Encrypted poll content
                    </div>
                )}
            </div>
        );
    };

    const renderSystemContent = () => {
        return (
            <div className="text-center py-2">
                <div className="text-sm text-muted-foreground bg-muted/50 rounded-full px-3 py-1 inline-block">
                    {message.decrypted_content || message.encrypted_content}
                </div>
            </div>
        );
    };

    const getCallStatusText = (status: string): string => {
        switch (status) {
            case 'missed': return 'Missed call';
            case 'answered': return 'Call ended';
            case 'declined': return 'Call declined';
            case 'busy': return 'Line busy';
            default: return 'Call';
        }
    };

    const renderCallContent = () => {
        const metadata = message.metadata;
        const callStatus = metadata?.call_status || 'unknown';
        const duration = metadata?.call_duration;
        
        const getCallIcon = () => {
            switch (callStatus) {
                case 'missed':
                    return <Phone className="h-4 w-4 text-destructive" />;
                case 'answered':
                    return <PhoneCall className="h-4 w-4 text-green-600" />;
                case 'declined':
                case 'busy':
                    return <Phone className="h-4 w-4 text-muted-foreground" />;
                default:
                    return <Phone className="h-4 w-4" />;
            }
        };
        
        return (
            <div className="bg-muted rounded-lg p-4">
                <div className="flex items-center gap-3">
                    {getCallIcon()}
                    <div>
                        <div className="font-medium capitalize">
                            {getCallStatusText(callStatus)}
                        </div>
                        {duration && callStatus === 'answered' && (
                            <div className="text-sm text-muted-foreground">
                                Duration: {formatDuration(duration)}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    const renderMessageContent = () => {
        const decryptionStatus = renderDecryptionStatus();
        if (decryptionStatus) {
            return decryptionStatus;
        }

        switch (message.type || 'text') {
            case 'text':
                return (
                    <div className="whitespace-pre-wrap break-words">
                        {message.decrypted_content || 'Message content unavailable'}
                    </div>
                );
            
            case 'image':
                return renderImageContent();
            
            case 'video':
                return renderVideoContent();
                
            case 'audio':
                return renderAudioContent();
            
            case 'file':
                return renderFileContent();
            
            case 'voice':
                return renderVoiceContent();
            
            case 'poll':
                return renderPollContent();
                
            case 'system':
                return renderSystemContent();
                
            case 'call':
                return renderCallContent();
            
            default:
                return (
                    <div className="text-muted-foreground text-sm flex items-center gap-2">
                        <AlertCircle className="h-4 w-4" />
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
        <div 
            className={cn(
                "flex gap-3 group",
                isOwn ? "flex-row-reverse" : "flex-row"
            )}
            role="listitem"
            onContextMenu={(e) => {
                e.preventDefault();
                // Context menu will be handled by the wrapper if needed
            }}
        >
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
                "flex flex-col max-w-[70%] relative",
                isOwn ? "items-end" : "items-start"
            )}>
                {/* Sender name (only for group chats and not own messages) */}
                {!isOwn && (
                    <div className="text-xs text-muted-foreground mb-1 px-1">
                        {message.sender.name}
                    </div>
                )}

                {/* Reply indicator - Telegram style */}
                {message.reply_to && (
                    <div className={cn(
                        "text-sm border-l-4 border-primary/60 pl-2 mb-2 max-w-full bg-muted/30 rounded p-2",
                        isOwn ? "border-r-4 border-l-0 pr-2 pl-0 text-right" : ""
                    )}>
                        <div className="font-medium text-primary mb-1 truncate">
                            {message.reply_to.sender?.name || 'Unknown User'}
                        </div>
                        <div className="text-foreground/80 truncate leading-tight">
                            {message.reply_to.decrypted_content || '[Encrypted message]'}
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
                    {message.type && message.type !== 'text' && (
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
                        <Button 
                            variant="ghost" 
                            size="icon" 
                            className="h-6 w-6"
                            onClick={() => onReply(message)}
                        >
                            <Reply className="h-3 w-3" />
                        </Button>
                        <MessageContextMenu
                            message={message}
                            currentUserId={currentUser?.id || ''}
                            conversations={conversations}
                            onReply={onReply}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            onForward={onForward}
                            onAddReaction={onAddReaction}
                            onPin={onPin}
                            onUnpin={onUnpin}
                            onBookmark={onBookmark}
                            onUnbookmark={onUnbookmark}
                            onFlag={onFlag}
                            onUnflag={onUnflag}
                            onDownload={onDownload}
                            onQuote={onQuote}
                        />
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