import { FullConfig } from '@playwright/test';

async function globalTeardown(config: FullConfig): Promise<void> {
  console.log('\n🏁 E2E Test Suite Complete\n');

  // Log summary
  console.log('  📊 Test artifacts available in:');
  console.log('     - Report: playwright-report/index.html');
  console.log('     - Results: test-results/');
  console.log('     - Traces: test-results/ (on failure)');

  console.log('\n  💡 Run "npm run report" to view the HTML report\n');
}

export default globalTeardown;
