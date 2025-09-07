import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuLabel,
    DropdownMenuShortcut,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    MoreVertical,
    Reply,
    Edit3,
    Trash2,
    Forward,
    Copy,
    Pin,
    Smile,
    MessageSquare,
    Send,
    Search,
    Users,
    Check,
    X,
    Clock,
    AlertTriangle,
} from 'lucide-react';
import { toast } from 'sonner';

interface MessageUser {
    id: string;
    name: string;
    avatar: string;
}

interface Message {
    id: string;
    sender: MessageUser;
    decrypted_content?: string;
    created_at: string;
    is_edited?: boolean;
    is_forwarded?: boolean;
    is_pinned?: boolean;
}

interface Conversation {
    id: string;
    name?: string;
    avatar_url?: string;
    participants: Array<{
        user_id: string;
        user?: MessageUser;
    }>;
}

interface MessageContextMenuProps {
    message: Message;
    currentUserId: string;
    conversations: Conversation[];
    onReply: (message: Message) => void;
    onEdit: (messageId: string, content: string) => void;
    onDelete: (messageId: string) => void;
    onForward: (messageId: string, conversationIds: string[]) => void;
    onAddReaction: (messageId: string, emoji: string) => void;
    onPin?: (messageId: string) => void;
    onUnpin?: (messageId: string) => void;
    disabled?: boolean;
    loading?: boolean;
}

export function MessageContextMenu({
    message,
    currentUserId,
    conversations,
    onReply,
    onEdit,
    onDelete,
    onForward,
    onAddReaction,
    onPin,
    onUnpin,
    disabled = false,
    loading = false,
}: MessageContextMenuProps) {
    const [showForwardDialog, setShowForwardDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [selectedConversations, setSelectedConversations] = useState<string[]>([]);
    const [isForwarding, setIsForwarding] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    
    const isOwnMessage = message.sender.id === currentUserId;
    const canEdit = isOwnMessage && !message.is_edited;
    const canDelete = isOwnMessage;
    const isPinned = message.is_pinned || false;
    const canPin = onPin || onUnpin;

    const commonEmojis = ['👍', '❤️', '😂', '😮', '😢', '😡', '👎', '🔥'];

    // Filtered conversations for forwarding
    const filteredConversations = conversations.filter(conv => 
        conv.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        conv.participants.some(p => 
            p.user?.name?.toLowerCase().includes(searchQuery.toLowerCase())
        )
    );

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!isOpen) return;
            
            if (e.key === 'Escape') {
                setIsOpen(false);
                setShowForwardDialog(false);
                setShowDeleteDialog(false);
            }
            
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        onReply(message);
                        setIsOpen(false);
                        break;
                    case 'c':
                        e.preventDefault();
                        handleCopyMessage();
                        setIsOpen(false);
                        break;
                    case 'e':
                        if (canEdit) {
                            e.preventDefault();
                            onEdit(message.id, message.decrypted_content || '');
                            setIsOpen(false);
                        }
                        break;
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, canEdit, message, onReply, onEdit]);

    const handleCopyMessage = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(message.decrypted_content || '[Encrypted]');
            toast.success('Message copied to clipboard');
        } catch (error) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = message.decrypted_content || '[Encrypted]';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            toast.success('Message copied to clipboard');
        }
    }, [message.decrypted_content]);

    const handlePinToggle = useCallback(() => {
        if (isPinned && onUnpin) {
            onUnpin(message.id);
            toast.success('Message unpinned');
        } else if (!isPinned && onPin) {
            onPin(message.id);
            toast.success('Message pinned');
        }
        setIsOpen(false);
    }, [isPinned, onPin, onUnpin, message.id]);

    const handleForwardClick = useCallback(() => {
        setShowForwardDialog(true);
        setSelectedConversations([]);
        setSearchQuery('');
    }, []);

    const handleDeleteClick = useCallback(() => {
        setShowDeleteDialog(true);
        setIsOpen(false);
    }, []);

    const handleDeleteConfirm = useCallback(async () => {
        setIsDeleting(true);
        try {
            await onDelete(message.id);
            setShowDeleteDialog(false);
            toast.success('Message deleted');
        } catch (error) {
            console.error('Failed to delete message:', error);
            toast.error('Failed to delete message');
        } finally {
            setIsDeleting(false);
        }
    }, [onDelete, message.id]);

    const handleForwardSubmit = useCallback(async () => {
        if (selectedConversations.length === 0) {
            toast.error('Please select at least one conversation');
            return;
        }

        setIsForwarding(true);
        try {
            await onForward(message.id, selectedConversations);
            setShowForwardDialog(false);
            setSelectedConversations([]);
            setSearchQuery('');
            toast.success(`Message forwarded to ${selectedConversations.length} conversation(s)`);
        } catch (error) {
            console.error('Failed to forward message:', error);
            toast.error('Failed to forward message');
        } finally {
            setIsForwarding(false);
        }
    }, [selectedConversations, onForward, message.id]);

    const handleConversationToggle = (conversationId: string) => {
        setSelectedConversations(prev =>
            prev.includes(conversationId)
                ? prev.filter(id => id !== conversationId)
                : [...prev, conversationId]
        );
    };

    return (
        <>
            <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
                <DropdownMenuTrigger asChild>
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        disabled={disabled || loading}
                        className="opacity-0 group-hover:opacity-100 transition-opacity data-[state=open]:opacity-100"
                    >
                        <MoreVertical className="w-4 h-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuLabel className="flex items-center gap-2">
                        <MessageSquare className="w-4 h-4" />
                        Message Actions
                        {message.is_edited && (
                            <Badge variant="outline" className="text-xs">
                                Edited
                            </Badge>
                        )}
                        {message.is_forwarded && (
                            <Badge variant="secondary" className="text-xs">
                                Forwarded
                            </Badge>
                        )}
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />

                    {/* Quick reactions */}
                    <div className="flex gap-1 p-2 border-b">
                        {commonEmojis.slice(0, 6).map((emoji) => (
                            <Button
                                key={emoji}
                                variant="ghost"
                                size="sm"
                                disabled={disabled || loading}
                                className="h-8 w-8 p-0 hover:bg-accent transition-colors"
                                onClick={() => {
                                    onAddReaction(message.id, emoji);
                                    setIsOpen(false);
                                }}
                            >
                                {emoji}
                            </Button>
                        ))}
                    </div>

                    <DropdownMenuItem 
                        onClick={() => {
                            onReply(message);
                            setIsOpen(false);
                        }}
                        disabled={disabled || loading}
                    >
                        <Reply className="w-4 h-4 mr-2" />
                        Reply
                        <DropdownMenuShortcut>⌘R</DropdownMenuShortcut>
                    </DropdownMenuItem>

                    <DropdownMenuItem 
                        onClick={handleForwardClick}
                        disabled={disabled || loading}
                    >
                        <Forward className="w-4 h-4 mr-2" />
                        Forward
                    </DropdownMenuItem>

                    <DropdownMenuItem 
                        onClick={handleCopyMessage}
                        disabled={disabled || loading}
                    >
                        <Copy className="w-4 h-4 mr-2" />
                        Copy message
                        <DropdownMenuShortcut>⌘C</DropdownMenuShortcut>
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    {canEdit && (
                        <DropdownMenuItem 
                            onClick={() => {
                                onEdit(message.id, message.decrypted_content || '');
                                setIsOpen(false);
                            }}
                            disabled={disabled || loading}
                        >
                            <Edit3 className="w-4 h-4 mr-2" />
                            Edit message
                            <DropdownMenuShortcut>⌘E</DropdownMenuShortcut>
                        </DropdownMenuItem>
                    )}

                    {canPin && (
                        <DropdownMenuItem 
                            onClick={handlePinToggle}
                            disabled={disabled || loading}
                        >
                            <Pin className="w-4 h-4 mr-2" />
                            {isPinned ? 'Unpin' : 'Pin'} message
                        </DropdownMenuItem>
                    )}

                    <DropdownMenuSeparator />

                    {canDelete && (
                        <DropdownMenuItem 
                            onClick={handleDeleteClick}
                            disabled={disabled || loading}
                            className="text-destructive focus:text-destructive"
                        >
                            <Trash2 className="w-4 h-4 mr-2" />
                            Delete message
                            {loading && <div className="ml-auto animate-spin w-3 h-3 border border-current border-t-transparent rounded-full" />}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {/* Forward Dialog */}
            <Dialog open={showForwardDialog} onOpenChange={(open) => {
                setShowForwardDialog(open);
                if (!open) {
                    setSearchQuery('');
                    setSelectedConversations([]);
                }
            }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Forward className="w-5 h-5" />
                            Forward message
                        </DialogTitle>
                        <DialogDescription>
                            Send this message to other conversations
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Original message preview */}
                        <div className="bg-muted/50 p-3 rounded-lg border">
                            <div className="flex items-center gap-2 mb-2">
                                <MessageSquare className="w-4 h-4 text-muted-foreground" />
                                <span className="text-sm font-medium">
                                    From {message.sender.name}
                                </span>
                                <Badge variant="secondary" className="text-xs">
                                    <Clock className="w-3 h-3 mr-1" />
                                    {new Date(message.created_at).toLocaleDateString()}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground line-clamp-2">
                                {message.decrypted_content || '[Encrypted message]'}
                            </p>
                        </div>

                        {/* Search conversations */}
                        <div className="space-y-2">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
                                <Input
                                    placeholder="Search conversations..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            
                            {selectedConversations.length > 0 && (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">Selected:</span>
                                    <Badge variant="default">
                                        {selectedConversations.length} conversation{selectedConversations.length !== 1 ? 's' : ''}
                                    </Badge>
                                </div>
                            )}
                        </div>

                        {/* Conversation selection */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <Users className="w-4 h-4" />
                                Available Conversations ({filteredConversations.length})
                            </h4>
                            <ScrollArea className="max-h-64 border rounded-md">
                                {filteredConversations.length === 0 ? (
                                    <div className="p-4 text-center text-muted-foreground">
                                        <MessageSquare className="w-8 h-8 mx-auto mb-2 opacity-50" />
                                        <p className="text-sm">No conversations found</p>
                                        {searchQuery && (
                                            <p className="text-xs mt-1">Try a different search term</p>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-1 p-2">
                                        {filteredConversations.map((conversation) => {
                                            const isSelected = selectedConversations.includes(conversation.id);
                                            return (
                                                <div
                                                    key={conversation.id}
                                                    className={`flex items-center space-x-3 p-3 rounded-md cursor-pointer transition-colors
                                                        ${isSelected ? 'bg-primary/10 border border-primary/20' : 'hover:bg-accent'}
                                                    `}
                                                    onClick={() => handleConversationToggle(conversation.id)}
                                                >
                                                    <div className="flex-shrink-0">
                                                        <Checkbox
                                                            checked={isSelected}
                                                            onChange={() => {}} // Controlled by parent click
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium truncate">
                                                            {conversation.name || 'Group Chat'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground flex items-center gap-1">
                                                            <Users className="w-3 h-3" />
                                                            {conversation.participants.length} members
                                                        </p>
                                                    </div>
                                                    {isSelected && (
                                                        <Check className="w-4 h-4 text-primary flex-shrink-0" />
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </ScrollArea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowForwardDialog(false)}
                            disabled={isForwarding}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleForwardSubmit}
                            disabled={selectedConversations.length === 0 || isForwarding}
                        >
                            {isForwarding ? (
                                <>
                                    <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2" />
                                    Forwarding...
                                </>
                            ) : (
                                <>
                                    <Send className="w-4 h-4 mr-2" />
                                    Forward {selectedConversations.length > 0 && `(${selectedConversations.length})`}
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-destructive" />
                            Delete Message
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this message? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    
                    {/* Message preview in delete dialog */}
                    <div className="bg-muted/50 p-3 rounded-lg border border-destructive/20">
                        <div className="flex items-center gap-2 mb-2">
                            <MessageSquare className="w-4 h-4 text-muted-foreground" />
                            <span className="text-sm font-medium">Your message</span>
                            <Badge variant="secondary" className="text-xs">
                                <Clock className="w-3 h-3 mr-1" />
                                {new Date(message.created_at).toLocaleDateString()}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground line-clamp-2">
                            {message.decrypted_content || '[Encrypted message]'}
                        </p>
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDeleteConfirm}
                            disabled={isDeleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isDeleting ? (
                                <>
                                    <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2" />
                                    Deleting...
                                </>
                            ) : (
                                <>
                                    <Trash2 className="w-4 h-4 mr-2" />
                                    Delete Message
                                </>
                            )}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}