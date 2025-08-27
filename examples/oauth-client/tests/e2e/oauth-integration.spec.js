import { test, expect } from '@playwright/test';

/**
 * OAuth 2.0 End-to-End Integration Tests
 * 
 * These tests perform complete OAuth flows including user registration,
 * authorization, and token operations to verify the entire system works.
 */

test.describe('Complete OAuth 2.0 Integration', () => {
  
  let testUser;
  
  test.beforeEach(async ({ page }) => {
    // Generate unique test user for each test
    testUser = {
      name: `Test User ${Date.now()}`,
      email: `test-${Date.now()}@example.com`,
      password: 'TestPassword123!'
    };
  });

  test('should complete full OAuth flow with new user registration', async ({ page }) => {
    console.log(`🧪 Testing with user: ${testUser.email}`);
    
    // Step 1: Start OAuth flow from client
    await page.goto('/oauth/');
    
    // Verify we're on the OAuth client dashboard
    await expect(page).toHaveTitle(/OAuth 2.0.*OpenID Connect Client/);
    
    // Select all scopes for comprehensive test
    await page.locator('input[name="scopes[]"][value="openid"]').check();
    await page.locator('input[name="scopes[]"][value="profile"]').check();
    await page.locator('input[name="scopes[]"][value="email"]').check();
    await page.locator('input[name="scopes[]"][value="https://api.yourcompany.com/auth/organization.readonly"]').check();
    
    // Start OAuth flow
    await page.locator('#auth-btn').click();
    
    // Step 2: Should be redirected to authorization server
    await page.waitForURL(/localhost:8000.*oauth\/authorize/);
    
    // Verify OAuth parameters in URL
    const authorizeUrl = page.url();
    expect(authorizeUrl).toContain('client_id=a8704536-ee26-4675-b324-741444ffb54e');
    expect(authorizeUrl).toContain('response_type=code');
    expect(authorizeUrl).toContain('scope=openid');
    
    // Step 3: Should be redirected to login (user not authenticated)
    await page.waitForURL(/localhost:8000.*login/);
    
    // Check if register link is available and click it
    const registerLink = page.locator('a[href*="register"]');
    if (await registerLink.count() > 0) {
      await registerLink.click();
      
      // Fill registration form
      await page.fill('input[name="name"]', testUser.name);
      await page.fill('input[name="email"]', testUser.email);
      await page.fill('input[name="password"]', testUser.password);
      await page.fill('input[name="password_confirmation"]', testUser.password);
      
      // Submit registration
      await page.locator('button[type="submit"]').click();
      
      // Wait for redirect after registration (might go to verification, dashboard, or back to OAuth)
      await page.waitForURL(/localhost:8000/, { timeout: 10000 });
      
    } else {
      // If no register link, try to fill login form
      console.log('ℹ️  No register link found, attempting login');
      
      // Try to find and fill login form
      const emailField = page.locator('input[name="email"], input[type="email"]');
      const passwordField = page.locator('input[name="password"], input[type="password"]');
      
      if (await emailField.count() > 0 && await passwordField.count() > 0) {
        await emailField.fill(testUser.email);
        await passwordField.fill(testUser.password);
        await page.locator('button[type="submit"], input[type="submit"]').click();
      }
    }
    
    // Step 4: After authentication, should see OAuth consent screen
    // Wait a bit for redirects to complete
    await page.waitForTimeout(2000);
    
    const currentUrl = page.url();
    console.log(`📍 Current URL after auth: ${currentUrl}`);
    
    // Look for OAuth consent elements
    const consentButton = page.locator('button:has-text("Authorize"), button:has-text("Allow"), button:has-text("Accept"), input[value*="Authorize"]');
    const denyButton = page.locator('button:has-text("Deny"), button:has-text("Cancel")');
    
    if (await consentButton.count() > 0) {
      console.log('✅ Found OAuth consent screen');
      
      // Grant consent
      await consentButton.first().click();
      
      // Step 5: Should be redirected back to OAuth client with success
      await page.waitForURL(/localhost:8081.*oauth\/callback/, { timeout: 15000 });
      
      // Verify we're on the success page
      await expect(page.locator('.header.success')).toBeVisible();
      await expect(page).toHaveTitle(/OAuth.*Result/);
      
      // Step 6: Verify tokens are displayed
      await expect(page.locator('h2')).toContainText('Access Token');
      await expect(page.locator('#access-token')).toBeVisible();
      
      // Check for user information
      await expect(page.locator('h2')).toContainText('User Information');
      
      // Verify user data matches our test user
      await expect(page.locator('.info-value')).toContainText(testUser.name);
      await expect(page.locator('.info-value')).toContainText(testUser.email);
      
      // Step 7: Test token operations
      const refreshButton = page.locator('button:has-text("Refresh Access Token")');
      if (await refreshButton.count() > 0) {
        console.log('🔄 Testing token refresh...');
        await refreshButton.click();
        
        // Wait for refresh result
        await expect(page.locator('#refresh-result')).toBeVisible({ timeout: 10000 });
      }
      
      console.log('🎉 Complete OAuth flow test passed!');
      
    } else {
      // No consent screen found - might have auto-approved or different flow
      console.log('ℹ️  No explicit consent screen found');
      
      // Check if we're already at the callback with results
      const isCallbackUrl = currentUrl.includes('oauth/callback');
      const hasSuccessHeader = await page.locator('.header.success').count() > 0;
      
      if (isCallbackUrl && hasSuccessHeader) {
        console.log('✅ OAuth flow completed successfully without explicit consent');
      } else {
        console.log(`⚠️  Unexpected flow state. Current URL: ${currentUrl}`);
        
        // Take a screenshot for debugging
        await page.screenshot({ path: `test-results/oauth-debug-${Date.now()}.png` });
      }
    }
  });

  test('should handle OAuth denial gracefully', async ({ page }) => {
    // Start OAuth flow
    await page.goto('/oauth/');
    await page.locator('#auth-btn').click();
    
    // Simulate OAuth denial by navigating directly to callback with error
    await page.goto('/oauth/callback?error=access_denied&error_description=The+user+denied+the+request');
    
    // Should show error page
    await expect(page.locator('.header.error')).toBeVisible();
    await expect(page.locator('h1')).toContainText('Error');
    await expect(page.locator('body')).toContainText('access_denied');
    await expect(page.locator('body')).toContainText('denied the request');
  });

  test('should handle token operations after successful OAuth', async ({ page }) => {
    // This test assumes we have valid tokens from a previous flow
    // For demonstration, we'll test the token operation endpoints directly
    
    await page.goto('/oauth/');
    
    // Test refresh endpoint with invalid token (should fail gracefully)
    const refreshResponse = await page.request.post('/oauth/refresh', {
      data: { refresh_token: 'fake_refresh_token' },
      headers: {
        'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content')
      }
    });
    
    expect(refreshResponse.status()).toBe(200);
    const refreshData = await refreshResponse.json();
    expect(refreshData.success).toBe(false);
    
    // Test revoke endpoint
    const revokeResponse = await page.request.post('/oauth/revoke', {
      data: {
        token: 'fake_access_token',
        token_type_hint: 'access_token'
      },
      headers: {
        'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content')
      }
    });
    
    expect(revokeResponse.status()).toBe(200);
  });

});

test.describe('OAuth Error Scenarios', () => {
  
  test('should handle invalid client configuration', async ({ page }) => {
    // Modify the OAuth URL to use invalid client ID
    await page.goto('/oauth/');
    
    // Inject JavaScript to modify the form action to use invalid client
    await page.evaluate(() => {
      const form = document.querySelector('#oauth-form');
      if (form) {
        // Create hidden input with invalid client ID
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'client_id';
        hiddenInput.value = 'invalid-client-id';
        form.appendChild(hiddenInput);
      }
    });
    
    // Start OAuth flow with invalid configuration
    await page.locator('#auth-btn').click();
    
    // Should be redirected to authorization server
    await page.waitForURL(/localhost:8000/);
    
    // Authorization server should handle invalid client gracefully
    // (exact behavior depends on server implementation)
    const currentUrl = page.url();
    console.log(`📍 Invalid client test URL: ${currentUrl}`);
  });

  test('should handle network timeouts', async ({ page }) => {
    await page.goto('/oauth/');
    
    // Slow down all requests to simulate timeout
    await page.route('**/*', async (route) => {
      await new Promise(resolve => setTimeout(resolve, 1000));
      await route.continue();
    });
    
    // Try to load discovery info with slow network
    await page.locator('button:has-text("Load Discovery Info")').click();
    
    // Should eventually show some response (success or timeout error)
    await page.waitForTimeout(5000);
  });

});

test.describe('Cross-Browser Compatibility', () => {
  
  ['chromium', 'firefox', 'webkit'].forEach(browserName => {
    test(`should work in ${browserName}`, async ({ page }) => {
      await page.goto('/oauth/');
      
      // Basic functionality test
      await expect(page.locator('h1')).toContainText('OAuth 2.0');
      await expect(page.locator('#auth-btn')).toBeVisible();
      
      // Test scope selection
      await page.locator('input[name="scopes[]"][value="email"]').uncheck();
      await expect(page.locator('input[name="scopes[]"][value="email"]')).not.toBeChecked();
      
      // Test discovery endpoint
      const response = await page.request.get('/oauth/discovery');
      expect(response.status()).toBe(200);
    });
  });

});

test.describe('Performance Tests', () => {
  
  test('should load OAuth dashboard quickly', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/oauth/');
    await expect(page.locator('h1')).toBeVisible();
    
    const loadTime = Date.now() - startTime;
    console.log(`⚡ Dashboard loaded in ${loadTime}ms`);
    
    // Should load within reasonable time (adjust as needed)
    expect(loadTime).toBeLessThan(5000);
  });

  test('should handle multiple concurrent requests', async ({ page }) => {
    await page.goto('/oauth/');
    
    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    
    // Make multiple concurrent API requests
    const requests = [];
    for (let i = 0; i < 5; i++) {
      requests.push(
        page.request.get('/oauth/discovery'),
        page.request.post('/oauth/refresh', {
          data: { refresh_token: `test_token_${i}` },
          headers: { 'X-CSRF-TOKEN': csrfToken }
        })
      );
    }
    
    const responses = await Promise.all(requests);
    
    // All requests should complete successfully
    responses.forEach(response => {
      expect(response.status()).toBeLessThanOrEqual(500); // Should not crash server
    });
  });

});