import React, { useEffect, useState } from 'react';
import { User } from '@/types';
import { Channel, Conversation } from '@/types/chat';
import ConversationList from './ConversationList';
import ChatWindow from './ChatWindow';
import ChannelSidebar from '@/components/ChannelSidebar';
import { useChat } from '@/hooks/useChat';
import { toast } from 'sonner';
import DeviceSetup from './DeviceSetup';
import DeviceManagement from './DeviceManagement';
import DeviceSetupOverlay from './DeviceSetupOverlay';
import DeviceSetupDialog from './DeviceSetupDialog';
import DeviceManagementDialog from './DeviceManagementDialog';
import AccessRevokedOverlay from './AccessRevokedOverlay';
import E2EEStatusBadge from './E2EEStatusBadge';
import MentionNotifications from './MentionNotification';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { ShieldCheckIcon, CogIcon, ExclamationTriangleIcon, HashtagIcon, ChatBubbleLeftRightIcon, ShieldExclamationIcon } from '@heroicons/react/24/outline';
import QuantumSecurityDashboard from '@/components/QuantumSecurityDashboard';
import { cn } from '@/lib/utils';

interface ChatLayoutProps {
  user: User;
  inviteCode?: string;
}

export default function ChatLayout({ user, inviteCode }: ChatLayoutProps) {
  const {
    conversations,
    activeConversation,
    messages,
    loading,
    error,
    sendMessage,
    createConversation,
    setActiveConversation,
    encryptionReady,
    replyingTo,
    setReplyingTo,
    typingUsers,
    toggleReaction,
    // Multi-device E2EE
    deviceRegistered,
    initializeDevice,
    registerDevice,
    deviceSecurityStatus,
    e2eeStatus,
    // Group management
    createGroup,
    updateGroupSettings,
    updateParticipantRole,
    removeParticipant,
    generateInviteLink,
    joinByInvite,
  } = useChat(user);

  const [activeTab, setActiveTab] = useState<'conversations' | 'channels'>('conversations');
  const [selectedChannel, setSelectedChannel] = useState<Channel | null>(null);
  const [showDeviceSetup, setShowDeviceSetup] = useState(false);
  const [showDeviceManagement, setShowDeviceManagement] = useState(false);
  const [showNewDeviceSetup, setShowNewDeviceSetup] = useState(false);
  const [showNewDeviceManagement, setShowNewDeviceManagement] = useState(false);
  const [showQuantumSecurity, setShowQuantumSecurity] = useState(false);
  const [accessRevoked, setAccessRevoked] = useState(false);

  // Handle invite code if provided
  useEffect(() => {
    if (inviteCode) {
      const handleInviteCode = async () => {
        try {
          await joinByInvite(inviteCode);
          toast.success('Successfully joined the group!');
          // Remove invite code from URL
          window.history.replaceState({}, document.title, '/chat');
        } catch (error: any) {
          toast.error(error.message || 'Failed to join group');
          // Redirect to regular chat page on error
          window.history.replaceState({}, document.title, '/chat');
        }
      };
      
      handleInviteCode();
    }
  }, [inviteCode, joinByInvite]);

  const handleChannelSelect = (channel: Channel) => {
    setSelectedChannel(channel);
    setActiveTab('channels');
    
    // If the channel has a conversation, also set it as active
    if (channel.conversation) {
      setActiveConversation(channel.conversation);
    }
  };

  const handleConversationSelect = (conversation: Conversation) => {
    setActiveConversation(conversation);
    
    // If we're switching to a non-channel conversation, clear channel selection
    if (!conversation.channel) {
      setSelectedChannel(null);
      setActiveTab('conversations');
    }
  };

  const activeEntity = selectedChannel || activeConversation;
  const isChannelActive = !!selectedChannel;

  return (
    <div className="flex h-screen bg-gray-100" data-testid="chat-layout">
      {/* Left Sidebar - Conversations and Channels */}
      <div className="w-80 bg-white border-r border-gray-200 flex flex-col">
        {/* Header */}
        <div className="p-4 border-b border-gray-200 flex-shrink-0">
          <div className="flex items-center justify-between mb-3">
            <h1 className="text-xl font-semibold text-gray-900">Chat</h1>
            
            <div className="flex space-x-2">
              {!deviceRegistered && (
                <Button 
                  size="sm" 
                  onClick={() => setShowDeviceSetup(true)}
                  className="bg-blue-600 hover:bg-blue-700"
                >
                  Setup Device
                </Button>
              )}
              
              {deviceRegistered && (
                <>
                  <Dialog open={showDeviceManagement} onOpenChange={setShowDeviceManagement}>
                    <DialogTrigger asChild>
                      <Button size="sm" variant="outline">
                        <CogIcon className="h-4 w-4 mr-1" />
                        Devices
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
                      <DialogHeader>
                        <DialogTitle>Device Management</DialogTitle>
                        <DialogDescription>
                          Manage your devices and encryption settings
                        </DialogDescription>
                      </DialogHeader>
                      <DeviceManagement 
                        onDeviceRegistered={() => {
                          setShowDeviceManagement(false);
                          toast.success('Device registered successfully');
                        }}
                        onDeviceRemoved={(deviceId) => {
                          toast.success('Device removed successfully');
                        }}
                      />
                    </DialogContent>
                  </Dialog>

                  <Dialog open={showQuantumSecurity} onOpenChange={setShowQuantumSecurity}>
                    <DialogTrigger asChild>
                      <Button size="sm" variant="outline" className="bg-gradient-to-r from-purple-50 to-blue-50 hover:from-purple-100 hover:to-blue-100 border-purple-200">
                        <ShieldExclamationIcon className="h-4 w-4 mr-1 text-purple-600" />
                        <span className="bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent font-medium">
                          Quantum Security
                        </span>
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-6xl max-h-[90vh] overflow-y-auto">
                      <DialogHeader>
                        <DialogTitle className="bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                          Quantum Security Dashboard
                        </DialogTitle>
                        <DialogDescription>
                          Monitor quantum-resistant encryption, threat detection, and security metrics
                        </DialogDescription>
                      </DialogHeader>
                      <QuantumSecurityDashboard />
                    </DialogContent>
                  </Dialog>
                </>
              )}
            </div>
          </div>

          {/* E2EE Status */}
          <div className="flex flex-col space-y-2 mb-4">
            <E2EEStatusBadge 
              status={encryptionReady ? 'enabled' : 'disabled'} 
              onClick={() => setShowNewDeviceManagement(true)}
            />
            {encryptionReady && (
              <div className="flex items-center space-x-1 text-xs">
                <div className="w-2 h-2 bg-gradient-to-r from-purple-400 to-blue-500 rounded-full animate-pulse"></div>
                <span className="text-purple-600 font-medium">Quantum-Safe</span>
              </div>
            )}
          </div>

          {/* Tab Navigation */}
          <Tabs value={activeTab} onValueChange={(value: 'conversations' | 'channels') => setActiveTab(value)}>
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="conversations" className="flex items-center gap-2">
                <ChatBubbleLeftRightIcon className="h-4 w-4" />
                Messages
                {conversations.length > 0 && (
                  <Badge variant="secondary" className="ml-1 h-5">
                    {conversations.length}
                  </Badge>
                )}
              </TabsTrigger>
              <TabsTrigger value="channels" className="flex items-center gap-2">
                <HashtagIcon className="h-4 w-4" />
                Channels
              </TabsTrigger>
            </TabsList>
          </Tabs>
        </div>

        {/* Content Area */}
        <div className="flex-1 overflow-hidden">
          <Tabs value={activeTab} className="h-full">
            <TabsContent value="conversations" className="h-full m-0">
              <ConversationList
                conversations={conversations}
                activeConversation={activeConversation}
                onSelectConversation={handleConversationSelect}
                onCreateConversation={createConversation}
                onCreateGroup={createGroup}
                currentUser={user}
                loading={loading}
                deviceRegistered={deviceRegistered}
              />
            </TabsContent>
            
            <TabsContent value="channels" className="h-full m-0">
              <ChannelSidebar
                selectedChannel={selectedChannel}
                onChannelSelect={handleChannelSelect}
                onConversationSelect={handleConversationSelect}
                organizationId={user.organization_id}
                className="h-full"
              />
            </TabsContent>
          </Tabs>
        </div>
      </div>

      {/* Main Chat Area */}
      <div className="flex-1 flex flex-col relative" data-testid="chat-window">
        {activeEntity ? (
          <>
            {/* Chat Header */}
            <div className="bg-white border-b border-gray-200 px-6 py-4 flex-shrink-0">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="flex-shrink-0">
                    {isChannelActive ? (
                      selectedChannel?.visibility === 'private' ? (
                        <div className="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                          <HashtagIcon className="w-4 h-4 text-red-600" />
                        </div>
                      ) : (
                        <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                          <HashtagIcon className="w-4 h-4 text-blue-600" />
                        </div>
                      )
                    ) : (
                      <div className="w-8 h-8 bg-gray-200 rounded-full"></div>
                    )}
                  </div>
                  <div>
                    <h2 className="text-lg font-semibold text-gray-900">
                      {isChannelActive ? selectedChannel?.name : activeConversation?.name || 'Chat'}
                    </h2>
                    <div className="flex items-center space-x-2 text-sm text-gray-500">
                      {isChannelActive && selectedChannel?.description && (
                        <span>{selectedChannel.description}</span>
                      )}
                      {activeEntity?.participants && (
                        <span>
                          {activeEntity.participants.filter(p => !p.left_at).length} members
                        </span>
                      )}
                      <span className="inline-flex items-center gap-1">
                        <ShieldCheckIcon className="w-3 h-3 text-green-600" />
                        E2EE
                      </span>
                    </div>
                  </div>
                </div>

                {/* Channel/Conversation Actions */}
                <div className="flex items-center space-x-2">
                  {isChannelActive && selectedChannel && (
                    <Badge variant={selectedChannel.visibility === 'private' ? 'secondary' : 'default'}>
                      {selectedChannel.visibility}
                    </Badge>
                  )}
                </div>
              </div>
            </div>

            {/* Chat Messages */}
            <div className="flex-1 overflow-hidden">
              <ChatWindow
                conversation={activeConversation}
                messages={messages}
                onSendMessage={sendMessage}
                currentUser={user}
                replyingTo={replyingTo}
                onSetReplyingTo={setReplyingTo}
                typingUsers={typingUsers}
                onToggleReaction={toggleReaction}
                loading={loading}
                encryptionReady={encryptionReady}
                deviceRegistered={deviceRegistered}
                onDeviceSetup={() => setShowNewDeviceSetup(true)}
              />
            </div>
          </>
        ) : (
          /* Welcome Screen */
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center">
              <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <ChatBubbleLeftRightIcon className="w-8 h-8 text-blue-600" />
              </div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                Welcome to Encrypted Chat
              </h2>
              <p className="text-gray-500 mb-6 max-w-md">
                Select a conversation or channel to start chatting. All messages are end-to-end encrypted for your privacy.
              </p>
              <div className="space-y-2">
                <Button 
                  onClick={() => setActiveTab('conversations')}
                  variant={activeTab === 'conversations' ? 'default' : 'outline'}
                >
                  Browse Messages
                </Button>
                <Button 
                  onClick={() => setActiveTab('channels')}
                  variant={activeTab === 'channels' ? 'default' : 'outline'}
                >
                  Explore Channels
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Dialogs and Overlays */}
      {showDeviceSetup && (
        <DeviceSetupDialog
          isOpen={showDeviceSetup}
          onClose={() => setShowDeviceSetup(false)}
          onDeviceRegistered={(deviceInfo) => {
            registerDevice(deviceInfo);
            setShowDeviceSetup(false);
          }}
        />
      )}

      {showNewDeviceSetup && (
        <DeviceSetupDialog
          isOpen={showNewDeviceSetup}
          onClose={() => setShowNewDeviceSetup(false)}
          onDeviceRegistered={(deviceInfo) => {
            registerDevice(deviceInfo);
            setShowNewDeviceSetup(false);
            toast.success('Device registered successfully');
          }}
        />
      )}

      {showNewDeviceManagement && (
        <DeviceManagementDialog
          isOpen={showNewDeviceManagement}
          onClose={() => setShowNewDeviceManagement(false)}
        />
      )}

      {accessRevoked && (
        <AccessRevokedOverlay
          onClose={() => setAccessRevoked(false)}
          onDeviceSetup={() => setShowNewDeviceSetup(true)}
        />
      )}

      {/* Mention Notifications */}
      <MentionNotifications
        messages={messages}
        currentUserId={user.id}
        participants={activeConversation?.participants || []}
        conversationName={
          selectedChannel?.name || 
          activeConversation?.name || 
          (activeConversation?.type === 'direct' 
            ? activeConversation?.participants?.find(p => p.user_id !== user.id)?.user?.name || 'Chat'
            : 'Group Chat')
        }
        onNotificationClick={(message) => {
          // Scroll to message or focus chat
          console.log('Mention notification clicked:', message);
        }}
        onMarkAsRead={(notificationId) => {
          console.log('Mention marked as read:', notificationId);
        }}
      />

      {/* Device Setup Dialog */}
      <Dialog open={showDeviceSetup} onOpenChange={setShowDeviceSetup}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Device Setup</DialogTitle>
            <DialogDescription>
              Set up end-to-end encryption for secure messaging
            </DialogDescription>
          </DialogHeader>
          <DeviceSetup 
            onSetupComplete={() => {
              setShowDeviceSetup(false);
              toast.success('Device setup completed successfully');
            }}
            onSetupError={(error: string) => {
              toast.error(error || 'Failed to setup device');
            }}
          />
        </DialogContent>
      </Dialog>

      {error && (
        <div className="absolute bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          {error}
        </div>
      )}
    </div>
  );
}