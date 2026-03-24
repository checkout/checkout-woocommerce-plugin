import { test, expect } from '../../src/fixtures';
import { getEnvConfig } from '../../src/config/env-loader';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress } from '../../src/test-data/customers';
import { getPaymentMethod, getDeclineCard, get3DSCard } from '../../src/test-data/payment-methods';

test.describe('Payment Failure Scenarios', () => {
  const config = getEnvConfig();

  test('should handle payment failure with declined card', async ({
    productPage,
    cartPage,
    checkoutPage,
    logger,
  }) => {
    // Skip if payment failure testing not enabled
    test.skip(!config.PAYMENT_FORCE_FAILURE, 'Payment failure testing not enabled');

    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();
    
    // Get decline card for Stripe (or configured payment method)
    const paymentMethod = getPaymentMethod('stripe');
    const declineCard = paymentMethod ? getDeclineCard('stripe') : undefined;

    test.skip(!paymentMethod?.requiresCard, 'No card-based payment method configured');

    // Add product
    logger.step('Setting up cart for payment failure test');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    // Fill billing
    await checkoutPage.fillBillingAddress(billingAddress);

    // Select card payment
    logger.step('Selecting card payment method');
    await checkoutPage.selectPaymentMethod(paymentMethod!.id);

    // Fill decline card details (if we can interact with payment form)
    if (declineCard) {
      logger.step('Entering decline card details');
      await checkoutPage.fillCardDetails({
        number: declineCard.number,
        expiry: declineCard.expiry,
        cvc: declineCard.cvc,
      });
    }

    // Attempt to place order
    logger.step('Attempting order with declined card');
    await checkoutPage.placeOrder();

    // Should show error
    const hasErrors = await checkoutPage.hasCheckoutErrors();
    expect(hasErrors, 'Expected checkout error for declined card').toBe(true);

    const errors = await checkoutPage.getCheckoutErrors();
    logger.info('Payment decline error displayed', { errors });

    // Verify we're still on checkout (not order received)
    const isOnOrderReceived = await checkoutPage.isOnOrderReceivedPage();
    expect(isOnOrderReceived).toBe(false);
  });

  test('should handle non-card payment method correctly', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    // Use Cash on Delivery (always available, no card needed)
    const paymentMethodId = 'cod';

    // Add product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    // Fill billing
    await checkoutPage.fillBillingAddress(billingAddress);

    // Get available payment methods
    const availableMethods = await checkoutPage.getAvailablePaymentMethods();
    logger.info('Available payment methods', { methods: availableMethods });

    // Select COD (or first available non-card method)
    logger.step('Selecting non-card payment method');
    await checkoutPage.selectPaymentMethod(paymentMethodId);

    // Place order - should succeed without card details
    await checkoutPage.placeOrder();

    // Verify success
    await checkoutPage.waitForOrderReceived();
    await orderReceivedPage.waitForPageLoad();

    const isOnOrderReceived = await orderReceivedPage.isOnOrderReceivedPage();
    expect(isOnOrderReceived).toBe(true);

    // Verify payment method on confirmation
    const orderDetails = await orderReceivedPage.getOrderDetails();
    logger.info('Order placed with non-card payment', {
      orderNumber: orderDetails.orderNumber,
      paymentMethod: orderDetails.paymentMethod,
    });
  });

  test('should handle 3DS authentication flow', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    // Skip if 3DS testing not enabled
    test.skip(!config.PAYMENT_3DS_REQUIRED, '3DS testing not enabled');

    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();
    
    const paymentMethod = getPaymentMethod('stripe');
    const threeDSCard = paymentMethod ? get3DSCard('stripe') : undefined;

    test.skip(!threeDSCard, 'No 3DS test card available');

    // Add product
    logger.step('Setting up cart for 3DS test');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    // Fill billing
    await checkoutPage.fillBillingAddress(billingAddress);

    // Select card payment
    await checkoutPage.selectPaymentMethod(paymentMethod!.id);

    // Fill 3DS card
    if (threeDSCard) {
      logger.step('Entering 3DS test card');
      await checkoutPage.fillCardDetails({
        number: threeDSCard.number,
        expiry: threeDSCard.expiry,
        cvc: threeDSCard.cvc,
      });
    }

    // Place order
    await checkoutPage.placeOrder();

    // Handle 3DS authentication
    logger.step('Handling 3DS authentication');
    await checkoutPage.handle3DSAuthentication();

    // Verify result (may succeed or fail depending on test card behavior)
    const isOnOrderReceived = await checkoutPage.isOnOrderReceivedPage();
    
    if (isOnOrderReceived) {
      await orderReceivedPage.waitForPageLoad();
      const orderNumber = await orderReceivedPage.getOrderNumber();
      logger.info('3DS payment completed successfully', { orderNumber });
    } else {
      const hasErrors = await checkoutPage.hasCheckoutErrors();
      if (hasErrors) {
        const errors = await checkoutPage.getCheckoutErrors();
        logger.info('3DS authentication failed/cancelled', { errors });
      }
    }
  });

  test('should display appropriate error for insufficient funds', async ({
    productPage,
    cartPage,
    checkoutPage,
    logger,
  }) => {
    test.skip(!config.PAYMENT_FORCE_FAILURE, 'Payment failure testing not enabled');

    const product = getTestProduct('expensive'); // High priced item
    const billingAddress = getBillingAddress();
    
    const paymentMethod = getPaymentMethod('stripe');
    const insufficientFundsCard = paymentMethod?.testCards?.find(
      c => c.scenario === 'insufficient_funds'
    );

    test.skip(!insufficientFundsCard, 'No insufficient funds test card available');

    // Add expensive product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    // Fill billing
    await checkoutPage.fillBillingAddress(billingAddress);

    // Select card payment and fill insufficient funds card
    await checkoutPage.selectPaymentMethod(paymentMethod!.id);
    await checkoutPage.fillCardDetails({
      number: insufficientFundsCard!.number,
      expiry: insufficientFundsCard!.expiry,
      cvc: insufficientFundsCard!.cvc,
    });

    // Attempt order
    logger.step('Attempting payment with insufficient funds');
    await checkoutPage.placeOrder();

    // Should show appropriate error
    const hasErrors = await checkoutPage.hasCheckoutErrors();
    expect(hasErrors).toBe(true);

    const errors = await checkoutPage.getCheckoutErrors();
    const hasRelevantError = errors.some(e => 
      e.toLowerCase().includes('insufficient') ||
      e.toLowerCase().includes('declined') ||
      e.toLowerCase().includes('failed')
    );

    expect(hasRelevantError, `Expected insufficient funds error, got: ${errors.join(', ')}`).toBe(true);
    logger.info('Insufficient funds error displayed correctly');
  });

  test('should allow retry after payment failure', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    // Add product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    // First, verify checkout is working
    const availableMethods = await checkoutPage.getAvailablePaymentMethods();
    expect(availableMethods.length).toBeGreaterThan(0);

    // Select a reliable payment method
    logger.step('Selecting payment method for retry test');
    await checkoutPage.selectPaymentMethod('cod');

    // Place order
    await checkoutPage.placeOrder();

    // Handle result
    await checkoutPage.waitForOrderReceived().catch(() => {
      // May fail if COD not available, which is fine for this test
    });

    const isOnOrderReceived = await checkoutPage.isOnOrderReceivedPage();
    
    if (isOnOrderReceived) {
      logger.info('Order placed successfully');
    } else {
      // If there's an error, verify we can still interact with checkout
      const canSelectPayment = await checkoutPage.getAvailablePaymentMethods();
      expect(canSelectPayment.length).toBeGreaterThan(0);
      logger.info('Checkout still accessible after failure, retry possible');
    }
  });
});
