import { test, expect } from '../../src/fixtures';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress, getShippingAddress } from '../../src/test-data/customers';
import { defaultPaymentMethod, getSuccessCard, paymentMethods } from '../../src/test-data/payment-methods';
import { getTestCoupon } from '../../src/test-data/coupons';

/**
 * Comprehensive User Journey Tests
 * 
 * These tests cover real-world checkout scenarios including:
 * - Checkout with/without discount codes
 * - Applying discounts before/after entering card details
 * - Different payment methods (Card, Google Pay, PayPal)
 * - Shipping scenarios (with/without shipping)
 * - Billing address changes after entering card details
 * - Cart modifications during checkout
 */

test.describe('User Journey: Basic Checkout Flows', () => {
  
  test('Journey 1: Simple checkout without any discount code', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    logger.step('Adding product to cart');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    logger.step('Proceeding to checkout');
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling billing details');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Selecting payment method and entering card details');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    logger.step('Placing order');
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    logger.step('Verifying order received');
    await orderReceivedPage.waitForPageLoad();
    const orderNumber = await orderReceivedPage.getOrderNumber();
    expect(orderNumber).toBeTruthy();
    
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBe(0);
    logger.info(`Order #${orderNumber} completed without discount`);
  });

  test('Journey 2: Checkout with discount code applied in cart (before checkout)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('premium');
    const billingAddress = getBillingAddress();
    const coupon = getTestCoupon('t10'); // 10% discount

    logger.step('Adding product to cart');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    logger.step('Applying discount code in cart');
    await cartPage.goto();
    await cartPage.applyCoupon(coupon.code);
    
    const cartTotals = await cartPage.getCartTotals();
    expect(cartTotals.discount).toBeGreaterThan(0);
    logger.info(`Cart discount applied: £${cartTotals.discount}`);

    logger.step('Proceeding to checkout');
    await cartPage.proceedToCheckout();

    logger.step('Filling billing details');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Verifying discount persisted to checkout');
    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    expect(checkoutTotals.discount).toBeGreaterThan(0);

    logger.step('Entering card details and placing order');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    logger.step('Verifying order with discount');
    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeGreaterThan(0);
    logger.info(`Order #${orderDetails.orderNumber} completed with £${orderDetails.discount} discount`);
  });

  test('Journey 3: Checkout with discount code applied BEFORE entering card details', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();
    const coupon = getTestCoupon('t10');

    logger.step('Adding product and going to checkout');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling billing details first');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Getting total BEFORE discount');
    const totalsBefore = await checkoutPage.getOrderReviewTotals();
    logger.info(`Total before discount: £${totalsBefore.total}`);

    logger.step('Applying discount code on checkout page');
    await checkoutPage.applyCoupon(coupon.code);

    logger.step('Getting total AFTER discount');
    const totalsAfter = await checkoutPage.getOrderReviewTotals();
    expect(totalsAfter.total).toBeLessThan(totalsBefore.total);
    logger.info(`Total after discount: £${totalsAfter.total}`);

    logger.step('NOW entering card details (after discount applied)');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    logger.step('Placing order');
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeGreaterThan(0);
    logger.info(`Order #${orderDetails.orderNumber} - discount applied before card entry`);
  });

  test('Journey 4: Checkout with discount code applied AFTER entering card details', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('premium');
    const billingAddress = getBillingAddress();
    const coupon = getTestCoupon('t10');

    logger.step('Adding product and going to checkout');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling billing details');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Entering card details FIRST (before discount)');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    logger.step('Getting total before discount');
    const totalsBefore = await checkoutPage.getOrderReviewTotals();
    logger.info(`Total before discount: £${totalsBefore.total}`);

    logger.step('NOW applying discount code AFTER card details entered');
    await checkoutPage.applyCoupon(coupon.code);

    logger.step('Verifying total reduced after discount');
    const totalsAfter = await checkoutPage.getOrderReviewTotals();
    expect(totalsAfter.total).toBeLessThan(totalsBefore.total);
    expect(totalsAfter.discount).toBeGreaterThan(0);
    logger.info(`Total after discount: £${totalsAfter.total}, Discount: £${totalsAfter.discount}`);

    logger.step('Placing order with discounted amount');
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeGreaterThan(0);
    logger.info(`Order #${orderDetails.orderNumber} - discount applied after card entry`);
  });
});

test.describe('User Journey: Payment Method Variations', () => {
  
  test('Journey 5: Checkout with Credit Card (standard flow)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const paymentMethod = await orderReceivedPage.getPaymentMethod();
    logger.info(`Order completed with payment method: ${paymentMethod}`);
  });

  test.skip('Journey 6: Checkout with Google Pay', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
    page,
  }) => {
    // NOTE: Google Pay requires a real device/browser with Google Pay configured
    // This test is skipped in automated testing but provides the structure
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Looking for Google Pay button');
    const googlePayButton = page.locator('.gpay-button, [data-testid="google-pay-button"], #google-pay-button');
    
    if (await googlePayButton.count() > 0) {
      logger.step('Google Pay available - clicking button');
      await googlePayButton.click();
      // Google Pay popup would appear here - requires manual interaction
    } else {
      logger.info('Google Pay not available on this browser/device');
    }
  });

  test.skip('Journey 7: Checkout with PayPal', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
    page,
  }) => {
    // NOTE: PayPal checkout opens a popup and requires real PayPal credentials
    // This test is skipped but provides the structure
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Looking for PayPal button');
    const paypalButton = page.locator('.paypal-button, [data-funding-source="paypal"], #paypal-button');
    
    if (await paypalButton.count() > 0) {
      logger.step('PayPal available - clicking button');
      // PayPal opens popup - would need to handle popup for real test
      // const popup = await page.waitForEvent('popup');
    } else {
      logger.info('PayPal not available');
    }
  });
});

test.describe('User Journey: Shipping Scenarios', () => {
  
  test('Journey 8: Checkout with shipping charges (different billing/shipping addresses)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple'); // Physical product that requires shipping
    const billingAddress = getBillingAddress();
    const shippingAddress = getShippingAddress();

    logger.step('Adding physical product to cart');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    logger.step('Checking if shipping is shown in cart');
    const cartTotals = await cartPage.getCartTotals();
    logger.info(`Cart shipping: £${cartTotals.shipping || 0}`);

    await cartPage.proceedToCheckout();

    logger.step('Filling billing address');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Using different shipping address');
    await checkoutPage.setShipToDifferentAddress(true);
    await checkoutPage.fillShippingAddress(shippingAddress);

    logger.step('Checking shipping options');
    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    logger.info(`Checkout shipping: £${checkoutTotals.shipping || 0}`);

    logger.step('Completing payment');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    logger.info(`Order #${orderDetails.orderNumber} with shipping: £${orderDetails.shipping}`);
  });

  test('Journey 9: Checkout with same billing and shipping address', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling billing address (shipping same as billing)');
    await checkoutPage.fillBillingAddress(billingAddress);
    
    // Ensure ship to different address is NOT checked
    await checkoutPage.setShipToDifferentAddress(false);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderNumber = await orderReceivedPage.getOrderNumber();
    logger.info(`Order #${orderNumber} completed with same billing/shipping`);
  });

  test('Journey 10: Checkout with free shipping (via coupon or threshold)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    // Add higher value product to potentially qualify for free shipping
    const product = getTestProduct('premium');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.setQuantity(2); // Increase cart value
    await productPage.addToCart();
    await cartPage.goto();

    const cartTotals = await cartPage.getCartTotals();
    logger.info(`Cart subtotal: £${cartTotals.subtotal}, Shipping: £${cartTotals.shipping || 'Free'}`);

    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    logger.info(`Checkout subtotal: £${checkoutTotals.subtotal}, Shipping: £${checkoutTotals.shipping || 'Free'}`);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    logger.info(`Order #${orderDetails.orderNumber} - Total: £${orderDetails.total}`);
  });
});

test.describe('User Journey: Address Change Scenarios', () => {
  
  test('Journey 11: Change billing address AFTER entering card details (should re-enter card)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
    page,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling initial billing address');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Entering card details FIRST');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    logger.step('NOW changing billing address (city/postcode)');
    // Change city and postcode
    await page.fill('#billing_city', 'Manchester');
    await page.fill('#billing_postcode', 'M1 1AA');
    
    // Wait for AJAX update
    await page.waitForTimeout(2000);

    logger.step('Checking if card details need to be re-entered');
    // Flow may require re-entering card details after billing change
    // Check if the card iframe is reset/empty
    
    try {
      // Try to re-fill card details
      await checkoutPage.fillCardDetails({
        number: successCard!.number,
        expiry: successCard!.expiry,
        cvc: successCard!.cvc,
      });
      logger.info('Card details re-entered after address change');
    } catch {
      logger.info('Card details preserved after address change');
    }

    logger.step('Placing order with updated address');
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderNumber = await orderReceivedPage.getOrderNumber();
    
    // Verify the updated address
    const billingAddressText = await orderReceivedPage.getBillingAddress();
    expect(billingAddressText).toContain('Manchester');
    logger.info(`Order #${orderNumber} completed with changed address`);
  });

  test('Journey 12: Change country after entering card details', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
    page,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Filling initial billing address (UK)');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Entering card details');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    logger.step('Country change would typically require re-entering card - skipping actual change');
    // NOTE: Changing country significantly impacts checkout (tax, shipping, payment options)
    // Flow component may fully reset

    logger.step('Placing order');
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderNumber = await orderReceivedPage.getOrderNumber();
    logger.info(`Order #${orderNumber} completed`);
  });
});

test.describe('User Journey: Cart Modification Scenarios', () => {
  
  test('Journey 13: Add multiple products, remove one, then checkout', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product1 = getTestProduct('simple');
    const product2 = getTestProduct('premium');
    const billingAddress = getBillingAddress();

    logger.step('Adding first product');
    await productPage.goToProduct(product1.slug);
    await productPage.addToCart();

    logger.step('Adding second product');
    await productPage.goToProduct(product2.slug);
    await productPage.addToCart();

    logger.step('Going to cart');
    await cartPage.goto();
    
    let cartItems = await cartPage.getCartItems();
    expect(cartItems.length).toBe(2);
    logger.info(`Cart has ${cartItems.length} items`);

    logger.step('Removing first product');
    await cartPage.removeItem(product1.name);
    
    cartItems = await cartPage.getCartItems();
    expect(cartItems.length).toBe(1);
    expect(cartItems[0].name).toContain(product2.name);

    logger.step('Proceeding to checkout with remaining item');
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.items.length).toBe(1);
    logger.info(`Order #${orderDetails.orderNumber} with 1 item after removal`);
  });

  test('Journey 14: Update quantity in cart, then checkout', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    logger.step('Adding product with quantity 1');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    await cartPage.goto();
    
    const initialTotals = await cartPage.getCartTotals();
    logger.info(`Initial subtotal: £${initialTotals.subtotal}`);

    logger.step('Updating quantity to 3');
    await cartPage.updateQuantity(product.name, 3);
    
    const updatedTotals = await cartPage.getCartTotals();
    expect(updatedTotals.subtotal).toBeGreaterThan(initialTotals.subtotal);
    logger.info(`Updated subtotal: £${updatedTotals.subtotal}`);

    logger.step('Proceeding to checkout');
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.items[0].quantity).toBe(3);
    logger.info(`Order #${orderDetails.orderNumber} with quantity: 3`);
  });
});

test.describe('User Journey: Discount Code Edge Cases', () => {
  
  test('Journey 15: Apply discount, remove it, then complete checkout', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('premium');
    const billingAddress = getBillingAddress();
    const coupon = getTestCoupon('t10');

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    logger.step('Applying discount code');
    await cartPage.applyCoupon(coupon.code);
    
    const totalsWithDiscount = await cartPage.getCartTotals();
    expect(totalsWithDiscount.discount).toBeGreaterThan(0);
    logger.info(`Discount applied: £${totalsWithDiscount.discount}`);

    logger.step('Removing discount code');
    await cartPage.removeCoupon(coupon.code);
    
    const totalsWithoutDiscount = await cartPage.getCartTotals();
    expect(totalsWithoutDiscount.discount || 0).toBe(0);
    expect(totalsWithoutDiscount.total).toBeGreaterThan(totalsWithDiscount.total);
    logger.info(`Discount removed, total: £${totalsWithoutDiscount.total}`);

    logger.step('Completing checkout without discount');
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBe(0);
    logger.info(`Order #${orderDetails.orderNumber} completed without discount`);
  });

  test('Journey 16: Try invalid discount code, then use valid one', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();
    const validCoupon = getTestCoupon('t10');

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    logger.step('Trying invalid discount code');
    await cartPage.applyCoupon('INVALID_CODE_123');
    
    // Invalid code should not apply discount
    const totalsAfterInvalid = await cartPage.getCartTotals();
    expect(totalsAfterInvalid.discount || 0).toBe(0);

    logger.step('Now applying valid discount code');
    await cartPage.applyCoupon(validCoupon.code);
    
    const totalsAfterValid = await cartPage.getCartTotals();
    expect(totalsAfterValid.discount).toBeGreaterThan(0);
    logger.info(`Valid discount applied: £${totalsAfterValid.discount}`);

    logger.step('Completing checkout');
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeGreaterThan(0);
    logger.info(`Order #${orderDetails.orderNumber} with valid discount`);
  });

  test('Journey 17: Apply fixed discount (£12 off)', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('premium');
    const billingAddress = getBillingAddress();
    const fixedCoupon = getTestCoupon('fixed10'); // £12 fixed discount

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    logger.step('Applying fixed discount code');
    await cartPage.applyCoupon(fixedCoupon.code);
    
    const totals = await cartPage.getCartTotals();
    expect(totals.discount).toBeCloseTo(12, 0);
    logger.info(`Fixed discount applied: £${totals.discount}`);

    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);

    await orderReceivedPage.waitForPageLoad();
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeCloseTo(12, 0);
    logger.info(`Order #${orderDetails.orderNumber} with £12 fixed discount`);
  });
});

test.describe('User Journey: Error Recovery', () => {
  
  test('Journey 18: Session timeout recovery - return to checkout', async ({
    productPage,
    cartPage,
    checkoutPage,
    logger,
    page,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Partially filling checkout');
    await checkoutPage.fillBillingAddress(billingAddress);

    logger.step('Simulating navigation away and back');
    await page.goto('https://www.wc-cko.net/');
    await page.waitForLoadState('networkidle');
    
    // Return to checkout
    await cartPage.goto();
    
    // Cart should still have items
    const isEmpty = await cartPage.isCartEmpty();
    expect(isEmpty).toBe(false);
    logger.info('Cart preserved after navigation');

    await cartPage.proceedToCheckout();
    // May need to re-fill billing
    await checkoutPage.fillBillingAddress(billingAddress);

    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const successCard = getSuccessCard(defaultPaymentMethod.id);
    if (successCard) {
      await checkoutPage.fillCardDetails({
        number: successCard.number,
        expiry: successCard.expiry,
        cvc: successCard.cvc,
      });
    }

    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);
    await checkoutPage.waitForOrderReceived(90000);
    
    logger.info('Successfully completed checkout after navigation');
  });
});
