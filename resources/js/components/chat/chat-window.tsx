import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Send,
    Mic,
    Shield,
    Phone,
    Video,
    MoreVertical,
    Lock,
    Users,
    Reply,
    X,
    Image,
    FileText,
    Music,
    Film
} from 'lucide-react';
import { cn } from '@/lib/utils';
import MessageBubble from './message-bubble';
import RichTextEditor from './RichTextEditor';

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

interface Conversation {
    id: string;
    type: 'direct' | 'group' | 'channel';
    name?: string;
    description?: string;
    avatar_url?: string;
    encryption_status: {
        is_encrypted: boolean;
        algorithm: string;
        quantum_ready: boolean;
    };
    participants: Array<{
        user: {
            id: string;
            name: string;
            avatar: string;
        };
        role: string;
    }>;
}

interface ChatWindowProps {
    conversation: Conversation | null;
    messages: Message[];
    onSendMessage: (content: string, options?: any) => Promise<void>;
    onReplyMessage?: (message: Message) => void;
    onEditMessage?: (messageId: string, content: string) => Promise<void>;
    onDeleteMessage?: (messageId: string) => Promise<void>;
    onForwardMessage?: (messageId: string, conversationIds: string[]) => Promise<void>;
    onAddReaction?: (messageId: string, emoji: string) => Promise<void>;
    onRemoveReaction?: (messageId: string, emoji: string) => Promise<void>;
    isLoadingMessages: boolean;
    error: string | null;
    currentUser: any;
    conversations?: Conversation[];
}

export default function ChatWindow({
    conversation,
    messages,
    onSendMessage,
    onReplyMessage,
    onEditMessage,
    onDeleteMessage,
    onForwardMessage,
    onAddReaction,
    onRemoveReaction,
    isLoadingMessages,
    error,
    currentUser,
    conversations = [],
}: ChatWindowProps) {
    const [messageInput, setMessageInput] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [isRecording, setIsRecording] = useState(false);
    const [replyToMessage, setReplyToMessage] = useState<Message | null>(null);
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom when new messages arrive
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleSendMessage = async () => {
        if ((!messageInput.trim() && selectedFiles.length === 0) || isSending || !conversation) return;

        setIsSending(true);
        try {
            const options = replyToMessage ? { reply_to_id: replyToMessage.id } : undefined;
            
            // Handle text message
            if (messageInput.trim()) {
                await onSendMessage(messageInput.trim(), options);
            }
            
            // Handle file uploads
            if (selectedFiles.length > 0) {
                console.log('Sending files:', selectedFiles);
                // TODO: Implement actual E2EE file upload
            }
            
            // Clear inputs
            setMessageInput('');
            setReplyToMessage(null);
            setSelectedFiles([]);
        } catch (error) {
            console.error('Failed to send message:', error);
        } finally {
            setIsSending(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    };

    // File handling functions
    const getFileIcon = (file: File) => {
        if (file.type.startsWith('image/')) return <Image className="h-4 w-4 text-blue-500" />;
        if (file.type.startsWith('video/')) return <Film className="h-4 w-4 text-red-500" />;
        if (file.type.startsWith('audio/')) return <Music className="h-4 w-4 text-purple-500" />;
        if (file.type.includes('pdf') || file.type.includes('document') || file.type.includes('text')) {
            return <FileText className="h-4 w-4 text-green-500" />;
        }
        return <FileText className="h-4 w-4 text-gray-500" />;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handleFilesSelected = (files: File[]) => {
        setSelectedFiles(files);
    };

    const removeFile = (index: number) => {
        setSelectedFiles(prev => prev.filter((_, i) => i !== index));
    };

    const clearAllFiles = () => {
        setSelectedFiles([]);
    };

    const getConversationTitle = (): string => {
        if (!conversation) return '';

        if (conversation.name) return conversation.name;

        if (conversation.type === 'direct') {
            const otherParticipant = conversation.participants.find(
                p => p.user.id !== currentUser.id
            );
            return otherParticipant?.user.name || 'Unknown User';
        }

        return 'Group Chat';
    };

    const getParticipantsText = (): string => {
        if (!conversation) return '';

        if (conversation.type === 'direct') {
            return 'Direct message';
        }

        return `${conversation.participants.length} participants`;
    };

    // Message action handlers
    const handleReply = (message: Message) => {
        setReplyToMessage(message);
        if (onReplyMessage) {
            onReplyMessage(message);
        }
    };

    const handleEdit = async (messageId: string, content: string) => {
        if (!onEditMessage || !conversation) return;
        try {
            await onEditMessage(messageId, content);
        } catch (error) {
            console.error('Failed to edit message:', error);
        }
    };

    const handleDelete = async (messageId: string) => {
        if (!onDeleteMessage || !conversation) return;
        try {
            await onDeleteMessage(messageId);
        } catch (error) {
            console.error('Failed to delete message:', error);
        }
    };

    const handleForward = async (messageId: string, conversationIds: string[]) => {
        if (!onForwardMessage || !conversation) return;
        try {
            await onForwardMessage(messageId, conversationIds);
        } catch (error) {
            console.error('Failed to forward message:', error);
        }
    };

    const handleAddReaction = async (messageId: string, emoji: string) => {
        if (!onAddReaction || !conversation) return;
        try {
            await onAddReaction(messageId, emoji);
        } catch (error) {
            console.error('Failed to add reaction:', error);
        }
    };

    if (!conversation) {
        return (
            <div className="flex items-center justify-center h-full bg-muted/10">
                <div className="text-center">
                    <div className="text-6xl mb-4">💬</div>
                    <h3 className="text-xl font-semibold mb-2">Welcome to E2EE Chat</h3>
                    <p className="text-muted-foreground">
                        Select a conversation to start chatting securely
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-full">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b bg-background">
                <div className="flex items-center gap-3">
                    <Avatar className="h-10 w-10">
                        <AvatarImage src={conversation.avatar_url} />
                        <AvatarFallback>
                            {getConversationTitle()
                                .split(' ')
                                .map(n => n[0])
                                .join('')
                                .slice(0, 2)
                                .toUpperCase()}
                        </AvatarFallback>
                    </Avatar>

                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h2 className="font-semibold">{getConversationTitle()}</h2>
                            {conversation.encryption_status.is_encrypted && (
                                <div className="flex items-center gap-1">
                                    <Lock className="h-3 w-3 text-green-600" />
                                    {conversation.encryption_status.quantum_ready && (
                                        <Shield className="h-3 w-3 text-green-600" />
                                    )}
                                </div>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground flex items-center gap-2">
                            {conversation.type === 'group' && (
                                <Users className="h-3 w-3" />
                            )}
                            {getParticipantsText()}
                            {conversation.encryption_status.is_encrypted && (
                                <Badge variant="secondary" className="text-xs">
                                    {conversation.encryption_status.quantum_ready ? 'Quantum-Safe' : 'E2EE'}
                                </Badge>
                            )}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="icon">
                        <Phone className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon">
                        <Video className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon">
                        <MoreVertical className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Messages Area */}
            <ScrollArea className="flex-1 p-4">
                {isLoadingMessages && (
                    <div className="text-center py-8 text-muted-foreground">
                        Loading messages...
                    </div>
                )}

                {error && (
                    <div className="text-center py-4 text-destructive">
                        Error loading messages: {error}
                    </div>
                )}

                <div className="space-y-4">
                    {messages.map((message) => (
                        <MessageBubble
                            key={message.id}
                            message={message}
                            isOwn={message.sender_id === currentUser.id}
                            currentUser={currentUser}
                            conversations={conversations}
                            onReply={handleReply}
                            onEdit={handleEdit}
                            onDelete={handleDelete}
                            onForward={handleForward}
                            onAddReaction={handleAddReaction}
                        />
                    ))}
                    <div ref={messagesEndRef} />
                </div>
            </ScrollArea>

            {/* Input Area */}
            <div className="p-4 border-t bg-background">
                {/* Reply Preview */}
                {replyToMessage && (
                    <div className="mb-3 bg-muted/50 border-l-4 border-primary p-3 rounded">
                        <div className="flex items-center justify-between mb-1">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Reply className="h-3 w-3" />
                                Replying to {replyToMessage.sender.name}
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 w-6 p-0"
                                onClick={() => setReplyToMessage(null)}
                            >
                                <X className="h-3 w-3" />
                            </Button>
                        </div>
                        <div className="text-sm text-muted-foreground truncate">
                            {replyToMessage.decrypted_content?.slice(0, 100)}
                            {(replyToMessage.decrypted_content?.length || 0) > 100 && '...'}
                        </div>
                    </div>
                )}

                {/* Encryption Status */}
                {conversation.encryption_status.is_encrypted && (
                    <div className="flex items-center justify-center mb-2 text-xs text-muted-foreground">
                        <Lock className="h-3 w-3 mr-1" />
                        Messages are secured with {conversation.encryption_status.algorithm.toUpperCase()}
                        {conversation.encryption_status.quantum_ready && " (Quantum-Safe)"}
                        encryption
                    </div>
                )}

                {/* Selected Files Preview */}
                {selectedFiles.length > 0 && (
                    <div className="mb-3 p-3 bg-muted/20 border border-border rounded-lg">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-medium text-muted-foreground">
                                {selectedFiles.length} file{selectedFiles.length > 1 ? 's' : ''} selected
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearAllFiles}
                                className="h-6 px-2 text-xs"
                            >
                                Clear all
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 gap-2 max-h-32 overflow-y-auto">
                            {selectedFiles.map((file, index) => (
                                <div key={index} className="flex items-center gap-2 p-2 bg-background border border-border rounded text-sm">
                                    {getFileIcon(file)}
                                    <div className="flex-1 min-w-0">
                                        <div className="font-medium truncate">{file.name}</div>
                                        <div className="text-xs text-muted-foreground">{formatFileSize(file.size)}</div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeFile(index)}
                                        className="h-6 w-6 p-0 flex-shrink-0"
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="flex items-end gap-2">
                    <div className="flex-1">
                        <RichTextEditor
                            placeholder="Type a message...347"
                            content={messageInput}
                            onUpdate={(content, text) => setMessageInput(text)}
                            onSubmit={(content, text) => {
                                handleSendMessage();
                            }}
                            onFilesSelected={handleFilesSelected}
                            disabled={isSending}
                            showToolbar={false}
                            minHeight={40}
                            maxHeight={120}
                            autoFocus={true}
                        />
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        className="mb-2"
                        onMouseDown={() => setIsRecording(true)}
                        onMouseUp={() => setIsRecording(false)}
                        onMouseLeave={() => setIsRecording(false)}
                    >
                        <Mic className={cn(
                            "h-4 w-4",
                            isRecording && "text-red-500"
                        )} />
                    </Button>

                    <Button
                        onClick={handleSendMessage}
                        disabled={(!messageInput.trim() && selectedFiles.length === 0) || isSending}
                        className="mb-2"
                    >
                        <Send className="h-4 w-4" />
                    </Button>
                </div>
            </div>

        </div>
    );
}
