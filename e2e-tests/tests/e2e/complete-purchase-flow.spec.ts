import { test, expect, Browser } from '@playwright/test';
import { 
  HomePage, 
  ProductPage, 
  CartPage, 
  CheckoutPage, 
  OrderReceivedPage 
} from '../../src/pages/storefront';
import { 
  AdminLoginPage, 
  AdminOrdersPage, 
  AdminOrderDetailsPage 
} from '../../src/pages/admin';
import { getEnvConfig } from '../../src/config/env-loader';
import { getTestProduct } from '../../src/test-data/products';
import { getBillingAddress, getTestCustomer } from '../../src/test-data/customers';
import { defaultPaymentMethod } from '../../src/test-data/payment-methods';
import { getTestCoupon, calculateDiscount } from '../../src/test-data/coupons';
import { 
  assertOrdersMatch, 
  verifyCompleteOrder,
  assertOrderStatus 
} from '../../src/assertions/order-assertions';
import { moneyEquals } from '../../src/utils/money';
import path from 'path';

const config = getEnvConfig();

test.describe('Complete Purchase Flow with Admin Verification', () => {
  
  /**
   * Complete E2E test: Purchase on storefront, then verify in admin
   */
  test('should complete purchase and verify order in admin', async ({ browser }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();
    
    // ========================================
    // STOREFRONT: Complete the purchase
    // ========================================
    const storefrontContext = await browser.newContext();
    const storefrontPage = await storefrontContext.newPage();
    
    const productPage = new ProductPage(storefrontPage);
    const cartPage = new CartPage(storefrontPage);
    const checkoutPage = new CheckoutPage(storefrontPage);
    const orderReceivedPage = new OrderReceivedPage(storefrontPage);

    console.log('📦 Starting storefront purchase flow...');

    // Add product to cart
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    
    // Go to checkout
    await cartPage.goto();
    const cartItems = await cartPage.getCartItems();
    expect(cartItems.length).toBeGreaterThan(0);
    
    const cartTotals = await cartPage.getCartTotals();
    console.log(`   Cart total: ${cartTotals.total}`);
    
    await cartPage.proceedToCheckout();

    // Fill checkout and place order
    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    
    const checkoutTotals = await checkoutPage.getOrderReviewTotals();
    console.log(`   Checkout total: ${checkoutTotals.total}`);
    
    await checkoutPage.placeOrder();

    // Wait for order received
    await checkoutPage.waitForOrderReceived();
    await orderReceivedPage.waitForPageLoad();

    // Get storefront order details
    const storefrontOrder = await orderReceivedPage.getOrderDetails();
    const orderNumber = storefrontOrder.orderNumber;
    
    console.log(`✅ Order placed: #${orderNumber}`);
    console.log(`   Items: ${storefrontOrder.items.length}`);
    console.log(`   Total: ${storefrontOrder.total}`);

    await storefrontContext.close();

    // ========================================
    // ADMIN: Verify the order
    // ========================================
    console.log('\n🔐 Verifying order in admin...');
    
    // Create admin context with stored auth or login fresh
    let adminContext;
    try {
      adminContext = await browser.newContext({
        storageState: path.join(__dirname, '../../.auth/admin.json'),
      });
    } catch {
      // Auth file doesn't exist, login manually
      adminContext = await browser.newContext();
      const loginPage = await adminContext.newPage();
      const adminLoginPage = new AdminLoginPage(loginPage);
      await adminLoginPage.goto();
      await adminLoginPage.login();
    }

    const adminPage = await adminContext.newPage();
    const adminOrdersPage = new AdminOrdersPage(adminPage);
    const adminOrderDetailsPage = new AdminOrderDetailsPage(adminPage);

    // Find the order
    await adminOrdersPage.goto();
    await adminOrdersPage.searchOrders(orderNumber);

    const orderExists = await adminOrdersPage.orderExists(orderNumber);
    expect(orderExists, `Order #${orderNumber} should exist in admin`).toBe(true);

    // Open order details
    await adminOrdersPage.openOrder(orderNumber);

    // Get admin order details
    const adminOrder = await adminOrderDetailsPage.getOrderDetails();
    
    console.log(`   Admin order #${adminOrder.orderNumber}`);
    console.log(`   Status: ${adminOrder.status}`);
    console.log(`   Items: ${adminOrder.items.length}`);
    console.log(`   Total: ${adminOrder.total}`);

    // ========================================
    // VERIFICATION: Compare storefront vs admin
    // ========================================
    console.log('\n🔍 Comparing storefront vs admin...');

    // Full order verification
    await verifyCompleteOrder(storefrontOrder, adminOrder, {
      expectedStatus: ['processing', 'completed', 'on-hold', 'pending'],
      expectedPaymentMethod: defaultPaymentMethod.title,
      expectedProducts: [{ name: product.name }],
    });

    // Detailed comparison
    const comparison = assertOrdersMatch(storefrontOrder, adminOrder);
    
    if (!comparison.passed) {
      console.error('❌ Order mismatch:', comparison.differences);
      
      // Take screenshot on mismatch
      await adminOrderDetailsPage.screenshotOrderDetails();
    }

    expect(comparison.passed, `Order verification failed:\n${comparison.differences.join('\n')}`).toBe(true);

    console.log('✅ Order verified successfully in admin');

    await adminContext.close();
  });

  test('should verify order with coupon discount', async ({ browser }) => {
    const product = getTestProduct('premium');
    const coupon = getTestCoupon('percent10');
    const billingAddress = getBillingAddress();

    // ========================================
    // STOREFRONT: Purchase with coupon
    // ========================================
    const storefrontContext = await browser.newContext();
    const storefrontPage = await storefrontContext.newPage();

    const productPage = new ProductPage(storefrontPage);
    const cartPage = new CartPage(storefrontPage);
    const checkoutPage = new CheckoutPage(storefrontPage);
    const orderReceivedPage = new OrderReceivedPage(storefrontPage);

    console.log('📦 Starting purchase with coupon...');

    // Add product
    await productPage.goToProduct(product.slug);
    await productPage.addToCart();

    // Apply coupon on cart
    await cartPage.goto();
    const cartTotalsBefore = await cartPage.getCartTotals();
    console.log(`   Cart before coupon: ${cartTotalsBefore.subtotal}`);

    await cartPage.applyCoupon(coupon.code);

    const cartTotalsAfter = await cartPage.getCartTotals();
    console.log(`   Cart after coupon: ${cartTotalsAfter.total}`);
    console.log(`   Discount applied: ${cartTotalsAfter.discount}`);

    // Complete checkout
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    await checkoutPage.placeOrder();

    await checkoutPage.waitForOrderReceived();
    await orderReceivedPage.waitForPageLoad();

    const storefrontOrder = await orderReceivedPage.getOrderDetails();
    const orderNumber = storefrontOrder.orderNumber;

    console.log(`✅ Order placed: #${orderNumber}`);
    console.log(`   Discount on order: ${storefrontOrder.discount}`);

    await storefrontContext.close();

    // ========================================
    // ADMIN: Verify discount
    // ========================================
    console.log('\n🔐 Verifying discount in admin...');

    const adminContext = await browser.newContext({
      storageState: path.join(__dirname, '../../.auth/admin.json'),
    }).catch(() => browser.newContext());

    const adminPage = await adminContext.newPage();
    const adminOrdersPage = new AdminOrdersPage(adminPage);
    const adminOrderDetailsPage = new AdminOrderDetailsPage(adminPage);

    // Check if we need to login
    const adminLoginPage = new AdminLoginPage(adminPage);
    await adminOrdersPage.goto().catch(async () => {
      await adminLoginPage.goto();
      await adminLoginPage.login();
      await adminOrdersPage.goto();
    });

    await adminOrdersPage.searchOrders(orderNumber);
    await adminOrdersPage.openOrder(orderNumber);

    const adminOrder = await adminOrderDetailsPage.getOrderDetails();

    // Verify discount in admin
    console.log(`   Admin discount: ${adminOrder.discount}`);

    expect(adminOrder.discount).toBeGreaterThan(0);
    expect(moneyEquals(storefrontOrder.discount, adminOrder.discount, 0.05)).toBe(true);

    // Calculate expected discount
    const expectedDiscount = calculateDiscount(coupon, adminOrder.subtotal);
    console.log(`   Expected discount (${coupon.amount}%): ${expectedDiscount}`);

    expect(moneyEquals(adminOrder.discount, expectedDiscount, 0.10)).toBe(true);

    console.log('✅ Coupon discount verified in admin');

    await adminContext.close();
  });

  test('should verify multiple items order', async ({ browser }) => {
    const products = [
      { product: getTestProduct('simple'), quantity: 2 },
      { product: getTestProduct('premium'), quantity: 1 },
    ];
    const billingAddress = getBillingAddress();

    // ========================================
    // STOREFRONT: Multi-item purchase
    // ========================================
    const storefrontContext = await browser.newContext();
    const storefrontPage = await storefrontContext.newPage();

    const productPage = new ProductPage(storefrontPage);
    const cartPage = new CartPage(storefrontPage);
    const checkoutPage = new CheckoutPage(storefrontPage);
    const orderReceivedPage = new OrderReceivedPage(storefrontPage);

    console.log('📦 Starting multi-item purchase...');

    // Add products
    for (const { product, quantity } of products) {
      await productPage.goToProduct(product.slug);
      await productPage.setQuantity(quantity);
      await productPage.addToCart();
      console.log(`   Added ${quantity}x ${product.name}`);
    }

    // Verify cart
    await cartPage.goto();
    const cartItems = await cartPage.getCartItems();
    expect(cartItems.length).toBe(products.length);

    // Complete checkout
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    await checkoutPage.placeOrder();

    await checkoutPage.waitForOrderReceived();
    await orderReceivedPage.waitForPageLoad();

    const storefrontOrder = await orderReceivedPage.getOrderDetails();
    const orderNumber = storefrontOrder.orderNumber;

    console.log(`✅ Order placed: #${orderNumber}`);
    console.log(`   Total items: ${storefrontOrder.items.length}`);

    await storefrontContext.close();

    // ========================================
    // ADMIN: Verify all items
    // ========================================
    console.log('\n🔐 Verifying items in admin...');

    const adminContext = await browser.newContext({
      storageState: path.join(__dirname, '../../.auth/admin.json'),
    }).catch(() => browser.newContext());

    const adminPage = await adminContext.newPage();
    const adminOrdersPage = new AdminOrdersPage(adminPage);
    const adminOrderDetailsPage = new AdminOrderDetailsPage(adminPage);

    const adminLoginPage = new AdminLoginPage(adminPage);
    await adminOrdersPage.goto().catch(async () => {
      await adminLoginPage.goto();
      await adminLoginPage.login();
      await adminOrdersPage.goto();
    });

    await adminOrdersPage.searchOrders(orderNumber);
    await adminOrdersPage.openOrder(orderNumber);

    const adminOrder = await adminOrderDetailsPage.getOrderDetails();

    // Verify all products present
    for (const { product, quantity } of products) {
      const adminItem = adminOrder.items.find(i => 
        i.name.toLowerCase().includes(product.name.toLowerCase())
      );

      expect(adminItem, `Product "${product.name}" should exist in admin order`).toBeTruthy();
      expect(adminItem?.quantity).toBe(quantity);

      console.log(`   ✓ ${product.name} x${quantity} verified`);
    }

    // Verify totals match
    expect(moneyEquals(storefrontOrder.total, adminOrder.total, 0.10)).toBe(true);

    console.log('✅ Multi-item order verified in admin');

    await adminContext.close();
  });

  test('should verify order status is appropriate', async ({ browser }) => {
    const product = getTestProduct('simple');
    const billingAddress = getBillingAddress();

    // Complete purchase
    const storefrontContext = await browser.newContext();
    const storefrontPage = await storefrontContext.newPage();

    const productPage = new ProductPage(storefrontPage);
    const cartPage = new CartPage(storefrontPage);
    const checkoutPage = new CheckoutPage(storefrontPage);
    const orderReceivedPage = new OrderReceivedPage(storefrontPage);

    await productPage.goToProduct(product.slug);
    await productPage.addToCart();
    await cartPage.goto();
    await cartPage.proceedToCheckout();
    await checkoutPage.fillBillingAddress(billingAddress);
    await checkoutPage.selectPaymentMethod(defaultPaymentMethod.id);
    await checkoutPage.placeOrder();

    await checkoutPage.waitForOrderReceived();
    await orderReceivedPage.waitForPageLoad();

    const storefrontOrder = await orderReceivedPage.getOrderDetails();
    const orderNumber = storefrontOrder.orderNumber;

    await storefrontContext.close();

    // Verify status in admin
    const adminContext = await browser.newContext({
      storageState: path.join(__dirname, '../../.auth/admin.json'),
    }).catch(() => browser.newContext());

    const adminPage = await adminContext.newPage();
    const adminOrdersPage = new AdminOrdersPage(adminPage);
    const adminOrderDetailsPage = new AdminOrderDetailsPage(adminPage);

    const adminLoginPage = new AdminLoginPage(adminPage);
    await adminOrdersPage.goto().catch(async () => {
      await adminLoginPage.goto();
      await adminLoginPage.login();
      await adminOrdersPage.goto();
    });

    await adminOrdersPage.searchOrders(orderNumber);
    await adminOrdersPage.openOrder(orderNumber);

    const status = await adminOrderDetailsPage.getOrderStatus();
    
    // Status should be appropriate for the payment method
    // COD typically results in "on-hold" or "processing"
    // BACS typically results in "on-hold"
    // Card payments typically result in "processing" or "completed"
    const expectedStatuses = ['processing', 'completed', 'on-hold', 'pending'];
    
    assertOrderStatus(status, expectedStatuses);

    console.log(`✅ Order #${orderNumber} status: ${status}`);

    await adminContext.close();
  });
});
