import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ConversationCreator } from '@/components/ui/conversation-creator';
import { MessageSquarePlus, Search, Settings, Shield, Users } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Conversation {
    id: string;
    type: 'direct' | 'group' | 'channel';
    name?: string;
    description?: string;
    avatar_url?: string;
    unread_count: number;
    is_muted: boolean;
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
    }>;
    last_activity_at: string;
}

interface ChatSidebarProps {
    conversations: Conversation[];
    selectedConversationId: string | null;
    onConversationSelect: (id: string) => void;
    onNewConversation: (participants: string[], options?: any) => void;
    isLoading: boolean;
    error: string | null;
    currentUser: any;
}

export default function ChatSidebar({
    conversations,
    selectedConversationId,
    onConversationSelect,
    onNewConversation,
    isLoading,
    error,
    currentUser,
}: ChatSidebarProps) {
    const [searchQuery, setSearchQuery] = useState('');

    const filteredConversations = conversations.filter(conv => {
        if (!searchQuery) return true;
        
        const name = conv.name || getDirectMessageName(conv, currentUser);
        return name.toLowerCase().includes(searchQuery.toLowerCase());
    });

    const getDirectMessageName = (conversation: Conversation, currentUser: any): string => {
        if (conversation.type === 'direct') {
            const otherParticipant = conversation.participants.find(
                p => p.user.id !== currentUser.id
            );
            return otherParticipant?.user.name || 'Unknown User';
        }
        return conversation.name || `${conversation.type} Chat`;
    };

    const getConversationAvatar = (conversation: Conversation): string => {
        if (conversation.avatar_url) return conversation.avatar_url;
        
        if (conversation.type === 'direct') {
            const otherParticipant = conversation.participants.find(
                p => p.user.id !== currentUser.id
            );
            return otherParticipant?.user.avatar || '';
        }
        
        return '';
    };

    const formatLastActivity = (timestamp: string): string => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffMins < 1) return 'now';
        if (diffMins < 60) return `${diffMins}m`;
        if (diffHours < 24) return `${diffHours}h`;
        if (diffDays < 7) return `${diffDays}d`;
        
        return date.toLocaleDateString();
    };

    return (
        <div className="flex flex-col h-full bg-background border-r">
            {/* Header */}
            <div className="p-4 border-b">
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-lg font-semibold">Messages</h2>
                    <div className="flex items-center gap-2">
                        <ConversationCreator
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8"
                                >
                                    <MessageSquarePlus className="h-4 w-4" />
                                </Button>
                            }
                            onConversationCreated={(conversationId) => {
                                onConversationSelect(conversationId);
                            }}
                        />
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                            <Settings className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                
                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                    <Input
                        placeholder="Search conversations..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-9"
                    />
                </div>
            </div>

            {/* Error Display */}
            {error && (
                <div className="p-3 bg-destructive/10 text-destructive text-sm border-b">
                    {error}
                </div>
            )}

            {/* Conversations List */}
            <ScrollArea className="flex-1">
                <div className="p-2">
                    {isLoading && conversations.length === 0 && (
                        <div className="text-center py-8 text-muted-foreground">
                            Loading conversations...
                        </div>
                    )}

                    {!isLoading && filteredConversations.length === 0 && (
                        <div className="text-center py-8 text-muted-foreground">
                            {searchQuery ? 'No conversations found' : 'No conversations yet'}
                        </div>
                    )}

                    {filteredConversations.map((conversation) => (
                        <div
                            key={conversation.id}
                            onClick={() => onConversationSelect(conversation.id)}
                            className={cn(
                                "flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors hover:bg-accent",
                                selectedConversationId === conversation.id && "bg-accent"
                            )}
                        >
                            <div className="relative">
                                <Avatar className="h-10 w-10">
                                    <AvatarImage src={getConversationAvatar(conversation)} />
                                    <AvatarFallback>
                                        {getDirectMessageName(conversation, currentUser)
                                            .split(' ')
                                            .map(n => n[0])
                                            .join('')
                                            .slice(0, 2)
                                            .toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                
                                {/* Encryption indicators */}
                                <div className="absolute -bottom-1 -right-1 flex gap-0.5">
                                    {conversation.encryption_status.is_encrypted && (
                                        <div 
                                            className={cn(
                                                "w-3 h-3 rounded-full border-2 border-background",
                                                conversation.encryption_status.quantum_ready 
                                                    ? "bg-green-500" 
                                                    : "bg-blue-500"
                                            )}
                                            title={conversation.encryption_status.quantum_ready 
                                                ? "Quantum-resistant encryption" 
                                                : "End-to-end encrypted"
                                            }
                                        />
                                    )}
                                </div>
                            </div>

                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-medium truncate">
                                        {getDirectMessageName(conversation, currentUser)}
                                    </h3>
                                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                        {formatLastActivity(conversation.last_activity_at)}
                                    </div>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {conversation.type === 'group' && (
                                            <Users className="h-3 w-3 text-muted-foreground" />
                                        )}
                                        {conversation.encryption_status.quantum_ready && (
                                            <Shield className="h-3 w-3 text-green-500" />
                                        )}
                                        <span className="text-sm text-muted-foreground truncate">
                                            {conversation.encryption_status.algorithm.toUpperCase()}
                                        </span>
                                    </div>
                                    
                                    {conversation.unread_count > 0 && (
                                        <Badge 
                                            variant="default" 
                                            className="h-5 min-w-[20px] text-xs px-1.5"
                                        >
                                            {conversation.unread_count > 99 ? '99+' : conversation.unread_count}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </ScrollArea>

            {/* Quick Actions */}
            <div className="p-3 border-t">
                <ConversationCreator
                    trigger={
                        <Button className="w-full" size="sm">
                            <MessageSquarePlus className="h-4 w-4 mr-2" />
                            New Chat
                        </Button>
                    }
                    onConversationCreated={(conversationId) => {
                        onConversationSelect(conversationId);
                    }}
                />
            </div>
        </div>
    );
}