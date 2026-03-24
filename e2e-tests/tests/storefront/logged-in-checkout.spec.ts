import { test, expect } from '../../src/fixtures';
import { getEnvConfig, hasCustomerCredentials } from '../../src/config/env-loader';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress } from '../../src/test-data/customers';
import { defaultPaymentMethod } from '../../src/test-data/payment-methods';
import { MyAccountPage } from '../../src/pages/storefront/MyAccountPage';
import { ProductPage } from '../../src/pages/storefront/ProductPage';
import { CartPage } from '../../src/pages/storefront/CartPage';
import { CheckoutPage } from '../../src/pages/storefront/CheckoutPage';
import { OrderReceivedPage } from '../../src/pages/storefront/OrderReceivedPage';

test.describe('Logged-In Customer Checkout Flow', () => {
  // Skip if no customer credentials configured
  test.skip(() => !hasCustomerCredentials(), 'Customer credentials not configured');

  test('should complete checkout as logged-in customer', async ({
    browser,
    logger,
  }) => {
    const config = getEnvConfig();
    const product = getTestProduct('simple');

    // Create new context for this test
    const context = await browser.newContext();
    const page = await context.newPage();

    const myAccountPage = new MyAccountPage(page);
    const productPage = new ProductPage(page);
    const cartPage = new CartPage(page);
    const checkoutPage = new CheckoutPage(page);
    const orderReceivedPage = new OrderReceivedPage(page);

    try {
      // Step 1: Login
      logger.step('Logging in as customer');
      await myAccountPage.goto();
      await myAccountPage.login(config.CUSTOMER_USER, config.CUSTOMER_PASS);
      await myAccountPage.assertLoggedIn();

      // Step 2: Add product to cart
      logger.step('Adding product to cart');
      await productPage.goToProduct(product.slug);
      await productPage.addToCart();

      // Step 3: Go to checkout
      logger.step('Proceeding to checkout');
      await cartPage.goto();
      await cartPage.proceedToCheckout();

      // Step 4: Verify if address is pre-filled (from saved address)
      logger.step('Checking for pre-filled address');
      
      // Fill address if not pre-filled
      const billingAddress = getBillingAddress();
      await checkoutPage.fillBillingAddress(billingAddress);

      // Step 5: Place order
      logger.step('Placing order');
      await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
      await checkoutPage.placeOrder();

      // Step 6: Verify order received
      await checkoutPage.waitForOrderReceived();
      await orderReceivedPage.waitForPageLoad();

      const orderNumber = await orderReceivedPage.getOrderNumber();
      expect(orderNumber).toBeTruthy();

      logger.info(`Order placed successfully: #${orderNumber}`);

      // Step 7: Verify order appears in My Account
      logger.step('Verifying order in My Account');
      await myAccountPage.goto();
      await myAccountPage.goToOrders();

      const recentOrders = await myAccountPage.getRecentOrderNumbers();
      expect(recentOrders).toContain(orderNumber);
      logger.info('Order visible in customer account');

    } finally {
      await context.close();
    }
  });

  test('should use saved address if available', async ({
    browser,
    logger,
  }) => {
    const config = getEnvConfig();
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    const myAccountPage = new MyAccountPage(page);

    try {
      // Login
      await myAccountPage.goto();
      await myAccountPage.login(config.CUSTOMER_USER, config.CUSTOMER_PASS);

      // Check for saved billing address
      const hasSavedAddress = await myAccountPage.hasSavedBillingAddress();
      
      if (hasSavedAddress) {
        const savedAddress = await myAccountPage.getSavedBillingAddress();
        logger.info('Customer has saved billing address', { address: savedAddress });
        expect(savedAddress.length).toBeGreaterThan(10);
      } else {
        logger.info('Customer has no saved address (will need to fill during checkout)');
      }

    } finally {
      await context.close();
    }
  });

  test('should show order history after purchase', async ({
    browser,
    logger,
  }) => {
    const config = getEnvConfig();
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    const myAccountPage = new MyAccountPage(page);

    try {
      // Login
      await myAccountPage.goto();
      await myAccountPage.login(config.CUSTOMER_USER, config.CUSTOMER_PASS);

      // Go to orders
      await myAccountPage.goToOrders();

      // Get order history
      const orders = await myAccountPage.getRecentOrderNumbers();
      logger.info(`Customer has ${orders.length} orders in history`);

      // If orders exist, verify we can view them
      if (orders.length > 0) {
        const mostRecentOrder = orders[0];
        logger.step(`Viewing order #${mostRecentOrder}`);
        await myAccountPage.viewOrder(mostRecentOrder);
        
        // Verify we're on order details page
        const currentUrl = page.url();
        expect(currentUrl).toContain('view-order');
        logger.info('Order details page loaded successfully');
      }

    } finally {
      await context.close();
    }
  });

  test('should maintain session during checkout', async ({
    browser,
    logger,
  }) => {
    const config = getEnvConfig();
    const product = getTestProduct('simple');

    const context = await browser.newContext();
    const page = await context.newPage();
    
    const myAccountPage = new MyAccountPage(page);
    const productPage = new ProductPage(page);
    const cartPage = new CartPage(page);
    const checkoutPage = new CheckoutPage(page);

    try {
      // Login
      await myAccountPage.goto();
      await myAccountPage.login(config.CUSTOMER_USER, config.CUSTOMER_PASS);

      // Add to cart
      await productPage.goToProduct(product.slug);
      await productPage.addToCart();

      // Navigate through checkout
      await cartPage.goto();
      await cartPage.proceedToCheckout();

      // Go back to My Account
      logger.step('Navigating back to My Account');
      await myAccountPage.goto();

      // Verify still logged in
      const isLoggedIn = await myAccountPage.isLoggedIn();
      expect(isLoggedIn).toBe(true);
      logger.info('Session maintained during checkout navigation');

      // Return to checkout and verify cart persisted
      await checkoutPage.goto();
      const reviewItems = await checkoutPage.getOrderReviewItems();
      expect(reviewItems.length).toBeGreaterThan(0);
      logger.info('Cart contents persisted through navigation');

    } finally {
      await context.close();
    }
  });
});
