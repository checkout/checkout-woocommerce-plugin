import { expect } from '@playwright/test';
import { OrderReceivedDetails } from '../pages/storefront/OrderReceivedPage';
import { AdminOrderDetails } from '../pages/admin/AdminOrderDetailsPage';
import { moneyEquals } from '../utils/money';
import { Logger } from '../utils/logger';

const logger = new Logger();

/**
 * Compare order details between storefront and admin.
 * This is crucial for verifying payment and order processing correctness.
 */
export interface OrderComparisonResult {
  passed: boolean;
  differences: string[];
}

/**
 * Assert that storefront order matches admin order.
 */
export function assertOrdersMatch(
  storefrontOrder: OrderReceivedDetails,
  adminOrder: AdminOrderDetails,
  options: { tolerance?: number } = {}
): OrderComparisonResult {
  const { tolerance = 0.01 } = options;
  const differences: string[] = [];

  // Compare order numbers
  if (storefrontOrder.orderNumber !== adminOrder.orderNumber) {
    differences.push(
      `Order number mismatch: storefront="${storefrontOrder.orderNumber}", admin="${adminOrder.orderNumber}"`
    );
  }

  // Compare totals
  if (!moneyEquals(storefrontOrder.total, adminOrder.total, tolerance)) {
    differences.push(
      `Total mismatch: storefront=${storefrontOrder.total}, admin=${adminOrder.total}`
    );
  }

  // Compare subtotals
  if (!moneyEquals(storefrontOrder.subtotal, adminOrder.subtotal, tolerance)) {
    differences.push(
      `Subtotal mismatch: storefront=${storefrontOrder.subtotal}, admin=${adminOrder.subtotal}`
    );
  }

  // Compare shipping
  if (!moneyEquals(storefrontOrder.shipping, adminOrder.shipping, tolerance)) {
    differences.push(
      `Shipping mismatch: storefront=${storefrontOrder.shipping}, admin=${adminOrder.shipping}`
    );
  }

  // Compare tax
  if (!moneyEquals(storefrontOrder.tax, adminOrder.tax, tolerance)) {
    differences.push(
      `Tax mismatch: storefront=${storefrontOrder.tax}, admin=${adminOrder.tax}`
    );
  }

  // Compare discount
  if (!moneyEquals(storefrontOrder.discount, adminOrder.discount, tolerance)) {
    differences.push(
      `Discount mismatch: storefront=${storefrontOrder.discount}, admin=${adminOrder.discount}`
    );
  }

  // Compare item counts
  if (storefrontOrder.items.length !== adminOrder.items.length) {
    differences.push(
      `Item count mismatch: storefront=${storefrontOrder.items.length}, admin=${adminOrder.items.length}`
    );
  }

  // Compare individual items
  for (const storefrontItem of storefrontOrder.items) {
    const adminItem = adminOrder.items.find(
      item => item.name.toLowerCase().includes(storefrontItem.name.toLowerCase()) ||
              storefrontItem.name.toLowerCase().includes(item.name.toLowerCase())
    );

    if (!adminItem) {
      differences.push(`Item "${storefrontItem.name}" not found in admin order`);
      continue;
    }

    if (storefrontItem.quantity !== adminItem.quantity) {
      differences.push(
        `Quantity mismatch for "${storefrontItem.name}": storefront=${storefrontItem.quantity}, admin=${adminItem.quantity}`
      );
    }

    if (!moneyEquals(storefrontItem.total, adminItem.lineTotal, tolerance)) {
      differences.push(
        `Line total mismatch for "${storefrontItem.name}": storefront=${storefrontItem.total}, admin=${adminItem.lineTotal}`
      );
    }
  }

  const passed = differences.length === 0;

  if (!passed) {
    logger.error('Order comparison failed', { differences });
  } else {
    logger.info('Order comparison passed');
  }

  return { passed, differences };
}

/**
 * Assert order has expected status.
 */
export function assertOrderStatus(
  actualStatus: string,
  expectedStatuses: string | string[]
): void {
  const expected = Array.isArray(expectedStatuses) ? expectedStatuses : [expectedStatuses];
  const normalizedActual = actualStatus.toLowerCase().replace('wc-', '');
  const normalizedExpected = expected.map(s => s.toLowerCase().replace('wc-', ''));

  expect(
    normalizedExpected.some(exp => normalizedActual.includes(exp)),
    `Expected order status to be one of [${normalizedExpected.join(', ')}], but got "${normalizedActual}"`
  ).toBe(true);
}

/**
 * Assert order total is correct given line items, shipping, tax, and discounts.
 */
export function assertCalculatedTotal(
  order: {
    items: Array<{ total: number } | { lineTotal: number }>;
    shipping: number;
    tax: number;
    discount: number;
    total: number;
  },
  tolerance = 0.01
): void {
  const itemsTotal = order.items.reduce((sum, item) => {
    const lineTotal = 'total' in item ? item.total : item.lineTotal;
    return sum + lineTotal;
  }, 0);

  const calculatedTotal = itemsTotal + order.shipping + order.tax - order.discount;

  expect(
    moneyEquals(calculatedTotal, order.total, tolerance),
    `Calculated total (${calculatedTotal}) doesn't match order total (${order.total})`
  ).toBe(true);
}

/**
 * Assert order contains all expected products.
 */
export function assertOrderContainsProducts(
  orderItems: Array<{ name: string; quantity?: number }>,
  expectedProducts: Array<{ name: string; quantity?: number }>
): void {
  for (const expected of expectedProducts) {
    const found = orderItems.find(item =>
      item.name.toLowerCase().includes(expected.name.toLowerCase())
    );

    expect(found, `Expected order to contain product "${expected.name}"`).toBeTruthy();

    if (expected.quantity && found) {
      expect(
        found.quantity,
        `Expected "${expected.name}" quantity to be ${expected.quantity}`
      ).toBe(expected.quantity);
    }
  }
}

/**
 * Assert payment method matches.
 */
export function assertPaymentMethod(
  actualMethod: string,
  expectedMethod: string
): void {
  expect(
    actualMethod.toLowerCase().includes(expectedMethod.toLowerCase()),
    `Expected payment method to contain "${expectedMethod}", got "${actualMethod}"`
  ).toBe(true);
}

/**
 * Assert billing email matches (case insensitive).
 */
export function assertBillingEmail(
  actualEmail: string,
  expectedEmail: string
): void {
  expect(
    actualEmail.toLowerCase(),
    `Expected billing email "${expectedEmail}", got "${actualEmail}"`
  ).toBe(expectedEmail.toLowerCase());
}

/**
 * Full order verification combining all assertions.
 */
export async function verifyCompleteOrder(
  storefrontOrder: OrderReceivedDetails,
  adminOrder: AdminOrderDetails,
  expectations: {
    expectedStatus?: string | string[];
    expectedPaymentMethod?: string;
    expectedProducts?: Array<{ name: string; quantity?: number }>;
    tolerance?: number;
  } = {}
): Promise<void> {
  const { 
    expectedStatus = ['processing', 'completed', 'on-hold'],
    expectedPaymentMethod,
    expectedProducts,
    tolerance = 0.01,
  } = expectations;

  // Compare storefront vs admin
  const comparison = assertOrdersMatch(storefrontOrder, adminOrder, { tolerance });
  expect(comparison.passed, `Order mismatch:\n${comparison.differences.join('\n')}`).toBe(true);

  // Verify status
  assertOrderStatus(adminOrder.status, expectedStatus);

  // Verify payment method if specified
  if (expectedPaymentMethod) {
    assertPaymentMethod(adminOrder.paymentMethod, expectedPaymentMethod);
  }

  // Verify products if specified
  if (expectedProducts) {
    assertOrderContainsProducts(adminOrder.items, expectedProducts);
  }

  // Verify calculated total
  assertCalculatedTotal(adminOrder, tolerance);

  logger.info('Complete order verification passed', { 
    orderNumber: adminOrder.orderNumber,
    status: adminOrder.status,
    total: adminOrder.total,
  });
}
