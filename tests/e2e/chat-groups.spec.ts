import { test, expect } from '@playwright/test';

test.describe('Chat Group Features', () => {
  test.beforeEach(async ({ page }) => {
    // Login as test user
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/chat');
  });

  test('should create a group conversation', async ({ page }) => {
    // Click on create group button
    await page.click('[data-testid="create-group"]');
    
    // Fill in group details
    await page.fill('[name="name"]', 'Test Group');
    await page.fill('[name="description"]', 'This is a test group chat');
    
    // Add participants
    await page.click('[data-testid="add-participant"]');
    await page.fill('[data-testid="user-search"]', 'user@example.com');
    await page.click('[data-testid="user-suggestion"]');
    
    // Create group
    await page.click('button[type="submit"]');
    
    // Verify group was created
    await expect(page.locator('[data-testid="conversation-item"]').first()).toContainText('Test Group');
  });

  test('should manage group participants', async ({ page }) => {
    // Assume we have a group conversation
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    await groupConversation.click();
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Add new participant
    await page.click('[data-testid="add-participant"]');
    await page.fill('[data-testid="user-search"]', 'newuser@example.com');
    await page.click('[data-testid="user-suggestion"]');
    await page.click('[data-testid="confirm-add"]');
    
    // Verify participant was added
    await expect(page.locator('[data-testid="participant-list"]')).toContainText('newuser@example.com');
  });

  test('should update participant roles', async ({ page }) => {
    // Select group conversation
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    await groupConversation.click();
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Change participant role
    const participantItem = page.locator('[data-testid="participant-item"]').first();
    await participantItem.locator('[data-testid="role-dropdown"]').click();
    await page.click('[data-testid="role-admin"]');
    
    // Confirm role change
    await page.click('[data-testid="confirm-role-change"]');
    
    // Verify role was updated
    await expect(participantItem).toContainText('Admin');
  });

  test('should generate and use invite link', async ({ page }) => {
    // Select group conversation
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    await groupConversation.click();
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Generate invite link
    await page.click('[data-testid="generate-invite-link"]');
    
    // Copy invite link
    const inviteLink = await page.locator('[data-testid="invite-link"]').textContent();
    expect(inviteLink).toContain('/chat/join/');
    
    // Test the invite link (open in new context)
    const inviteCode = inviteLink?.split('/').pop();
    await page.goto(`/chat/join/${inviteCode}`);
    
    // Verify join page is displayed
    await expect(page.locator('[data-testid="join-group-dialog"]')).toBeVisible();
  });

  test('should update group settings', async ({ page }) => {
    // Select group conversation
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    await groupConversation.click();
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Update group name
    await page.fill('[name="groupName"]', 'Updated Group Name');
    
    // Update description
    await page.fill('[name="description"]', 'Updated group description');
    
    // Update privacy settings
    await page.click('[data-testid="privacy-private"]');
    
    // Save changes
    await page.click('[data-testid="save-settings"]');
    
    // Verify changes were saved
    await expect(page.locator('[data-testid="group-name"]')).toContainText('Updated Group Name');
  });

  test('should leave group conversation', async ({ page }) => {
    // Select group conversation
    const groupConversation = page.locator('[data-testid="group-conversation"]').first();
    await groupConversation.click();
    
    // Open group settings
    await page.click('[data-testid="group-settings"]');
    
    // Leave group
    await page.click('[data-testid="leave-group"]');
    
    // Confirm leaving
    await page.click('[data-testid="confirm-leave"]');
    
    // Verify group is no longer in conversation list
    await expect(groupConversation).not.toBeVisible();
  });
});