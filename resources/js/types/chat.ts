export interface User {
  id: string;
  name: string;
  email: string;
}

export interface ConversationSettings {
  allow_member_invite?: boolean;
  only_admins_can_message?: boolean;
  message_retention_days?: number;
}

export interface ConversationMetadata {
  settings?: ConversationSettings;
  invite_links?: Array<{
    code: string;
    created_by: string;
    created_at: string;
    expires_at?: string;
    max_uses?: number;
    current_uses: number;
    is_active: boolean;
  }>;
}

export interface Conversation {
  id: string;
  name?: string;
  type: 'direct' | 'group';
  description?: string;
  avatar_url?: string;
  metadata?: ConversationMetadata;
  status: 'active' | 'archived' | 'deleted';
  last_message_at?: string;
  created_by?: string;
  created_at: string;
  updated_at: string;
  participants?: Participant[];
  messages?: Message[];
}

export interface Participant {
  id: string;
  conversation_id: string;
  user_id: string;
  role: 'member' | 'admin' | 'owner';
  joined_at: string;
  left_at?: string;
  last_read_at?: string;
  permissions?: Record<string, unknown>;
  notification_settings?: Record<string, unknown>;
  is_muted: boolean;
  user?: User;
}

export interface Message {
  id: string;
  conversation_id: string;
  sender_id: string;
  reply_to_id?: string;
  type: 'text' | 'image' | 'file' | 'voice' | 'system';
  content?: string;
  encrypted_content?: string;
  content_hash?: string;
  content_hmac?: string;
  encryption_version?: '1.0' | '2.0';
  voice_duration_seconds?: number;
  voice_transcript?: string;
  voice_waveform_data?: string;
  scheduled_at?: string;
  message_priority?: 'low' | 'normal' | 'high' | 'urgent';
  metadata?: Record<string, unknown>;
  is_edited: boolean;
  edited_at?: string;
  status: 'sent' | 'delivered' | 'read' | 'failed' | 'scheduled';
  created_at: string;
  updated_at: string;
  sender?: User;
  reply_to?: Message;
  reactions?: MessageReaction[];
  read_receipts?: MessageReadReceipt[];
  thread_replies_count?: number;
  is_thread_root?: boolean;
}

export interface MessageReaction {
  id: string;
  message_id: string;
  user_id: string;
  emoji: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

export interface MessageReadReceipt {
  id: string;
  message_id: string;
  user_id: string;
  read_at: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

export interface TypingIndicator {
  id: string;
  conversation_id: string;
  user_id: string;
  is_typing: boolean;
  last_typed_at: string;
  expires_at?: string;
  created_at: string;
  updated_at: string;
  user?: User;
}

export interface ReactionSummary {
  emoji: string;
  count: number;
  users: Array<{
    id: string;
    name: string;
    reacted_at: string;
  }>;
}

export interface VoiceRecording {
  blob: Blob;
  duration: number;
  waveformData: number[];
}

export interface EncryptionKey {
  id: string;
  conversation_id: string;
  user_id: string;
  encrypted_key: string;
  public_key: string;
  key_version: string;
  expires_at?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface EncryptedMessageData {
  data: string;
  iv: string;
  hash: string;
  hmac?: string;
  auth_data?: string;
  timestamp?: number;
  nonce?: string;
  version?: '1.0' | '2.0';
}

export interface KeyPair {
  public_key: string;
  private_key: string;
}

export interface EncryptionOptions {
  version?: '1.0' | '2.0';
  maxAge?: number;
  enableReplayProtection?: boolean;
}

export interface DecryptionResult {
  content: string;
  verified: boolean;
  version: '1.0' | '2.0';
  timestamp?: number;
}

export interface E2EEStatus {
  enabled: boolean;
  keyGenerated: boolean;
  conversationKeysReady: boolean;
  version: '1.0' | '2.0';
  lastKeyRotation?: string;
}

export interface MessageThread {
  id: string;
  root_message: Message;
  replies: Message[];
  replies_count: number;
  last_reply_at?: string;
}

export interface ThreadViewMode {
  showThreadsOnly: boolean;
  expandedThreads: Set<string>;
  selectedThread?: string;
}

export interface ThreadNavigation {
  threadId: string;
  messageId: string;
  position: 'root' | 'reply';
}

export interface Channel {
  id: string;
  name: string;
  slug: string;
  description?: string;
  visibility: 'public' | 'private';
  avatar_url?: string;
  metadata?: Record<string, unknown>;
  status: 'active' | 'archived' | 'deleted';
  conversation_id: string;
  organization_id?: string;
  created_by: string;
  created_at: string;
  updated_at: string;
  conversation?: Conversation;
  creator?: User;
  organization?: {
    id: string;
    name: string;
  };
}

export interface ChannelMember {
  id: string;
  channel_id: string;
  user_id: string;
  role: 'member' | 'admin' | 'owner';
  joined_at: string;
  left_at?: string;
  user?: User;
}

export interface ChannelSearchResult {
  channels: Channel[];
  total: number;
  per_page: number;
  current_page: number;
}

export interface ChannelInviteResponse {
  message: string;
  participants?: Participant[];
  added_count: number;
  errors?: string[];
}