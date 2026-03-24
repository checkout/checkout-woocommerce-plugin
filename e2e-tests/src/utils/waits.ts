import { Page, Locator, expect } from '@playwright/test';

/**
 * Wait utilities for WooCommerce-specific scenarios.
 * Avoids hard sleeps in favor of condition-based waits.
 */

/**
 * Wait for WooCommerce AJAX to complete.
 * WooCommerce uses a blocking overlay during AJAX operations.
 */
export async function waitForWooAjax(page: Page, timeout = 30000): Promise<void> {
  // Wait for any WooCommerce blocking overlay to disappear
  const blockingOverlay = page.locator('.blockUI.blockOverlay, .woocommerce-checkout-payment.processing');
  
  try {
    // First check if overlay exists
    const count = await blockingOverlay.count();
    if (count > 0) {
      await blockingOverlay.first().waitFor({ state: 'hidden', timeout });
    }
  } catch {
    // Overlay may not appear for quick operations
  }
}

/**
 * Wait for WooCommerce checkout to finish processing.
 */
export async function waitForCheckoutProcessing(page: Page, timeout = 60000): Promise<void> {
  // Wait for processing state to appear and then disappear
  const processingIndicators = [
    '.woocommerce-checkout-payment.processing',
    '.blockUI.blockOverlay',
    '.woocommerce-checkout-review-order-table.processing',
  ];

  for (const selector of processingIndicators) {
    const element = page.locator(selector);
    try {
      await element.waitFor({ state: 'hidden', timeout });
    } catch {
      // May not appear
    }
  }
}

/**
 * Wait for cart to update after quantity change or coupon application.
 */
export async function waitForCartUpdate(page: Page, timeout = 15000): Promise<void> {
  await waitForWooAjax(page, timeout);
  
  // Wait for cart totals to be visible
  const cartTotals = page.locator('.cart_totals, .cart-collaterals');
  if (await cartTotals.count() > 0) {
    await cartTotals.first().waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
  }
}

/**
 * Wait for page to be fully loaded and interactive.
 */
export async function waitForPageReady(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForLoadState('networkidle').catch(() => {
    // Network idle may not complete for pages with continuous polling
  });
}

/**
 * Wait for a specific WooCommerce notice to appear.
 */
export async function waitForWooNotice(
  page: Page,
  type: 'message' | 'error' | 'info' = 'message',
  timeout = 10000
): Promise<Locator> {
  const selector = `.woocommerce-${type}`;
  const notice = page.locator(selector);
  await notice.waitFor({ state: 'visible', timeout });
  return notice;
}

/**
 * Wait for element to be stable (not moving/resizing).
 */
export async function waitForElementStable(locator: Locator, timeout = 5000): Promise<void> {
  const startTime = Date.now();
  let lastBox: { x: number; y: number; width: number; height: number } | null = null;
  let stableCount = 0;
  const requiredStableChecks = 3;

  while (Date.now() - startTime < timeout) {
    const box = await locator.boundingBox();
    if (box) {
      if (
        lastBox &&
        box.x === lastBox.x &&
        box.y === lastBox.y &&
        box.width === lastBox.width &&
        box.height === lastBox.height
      ) {
        stableCount++;
        if (stableCount >= requiredStableChecks) {
          return;
        }
      } else {
        stableCount = 0;
      }
      lastBox = box;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
}

/**
 * Wait for network requests matching a pattern to complete.
 */
export async function waitForApiResponse(
  page: Page,
  urlPattern: string | RegExp,
  timeout = 30000
): Promise<void> {
  await page.waitForResponse(
    (response) => {
      const url = response.url();
      if (typeof urlPattern === 'string') {
        return url.includes(urlPattern);
      }
      return urlPattern.test(url);
    },
    { timeout }
  );
}

/**
 * Poll until a condition is met or timeout.
 * Use sparingly - prefer explicit waits.
 */
export async function pollUntil(
  condition: () => Promise<boolean>,
  options: { timeout?: number; interval?: number; message?: string } = {}
): Promise<void> {
  const { timeout = 10000, interval = 200, message = 'Condition not met' } = options;
  const startTime = Date.now();

  while (Date.now() - startTime < timeout) {
    if (await condition()) {
      return;
    }
    await new Promise((resolve) => setTimeout(resolve, interval));
  }

  throw new Error(`Timeout: ${message} (waited ${timeout}ms)`);
}

/**
 * Wait for navigation to complete after an action.
 */
export async function waitForNavigation(
  page: Page,
  action: () => Promise<void>,
  options: { timeout?: number; waitUntil?: 'load' | 'domcontentloaded' | 'networkidle' } = {}
): Promise<void> {
  const { timeout = 30000, waitUntil = 'domcontentloaded' } = options;

  await Promise.all([
    page.waitForLoadState(waitUntil, { timeout }),
    action(),
  ]);
}
