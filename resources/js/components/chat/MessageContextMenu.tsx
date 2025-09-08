import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
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
    Info,
    Eye,
    EyeOff,
    CheckCheck,
    Share2,
    Bookmark,
    Flag,
    Download,
    Quote,
    Lock,
} from 'lucide-react';
import { toast } from 'sonner';

import type { Message, Conversation } from '@/types/chat';

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
    onBookmark?: (messageId: string) => void;
    onUnbookmark?: (messageId: string) => void;
    onFlag?: (messageId: string) => void;
    onUnflag?: (messageId: string) => void;
    onDownload?: (messageId: string) => void;
    onQuote?: (message: Message) => void;
    onClose?: () => void;
    disabled?: boolean;
    loading?: boolean;
    enableReadReceipts?: boolean;
    asContextMenu?: boolean;
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
    onBookmark,
    onUnbookmark,
    onFlag,
    onUnflag,
    onDownload,
    onQuote,
    onClose,
    disabled = false,
    loading = false,
    enableReadReceipts = true,
    asContextMenu = false,
}: MessageContextMenuProps) {
    const [showForwardDialog, setShowForwardDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showInfoDialog, setShowInfoDialog] = useState(false);
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [selectedConversations, setSelectedConversations] = useState<string[]>([]);
    const [isForwarding, setIsForwarding] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [editContent, setEditContent] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    
    // Safety check - if message is null/undefined or sender is null, don't render
    if (!message || !message.id || !message.sender) {
        return null;
    }
    
    const isOwnMessage = message.sender?.id === currentUserId;
    const canEdit = isOwnMessage && !message.is_edited;
    const canDelete = isOwnMessage;
    const isPinned = message.is_pinned || false;
    const isBookmarked = message.is_bookmarked || false;
    const isFlagged = message.is_flagged || false;
    const canPin = onPin || onUnpin;
    const canBookmark = onBookmark || onUnbookmark;
    const canFlag = onFlag || onUnflag;
    const canDownload = onDownload && (message.type === 'file' || message.type === 'image' || message.type === 'video' || message.type === 'audio');
    const hasReadReceipts = message.read_receipts && message.read_receipts.length > 0;

    const commonEmojis = ['👍', '❤️', '😂', '😮', '😢', '😡', '👎', '🔥'];

    // Filtered conversations for forwarding
    const filteredConversations = (conversations || []).filter(conv => 
        conv?.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        conv?.participants?.some(p => 
            p?.user?.name?.toLowerCase().includes(searchQuery.toLowerCase())
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
                        if (message && onReply) {
                            onReply(message);
                        }
                        setIsOpen(false);
                        break;
                    case 'c':
                        e.preventDefault();
                        handleCopyMessage();
                        setIsOpen(false);
                        break;
                    case 'e':
                        if (canEdit && message && onEdit) {
                            e.preventDefault();
                            handleEditClick();
                            setIsOpen(false);
                        }
                        break;
                    case 'i':
                        e.preventDefault();
                        setShowInfoDialog(true);
                        setIsOpen(false);
                        break;
                    case 'b':
                        if (canBookmark) {
                            e.preventDefault();
                            handleBookmarkToggle();
                        }
                        break;
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, canEdit, canBookmark, message, onReply, onEdit]);

    const handleCopyMessage = useCallback(async () => {
        if (!message) {
            toast.error('No message to copy');
            return;
        }
        
        try {
            const textToCopy = message.decrypted_content || '[Encrypted]';
            await navigator.clipboard.writeText(textToCopy);
            toast.success('Message copied to clipboard');
        } catch (error) {
            // Fallback for older browsers
            try {
                const textArea = document.createElement('textarea');
                textArea.value = message.decrypted_content || '[Encrypted]';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                toast.success('Message copied to clipboard');
            } catch (fallbackError) {
                console.error('Failed to copy message:', fallbackError);
                toast.error('Failed to copy message');
            }
        }
    }, [message]);

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

    const handleBookmarkToggle = useCallback(() => {
        if (isBookmarked && onUnbookmark) {
            onUnbookmark(message.id);
            toast.success('Bookmark removed');
        } else if (!isBookmarked && onBookmark) {
            onBookmark(message.id);
            toast.success('Message bookmarked');
        }
        setIsOpen(false);
    }, [isBookmarked, onBookmark, onUnbookmark, message.id]);

    const handleFlagToggle = useCallback(() => {
        if (isFlagged && onUnflag) {
            onUnflag(message.id);
            toast.success('Flag removed');
        } else if (!isFlagged && onFlag) {
            onFlag(message.id);
            toast.success('Message flagged');
        }
        setIsOpen(false);
    }, [isFlagged, onFlag, onUnflag, message.id]);

    const handleDownload = useCallback(() => {
        if (onDownload) {
            onDownload(message.id);
            toast.success('Download started');
        }
        setIsOpen(false);
    }, [onDownload, message.id]);

    const handleQuote = useCallback(() => {
        if (onQuote) {
            onQuote(message);
            toast.success('Message quoted');
        }
        setIsOpen(false);
    }, [onQuote, message]);

    const handleInfoClick = useCallback(() => {
        setShowInfoDialog(true);
        setIsOpen(false);
    }, []);

    const handleEditClick = useCallback(() => {
        setEditContent(message.decrypted_content || '');
        setShowEditDialog(true);
        setIsOpen(false);
    }, [message.decrypted_content]);

    const handleEditSubmit = useCallback(async () => {
        if (!onEdit || !editContent.trim()) return;
        
        setIsEditing(true);
        try {
            await onEdit(message.id, editContent.trim());
            setShowEditDialog(false);
            setEditContent('');
            toast.success('Message updated successfully');
        } catch (error) {
            console.error('Failed to edit message:', error);
            toast.error('Failed to edit message');
        } finally {
            setIsEditing(false);
        }
    }, [onEdit, message.id, editContent]);

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

    // If used as context menu, render directly without dropdown wrapper
    if (asContextMenu) {
        return (
            <>
                <div className="bg-background border rounded-lg shadow-lg p-1 w-56 max-h-96 overflow-y-auto">
                    <div className="flex items-center gap-2 px-3 py-2 border-b">
                        <MessageSquare className="w-4 h-4" />
                        <span className="text-sm font-medium">Message Actions</span>
                        {message.is_edited && (
                            <Badge key="edited-header" variant="outline" className="text-xs">
                                Edited
                            </Badge>
                        )}
                        {message.is_forwarded && (
                            <Badge key="forwarded-header" variant="secondary" className="text-xs">
                                Forwarded
                            </Badge>
                        )}
                    </div>

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
                                    onClose?.();
                                }}
                            >
                                {emoji}
                            </Button>
                        ))}
                    </div>

                    <div className="space-y-1 p-1">
                        <Button
                            variant="ghost"
                            className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                            onClick={() => {
                                onReply(message);
                                onClose?.();
                            }}
                            disabled={disabled || loading}
                        >
                            <Reply className="w-4 h-4 mr-2" />
                            Reply
                            <span className="ml-auto text-xs text-muted-foreground">⌘R</span>
                        </Button>

                        <Button
                            variant="ghost"
                            className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                            onClick={() => {
                                handleForwardClick();
                                onClose?.();
                            }}
                            disabled={disabled || loading}
                        >
                            <Forward className="w-4 h-4 mr-2" />
                            Forward
                        </Button>

                        <Button
                            variant="ghost"
                            className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                            onClick={() => {
                                handleCopyMessage();
                                onClose?.();
                            }}
                            disabled={disabled || loading}
                        >
                            <Copy className="w-4 h-4 mr-2" />
                            Copy message
                            <span className="ml-auto text-xs text-muted-foreground">⌘C</span>
                        </Button>

                        {onQuote && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                                onClick={() => {
                                    handleQuote();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Quote className="w-4 h-4 mr-2" />
                                Quote message
                            </Button>
                        )}

                        {canDownload && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                                onClick={() => {
                                    handleDownload();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Download className="w-4 h-4 mr-2" />
                                Download file
                            </Button>
                        )}

                        <div className="border-t my-1" />

                        {canEdit && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                                onClick={() => {
                                    handleEditClick();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Edit3 className="w-4 h-4 mr-2" />
                                Edit message
                                <span className="ml-auto text-xs text-muted-foreground">⌘E</span>
                            </Button>
                        )}

                        {canPin && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                                onClick={() => {
                                    handlePinToggle();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Pin className="w-4 h-4 mr-2" />
                                {isPinned ? 'Unpin' : 'Pin'} message
                            </Button>
                        )}

                        {canBookmark && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                                onClick={() => {
                                    handleBookmarkToggle();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Bookmark className="w-4 h-4 mr-2" />
                                {isBookmarked ? 'Remove bookmark' : 'Bookmark message'}
                                <span className="ml-auto text-xs text-muted-foreground">⌘B</span>
                            </Button>
                        )}

                        <Button
                            variant="ghost"
                            className="w-full justify-start text-sm font-normal h-auto py-2 px-3"
                            onClick={() => {
                                handleInfoClick();
                                onClose?.();
                            }}
                            disabled={disabled || loading}
                        >
                            <Info className="w-4 h-4 mr-2" />
                            Message info
                            <span className="ml-auto text-xs text-muted-foreground">⌘I</span>
                        </Button>

                        <div className="border-t my-1" />

                        {canFlag && (
                            <Button
                                variant="ghost"
                                className={`w-full justify-start text-sm font-normal h-auto py-2 px-3 ${
                                    isFlagged ? "text-orange-600" : ""
                                }`}
                                onClick={() => {
                                    handleFlagToggle();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Flag className="w-4 h-4 mr-2" />
                                {isFlagged ? 'Remove flag' : 'Flag message'}
                            </Button>
                        )}

                        <div className="border-t my-1" />

                        {canDelete && (
                            <Button
                                variant="ghost"
                                className="w-full justify-start text-sm font-normal h-auto py-2 px-3 text-destructive hover:text-destructive"
                                onClick={() => {
                                    handleDeleteClick();
                                    onClose?.();
                                }}
                                disabled={disabled || loading}
                            >
                                <Trash2 className="w-4 h-4 mr-2" />
                                Delete message
                                {loading && <div className="ml-auto animate-spin w-3 h-3 border border-current border-t-transparent rounded-full" />}
                            </Button>
                        )}
                    </div>
                </div>
                
                {/* Dialogs - same as dropdown version */}
                {showForwardDialog && (
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
                            {/* Rest of forward dialog content */}
                        </DialogContent>
                    </Dialog>
                )}
                
                {/* Other dialogs... */}
            </>
        );
    }

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
                            <Badge key="edited-dropdown" variant="outline" className="text-xs">
                                Edited
                            </Badge>
                        )}
                        {message.is_forwarded && (
                            <Badge key="forwarded-dropdown" variant="secondary" className="text-xs">
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

                    {onQuote && (
                        <DropdownMenuItem 
                            onClick={handleQuote}
                            disabled={disabled || loading}
                        >
                            <Quote className="w-4 h-4 mr-2" />
                            Quote message
                        </DropdownMenuItem>
                    )}

                    {canDownload && (
                        <DropdownMenuItem 
                            onClick={handleDownload}
                            disabled={disabled || loading}
                        >
                            <Download className="w-4 h-4 mr-2" />
                            Download file
                        </DropdownMenuItem>
                    )}

                    <DropdownMenuSeparator />

                    {canEdit && (
                        <DropdownMenuItem 
                            onClick={() => {
                                handleEditClick();
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

                    {canBookmark && (
                        <DropdownMenuItem 
                            onClick={handleBookmarkToggle}
                            disabled={disabled || loading}
                        >
                            <Bookmark className="w-4 h-4 mr-2" />
                            {isBookmarked ? 'Remove bookmark' : 'Bookmark message'}
                            <DropdownMenuShortcut>⌘B</DropdownMenuShortcut>
                        </DropdownMenuItem>
                    )}

                    <DropdownMenuItem 
                        onClick={handleInfoClick}
                        disabled={disabled || loading}
                    >
                        <Info className="w-4 h-4 mr-2" />
                        Message info
                        <DropdownMenuShortcut>⌘I</DropdownMenuShortcut>
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    {canFlag && (
                        <DropdownMenuItem 
                            onClick={handleFlagToggle}
                            disabled={disabled || loading}
                            className={isFlagged ? "text-orange-600" : ""}
                        >
                            <Flag className="w-4 h-4 mr-2" />
                            {isFlagged ? 'Remove flag' : 'Flag message'}
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
                                    From {message.sender?.name || 'Unknown User'}
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

            {/* Message Info Dialog */}
            <Dialog open={showInfoDialog} onOpenChange={setShowInfoDialog}>
                <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Info className="w-5 h-5" />
                            Message Information
                        </DialogTitle>
                        <DialogDescription>
                            Detailed information about this message
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6">
                        {/* Message Content Preview */}
                        <div className="bg-muted/50 p-4 rounded-lg border">
                            <div className="flex items-center gap-2 mb-3">
                                <MessageSquare className="w-4 h-4 text-muted-foreground" />
                                <span className="text-sm font-medium">Message Content</span>
                                {message.encrypted && (
                                    <Badge variant="secondary" className="text-xs">
                                        <Lock className="w-3 h-3 mr-1" />
                                        Encrypted
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm">
                                {message.decrypted_content || '[Encrypted content]'}
                            </div>
                        </div>

                        {/* Sender Information */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <Users className="w-4 h-4" />
                                Sender Details
                            </h4>
                            <div className="bg-card border rounded-lg p-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-sm font-medium">
                                        {message.sender?.name?.charAt(0).toUpperCase() || '?'}
                                    </div>
                                    <div>
                                        <p className="font-medium">{message.sender?.name || 'Unknown User'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            @{message.sender?.username || message.sender?.id || 'N/A'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Timestamps */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <Clock className="w-4 h-4" />
                                Timestamps
                            </h4>
                            <div className="space-y-3">
                                <div className="bg-card border rounded-lg p-3">
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-muted-foreground">Sent</span>
                                        <span className="text-sm font-mono">
                                            {new Date(message.created_at).toLocaleString()}
                                        </span>
                                    </div>
                                </div>
                                {message.updated_at && message.is_edited && (
                                    <div className="bg-card border rounded-lg p-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Last edited</span>
                                            <span className="text-sm font-mono">
                                                {new Date(message.updated_at).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Message Status */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <CheckCheck className="w-4 h-4" />
                                Message Status
                            </h4>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-card border rounded-lg p-3">
                                    <div className="text-xs text-muted-foreground mb-1">Delivery Status</div>
                                    <div className="flex items-center gap-2">
                                        {message.delivery_status === 'read' && (
                                            <>
                                                <CheckCheck className="w-4 h-4 text-blue-500" />
                                                <span className="text-sm">Read</span>
                                            </>
                                        )}
                                        {message.delivery_status === 'delivered' && (
                                            <>
                                                <Check className="w-4 h-4 text-green-500" />
                                                <span className="text-sm">Delivered</span>
                                            </>
                                        )}
                                        {message.delivery_status === 'sent' && (
                                            <>
                                                <Clock className="w-4 h-4 text-yellow-500" />
                                                <span className="text-sm">Sent</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <div className="bg-card border rounded-lg p-3">
                                    <div className="text-xs text-muted-foreground mb-1">Type</div>
                                    <div className="text-sm capitalize">{message.type || 'text'}</div>
                                </div>
                            </div>
                        </div>

                        {/* File Information */}
                        {(message.type === 'file' || message.type === 'image' || message.type === 'video' || message.type === 'audio') && (
                            <div>
                                <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                    <Download className="w-4 h-4" />
                                    File Details
                                </h4>
                                <div className="bg-card border rounded-lg p-4">
                                    <div className="space-y-2">
                                        {message.file_name && (
                                            <div className="flex justify-between">
                                                <span className="text-sm text-muted-foreground">Filename</span>
                                                <span className="text-sm font-mono">{message.file_name}</span>
                                            </div>
                                        )}
                                        {message.file_size && (
                                            <div className="flex justify-between">
                                                <span className="text-sm text-muted-foreground">Size</span>
                                                <span className="text-sm font-mono">
                                                    {(message.file_size / 1024).toFixed(1)} KB
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Read Receipts */}
                        {enableReadReceipts && hasReadReceipts && (
                            <div>
                                <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                    <Eye className="w-4 h-4" />
                                    Read by ({message.read_receipts!.length})
                                </h4>
                                <ScrollArea className="max-h-32 border rounded-md">
                                    <div className="space-y-2 p-3">
                                        {message.read_receipts!.map((receipt, index) => (
                                            <div key={`${receipt.user_id}-${receipt.read_at}-${index}`} className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-6 h-6 rounded-full bg-muted flex items-center justify-center text-xs">
                                                        {receipt.user?.name?.charAt(0).toUpperCase() || '?'}
                                                    </div>
                                                    <div>
                                                        <span className="text-sm font-medium">{receipt.user?.name || 'Unknown User'}</span>
                                                        <div className="text-xs text-muted-foreground">
                                                            @{receipt.user?.username || receipt.user?.id || 'N/A'}
                                                        </div>
                                                    </div>
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(receipt.read_at).toLocaleString()}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </ScrollArea>
                            </div>
                        )}

                        {/* Message Flags */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <Flag className="w-4 h-4" />
                                Message Properties
                            </h4>
                            <div className="flex flex-wrap gap-2">
                                {message.is_edited && (
                                    <Badge key="edited" variant="outline" className="text-xs">
                                        <Edit3 className="w-3 h-3 mr-1" />
                                        Edited
                                    </Badge>
                                )}
                                {message.is_forwarded && (
                                    <Badge key="forwarded" variant="secondary" className="text-xs">
                                        <Forward className="w-3 h-3 mr-1" />
                                        Forwarded
                                    </Badge>
                                )}
                                {message.is_pinned && (
                                    <Badge key="pinned" variant="default" className="text-xs">
                                        <Pin className="w-3 h-3 mr-1" />
                                        Pinned
                                    </Badge>
                                )}
                                {message.is_bookmarked && (
                                    <Badge key="bookmarked" variant="default" className="text-xs">
                                        <Bookmark className="w-3 h-3 mr-1" />
                                        Bookmarked
                                    </Badge>
                                )}
                                {message.is_flagged && (
                                    <Badge key="flagged" variant="destructive" className="text-xs">
                                        <Flag className="w-3 h-3 mr-1" />
                                        Flagged
                                    </Badge>
                                )}
                                {message.encrypted && (
                                    <Badge key="encrypted" variant="secondary" className="text-xs">
                                        <Lock className="w-3 h-3 mr-1" />
                                        End-to-end encrypted
                                    </Badge>
                                )}
                            </div>
                        </div>

                        {/* Technical Details */}
                        <div>
                            <h4 className="text-sm font-medium mb-3 flex items-center gap-2">
                                <MessageSquare className="w-4 h-4" />
                                Technical Details
                            </h4>
                            <div className="bg-card border rounded-lg p-4">
                                <div className="space-y-2 text-xs font-mono">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Message ID</span>
                                        <span>{message.id}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Sender</span>
                                        <span>@{message.sender?.username || message.sender?.id || 'N/A'}</span>
                                    </div>
                                    {message.file_url && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">File URL</span>
                                            <span className="truncate max-w-48">{message.file_url}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowInfoDialog(false)}>
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Message Dialog */}
            <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Edit3 className="w-5 h-5" />
                            Edit Message
                        </DialogTitle>
                        <DialogDescription>
                            Edit your message content. You have 24 hours to edit a message.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <label className="text-sm font-medium mb-2 block">
                                Message Content
                            </label>
                            <Textarea
                                value={editContent}
                                onChange={(e) => setEditContent(e.target.value)}
                                placeholder="Enter your message..."
                                className="min-h-[100px] resize-none"
                                disabled={isEditing}
                            />
                            <div className="flex items-center justify-between mt-1">
                                <span className="text-xs text-muted-foreground">
                                    {editContent.length}/10000 characters
                                </span>
                                {editContent.length > 10000 && (
                                    <span className="text-xs text-destructive">
                                        Message too long
                                    </span>
                                )}
                            </div>
                        </div>

                        {message.decrypted_content !== editContent && (
                            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <div className="flex items-start gap-2">
                                    <AlertTriangle className="w-4 h-4 text-yellow-600 flex-shrink-0 mt-0.5" />
                                    <div className="text-xs text-yellow-800">
                                        <strong>Note:</strong> Edited messages will show an "edited" indicator to all participants.
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowEditDialog(false);
                                setEditContent('');
                            }}
                            disabled={isEditing}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleEditSubmit}
                            disabled={isEditing || !editContent.trim() || editContent.length > 10000 || editContent === message.decrypted_content}
                        >
                            {isEditing ? (
                                <>
                                    <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2" />
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <Edit3 className="w-4 h-4 mr-2" />
                                    Save Changes
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