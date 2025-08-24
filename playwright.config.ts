import { defineConfig, devices } from '@playwright/test';
import os from 'os';

const totalCpus = os.cpus().length;
const fraction = parseFloat(process.env.WORKER_FRACTION || "0.75");
const workers = Math.ceil(totalCpus * fraction);
// const workers = totalCpus - 1;

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : workers,
  reporter: 'html',
  timeout: 1200000, // Increase timeout for E2EE operations
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    // Enable HTTPS context for WebCrypto API
    ignoreHTTPSErrors: true,
    // Increase action timeout for crypto operations
    actionTimeout: 30000,
    // Enable video recording for E2EE test debugging
    video: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    // E2EE specific test configurations
    {
      name: 'e2ee-multi-device',
      testMatch: '**/multi-device-e2ee*.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        // Enable SharedArrayBuffer for better crypto performance
        launchOptions: {
          args: ['--enable-features=SharedArrayBuffer']
        }
      },
    },
    {
      name: 'e2ee-performance',
      testMatch: '**/e2ee-performance*.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        // Performance test specific settings
        launchOptions: {
          args: ['--enable-features=SharedArrayBuffer', '--disable-web-security']
        }
      },
      timeout: 120000, // Longer timeout for performance tests
    },
    {
      name: 'e2ee-integration',
      testMatch: '**/e2ee-backend-integration*.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        // Real backend integration settings
        baseURL: process.env.E2EE_TEST_URL || 'http://127.0.0.1:8000',
      },
      timeout: 90000,
    },
  ],

  webServer: {
    command: 'composer dev',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
  },
});
