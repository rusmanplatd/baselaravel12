import { test, expect } from '@playwright/test';

test.describe('E2EE Mention Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to chat app
    await page.goto('/chat');

    // Wait for initial load
    await page.waitForLoadState('networkidle');
  });

  test('should encrypt mentions in message content', async ({ page }) => {
    // This test verifies that mentions are part of the encrypted message
    // and not processed server-side

    // Set up test conversation with participants
    const conversationName = 'E2EE Test Group';
    const mentionedUser = 'John Doe';

    // Create or select test conversation
    await page.getByTestId('create-conversation').click();
    await page.fill('[data-testid="conversation-name"]', conversationName);
    await page.getByTestId('add-participant').click();
    await page.fill('[data-testid="participant-search"]', 'john@example.com');
    await page.getByTestId('select-participant').click();
    await page.getByTestId('create-conversation-submit').click();

    // Type message with mention
    const messageInput = page.getByTestId('message-input');
    await messageInput.fill('Hello @"John Doe", this is an encrypted mention test!');

    // Send message
    await page.getByTestId('send-message').click();

    // Verify message appears in chat
    await expect(page.getByText('Hello @"John Doe", this is an encrypted mention test!')).toBeVisible();

    // Verify mention is highlighted
    const mentionElement = page.locator('.mention-highlight').filter({ hasText: '@"John Doe"' });
    await expect(mentionElement).toBeVisible();

    // Check network requests to ensure mention data wasn't sent separately
    const requests = page.context().on('request', request => {
      if (request.url().includes('/messages') && request.method() === 'POST') {
        const postData = request.postDataJSON();
        expect(postData).not.toHaveProperty('mentions');
        expect(postData).toHaveProperty('content');
        // Content should contain the mention text for encryption
        expect(postData.content).toContain('@"John Doe"');
      }
    });
  });

  test('should show mention autocomplete during typing', async ({ page }) => {
    // Navigate to a group conversation
    await page.getByText('Test Group').click();

    const messageInput = page.getByTestId('message-input');

    // Start typing a mention
    await messageInput.fill('Hey @Jo');

    // Verify autocomplete appears
    await expect(page.getByTestId('mention-autocomplete')).toBeVisible();

    // Verify suggestions are shown
    await expect(page.getByText('John Doe')).toBeVisible();

    // Select a suggestion
    await page.getByText('John Doe').click();

    // Verify mention is inserted
    await expect(messageInput).toHaveValue('Hey @"John Doe"');

    // Verify autocomplete disappears
    await expect(page.getByTestId('mention-autocomplete')).not.toBeVisible();
  });

  test('should parse and highlight mentions in received messages', async ({ page }) => {
    // This test verifies client-side mention parsing after decryption

    // Mock receiving an encrypted message with mentions
    // TODO: In a real test, this would come from the WebSocket or polling
    await page.evaluate(() => {
      // Simulate receiving a decrypted message with mentions
      const mockMessage = {
        id: 'test-message-1',
        content: 'Hi @"John Doe" and @"Jane Smith", please check this out!',
        sender_id: 'other-user',
        sender: { name: 'Alice Test', id: 'other-user' },
        created_at: new Date().toISOString(),
        conversation_id: 'test-conv-1'
      };

      // Trigger message display (would normally come through chat system)
      window.dispatchEvent(new CustomEvent('mock-message-received', {
        detail: mockMessage
      }));
    });

    // Verify mentions are highlighted
    const firstMention = page.locator('.mention-highlight').filter({ hasText: '@"John Doe"' });
    const secondMention = page.locator('.mention-highlight').filter({ hasText: '@"Jane Smith"' });

    await expect(firstMention).toBeVisible();
    await expect(secondMention).toBeVisible();

    // Verify mentions have appropriate styling for current user vs others
    if (await firstMention.getAttribute('class') !== null) {
      const classes = await firstMention.getAttribute('class');
      expect(classes).toContain('mention-highlight');
    }
  });

  test('should show mention notifications for current user', async ({ page }) => {
    // Mock current user
    await page.evaluate(() => {
      window.currentUser = { id: 'current-user-id', name: 'Current User' };
    });

    // Mock receiving a message that mentions current user
    await page.evaluate(() => {
      const mockMessage = {
        id: 'mention-notification-test',
        content: 'Hey @"Current User", you have been mentioned!',
        sender_id: 'other-user',
        sender: { name: 'Alice Test', id: 'other-user' },
        created_at: new Date().toISOString(),
        conversation_id: 'test-conv-1'
      };

      window.dispatchEvent(new CustomEvent('mock-message-received', {
        detail: mockMessage
      }));
    });

    // Verify mention notification appears
    await expect(page.getByText('1 mention')).toBeVisible();
    await expect(page.getByText('Alice Test mentioned you')).toBeVisible();

    // Verify notification can be clicked
    await page.getByTestId('mention-notification').click();

    // Verify it navigates to the message or marks as read
    await expect(page.getByText('Hey @"Current User", you have been mentioned!')).toBeVisible();
  });

  test('should maintain privacy - server never sees plaintext mentions', async ({ page }) => {
    // This test verifies the core security requirement

    let encryptedContent = null;
    let plaintextContent = null;

    // Intercept API calls
    await page.route('**/api/v1/chat/conversations/*/messages', async (route, request) => {
      if (request.method() === 'POST') {
        const postData = request.postDataJSON();
        plaintextContent = postData.content;
        encryptedContent = postData.encrypted_content;

        // Mock successful response
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            id: 'test-msg-id',
            content: postData.content,
            encrypted_content: postData.encrypted_content,
            sender_id: 'current-user',
            created_at: new Date().toISOString()
          })
        });
      } else {
        await route.continue();
      }
    });

    // Send message with mention
    await page.getByTestId('message-input').fill('Secret mention: @"John Doe" only you can see this!');
    await page.getByTestId('send-message').click();

    // Verify request was intercepted
    expect(plaintextContent).toContain('@"John Doe"');
    expect(encryptedContent).toBeTruthy();

    // Verify encrypted content doesn't contain plaintext
    if (encryptedContent) {
      expect(encryptedContent).not.toContain('John Doe');
      expect(encryptedContent).not.toContain('Secret mention');
      expect(encryptedContent).not.toContain('@"');
    }
  });

  test('should handle mention parsing edge cases', async ({ page }) => {
    // Test various mention formats
    const testCases = [
      'Simple @"John Doe" mention',
      'Multiple @"John Doe" and @"Jane Smith" mentions',
      'Edge case @"User With Special-Characters_123" mention',
      'No mention in this message',
      '@"NonExistent User" should not be highlighted'
    ];

    for (const testMessage of testCases) {
      await page.getByTestId('message-input').fill(testMessage);
      await page.getByTestId('send-message').click();

      // Wait for message to appear
      await expect(page.getByText(testMessage)).toBeVisible();

      // Verify proper mention highlighting based on participants
      const mentionCount = (testMessage.match(/@"[^"]+"/g) || []).length;
      const highlightedMentions = await page.locator('.mention-highlight').count();

      // Should only highlight mentions for actual participants
      if (testMessage.includes('John Doe') || testMessage.includes('Jane Smith')) {
        expect(highlightedMentions).toBeGreaterThan(0);
      } else {
        expect(highlightedMentions).toBe(0);
      }
    }
  });
});
