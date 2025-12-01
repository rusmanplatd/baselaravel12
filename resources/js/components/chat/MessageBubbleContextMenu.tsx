import React, { useState, useRef, useEffect } from 'react';
import { MessageContextMenu } from './MessageContextMenu';

interface Message {
    id: string;
    sender: {
        id: string;
        name: string;
        avatar: string;
    };
    decrypted_content?: string;
    created_at: string;
    updated_at?: string;
    is_edited?: boolean;
    is_forwarded?: boolean;
    is_pinned?: boolean;
    is_bookmarked?: boolean;
    is_flagged?: boolean;
    read_receipts?: Array<{
        user_id: string;
        user: {
            id: string;
            name: string;
        };
        read_at: string;
    }>;
    delivery_status?: 'sent' | 'delivered' | 'read';
    type?: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll';
    file_url?: string;
    file_name?: string;
    file_size?: number;
    encrypted?: boolean;
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
        };
    }>;
}

interface ContextMenuPosition {
    x: number;
    y: number;
}

interface MessageBubbleContextMenuProps {
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
    disabled?: boolean;
    loading?: boolean;
    enableReadReceipts?: boolean;
    children: React.ReactNode;
}

export function MessageBubbleContextMenu({
    children,
    ...contextMenuProps
}: MessageBubbleContextMenuProps) {
    const [showContextMenu, setShowContextMenu] = useState(false);
    const [contextMenuPosition, setContextMenuPosition] = useState<ContextMenuPosition>({ x: 0, y: 0 });
    const containerRef = useRef<HTMLDivElement>(null);

    const handleRightClick = (event: React.MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();
        
        const rect = containerRef.current?.getBoundingClientRect();
        if (!rect) return;

        // Calculate position relative to viewport
        let x = event.clientX;
        let y = event.clientY;

        // Adjust position if context menu would go off-screen
        const menuWidth = 250; // Approximate context menu width
        const menuHeight = 400; // Approximate context menu height
        
        if (x + menuWidth > window.innerWidth) {
            x = window.innerWidth - menuWidth - 10;
        }
        
        if (y + menuHeight > window.innerHeight) {
            y = window.innerHeight - menuHeight - 10;
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

    return (
        <>
            <div 
                ref={containerRef}
                onContextMenu={handleRightClick}
                onKeyDown={handleKeyDown}
                className="relative"
            >
                {children}
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
                            {...contextMenuProps}
                            onClose={() => setShowContextMenu(false)}
                        />
                    </div>
                </div>
            )}
        </>
    );
}