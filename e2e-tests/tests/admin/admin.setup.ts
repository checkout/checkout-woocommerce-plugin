import { test as setup, expect } from '@playwright/test';
import { AdminLoginPage } from '../../src/pages/admin';
import { getEnvConfig } from '../../src/config/env-loader';
import path from 'path';
import fs from 'fs';

const authFile = path.join(__dirname, '../../.auth/admin.json');

/**
 * Admin authentication setup.
 * This runs before admin/e2e tests to create authenticated state.
 */
setup('authenticate as admin', async ({ page }) => {
  const config = getEnvConfig();
  
  console.log('🔐 Setting up admin authentication...');
  console.log(`   Admin URL: ${config.ADMIN_URL}`);
  
  // Ensure .auth directory exists
  const authDir = path.dirname(authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const adminLoginPage = new AdminLoginPage(page);
  
  // Navigate to admin login
  await adminLoginPage.goto();
  
  // Check if already logged in
  if (await adminLoginPage.isLoggedIn()) {
    console.log('   Already logged in');
  } else {
    // Perform login
    await adminLoginPage.login(config.ADMIN_USER, config.ADMIN_PASS);
  }
  
  // Verify login succeeded
  await adminLoginPage.assertLoggedIn();
  
  // Navigate to WooCommerce to ensure cookies are set
  await page.goto(`${config.ADMIN_URL}/admin.php?page=wc-admin`);
  await page.waitForLoadState('networkidle').catch(() => {});
  
  // Save storage state
  await page.context().storageState({ path: authFile });
  
  console.log('✅ Admin authentication saved');
  console.log(`   Auth file: ${authFile}`);
});
