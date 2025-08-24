import { test, expect } from '@playwright/test';

test.describe('Chat Voice Features', () => {
  test.beforeEach(async ({ page }) => {
    // Grant microphone permissions
    await page.context().grantPermissions(['microphone']);
    
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should display voice recorder button', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Check for voice recorder button
    await expect(page.locator('[data-testid="voice-recorder"]')).toBeVisible();
  });

  test('should start and stop voice recording', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Start recording
    await page.click('[data-testid="start-recording"]');
    
    // Verify recording indicator is visible
    await expect(page.locator('[data-testid="recording-indicator"]')).toBeVisible();
    await expect(page.locator('[data-testid="recording-timer"]')).toBeVisible();
    
    // Wait a bit to simulate recording
    await page.waitForTimeout(3000);
    
    // Stop recording
    await page.click('[data-testid="stop-recording"]');
    
    // Verify recording preview appears
    await expect(page.locator('[data-testid="voice-preview"]')).toBeVisible();
  });

  test('should play voice message preview', async ({ page }) => {
    // Assume we have a recorded voice message preview
    await page.goto('/chat');
    
    // Start and stop recording (simplified)
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    await page.click('[data-testid="start-recording"]');
    await page.waitForTimeout(2000);
    await page.click('[data-testid="stop-recording"]');
    
    // Play preview
    await page.click('[data-testid="play-preview"]');
    
    // Verify playback controls
    await expect(page.locator('[data-testid="pause-preview"]')).toBeVisible();
    await expect(page.locator('[data-testid="playback-progress"]')).toBeVisible();
  });

  test('should send voice message', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Record voice message
    await page.click('[data-testid="start-recording"]');
    await page.waitForTimeout(3000);
    await page.click('[data-testid="stop-recording"]');
    
    // Send voice message
    await page.click('[data-testid="send-voice-message"]');
    
    // Verify voice message appears in chat
    const voiceMessage = page.locator('[data-testid="voice-message"]').last();
    await expect(voiceMessage).toBeVisible();
    await expect(voiceMessage.locator('[data-testid="voice-duration"]')).toBeVisible();
  });

  test('should cancel voice recording', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Start recording
    await page.click('[data-testid="start-recording"]');
    await expect(page.locator('[data-testid="recording-indicator"]')).toBeVisible();
    
    // Cancel recording
    await page.click('[data-testid="cancel-recording"]');
    
    // Verify recording is cancelled
    await expect(page.locator('[data-testid="recording-indicator"]')).not.toBeVisible();
    await expect(page.locator('[data-testid="voice-preview"]')).not.toBeVisible();
  });

  test('should play received voice messages', async ({ page }) => {
    // Assume we have received voice messages
    const voiceMessage = page.locator('[data-testid="voice-message"]').first();
    
    // Play voice message
    await voiceMessage.locator('[data-testid="play-voice"]').click();
    
    // Verify playback controls
    await expect(voiceMessage.locator('[data-testid="voice-playing"]')).toBeVisible();
    await expect(voiceMessage.locator('[data-testid="playback-progress"]')).toBeVisible();
    
    // Pause playback
    await voiceMessage.locator('[data-testid="pause-voice"]').click();
    await expect(voiceMessage.locator('[data-testid="voice-paused"]')).toBeVisible();
  });

  test('should handle voice message encryption', async ({ page }) => {
    // Select first conversation
    const firstConversation = page.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    // Verify encryption is enabled
    await expect(page.locator('[data-testid="encryption-active"]')).toBeVisible();
    
    // Record and send voice message
    await page.click('[data-testid="start-recording"]');
    await page.waitForTimeout(3000);
    await page.click('[data-testid="stop-recording"]');
    await page.click('[data-testid="send-voice-message"]');
    
    // Verify voice message has encryption indicator
    const voiceMessage = page.locator('[data-testid="voice-message"]').last();
    await expect(voiceMessage.locator('[data-testid="voice-encrypted"]')).toBeVisible();
  });

  test('should show voice recording permissions', async ({ page }) => {
    // Create a new page context without microphone permissions
    const newContext = await page.context().browser()?.newContext({
      permissions: []
    });
    const newPage = await newContext!.newPage();
    
    // Login
    await newPage.goto('/login');
    await newPage.fill('[name="email"]', 'test@example.com');
    await newPage.fill('[name="password"]', 'password');
    await newPage.click('button[type="submit"]');
    await newPage.waitForURL('/dashboard');
    await newPage.goto('/chat');
    
    // Try to start recording without permissions
    const firstConversation = newPage.locator('[data-testid="conversation-item"]').first();
    if (await firstConversation.isVisible()) {
      await firstConversation.click();
    }
    
    await newPage.click('[data-testid="start-recording"]');
    
    // Verify permission request dialog
    await expect(newPage.locator('[data-testid="microphone-permission-dialog"]')).toBeVisible();
    
    await newContext?.close();
  });

  test('should handle voice message download', async ({ page }) => {
    // Assume we have a voice message
    const voiceMessage = page.locator('[data-testid="voice-message"]').first();
    
    // Right-click for context menu
    await voiceMessage.click({ button: 'right' });
    
    // Set up download promise
    const downloadPromise = page.waitForEvent('download');
    
    // Download voice message
    await page.click('[data-testid="download-voice"]');
    
    // Verify download
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.(mp3|wav|ogg)$/);
  });

  test('should display voice message waveform', async ({ page }) => {
    // Assume we have a voice message
    const voiceMessage = page.locator('[data-testid="voice-message"]').first();
    
    // Verify waveform is displayed
    await expect(voiceMessage.locator('[data-testid="voice-waveform"]')).toBeVisible();
    
    // Click on waveform to seek
    const waveform = voiceMessage.locator('[data-testid="voice-waveform"]');
    const bbox = await waveform.boundingBox();
    if (bbox) {
      // Click at 50% position
      await page.mouse.click(bbox.x + bbox.width * 0.5, bbox.y + bbox.height * 0.5);
    }
    
    // Verify playback position changed
    await expect(voiceMessage.locator('[data-testid="playback-time"]')).not.toContainText('0:00');
  });
});