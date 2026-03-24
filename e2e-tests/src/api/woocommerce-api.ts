import { getEnvConfig, hasWcApiCredentials } from '../config/env-loader';
import { Logger } from '../utils/logger';

const logger = new Logger();

/**
 * WooCommerce REST API response types.
 */
export interface WcApiOrder {
  id: number;
  parent_id: number;
  number: string;
  status: string;
  currency: string;
  date_created: string;
  date_modified: string;
  total: string;
  subtotal?: string;
  shipping_total: string;
  discount_total: string;
  total_tax: string;
  billing: {
    first_name: string;
    last_name: string;
    company: string;
    address_1: string;
    address_2: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
    email: string;
    phone: string;
  };
  shipping: {
    first_name: string;
    last_name: string;
    company: string;
    address_1: string;
    address_2: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
  };
  payment_method: string;
  payment_method_title: string;
  line_items: Array<{
    id: number;
    name: string;
    product_id: number;
    variation_id: number;
    quantity: number;
    tax_class: string;
    subtotal: string;
    subtotal_tax: string;
    total: string;
    total_tax: string;
    sku: string;
    price: number;
  }>;
  shipping_lines: Array<{
    id: number;
    method_title: string;
    method_id: string;
    total: string;
    total_tax: string;
  }>;
  fee_lines: Array<{
    id: number;
    name: string;
    total: string;
    total_tax: string;
  }>;
  coupon_lines: Array<{
    id: number;
    code: string;
    discount: string;
    discount_tax: string;
  }>;
  order_notes?: Array<{
    id: number;
    author: string;
    date_created: string;
    note: string;
    customer_note: boolean;
  }>;
}

export interface WcApiProduct {
  id: number;
  name: string;
  slug: string;
  sku: string;
  price: string;
  regular_price: string;
  sale_price: string;
  stock_status: string;
  stock_quantity: number | null;
  type: string;
}

/**
 * WooCommerce REST API client.
 * Provides methods to fetch and verify order data programmatically.
 * 
 * Requires WC_CONSUMER_KEY and WC_CONSUMER_SECRET environment variables.
 * 
 * @example
 * ```typescript
 * const api = new WooCommerceApi();
 * if (api.isAvailable()) {
 *   const order = await api.getOrder(123);
 *   console.log(order.status);
 * }
 * ```
 */
export class WooCommerceApi {
  private readonly baseUrl: string;
  private readonly consumerKey: string;
  private readonly consumerSecret: string;
  private readonly available: boolean;

  constructor() {
    const config = getEnvConfig();
    this.baseUrl = config.BASE_URL.replace(/\/$/, '');
    this.consumerKey = config.WC_CONSUMER_KEY;
    this.consumerSecret = config.WC_CONSUMER_SECRET;
    this.available = hasWcApiCredentials();
  }

  /**
   * Check if API credentials are configured.
   */
  isAvailable(): boolean {
    return this.available;
  }

  /**
   * Get the authorization header for API requests.
   */
  private getAuthHeader(): string {
    const credentials = Buffer.from(`${this.consumerKey}:${this.consumerSecret}`).toString('base64');
    return `Basic ${credentials}`;
  }

  /**
   * Make a request to the WooCommerce REST API.
   */
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    if (!this.available) {
      throw new Error(
        'WooCommerce API credentials not configured. ' +
        'Set WC_CONSUMER_KEY and WC_CONSUMER_SECRET in your .env file.'
      );
    }

    const url = `${this.baseUrl}/wp-json/wc/v3/${endpoint}`;
    
    const response = await fetch(url, {
      ...options,
      headers: {
        'Authorization': this.getAuthHeader(),
        'Content-Type': 'application/json',
        ...options.headers,
      },
    });

    if (!response.ok) {
      const errorBody = await response.text().catch(() => 'Unknown error');
      throw new Error(
        `WooCommerce API error: ${response.status} ${response.statusText}\n${errorBody}`
      );
    }

    return response.json() as Promise<T>;
  }

  /**
   * Get a single order by ID.
   */
  async getOrder(orderId: number | string): Promise<WcApiOrder> {
    logger.debug(`Fetching order #${orderId} via API`);
    return this.request<WcApiOrder>(`orders/${orderId}`);
  }

  /**
   * Get orders with optional filters.
   */
  async getOrders(params: {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    customer?: number;
    email?: string;
    after?: string;
    before?: string;
    orderby?: 'date' | 'id' | 'include' | 'title' | 'slug';
    order?: 'asc' | 'desc';
  } = {}): Promise<WcApiOrder[]> {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined) {
        queryParams.set(key, String(value));
      }
    });

    const endpoint = `orders${queryParams.toString() ? `?${queryParams}` : ''}`;
    return this.request<WcApiOrder[]>(endpoint);
  }

  /**
   * Find the most recent order for a customer email.
   */
  async getLastOrderByEmail(email: string): Promise<WcApiOrder | null> {
    logger.debug(`Fetching last order for email: ${email}`);
    
    const orders = await this.getOrders({
      search: email,
      per_page: 1,
      orderby: 'date',
      order: 'desc',
    });

    if (orders.length === 0) {
      logger.warn(`No orders found for email: ${email}`);
      return null;
    }

    return orders[0];
  }

  /**
   * Find order by order number.
   */
  async findOrderByNumber(orderNumber: string): Promise<WcApiOrder | null> {
    logger.debug(`Searching for order #${orderNumber}`);
    
    const orders = await this.getOrders({
      search: orderNumber,
      per_page: 10,
    });

    const order = orders.find(o => o.number === orderNumber);
    
    if (!order) {
      logger.warn(`Order #${orderNumber} not found via API`);
      return null;
    }

    return order;
  }

  /**
   * Get product by ID.
   */
  async getProduct(productId: number): Promise<WcApiProduct> {
    return this.request<WcApiProduct>(`products/${productId}`);
  }

  /**
   * Get products with optional filters.
   */
  async getProducts(params: {
    page?: number;
    per_page?: number;
    search?: string;
    sku?: string;
    status?: 'any' | 'draft' | 'pending' | 'private' | 'publish';
    type?: 'simple' | 'grouped' | 'external' | 'variable';
    in_stock?: boolean;
  } = {}): Promise<WcApiProduct[]> {
    const queryParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined) {
        queryParams.set(key, String(value));
      }
    });

    const endpoint = `products${queryParams.toString() ? `?${queryParams}` : ''}`;
    return this.request<WcApiProduct[]>(endpoint);
  }

  /**
   * Find product by SKU.
   */
  async findProductBySku(sku: string): Promise<WcApiProduct | null> {
    const products = await this.getProducts({ sku });
    return products.length > 0 ? products[0] : null;
  }

  /**
   * Update order status.
   */
  async updateOrderStatus(orderId: number | string, status: string): Promise<WcApiOrder> {
    logger.info(`Updating order #${orderId} status to: ${status}`);
    
    return this.request<WcApiOrder>(`orders/${orderId}`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    });
  }

  /**
   * Add a note to an order.
   */
  async addOrderNote(
    orderId: number | string,
    note: string,
    customerNote = false
  ): Promise<{ id: number; note: string }> {
    logger.debug(`Adding note to order #${orderId}`);
    
    return this.request<{ id: number; note: string }>(`orders/${orderId}/notes`, {
      method: 'POST',
      body: JSON.stringify({
        note,
        customer_note: customerNote,
      }),
    });
  }

  /**
   * Get order notes.
   */
  async getOrderNotes(orderId: number | string): Promise<Array<{
    id: number;
    author: string;
    date_created: string;
    note: string;
    customer_note: boolean;
  }>> {
    return this.request(`orders/${orderId}/notes`);
  }

  /**
   * Verify order totals via API.
   * Returns comparison result.
   */
  async verifyOrderTotals(
    orderId: number | string,
    expectedTotals: {
      subtotal?: number;
      shipping?: number;
      tax?: number;
      discount?: number;
      total: number;
    },
    tolerance = 0.01
  ): Promise<{ passed: boolean; differences: string[] }> {
    const order = await this.getOrder(orderId);
    const differences: string[] = [];

    const compare = (name: string, expected: number | undefined, actual: string) => {
      if (expected === undefined) return;
      const actualNum = parseFloat(actual);
      if (Math.abs(actualNum - expected) > tolerance) {
        differences.push(`${name}: expected ${expected}, got ${actualNum}`);
      }
    };

    // Calculate subtotal from line items if not directly available
    const apiSubtotal = order.line_items.reduce(
      (sum, item) => sum + parseFloat(item.subtotal),
      0
    );

    compare('subtotal', expectedTotals.subtotal, apiSubtotal.toString());
    compare('shipping', expectedTotals.shipping, order.shipping_total);
    compare('tax', expectedTotals.tax, order.total_tax);
    compare('discount', expectedTotals.discount, order.discount_total);
    compare('total', expectedTotals.total, order.total);

    const passed = differences.length === 0;

    if (!passed) {
      logger.warn('API order verification failed', { orderId, differences });
    } else {
      logger.info('API order verification passed', { orderId });
    }

    return { passed, differences };
  }

  /**
   * Verify order items via API.
   */
  async verifyOrderItems(
    orderId: number | string,
    expectedItems: Array<{ name: string; quantity: number; sku?: string }>
  ): Promise<{ passed: boolean; differences: string[] }> {
    const order = await this.getOrder(orderId);
    const differences: string[] = [];

    for (const expected of expectedItems) {
      const item = order.line_items.find(i => 
        i.name.toLowerCase().includes(expected.name.toLowerCase()) ||
        (expected.sku && i.sku === expected.sku)
      );

      if (!item) {
        differences.push(`Item "${expected.name}" not found in order`);
        continue;
      }

      if (item.quantity !== expected.quantity) {
        differences.push(
          `Item "${expected.name}": expected qty ${expected.quantity}, got ${item.quantity}`
        );
      }
    }

    // Check for unexpected items
    if (order.line_items.length !== expectedItems.length) {
      differences.push(
        `Item count: expected ${expectedItems.length}, got ${order.line_items.length}`
      );
    }

    return { passed: differences.length === 0, differences };
  }
}

/**
 * Singleton instance for convenience.
 */
let apiInstance: WooCommerceApi | null = null;

export function getWcApi(): WooCommerceApi {
  if (!apiInstance) {
    apiInstance = new WooCommerceApi();
  }
  return apiInstance;
}
