import { Page, Locator } from '@playwright/test';

/**
 * Retry configuration options.
 */
export interface RetryOptions {
  maxAttempts?: number;
  delay?: number;
  timeout?: number;
  onRetry?: (attempt: number, error: Error) => void;
}

/**
 * Retry a function until it succeeds or max attempts reached.
 */
export async function retry<T>(
  fn: () => Promise<T>,
  options: RetryOptions = {}
): Promise<T> {
  const {
    maxAttempts = 3,
    delay = 1000,
    timeout = 30000,
    onRetry,
  } = options;

  const startTime = Date.now();
  let lastError: Error | null = null;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    if (Date.now() - startTime > timeout) {
      throw new Error(`Retry timeout exceeded (${timeout}ms)`);
    }

    try {
      return await fn();
    } catch (error) {
      lastError = error as Error;

      if (attempt < maxAttempts) {
        onRetry?.(attempt, lastError);
        await new Promise((resolve) => setTimeout(resolve, delay));
      }
    }
  }

  throw lastError || new Error('Retry failed');
}

/**
 * Retry with exponential backoff.
 */
export async function retryWithBackoff<T>(
  fn: () => Promise<T>,
  options: RetryOptions & { backoffMultiplier?: number } = {}
): Promise<T> {
  const {
    maxAttempts = 3,
    delay = 1000,
    backoffMultiplier = 2,
    timeout = 30000,
    onRetry,
  } = options;

  const startTime = Date.now();
  let currentDelay = delay;
  let lastError: Error | null = null;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    if (Date.now() - startTime > timeout) {
      throw new Error(`Retry timeout exceeded (${timeout}ms)`);
    }

    try {
      return await fn();
    } catch (error) {
      lastError = error as Error;

      if (attempt < maxAttempts) {
        onRetry?.(attempt, lastError);
        await new Promise((resolve) => setTimeout(resolve, currentDelay));
        currentDelay *= backoffMultiplier;
      }
    }
  }

  throw lastError || new Error('Retry with backoff failed');
}

/**
 * Safe click with retry for flaky elements.
 * Only use when necessary - prefer Playwright's built-in auto-waiting.
 */
export async function safeClick(
  locator: Locator,
  options: RetryOptions & { force?: boolean } = {}
): Promise<void> {
  const { maxAttempts = 3, delay = 500, force = false } = options;

  await retry(
    async () => {
      await locator.click({ force, timeout: 5000 });
    },
    { maxAttempts, delay }
  );
}

/**
 * Safe fill with retry for inputs that may not be immediately ready.
 */
export async function safeFill(
  locator: Locator,
  value: string,
  options: RetryOptions = {}
): Promise<void> {
  const { maxAttempts = 3, delay = 500 } = options;

  await retry(
    async () => {
      await locator.clear();
      await locator.fill(value);
    },
    { maxAttempts, delay }
  );
}

/**
 * Safe select with retry for dropdowns.
 */
export async function safeSelect(
  locator: Locator,
  value: string,
  options: RetryOptions = {}
): Promise<void> {
  const { maxAttempts = 3, delay = 500 } = options;

  await retry(
    async () => {
      await locator.selectOption(value, { timeout: 5000 });
    },
    { maxAttempts, delay }
  );
}

/**
 * Wait for condition with polling.
 */
export async function waitForCondition(
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

  throw new Error(`Timeout waiting for condition: ${message}`);
}

/**
 * Retry navigation if it fails.
 */
export async function safeGoto(
  page: Page,
  url: string,
  options: RetryOptions = {}
): Promise<void> {
  const { maxAttempts = 2, delay = 2000 } = options;

  await retry(
    async () => {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    },
    { maxAttempts, delay }
  );
}
