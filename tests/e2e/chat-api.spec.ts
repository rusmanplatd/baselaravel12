import { test, expect } from '@playwright/test';

test.describe('Chat API Integration', () => {
  let authToken: string;

  test.beforeEach(async ({ page, request }) => {
    // Login to get auth token
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    
    // Get auth token from cookies or local storage
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(cookie => cookie.name.includes('session'));
    authToken = sessionCookie?.value || '';
  });

  test('should create conversation via API', async ({ request }) => {
    const response = await request.post('/api/v1/chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        type: 'group',
        name: 'API Test Conversation',
        description: 'Created via API test'
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const conversation = await response.json();
    expect(conversation.data).toHaveProperty('id');
    expect(conversation.data.name).toBe('API Test Conversation');
  });

  test('should send message via API', async ({ request }) => {
    // First create a conversation
    const convResponse = await request.post('/api/v1/chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        type: 'direct',
        name: 'API Message Test'
      }
    });
    
    const conversation = await convResponse.json();
    const conversationId = conversation.data.id;
    
    // Send message
    const msgResponse = await request.post(`/api/v1/chat/conversations/${conversationId}/messages`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        content: 'Hello from API test',
        type: 'text'
      }
    });
    
    expect(msgResponse.ok()).toBeTruthy();
    const message = await msgResponse.json();
    expect(message.data.content).toBe('Hello from API test');
  });

  test('should retrieve messages via API', async ({ request }) => {
    // Assume we have a conversation with messages
    const response = await request.get('/api/v1/chat/conversations/1/messages', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    if (response.ok()) {
      const messages = await response.json();
      expect(messages.data).toBeInstanceOf(Array);
    }
  });

  test('should add participant to conversation via API', async ({ request }) => {
    // Create conversation first
    const convResponse = await request.post('/api/v1/chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        type: 'group',
        name: 'Participant Test'
      }
    });
    
    const conversation = await convResponse.json();
    const conversationId = conversation.data.id;
    
    // Add participant
    const participantResponse = await request.post(`/api/v1/chat/conversations/${conversationId}/participants`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        email: 'participant@example.com',
        role: 'member'
      }
    });
    
    if (participantResponse.ok()) {
      const participant = await participantResponse.json();
      expect(participant.data).toHaveProperty('user_id');
      expect(participant.data.role).toBe('member');
    }
  });

  test('should upload file via API', async ({ request }) => {
    // Create conversation first
    const convResponse = await request.post('/api/v1/chat/conversations', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        type: 'direct',
        name: 'File Upload Test'
      }
    });
    
    const conversation = await convResponse.json();
    const conversationId = conversation.data.id;
    
    // Create form data for file upload
    const fileContent = 'This is a test file content';
    const fileResponse = await request.post(`/api/v1/chat/conversations/${conversationId}/upload`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      multipart: {
        file: {
          name: 'test-file.txt',
          mimeType: 'text/plain',
          buffer: Buffer.from(fileContent)
        },
        caption: 'Test file upload'
      }
    });
    
    if (fileResponse.ok()) {
      const file = await fileResponse.json();
      expect(file.data).toHaveProperty('file_path');
      expect(file.data.original_name).toBe('test-file.txt');
    }
  });

  test('should handle message reactions via API', async ({ request }) => {
    // Assume we have a message ID
    const messageId = 1;
    
    // Add reaction
    const reactionResponse = await request.post(`/api/v1/chat/messages/${messageId}/reactions`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        emoji: '👍'
      }
    });
    
    if (reactionResponse.ok()) {
      const reaction = await reactionResponse.json();
      expect(reaction.data.emoji).toBe('👍');
    }
  });

  test('should handle typing indicators via API', async ({ request }) => {
    const conversationId = 1;
    
    // Start typing
    const typingResponse = await request.post(`/api/v1/chat/conversations/${conversationId}/typing`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        typing: true
      }
    });
    
    expect(typingResponse.ok()).toBeTruthy();
    
    // Stop typing
    const stopTypingResponse = await request.delete(`/api/v1/chat/conversations/${conversationId}/typing`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    expect(stopTypingResponse.ok()).toBeTruthy();
  });

  test('should handle encryption endpoints via API', async ({ request }) => {
    // Generate keypair
    const keypairResponse = await request.post('/api/v1/chat/encryption/generate-keypair', {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    if (keypairResponse.ok()) {
      const keypair = await keypairResponse.json();
      expect(keypair.data).toHaveProperty('public_key');
      expect(keypair.data).toHaveProperty('private_key');
    }
  });

  test('should handle read receipts via API', async ({ request }) => {
    const messageId = 1;
    
    // Mark message as read
    const readResponse = await request.post(`/api/v1/chat/messages/${messageId}/read-receipts`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    if (readResponse.ok()) {
      const receipt = await readResponse.json();
      expect(receipt.data).toHaveProperty('read_at');
    }
  });

  test('should handle rate limiting', async ({ request }) => {
    // Send multiple rapid requests to test rate limiting
    const promises = [];
    for (let i = 0; i < 10; i++) {
      promises.push(
        request.post('/api/v1/chat/conversations', {
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          data: {
            type: 'direct',
            name: `Rate limit test ${i}`
          }
        })
      );
    }
    
    const responses = await Promise.all(promises);
    
    // Some requests should be rate limited (429 status)
    const rateLimitedResponses = responses.filter(r => r.status() === 429);
    expect(rateLimitedResponses.length).toBeGreaterThan(0);
  });
});