import { FullConfig } from '@playwright/test';
import { loadEnvConfig } from './env-loader';
import fs from 'fs';
import path from 'path';

async function globalSetup(config: FullConfig): Promise<void> {
  console.log('\n🚀 Starting E2E Test Suite Global Setup\n');

  // Load and validate environment
  const env = loadEnvConfig();
  console.log(`  📍 Base URL: ${env.BASE_URL}`);
  console.log(`  📍 Admin URL: ${env.ADMIN_URL}`);
  console.log(`  📍 Run ID: ${env.RUN_ID}`);
  console.log(`  📍 Payment Test Mode: ${env.PAYMENT_TEST_MODE}`);

  // Create auth directory if it doesn't exist
  const authDir = path.join(__dirname, '../../.auth');
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
    console.log('  📁 Created .auth directory');
  }

  // Create test-results directory
  const resultsDir = path.join(__dirname, '../../test-results');
  if (!fs.existsSync(resultsDir)) {
    fs.mkdirSync(resultsDir, { recursive: true });
    console.log('  📁 Created test-results directory');
  }

  // Log test configuration
  console.log('\n  📋 Test Configuration:');
  console.log(`     - Retries: ${config.projects[0]?.retries ?? 0}`);
  console.log(`     - Timeout: ${config.projects[0]?.timeout ?? 60000}ms`);
  console.log(`     - Workers: ${config.workers}`);

  // Verify store is accessible (quick health check)
  try {
    const response = await fetch(env.BASE_URL, { method: 'HEAD' });
    if (response.ok) {
      console.log(`\n  ✅ Store is accessible (status: ${response.status})`);
    } else {
      console.warn(`\n  ⚠️  Store returned status: ${response.status}`);
    }
  } catch (error) {
    console.error(`\n  ❌ Could not reach store at ${env.BASE_URL}`);
    console.error('     Ensure the store is running and accessible.');
  }

  console.log('\n✅ Global Setup Complete\n');
}

export default globalSetup;
