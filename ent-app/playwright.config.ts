import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 30 * 1000,
  use: {
    headless: true,
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8080'
  },
  reporter: [['list'], ['html', { outputFolder: 'playwright-report' }]]
});
