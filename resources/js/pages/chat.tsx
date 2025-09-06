import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useE2EEChat } from '@/hooks/useE2EEChat';
import ChatSidebar from '@/components/chat/chat-sidebar';
import ChatWindow from '@/components/chat/chat-window';
import DeviceSetupDialog from '@/components/chat/device-setup-dialog';
import { PageProps } from '@/types';

interface ChatPageProps extends PageProps {
    initialConversationId?: string;
}

export default function Chat({ auth, initialConversationId }: ChatPageProps) {
    const {
        conversations,
        messages,
        currentConversation,
        devices,
        isLoading,
        isLoadingMessages,
        error,
        loadConversations,
        loadConversation,
        loadMessages,
        sendMessage,
        createConversation,
        registerDevice,
        subscribeToConversation,
        unsubscribeFromConversation,
    } = useE2EEChat();

    const [selectedConversationId, setSelectedConversationId] = useState<string | null>(
        initialConversationId || null
    );
    const [showDeviceSetup, setShowDeviceSetup] = useState(false);
    const [isRegisteringDevice, setIsRegisteringDevice] = useState(false);
    const [deviceSetupDismissed, setDeviceSetupDismissed] = useState(false);

    // Load conversations on mount
    useEffect(() => {
        loadConversations();
    }, [loadConversations]);

    // Auto-show device setup dialog when no devices are registered
    useEffect(() => {
        if (devices.length === 0 && !isLoading && !showDeviceSetup && !deviceSetupDismissed) {
            setShowDeviceSetup(true);
        } else if (devices.length > 0) {
            // Hide modal and reset dismissed state if devices are found
            setShowDeviceSetup(false);
            setDeviceSetupDismissed(false);
        }
    }, [devices.length, isLoading, showDeviceSetup, deviceSetupDismissed]);

    // Load initial conversation if provided
    useEffect(() => {
        if (initialConversationId) {
            setSelectedConversationId(initialConversationId);
            loadConversation(initialConversationId);
            loadMessages(initialConversationId);
            subscribeToConversation(initialConversationId);
        }
    }, [initialConversationId]);

    // Subscribe to conversation changes
    useEffect(() => {
        if (selectedConversationId && selectedConversationId !== currentConversation?.id) {
            // Unsubscribe from previous conversation
            if (currentConversation?.id) {
                unsubscribeFromConversation(currentConversation.id);
            }
            
            // Load and subscribe to new conversation
            loadConversation(selectedConversationId);
            loadMessages(selectedConversationId);
            subscribeToConversation(selectedConversationId);
        }
    }, [selectedConversationId, currentConversation?.id]);

    const handleConversationSelect = (conversationId: string) => {
        setSelectedConversationId(conversationId);
    };

    const handleSendMessage = async (content: string, options?: any) => {
        if (!selectedConversationId) return;
        
        try {
            await sendMessage(selectedConversationId, content, options);
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    };

    const handleNewConversation = async (participants: string[], options?: any) => {
        try {
            const conversation = await createConversation(participants, options);
            // Refresh conversations list
            await loadConversations();
            // Auto-select the new conversation
            if (conversation?.id) {
                setSelectedConversationId(conversation.id);
            }
        } catch (error) {
            console.error('Failed to create conversation:', error);
        }
    };

    const handleRegisterDevice = async (deviceInfo: any) => {
        setIsRegisteringDevice(true);
        try {
            await registerDevice(deviceInfo);
            setShowDeviceSetup(false);
            setDeviceSetupDismissed(true); // Mark as dismissed after successful registration
        } catch (error) {
            console.error('Failed to register device:', error);
            throw error; // Re-throw so the dialog can show the error
        } finally {
            setIsRegisteringDevice(false);
        }
    };

    const handleDeviceSetupClose = (open: boolean) => {
        setShowDeviceSetup(open);
        if (!open) {
            setDeviceSetupDismissed(true); // Mark as dismissed when manually closed
        }
    };

    return (
        <AppLayout>
            <Head title="Chat - E2EE Secure Messaging" />

            <div className="flex h-screen bg-background">
                {/* Chat Sidebar */}
                <div className="w-80 border-r border-border flex-shrink-0">
                    <ChatSidebar
                        conversations={conversations}
                        selectedConversationId={selectedConversationId}
                        onConversationSelect={handleConversationSelect}
                        onNewConversation={handleNewConversation}
                        isLoading={isLoading}
                        error={error}
                        currentUser={auth.user}
                    />
                </div>

                {/* Chat Window */}
                <div className="flex-1 flex flex-col">
                    <ChatWindow
                        conversation={currentConversation}
                        messages={messages}
                        onSendMessage={handleSendMessage}
                        isLoadingMessages={isLoadingMessages}
                        error={error}
                        currentUser={auth.user}
                    />
                </div>
            </div>

            {/* Device Setup Dialog */}
            <DeviceSetupDialog
                open={showDeviceSetup}
                onOpenChange={handleDeviceSetupClose}
                onRegisterDevice={handleRegisterDevice}
                isRegistering={isRegisteringDevice}
                error={error}
            />

            {/* Quantum Status Indicator */}
            {currentConversation?.encryption_status.quantum_ready && (
                <div className="fixed bottom-4 left-4 bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded-full text-xs font-medium">
                    🔒 Quantum-Safe
                </div>
            )}
        </AppLayout>
    );
}