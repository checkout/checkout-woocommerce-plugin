/**
 * Test shipping method data.
 */

export interface TestShippingMethod {
  id: string;
  title: string;
  cost: number;
  freeAbove?: number;
}

/**
 * Available shipping methods.
 * Update to match your test store configuration.
 */
export const shippingMethods: Record<string, TestShippingMethod> = {
  flatRate: {
    id: 'flat_rate',
    title: 'Flat rate',
    cost: 10.00,
  },
  
  freeShipping: {
    id: 'free_shipping',
    title: 'Free shipping',
    cost: 0,
    freeAbove: 50, // Often requires minimum order
  },
  
  localPickup: {
    id: 'local_pickup',
    title: 'Local pickup',
    cost: 0,
  },
  
  express: {
    id: 'express',
    title: 'Express Delivery',
    cost: 25.00,
  },
  
  standard: {
    id: 'standard',
    title: 'Standard Shipping',
    cost: 5.00,
  },
};

/**
 * Get shipping method by ID.
 */
export function getShippingMethod(id: string): TestShippingMethod | undefined {
  return Object.values(shippingMethods).find(m => m.id === id);
}

/**
 * Get shipping cost for an order total.
 */
export function getShippingCost(method: TestShippingMethod, orderTotal: number): number {
  if (method.freeAbove && orderTotal >= method.freeAbove) {
    return 0;
  }
  return method.cost;
}

/**
 * Check if free shipping is available for an order.
 */
export function isFreeShippingAvailable(orderTotal: number): boolean {
  const freeShipping = shippingMethods.freeShipping;
  return !freeShipping.freeAbove || orderTotal >= freeShipping.freeAbove;
}

/**
 * Default shipping method.
 */
export const defaultShippingMethod = shippingMethods.flatRate;
