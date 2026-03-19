import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';

/**
 * WordPress Admin Login page object.
 */
export class AdminLoginPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(`${this.config.ADMIN_URL}/wp-login.php`);
    await this.waitForPageReady();
    this.logger.step('Navigated to admin login page');
  }

  getUrlPattern(): string | RegExp {
    return /wp-login\.php/;
  }

  /**
   * Check if we're on the login page.
   */
  async isOnLoginPage(): Promise<boolean> {
    const loginForm = this.locator(WooSelectors.ADMIN_LOGIN_FORM);
    return await loginForm.isVisible().catch(() => false);
  }

  /**
   * Check if we're already logged in (redirected to dashboard).
   */
  async isLoggedIn(): Promise<boolean> {
    const adminMenu = this.locator(WooSelectors.ADMIN_MENU);
    return await adminMenu.isVisible().catch(() => false);
  }

  /**
   * Login to WordPress admin.
   */
  async login(username?: string, password?: string): Promise<void> {
    const user = username || this.config.ADMIN_USER;
    const pass = password || this.config.ADMIN_PASS;

    this.logger.step(`Logging in as admin: ${user}`);

    // Check if already logged in
    if (await this.isLoggedIn()) {
      this.logger.info('Already logged in');
      return;
    }

    // Fill username
    const usernameInput = this.locator(WooSelectors.ADMIN_USERNAME);
    await this.safeFill(usernameInput, user);

    // Fill password
    const passwordInput = this.locator(WooSelectors.ADMIN_PASSWORD);
    await this.safeFill(passwordInput, pass);

    // Check "Remember Me"
    const rememberMe = this.locator('#rememberme');
    if (await rememberMe.isVisible()) {
      await this.safeCheck(rememberMe);
    }

    // Click login
    const loginButton = this.locator(WooSelectors.ADMIN_SUBMIT);
    await loginButton.click();

    // Wait for login to complete
    await this.page.waitForURL(/wp-admin/, { timeout: 30000 });
    await this.waitForPageReady();

    // Verify login succeeded
    if (await this.hasLoginError()) {
      const error = await this.getLoginError();
      throw new Error(`Admin login failed: ${error}`);
    }

    this.logger.step('Admin login successful');
  }

  /**
   * Check if there's a login error.
   */
  async hasLoginError(): Promise<boolean> {
    const error = this.locator('#login_error');
    return await error.count() > 0;
  }

  /**
   * Get login error message.
   */
  async getLoginError(): Promise<string> {
    const error = this.locator('#login_error');
    if (await error.count() > 0) {
      return this.getTrimmedText(error);
    }
    return '';
  }

  /**
   * Logout from admin.
   */
  async logout(): Promise<void> {
    // Hover over the admin bar user menu
    const userMenu = this.locator('#wp-admin-bar-my-account');
    await userMenu.hover();

    // Click logout
    const logoutLink = this.locator('#wp-admin-bar-logout a');
    await logoutLink.click();

    await this.waitForPageReady();
    this.logger.step('Logged out from admin');
  }

  /**
   * Get the logged-in user display name.
   */
  async getLoggedInUser(): Promise<string> {
    const userDisplay = this.locator('#wp-admin-bar-my-account .display-name');
    if (await userDisplay.count() > 0) {
      return this.getTrimmedText(userDisplay);
    }
    return '';
  }

  /**
   * Assert admin is logged in.
   */
  async assertLoggedIn(): Promise<void> {
    expect(await this.isLoggedIn()).toBe(true);
    this.logger.assertion('Admin is logged in');
  }
}
