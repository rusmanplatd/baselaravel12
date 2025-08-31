import { test, expect, Page } from '@playwright/test';

test.describe('Quantum Performance and Security E2E Tests', () => {
  let page: Page;

  test.beforeEach(async ({ browser }) => {
    page = await browser.newPage();
    
    // Setup authentication and quantum-ready environment
    await page.goto('/login');
    await page.fill('[name="email"]', 'quantum.test@example.com');
    await page.fill('[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Wait for login to complete
    await expect(page).toHaveURL('/dashboard');
    
    // Ensure quantum features are available
    await page.goto('/admin/quantum');
    await expect(page.locator('.quantum-health-indicator')).toBeVisible();
  });

  test('quantum encryption performance benchmarks', async () => {
    await page.goto('/chat');
    
    // Create a new conversation
    await page.click('[data-testid="new-conversation"]');
    await page.fill('[data-testid="conversation-title"]', 'Quantum Performance Test');
    await page.click('[data-testid="create-conversation"]');
    
    // Switch to quantum encryption
    await page.click('[data-testid="encryption-settings"]');
    await page.selectOption('[data-testid="algorithm-select"]', 'ML-KEM-768');
    await page.click('[data-testid="apply-encryption"]');
    
    // Benchmark message encryption speed
    const messages = [
      'Small message for quantum encryption test',
      'Medium length message that contains more text to test quantum encryption performance with realistic message sizes',
      'Large message with extensive content that simulates real-world usage scenarios including multiple paragraphs, special characters, and various text formats that users typically send in chat applications. This message is designed to test the quantum encryption performance under more demanding conditions.'
    ];
    
    const performanceResults = [];
    
    for (let i = 0; i < messages.length; i++) {
      const startTime = Date.now();
      
      await page.fill('[data-testid="message-input"]', messages[i]);
      await page.click('[data-testid="send-message"]');
      
      // Wait for message to be encrypted and sent
      await expect(page.locator(`[data-testid="message-${i + 1}"]`)).toBeVisible();
      
      const endTime = Date.now();
      const encryptionTime = endTime - startTime;
      
      performanceResults.push({
        messageSize: messages[i].length,
        encryptionTime,
        throughput: messages[i].length / encryptionTime * 1000 // chars per second
      });
      
      // Verify message was encrypted with quantum algorithm
      const messageElement = page.locator(`[data-testid="message-${i + 1}"]`);
      await expect(messageElement).toHaveAttribute('data-algorithm', 'ML-KEM-768');
    }
    
    // Performance assertions
    expect(performanceResults[0].encryptionTime).toBeLessThan(1000); // Small messages < 1s
    expect(performanceResults[1].encryptionTime).toBeLessThan(2000); // Medium messages < 2s  
    expect(performanceResults[2].encryptionTime).toBeLessThan(3000); // Large messages < 3s
    
    // Throughput should be reasonable
    expect(performanceResults[2].throughput).toBeGreaterThan(100); // > 100 chars/sec
    
    console.log('Quantum Encryption Performance Results:', performanceResults);
  });

  test('quantum vs classical encryption performance comparison', async () => {
    await page.goto('/admin/quantum');
    
    // Run performance comparison test
    await page.click('[data-testid="performance-test-tab"]');
    await page.click('[data-testid="run-comparison-test"]');
    
    // Wait for test to complete
    await expect(page.locator('[data-testid="test-progress"]')).toBeVisible();
    await expect(page.locator('[data-testid="test-complete"]')).toBeVisible({ timeout: 30000 });
    
    // Verify performance metrics
    const rsaTime = await page.locator('[data-testid="rsa-key-generation-time"]').textContent();
    const quantumTime = await page.locator('[data-testid="quantum-key-generation-time"]').textContent();
    
    const rsaMs = parseFloat(rsaTime?.replace('ms', '') || '0');
    const quantumMs = parseFloat(quantumTime?.replace('ms', '') || '0');
    
    // Quantum should be significantly faster
    expect(quantumMs).toBeLessThan(rsaMs / 10); // At least 10x faster
    
    // Check memory usage comparison
    const rsaMemory = await page.locator('[data-testid="rsa-memory-usage"]').textContent();
    const quantumMemory = await page.locator('[data-testid="quantum-memory-usage"]').textContent();
    
    const rsaKb = parseFloat(rsaMemory?.replace('KB', '') || '0');
    const quantumKb = parseFloat(quantumMemory?.replace('KB', '') || '0');
    
    // Quantum should use less memory
    expect(quantumKb).toBeLessThan(rsaKb);
  });

  test('quantum encryption security validation', async () => {
    await page.goto('/chat');
    
    // Create conversation with quantum encryption
    await page.click('[data-testid="new-conversation"]');
    await page.fill('[data-testid="conversation-title"]', 'Security Validation Test');
    await page.click('[data-testid="create-conversation"]');
    
    await page.click('[data-testid="encryption-settings"]');
    await page.selectOption('[data-testid="algorithm-select"]', 'ML-KEM-768');
    await page.click('[data-testid="apply-encryption"]');
    
    // Send sensitive test message
    await page.fill('[data-testid="message-input"]', 'CONFIDENTIAL: Quantum security test message');
    await page.click('[data-testid="send-message"]');
    
    // Verify encryption indicators
    const messageElement = page.locator('[data-testid="message-1"]');
    await expect(messageElement).toHaveAttribute('data-encrypted', 'true');
    await expect(messageElement).toHaveAttribute('data-quantum-resistant', 'true');
    
    // Check security status badge
    const securityBadge = page.locator('[data-testid="security-badge"]');
    await expect(securityBadge).toHaveText(/Quantum-Safe/);
    await expect(securityBadge).toHaveClass(/quantum-secure/);
    
    // Verify encryption metadata
    await page.click('[data-testid="message-details-1"]');
    await expect(page.locator('[data-testid="encryption-algorithm"]')).toHaveText('ML-KEM-768');
    await expect(page.locator('[data-testid="security-level"]')).toHaveText('NIST Level 3');
    await expect(page.locator('[data-testid="quantum-resistant"]')).toHaveText('Yes');
  });

  test('multi-device quantum encryption synchronization', async () => {
    // Simulate multiple devices by opening multiple browser contexts
    const context1 = await page.context().browser()?.newContext();
    const context2 = await page.context().browser()?.newContext();
    
    const device1 = await context1?.newPage();
    const device2 = await context2?.newPage();
    
    // Login on both devices
    for (const devicePage of [device1!, device2!]) {
      await devicePage.goto('/login');
      await devicePage.fill('[name="email"]', 'quantum.test@example.com');
      await devicePage.fill('[name="password"]', 'password123');
      await devicePage.click('button[type="submit"]');
      await expect(devicePage).toHaveURL('/dashboard');
    }
    
    // Navigate to chat on device 1
    await device1!.goto('/chat');
    await device1!.click('[data-testid="conversation-security-test"]');
    
    // Send quantum-encrypted message from device 1
    await device1!.fill('[data-testid="message-input"]', 'Multi-device quantum test message');
    await device1!.click('[data-testid="send-message"]');
    
    // Verify message appears on device 2
    await device2!.goto('/chat');
    await device2!.click('[data-testid="conversation-security-test"]');
    
    await expect(device2!.locator('[data-testid="message-1"]')).toBeVisible();
    await expect(device2!.locator('[data-testid="message-1"]')).toContainText('Multi-device quantum test message');
    
    // Verify quantum encryption status on device 2
    const device2SecurityBadge = device2!.locator('[data-testid="security-badge"]');
    await expect(device2SecurityBadge).toHaveText(/Quantum-Safe/);
    
    // Send response from device 2
    await device2!.fill('[data-testid="message-input"]', 'Quantum response from device 2');
    await device2!.click('[data-testid="send-message"]');
    
    // Verify response appears on device 1
    await expect(device1!.locator('[data-testid="message-2"]')).toBeVisible();
    await expect(device1!.locator('[data-testid="message-2"]')).toContainText('Quantum response from device 2');
    
    await context1?.close();
    await context2?.close();
  });

  test('quantum encryption algorithm negotiation flow', async () => {
    await page.goto('/admin/quantum');
    
    // Navigate to device management
    await page.click('[data-testid="device-management-tab"]');
    
    // Create devices with different capabilities
    await page.click('[data-testid="add-device"]');
    await page.fill('[data-testid="device-name"]', 'High Security Device');
    await page.selectOption('[data-testid="device-type"]', 'desktop');
    await page.check('[data-testid="ml-kem-1024"]');
    await page.check('[data-testid="ml-kem-768"]');
    await page.click('[data-testid="register-device"]');
    
    await page.click('[data-testid="add-device"]');
    await page.fill('[data-testid="device-name"]', 'Standard Device');
    await page.selectOption('[data-testid="device-type"]', 'mobile');
    await page.check('[data-testid="ml-kem-768"]');
    await page.check('[data-testid="hybrid"]');
    await page.click('[data-testid="register-device"]');
    
    await page.click('[data-testid="add-device"]');
    await page.fill('[data-testid="device-name"]', 'Legacy Device');
    await page.selectOption('[data-testid="device-type"]', 'tablet');
    await page.check('[data-testid="rsa-4096"]');
    await page.click('[data-testid="register-device"]');
    
    // Test algorithm negotiation
    await page.click('[data-testid="test-negotiation"]');
    
    await expect(page.locator('[data-testid="negotiated-algorithm"]')).toBeVisible();
    const negotiatedAlgorithm = await page.locator('[data-testid="negotiated-algorithm"]').textContent();
    
    // Should negotiate hybrid mode due to legacy device
    expect(negotiatedAlgorithm).toMatch(/HYBRID-RSA4096-MLKEM768|RSA-4096-OAEP/);
    
    // Verify negotiation details
    await expect(page.locator('[data-testid="compatible-devices"]')).toContainText('3 devices');
    await expect(page.locator('[data-testid="quantum-ready-devices"]')).toContainText('2 devices');
  });

  test('quantum migration process monitoring', async () => {
    await page.goto('/admin/quantum');
    
    // Navigate to migration tab
    await page.click('[data-testid="migration-tab"]');
    
    // Start gradual migration
    await page.click('[data-testid="start-migration"]');
    await page.selectOption('[data-testid="migration-strategy"]', 'gradual');
    await page.fill('[data-testid="batch-size"]', '5');
    await page.click('[data-testid="confirm-migration"]');
    
    // Monitor migration progress
    await expect(page.locator('[data-testid="migration-in-progress"]')).toBeVisible();
    
    // Wait for migration to progress
    let progress = 0;
    const maxAttempts = 30;
    let attempts = 0;
    
    while (progress < 100 && attempts < maxAttempts) {
      await page.waitForTimeout(1000);
      
      const progressText = await page.locator('[data-testid="migration-progress"]').textContent();
      progress = parseInt(progressText?.match(/(\d+)%/)?.[1] || '0');
      attempts++;
      
      // Verify progress indicators
      await expect(page.locator('[data-testid="current-phase"]')).toBeVisible();
      await expect(page.locator('[data-testid="step-description"]')).toBeVisible();
    }
    
    // Verify migration completion or significant progress
    if (progress === 100) {
      await expect(page.locator('[data-testid="migration-completed"]')).toBeVisible();
      await expect(page.locator('[data-testid="migration-success-metrics"]')).toBeVisible();
      
      // Check success metrics
      const migratedCount = await page.locator('[data-testid="conversations-migrated"]').textContent();
      const upgradedDevices = await page.locator('[data-testid="devices-upgraded"]').textContent();
      
      expect(parseInt(migratedCount || '0')).toBeGreaterThan(0);
      expect(parseInt(upgradedDevices || '0')).toBeGreaterThan(0);
    } else {
      expect(progress).toBeGreaterThan(0);
    }
  });

  test('quantum system health monitoring', async () => {
    await page.goto('/admin/quantum');
    
    // Check overall system health
    await expect(page.locator('[data-testid="quantum-health-indicator"]')).toBeVisible();
    
    const healthStatus = await page.locator('[data-testid="health-status"]').textContent();
    expect(healthStatus).toMatch(/Healthy|Warning|Critical/);
    
    // Check quantum support status
    await expect(page.locator('[data-testid="quantum-support-status"]')).toBeVisible();
    await expect(page.locator('[data-testid="ml-kem-available"]')).toContainText(/Available|Not Available/);
    
    // Verify system metrics
    const readyDevices = await page.locator('[data-testid="quantum-ready-devices"]').textContent();
    const totalDevices = await page.locator('[data-testid="total-devices"]').textContent();
    const readinessPercentage = await page.locator('[data-testid="readiness-percentage"]').textContent();
    
    expect(parseInt(readyDevices || '0')).toBeGreaterThanOrEqual(0);
    expect(parseInt(totalDevices || '0')).toBeGreaterThanOrEqual(parseInt(readyDevices || '0'));
    
    const percentage = parseFloat(readinessPercentage?.replace('%', '') || '0');
    expect(percentage).toBeGreaterThanOrEqual(0);
    expect(percentage).toBeLessThanOrEqual(100);
    
    // Test health refresh
    await page.click('[data-testid="refresh-health"]');
    await expect(page.locator('[data-testid="health-last-updated"]')).toBeVisible();
  });

  test('quantum encryption error handling and recovery', async () => {
    await page.goto('/chat');
    
    // Create conversation
    await page.click('[data-testid="new-conversation"]');
    await page.fill('[data-testid="conversation-title"]', 'Error Recovery Test');
    await page.click('[data-testid="create-conversation"]');
    
    // Force an encryption error by simulating network issues
    await page.route('**/api/v1/quantum/encapsulate', route => {
      route.abort('failed');
    });
    
    // Try to send message with quantum encryption
    await page.click('[data-testid="encryption-settings"]');
    await page.selectOption('[data-testid="algorithm-select"]', 'ML-KEM-768');
    await page.click('[data-testid="apply-encryption"]');
    
    await page.fill('[data-testid="message-input"]', 'Test message for error recovery');
    await page.click('[data-testid="send-message"]');
    
    // Verify error handling
    await expect(page.locator('[data-testid="encryption-error"]')).toBeVisible();
    await expect(page.locator('[data-testid="fallback-notice"]')).toBeVisible();
    
    // Verify fallback to classical encryption
    const fallbackIndicator = page.locator('[data-testid="fallback-algorithm"]');
    await expect(fallbackIndicator).toContainText('RSA-4096-OAEP');
    
    // Clear network interception and retry
    await page.unroute('**/api/v1/quantum/encapsulate');
    
    await page.click('[data-testid="retry-quantum"]');
    await page.fill('[data-testid="message-input"]', 'Retry message after error recovery');
    await page.click('[data-testid="send-message"]');
    
    // Verify successful quantum encryption after recovery
    const retryMessage = page.locator('[data-testid="message-2"]');
    await expect(retryMessage).toBeVisible();
    await expect(retryMessage).toHaveAttribute('data-quantum-resistant', 'true');
  });

  test('quantum performance under load', async () => {
    await page.goto('/admin/quantum');
    
    // Navigate to performance testing
    await page.click('[data-testid="performance-test-tab"]');
    
    // Configure load test
    await page.fill('[data-testid="concurrent-operations"]', '50');
    await page.fill('[data-testid="message-size"]', '1000');
    await page.fill('[data-testid="test-duration"]', '30');
    
    await page.click('[data-testid="start-load-test"]');
    
    // Monitor test progress
    await expect(page.locator('[data-testid="load-test-running"]')).toBeVisible();
    
    // Wait for test completion
    await expect(page.locator('[data-testid="load-test-complete"]')).toBeVisible({ timeout: 45000 });
    
    // Verify performance results
    const throughput = await page.locator('[data-testid="operations-per-second"]').textContent();
    const errorRate = await page.locator('[data-testid="error-rate"]').textContent();
    const avgLatency = await page.locator('[data-testid="average-latency"]').textContent();
    
    expect(parseFloat(throughput || '0')).toBeGreaterThan(10); // > 10 ops/sec
    expect(parseFloat(errorRate?.replace('%', '') || '0')).toBeLessThan(5); // < 5% error rate
    expect(parseFloat(avgLatency?.replace('ms', '') || '0')).toBeLessThan(1000); // < 1s latency
    
    console.log('Load Test Results:', { throughput, errorRate, avgLatency });
  });

  test('quantum security audit trail', async () => {
    await page.goto('/admin/quantum');
    
    // Navigate to security audit tab
    await page.click('[data-testid="security-audit-tab"]');
    
    // Verify audit log entries
    await expect(page.locator('[data-testid="audit-log"]')).toBeVisible();
    
    // Check for critical security events
    const auditEntries = page.locator('[data-testid="audit-entry"]');
    const entryCount = await auditEntries.count();
    
    expect(entryCount).toBeGreaterThan(0);
    
    // Verify audit entry structure
    const firstEntry = auditEntries.first();
    await expect(firstEntry.locator('[data-testid="event-type"]')).toBeVisible();
    await expect(firstEntry.locator('[data-testid="timestamp"]')).toBeVisible();
    await expect(firstEntry.locator('[data-testid="user-id"]')).toBeVisible();
    await expect(firstEntry.locator('[data-testid="algorithm-used"]')).toBeVisible();
    
    // Filter by quantum-related events
    await page.selectOption('[data-testid="event-filter"]', 'quantum');
    
    const quantumEntries = page.locator('[data-testid="audit-entry"][data-event-category="quantum"]');
    const quantumCount = await quantumEntries.count();
    
    expect(quantumCount).toBeGreaterThan(0);
    
    // Verify quantum-specific audit information
    const quantumEntry = quantumEntries.first();
    await expect(quantumEntry.locator('[data-testid="algorithm-used"]')).toContainText(/ML-KEM|HYBRID/);
    await expect(quantumEntry.locator('[data-testid="security-level"]')).toBeVisible();
  });
});