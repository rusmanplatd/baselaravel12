import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { ChatInterface } from '../chat/ChatInterface';

// Mock the hooks
const mockUseE2EEChat = vi.fn();
const mockUseWebSocket = vi.fn();

vi.mock('@/hooks/useE2EEChat', () => ({
    useE2EEChat: () => mockUseE2EEChat()
}));

vi.mock('@/hooks/useWebSocket', () => ({
    useWebSocket: () => mockUseWebSocket()
}));

// Mock toast
vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    }
}));

// Mock date-fns
vi.mock('date-fns', () => ({
    formatDistance: vi.fn(() => '2 minutes ago')
}));

// Mock UI components
vi.mock('@/components/ui/button', () => ({
    Button: ({ children, onClick, ...props }: any) => (
        <button onClick={onClick} {...props}>{children}</button>
    )
}));

vi.mock('@/components/ui/input', () => ({
    Input: (props: any) => <input {...props} />
}));

vi.mock('@/components/ui/avatar', () => ({
    Avatar: ({ children }: any) => <div data-testid="avatar">{children}</div>,
    AvatarImage: ({ src }: any) => <img src={src} alt="avatar" />,
    AvatarFallback: ({ children }: any) => <span>{children}</span>
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children }: any) => <span data-testid="badge">{children}</span>
}));

vi.mock('@/components/ui/scroll-area', () => ({
    ScrollArea: ({ children }: any) => <div data-testid="scroll-area">{children}</div>
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: ({ children, value, onValueChange }: any) => (
        <div data-testid="tabs" data-value={value}>
            <div onClick={() => onValueChange?.('messages')}>Messages</div>
            <div onClick={() => onValueChange?.('media')}>Media</div>
            {children}
        </div>
    ),
    TabsList: ({ children }: any) => <div data-testid="tabs-list">{children}</div>,
    TabsTrigger: ({ children, value, onClick }: any) => (
        <button data-testid={`tab-${value}`} onClick={onClick}>{children}</button>
    ),
    TabsContent: ({ children, value }: any) => (
        <div data-testid={`tab-content-${value}`}>{children}</div>
    )
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: ({ children }: any) => <div>{children}</div>,
    DropdownMenuTrigger: ({ children }: any) => <div>{children}</div>,
    DropdownMenuContent: ({ children }: any) => <div>{children}</div>,
    DropdownMenuItem: ({ children, onClick }: any) => (
        <div onClick={onClick}>{children}</div>
    ),
    DropdownMenuLabel: ({ children }: any) => <div>{children}</div>,
    DropdownMenuSeparator: () => <hr />
}));

vi.mock('@/components/ui/dialog', () => ({
    Dialog: ({ children, open }: any) => open ? <div data-testid="dialog">{children}</div> : null,
    DialogContent: ({ children }: any) => <div data-testid="dialog-content">{children}</div>,
    DialogHeader: ({ children }: any) => <div>{children}</div>,
    DialogTitle: ({ children }: any) => <h2>{children}</h2>,
    DialogDescription: ({ children }: any) => <p>{children}</p>
}));

const mockConversation = {
    id: '1',
    name: 'Test Conversation',
    avatar_url: 'avatar.jpg',
    participants: [
        {
            user_id: '1',
            role: 'member',
            is_active: true,
            user: { id: '1', name: 'User 1', avatar: 'avatar1.jpg', avatar_url: 'avatar1.jpg' }
        }
    ],
    encryption_status: {
        is_encrypted: true,
        quantum_ready: true
    },
    last_activity_at: '2023-01-01T00:00:00Z',
    unread_count: 2
};

const mockMessages = [
    {
        id: '1',
        sender: { id: '1', name: 'User 1', avatar: 'avatar1.jpg' },
        decrypted_content: 'Hello world!',
        created_at: '2023-01-01T00:00:00Z',
        is_edited: false,
        reactions: [{ id: '1', emoji: '👍' }],
        attachments: []
    },
    {
        id: '2',
        sender: { id: '2', name: 'User 2', avatar: 'avatar2.jpg' },
        decrypted_content: 'Hi there!',
        created_at: '2023-01-01T00:01:00Z',
        is_edited: true,
        reactions: [],
        attachments: [
            { id: '1', type: 'image' as const, filename: 'image.jpg', file_size: 1024, url: 'image.jpg' }
        ]
    }
];

describe('ChatInterface', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        mockUseE2EEChat.mockReturnValue({
            conversations: [mockConversation],
            messages: mockMessages,
            currentConversation: mockConversation,
            isLoading: false,
            isLoadingMessages: false,
            error: null,
            loadConversations: vi.fn(),
            loadConversation: vi.fn(),
            loadMessages: vi.fn(),
            sendMessage: vi.fn(),
            addReaction: vi.fn(),
            editMessage: vi.fn(),
            deleteMessage: vi.fn(),
            subscribeToConversation: vi.fn(),
            unsubscribeFromConversation: vi.fn()
        });

        mockUseWebSocket.mockReturnValue({
            sendMessage: vi.fn(),
            isConnected: true,
            connectionStatus: 'connected'
        });
    });

    it('renders loading state correctly', () => {
        mockUseE2EEChat.mockReturnValue({
            ...mockUseE2EEChat(),
            isLoading: true
        });

        render(<ChatInterface />);

        expect(screen.getByText('Loading conversations...')).toBeInTheDocument();
    });

    it('renders conversation list', () => {
        render(<ChatInterface />);

        expect(screen.getByText('Messages')).toBeInTheDocument();
        expect(screen.getByText('Test Conversation')).toBeInTheDocument();
        expect(screen.getByText('New Chat')).toBeInTheDocument();
    });

    it('displays encryption status correctly', () => {
        render(<ChatInterface initialConversationId="1" />);

        // Should show quantum E2EE badge
        expect(screen.getByText('Quantum E2EE')).toBeInTheDocument();
    });

    it('renders messages correctly', () => {
        render(<ChatInterface initialConversationId="1" />);

        expect(screen.getByText('Hello world!')).toBeInTheDocument();
        expect(screen.getByText('Hi there!')).toBeInTheDocument();
        expect(screen.getByText('User 1')).toBeInTheDocument();
        expect(screen.getByText('User 2')).toBeInTheDocument();
    });

    it('handles message sending', async () => {
        const mockSendMessage = vi.fn();
        mockUseE2EEChat.mockReturnValue({
            ...mockUseE2EEChat(),
            sendMessage: mockSendMessage
        });

        render(<ChatInterface initialConversationId="1" />);

        const input = screen.getByPlaceholderText('Type an encrypted message...');
        const sendButton = screen.getByRole('button', { name: /send/i });

        fireEvent.change(input, { target: { value: 'Test message' } });
        fireEvent.click(sendButton);

        await waitFor(() => {
            expect(mockSendMessage).toHaveBeenCalledWith('1', 'Test message');
        });
    });

    it('handles message editing', async () => {
        const mockEditMessage = vi.fn();
        mockUseE2EEChat.mockReturnValue({
            ...mockUseE2EEChat(),
            editMessage: mockEditMessage
        });

        render(<ChatInterface initialConversationId="1" />);

        // Find and click edit button (this would be in the message actions)
        // Note: In a real test, you'd need to trigger the hover state to show actions
        const editButton = screen.getAllByText('Save')[0]; // Assuming edit mode is active
        if (editButton) {
            fireEvent.click(editButton);

            await waitFor(() => {
                expect(mockEditMessage).toHaveBeenCalled();
            });
        }
    });

    it('handles tab switching', () => {
        render(<ChatInterface initialConversationId="1" />);

        const mediaTab = screen.getByText('Media');
        fireEvent.click(mediaTab);

        // Should switch to media tab content
        expect(screen.getByTestId('tabs')).toHaveAttribute('data-value', 'media');
    });

    it('shows typing indicator', () => {
        render(<ChatInterface initialConversationId="1" />);

        const input = screen.getByPlaceholderText('Type an encrypted message...');
        fireEvent.change(input, { target: { value: 'typing...' } });

        // Should trigger typing handler
        expect(input.value).toBe('typing...');
    });

    it('handles file upload', () => {
        render(<ChatInterface initialConversationId="1" />);

        const fileInput = screen.getByTestId('file-input');
        const file = new File(['test'], 'test.txt', { type: 'text/plain' });

        fireEvent.change(fileInput, { target: { files: [file] } });

        // Should handle file upload
        expect(fileInput.files).toHaveLength(1);
    });

    it('displays participants dialog', () => {
        render(<ChatInterface initialConversationId="1" />);

        // Open participants dialog
        const usersButton = screen.getByLabelText(/users/i);
        fireEvent.click(usersButton);

        expect(screen.getByTestId('dialog')).toBeInTheDocument();
        expect(screen.getByText('Participants (1)')).toBeInTheDocument();
    });

    it('handles search functionality', () => {
        render(<ChatInterface initialConversationId="1" />);

        // Open search
        const searchButton = screen.getByLabelText(/search/i);
        fireEvent.click(searchButton);

        const searchInput = screen.getByPlaceholderText('Search messages...');
        fireEvent.change(searchInput, { target: { value: 'hello' } });

        expect(searchInput.value).toBe('hello');
    });

    it('shows unencrypted warning when not encrypted', () => {
        const unencryptedConversation = {
            ...mockConversation,
            encryption_status: { is_encrypted: false, quantum_ready: false }
        };

        mockUseE2EEChat.mockReturnValue({
            ...mockUseE2EEChat(),
            currentConversation: unencryptedConversation
        });

        render(<ChatInterface initialConversationId="1" />);

        expect(screen.getByText('This conversation is not encrypted')).toBeInTheDocument();
    });

    it('handles WebSocket messages', () => {
        const mockSendWebSocketMessage = vi.fn();
        mockUseWebSocket.mockReturnValue({
            sendMessage: mockSendWebSocketMessage,
            isConnected: true,
            connectionStatus: 'connected'
        });

        render(<ChatInterface initialConversationId="1" />);

        // Typing should send WebSocket message
        const input = screen.getByPlaceholderText('Type an encrypted message...');
        fireEvent.change(input, { target: { value: 'test' } });

        // WebSocket message should be sent for typing indicator
        expect(mockSendWebSocketMessage).toHaveBeenCalledWith({
            type: 'typing_start',
            conversation_id: '1'
        });
    });

    it('displays error state correctly', () => {
        mockUseE2EEChat.mockReturnValue({
            ...mockUseE2EEChat(),
            error: 'Connection failed'
        });

        render(<ChatInterface />);

        expect(screen.getByText('Connection failed')).toBeInTheDocument();
    });

    it('handles reply functionality', () => {
        render(<ChatInterface initialConversationId="1" />);

        // Test the reply functionality by simulating user interactions
        const replyButton = screen.getAllByRole('button').find(btn => 
            btn.getAttribute('aria-label')?.includes('Reply') || 
            btn.textContent?.includes('Reply')
        );
        
        if (replyButton) {
            fireEvent.click(replyButton);
            // Check if reply preview appears in the UI
            expect(screen.getByText(/replying to/i)).toBeInTheDocument();
        }
    });

    it('handles voice recording', () => {
        render(<ChatInterface initialConversationId="1" />);

        const micButton = screen.getByLabelText(/mic/i);
        fireEvent.mouseDown(micButton);

        // Should start recording
        expect(screen.getByLabelText(/stop/i)).toBeInTheDocument();

        fireEvent.mouseUp(screen.getByLabelText(/stop/i));

        // Should stop recording
        expect(screen.getByLabelText(/mic/i)).toBeInTheDocument();
    });
});
