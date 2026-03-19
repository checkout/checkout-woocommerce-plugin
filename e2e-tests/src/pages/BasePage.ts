import { Page, Locator, expect } from '@playwright/test';
import { waitForPageReady, waitForWooAjax } from '../utils/waits';
import { Logger } from '../utils/logger';
import { retry } from '../utils/retry';

/**
 * Base page class providing common functionality for all page objects.
 * Implements robust, accessible locator helpers and safe interaction methods.
 */
export abstract class BasePage {
  protected readonly page: Page;
  protected readonly logger: Logger;

  constructor(page: Page) {
    this.page = page;
    this.logger = new Logger();
  }

  /**
   * Navigate to the page and wait for it to be ready.
   */
  abstract goto(): Promise<void>;

  /**
   * Get the expected URL pattern for this page.
   */
  abstract getUrlPattern(): string | RegExp;

  /**
   * Verify we're on the expected page.
   */
  async verifyOnPage(): Promise<void> {
    const pattern = this.getUrlPattern();
    if (typeof pattern === 'string') {
      await expect(this.page).toHaveURL(new RegExp(pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
    } else {
      await expect(this.page).toHaveURL(pattern);
    }
  }

  /**
   * Wait for page to be fully loaded and interactive.
   */
  protected async waitForPageReady(): Promise<void> {
    await waitForPageReady(this.page);
  }

  /**
   * Wait for WooCommerce AJAX operations to complete.
   */
  protected async waitForWooAjax(): Promise<void> {
    await waitForWooAjax(this.page);
  }

  // ============================================
  // LOCATOR HELPERS - Prefer accessible selectors
  // ============================================

  /**
   * Get element by role (most accessible).
   */
  protected getByRole(
    role: Parameters<Page['getByRole']>[0],
    options?: Parameters<Page['getByRole']>[1]
  ): Locator {
    return this.page.getByRole(role, options);
  }

  /**
   * Get element by label text.
   */
  protected getByLabel(text: string | RegExp, options?: { exact?: boolean }): Locator {
    return this.page.getByLabel(text, options);
  }

  /**
   * Get element by placeholder text.
   */
  protected getByPlaceholder(text: string | RegExp, options?: { exact?: boolean }): Locator {
    return this.page.getByPlaceholder(text, options);
  }

  /**
   * Get element by text content.
   */
  protected getByText(text: string | RegExp, options?: { exact?: boolean }): Locator {
    return this.page.getByText(text, options);
  }

  /**
   * Get element by test ID (data-testid attribute).
   */
  protected getByTestId(testId: string): Locator {
    return this.page.getByTestId(testId);
  }

  /**
   * Get element by CSS selector (use sparingly).
   */
  protected locator(selector: string): Locator {
    return this.page.locator(selector);
  }

  /**
   * Get element by ID.
   */
  protected getById(id: string): Locator {
    return this.page.locator(`#${id}`);
  }

  /**
   * Get element by name attribute.
   */
  protected getByName(name: string): Locator {
    return this.page.locator(`[name="${name}"]`);
  }

  // ============================================
  // SAFE INTERACTION METHODS
  // ============================================

  /**
   * Click with automatic waiting and optional retry.
   */
  protected async safeClick(
    locator: Locator,
    options: { force?: boolean; retries?: number } = {}
  ): Promise<void> {
    const { force = false, retries = 1 } = options;

    if (retries > 1) {
      await retry(
        async () => {
          await locator.click({ force });
        },
        { maxAttempts: retries, delay: 500 }
      );
    } else {
      await locator.click({ force });
    }
  }

  /**
   * Fill input with automatic waiting and clearing.
   */
  protected async safeFill(locator: Locator, value: string): Promise<void> {
    await locator.clear();
    await locator.fill(value);
  }

  /**
   * Fill input only if it's empty or has different value.
   */
  protected async fillIfEmpty(locator: Locator, value: string): Promise<void> {
    const currentValue = await locator.inputValue();
    if (currentValue !== value) {
      await this.safeFill(locator, value);
    }
  }

  /**
   * Select option from dropdown.
   */
  protected async safeSelect(locator: Locator, value: string): Promise<void> {
    await locator.selectOption(value);
  }

  /**
   * Check a checkbox if not already checked.
   */
  protected async safeCheck(locator: Locator): Promise<void> {
    if (!(await locator.isChecked())) {
      await locator.check();
    }
  }

  /**
   * Uncheck a checkbox if checked.
   */
  protected async safeUncheck(locator: Locator): Promise<void> {
    if (await locator.isChecked()) {
      await locator.uncheck();
    }
  }

  /**
   * Type text character by character (for inputs that need it).
   */
  protected async typeSlowly(locator: Locator, text: string, delay = 50): Promise<void> {
    await locator.clear();
    await locator.pressSequentially(text, { delay });
  }

  // ============================================
  // VISIBILITY AND STATE HELPERS
  // ============================================

  /**
   * Check if element is visible.
   */
  protected async isVisible(locator: Locator, timeout = 5000): Promise<boolean> {
    try {
      await locator.waitFor({ state: 'visible', timeout });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Wait for element to be visible.
   */
  protected async waitForVisible(locator: Locator, timeout = 10000): Promise<void> {
    await locator.waitFor({ state: 'visible', timeout });
  }

  /**
   * Wait for element to be hidden or removed.
   */
  protected async waitForHidden(locator: Locator, timeout = 10000): Promise<void> {
    await locator.waitFor({ state: 'hidden', timeout });
  }

  /**
   * Wait for element to be attached to DOM (visible or not).
   */
  protected async waitForAttached(locator: Locator, timeout = 10000): Promise<void> {
    await locator.waitFor({ state: 'attached', timeout });
  }

  // ============================================
  // TEXT AND VALUE EXTRACTION
  // ============================================

  /**
   * Get text content from element.
   */
  protected async getText(locator: Locator): Promise<string> {
    return (await locator.textContent()) ?? '';
  }

  /**
   * Get trimmed text content.
   */
  protected async getTrimmedText(locator: Locator): Promise<string> {
    const text = await this.getText(locator);
    return text.trim();
  }

  /**
   * Get input value.
   */
  protected async getValue(locator: Locator): Promise<string> {
    return locator.inputValue();
  }

  /**
   * Get attribute value.
   */
  protected async getAttribute(locator: Locator, attribute: string): Promise<string | null> {
    return locator.getAttribute(attribute);
  }

  // ============================================
  // SCREENSHOT HELPERS
  // ============================================

  /**
   * Take a screenshot of the current page.
   */
  async screenshot(name: string): Promise<Buffer> {
    return this.page.screenshot({
      path: `test-results/screenshots/${name}.png`,
      fullPage: true,
    });
  }

  /**
   * Take a screenshot of a specific element.
   */
  async screenshotElement(locator: Locator, name: string): Promise<Buffer> {
    return locator.screenshot({
      path: `test-results/screenshots/${name}.png`,
    });
  }

  // ============================================
  // NAVIGATION HELPERS
  // ============================================

  /**
   * Navigate to a URL.
   */
  protected async navigateTo(url: string): Promise<void> {
    await this.page.goto(url);
    await this.waitForPageReady();
  }

  /**
   * Reload the current page.
   */
  protected async reload(): Promise<void> {
    await this.page.reload();
    await this.waitForPageReady();
  }

  /**
   * Go back to previous page.
   */
  protected async goBack(): Promise<void> {
    await this.page.goBack();
    await this.waitForPageReady();
  }

  /**
   * Get current URL.
   */
  getCurrentUrl(): string {
    return this.page.url();
  }

  /**
   * Get the page title.
   */
  async getTitle(): Promise<string> {
    return this.page.title();
  }

  // ============================================
  // FRAME HANDLING
  // ============================================

  /**
   * Get iframe by name or URL pattern.
   */
  protected getFrame(nameOrUrl: string | RegExp) {
    if (typeof nameOrUrl === 'string') {
      return this.page.frameLocator(`iframe[name="${nameOrUrl}"]`);
    }
    return this.page.frameLocator(`iframe[src*="${nameOrUrl.source}"]`);
  }

  // ============================================
  // ASSERTIONS
  // ============================================

  /**
   * Assert element is visible.
   */
  protected async assertVisible(locator: Locator): Promise<void> {
    await expect(locator).toBeVisible();
  }

  /**
   * Assert element has specific text.
   */
  protected async assertText(locator: Locator, text: string | RegExp): Promise<void> {
    await expect(locator).toHaveText(text);
  }

  /**
   * Assert element contains text.
   */
  protected async assertContainsText(locator: Locator, text: string): Promise<void> {
    await expect(locator).toContainText(text);
  }

  /**
   * Assert input has specific value.
   */
  protected async assertValue(locator: Locator, value: string): Promise<void> {
    await expect(locator).toHaveValue(value);
  }

  /**
   * Assert element count.
   */
  protected async assertCount(locator: Locator, count: number): Promise<void> {
    await expect(locator).toHaveCount(count);
  }
}
