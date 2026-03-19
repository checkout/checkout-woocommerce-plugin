import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';
import { parseMoney, moneyEquals } from '../../utils/money';

/**
 * Order item from the order received page.
 */
export interface OrderReceivedItem {
  name: string;
  quantity: number;
  total: number;
}

/**
 * Complete order details from the order received page.
 */
export interface OrderReceivedDetails {
  orderNumber: string;
  date?: string;
  email?: string;
  total: number;
  paymentMethod?: string;
  items: OrderReceivedItem[];
  subtotal: number;
  shipping: number;
  tax: number;
  discount: number;
}

/**
 * Order Received / Thank You page object.
 */
export class OrderReceivedPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    throw new Error('OrderReceivedPage is navigated to after placing an order');
  }

  getUrlPattern(): string | RegExp {
    return /\/checkout\/order-received\/|\/order-received\//;
  }

  /**
   * Wait for order received page to be fully loaded.
   */
  async waitForPageLoad(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for order received page to be visible
    // Use .first() to avoid strict mode violation
    const orderSection = this.locator('.woocommerce-order, .woocommerce-order-received');
    await orderSection.first().waitFor({ state: 'visible', timeout: 15000 });
    this.logger.step('Order received page loaded');
  }

  /**
   * Check if this is the order received page.
   */
  async isOnOrderReceivedPage(): Promise<boolean> {
    const orderReceivedIndicators = [
      this.locator(WooSelectors.ORDER_RECEIVED),
      this.getByText(/thank you|order received/i),
      this.locator('.woocommerce-order'),
    ];

    for (const indicator of orderReceivedIndicators) {
      if (await indicator.count() > 0) {
        return true;
      }
    }

    return this.page.url().includes('order-received');
  }

  /**
   * Get the order number.
   */
  async getOrderNumber(): Promise<string> {
    // Try multiple patterns for order number
    const patterns = [
      this.locator('.woocommerce-order-overview__order strong'),
      this.locator('.order-number'),
      this.locator('.woocommerce-order-overview__order.order'),
    ];

    for (const pattern of patterns) {
      if (await pattern.count() > 0) {
        const text = await this.getTrimmedText(pattern);
        // Clean up the order number
        return text.replace(/[^0-9]/g, '') || text;
      }
    }

    // Try extracting from URL
    const url = this.page.url();
    const match = url.match(/order-received\/(\d+)/);
    if (match) {
      return match[1];
    }

    throw new Error('Could not find order number on page');
  }

  /**
   * Get the order date.
   */
  async getOrderDate(): Promise<string> {
    const dateElement = this.locator('.woocommerce-order-overview__date strong');
    if (await dateElement.count() > 0) {
      return this.getTrimmedText(dateElement);
    }
    return '';
  }

  /**
   * Get the order email.
   */
  async getOrderEmail(): Promise<string> {
    const emailElement = this.locator('.woocommerce-order-overview__email strong');
    if (await emailElement.count() > 0) {
      return this.getTrimmedText(emailElement);
    }
    return '';
  }

  /**
   * Get the order total.
   */
  async getOrderTotal(): Promise<number> {
    // Try specific selectors and use .first() to avoid strict mode issues
    const totalSelectors = [
      '.woocommerce-order-overview__total .woocommerce-Price-amount bdi',
      '.woocommerce-order-overview__total strong .woocommerce-Price-amount',
      '.woocommerce-order-overview__total .woocommerce-Price-amount',
      '.order-total .woocommerce-Price-amount bdi',
      '.order-total .woocommerce-Price-amount',
    ];
    
    for (const selector of totalSelectors) {
      const element = this.locator(selector);
      if (await element.count() > 0) {
        const text = await this.getTrimmedText(element.first());
        return parseMoney(text).amount;
      }
    }

    throw new Error('Could not find order total');
  }

  /**
   * Get the payment method used.
   */
  async getPaymentMethod(): Promise<string> {
    const paymentElement = this.locator('.woocommerce-order-overview__payment-method strong');
    if (await paymentElement.count() > 0) {
      return this.getTrimmedText(paymentElement);
    }
    return '';
  }

  /**
   * Get order line items from the details table.
   */
  async getOrderItems(): Promise<OrderReceivedItem[]> {
    const items: OrderReceivedItem[] = [];
    const rows = this.locator(`${WooSelectors.ORDER_DETAILS_TABLE} tbody tr.woocommerce-table__line-item`);
    
    const count = await rows.count();
    for (let i = 0; i < count; i++) {
      const row = rows.nth(i);
      
      // Get product name
      const nameCell = row.locator('.woocommerce-table__product-name');
      const nameText = await this.getTrimmedText(nameCell);
      
      // Parse name and quantity
      const quantityMatch = nameText.match(/×\s*(\d+)/);
      const quantity = quantityMatch ? parseInt(quantityMatch[1], 10) : 1;
      const name = nameText.replace(/×\s*\d+/, '').trim();
      
      // Get total
      const totalCell = row.locator('.woocommerce-table__product-total .woocommerce-Price-amount');
      const totalText = await this.getTrimmedText(totalCell);
      const total = parseMoney(totalText).amount;
      
      items.push({ name, quantity, total });
    }
    
    return items;
  }

  /**
   * Get order subtotal.
   */
  async getSubtotal(): Promise<number> {
    const subtotalElement = this.locator('tr.cart-subtotal .woocommerce-Price-amount, tfoot tr:has-text("Subtotal") .woocommerce-Price-amount');
    if (await subtotalElement.count() > 0) {
      const text = await this.getTrimmedText(subtotalElement.first());
      return parseMoney(text).amount;
    }
    return 0;
  }

  /**
   * Get shipping cost.
   */
  async getShippingCost(): Promise<number> {
    const shippingElement = this.locator('tr.shipping .woocommerce-Price-amount, tfoot tr:has-text("Shipping") .woocommerce-Price-amount');
    if (await shippingElement.count() > 0) {
      const text = await this.getTrimmedText(shippingElement.first());
      return parseMoney(text).amount;
    }
    return 0;
  }

  /**
   * Get tax amount.
   */
  async getTax(): Promise<number> {
    const taxElement = this.locator('tr.tax-rate .woocommerce-Price-amount, tfoot tr:has-text("Tax") .woocommerce-Price-amount');
    if (await taxElement.count() > 0) {
      const text = await this.getTrimmedText(taxElement.first());
      return parseMoney(text).amount;
    }
    return 0;
  }

  /**
   * Get discount amount.
   */
  async getDiscount(): Promise<number> {
    const discountElement = this.locator('tr.cart-discount .woocommerce-Price-amount, tfoot tr:has-text("Discount") .woocommerce-Price-amount');
    if (await discountElement.count() > 0) {
      const text = await this.getTrimmedText(discountElement.first());
      return Math.abs(parseMoney(text).amount);
    }
    return 0;
  }

  /**
   * Get complete order details.
   */
  async getOrderDetails(): Promise<OrderReceivedDetails> {
    const details: OrderReceivedDetails = {
      orderNumber: await this.getOrderNumber(),
      date: await this.getOrderDate(),
      email: await this.getOrderEmail(),
      total: await this.getOrderTotal(),
      paymentMethod: await this.getPaymentMethod(),
      items: await this.getOrderItems(),
      subtotal: await this.getSubtotal(),
      shipping: await this.getShippingCost(),
      tax: await this.getTax(),
      discount: await this.getDiscount(),
    };

    this.logger.info('Retrieved order details', { orderNumber: details.orderNumber });
    return details;
  }

  /**
   * Get billing address from order details.
   */
  async getBillingAddress(): Promise<string> {
    const billingAddress = this.locator('.woocommerce-column--billing-address address');
    if (await billingAddress.count() > 0) {
      return this.getTrimmedText(billingAddress);
    }
    return '';
  }

  /**
   * Get shipping address from order details.
   */
  async getShippingAddress(): Promise<string> {
    const shippingAddress = this.locator('.woocommerce-column--shipping-address address');
    if (await shippingAddress.count() > 0) {
      return this.getTrimmedText(shippingAddress);
    }
    return '';
  }

  /**
   * Assert order was received successfully.
   */
  async assertOrderReceived(): Promise<void> {
    expect(await this.isOnOrderReceivedPage()).toBe(true);
    
    // Verify order number is displayed
    const orderNumber = await this.getOrderNumber();
    expect(orderNumber).toBeTruthy();
    
    this.logger.assertion(`Order ${orderNumber} received successfully`);
  }

  /**
   * Assert order total matches expected value.
   */
  async assertTotal(expectedTotal: number, tolerance = 0.01): Promise<void> {
    const actualTotal = await this.getOrderTotal();
    expect(
      moneyEquals(actualTotal, expectedTotal, tolerance),
      `Expected order total ${expectedTotal}, got ${actualTotal}`
    ).toBe(true);
    
    this.logger.assertion(`Order total ${actualTotal} matches expected ${expectedTotal}`);
  }

  /**
   * Assert order contains expected items.
   */
  async assertContainsItems(expectedItems: Array<{ name: string; quantity?: number }>): Promise<void> {
    const items = await this.getOrderItems();
    
    for (const expected of expectedItems) {
      const found = items.find(item => 
        item.name.toLowerCase().includes(expected.name.toLowerCase())
      );
      
      expect(found, `Expected order to contain "${expected.name}"`).toBeTruthy();
      
      if (expected.quantity && found) {
        expect(found.quantity).toBe(expected.quantity);
      }
    }
    
    this.logger.assertion('Order contains expected items');
  }

  /**
   * Assert payment method matches expected.
   */
  async assertPaymentMethod(expectedMethod: string): Promise<void> {
    const actualMethod = await this.getPaymentMethod();
    expect(actualMethod.toLowerCase()).toContain(expectedMethod.toLowerCase());
    
    this.logger.assertion(`Payment method "${actualMethod}" matches expected`);
  }

  /**
   * Take a screenshot of the order confirmation.
   */
  async screenshotOrderConfirmation(): Promise<Buffer> {
    const orderNumber = await this.getOrderNumber();
    return this.screenshot(`order-confirmation-${orderNumber}`);
  }
}
