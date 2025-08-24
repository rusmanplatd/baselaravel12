import React, { useEffect, useState } from 'react';
import { User } from '@/types';
import ConversationList from './ConversationList';
import ChatWindow from './ChatWindow';
import { useChat } from '@/hooks/useChat';
import { toast } from 'sonner';
import DeviceSetup from './DeviceSetup';
import DeviceManagement from './DeviceManagement';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ShieldCheckIcon, CogIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import { E2EEStatusBadge } from './E2EEStatusIndicator';

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

  const [showDeviceSetup, setShowDeviceSetup] = useState(false);
  const [showDeviceManagement, setShowDeviceManagement] = useState(false);

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

  return (
    <div className="flex h-screen bg-gray-100" data-testid="chat-layout">
      {/* Sidebar */}
      <div className="w-1/3 bg-white border-r border-gray-200" data-testid="conversation-list">
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-semibold text-gray-900">Messages</h1>
              <div className="flex items-center">
                <E2EEStatusBadge status={e2eeStatus} />
              </div>
            </div>
            
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
              )}
            </div>
          </div>
        </div>
        
        <ConversationList
          conversations={conversations}
          activeConversation={activeConversation}
          onSelectConversation={setActiveConversation}
          onCreateConversation={createConversation}
          onCreateGroup={createGroup}
          currentUser={user}
          loading={loading}
          deviceRegistered={deviceRegistered}
        />
      </div>

      {/* Main Chat Area */}
      <div className="flex-1 flex flex-col" data-testid="chat-window">
        {!deviceRegistered ? (
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center p-8 bg-white rounded-lg shadow-sm border max-w-md">
              <ShieldCheckIcon className="mx-auto h-16 w-16 text-blue-500 mb-4" />
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Secure Your Messages</h3>
              <p className="text-gray-600 mb-6">
                Set up your device for end-to-end encrypted messaging. Your messages will be protected with industry-standard encryption.
              </p>
              <Button 
                onClick={() => setShowDeviceSetup(true)}
                className="bg-blue-600 hover:bg-blue-700"
              >
                <ShieldCheckIcon className="h-4 w-4 mr-2" />
                Setup Device Encryption
              </Button>
            </div>
          </div>
        ) : activeConversation ? (
          <ChatWindow
            conversation={activeConversation}
            messages={messages}
            onSendMessage={sendMessage}
            currentUser={user}
            loading={loading}
            encryptionReady={encryptionReady && deviceRegistered}
            onReactionToggle={toggleReaction}
            onReplyClick={setReplyingTo}
            typingUsers={typingUsers}
            replyingTo={replyingTo}
            onUpdateGroupSettings={updateGroupSettings}
            onUpdateParticipantRole={updateParticipantRole}
            onRemoveParticipant={removeParticipant}
            onGenerateInviteLink={generateInviteLink}
          />
        ) : (
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center">
              <div className="mx-auto h-24 w-24 text-gray-400">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <h3 className="mt-4 text-lg font-medium text-gray-900">Select a conversation</h3>
              <p className="mt-2 text-sm text-gray-500">Choose a conversation from the sidebar to start messaging</p>
            </div>
          </div>
        )}
      </div>

      {error && (
        <div className="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          <span className="block sm:inline">{error}</span>
        </div>
      )}
      
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
            onComplete={async (deviceInfo) => {
              try {
                await registerDevice(deviceInfo);
                setShowDeviceSetup(false);
                toast.success('Device setup completed successfully');
              } catch (error: any) {
                toast.error(error.message || 'Failed to setup device');
              }
            }}
            onCancel={() => setShowDeviceSetup(false)}
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}