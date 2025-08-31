import { test, expect, Page } from '@playwright/test';

test.describe('Quantum Admin UI Automated Tests', () => {
  let page: Page;

  test.beforeEach(async ({ browser }) => {
    page = await browser.newPage();
    
    // Login as admin user
    await page.goto('/login');
    await page.fill('[name="email"]', 'admin@example.com');
    await page.fill('[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    await expect(page).toHaveURL('/dashboard');
    
    // Navigate to quantum admin panel
    await page.goto('/admin/quantum');
    await expect(page.locator('.quantum-admin-panel')).toBeVisible();
  });

  test('quantum admin dashboard overview functionality', async () => {
    // Test main dashboard components
    await expect(page.locator('[data-testid="quantum-admin-panel"]')).toBeVisible();
    
    // Verify tab navigation
    const tabs = ['overview', 'migration', 'devices', 'analytics', 'settings'];
    
    for (const tab of tabs) {
      await page.click(`[data-testid="tab-${tab}"]`);
      await expect(page.locator(`[data-testid="tab-content-${tab}"]`)).toBeVisible();
    }
    
    // Return to overview tab
    await page.click('[data-testid="tab-overview"]');
    
    // Verify system status cards
    await expect(page.locator('[data-testid="quantum-status-card"]')).toBeVisible();
    await expect(page.locator('[data-testid="ready-devices-card"]')).toBeVisible();
    await expect(page.locator('[data-testid="conversations-card"]')).toBeVisible();
    await expect(page.locator('[data-testid="risk-level-card"]')).toBeVisible();
    
    // Test refresh functionality
    await page.click('[data-testid="refresh-button"]');
    await expect(page.locator('.animate-spin')).toBeVisible();
    await expect(page.locator('.animate-spin')).not.toBeVisible({ timeout: 5000 });
  });

  test('quantum health indicator component', async () => {
    await page.click('[data-testid="tab-overview"]');
    
    // Verify health indicator is present
    const healthIndicator = page.locator('[data-testid="quantum-health-indicator"]');
    await expect(healthIndicator).toBeVisible();
    
    // Check health status display
    const healthStatus = page.locator('[data-testid="health-status"]');
    await expect(healthStatus).toBeVisible();
    
    const statusText = await healthStatus.textContent();
    expect(statusText).toMatch(/Healthy|Warning|Critical/);
    
    // Test details toggle
    const showDetailsButton = page.locator('[data-testid="show-health-details"]');
    if (await showDetailsButton.isVisible()) {
      await showDetailsButton.click();
      await expect(page.locator('[data-testid="health-details"]')).toBeVisible();
      
      // Verify detailed metrics
      await expect(page.locator('[data-testid="quantum-support-details"]')).toBeVisible();
      await expect(page.locator('[data-testid="device-readiness-details"]')).toBeVisible();
      await expect(page.locator('[data-testid="system-performance-details"]')).toBeVisible();
    }
    
    // Test auto-refresh toggle
    const autoRefreshToggle = page.locator('[data-testid="auto-refresh-toggle"]');
    if (await autoRefreshToggle.isVisible()) {
      await autoRefreshToggle.click();
      
      // Wait and verify auto-refresh is working
      const initialTimestamp = await page.locator('[data-testid="last-updated"]').textContent();
      await page.waitForTimeout(3000);
      const newTimestamp = await page.locator('[data-testid="last-updated"]').textContent();
      
      expect(newTimestamp).not.toBe(initialTimestamp);
    }
  });

  test('quantum device manager interface', async () => {
    await page.click('[data-testid="tab-devices"]');
    
    // Verify device manager is loaded
    await expect(page.locator('[data-testid="quantum-device-manager"]')).toBeVisible();
    
    // Test device list display
    const deviceList = page.locator('[data-testid="device-list"]');
    await expect(deviceList).toBeVisible();
    
    // Check if devices are present
    const deviceCount = await page.locator('[data-testid="device-item"]').count();
    expect(deviceCount).toBeGreaterThanOrEqual(0);
    
    if (deviceCount > 0) {
      // Test device selection
      const firstDevice = page.locator('[data-testid="device-item"]').first();
      await firstDevice.click();
      
      // Verify device details appear
      await expect(page.locator('[data-testid="device-details"]')).toBeVisible();
      await expect(page.locator('[data-testid="device-capabilities"]')).toBeVisible();
      await expect(page.locator('[data-testid="device-status"]')).toBeVisible();
      
      // Test device actions
      const upgradeButton = page.locator('[data-testid="upgrade-device"]');
      if (await upgradeButton.isVisible()) {
        await upgradeButton.click();
        await expect(page.locator('[data-testid="upgrade-progress"]')).toBeVisible();
      }
    }
    
    // Test add device functionality
    const addDeviceButton = page.locator('[data-testid="add-device-button"]');
    if (await addDeviceButton.isVisible()) {
      await addDeviceButton.click();
      await expect(page.locator('[data-testid="add-device-modal"]')).toBeVisible();
      
      // Fill device information
      await page.fill('[data-testid="device-name-input"]', 'Test Admin Device');
      await page.selectOption('[data-testid="device-type-select"]', 'desktop');
      await page.check('[data-testid="ml-kem-768-capability"]');
      
      await page.click('[data-testid="register-device-button"]');
      
      // Verify success message
      await expect(page.locator('[data-testid="device-registered-success"]')).toBeVisible();
    }
  });

  test('quantum migration management interface', async () => {
    await page.click('[data-testid="tab-migration"]');
    
    // Check if migration is already in progress
    const migrationInProgress = await page.locator('[data-testid="migration-in-progress"]').isVisible();
    
    if (migrationInProgress) {
      // Test monitoring ongoing migration
      await expect(page.locator('[data-testid="migration-progress-bar"]')).toBeVisible();
      await expect(page.locator('[data-testid="migration-phase"]')).toBeVisible();
      await expect(page.locator('[data-testid="migration-step-description"]')).toBeVisible();
      
      // Test cancel migration
      const cancelButton = page.locator('[data-testid="cancel-migration"]');
      if (await cancelButton.isVisible()) {
        await cancelButton.click();
        await expect(page.locator('[data-testid="cancel-confirmation"]')).toBeVisible();
        
        // Don't actually cancel, just verify UI
        await page.click('[data-testid="cancel-confirmation-no"]');
      }
    } else {
      // Test starting new migration
      await expect(page.locator('[data-testid="start-migration-section"]')).toBeVisible();
      
      // Verify migration readiness assessment
      const assessmentCard = page.locator('[data-testid="migration-assessment"]');
      await expect(assessmentCard).toBeVisible();
      
      const riskLevel = await page.locator('[data-testid="risk-level"]').textContent();
      expect(riskLevel).toMatch(/LOW|MEDIUM|HIGH/);
      
      const recommendedStrategy = await page.locator('[data-testid="recommended-strategy"]').textContent();
      expect(recommendedStrategy).toMatch(/immediate|gradual|hybrid/);
      
      // Test migration strategy buttons
      const strategies = ['immediate', 'gradual', 'hybrid'];
      
      for (const strategy of strategies) {
        const strategyButton = page.locator(`[data-testid="strategy-${strategy}"]`);
        if (await strategyButton.isVisible()) {
          await expect(strategyButton).toBeVisible();
          
          // Hover to see description
          await strategyButton.hover();
          await expect(page.locator('[data-testid="strategy-tooltip"]')).toBeVisible();
        }
      }
    }
    
    // Test migration history if available
    const historySection = page.locator('[data-testid="migration-history"]');
    if (await historySection.isVisible()) {
      await expect(historySection).toBeVisible();
      
      const historyItems = page.locator('[data-testid="migration-history-item"]');
      const historyCount = await historyItems.count();
      
      if (historyCount > 0) {
        const firstHistoryItem = historyItems.first();
        await firstHistoryItem.click();
        
        await expect(page.locator('[data-testid="migration-details"]')).toBeVisible();
        await expect(page.locator('[data-testid="migration-results"]')).toBeVisible();
      }
    }
  });

  test('quantum analytics and metrics display', async () => {
    await page.click('[data-testid="tab-analytics"]');
    
    // Check if analytics are implemented
    const analyticsPlaceholder = page.locator('[data-testid="analytics-placeholder"]');
    const analyticsContent = page.locator('[data-testid="analytics-content"]');
    
    if (await analyticsContent.isVisible()) {
      // Test analytics functionality
      await expect(page.locator('[data-testid="performance-metrics"]')).toBeVisible();
      await expect(page.locator('[data-testid="usage-statistics"]')).toBeVisible();
      
      // Test metric filters
      const timeRangeSelect = page.locator('[data-testid="time-range-select"]');
      if (await timeRangeSelect.isVisible()) {
        await timeRangeSelect.selectOption('7d');
        await expect(page.locator('[data-testid="loading-metrics"]')).toBeVisible();
        await expect(page.locator('[data-testid="loading-metrics"]')).not.toBeVisible();
      }
      
      // Test metric export
      const exportButton = page.locator('[data-testid="export-metrics"]');
      if (await exportButton.isVisible()) {
        await exportButton.click();
        await expect(page.locator('[data-testid="export-options"]')).toBeVisible();
      }
    } else if (await analyticsPlaceholder.isVisible()) {
      // Verify placeholder content
      await expect(analyticsPlaceholder).toContainText('Analytics dashboard coming soon');
    }
  });

  test('quantum settings configuration', async () => {
    await page.click('[data-testid="tab-settings"]');
    
    // Check if settings are implemented
    const settingsPlaceholder = page.locator('[data-testid="settings-placeholder"]');
    const settingsContent = page.locator('[data-testid="settings-content"]');
    
    if (await settingsContent.isVisible()) {
      // Test settings functionality
      await expect(page.locator('[data-testid="quantum-preferences"]')).toBeVisible();
      
      // Test algorithm preferences
      const defaultAlgorithmSelect = page.locator('[data-testid="default-algorithm-select"]');
      if (await defaultAlgorithmSelect.isVisible()) {
        await defaultAlgorithmSelect.selectOption('ML-KEM-768');
        await expect(page.locator('[data-testid="settings-saved"]')).toBeVisible();
      }
      
      // Test security level settings
      const securityLevelRadios = page.locator('[data-testid="security-level-radio"]');
      const radioCount = await securityLevelRadios.count();
      
      if (radioCount > 0) {
        await securityLevelRadios.first().check();
        await expect(page.locator('[data-testid="security-level-updated"]')).toBeVisible();
      }
      
      // Test performance vs security balance
      const performanceSlider = page.locator('[data-testid="performance-security-slider"]');
      if (await performanceSlider.isVisible()) {
        await performanceSlider.fill('75');
        await expect(page.locator('[data-testid="balance-updated"]')).toBeVisible();
      }
    } else if (await settingsPlaceholder.isVisible()) {
      // Verify placeholder content
      await expect(settingsPlaceholder).toContainText('Settings panel coming soon');
    }
  });

  test('quantum status badges and indicators', async () => {
    await page.click('[data-testid="tab-overview"]');
    
    // Test quantum status badges throughout the interface
    const statusBadges = page.locator('[data-testid="quantum-status-badge"]');
    const badgeCount = await statusBadges.count();
    
    if (badgeCount > 0) {
      for (let i = 0; i < badgeCount; i++) {
        const badge = statusBadges.nth(i);
        await expect(badge).toBeVisible();
        
        // Verify badge has appropriate styling
        const badgeClasses = await badge.getAttribute('class');
        expect(badgeClasses).toMatch(/quantum|secure|algorithm/);
        
        // Test tooltip on hover
        await badge.hover();
        const tooltip = page.locator('[data-testid="status-tooltip"]');
        if (await tooltip.isVisible()) {
          const tooltipText = await tooltip.textContent();
          expect(tooltipText).toBeTruthy();
          expect(tooltipText!.length).toBeGreaterThan(0);
        }
      }
    }
  });

  test('quantum admin alert and notification handling', async () => {
    // Test various alert scenarios
    const alertTypes = [
      'quantum-unavailable',
      'device-upgrade-needed',
      'migration-required',
      'security-warning'
    ];
    
    for (const alertType of alertTypes) {
      // Look for specific alert type
      const alert = page.locator(`[data-testid="alert-${alertType}"]`);
      
      if (await alert.isVisible()) {
        await expect(alert).toBeVisible();
        
        // Test alert actions
        const dismissButton = alert.locator('[data-testid="dismiss-alert"]');
        const actionButton = alert.locator('[data-testid="alert-action"]');
        
        if (await actionButton.isVisible()) {
          await actionButton.click();
          
          // Verify appropriate action was taken
          switch (alertType) {
            case 'device-upgrade-needed':
              await expect(page.locator('[data-testid="device-upgrade-modal"]')).toBeVisible();
              await page.click('[data-testid="close-modal"]');
              break;
            case 'migration-required':
              await expect(page.locator('[data-testid="migration-wizard"]')).toBeVisible();
              await page.click('[data-testid="close-wizard"]');
              break;
          }
        }
        
        if (await dismissButton.isVisible()) {
          await dismissButton.click();
          await expect(alert).not.toBeVisible();
        }
      }
    }
  });

  test('quantum admin responsive design and accessibility', async () => {
    // Test responsive design at different viewport sizes
    const viewports = [
      { width: 1920, height: 1080, name: 'desktop' },
      { width: 1024, height: 768, name: 'tablet' },
      { width: 375, height: 667, name: 'mobile' }
    ];
    
    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      
      // Verify main components are visible and properly arranged
      await expect(page.locator('[data-testid="quantum-admin-panel"]')).toBeVisible();
      
      if (viewport.width < 768) {
        // Mobile view - check for collapsed navigation
        const mobileMenu = page.locator('[data-testid="mobile-menu-toggle"]');
        if (await mobileMenu.isVisible()) {
          await mobileMenu.click();
          await expect(page.locator('[data-testid="mobile-nav-menu"]')).toBeVisible();
        }
      } else {
        // Desktop/tablet view - check for full navigation
        await expect(page.locator('[data-testid="tab-navigation"]')).toBeVisible();
      }
    }
    
    // Reset to desktop viewport
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // Test keyboard navigation
    await page.keyboard.press('Tab');
    let focusedElement = await page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
    expect(focusedElement).toBeTruthy();
    
    // Test several tab presses to ensure proper focus management
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');
      const newFocusedElement = await page.evaluate(() => document.activeElement?.getAttribute('data-testid'));
      
      if (newFocusedElement !== focusedElement) {
        expect(newFocusedElement).toBeTruthy();
        focusedElement = newFocusedElement;
      }
    }
    
    // Test ARIA labels and roles
    const interactiveElements = page.locator('button, [role="button"], [role="tab"], [role="tabpanel"]');
    const elementCount = await interactiveElements.count();
    
    for (let i = 0; i < Math.min(elementCount, 10); i++) {
      const element = interactiveElements.nth(i);
      
      // Check for appropriate ARIA attributes
      const ariaLabel = await element.getAttribute('aria-label');
      const ariaRole = await element.getAttribute('role');
      const ariaDescribedBy = await element.getAttribute('aria-describedby');
      
      // At least one of these should be present for accessibility
      const hasAccessibilityAttribute = ariaLabel || ariaRole || ariaDescribedBy;
      expect(hasAccessibilityAttribute).toBeTruthy();
    }
  });

  test('quantum admin error handling and user feedback', async () => {
    // Test error scenarios and user feedback
    
    // Simulate network error
    await page.route('**/api/v1/quantum/health', route => {
      route.abort('failed');
    });
    
    await page.click('[data-testid="refresh-button"]');
    
    // Verify error message appears
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible();
    await expect(page.locator('[data-testid="error-message"]')).toContainText(/error|failed|unavailable/i);
    
    // Test retry functionality
    await page.unroute('**/api/v1/quantum/health');
    
    const retryButton = page.locator('[data-testid="retry-button"]');
    if (await retryButton.isVisible()) {
      await retryButton.click();
      await expect(page.locator('[data-testid="error-message"]')).not.toBeVisible({ timeout: 5000 });
    }
    
    // Test loading states
    await page.click('[data-testid="tab-devices"]');
    await expect(page.locator('[data-testid="loading-devices"]')).toBeVisible();
    await expect(page.locator('[data-testid="loading-devices"]')).not.toBeVisible({ timeout: 10000 });
    
    // Test success feedback
    const successMessage = page.locator('[data-testid="success-message"]');
    if (await successMessage.isVisible()) {
      await expect(successMessage).toContainText(/success|completed|updated/i);
      
      // Verify success message auto-dismisses
      await expect(successMessage).not.toBeVisible({ timeout: 5000 });
    }
  });

  test('quantum admin performance and optimization', async () => {
    // Test performance aspects of the admin interface
    
    const startTime = Date.now();
    
    // Navigate through all tabs to test loading performance
    const tabs = ['overview', 'migration', 'devices', 'analytics', 'settings'];
    
    for (const tab of tabs) {
      const tabStartTime = Date.now();
      await page.click(`[data-testid="tab-${tab}"]`);
      await expect(page.locator(`[data-testid="tab-content-${tab}"]`)).toBeVisible();
      
      const tabLoadTime = Date.now() - tabStartTime;
      expect(tabLoadTime).toBeLessThan(2000); // Each tab should load within 2 seconds
      
      console.log(`${tab} tab loaded in ${tabLoadTime}ms`);
    }
    
    const totalTime = Date.now() - startTime;
    console.log(`Total navigation time: ${totalTime}ms`);
    
    // Test data refresh performance
    await page.click('[data-testid="tab-overview"]');
    
    const refreshStartTime = Date.now();
    await page.click('[data-testid="refresh-button"]');
    await expect(page.locator('.animate-spin')).not.toBeVisible({ timeout: 10000 });
    
    const refreshTime = Date.now() - refreshStartTime;
    expect(refreshTime).toBeLessThan(5000); // Refresh should complete within 5 seconds
    
    console.log(`Data refresh completed in ${refreshTime}ms`);
  });
});