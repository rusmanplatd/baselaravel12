import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useE2EEChat } from '@/hooks/useE2EEChat';
import { useWebSocket } from '@/hooks/useWebSocket';
import { useMentions } from '@/hooks/useMentions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import RichTextEditor from './RichTextEditor';
import type { JSONContent } from '@tiptap/react';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    Send, 
    Paperclip, 
    Shield, 
    ShieldCheck, 
    MoreVertical, 
    Smile,
    Reply,
    Edit3,
    Trash2,
    Lock,
    Unlock,
    Search,
    Phone,
    Video,
    Users,
    Pin,
    Archive,
    File,
    Download,
    Mic,
    StopCircle,
    Quote,
    AlertTriangle
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDistance } from 'date-fns';
import { toast } from 'sonner';

interface Attachment {
    id: string;
    type: 'image' | 'video' | 'file';
    filename: string;
    file_size: number;
    url?: string;
}

interface MessageUser {
    id: string;
    name: string;
    avatar: string;
    avatar_url?: string;
}

interface ConversationParticipant {
    user_id: string;
    role: string;
    is_active: boolean;
    user?: MessageUser;
}

interface Message {
    id: string;
    sender: MessageUser;
    decrypted_content: string;
    created_at: string;
    is_edited?: boolean;
    reactions?: Array<{ id: string; emoji: string }>;
    attachments?: Attachment[];
}

interface ChatInterfaceProps {
    readonly initialConversationId?: string;
}

export function ChatInterface({ initialConversationId }: ChatInterfaceProps) {
    const {
        conversations,
        messages,
        currentConversation,
        isLoading,
        isLoadingMessages,
        error,
        loadConversations,
        loadConversation,
        loadMessages,
        sendMessage,
        addReaction,
        editMessage,
        deleteMessage,
        subscribeToConversation,
        unsubscribeFromConversation,
        uploadFile,
    } = useE2EEChat();

    const [messageInput, setMessageInput] = useState('');
    const [messageContent, setMessageContent] = useState<JSONContent | null>(null);
    const [selectedConversationId, setSelectedConversationId] = useState<string | null>(initialConversationId || null);
    const [editingMessageId, setEditingMessageId] = useState<string | null>(null);
    const [editingContent, setEditingContent] = useState('');
    const [showEmojiPicker, setShowEmojiPicker] = useState<string | null>(null);
    const [replyingTo, setReplyingTo] = useState<Message | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [showSearch, setShowSearch] = useState(false);
    const [selectedTab, setSelectedTab] = useState<'messages' | 'media' | 'files' | 'links'>('messages');
    const [showParticipants, setShowParticipants] = useState(false);
    const [typingUsers, setTypingUsers] = useState<string[]>([]);
    const [isRecording, setIsRecording] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // WebSocket connection for real-time features
    const { sendMessage: sendWebSocketMessage } = useWebSocket({
        url: selectedConversationId ? `ws://localhost:8080/ws/conversations/${selectedConversationId}` : '',
        options: {
            onMessage: handleWebSocketMessage,
        },
        enabled: !!selectedConversationId
    });

    // Mentions hook for user and channel suggestions
    const { users: mentionableUsers, channels: mentionableChannels } = useMentions({
        conversationId: selectedConversationId || undefined,
        organizationId: currentConversation?.organization_id
    });

    // WebSocket message handler
    function handleWebSocketMessage(event: MessageEvent) {
        try {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'message':
                    loadMessages(selectedConversationId!);
                    break;
                case 'typing_start':
                    setTypingUsers(prev => [...prev.filter(id => id !== data.user_id), data.user_id]);
                    break;
                case 'typing_stop':
                    setTypingUsers(prev => prev.filter(id => id !== data.user_id));
                    break;
                case 'message_edited':
                case 'message_deleted':
                case 'reaction_added':
                case 'reaction_removed':
                    loadMessages(selectedConversationId!);
                    break;
                case 'participant_joined':
                case 'participant_left':
                    loadConversation(selectedConversationId!);
                    break;
            }
        } catch (error) {
            console.error('Failed to parse WebSocket message:', error);
        }
    }

    // Typing handler
    const handleTyping = useCallback(() => {
        if (!isTyping) {
            setIsTyping(true);
            sendWebSocketMessage({ type: 'typing_start', conversation_id: selectedConversationId });
        }

        if (typingTimeoutRef.current) {
            clearTimeout(typingTimeoutRef.current);
        }

        typingTimeoutRef.current = setTimeout(() => {
            setIsTyping(false);
            sendWebSocketMessage({ type: 'typing_stop', conversation_id: selectedConversationId });
        }, 3000);
    }, [isTyping, sendWebSocketMessage, selectedConversationId]);

    // Load conversations on mount
    useEffect(() => {
        loadConversations();
    }, [loadConversations]);

    // Load conversation when selected
    useEffect(() => {
        if (selectedConversationId) {
            loadConversation(selectedConversationId);
            loadMessages(selectedConversationId);
            subscribeToConversation(selectedConversationId);
        }
        
        return () => {
            if (selectedConversationId) {
                unsubscribeFromConversation(selectedConversationId);
            }
        };
    }, [selectedConversationId, loadConversation, loadMessages, subscribeToConversation, unsubscribeFromConversation]);

    // Scroll to bottom when new messages arrive
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const handleSendMessage = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!messageInput.trim() || !selectedConversationId) {
            return;
        }

        try {
            // For rich content, we could send the JSON content as metadata
            // For now, we'll send the plain text
            await sendMessage(selectedConversationId, messageInput);
            setMessageInput('');
            setMessageContent(null);
            setReplyingTo(null);
            
            // Stop typing indicator
            if (isTyping) {
                setIsTyping(false);
                sendWebSocketMessage({ type: 'typing_stop', conversation_id: selectedConversationId });
                if (typingTimeoutRef.current) {
                    clearTimeout(typingTimeoutRef.current);
                }
            }
            
            toast.success('Message sent');
        } catch (error) {
            console.error('Failed to send message:', error);
            toast.error('Failed to send message');
        }
    };

    // Handle rich text editor updates
    const handleRichTextUpdate = useCallback((content: JSONContent, text: string) => {
        setMessageContent(content);
        setMessageInput(text);
        handleTyping();
    }, [handleTyping]);

    // Handle rich text editor submission
    const handleRichTextSubmit = useCallback((content: JSONContent, text: string) => {
        if (!text.trim() || !selectedConversationId) {
            return;
        }

        // Create a synthetic form event for compatibility
        const syntheticEvent = {
            preventDefault: () => {},
        } as React.FormEvent;

        handleSendMessage(syntheticEvent);
    }, [selectedConversationId, handleSendMessage]);

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage(e as React.FormEvent);
        }
    };

    const handleFileUpload = async (files: FileList) => {
        if (!selectedConversationId) {
            toast.error('Please select a conversation first');
            return;
        }

        const maxFileSize = 50 * 1024 * 1024; // 50MB limit
        const allowedTypes = [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Documents
            'application/pdf', 'text/plain', 'text/markdown', 
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Audio
            'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac',
            // Video
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'
        ];

        for (const file of Array.from(files)) {
            try {
                // Validate file size
                if (file.size > maxFileSize) {
                    toast.error(`${file.name} is too large. Maximum size is 50MB.`);
                    continue;
                }

                // Validate file type
                if (!allowedTypes.includes(file.type)) {
                    toast.error(`${file.name} has an unsupported file type.`);
                    continue;
                }

                // Show upload progress
                toast.loading(`Uploading ${file.name}...`, { id: `upload-${file.name}` });

                // Upload file using E2EE service
                const fileInfo = await uploadFile(selectedConversationId, file);
                
                // Send message with file attachment
                await sendMessage(selectedConversationId, `📎 Shared file: ${file.name}`, {
                    type: 'file',
                    file_info: fileInfo
                });

                toast.success(`${file.name} uploaded successfully`, { id: `upload-${file.name}` });
            } catch (uploadError) {
                console.error('File upload error:', uploadError);
                toast.error(`Failed to upload ${file.name}`, { id: `upload-${file.name}` });
            }
        }
    };

    const handleEditMessage = async (messageId: string) => {
        if (!editingContent.trim() || !selectedConversationId) {
            return;
        }

        try {
            await editMessage(selectedConversationId, messageId, editingContent);
            setEditingMessageId(null);
            setEditingContent('');
        } catch (error) {
            console.error('Failed to edit message:', error);
        }
    };

    const handleDeleteMessage = async (messageId: string) => {
        if (!selectedConversationId) return;
        
        try {
            await deleteMessage(selectedConversationId, messageId);
        } catch (error) {
            console.error('Failed to delete message:', error);
        }
    };

    const handleAddReaction = async (messageId: string, emoji: string) => {
        if (!selectedConversationId) return;
        
        try {
            await addReaction(selectedConversationId, messageId, emoji);
            setShowEmojiPicker(null);
        } catch (error) {
            console.error('Failed to add reaction:', error);
        }
    };

    const getEncryptionStatus = () => {
        if (!currentConversation) return null;
        
        const { encryption_status } = currentConversation;
        
        if (!encryption_status.is_encrypted) {
            return (
                <Badge variant="destructive" className="flex items-center gap-1">
                    <Unlock className="w-3 h-3" />
                    Unencrypted
                </Badge>
            );
        }
        
        return (
            <Badge variant={encryption_status.quantum_ready ? "default" : "secondary"} className="flex items-center gap-1">
                <Lock className="w-3 h-3" />
                {encryption_status.quantum_ready ? 'Quantum E2EE' : 'E2EE'}
            </Badge>
        );
    };

    const formatMessageTime = (timestamp: string) => {
        return formatDistance(new Date(timestamp), new Date(), { addSuffix: true });
    };

    const commonEmojis = ['👍', '❤️', '😂', '😮', '😢', '😡', '👎', '🔥'];

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
                    <p className="text-muted-foreground">Loading conversations...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-screen bg-background">
            {/* Sidebar - Conversations List */}
            <div className="w-80 border-r border-border flex flex-col">
                <div className="p-4 border-b border-border">
                    <div className="flex items-center justify-between">
                        <h1 className="text-xl font-semibold">Messages</h1>
                        <Button variant="outline" size="sm">
                            New Chat
                        </Button>
                    </div>
                </div>
                
                <ScrollArea className="flex-1">
                    {conversations.map((conversation) => (
                        <div
                            key={conversation.id}
                            className={`p-4 cursor-pointer hover:bg-accent transition-colors ${
                                selectedConversationId === conversation.id ? 'bg-accent' : ''
                            }`}
                            onClick={() => setSelectedConversationId(conversation.id)}
                        >
                            <div className="flex items-start gap-3">
                                <Avatar className="h-10 w-10">
                                    <AvatarImage src={conversation.avatar_url} />
                                    <AvatarFallback>
                                        {conversation.name ? conversation.name[0] : 'G'}
                                    </AvatarFallback>
                                </Avatar>
                                
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between mb-1">
                                        <h3 className="font-medium truncate">
                                            {conversation.name || 'Group Chat'}
                                        </h3>
                                        {conversation.unread_count > 0 && (
                                            <Badge variant="default" className="ml-2">
                                                {conversation.unread_count}
                                            </Badge>
                                        )}
                                    </div>
                                    
                                    <div className="flex items-center gap-2">
                                        <div className="flex items-center gap-1">
                                            {conversation.encryption_status.is_encrypted ? (
                                                <Shield className={`w-3 h-3 ${
                                                    conversation.encryption_status.quantum_ready 
                                                        ? 'text-green-500' 
                                                        : 'text-blue-500'
                                                }`} />
                                            ) : (
                                                <Unlock className="w-3 h-3 text-orange-500" />
                                            )}
                                        </div>
                                        
                                        <p className="text-xs text-muted-foreground truncate">
                                            {formatMessageTime(conversation.last_activity_at)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </ScrollArea>
            </div>

            {/* Main Chat Area */}
            <div className="flex-1 flex flex-col">
                {currentConversation ? (
                    <>
                        {/* Chat Header */}
                        <div className="p-4 border-b border-border">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Avatar className="h-8 w-8">
                                        <AvatarImage src={currentConversation.avatar_url} />
                                        <AvatarFallback>
                                            {currentConversation.name ? currentConversation.name[0] : 'G'}
                                        </AvatarFallback>
                                    </Avatar>
                                    
                                    <div>
                                        <h2 className="font-semibold">
                                            {currentConversation.name || 'Group Chat'}
                                        </h2>
                                        <div className="flex items-center gap-2">
                                            {getEncryptionStatus()}
                                            <span className="text-xs text-muted-foreground">
                                                {currentConversation.participants.length} members
                                            </span>
                                            {typingUsers.length > 0 && (
                                                <span className="text-xs text-blue-500 flex items-center gap-1">
                                                    <div className="flex space-x-1">
                                                        <div className="w-1 h-1 bg-current rounded-full animate-bounce" />
                                                        <div className="w-1 h-1 bg-current rounded-full animate-bounce delay-100" />
                                                        <div className="w-1 h-1 bg-current rounded-full animate-bounce delay-200" />
                                                    </div>
                                                    {typingUsers.length} typing...
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="flex items-center gap-2">
                                    <Button 
                                        variant="ghost" 
                                        size="sm"
                                        onClick={() => setShowSearch(!showSearch)}
                                    >
                                        <Search className="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm">
                                        <Phone className="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm">
                                        <Video className="w-4 h-4" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm"
                                        onClick={() => setShowParticipants(true)}
                                    >
                                        <Users className="w-4 h-4" />
                                    </Button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm">
                                                <MoreVertical className="w-4 h-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Conversation</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem>
                                                <Pin className="w-4 h-4 mr-2" />
                                                Pin Conversation
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>
                                                <Archive className="w-4 h-4 mr-2" />
                                                Archive
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>View Details</DropdownMenuItem>
                                            <DropdownMenuItem>Rotate Keys</DropdownMenuItem>
                                            <DropdownMenuItem>Export Chat</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem className="text-destructive">
                                                <Trash2 className="w-4 h-4 mr-2" />
                                                Leave Chat
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </div>

                        {/* Search Bar */}
                        {showSearch && (
                            <div className="p-4 border-b border-border">
                                <Input
                                    placeholder="Search messages..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                            </div>
                        )}

                        {/* Messages Area with Tabs */}
                        <div className="flex-1 flex flex-col">
                            <Tabs value={selectedTab} onValueChange={(value) => setSelectedTab(value as 'messages' | 'media' | 'files' | 'links')}>
                                <TabsList className="mx-4 mt-2">
                                    <TabsTrigger value="messages">Messages</TabsTrigger>
                                    <TabsTrigger value="media">Media</TabsTrigger>
                                    <TabsTrigger value="files">Files</TabsTrigger>
                                    <TabsTrigger value="links">Links</TabsTrigger>
                                </TabsList>

                                <TabsContent value="messages" className="flex-1 flex flex-col">
                                    <ScrollArea className="flex-1 px-4">
                                        <div className="space-y-4 py-4">
                                            {messages.map((message) => (
                                    <div key={message.id} className="group relative">
                                        <div className="flex items-start gap-3">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage src={message.sender.avatar} />
                                                <AvatarFallback>
                                                    {message.sender.name[0]}
                                                </AvatarFallback>
                                            </Avatar>
                                            
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-medium text-sm">
                                                        {message.sender.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {formatMessageTime(message.created_at)}
                                                    </span>
                                                    {message.is_edited && (
                                                        <Badge variant="outline" className="text-xs">
                                                            edited
                                                        </Badge>
                                                    )}
                                                </div>
                                                
                                                {editingMessageId === message.id ? (
                                                    <div className="space-y-2">
                                                        <Input
                                                            value={editingContent}
                                                            onChange={(e) => setEditingContent(e.target.value)}
                                                            className="text-sm"
                                                            autoFocus
                                                        />
                                                        <div className="flex gap-2">
                                                            <Button
                                                                size="sm"
                                                                onClick={() => handleEditMessage(message.id)}
                                                            >
                                                                Save
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => {
                                                                    setEditingMessageId(null);
                                                                    setEditingContent('');
                                                                }}
                                                            >
                                                                Cancel
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <>
                                                        <div className="bg-muted p-3 rounded-lg max-w-md">
                                                            <p className="text-sm whitespace-pre-wrap">
                                                                {message.decrypted_content || '[Encrypted]'}
                                                            </p>
                                                        </div>
                                                        
                                                        {/* Reactions */}
                                                        {message.reactions && message.reactions.length > 0 && (
                                                            <div className="flex gap-1 mt-2">
                                                                {message.reactions.map((reaction) => (
                                                                    <Button
                                                                        key={reaction.id}
                                                                        variant="outline"
                                                                        size="sm"
                                                                        className="h-6 px-2 text-xs"
                                                                    >
                                                                        {reaction.emoji}
                                                                    </Button>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                        
                                        {/* Message Actions */}
                                        <div className="absolute right-0 top-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <div className="flex gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setShowEmojiPicker(message.id)}
                                                >
                                                    <Smile className="w-4 h-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm">
                                                    <Reply className="w-4 h-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setEditingMessageId(message.id);
                                                        setEditingContent(message.decrypted_content || '');
                                                    }}
                                                >
                                                    <Edit3 className="w-4 h-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDeleteMessage(message.id)}
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        
                                        {/* Emoji Picker */}
                                        {showEmojiPicker === message.id && (
                                            <div className="absolute top-8 right-0 z-10 bg-popover border border-border rounded-lg p-2 shadow-lg">
                                                <div className="grid grid-cols-4 gap-1">
                                                    {commonEmojis.map((emoji) => (
                                                        <Button
                                                            key={emoji}
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                            onClick={() => handleAddReaction(message.id, emoji)}
                                                        >
                                                            {emoji}
                                                        </Button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                            <div ref={messagesEndRef} />
                                        </div>
                                    </ScrollArea>
                                </TabsContent>

                                <TabsContent value="media" className="flex-1">
                                    <ScrollArea className="h-full">
                                        <div className="grid grid-cols-3 gap-2 p-4">
                                            {messages
                                                .filter(m => m.attachments?.some(a => a.type === 'image' || a.type === 'video'))
                                                .map(message => (
                                                    message.attachments
                                                        ?.filter(a => a.type === 'image' || a.type === 'video')
                                                        .map((attachment, index) => (
                                                            <div key={`${message.id}-${index}`} className="aspect-square bg-muted rounded overflow-hidden">
                                                                {attachment.type === 'image' ? (
                                                                    <div className="w-full h-full bg-muted flex items-center justify-center">
                                                                        <span className="text-xs text-muted-foreground">Image</span>
                                                                    </div>
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center">
                                                                        <Video className="h-8 w-8 text-muted-foreground" />
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ))
                                                ))}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>

                                <TabsContent value="files" className="flex-1">
                                    <ScrollArea className="h-full">
                                        <div className="space-y-2 p-4">
                                            {messages
                                                .filter(m => m.attachments?.some(a => a.type === 'file'))
                                                .map(message => (
                                                    message.attachments
                                                        ?.filter(a => a.type === 'file')
                                                        .map((attachment, index) => (
                                                            <div key={`${message.id}-${index}`} className="flex items-center gap-3 p-3 border rounded-lg">
                                                                <File className="h-8 w-8 text-muted-foreground" />
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="font-medium truncate">{attachment.filename || 'File'}</p>
                                                                    <p className="text-sm text-muted-foreground">
                                                                        {attachment.file_size ? `${(attachment.file_size / 1024 / 1024).toFixed(1)} MB` : 'Unknown size'}
                                                                    </p>
                                                                </div>
                                                                <Button variant="ghost" size="sm">
                                                                    <Download className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        ))
                                                ))}
                                        </div>
                                    </ScrollArea>
                                </TabsContent>

                                <TabsContent value="links" className="flex-1">
                                    <ScrollArea className="h-full">
                                        <div className="space-y-2 p-4">
                                            <p className="text-center text-muted-foreground py-8">
                                                No shared links yet
                                            </p>
                                        </div>
                                    </ScrollArea>
                                </TabsContent>
                            </Tabs>

                            {/* Reply Preview */}
                            {replyingTo && (
                                <div className="mx-4 mb-2 p-3 bg-muted rounded-lg">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Quote className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">
                                                Replying to {replyingTo.sender?.name}
                                            </span>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setReplyingTo(null)}
                                        >
                                            ×
                                        </Button>
                                    </div>
                                    <p className="text-sm text-muted-foreground mt-1 truncate">
                                        {replyingTo.decrypted_content}
                                    </p>
                                </div>
                            )}

                            {/* Message Input */}
                            <div className="p-4 border-t border-border">
                                <div className="flex items-end gap-2">
                                    <div className="flex items-center gap-1">
                                        <input
                                            type="file"
                                            ref={fileInputRef}
                                            className="hidden"
                                            multiple
                                            accept="*/*"
                                            onChange={(e) => e.target.files && handleFileUpload(e.target.files)}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <Paperclip className="w-4 h-4" />
                                        </Button>
                                    </div>
                                    
                                    <div className="flex-1">
                                        <RichTextEditor
                                            content={messageInput}
                                            placeholder="Type an encrypted message..."
                                            onUpdate={handleRichTextUpdate}
                                            onSubmit={handleRichTextSubmit}
                                            onEmojiClick={() => setShowEmojiPicker('input')}
                                            disabled={!currentConversation.encryption_status.is_encrypted}
                                            mentionableUsers={mentionableUsers}
                                            mentionableChannels={mentionableChannels}
                                            showToolbar={true}
                                            autoFocus={false}
                                            minHeight={40}
                                            maxHeight={120}
                                            className="border border-input rounded-md"
                                        />
                                    </div>
                                    
                                    <div className="flex items-center gap-1">
                                        {!isRecording ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onMouseDown={() => setIsRecording(true)}
                                            >
                                                <Mic className="w-4 h-4" />
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onMouseUp={() => setIsRecording(false)}
                                                className="text-red-500"
                                            >
                                                <StopCircle className="w-4 h-4" />
                                            </Button>
                                        )}
                                        
                                        <Button 
                                            type="button" 
                                            size="sm" 
                                            disabled={!messageInput.trim() || isLoadingMessages}
                                            onClick={() => handleRichTextSubmit(messageContent || {}, messageInput)}
                                        >
                                            <Send className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                                
                                {!currentConversation.encryption_status.is_encrypted && (
                                    <p className="text-xs text-muted-foreground mt-2 flex items-center gap-1">
                                        <AlertTriangle className="w-3 h-3" />
                                        This conversation is not encrypted
                                    </p>
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    /* No Conversation Selected */
                    <div className="flex-1 flex items-center justify-center">
                        <div className="text-center">
                            <ShieldCheck className="w-16 h-16 mx-auto mb-4 text-muted-foreground" />
                            <h2 className="text-xl font-semibold mb-2">End-to-End Encrypted Chat</h2>
                            <p className="text-muted-foreground">
                                Select a conversation to start secure messaging
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {/* Click outside to close emoji picker */}
            {showEmojiPicker && (
                <div
                    className="fixed inset-0 z-5"
                    onClick={() => setShowEmojiPicker(null)}
                />
            )}

            {/* Participants Dialog */}
            <Dialog open={showParticipants} onOpenChange={setShowParticipants}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Participants ({currentConversation?.participants.length || 0})</DialogTitle>
                        <DialogDescription>
                            People in this conversation
                        </DialogDescription>
                    </DialogHeader>
                    <ScrollArea className="max-h-96">
                        <div className="space-y-3">
                            {currentConversation?.participants.map((participant) => (
                                <div key={participant.user_id} className="flex items-center gap-3">
                                    <Avatar>
                                        <AvatarImage src={participant.user?.avatar_url} />
                                        <AvatarFallback>
                                            {participant.user?.name?.charAt(0) || 'U'}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex-1">
                                        <p className="font-medium">{participant.user?.name || 'Unknown'}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {participant.role}
                                        </p>
                                    </div>
                                    <Badge variant={participant.is_active ? 'default' : 'secondary'}>
                                        {participant.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </ScrollArea>
                </DialogContent>
            </Dialog>

            {/* Error Display */}
            {error && (
                <div className="fixed bottom-4 right-4 bg-destructive text-destructive-foreground p-3 rounded-lg shadow-lg">
                    <p className="text-sm">{error}</p>
                </div>
            )}
        </div>
    );
}