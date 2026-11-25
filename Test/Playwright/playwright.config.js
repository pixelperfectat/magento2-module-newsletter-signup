// @ts-check
const { defineConfig, devices } = require('@playwright/test');
require('dotenv').config();

const enabled = (name, fallback) => (process.env[name] ?? fallback) === '1';

const projects = [];
if (enabled('LUMA_ENABLED', '1')) {
  projects.push({
    name: 'luma',
    use: {
      ...devices['Desktop Chrome'],
      theme: 'luma',
      checkout: 'luma',
      storeCode: process.env.LUMA_STORE_CODE || '',
    },
  });
}
if (enabled('HYVA_ENABLED', '0')) {
  projects.push({
    name: 'hyva',
    use: {
      ...devices['Desktop Chrome'],
      theme: 'hyva',
      checkout: process.env.HYVA_CHECKOUT || 'hyva',
      storeCode: process.env.HYVA_STORE_CODE || '',
    },
  });
}

/**
 * Playwright configuration for the newsletter sign-up storefront tests
 *
 * Every test places real orders and creates real subscribers with fresh addresses on a reserved domain,
 * so run it against a development or staging store, never production.
 *
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests',
  timeout: 180000,
  expect: { timeout: 15000 },
  // Orders and subscriptions share one cart session per test; keep it simple and sequential.
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'https://shop.example.com',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // Required for self-signed SSL certificates in development
    ignoreHTTPSErrors: true,
    viewport: { width: 1280, height: 1000 },
  },
  outputDir: './test-artifacts/',
  projects,
});
