import * as path from 'node:path';
import { test as setup, expect } from '@playwright/test';

const username = process.env.T3_ADMIN_USERNAME ?? 'admin';
const password = process.env.T3_ADMIN_PASSWORD ?? 'Password.123';

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/typo3');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  // v13 renders the module menu directly, v14 behind its sidebar toggle.
  await expect(
    page.locator('[data-modulemenu-identifier], typo3-backend-sidebar-toggle').first(),
  ).toBeVisible({ timeout: 15_000 });
  await page.context().storageState({ path: path.join(__dirname, '../.auth/login.json') });
});
