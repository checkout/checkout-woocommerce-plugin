/**
 * Test coupon data.
 * These should match coupons configured in your test WooCommerce store.
 */

export interface TestCoupon {
  code: string;
  type: 'percent' | 'fixed_cart' | 'fixed_product';
  amount: number;
  description: string;
  minimumSpend?: number;
  maximumSpend?: number;
  excludesSale?: boolean;
  usageLimit?: number;
}

/**
 * Test coupons.
 * Update these to match your test store's coupon configuration.
 * 
 * For wc-cko.net store, the primary coupon is 't10'
 */
export const testCoupons: Record<string, TestCoupon> = {
  // Primary coupon for wc-cko.net store
  t10: {
    code: 't10',
    type: 'percent',
    amount: 10,
    description: '10% off entire order',
  },
  
  // Alias for backward compatibility
  percent10: {
    code: 't10',
    type: 'percent',
    amount: 10,
    description: '10% off entire order',
  },
  
  percent25: {
    code: 'PERCENT25',
    type: 'percent',
    amount: 25,
    description: '25% off entire order',
  },
  
  fixed10: {
    code: '12',
    type: 'fixed_cart',
    amount: 12,
    description: '£12 off cart total',
  },
  
  fixed50: {
    code: 'FIXED50',
    type: 'fixed_cart',
    amount: 50,
    description: '£50 off cart total',
    minimumSpend: 100,
  },
  
  freeShipping: {
    code: 'FREESHIP',
    type: 'fixed_cart',
    amount: 0,
    description: 'Free shipping (no discount on products)',
  },
  
  expired: {
    code: 'EXPIRED',
    type: 'percent',
    amount: 50,
    description: 'Expired coupon (should fail)',
  },
  
  invalid: {
    code: 'INVALIDCODE123',
    type: 'percent',
    amount: 0,
    description: 'Invalid/non-existent coupon',
  },
  
  minSpend: {
    code: 'MINSPEND100',
    type: 'percent',
    amount: 15,
    description: '15% off with £100 minimum',
    minimumSpend: 100,
  },
  
  excludeSale: {
    code: 'NOSALE20',
    type: 'percent',
    amount: 20,
    description: '20% off excluding sale items',
    excludesSale: true,
  },
  
  oneTimeUse: {
    code: 'ONETIME',
    type: 'fixed_cart',
    amount: 5,
    description: 'Single use coupon',
    usageLimit: 1,
  },
};

/**
 * Get a test coupon by key.
 */
export function getTestCoupon(key: keyof typeof testCoupons): TestCoupon {
  return testCoupons[key];
}

/**
 * Calculate discount for a cart total.
 */
export function calculateDiscount(coupon: TestCoupon, cartTotal: number): number {
  if (coupon.minimumSpend && cartTotal < coupon.minimumSpend) {
    return 0;
  }

  switch (coupon.type) {
    case 'percent':
      return Math.round(cartTotal * (coupon.amount / 100) * 100) / 100;
    case 'fixed_cart':
      return Math.min(coupon.amount, cartTotal);
    case 'fixed_product':
      return coupon.amount; // Per product, would need quantity
    default:
      return 0;
  }
}

/**
 * Get expected total after applying coupon.
 */
export function getExpectedTotalWithCoupon(
  coupon: TestCoupon,
  subtotal: number,
  shipping = 0,
  tax = 0
): number {
  const discount = calculateDiscount(coupon, subtotal);
  const total = subtotal - discount + shipping + tax;
  return Math.round(Math.max(0, total) * 100) / 100;
}
