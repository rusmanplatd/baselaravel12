import { Page } from '@playwright/test';

export async function loginAsTestUser(page: Page, email = 'test@example.com', password = 'password') {
  await page.goto('/login');
  await page.fill('[name="email"]', email);
  await page.fill('[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL('/dashboard');
}

export async function createTestUser(page: Page, userData = {
  name: 'Test User',
  email: 'test@example.com',
  password: 'password',
  password_confirmation: 'password'
}) {
  await page.goto('/register');
  await page.fill('[name="name"]', userData.name);
  await page.fill('[name="email"]', userData.email);
  await page.fill('[name="password"]', userData.password);
  await page.fill('[name="password_confirmation"]', userData.password_confirmation);
  await page.click('button[type="submit"]');
}

export async function logout(page: Page) {
  await page.click('[data-testid="user-menu"]');
  await page.click('[data-testid="logout"]');
  await page.waitForURL('/login');
}

export async function ensureLoggedIn(page: Page) {
  // Check if already logged in
  try {
    await page.waitForURL('/dashboard', { timeout: 2000 });
  } catch {
    // Not logged in, perform login
    await loginAsTestUser(page);
  }
}
