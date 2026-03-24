import { test, expect } from '@playwright/test';
import { AdminOrdersPage, AdminOrderDetailsPage } from '../../src/pages/admin';
import { getEnvConfig } from '../../src/config/env-loader';
import { assertOrderStatus, assertPaymentMethod } from '../../src/assertions/order-assertions';
import path from 'path';

// Use admin authentication
test.use({
  storageState: path.join(__dirname, '../../.auth/admin.json'),
});

const config = getEnvConfig();

test.describe('WooCommerce Admin Order Verification', () => {
  let adminOrdersPage: AdminOrdersPage;
  let adminOrderDetailsPage: AdminOrderDetailsPage;

  test.beforeEach(async ({ page }) => {
    adminOrdersPage = new AdminOrdersPage(page);
    adminOrderDetailsPage = new AdminOrderDetailsPage(page);
  });

  test('should display orders list', async ({ page }) => {
    await adminOrdersPage.goto();

    // Verify we're on the orders page
    await expect(page).toHaveURL(/post_type=shop_order|wc-orders/);

    // Get orders
    const orders = await adminOrdersPage.getOrders();
    console.log(`Found ${orders.length} orders on current page`);

    // If there are orders, verify structure
    if (orders.length > 0) {
      const firstOrder = orders[0];
      expect(firstOrder.orderNumber).toBeTruthy();
      expect(firstOrder.status).toBeTruthy();
      
      console.log('Sample order:', {
        number: firstOrder.orderNumber,
        status: firstOrder.status,
        total: firstOrder.total,
      });
    }
  });

  test('should search orders by order number', async () => {
    await adminOrdersPage.goto();

    // Get first available order
    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available for search test');

    const orderNumber = orders[0].orderNumber;

    // Search for the order
    await adminOrdersPage.searchOrders(orderNumber);

    // Verify order found
    const searchResults = await adminOrdersPage.getOrders();
    const found = searchResults.some(o => o.orderNumber === orderNumber);
    expect(found).toBe(true);

    console.log(`Successfully found order #${orderNumber} via search`);
  });

  test('should filter orders by status', async () => {
    await adminOrdersPage.goto();

    // Try filtering by processing status
    await adminOrdersPage.filterByStatus('wc-processing');

    // Get filtered orders
    const orders = await adminOrdersPage.getOrders();
    
    // All orders should have processing status
    for (const order of orders) {
      expect(order.status.toLowerCase()).toContain('processing');
    }

    console.log(`Found ${orders.length} processing orders`);
  });

  test('should view order details', async () => {
    await adminOrdersPage.goto();

    // Get an order to view
    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available for details test');

    const orderNumber = orders[0].orderNumber;

    // Open order details
    await adminOrdersPage.openOrder(orderNumber);

    // Verify on order details page
    const detailsOrderNumber = await adminOrderDetailsPage.getOrderNumber();
    expect(detailsOrderNumber).toBe(orderNumber);

    // Get order details
    const details = await adminOrderDetailsPage.getOrderDetails();
    
    console.log('Order details:', {
      orderNumber: details.orderNumber,
      status: details.status,
      total: details.total,
      items: details.items.length,
      paymentMethod: details.paymentMethod,
    });

    // Verify basic details are present
    expect(details.orderNumber).toBeTruthy();
    expect(details.total).toBeGreaterThanOrEqual(0);
  });

  test('should display correct line items', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    // Open first order
    await adminOrdersPage.openOrder(orders[0].orderNumber);

    // Get line items
    const items = await adminOrderDetailsPage.getLineItems();

    // Verify items exist and have required fields
    if (items.length > 0) {
      for (const item of items) {
        expect(item.name).toBeTruthy();
        expect(item.quantity).toBeGreaterThan(0);
        expect(item.lineTotal).toBeGreaterThanOrEqual(0);

        console.log('Line item:', {
          name: item.name,
          qty: item.quantity,
          unitPrice: item.unitPrice,
          lineTotal: item.lineTotal,
        });
      }
    }
  });

  test('should display correct order totals', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    // Get totals
    const details = await adminOrderDetailsPage.getOrderDetails();

    console.log('Order totals:', {
      subtotal: details.subtotal,
      shipping: details.shipping,
      tax: details.tax,
      discount: details.discount,
      total: details.total,
    });

    // Basic total calculation validation
    const calculatedTotal = 
      details.subtotal + 
      details.shipping + 
      details.tax - 
      details.discount;

    // Allow small variance due to rounding
    expect(Math.abs(calculatedTotal - details.total)).toBeLessThan(0.10);
  });

  test('should display billing information', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    // Get billing info
    const details = await adminOrderDetailsPage.getOrderDetails();

    // Verify billing details exist
    expect(details.billingAddress).toBeTruthy();
    
    console.log('Billing:', {
      name: details.billingName,
      email: details.billingEmail,
      address: details.billingAddress?.substring(0, 50) + '...',
    });
  });

  test('should display payment method', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    const paymentMethod = await adminOrderDetailsPage.getPaymentMethod();
    
    // Payment method should exist (may be empty string for some payment types)
    expect(typeof paymentMethod).toBe('string');
    
    console.log(`Payment method: ${paymentMethod || 'Not specified'}`);
  });

  test('should display order notes', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    const notes = await adminOrderDetailsPage.getOrderNotes();
    
    console.log(`Found ${notes.length} order notes`);
    
    if (notes.length > 0) {
      console.log('Sample note:', notes[0].substring(0, 100));
    }
  });

  test('should add order note', async () => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    // Get initial note count
    const notesBefore = await adminOrderDetailsPage.getOrderNotes();
    const initialCount = notesBefore.length;

    // Add a test note
    const testNote = `E2E Test Note - ${new Date().toISOString()}`;
    await adminOrderDetailsPage.addOrderNote(testNote);

    // Verify note was added
    const notesAfter = await adminOrderDetailsPage.getOrderNotes();
    
    // Note should be in the list
    const noteAdded = notesAfter.some(note => note.includes('E2E Test Note'));
    expect(noteAdded).toBe(true);

    console.log('Order note added successfully');
  });

  test('should capture screenshot on order mismatch', async ({ page }) => {
    await adminOrdersPage.goto();

    const orders = await adminOrdersPage.getOrders();
    test.skip(orders.length === 0, 'No orders available');

    await adminOrdersPage.openOrder(orders[0].orderNumber);

    // Take screenshot for documentation
    const screenshot = await adminOrderDetailsPage.screenshotOrderDetails();
    expect(screenshot).toBeTruthy();

    console.log('Order details screenshot captured');
  });
});
