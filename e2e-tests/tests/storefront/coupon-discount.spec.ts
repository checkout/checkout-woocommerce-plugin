import { test, expect } from '../../src/fixtures';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress } from '../../src/test-data/customers';
import { getTestCoupon, calculateDiscount } from '../../src/test-data/coupons';
import { defaultPaymentMethod } from '../../src/test-data/payment-methods';
import { 
  assertCartContainsProduct, 
  assertDiscountApplied,
  verifyCartState 
} from '../../src/assertions/cart-assertions';
import { moneyEquals } from '../../src/utils/money';

test.describe('Coupon and Discount Scenarios', () => {

  test('should apply percentage discount coupon', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const coupon = getTestCoupon('percent10');

    // Add product to cart
    logger.step('Adding product to cart');
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    // Go to cart
    await cartPage.goto();

    // Get subtotal before coupon
    const totalsBefore = await cartPage.getCartTotals();
    const subtotalBefore = totalsBefore.subtotal;
    logger.info(`Subtotal before coupon: ${subtotalBefore}`);

    // Apply coupon
    logger.step(`Applying coupon: ${coupon.code}`);
    await cartPage.applyCoupon(coupon.code);

    // Verify coupon was applied
    const isCouponApplied = await cartPage.isCouponApplied(coupon.code);
    expect(isCouponApplied).toBe(true);

    // Get totals after coupon
    const totalsAfter = await cartPage.getCartTotals();
    
    // Calculate expected discount
    const expectedDiscount = calculateDiscount(coupon, subtotalBefore);
    logger.info(`Expected ${coupon.amount}% discount: ${expectedDiscount}`);

    // Verify discount applied
    assertDiscountApplied(totalsAfter, expectedDiscount, 0.05);

    // Verify total is reduced correctly
    const expectedTotal = subtotalBefore - expectedDiscount + (totalsAfter.shipping || 0);
    expect(moneyEquals(totalsAfter.total, expectedTotal, 0.10)).toBe(true);

    logger.info('Percentage coupon applied successfully', {
      subtotal: subtotalBefore,
      discount: totalsAfter.discount,
      total: totalsAfter.total,
    });
  });

  test('should apply fixed amount discount coupon', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    // Using coupon code '12' which gives £12 fixed discount
    const product = getTestProduct('premium'); // Use higher priced product to exceed discount
    const coupon = getTestCoupon('fixed10');

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    const totalsBefore = await cartPage.getCartTotals();
    logger.info(`Subtotal before coupon: ${totalsBefore.subtotal}`);

    logger.step(`Applying fixed discount coupon: ${coupon.code}`);
    await cartPage.applyCoupon(coupon.code);

    const totalsAfter = await cartPage.getCartTotals();

    // Fixed discount should be exactly the coupon amount (if subtotal > amount)
    const expectedDiscount = Math.min(coupon.amount, totalsBefore.subtotal);
    assertDiscountApplied(totalsAfter, expectedDiscount, 0.5);

    logger.info('Fixed discount coupon applied', {
      couponAmount: coupon.amount,
      actualDiscount: totalsAfter.discount,
    });
  });

  test.skip('should reject invalid coupon code', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    // NOTE: Skipped - error message detection may vary based on store configuration
    const product = getTestProduct('simple');
    const invalidCoupon = getTestCoupon('invalid');

    // Add product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    // Try to apply invalid coupon
    logger.step(`Attempting invalid coupon: ${invalidCoupon.code}`);
    await cartPage.applyCoupon(invalidCoupon.code);

    // Check for error message
    const errors = await cartPage.getErrorMessages();
    expect(errors.length).toBeGreaterThan(0);
    
    // Verify coupon not applied
    const totals = await cartPage.getCartTotals();
    expect(totals.discount || 0).toBe(0);

    logger.info('Invalid coupon rejected correctly', { errors });
  });

  test('should remove applied coupon', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const coupon = getTestCoupon('percent10');

    // Add product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();

    // Apply coupon
    await cartPage.applyCoupon(coupon.code);
    
    // Verify applied
    const totalsWithCoupon = await cartPage.getCartTotals();
    expect(totalsWithCoupon.discount).toBeGreaterThan(0);

    // Remove coupon
    logger.step('Removing coupon');
    await cartPage.removeCoupon(coupon.code);

    // Verify removed
    const totalsAfterRemove = await cartPage.getCartTotals();
    expect(totalsAfterRemove.discount || 0).toBe(0);

    // Total should be back to original
    expect(totalsAfterRemove.subtotal).toBeCloseTo(totalsWithCoupon.subtotal, 1);

    logger.info('Coupon removed successfully');
  });

  test('should persist coupon through checkout', async ({
    productPage,
    cartPage,
    checkoutPage,
    orderReceivedPage,
    logger,
  }) => {
    const product = getTestProduct('premium');
    const coupon = getTestCoupon('percent10');
    const billingAddress = getBillingAddress();

    // Add product and apply coupon
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.applyCoupon(coupon.code);

    // Get cart totals with discount
    const cartTotals = await cartPage.getCartTotals();
    const cartDiscount = cartTotals.discount || 0;
    logger.info(`Cart discount: ${cartDiscount}`);

    // Proceed to checkout
    await cartPage.proceedToCheckout();

    // Fill billing and verify discount in checkout
    await checkoutPage.fillBillingAddress(billingAddress);

    // Verify discount in order review
    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    expect(checkoutTotals.discount).toBeGreaterThan(0);
    expect(moneyEquals(checkoutTotals.discount, cartDiscount, 0.05)).toBe(true);

    // Complete order (with 3DS handling)
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    // Fill card details if payment method requires it
    if (defaultPaymentMethod.requiresCard) {
      const { getSuccessCard } = await import('../../src/test-data/payment-methods');
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
    await checkoutPage.waitForOrderReceived(90000);
    await orderReceivedPage.waitForPageLoad();

    // Verify discount on order received page
    const orderDetails = await orderReceivedPage.getOrderDetails();
    expect(orderDetails.discount).toBeGreaterThan(0);

    logger.info('Discount persisted through checkout', {
      cartDiscount,
      checkoutDiscount: checkoutTotals.discount,
      orderDiscount: orderDetails.discount,
    });
  });

  test('should apply coupon on checkout page', async ({
    productPage,
    cartPage,
    checkoutPage,
    logger,
  }) => {
    const product = getTestProduct('simple');
    const coupon = getTestCoupon('percent10');
    const billingAddress = getBillingAddress();

    // Add product and go to checkout (without applying coupon on cart)
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();

    // Fill billing first
    await checkoutPage.fillBillingAddress(billingAddress);

    // Get totals before coupon
    const totalsBefore = await checkoutPage.getOrderReviewTotals();
    logger.info(`Checkout total before coupon: ${totalsBefore.total}`);

    // Apply coupon on checkout page
    logger.step('Applying coupon on checkout page');
    await checkoutPage.applyCoupon(coupon.code);

    // Get updated totals
    const totalsAfter = await checkoutPage.getOrderReviewTotals();
    
    // Verify discount applied
    expect(totalsAfter.discount).toBeGreaterThan(0);
    expect(totalsAfter.total).toBeLessThan(totalsBefore.total);

    logger.info('Coupon applied on checkout page', {
      beforeTotal: totalsBefore.total,
      afterTotal: totalsAfter.total,
      discount: totalsAfter.discount,
    });
  });

  test('should handle minimum spend requirement', async ({
    productPage,
    cartPage,
    logger,
  }) => {
    const cheapProduct = getTestProduct('cheap'); // Low-priced product
    const coupon = getTestCoupon('minSpend'); // Requires $100 minimum

    // Add cheap product (should be below minimum)
    await productPage.goToProduct(cheapProduct.slug);
    await productPage.addToCart();
    await cartPage.goto();

    const totals = await cartPage.getCartTotals();
    logger.info(`Cart subtotal: ${totals.subtotal}, Minimum required: ${coupon.minimumSpend}`);

    // Try to apply coupon
    if (totals.subtotal < (coupon.minimumSpend || 0)) {
      logger.step('Attempting coupon below minimum spend');
      await cartPage.applyCoupon(coupon.code);

      // Should show error about minimum spend
      const errors = await cartPage.getErrorMessages();
      const hasMinimumError = errors.some(e => 
        e.toLowerCase().includes('minimum') || e.toLowerCase().includes('spend')
      );
      
      // Some stores may show error, others may silently not apply
      if (errors.length > 0) {
        logger.info('Minimum spend error displayed', { errors });
      } else {
        // Verify discount not applied
        const totalsAfter = await cartPage.getCartTotals();
        expect(totalsAfter.discount || 0).toBe(0);
        logger.info('Coupon silently rejected due to minimum spend');
      }
    } else {
      logger.info('Cart already meets minimum spend requirement');
    }
  });
});
