import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';

/**
 * My Account page object for customer login and account management.
 */
export class MyAccountPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/my-account`);
    await this.waitForPageReady();
    this.logger.step('Navigated to My Account page');
  }

  getUrlPattern(): string | RegExp {
    return /\/my-account\/?/;
  }

  /**
   * Check if user is logged in.
   */
  async isLoggedIn(): Promise<boolean> {
    // If we can see the dashboard or logout link, user is logged in
    const dashboard = this.locator('.woocommerce-MyAccount-navigation');
    const loginForm = this.locator(WooSelectors.LOGIN_FORM);
    
    const dashboardVisible = await dashboard.isVisible().catch(() => false);
    const loginFormVisible = await loginForm.isVisible().catch(() => false);
    
    return dashboardVisible && !loginFormVisible;
  }

  /**
   * Login with username and password.
   */
  async login(username: string, password: string): Promise<void> {
    this.logger.step(`Logging in as ${username}`);
    
    // Fill username
    const usernameInput = this.locator(WooSelectors.LOGIN_USERNAME);
    await this.safeFill(usernameInput, username);
    
    // Fill password
    const passwordInput = this.locator(WooSelectors.LOGIN_PASSWORD);
    await this.safeFill(passwordInput, password);
    
    // Check "Remember me" if available
    const rememberMe = this.locator('#rememberme');
    if (await rememberMe.isVisible()) {
      await this.safeCheck(rememberMe);
    }
    
    // Click login button
    const loginButton = this.getByRole('button', { name: /log in/i })
      .or(this.locator(WooSelectors.LOGIN_BUTTON));
    
    await loginButton.click();
    await this.waitForPageReady();
    
    // Verify login succeeded
    if (await this.hasLoginError()) {
      const error = await this.getLoginError();
      throw new Error(`Login failed: ${error}`);
    }
    
    this.logger.step('Login successful');
  }

  /**
   * Check if there's a login error.
   */
  async hasLoginError(): Promise<boolean> {
    const error = this.locator(WooSelectors.WOO_ERROR);
    return await error.count() > 0;
  }

  /**
   * Get login error message.
   */
  async getLoginError(): Promise<string> {
    const error = this.locator(`${WooSelectors.WOO_ERROR} li`);
    if (await error.count() > 0) {
      return this.getTrimmedText(error.first());
    }
    return '';
  }

  /**
   * Logout the current user.
   */
  async logout(): Promise<void> {
    const logoutLink = this.getByRole('link', { name: /log ?out/i });
    
    if (await logoutLink.count() > 0) {
      await logoutLink.first().click();
      await this.waitForPageReady();
      this.logger.step('Logged out');
    }
  }

  /**
   * Navigate to the Orders section.
   */
  async goToOrders(): Promise<void> {
    const ordersLink = this.getByRole('link', { name: /orders/i })
      .or(this.locator('.woocommerce-MyAccount-navigation-link--orders a'));
    
    await ordersLink.first().click();
    await this.waitForPageReady();
    this.logger.step('Navigated to Orders');
  }

  /**
   * Navigate to the Addresses section.
   */
  async goToAddresses(): Promise<void> {
    const addressesLink = this.getByRole('link', { name: /addresses/i })
      .or(this.locator('.woocommerce-MyAccount-navigation-link--edit-address a'));
    
    await addressesLink.first().click();
    await this.waitForPageReady();
    this.logger.step('Navigated to Addresses');
  }

  /**
   * Navigate to Account Details.
   */
  async goToAccountDetails(): Promise<void> {
    const accountLink = this.getByRole('link', { name: /account details/i })
      .or(this.locator('.woocommerce-MyAccount-navigation-link--edit-account a'));
    
    await accountLink.first().click();
    await this.waitForPageReady();
    this.logger.step('Navigated to Account Details');
  }

  /**
   * Get recent order numbers from the Orders page.
   */
  async getRecentOrderNumbers(): Promise<string[]> {
    const orders: string[] = [];
    const orderRows = this.locator('.woocommerce-orders-table__row');
    
    const count = await orderRows.count();
    for (let i = 0; i < count; i++) {
      const orderNumber = orderRows.nth(i).locator('.woocommerce-orders-table__cell-order-number');
      const text = await this.getTrimmedText(orderNumber);
      const number = text.replace(/[^0-9]/g, '');
      if (number) {
        orders.push(number);
      }
    }
    
    return orders;
  }

  /**
   * View a specific order by number.
   */
  async viewOrder(orderNumber: string): Promise<void> {
    const viewLink = this.locator(`.woocommerce-orders-table__row`)
      .filter({ hasText: orderNumber })
      .locator('a:has-text("View")');
    
    await viewLink.click();
    await this.waitForPageReady();
    this.logger.step(`Viewing order ${orderNumber}`);
  }

  /**
   * Check if saved address exists.
   */
  async hasSavedBillingAddress(): Promise<boolean> {
    await this.goToAddresses();
    const billingAddress = this.locator('.woocommerce-address-fields--billing address, .u-column1.woocommerce-Address address');
    const hasAddress = await billingAddress.count() > 0;
    const text = hasAddress ? await this.getTrimmedText(billingAddress.first()) : '';
    return hasAddress && text.length > 10; // Has some content
  }

  /**
   * Get the saved billing address.
   */
  async getSavedBillingAddress(): Promise<string> {
    await this.goToAddresses();
    const billingAddress = this.locator('.woocommerce-address-fields--billing address, .u-column1.woocommerce-Address address');
    if (await billingAddress.count() > 0) {
      return this.getTrimmedText(billingAddress.first());
    }
    return '';
  }

  /**
   * Get the welcome message (shows username).
   */
  async getWelcomeMessage(): Promise<string> {
    const welcome = this.locator('.woocommerce-MyAccount-content p');
    if (await welcome.count() > 0) {
      return this.getTrimmedText(welcome.first());
    }
    return '';
  }

  /**
   * Assert user is logged in.
   */
  async assertLoggedIn(): Promise<void> {
    expect(await this.isLoggedIn()).toBe(true);
    this.logger.assertion('User is logged in');
  }

  /**
   * Assert user is not logged in (on login page).
   */
  async assertNotLoggedIn(): Promise<void> {
    const loginForm = this.locator(WooSelectors.LOGIN_FORM);
    await expect(loginForm).toBeVisible();
    this.logger.assertion('User is not logged in');
  }
}
