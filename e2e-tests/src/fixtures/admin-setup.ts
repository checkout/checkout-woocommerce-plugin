import { test as setup } from '@playwright/test';
import { AdminLoginPage } from '../pages/admin';
import { getEnvConfig } from '../config/env-loader';
import path from 'path';

const authFile = path.join(__dirname, '../../.auth/admin.json');

/**
 * Admin authentication setup.
 * Runs once before admin tests to create authenticated state.
 */
setup('authenticate as admin', async ({ page }) => {
  const config = getEnvConfig();
  
  console.log('🔐 Setting up admin authentication...');
  
  const adminLoginPage = new AdminLoginPage(page);
  await adminLoginPage.goto();
  await adminLoginPage.login(config.ADMIN_USER, config.ADMIN_PASS);
  
  // Verify login succeeded
  await adminLoginPage.assertLoggedIn();
  
  // Save storage state
  await page.context().storageState({ path: authFile });
  
  console.log('✅ Admin authentication saved to:', authFile);
});
