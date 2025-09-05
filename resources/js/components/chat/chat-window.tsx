import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { 
    Send, 
    Paperclip, 
    Mic, 
    Shield, 
    Phone, 
    Video, 
    MoreVertical,
    Lock,
    Users
} from 'lucide-react';
import { cn } from '@/lib/utils';
import MessageBubble from './message-bubble';

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
    isLoadingMessages: boolean;
    error: string | null;
    currentUser: any;
}

export default function ChatWindow({
    conversation,
    messages,
    onSendMessage,
    isLoadingMessages,
    error,
    currentUser,
}: ChatWindowProps) {
    const [messageInput, setMessageInput] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [isRecording, setIsRecording] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom when new messages arrive
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleSendMessage = async () => {
        if (!messageInput.trim() || isSending || !conversation) return;

        setIsSending(true);
        try {
            await onSendMessage(messageInput.trim());
            setMessageInput('');
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
                        />
                    ))}
                    <div ref={messagesEndRef} />
                </div>
            </ScrollArea>

            {/* Input Area */}
            <div className="p-4 border-t bg-background">
                {/* Encryption Status */}
                {conversation.encryption_status.is_encrypted && (
                    <div className="flex items-center justify-center mb-2 text-xs text-muted-foreground">
                        <Lock className="h-3 w-3 mr-1" />
                        Messages are secured with {conversation.encryption_status.algorithm.toUpperCase()}
                        {conversation.encryption_status.quantum_ready && " (Quantum-Safe)"}
                        encryption
                    </div>
                )}
                
                <div className="flex items-end gap-2">
                    <Button variant="ghost" size="icon" className="mb-2">
                        <Paperclip className="h-4 w-4" />
                    </Button>
                    
                    <div className="flex-1">
                        <Input
                            placeholder="Type a message..."
                            value={messageInput}
                            onChange={(e) => setMessageInput(e.target.value)}
                            onKeyPress={handleKeyPress}
                            disabled={isSending}
                            className="min-h-[40px]"
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
                        disabled={!messageInput.trim() || isSending}
                        className="mb-2"
                    >
                        <Send className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}