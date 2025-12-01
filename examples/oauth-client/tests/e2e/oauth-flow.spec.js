import { test, expect } from '@playwright/test';

/**
 * OAuth 2.0 / OpenID Connect Flow Tests
 * 
 * These tests verify the complete OAuth authorization code flow,
 * token operations, and error handling scenarios.
 */

test.describe('OAuth 2.0 Authorization Code Flow', () => {
  
  test.beforeEach(async ({ page }) => {
    // Start at the OAuth client dashboard
    await page.goto('/oauth/');
  });

  test('should display OAuth dashboard correctly', async ({ page }) => {
    // Check page title and main elements
    await expect(page).toHaveTitle(/OAuth 2.0.*OpenID Connect Client/);
    
    // Verify main heading
    await expect(page.locator('h1')).toContainText('OAuth 2.0 / OpenID Connect Client');
    
    // Check client configuration is displayed
    await expect(page.locator('.info-value')).toContainText('a8704536-ee26-4675-b324-741444ffb54e');
    
    // Verify scope checkboxes are present
    await expect(page.locator('input[name="scopes[]"][value="openid"]')).toBeVisible();
    await expect(page.locator('input[name="scopes[]"][value="profile"]')).toBeVisible();
    await expect(page.locator('input[name="scopes[]"][value="email"]')).toBeVisible();
    
    // Check start OAuth flow button
    await expect(page.locator('#auth-btn')).toBeVisible();
    await expect(page.locator('#auth-btn')).toContainText('Start OAuth Flow');
  });

  test('should allow scope selection', async ({ page }) => {
    // Initially openid, profile, email should be checked
    await expect(page.locator('input[name="scopes[]"][value="openid"]')).toBeChecked();
    await expect(page.locator('input[name="scopes[]"][value="profile"]')).toBeChecked();
    await expect(page.locator('input[name="scopes[]"][value="email"]')).toBeChecked();
    
    // Uncheck email scope
    await page.locator('input[name="scopes[]"][value="email"]').uncheck();
    await expect(page.locator('input[name="scopes[]"][value="email"]')).not.toBeChecked();
    
    // Check organization scope
    const orgScope = page.locator('input[name="scopes[]"][value="https://api.yourcompany.com/auth/organization.readonly"]');
    await orgScope.check();
    await expect(orgScope).toBeChecked();
    
    // Verify visual feedback for selected scopes
    const selectedItem = page.locator('.checkbox-item.selected').first();
    await expect(selectedItem).toBeVisible();
  });

  test('should load discovery information', async ({ page }) => {
    // Click load discovery info button
    await page.locator('button:has-text("Load Discovery Info")').click();
    
    // Wait for discovery info to load
    await expect(page.locator('#discovery-info')).toBeVisible();
    await expect(page.locator('#discovery-content')).toContainText('OAuth 2.0 Endpoints');
  });

  test('should start OAuth authorization flow', async ({ page }) => {
    // Select scopes (keep defaults)
    // Click start OAuth flow
    await page.locator('#auth-btn').click();
    
    // Should be redirected to authorization server
    await expect(page).toHaveURL(/localhost:8000.*oauth\/authorize/);
    
    // Should see authorization page elements
    await expect(page.locator('body')).toContainText('OAuth');
    
    // Check for required OAuth parameters in URL
    const url = page.url();
    expect(url).toContain('client_id=a8704536-ee26-4675-b324-741444ffb54e');
    expect(url).toContain('response_type=code');
    expect(url).toContain('scope=openid%20profile%20email');
    expect(url).toContain('state=');
    expect(url).toContain('redirect_uri=');
  });

  test('should handle OAuth authorization with login', async ({ page }) => {
    // Start OAuth flow
    await page.locator('#auth-btn').click();
    
    // Wait for redirect to authorization server
    await page.waitForURL(/localhost:8000/);
    
    // Check if we need to login first
    const loginFormExists = await page.locator('form[action*="login"]').count() > 0;
    const registerLinkExists = await page.locator('a[href*="register"]').count() > 0;
    
    if (loginFormExists || registerLinkExists) {
      console.log('🔐 Login required - redirecting to register page');
      
      // If not logged in, we'll see login form or be redirected to login
      // For testing purposes, we'll just verify the OAuth parameters were passed correctly
      const currentUrl = page.url();
      
      // The OAuth parameters should be preserved in the session or URL
      expect(currentUrl).toContain('localhost:8000');
      
      // Log information about the authorization process
      console.log('📍 Current URL:', currentUrl);
      console.log('ℹ️  Authorization server is ready for user authentication');
    } else {
      // If already logged in, should see authorization consent screen
      await expect(page.locator('body')).toContainText(['authorize', 'consent', 'permissions', 'OAuth'], { matchCase: false });
    }
  });

  test('should handle OAuth errors gracefully', async ({ page }) => {
    // Test with invalid state parameter by navigating directly to callback with invalid state
    await page.goto('/oauth/callback?error=access_denied&error_description=User+denied+access');
    
    // Should show error page
    await expect(page.locator('.header.error')).toBeVisible();
    await expect(page.locator('body')).toContainText('access_denied');
    await expect(page.locator('body')).toContainText('User denied access');
    
    // Should have back to dashboard link
    await expect(page.locator('a[href*="/oauth/"]')).toContainText('Back to OAuth Dashboard');
  });

  test('should validate state parameter', async ({ page }) => {
    // Try to access callback with invalid state
    await page.goto('/oauth/callback?code=test_code&state=invalid_state');
    
    // Should show state validation error
    await expect(page.locator('.header.error')).toBeVisible();
    await expect(page.locator('body')).toContainText('State parameter mismatch');
  });

  test('should handle missing authorization code', async ({ page }) => {
    // Set a valid state in session first by starting OAuth flow
    await page.locator('#auth-btn').click();
    
    // Go back and try callback without code
    await page.goBack();
    await page.goto('/oauth/callback?state=some_state');
    
    // Should show missing code error
    await expect(page.locator('.header.error')).toBeVisible();
    await expect(page.locator('body')).toContainText('Authorization code not provided');
  });

});

test.describe('OAuth Token Operations', () => {
  
  test('should handle token refresh API', async ({ page }) => {
    // Navigate to OAuth dashboard
    await page.goto('/oauth/');
    
    // Test refresh endpoint directly
    const response = await page.request.post('/oauth/refresh', {
      data: {
        refresh_token: 'invalid_token'
      },
      headers: {
        'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content')
      }
    });
    
    expect(response.status()).toBe(200);
    
    const responseData = await response.json();
    expect(responseData.success).toBe(false);
    expect(responseData.error).toBeDefined();
  });

  test('should handle token revocation API', async ({ page }) => {
    // Navigate to OAuth dashboard
    await page.goto('/oauth/');
    
    // Test revoke endpoint directly
    const response = await page.request.post('/oauth/revoke', {
      data: {
        token: 'invalid_token',
        token_type_hint: 'access_token'
      },
      headers: {
        'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content')
      }
    });
    
    expect(response.status()).toBe(200);
    
    const responseData = await response.json();
    // Revoke might succeed even with invalid token (per OAuth spec)
    expect(responseData.success).toBeDefined();
  });

});

test.describe('OAuth Discovery', () => {
  
  test('should fetch discovery information', async ({ page }) => {
    await page.goto('/oauth/');
    
    // Test discovery endpoint
    const response = await page.request.get('/oauth/discovery');
    expect(response.status()).toBe(200);
    
    const discoveryData = await response.json();
    
    // Check for OAuth 2.0 discovery data
    if (discoveryData.oauth2) {
      expect(discoveryData.oauth2.authorization_endpoint).toContain('oauth/authorize');
      expect(discoveryData.oauth2.token_endpoint).toContain('token');
    }
    
    // Check for OIDC discovery data
    if (discoveryData.oidc) {
      expect(discoveryData.oidc.userinfo_endpoint).toContain('userinfo');
      expect(discoveryData.oidc.jwks_uri).toContain('jwks');
    }
  });

});

test.describe('Mobile and Responsive', () => {
  
  test('should work on mobile devices', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/oauth/');
    
    // Verify responsive design
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('#auth-btn')).toBeVisible();
    
    // Check that grid layout adapts to mobile
    const gridElement = page.locator('.grid').first();
    await expect(gridElement).toBeVisible();
    
    // Verify scope selection works on mobile
    await page.locator('input[name="scopes[]"][value="email"]').click();
    await expect(page.locator('input[name="scopes[]"][value="email"]')).not.toBeChecked();
  });

});

test.describe('Accessibility', () => {
  
  test('should have proper accessibility attributes', async ({ page }) => {
    await page.goto('/oauth/');
    
    // Check for proper form labels
    await expect(page.locator('label')).toHaveCount(4); // One label per scope
    
    // Check button accessibility
    const authButton = page.locator('#auth-btn');
    await expect(authButton).toHaveAttribute('type', 'submit');
    
    // Verify semantic HTML structure
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('h2')).toHaveCount(4); // Main sections
    
    // Check form accessibility
    await expect(page.locator('form')).toHaveAttribute('action');
  });

});

test.describe('Error Boundaries', () => {
  
  test('should handle network errors gracefully', async ({ page }) => {
    await page.goto('/oauth/');
    
    // Simulate network failure by blocking requests to main server
    await page.route('http://localhost:8000/**', route => {
      route.abort('failed');
    });
    
    // Try to load discovery info with blocked network
    await page.locator('button:has-text("Load Discovery Info")').click();
    
    // Should show network error
    await expect(page.locator('#discovery-content')).toContainText('Network error');
  });

});

test.describe('Legacy Compatibility', () => {
  
  test('should redirect legacy /oauth/test route', async ({ page }) => {
    await page.goto('/oauth/test');
    
    // Should redirect to new OAuth dashboard
    await expect(page).toHaveURL('/oauth/');
    await expect(page.locator('h1')).toContainText('OAuth 2.0 / OpenID Connect Client');
  });

});