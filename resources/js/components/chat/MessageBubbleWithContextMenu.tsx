import React, { useState, useRef, useEffect } from 'react';
import MessageBubble from './message-bubble';
import { MessageContextMenu } from './MessageContextMenu';

interface Message {
    id: string;
    conversation_id: string;
    sender_id: string;
    type: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll';
    decrypted_content?: string;
    encrypted_content: string;
    is_edited: boolean;
    created_at: string;
    updated_at?: string;
    is_forwarded?: boolean;
    is_pinned?: boolean;
    is_bookmarked?: boolean;
    is_flagged?: boolean;
    read_receipts?: Array<{
        user_id: string;
        user: {
            id: string;
            name: string;
            avatar?: string;
        };
        read_at: string;
    }>;
    delivery_status?: 'sent' | 'delivered' | 'read';
    file_url?: string;
    file_name?: string;
    file_size?: number;
    encrypted?: boolean;
    sender: {
        id: string;
        name: string;
        avatar?: string;
    };
    reactions?: Array<{
        id: string;
        user_id: string;
        emoji: string;
        user: {
            id: string;
            name: string;
            avatar?: string;
        };
    }>;
    replies?: Message[];
    reply_to?: Message;
}

interface Conversation {
    id: string;
    name?: string;
    avatar_url?: string;
    participants: Array<{
        user_id: string;
        user?: {
            id: string;
            name: string;
            avatar?: string;
        };
    }>;
}

interface ContextMenuPosition {
    x: number;
    y: number;
}

interface MessageBubbleWithContextMenuProps {
    message: Message;
    isOwn: boolean;
    currentUser: any;
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
    enableRightClickMenu?: boolean;
}

export function MessageBubbleWithContextMenu({
    message,
    isOwn,
    currentUser,
    conversations = [],
    enableRightClickMenu = true,
    ...handlers
}: MessageBubbleWithContextMenuProps) {
    const [showContextMenu, setShowContextMenu] = useState(false);
    const [contextMenuPosition, setContextMenuPosition] = useState<ContextMenuPosition>({ x: 0, y: 0 });
    const containerRef = useRef<HTMLDivElement>(null);

    const handleRightClick = (event: React.MouseEvent) => {
        if (!enableRightClickMenu) return;
        
        event.preventDefault();
        event.stopPropagation();
        
        // Calculate position relative to viewport
        let x = event.clientX;
        let y = event.clientY;

        // Adjust position if context menu would go off-screen
        const menuWidth = 280; // Approximate context menu width
        const menuHeight = 500; // Approximate context menu height
        
        if (x + menuWidth > window.innerWidth) {
            x = window.innerWidth - menuWidth - 20;
        }
        
        if (y + menuHeight > window.innerHeight) {
            y = window.innerHeight - menuHeight - 20;
        }

        setContextMenuPosition({ x, y });
        setShowContextMenu(true);
    };

    const handleClickOutside = (event: MouseEvent) => {
        if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
            setShowContextMenu(false);
        }
    };

    useEffect(() => {
        if (showContextMenu) {
            document.addEventListener('click', handleClickOutside);
            document.addEventListener('contextmenu', handleClickOutside);
            
            return () => {
                document.removeEventListener('click', handleClickOutside);
                document.removeEventListener('contextmenu', handleClickOutside);
            };
        }
    }, [showContextMenu]);

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Escape') {
            setShowContextMenu(false);
        }
    };

    const handleContextMenuClose = () => {
        setShowContextMenu(false);
    };

    return (
        <>
            <div 
                ref={containerRef}
                onContextMenu={handleRightClick}
                onKeyDown={handleKeyDown}
                className="relative"
            >
                <MessageBubble
                    message={message}
                    isOwn={isOwn}
                    currentUser={currentUser}
                    conversations={conversations}
                    {...handlers}
                />
            </div>

            {showContextMenu && (
                <div
                    className="fixed inset-0 z-50"
                    style={{ pointerEvents: 'none' }}
                >
                    <div
                        style={{
                            position: 'absolute',
                            left: contextMenuPosition.x,
                            top: contextMenuPosition.y,
                            pointerEvents: 'auto',
                        }}
                    >
                        <MessageContextMenu
                            message={message}
                            currentUserId={currentUser?.id || ''}
                            conversations={conversations}
                            onClose={handleContextMenuClose}
                            asContextMenu={true}
                            {...handlers}
                        />
                    </div>
                </div>
            )}
        </>
    );
}

export default MessageBubbleWithContextMenu;