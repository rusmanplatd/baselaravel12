// Shared chat types for the application

export interface MessageUser {
    id: string;
    name: string;
    avatar?: string;
    avatar_url?: string;
}

export interface MessageAttachment {
    id: string;
    filename: string;
    file_path: string;
    file_size: number;
    mime_type: string;
    thumbnail_path?: string;
    type: string;
}

export interface MessageReaction {
    id: string;
    user_id: string;
    emoji: string;
    user: MessageUser;
}

export interface Message {
    id: string;
    conversation_id: string;
    sender_id: string;
    type?: 'text' | 'image' | 'video' | 'audio' | 'file' | 'voice' | 'poll' | 'system' | 'call';
    decrypted_content?: string;
    encrypted_content: string;
    decryption_failed?: boolean;
    decryption_error?: string;
    is_edited: boolean;
    is_forwarded?: boolean;
    forward_count?: number;
    is_pinned?: boolean;
    is_bookmarked?: boolean;
    is_flagged?: boolean;
    created_at: string;
    updated_at?: string;
    sender: MessageUser;
    reactions?: MessageReaction[];
    replies?: Message[];
    reply_to?: Message;
    attachments?: MessageAttachment[];
    metadata?: {
        filename?: string;
        file_size?: number;
        mime_type?: string;
        duration?: number;
        voice_duration_seconds?: number;
        poll_options?: Array<{
            id: string;
            text: string;
            votes: number;
        }>;
        call_duration?: number;
        call_status?: 'missed' | 'answered' | 'declined' | 'busy';
    };
    read_receipts?: Array<{
        user_id: string;
        user: MessageUser;
        read_at: string;
    }>;
    delivery_status?: 'sent' | 'delivered' | 'read';
    file_url?: string;
    file_name?: string;
    file_size?: number;
    encrypted?: boolean;
}

export interface ConversationParticipant {
    id: string;
    user_id: string;
    role: 'admin' | 'moderator' | 'member';
    is_active: boolean;
    user: MessageUser;
}

export interface Conversation {
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
    participants: ConversationParticipant[];
    last_activity_at: string;
    organization_id?: string;
}

export interface Device {
    id: string;
    device_name: string;
    device_type: 'mobile' | 'desktop' | 'web' | 'tablet';
    is_trusted: boolean;
    is_active: boolean;
    quantum_ready: boolean;
    security_level: string;
    fingerprint_short: string;
}