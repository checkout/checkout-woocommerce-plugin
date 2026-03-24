/**
 * Test product data for wc-cko.net store.
 * These products match the actual WooCommerce store inventory.
 */

export interface TestProduct {
  name: string;
  slug: string;
  sku: string;
  price: number;
  salePrice?: number;
  type: 'simple' | 'variable' | 'grouped' | 'virtual' | 'downloadable';
  inStock: boolean;
  variations?: Array<{
    attributes: Record<string, string>;
    sku: string;
    price: number;
  }>;
}

/**
 * Products from wc-cko.net store.
 */
export const testProducts: Record<string, TestProduct> = {
  // Simple products
  simple: {
    name: 'Album',
    slug: 'album',
    sku: 'ALBUM-001',
    price: 15.00,
    type: 'simple',
    inStock: true,
  },

  beanie: {
    name: 'Beanie',
    slug: 'beanie',
    sku: 'BEANIE-001',
    price: 18.00,
    salePrice: 18.00,
    type: 'simple',
    inStock: true,
  },

  beanieWithLogo: {
    name: 'Beanie with Logo',
    slug: 'beanie-with-logo',
    sku: 'BEANIE-LOGO-001',
    price: 18.00,
    salePrice: 18.00,
    type: 'simple',
    inStock: true,
  },

  belt: {
    name: 'Belt',
    slug: 'belt',
    sku: 'BELT-001',
    price: 55.00,
    salePrice: 55.00,
    type: 'simple',
    inStock: true,
  },

  cap: {
    name: 'Cap',
    slug: 'cap',
    sku: 'CAP-001',
    price: 16.00,
    salePrice: 16.00,
    type: 'simple',
    inStock: true,
  },

  hoodieWithLogo: {
    name: 'Hoodie with Logo',
    slug: 'hoodie-with-logo',
    sku: 'HOODIE-LOGO-001',
    price: 45.00,
    type: 'simple',
    inStock: true,
  },

  hoodieWithZipper: {
    name: 'Hoodie with Zipper',
    slug: 'hoodie-with-zipper',
    sku: 'HOODIE-ZIP-001',
    price: 45.00,
    type: 'simple',
    inStock: true,
  },

  longSleeveTee: {
    name: 'Long Sleeve Tee',
    slug: 'long-sleeve-tee',
    sku: 'LONGSLEEVE-001',
    price: 25.00,
    type: 'simple',
    inStock: true,
  },

  polo: {
    name: 'Polo',
    slug: 'polo',
    sku: 'POLO-001',
    price: 20.00,
    type: 'simple',
    inStock: true,
  },

  single: {
    name: 'Single',
    slug: 'single',
    sku: 'SINGLE-001',
    price: 2.00,
    salePrice: 2.00,
    type: 'simple',
    inStock: true,
  },

  // Variable product
  hoodie: {
    name: 'Hoodie',
    slug: 'hoodie',
    sku: 'HOODIE-001',
    price: 42.00,
    type: 'variable',
    inStock: true,
    variations: [
      {
        attributes: { Color: 'Blue', Size: 'Small' },
        sku: 'HOODIE-BLUE-S',
        price: 42.00,
      },
      {
        attributes: { Color: 'Blue', Size: 'Medium' },
        sku: 'HOODIE-BLUE-M',
        price: 45.00,
      },
      {
        attributes: { Color: 'Blue', Size: 'Large' },
        sku: 'HOODIE-BLUE-L',
        price: 45.00,
      },
    ],
  },

  // Grouped product
  logoCollection: {
    name: 'Logo Collection',
    slug: 'logo-collection',
    sku: 'LOGO-COLLECTION',
    price: 18.00,
    type: 'grouped',
    inStock: true,
  },

  // Aliases for test compatibility
  premium: {
    name: 'Belt',
    slug: 'belt',
    sku: 'BELT-001',
    price: 55.00,
    type: 'simple',
    inStock: true,
  },

  cheap: {
    name: 'Single',
    slug: 'single',
    sku: 'SINGLE-001',
    price: 2.00,
    type: 'simple',
    inStock: true,
  },

  expensive: {
    name: 'Hoodie with Zipper',
    slug: 'hoodie-with-zipper',
    sku: 'HOODIE-ZIP-001',
    price: 45.00,
    type: 'simple',
    inStock: true,
  },

  sale: {
    name: 'Cap',
    slug: 'cap',
    sku: 'CAP-001',
    price: 16.00,
    salePrice: 16.00,
    type: 'simple',
    inStock: true,
  },

  variable: {
    name: 'Hoodie',
    slug: 'hoodie',
    sku: 'HOODIE-001',
    price: 42.00,
    type: 'variable',
    inStock: true,
  },
};

/**
 * Get a test product by key.
 */
export function getTestProduct(key: keyof typeof testProducts): TestProduct {
  return testProducts[key];
}

/**
 * Get product by SKU.
 */
export function getProductBySku(sku: string): TestProduct | undefined {
  return Object.values(testProducts).find(p => p.sku === sku);
}

/**
 * Get product by slug.
 */
export function getProductBySlug(slug: string): TestProduct | undefined {
  return Object.values(testProducts).find(p => p.slug === slug);
}

/**
 * Get all in-stock products.
 */
export function getInStockProducts(): TestProduct[] {
  return Object.values(testProducts).filter(p => p.inStock);
}

/**
 * Get products by type.
 */
export function getProductsByType(type: TestProduct['type']): TestProduct[] {
  return Object.values(testProducts).filter(p => p.type === type);
}

/**
 * Get simple products only (for straightforward tests).
 */
export function getSimpleProducts(): TestProduct[] {
  return Object.values(testProducts).filter(p => p.type === 'simple');
}

/**
 * Calculate expected line total for a product.
 */
export function calculateLineTotal(product: TestProduct, quantity: number): number {
  const price = product.salePrice ?? product.price;
  return Math.round(price * quantity * 100) / 100;
}
