import { defineConfig, devices } from '@playwright/test';
import { loadEnvConfig } from './src/config/env-loader';
import path from 'path';

const env = loadEnvConfig();

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'test-results/results.json' }],
  ],

  timeout: 60000,
  expect: {
    timeout: 10000,
  },

  use: {
    baseURL: env.BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15000,
    navigationTimeout: 30000,
    locale: 'en-US',
    timezoneId: 'America/New_York',
  },

  outputDir: 'test-results/',

  projects: [
    {
      name: 'storefront',
      testDir: './tests/storefront',
      use: {
        ...devices['Desktop Chrome'],
        storageState: undefined,
      },
    },
    {
      name: 'admin',
      testDir: './tests/admin',
      use: {
        ...devices['Desktop Chrome'],
        storageState: path.join(__dirname, '.auth/admin.json'),
      },
      dependencies: ['admin-setup'],
    },
    {
      name: 'admin-setup',
      testMatch: /admin\.setup\.ts/,
      use: {
        ...devices['Desktop Chrome'],
      },
    },
    {
      name: 'e2e',
      testDir: './tests/e2e',
      use: {
        ...devices['Desktop Chrome'],
      },
      dependencies: ['admin-setup'],
    },
    {
      name: 'mobile-storefront',
      testDir: './tests/storefront',
      use: {
        ...devices['iPhone 14'],
      },
    },
  ],

  globalSetup: path.join(__dirname, 'src/config/global-setup.ts'),
  globalTeardown: path.join(__dirname, 'src/config/global-teardown.ts'),
});
