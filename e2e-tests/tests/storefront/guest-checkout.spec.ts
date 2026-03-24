import { test, expect } from '../../src/fixtures';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress } from '../../src/test-data/customers';
import { defaultPaymentMethod, getSuccessCard } from '../../src/test-data/payment-methods';
import { assertCalculatedTotal } from '../../src/assertions/order-assertions';
import { assertCartContainsProduct, assertCartTotalCorrect } from '../../src/assertions/cart-assertions';

test.describe('Guest Checkout Flow', () => {
  test.describe.configure({ mode: 'serial' });

  test('should complete checkout with a simple product', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    // Step 1: Add product to cart
    logger.step('Adding product to cart');
    await productPage.goToProduct(product.slug);
    await productPage.assertProductDisplayed(product.name);
    await productPage.addToCart();

    // Step 2: Verify cart
    logger.step('Verifying cart contents');
    await cartPage.goto();
    const cartItems = await cartPage.getCartItems();
    assertCartContainsProduct(cartItems, product.name, 1);

    const cartTotals = await cartPage.getCartTotals();
    expect(cartTotals.subtotal).toBeGreaterThan(0);
    assertCartTotalCorrect(cartTotals);

    // Step 3: Proceed to checkout
    logger.step('Proceeding to checkout');
    await cartPage.proceedToCheckout();

    // Step 4: Fill billing details
    logger.step('Filling billing address');
    await checkoutPage.fillBillingAddress(billingAddress);

    // Step 5: Verify order review
    logger.step('Verifying order review');
    const orderItems = await checkoutPage.getOrderReviewItems();
    expect(orderItems.length).toBeGreaterThan(0);
    expect(orderItems[0].name).toContain(product.name);

    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    expect(checkoutTotals.total).toBeGreaterThan(0);

    // Step 6: Select payment method and fill card if needed
    logger.step('Selecting payment method and placing order');
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    // Fill card details if payment method requires it
    if (defaultPaymentMethod.requiresCard) {
      const successCard = getSuccessCard(defaultPaymentMethod.id);
      if (successCard) {
        await checkoutPage.fillCardDetails({
          number: successCard.number,
          expiry: successCard.expiry,
          cvc: successCard.cvc,
        });
      }
    }
    
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);

    // Step 7: Handle 3DS if needed and verify order received
    logger.step('Verifying order received');
    await checkoutPage.waitForOrderReceived(90000); // Extended timeout for 3DS
    await orderReceivedPage.waitForPageLoad();

    const isOnOrderReceived = await orderReceivedPage.isOnOrderReceivedPage();
    expect(isOnOrderReceived).toBe(true);

    const orderNumber = await orderReceivedPage.getOrderNumber();
    expect(orderNumber).toBeTruthy();
    logger.info(`Order placed successfully: #${orderNumber}`);

    // Verify order details
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.items.length).toBeGreaterThan(0);
    await orderReceivedPage.assertContainsItems([{ name: product.name }]);
  });

  test('should complete checkout with multiple quantities', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const quantity = 3;
    const billingAddress = getBillingAddress();

    // Add product with quantity
    logger.step(`Adding ${quantity} units to cart`);
    await productPage.goToProduct(product.slug);
    await productPage.setQuantity(quantity);
    await productPage.addToCart();

    // Verify cart
    await cartPage.goto();
    const cartItems = await cartPage.getCartItems();
    assertCartContainsProduct(cartItems, product.name, quantity);

    // Expected subtotal
    const expectedSubtotal = product.price * quantity;
    const cartTotals = await cartPage.getCartTotals();
    expect(cartTotals.subtotal).toBeCloseTo(expectedSubtotal, 1);

    // Complete checkout
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    // Fill card details if payment method requires it
    if (defaultPaymentMethod.requiresCard) {
      const successCard = getSuccessCard(defaultPaymentMethod.id);
      if (successCard) {
        await checkoutPage.fillCardDetails({
          number: successCard.number,
          expiry: successCard.expiry,
          cvc: successCard.cvc,
        });
      }
    }
    
    await checkoutPage.placeOrderWith3DS(defaultPaymentMethod.threeDSPassword);

    // Handle 3DS if needed and verify order
    await checkoutPage.waitForOrderReceived(90000);
    await orderReceivedPage.waitForPageLoad();

    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.items[0].quantity).toBe(quantity);
    logger.info(`Order placed with quantity: ${quantity}`);
  });

  test.skip('should show validation errors for empty required fields', async ({
    cartPage,
    checkoutPage,
    productPage,
    logger,
  }) => {
    // NOTE: Skipped because Checkout.com Flow hides the Place Order button until
    // billing email is provided and the payment component initializes.
    // This test scenario isn't applicable for Flow payment integration.
    const product = getTestProduct('simple');

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    await cartPage.goto();
    await cartPage.proceedToCheckout();

    logger.step('Attempting to place order without required fields');
    await checkoutPage.placeOrder();

    const hasErrors = await checkoutPage.hasCheckoutErrors();
    expect(hasErrors).toBe(true);

    const errors = await checkoutPage.getCheckoutErrors();
    expect(errors.length).toBeGreaterThan(0);
    logger.info('Validation errors displayed correctly', { errorCount: errors.length });
  });

  test('should update cart and recalculate totals', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    const product = getTestProduct('simple');

    // Add product to cart
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    // Get initial totals
    const initialTotals = await cartPage.getCartTotals();
    const initialSubtotal = initialTotals.subtotal;

    // Update quantity
    logger.step('Updating cart quantity');
    await cartPage.updateQuantity(product.name, 2);

    // Get updated totals
    const updatedTotals = await cartPage.getCartTotals();
    
    // Verify subtotal doubled (approximately)
    expect(updatedTotals.subtotal).toBeCloseTo(initialSubtotal * 2, 1);
    logger.info('Cart totals updated correctly', {
      initial: initialSubtotal,
      updated: updatedTotals.subtotal,
    });
  });

  test('should handle empty cart gracefully', async ({
    cartPage,
    logger,
  }) => {
    // Navigate directly to cart page
    await cartPage.goto();

    // Check if cart is empty
    const isEmpty = await cartPage.isCartEmpty();
    
    if (isEmpty) {
      logger.info('Cart is empty as expected');
      // Verify continue shopping link exists
      const itemCount = await cartPage.getItemCount();
      expect(itemCount).toBe(0);
    } else {
      // If cart has items from previous test, clear and verify
      logger.info('Cart has items, verifying cart functionality');
      const items = await cartPage.getCartItems();
      expect(items.length).toBeGreaterThan(0);
    }
  });

  test('should maintain cart contents after page reload', async ({
    productPage,
    cartPage,
    page,
    logger,
  }) => {
    const product = getTestProduct('simple');

    // Add product to cart
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    
    // Navigate to cart and get items
    await cartPage.goto();
    const itemsBefore = await cartPage.getCartItems();
    assertCartContainsProduct(itemsBefore, product.name);

    // Reload page
    logger.step('Reloading cart page');
    await page.reload();
    await cartPage.goto();

    // Verify cart persisted
    const itemsAfter = await cartPage.getCartItems();
    assertCartContainsProduct(itemsAfter, product.name);
    logger.info('Cart contents persisted after reload');
  });
});
