import { test, expect } from '@playwright/test';

test.describe('Simple Chat Navigation Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to login page
    await page.goto('/login');

    // Login with test user
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // Wait for redirect to dashboard
    await page.waitForURL('/dashboard');
  });

  test('should navigate to chat page', async ({ page }) => {
    await page.goto('/chat');
    await expect(page).toHaveURL('/chat');

    // Wait for page to load
    await page.waitForTimeout(2000);

    // Check basic page structure
    await expect(page.locator('body')).toBeVisible();
  });

  test('should display basic chat layout', async ({ page }) => {
    await page.goto('/chat');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check if main layout exists
    const chatLayout = page.locator('[data-testid="chat-layout"]');
    await expect(chatLayout).toBeVisible({ timeout: 20000 });
  });

  test('should show conversation list area', async ({ page }) => {
    await page.goto('/chat');

    // Wait for page to load
    await page.waitForTimeout(3000);

    // Check if conversation list exists
    const conversationList = page.locator('[data-testid="conversation-list"]');
    await expect(conversationList).toBeVisible({ timeout: 20000 });
  });
});
