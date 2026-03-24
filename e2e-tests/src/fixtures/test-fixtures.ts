import { test as base, Page, BrowserContext } from '@playwright/test';
import { 
  HomePage, 
  ProductPage, 
  CartPage, 
  CheckoutPage, 
  OrderReceivedPage,
  MyAccountPage 
} from '../pages/storefront';
import { 
  AdminLoginPage, 
  AdminOrdersPage, 
  AdminOrderDetailsPage 
} from '../pages/admin';
import { getEnvConfig, hasCustomerCredentials } from '../config/env-loader';
import { Logger, captureConsoleErrors, captureNetworkErrors, attachErrorsToReport } from '../utils/logger';
import { getTestCustomer, getBillingAddress, TestCustomer } from '../test-data/customers';
import { getTestProduct, TestProduct } from '../test-data/products';
import { TestCoupon, getTestCoupon } from '../test-data/coupons';
import { AddressData } from '../pages/storefront/CheckoutPage';

/**
 * Extended test fixtures for WooCommerce E2E testing.
 */
export interface WooCommerceFixtures {
  // Page objects - Storefront
  homePage: HomePage;
  productPage: ProductPage;
  cartPage: CartPage;
  checkoutPage: CheckoutPage;
  orderReceivedPage: OrderReceivedPage;
  myAccountPage: MyAccountPage;
  
  // Page objects - Admin
  adminLoginPage: AdminLoginPage;
  adminOrdersPage: AdminOrdersPage;
  adminOrderDetailsPage: AdminOrderDetailsPage;
  
  // Test data
  testCustomer: TestCustomer;
  billingAddress: AddressData;
  simpleProduct: TestProduct;
  
  // Utilities
  logger: Logger;
  
  // Authenticated contexts
  authenticatedCustomerPage: Page;
  adminPage: Page;
}

/**
 * Create the base test with all fixtures.
 */
export const test = base.extend<WooCommerceFixtures>({
  // Logger with error capturing
  logger: async ({ page }, use, testInfo) => {
    const logger = new Logger();
    logger.attachTestInfo(testInfo);
    
    // Capture errors
    const consoleErrors = captureConsoleErrors(page);
    const networkErrors = captureNetworkErrors(page);
    
    await use(logger);
    
    // On test failure, attach logs and errors
    if (testInfo.status !== 'passed') {
      await logger.attachLogsToReport();
      await attachErrorsToReport(testInfo, consoleErrors, networkErrors);
    }
  },

  // Storefront page objects
  homePage: async ({ page }, use) => {
    await use(new HomePage(page));
  },

  productPage: async ({ page }, use) => {
    await use(new ProductPage(page));
  },

  cartPage: async ({ page }, use) => {
    await use(new CartPage(page));
  },

  checkoutPage: async ({ page }, use) => {
    await use(new CheckoutPage(page));
  },

  orderReceivedPage: async ({ page }, use) => {
    await use(new OrderReceivedPage(page));
  },

  myAccountPage: async ({ page }, use) => {
    await use(new MyAccountPage(page));
  },

  // Admin page objects
  adminLoginPage: async ({ page }, use) => {
    await use(new AdminLoginPage(page));
  },

  adminOrdersPage: async ({ page }, use) => {
    await use(new AdminOrdersPage(page));
  },

  adminOrderDetailsPage: async ({ page }, use) => {
    await use(new AdminOrderDetailsPage(page));
  },

  // Test data fixtures
  testCustomer: async ({}, use) => {
    const customer = getTestCustomer();
    await use(customer);
  },

  billingAddress: async ({}, use) => {
    const address = getBillingAddress();
    await use(address);
  },

  simpleProduct: async ({}, use) => {
    const product = getTestProduct('simple');
    await use(product);
  },

  // Authenticated customer page (if credentials available)
  authenticatedCustomerPage: async ({ browser }, use) => {
    const config = getEnvConfig();
    
    if (!hasCustomerCredentials()) {
      // Return a new page without auth if no credentials
      const context = await browser.newContext();
      const page = await context.newPage();
      await use(page);
      await context.close();
      return;
    }

    // Create authenticated context
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Login
    const myAccountPage = new MyAccountPage(page);
    await myAccountPage.goto();
    await myAccountPage.login(config.CUSTOMER_USER, config.CUSTOMER_PASS);
    
    await use(page);
    await context.close();
  },

  // Admin authenticated page (uses storage state from setup)
  adminPage: async ({ browser }, use) => {
    const config = getEnvConfig();
    
    // Try to use stored auth state
    let context: BrowserContext;
    try {
      context = await browser.newContext({
        storageState: '.auth/admin.json',
      });
    } catch {
      // No stored state, create fresh and login
      context = await browser.newContext();
      const page = await context.newPage();
      const adminLoginPage = new AdminLoginPage(page);
      await adminLoginPage.goto();
      await adminLoginPage.login();
    }
    
    const page = await context.newPage();
    await use(page);
    await context.close();
  },
});

/**
 * Expect from base test.
 */
export { expect } from '@playwright/test';

/**
 * Page object factory for custom page creation.
 */
export function createPageObjects(page: Page) {
  return {
    homePage: new HomePage(page),
    productPage: new ProductPage(page),
    cartPage: new CartPage(page),
    checkoutPage: new CheckoutPage(page),
    orderReceivedPage: new OrderReceivedPage(page),
    myAccountPage: new MyAccountPage(page),
    adminLoginPage: new AdminLoginPage(page),
    adminOrdersPage: new AdminOrdersPage(page),
    adminOrderDetailsPage: new AdminOrderDetailsPage(page),
  };
}
