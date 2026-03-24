import { expect } from '@playwright/test';
import { CartItem, CartTotals } from '../pages/storefront/CartPage';
import { moneyEquals, calculateExpectedTotal, OrderLineItem } from '../utils/money';
import { Logger } from '../utils/logger';

const logger = new Logger();

/**
 * Assert cart contains specific product.
 */
export function assertCartContainsProduct(
  cartItems: CartItem[],
  productName: string,
  expectedQuantity?: number
): void {
  const item = cartItems.find(i => 
    i.name.toLowerCase().includes(productName.toLowerCase())
  );

  expect(item, `Expected cart to contain "${productName}"`).toBeTruthy();

  if (expectedQuantity && item) {
    expect(
      item.quantity,
      `Expected "${productName}" quantity to be ${expectedQuantity}, got ${item.quantity}`
    ).toBe(expectedQuantity);
  }

  logger.assertion(`Cart contains "${productName}"${expectedQuantity ? ` x${expectedQuantity}` : ''}`);
}

/**
 * Assert cart does not contain a product.
 */
export function assertCartDoesNotContain(
  cartItems: CartItem[],
  productName: string
): void {
  const item = cartItems.find(i => 
    i.name.toLowerCase().includes(productName.toLowerCase())
  );

  expect(item, `Expected cart to NOT contain "${productName}"`).toBeFalsy();
  logger.assertion(`Cart does not contain "${productName}"`);
}

/**
 * Assert cart item subtotal is correct.
 */
export function assertCartItemSubtotal(
  item: CartItem,
  tolerance = 0.01
): void {
  const expectedSubtotal = item.price * item.quantity;
  expect(
    moneyEquals(item.subtotal, expectedSubtotal, tolerance),
    `Expected item subtotal ${expectedSubtotal}, got ${item.subtotal}`
  ).toBe(true);
  logger.assertion(`Cart item subtotal correct: ${item.subtotal}`);
}

/**
 * Assert cart subtotal equals sum of item subtotals.
 */
export function assertCartSubtotalCorrect(
  cartItems: CartItem[],
  cartTotals: CartTotals,
  tolerance = 0.01
): void {
  const calculatedSubtotal = cartItems.reduce((sum, item) => sum + item.subtotal, 0);
  
  expect(
    moneyEquals(calculatedSubtotal, cartTotals.subtotal, tolerance),
    `Expected cart subtotal ${calculatedSubtotal}, got ${cartTotals.subtotal}`
  ).toBe(true);
  logger.assertion(`Cart subtotal correct: ${cartTotals.subtotal}`);
}

/**
 * Assert cart total is correctly calculated.
 */
export function assertCartTotalCorrect(
  cartTotals: CartTotals,
  tolerance = 0.01
): void {
  const calculatedTotal = 
    cartTotals.subtotal + 
    (cartTotals.shipping || 0) + 
    (cartTotals.tax || 0) - 
    (cartTotals.discount || 0);

  expect(
    moneyEquals(calculatedTotal, cartTotals.total, tolerance),
    `Expected cart total ${calculatedTotal}, got ${cartTotals.total}`
  ).toBe(true);
  logger.assertion(`Cart total correct: ${cartTotals.total}`);
}

/**
 * Assert discount was applied correctly.
 */
export function assertDiscountApplied(
  cartTotals: CartTotals,
  expectedDiscount: number,
  tolerance = 0.01
): void {
  expect(
    cartTotals.discount !== undefined && cartTotals.discount > 0,
    'Expected discount to be applied'
  ).toBe(true);

  if (expectedDiscount > 0) {
    expect(
      moneyEquals(cartTotals.discount || 0, expectedDiscount, tolerance),
      `Expected discount ${expectedDiscount}, got ${cartTotals.discount}`
    ).toBe(true);
  }
  logger.assertion(`Discount applied: ${cartTotals.discount}`);
}

/**
 * Assert percent discount is calculated correctly.
 */
export function assertPercentDiscount(
  subtotal: number,
  discount: number,
  percentOff: number,
  tolerance = 0.01
): void {
  const expectedDiscount = subtotal * (percentOff / 100);
  expect(
    moneyEquals(discount, expectedDiscount, tolerance),
    `Expected ${percentOff}% discount (${expectedDiscount}), got ${discount}`
  ).toBe(true);
  logger.assertion(`Percent discount correct: ${percentOff}% = ${discount}`);
}

/**
 * Assert cart is empty.
 */
export function assertCartEmpty(cartItems: CartItem[]): void {
  expect(cartItems.length, 'Expected cart to be empty').toBe(0);
  logger.assertion('Cart is empty');
}

/**
 * Assert cart has specific number of unique items.
 */
export function assertCartItemCount(
  cartItems: CartItem[],
  expectedCount: number
): void {
  expect(
    cartItems.length,
    `Expected ${expectedCount} unique items in cart, got ${cartItems.length}`
  ).toBe(expectedCount);
  logger.assertion(`Cart has ${expectedCount} unique items`);
}

/**
 * Assert total quantity in cart.
 */
export function assertTotalQuantity(
  cartItems: CartItem[],
  expectedQuantity: number
): void {
  const totalQuantity = cartItems.reduce((sum, item) => sum + item.quantity, 0);
  expect(
    totalQuantity,
    `Expected total quantity ${expectedQuantity}, got ${totalQuantity}`
  ).toBe(expectedQuantity);
  logger.assertion(`Total cart quantity: ${expectedQuantity}`);
}

/**
 * Assert shipping is displayed.
 */
export function assertShippingDisplayed(cartTotals: CartTotals): void {
  expect(
    cartTotals.shipping !== undefined,
    'Expected shipping to be displayed in cart totals'
  ).toBe(true);
  logger.assertion(`Shipping displayed: ${cartTotals.shipping}`);
}

/**
 * Assert free shipping is applied.
 */
export function assertFreeShipping(cartTotals: CartTotals): void {
  expect(
    cartTotals.shipping === 0 || cartTotals.shipping === undefined,
    `Expected free shipping, got ${cartTotals.shipping}`
  ).toBe(true);
  logger.assertion('Free shipping applied');
}

/**
 * Full cart verification.
 */
export function verifyCartState(
  cartItems: CartItem[],
  cartTotals: CartTotals,
  expectations: {
    expectedProducts?: Array<{ name: string; quantity?: number }>;
    expectedSubtotal?: number;
    expectedTotal?: number;
    expectedDiscount?: number;
    tolerance?: number;
  }
): void {
  const {
    expectedProducts,
    expectedSubtotal,
    expectedTotal,
    expectedDiscount,
    tolerance = 0.01,
  } = expectations;

  // Verify products
  if (expectedProducts) {
    for (const product of expectedProducts) {
      assertCartContainsProduct(cartItems, product.name, product.quantity);
    }
  }

  // Verify subtotal
  if (expectedSubtotal !== undefined) {
    expect(
      moneyEquals(cartTotals.subtotal, expectedSubtotal, tolerance),
      `Expected subtotal ${expectedSubtotal}, got ${cartTotals.subtotal}`
    ).toBe(true);
  }

  // Verify total
  if (expectedTotal !== undefined) {
    expect(
      moneyEquals(cartTotals.total, expectedTotal, tolerance),
      `Expected total ${expectedTotal}, got ${cartTotals.total}`
    ).toBe(true);
  }

  // Verify discount
  if (expectedDiscount !== undefined) {
    assertDiscountApplied(cartTotals, expectedDiscount, tolerance);
  }

  // Verify calculations are consistent
  assertCartTotalCorrect(cartTotals, tolerance);

  logger.info('Cart verification passed', {
    itemCount: cartItems.length,
    subtotal: cartTotals.subtotal,
    total: cartTotals.total,
  });
}
