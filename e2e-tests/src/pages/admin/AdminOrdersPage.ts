import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { parseMoney } from '../../utils/money';

/**
 * Order list item from admin orders table.
 */
export interface AdminOrderListItem {
  orderNumber: string;
  date: string;
  status: string;
  billingName: string;
  total: number;
}

/**
 * WooCommerce Admin Orders list page object.
 */
export class AdminOrdersPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(`${this.config.ADMIN_URL}/edit.php?post_type=shop_order`);
    await this.waitForOrdersPageReady();
    this.logger.step('Navigated to admin orders page');
  }

  getUrlPattern(): string | RegExp {
    return /edit\.php\?post_type=shop_order|admin\.php\?page=wc-orders/;
  }

  /**
   * Wait for orders page to be ready.
   */
  private async waitForOrdersPageReady(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for orders table or HPOS table
    const ordersTable = this.locator('.wp-list-table, #wc-orders-list');
    await ordersTable.waitFor({ state: 'visible', timeout: 15000 });
  }

  /**
   * Navigate to orders page via WooCommerce menu.
   */
  async goToOrdersViaMenu(): Promise<void> {
    // Click WooCommerce menu
    const wcMenu = this.locator('#adminmenu a.menu-top[href*="woocommerce"]');
    await wcMenu.click();

    // Click Orders submenu
    const ordersMenu = this.locator('#adminmenu a[href*="shop_order"], #adminmenu a[href*="wc-orders"]');
    await ordersMenu.first().click();

    await this.waitForOrdersPageReady();
    this.logger.step('Navigated to orders via menu');
  }

  /**
   * Search for orders by keyword (order number, customer name, email).
   */
  async searchOrders(searchTerm: string): Promise<void> {
    const searchInput = this.locator('#post-search-input, #wc-orders-search-input, input[name="s"]');
    await this.safeFill(searchInput, searchTerm);

    const searchButton = this.locator('#search-submit, button[type="submit"]');
    await searchButton.click();

    await this.waitForOrdersPageReady();
    this.logger.step(`Searched for orders: ${searchTerm}`);
  }

  /**
   * Filter orders by status.
   */
  async filterByStatus(status: string): Promise<void> {
    // Click the status link in the subsubsub menu
    const statusLink = this.locator(`.subsubsub a[href*="post_status=${status}"], .subsubsub a[href*="status=${status}"]`);
    
    if (await statusLink.count() > 0) {
      await statusLink.click();
      await this.waitForOrdersPageReady();
      this.logger.step(`Filtered orders by status: ${status}`);
    } else {
      // Try using the dropdown filter
      const filterDropdown = this.locator('select[name="post_status"], select[name="_shop_order_status"]');
      if (await filterDropdown.count() > 0) {
        await filterDropdown.selectOption(status);
        const filterButton = this.locator('#post-query-submit, button[type="submit"]');
        await filterButton.click();
        await this.waitForOrdersPageReady();
      }
    }
  }

  /**
   * Get all orders from the current page.
   */
  async getOrders(): Promise<AdminOrderListItem[]> {
    const orders: AdminOrderListItem[] = [];
    
    // Handle both classic and HPOS order tables
    const orderRows = this.locator('tr.type-shop_order, tr.order');
    const count = await orderRows.count();

    for (let i = 0; i < count; i++) {
      const row = orderRows.nth(i);
      
      // Order number
      const orderLink = row.locator('.order-view, .column-order_number a, td.order_number a');
      const orderText = await this.getTrimmedText(orderLink);
      const orderNumber = orderText.replace(/[^0-9]/g, '');

      // Date
      const dateCell = row.locator('.column-order_date, td.date');
      const date = await this.getTrimmedText(dateCell);

      // Status
      const statusCell = row.locator('.column-order_status mark, td.order_status mark');
      const status = await statusCell.getAttribute('data-tip') || await this.getTrimmedText(statusCell);

      // Billing name
      const billingCell = row.locator('.column-billing_address, td.billing_address');
      const billingName = await this.getTrimmedText(billingCell);

      // Total
      const totalCell = row.locator('.column-order_total, td.order_total');
      const totalText = await this.getTrimmedText(totalCell);
      const total = parseMoney(totalText).amount;

      orders.push({
        orderNumber,
        date,
        status: status.toLowerCase(),
        billingName,
        total,
      });
    }

    return orders;
  }

  /**
   * Find order by order number.
   */
  async findOrder(orderNumber: string): Promise<AdminOrderListItem | null> {
    await this.searchOrders(orderNumber);
    const orders = await this.getOrders();
    return orders.find(o => o.orderNumber === orderNumber) || null;
  }

  /**
   * Find order by customer email.
   */
  async findOrderByEmail(email: string): Promise<AdminOrderListItem | null> {
    await this.searchOrders(email);
    const orders = await this.getOrders();
    return orders.length > 0 ? orders[0] : null;
  }

  /**
   * Click on an order to view details.
   */
  async openOrder(orderNumber: string): Promise<void> {
    // First search for the order
    await this.searchOrders(orderNumber);

    // Click on the order link
    const orderLink = this.locator(`a:has-text("#${orderNumber}"), a.order-view:has-text("${orderNumber}")`);
    
    if (await orderLink.count() > 0) {
      await orderLink.first().click();
      await this.waitForPageReady();
      this.logger.step(`Opened order ${orderNumber}`);
    } else {
      throw new Error(`Order ${orderNumber} not found`);
    }
  }

  /**
   * Get order count from status links.
   */
  async getOrderCountByStatus(status: string): Promise<number> {
    const statusLink = this.locator(`.subsubsub a[href*="${status}"] .count`);
    if (await statusLink.count() > 0) {
      const text = await this.getTrimmedText(statusLink);
      const match = text.match(/\d+/);
      return match ? parseInt(match[0], 10) : 0;
    }
    return 0;
  }

  /**
   * Get total orders count.
   */
  async getTotalOrdersCount(): Promise<number> {
    const countElement = this.locator('.displaying-num');
    if (await countElement.count() > 0) {
      const text = await this.getTrimmedText(countElement);
      const match = text.match(/\d+/);
      return match ? parseInt(match[0], 10) : 0;
    }
    return (await this.getOrders()).length;
  }

  /**
   * Check if an order exists in the list.
   */
  async orderExists(orderNumber: string): Promise<boolean> {
    await this.searchOrders(orderNumber);
    const orders = await this.getOrders();
    return orders.some(o => o.orderNumber === orderNumber);
  }

  /**
   * Assert order exists with expected status.
   */
  async assertOrderExists(orderNumber: string, expectedStatus?: string): Promise<void> {
    const order = await this.findOrder(orderNumber);
    expect(order, `Order ${orderNumber} should exist`).not.toBeNull();
    
    if (expectedStatus && order) {
      expect(order.status).toContain(expectedStatus.toLowerCase());
    }
    
    this.logger.assertion(`Order ${orderNumber} exists`);
  }

  /**
   * Bulk action on selected orders.
   */
  async bulkAction(action: string, orderNumbers: string[]): Promise<void> {
    // Select orders
    for (const orderNumber of orderNumbers) {
      const checkbox = this.locator(`input[name="id[]"][value="${orderNumber}"], input[name="post[]"][value="${orderNumber}"]`);
      await this.safeCheck(checkbox);
    }

    // Select action
    const bulkSelect = this.locator('#bulk-action-selector-top');
    await bulkSelect.selectOption(action);

    // Apply
    const applyButton = this.locator('#doaction');
    await applyButton.click();

    await this.waitForOrdersPageReady();
    this.logger.step(`Applied bulk action "${action}" to ${orderNumbers.length} orders`);
  }

  /**
   * Take screenshot of orders list.
   */
  async screenshotOrdersList(): Promise<Buffer> {
    return this.screenshot('admin-orders-list');
  }
}
