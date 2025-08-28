import React, { useEffect, useState } from 'react';
import { User } from '@/types';
import ConversationList from './ConversationList';
import ChatWindow from './ChatWindow';
import { useChat } from '@/hooks/useChat';
import { toast } from 'sonner';
import DeviceSetup from './DeviceSetup';
import DeviceManagement from './DeviceManagement';
import DeviceSetupOverlay from './DeviceSetupOverlay';
import DeviceSetupDialog from './DeviceSetupDialog';
import DeviceManagementDialog from './DeviceManagementDialog';
import AccessRevokedOverlay from './AccessRevokedOverlay';
import E2EEStatusBadge from './E2EEStatusBadge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ShieldCheckIcon, CogIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

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
  const [showNewDeviceSetup, setShowNewDeviceSetup] = useState(false);
  const [showNewDeviceManagement, setShowNewDeviceManagement] = useState(false);
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

  return (
    <div className="flex h-screen bg-gray-100" data-testid="chat-layout">
      {/* Sidebar */}
      <div className="w-1/3 bg-white border-r border-gray-200" data-testid="conversation-list">
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-semibold text-gray-900">Messages</h1>
              <div className="flex items-center">
                <E2EEStatusBadge 
                  status={encryptionReady ? 'enabled' : 'disabled'} 
                  onClick={() => setShowNewDeviceManagement(true)}
                />
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
      <div className="flex-1 flex flex-col relative" data-testid="chat-window">
        {!deviceRegistered && (
          <DeviceSetupOverlay onStartSetup={() => setShowNewDeviceSetup(true)} />
        )}
        
        {accessRevoked && (
          <AccessRevokedOverlay 
            deviceName="this device" 
            onReauthorize={() => {
              setAccessRevoked(false);
              setShowNewDeviceSetup(true);
            }}
          />
        )}
        
        {activeConversation ? (
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
      
      {/* New Device Setup Dialog */}
      <DeviceSetupDialog
        isOpen={showNewDeviceSetup}
        onClose={() => setShowNewDeviceSetup(false)}
        onComplete={() => {
          setShowNewDeviceSetup(false);
          // Simulate device registration
          toast.success('Device setup completed successfully');
        }}
      />

      {/* New Device Management Dialog */}
      <DeviceManagementDialog
        isOpen={showNewDeviceManagement}
        onClose={() => setShowNewDeviceManagement(false)}
        devices={[]}
        onTrustDevice={(deviceId) => {
          toast.success('Device trusted successfully');
        }}
        onRevokeDevice={(deviceId) => {
          // Simulate revoking current device access
          if (deviceId === 'current') {
            setAccessRevoked(true);
            setShowNewDeviceManagement(false);
            toast.error('Your device access has been revoked');
          } else {
            toast.success('Device access revoked');
          }
        }}
        onRotateKeys={() => {
          toast.success('Key rotation completed');
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
    </div>
  );
}