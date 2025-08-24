import React, { useState } from 'react';
import { User } from '@/types';
import { Conversation } from '@/types/chat';
import { formatDistanceToNow } from 'date-fns';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { UserSearchCombobox } from '@/components/ui/user-search-combobox';
import { PlusIcon, UserIcon, UserGroupIcon } from '@heroicons/react/24/outline';
import CreateGroupDialog from './CreateGroupDialog';

interface ConversationListProps {
  conversations: Conversation[];
  activeConversation: Conversation | null;
  onSelectConversation: (conversation: Conversation) => void;
  onCreateConversation: (participants: string[], name?: string) => void;
  onCreateGroup: (groupData: { name: string; description?: string; participants: string[] }) => Promise<void>;
  currentUser: User;
  loading: boolean;
  deviceRegistered?: boolean;
}

export default function ConversationList({
  conversations,
  activeConversation,
  onSelectConversation,
  onCreateConversation,
  onCreateGroup,
  currentUser,
  loading,
  deviceRegistered = true
}: ConversationListProps) {
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [newConversationEmail, setNewConversationEmail] = useState('');
  const [newConversationName, setNewConversationName] = useState('');

  const handleCreateConversation = async () => {
    if (!newConversationEmail.trim()) return;
    
    // In a real app, you'd search for users by email
    // For now, we'll just use the email as a participant ID
    await onCreateConversation([newConversationEmail.trim()], newConversationName.trim() || undefined);
    
    setNewConversationEmail('');
    setNewConversationName('');
    setShowCreateDialog(false);
  };

  const getConversationName = (conversation: Conversation) => {
    if (conversation.name) return conversation.name;
    
    if (conversation.type === 'direct') {
      const otherParticipant = conversation.participants?.find(
        p => p.user_id !== currentUser.id
      );
      return otherParticipant?.user?.name || 'Unknown User';
    }
    
    return `Group (${conversation.participants?.length || 0} members)`;
  };

  const getConversationAvatar = (conversation: Conversation) => {
    if (conversation.avatar_url) return conversation.avatar_url;
    
    if (conversation.type === 'direct') {
      const otherParticipant = conversation.participants?.find(
        p => p.user_id !== currentUser.id
      );
      return otherParticipant?.user?.name?.charAt(0).toUpperCase() || '?';
    }
    
    return '#';
  };

  if (loading && conversations.length === 0) {
    return (
      <div className="p-4">
        <div className="animate-pulse space-y-4">
          {[1, 2, 3].map(i => (
            <div key={i} className="flex items-center space-x-3">
              <div className="h-12 w-12 bg-gray-200 rounded-full"></div>
              <div className="flex-1 space-y-2">
                <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                <div className="h-3 bg-gray-200 rounded w-1/2"></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto">
      <div className="p-4 border-b border-gray-200 space-y-2">
        <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
          <DialogTrigger asChild>
            <Button 
              variant="outline" 
              size="sm" 
              className="w-full" 
              data-testid="create-conversation"
              disabled={!deviceRegistered}
            >
              <UserIcon className="h-4 w-4 mr-2" />
              New Direct Chat
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Start a direct conversation</DialogTitle>
            </DialogHeader>
            <div className="space-y-4">
              <div>
                <label className="text-sm font-medium mb-2 block">Email address</label>
                <UserSearchCombobox
                  value={newConversationEmail}
                  onSelect={setNewConversationEmail}
                  placeholder="Search for users by name or email..."
                />
              </div>
              <div>
                <label className="text-sm font-medium">Conversation name (optional)</label>
                <Input
                  type="text"
                  placeholder="Enter conversation name"
                  value={newConversationName}
                  onChange={(e) => setNewConversationName(e.target.value)}
                />
              </div>
              <Button onClick={handleCreateConversation} className="w-full">
                Create Conversation
              </Button>
            </div>
          </DialogContent>
        </Dialog>
        
        <CreateGroupDialog
          onCreateGroup={onCreateGroup}
          trigger={
            <Button 
              variant="outline" 
              size="sm" 
              className="w-full"
              disabled={!deviceRegistered}
            >
              <UserGroupIcon className="h-4 w-4 mr-2" />
              Create Group
            </Button>
          }
        />
      </div>

      <div className="divide-y divide-gray-200">
        {conversations.map((conversation) => (
          <div
            key={conversation.id}
            className={`p-4 cursor-pointer hover:bg-gray-50 transition-colors ${
              activeConversation?.id === conversation.id ? 'bg-blue-50 border-r-2 border-blue-500' : ''
            }`}
            onClick={() => onSelectConversation(conversation)}
            data-testid="conversation-item"
          >
            <div className="flex items-center space-x-3">
              <div className="h-12 w-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                {typeof getConversationAvatar(conversation) === 'string' && 
                 getConversationAvatar(conversation).length === 1 ? 
                  getConversationAvatar(conversation) : 
                  <UserIcon className="h-6 w-6" />
                }
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-medium text-gray-900 truncate">
                    {getConversationName(conversation)}
                  </h3>
                  {conversation.last_message_at && (
                    <span className="text-xs text-gray-500">
                      {formatDistanceToNow(new Date(conversation.last_message_at), { addSuffix: true })}
                    </span>
                  )}
                </div>
                <div className="flex items-center justify-between">
                  <p className="text-sm text-gray-500 truncate">
                    {conversation.type === 'direct' ? 'Direct message' : 'Group conversation'}
                    {conversation.participants && (
                      <span className="ml-1">• {conversation.participants.length} member{conversation.participants.length !== 1 ? 's' : ''}</span>
                    )}
                  </p>
                  <div className="flex items-center space-x-1">
                    <div className="h-2 w-2 bg-green-400 rounded-full" title="End-to-end encrypted" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        ))}
        
        {conversations.length === 0 && !loading && (
          <div className="p-8 text-center text-gray-500">
            <UserIcon className="mx-auto h-12 w-12 text-gray-400" />
            <h3 className="mt-4 text-sm font-medium text-gray-900">No conversations</h3>
            <p className="mt-2 text-sm text-gray-500">Start a new conversation to begin messaging</p>
          </div>
        )}
      </div>
    </div>
  );
}