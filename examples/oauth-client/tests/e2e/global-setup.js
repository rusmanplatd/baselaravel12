/**
 * Global setup for OAuth 2.0 tests
 * This runs once before all tests
 */

import { chromium } from '@playwright/test';

async function globalSetup() {
  console.log('🚀 Starting OAuth 2.0 Test Setup...');

  // Create a browser instance to verify servers are running
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  try {
    // Verify main server is running
    console.log('⏳ Checking main server (port 8000)...');
    await page.goto('http://localhost:8000', { timeout: 30000 });
    console.log('✅ Main server is running');

    // Verify OAuth client is running  
    console.log('⏳ Checking OAuth client server (port 8081)...');
    await page.goto('http://localhost:8081/oauth/', { timeout: 30000 });
    console.log('✅ OAuth client server is running');

    // Check if discovery endpoints are accessible
    console.log('⏳ Checking OAuth discovery endpoints...');
    const response = await page.request.get('http://localhost:8000/.well-known/oauth-authorization-server');
    if (response.ok()) {
      console.log('✅ OAuth discovery endpoint is accessible');
    } else {
      console.warn('⚠️ OAuth discovery endpoint returned:', response.status());
    }

    console.log('🎉 Global setup completed successfully!');

  } catch (error) {
    console.error('❌ Global setup failed:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

export default globalSetup;