import { renderHook, act } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { useWebSocket } from '../useWebSocket';

// Mock WebSocket
class MockWebSocket {
    static CONNECTING = 0;
    static OPEN = 1;
    static CLOSING = 2;
    static CLOSED = 3;

    readyState = MockWebSocket.CONNECTING;
    url: string;
    onopen: ((event: Event) => void) | null = null;
    onmessage: ((event: MessageEvent) => void) | null = null;
    onclose: ((event: CloseEvent) => void) | null = null;
    onerror: ((event: Event) => void) | null = null;

    constructor(url: string) {
        this.url = url;
        
        // Simulate connection opening
        setTimeout(() => {
            this.readyState = MockWebSocket.OPEN;
            if (this.onopen) {
                this.onopen(new Event('open'));
            }
        }, 10);
    }

    send(data: string) {
        if (this.readyState === MockWebSocket.OPEN) {
            // Simulate message sent successfully
            return;
        }
        throw new Error('WebSocket is not open');
    }

    close() {
        this.readyState = MockWebSocket.CLOSED;
        if (this.onclose) {
            this.onclose(new CloseEvent('close'));
        }
    }

    // Helper methods for testing
    simulateMessage(data: any) {
        if (this.onmessage) {
            const messageEvent = new MessageEvent('message', {
                data: JSON.stringify(data)
            });
            this.onmessage(messageEvent);
        }
    }

    simulateError() {
        if (this.onerror) {
            this.onerror(new Event('error'));
        }
    }
}

// Mock global WebSocket
(global as any).WebSocket = MockWebSocket;

describe('useWebSocket', () => {
    let mockWebSocket: MockWebSocket;

    beforeEach(() => {
        vi.clearAllMocks();
        mockWebSocket = new MockWebSocket('ws://localhost:8080/ws');
    });

    afterEach(() => {
        if (mockWebSocket) {
            mockWebSocket.close();
        }
    });

    it('establishes WebSocket connection', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {}
            })
        );

        expect(result.current.isConnected).toBe(false);
        expect(result.current.connectionStatus).toBe('connecting');

        // Wait for connection to open
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        expect(result.current.isConnected).toBe(true);
        expect(result.current.connectionStatus).toBe('connected');
    });

    it('handles message reception', async () => {
        const onMessage = vi.fn();
        
        renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: { onMessage }
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        // Simulate receiving a message
        act(() => {
            mockWebSocket.simulateMessage({ type: 'test', data: 'hello' });
        });

        expect(onMessage).toHaveBeenCalled();
    });

    it('sends messages correctly', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {}
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        const sendSpy = vi.spyOn(mockWebSocket, 'send');

        act(() => {
            result.current.sendMessage({ type: 'test', message: 'hello' });
        });

        expect(sendSpy).toHaveBeenCalledWith(
            JSON.stringify({
                type: 'test',
                message: 'hello',
                timestamp: expect.any(String)
            })
        );
    });

    it('handles connection errors', async () => {
        const onError = vi.fn();
        
        renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: { onError }
            })
        );

        // Simulate error
        act(() => {
            mockWebSocket.simulateError();
        });

        expect(onError).toHaveBeenCalled();
    });

    it('attempts reconnection on disconnect', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {
                    reconnectInterval: 100,
                    maxReconnectAttempts: 2
                }
            })
        );

        // Wait for initial connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        expect(result.current.isConnected).toBe(true);

        // Simulate disconnect
        act(() => {
            mockWebSocket.close();
        });

        expect(result.current.isConnected).toBe(false);
        expect(result.current.connectionStatus).toBe('disconnected');

        // Should attempt reconnection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 150));
        });

        expect(result.current.connectionStatus).toBe('reconnecting');
    });

    it('handles ping/pong heartbeat', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {
                    heartbeatInterval: 100
                }
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        const sendSpy = vi.spyOn(mockWebSocket, 'send');

        // Wait for heartbeat
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 150));
        });

        expect(sendSpy).toHaveBeenCalledWith(
            JSON.stringify({ type: 'ping' })
        );
    });

    it('can be manually disconnected', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {}
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        expect(result.current.isConnected).toBe(true);

        // Manually disconnect
        act(() => {
            result.current.disconnect();
        });

        expect(result.current.isConnected).toBe(false);
        expect(result.current.connectionStatus).toBe('closed');
    });

    it('can manually reconnect', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {}
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        // Disconnect
        act(() => {
            result.current.disconnect();
        });

        expect(result.current.isConnected).toBe(false);

        // Reconnect
        act(() => {
            result.current.reconnect();
        });

        // Should be attempting to connect
        expect(result.current.connectionStatus).toBe('connecting');
    });

    it('handles disabled state correctly', () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {},
                enabled: false
            })
        );

        expect(result.current.isConnected).toBe(false);
        expect(result.current.connectionStatus).toBe('disconnected');
    });

    it('filters pong messages correctly', async () => {
        const { result } = renderHook(() => 
            useWebSocket({
                url: 'ws://localhost:8080/ws',
                options: {}
            })
        );

        // Wait for connection
        await act(async () => {
            await new Promise(resolve => setTimeout(resolve, 20));
        });

        // Simulate pong message
        act(() => {
            mockWebSocket.simulateMessage({ type: 'pong' });
        });

        // lastMessage should not be updated for pong
        expect(result.current.lastMessage).toBeNull();

        // Simulate regular message
        act(() => {
            mockWebSocket.simulateMessage({ type: 'message', data: 'test' });
        });

        // lastMessage should be updated for regular messages
        expect(result.current.lastMessage).toEqual({ type: 'message', data: 'test' });
    });
});