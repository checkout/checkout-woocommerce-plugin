import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { parseMoney, moneyEquals } from '../../utils/money';

/**
 * Order line item from admin order details.
 */
export interface AdminOrderLineItem {
  name: string;
  sku?: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
}

/**
 * Complete admin order details.
 */
export interface AdminOrderDetails {
  orderNumber: string;
  status: string;
  dateCreated: string;
  billingEmail: string;
  billingName: string;
  billingAddress: string;
  shippingAddress?: string;
  paymentMethod: string;
  items: AdminOrderLineItem[];
  subtotal: number;
  shipping: number;
  tax: number;
  discount: number;
  total: number;
  orderNotes?: string[];
}

/**
 * WooCommerce Admin Order Details page object.
 */
export class AdminOrderDetailsPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    throw new Error('AdminOrderDetailsPage requires an order ID. Use goToOrder(orderId) instead.');
  }

  getUrlPattern(): string | RegExp {
    return /post\.php\?post=\d+&action=edit|admin\.php\?page=wc-orders&action=edit&id=\d+/;
  }

  /**
   * Navigate to a specific order by ID.
   */
  async goToOrder(orderId: string | number): Promise<void> {
    // Try HPOS URL first, then classic
    const hposUrl = `${this.config.ADMIN_URL}/admin.php?page=wc-orders&action=edit&id=${orderId}`;
    const classicUrl = `${this.config.ADMIN_URL}/post.php?post=${orderId}&action=edit`;

    try {
      await this.navigateTo(hposUrl);
      if (!(await this.isOnOrderPage())) {
        await this.navigateTo(classicUrl);
      }
    } catch {
      await this.navigateTo(classicUrl);
    }

    await this.waitForOrderPageReady();
    this.logger.step(`Navigated to order ${orderId}`);
  }

  /**
   * Wait for order details page to be ready.
   */
  private async waitForOrderPageReady(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for order data metabox
    const orderData = this.locator('#woocommerce-order-data, .woocommerce-order-data');
    await orderData.waitFor({ state: 'visible', timeout: 15000 });
  }

  /**
   * Check if we're on an order details page.
   */
  async isOnOrderPage(): Promise<boolean> {
    const orderData = this.locator('#woocommerce-order-data, .woocommerce-order-data');
    return await orderData.isVisible().catch(() => false);
  }

  /**
   * Get the order number.
   */
  async getOrderNumber(): Promise<string> {
    const orderNumber = this.locator('.woocommerce-order-data__heading h2, h1.wp-heading-inline');
    const text = await this.getTrimmedText(orderNumber);
    const match = text.match(/#?(\d+)/);
    return match ? match[1] : '';
  }

  /**
   * Get the order status.
   */
  async getOrderStatus(): Promise<string> {
    const statusSelect = this.locator('#order_status, select[name="order_status"]');
    if (await statusSelect.count() > 0) {
      const value = await statusSelect.inputValue();
      return value.replace('wc-', '');
    }
    
    // Fallback to status label
    const statusLabel = this.locator('.wc-order-status');
    if (await statusLabel.count() > 0) {
      return (await this.getTrimmedText(statusLabel)).toLowerCase();
    }
    
    return '';
  }

  /**
   * Get the order creation date.
   */
  async getDateCreated(): Promise<string> {
    const dateInput = this.locator('input[name="order_date"], .order_date input');
    if (await dateInput.count() > 0) {
      return dateInput.inputValue();
    }
    
    const dateText = this.locator('.woocommerce-order-data__meta');
    if (await dateText.count() > 0) {
      return this.getTrimmedText(dateText);
    }
    
    return '';
  }

  /**
   * Get billing email.
   */
  async getBillingEmail(): Promise<string> {
    const emailInput = this.locator('#_billing_email, input[name="_billing_email"]');
    if (await emailInput.count() > 0) {
      return emailInput.inputValue();
    }
    
    const emailLink = this.locator('.woocommerce-order-data__meta a[href^="mailto:"]');
    if (await emailLink.count() > 0) {
      return this.getTrimmedText(emailLink);
    }
    
    return '';
  }

  /**
   * Get billing name.
   */
  async getBillingName(): Promise<string> {
    const firstName = this.locator('#_billing_first_name').inputValue().catch(() => '');
    const lastName = this.locator('#_billing_last_name').inputValue().catch(() => '');
    
    const first = await firstName;
    const last = await lastName;
    
    if (first || last) {
      return `${first} ${last}`.trim();
    }
    
    // Try from address block
    const billingAddress = this.locator('.order_data_column:first-child address');
    if (await billingAddress.count() > 0) {
      const addressText = await this.getTrimmedText(billingAddress);
      const firstLine = addressText.split('\n')[0];
      return firstLine || '';
    }
    
    return '';
  }

  /**
   * Get full billing address.
   */
  async getBillingAddress(): Promise<string> {
    const billingAddress = this.locator('.order_data_column:first-child address, #order_data .billing address');
    if (await billingAddress.count() > 0) {
      return this.getTrimmedText(billingAddress);
    }
    return '';
  }

  /**
   * Get full shipping address.
   */
  async getShippingAddress(): Promise<string> {
    const shippingAddress = this.locator('.order_data_column:nth-child(2) address, #order_data .shipping address');
    if (await shippingAddress.count() > 0) {
      return this.getTrimmedText(shippingAddress);
    }
    return '';
  }

  /**
   * Get payment method.
   */
  async getPaymentMethod(): Promise<string> {
    const paymentMethod = this.locator('.wc-order-totals .payment-method, #woocommerce-order-data .payment-method-title, .order_data_column_container .wc-payment-method-title');
    if (await paymentMethod.count() > 0) {
      return this.getTrimmedText(paymentMethod);
    }
    
    // Try from order actions metabox
    const paymentInfo = this.locator('#woocommerce-order-notes ~ .inside .payment_method');
    if (await paymentInfo.count() > 0) {
      return this.getTrimmedText(paymentInfo);
    }
    
    return '';
  }

  /**
   * Get order line items.
   */
  async getLineItems(): Promise<AdminOrderLineItem[]> {
    const items: AdminOrderLineItem[] = [];
    const rows = this.locator('#order_line_items tr.item, .woocommerce_order_items tr.item');
    
    const count = await rows.count();
    for (let i = 0; i < count; i++) {
      const row = rows.nth(i);
      
      // Product name
      const nameElement = row.locator('.wc-order-item-name, .name a');
      const name = await this.getTrimmedText(nameElement);
      
      // SKU
      const skuElement = row.locator('.wc-order-item-sku, .sku');
      const sku = await skuElement.count() > 0 ? await this.getTrimmedText(skuElement) : undefined;
      
      // Quantity
      const qtyElement = row.locator('.quantity .view, td.quantity');
      const qtyText = await this.getTrimmedText(qtyElement);
      const quantity = parseInt(qtyText.replace(/[^0-9]/g, ''), 10) || 1;
      
      // Unit price (cost)
      const costElement = row.locator('.item_cost .view, td.item_cost');
      const costText = await this.getTrimmedText(costElement);
      const unitPrice = parseMoney(costText).amount;
      
      // Line total
      const totalElement = row.locator('.line_cost .view, td.line_cost');
      const totalText = await this.getTrimmedText(totalElement);
      const lineTotal = parseMoney(totalText).amount;
      
      items.push({ name, sku, quantity, unitPrice, lineTotal });
    }
    
    return items;
  }

  /**
   * Get order subtotal.
   */
  async getSubtotal(): Promise<number> {
    const subtotalElement = this.locator('.wc-order-totals tr:has(.woocommerce-Price-amount):first-child .woocommerce-Price-amount, td.total .woocommerce-Price-amount');
    
    // Find subtotal row specifically
    const subtotalRow = this.locator('tr.item_cost:has-text("Items Subtotal"), tr:has-text("Subtotal")').first();
    const subtotalValue = subtotalRow.locator('.woocommerce-Price-amount');
    
    if (await subtotalValue.count() > 0) {
      const text = await this.getTrimmedText(subtotalValue);
      return parseMoney(text).amount;
    }
    
    // Calculate from line items
    const items = await this.getLineItems();
    return items.reduce((sum, item) => sum + item.lineTotal, 0);
  }

  /**
   * Get shipping cost.
   */
  async getShipping(): Promise<number> {
    const shippingRow = this.locator('tr:has-text("Shipping")');
    const shippingValue = shippingRow.locator('.woocommerce-Price-amount');
    
    if (await shippingValue.count() > 0) {
      const text = await this.getTrimmedText(shippingValue.first());
      return parseMoney(text).amount;
    }
    return 0;
  }

  /**
   * Get tax amount.
   */
  async getTax(): Promise<number> {
    const taxRow = this.locator('tr:has-text("Tax"), tr.tax');
    const taxValue = taxRow.locator('.woocommerce-Price-amount');
    
    if (await taxValue.count() > 0) {
      const text = await this.getTrimmedText(taxValue.first());
      return parseMoney(text).amount;
    }
    return 0;
  }

  /**
   * Get discount amount.
   */
  async getDiscount(): Promise<number> {
    const discountRow = this.locator('tr:has-text("Discount"), tr.discount');
    const discountValue = discountRow.locator('.woocommerce-Price-amount');
    
    if (await discountValue.count() > 0) {
      const text = await this.getTrimmedText(discountValue.first());
      return Math.abs(parseMoney(text).amount);
    }
    return 0;
  }

  /**
   * Get order total.
   */
  async getTotal(): Promise<number> {
    const totalRow = this.locator('tr.order_total, tr:has-text("Order Total")');
    const totalValue = totalRow.locator('.woocommerce-Price-amount');
    
    if (await totalValue.count() > 0) {
      const text = await this.getTrimmedText(totalValue.first());
      return parseMoney(text).amount;
    }
    
    // Fallback to total in header
    const headerTotal = this.locator('.woocommerce-order-data__heading .woocommerce-Price-amount');
    if (await headerTotal.count() > 0) {
      const text = await this.getTrimmedText(headerTotal);
      return parseMoney(text).amount;
    }
    
    return 0;
  }

  /**
   * Get order notes.
   */
  async getOrderNotes(): Promise<string[]> {
    const notes: string[] = [];
    const noteElements = this.locator('#woocommerce-order-notes .note_content, .order_notes .note_content');
    
    const count = await noteElements.count();
    for (let i = 0; i < count; i++) {
      const noteText = await this.getTrimmedText(noteElements.nth(i));
      notes.push(noteText);
    }
    
    return notes;
  }

  /**
   * Get complete order details.
   */
  async getOrderDetails(): Promise<AdminOrderDetails> {
    const details: AdminOrderDetails = {
      orderNumber: await this.getOrderNumber(),
      status: await this.getOrderStatus(),
      dateCreated: await this.getDateCreated(),
      billingEmail: await this.getBillingEmail(),
      billingName: await this.getBillingName(),
      billingAddress: await this.getBillingAddress(),
      shippingAddress: await this.getShippingAddress(),
      paymentMethod: await this.getPaymentMethod(),
      items: await this.getLineItems(),
      subtotal: await this.getSubtotal(),
      shipping: await this.getShipping(),
      tax: await this.getTax(),
      discount: await this.getDiscount(),
      total: await this.getTotal(),
      orderNotes: await this.getOrderNotes(),
    };

    this.logger.info('Retrieved admin order details', { orderNumber: details.orderNumber });
    return details;
  }

  /**
   * Update order status.
   */
  async updateStatus(newStatus: string): Promise<void> {
    const statusSelect = this.locator('#order_status, select[name="order_status"]');
    await statusSelect.selectOption(`wc-${newStatus}`);
    
    // Save order
    await this.saveOrder();
    this.logger.step(`Updated order status to: ${newStatus}`);
  }

  /**
   * Save/update the order.
   */
  async saveOrder(): Promise<void> {
    const saveButton = this.locator('button.save_order, #publish, button[name="save"]');
    await saveButton.click();
    await this.waitForOrderPageReady();
    
    // Wait for success notice
    const notice = this.locator('.notice-success, #message.updated');
    await notice.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
    
    this.logger.step('Order saved');
  }

  /**
   * Add an order note.
   */
  async addOrderNote(note: string, isCustomerNote = false): Promise<void> {
    const noteInput = this.locator('#add_order_note, textarea[name="order_note"]');
    await this.safeFill(noteInput, note);
    
    if (isCustomerNote) {
      const customerNoteCheckbox = this.locator('#order_note_type, select[name="order_note_type"]');
      if (await customerNoteCheckbox.count() > 0) {
        await customerNoteCheckbox.selectOption('customer');
      }
    }
    
    const addButton = this.locator('button.add_note, #add_note');
    await addButton.click();
    await this.waitForWooAjax();
    
    this.logger.step(`Added order note: ${note.substring(0, 50)}...`);
  }

  /**
   * Assert order total matches expected.
   */
  async assertTotal(expectedTotal: number, tolerance = 0.01): Promise<void> {
    const actualTotal = await this.getTotal();
    expect(
      moneyEquals(actualTotal, expectedTotal, tolerance),
      `Expected admin order total ${expectedTotal}, got ${actualTotal}`
    ).toBe(true);
    this.logger.assertion(`Order total ${actualTotal} matches expected`);
  }

  /**
   * Assert order status matches expected.
   */
  async assertStatus(expectedStatus: string): Promise<void> {
    const actualStatus = await this.getOrderStatus();
    expect(actualStatus.toLowerCase()).toContain(expectedStatus.toLowerCase());
    this.logger.assertion(`Order status "${actualStatus}" matches expected`);
  }

  /**
   * Assert order contains expected items.
   */
  async assertContainsItems(expectedItems: Array<{ name: string; quantity?: number }>): Promise<void> {
    const items = await this.getLineItems();
    
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
   * Assert payment method matches.
   */
  async assertPaymentMethod(expectedMethod: string): Promise<void> {
    const actualMethod = await this.getPaymentMethod();
    expect(actualMethod.toLowerCase()).toContain(expectedMethod.toLowerCase());
    this.logger.assertion(`Payment method matches: ${actualMethod}`);
  }

  /**
   * Take screenshot of order details.
   */
  async screenshotOrderDetails(): Promise<Buffer> {
    const orderNumber = await this.getOrderNumber();
    return this.screenshot(`admin-order-${orderNumber}`);
  }
}
