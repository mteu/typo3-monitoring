import * as path from 'node:path';
import { defineConfig } from '@playwright/test';

/**
 * Runs against an already running TYPO3 instance, locally (e.g. ddev) or the
 * throwaway sqlite instance built in .github/workflows/e2e.yaml. Configured
 * through environment variables:
 *
 *   PLAYWRIGHT_BASE_URL  base URL of the instance (default http://127.0.0.1:8080)
 *   T3_MAJOR             TYPO3 major version under test, 13 or 14 (default 13)
 *   T3_ADMIN_USERNAME    backend admin username (default admin)
 *   T3_ADMIN_PASSWORD    backend admin password (default Password.123)
 */
export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080',
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'e2e',
      testMatch: /.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        storageState: path.join(__dirname, '.auth/login.json'),
      },
    },
  ],
});
