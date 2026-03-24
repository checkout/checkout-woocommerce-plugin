import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';
import { parseMoney, moneyEquals } from '../../utils/money';
import { waitForCartUpdate } from '../../utils/waits';

/**
 * Cart item data structure.
 */
export interface CartItem {
  name: string;
  price: number;
  quantity: number;
  subtotal: number;
}

/**
 * Cart totals data structure.
 */
export interface CartTotals {
  subtotal: number;
  shipping?: number;
  tax?: number;
  discount?: number;
  total: number;
}

/**
 * Cart page object.
 */
export class CartPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/cart`);
    await this.waitForCartPageReady();
    this.logger.step('Navigated to cart page');
  }

  getUrlPattern(): string | RegExp {
    return /\/cart\/?/;
  }

  /**
   * Wait for cart page to be ready.
   */
  private async waitForCartPageReady(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for cart table or empty cart message
    const cartTable = this.locator(WooSelectors.CART_TABLE);
    const emptyCart = this.getByText(/cart is.*empty/i);
    
    await Promise.race([
      cartTable.waitFor({ state: 'visible', timeout: 10000 }),
      emptyCart.waitFor({ state: 'visible', timeout: 10000 }),
    ]).catch(() => {});
  }

  /**
   * Check if cart is empty.
   */
  async isCartEmpty(): Promise<boolean> {
    const emptyMessage = this.locator('.cart-empty, .wc-empty-cart-message');
    return await emptyMessage.count() > 0;
  }

  /**
   * Get number of unique items in cart.
   */
  async getItemCount(): Promise<number> {
    const items = this.locator(WooSelectors.CART_ITEM_ROW);
    return await items.count();
  }

  /**
   * Get all cart items.
   */
  async getCartItems(): Promise<CartItem[]> {
    const items: CartItem[] = [];
    const rows = this.locator(WooSelectors.CART_ITEM_ROW);
    const count = await rows.count();

    for (let i = 0; i < count; i++) {
      const row = rows.nth(i);
      
      // Get product name
      const nameElement = row.locator(WooSelectors.CART_ITEM_NAME);
      const name = await this.getTrimmedText(nameElement);
      
      // Get price
      const priceElement = row.locator(WooSelectors.CART_ITEM_PRICE);
      const priceText = await this.getTrimmedText(priceElement);
      const price = parseMoney(priceText).amount;
      
      // Get quantity
      const qtyInput = row.locator(WooSelectors.CART_ITEM_QUANTITY);
      const qtyValue = await qtyInput.inputValue();
      const quantity = parseInt(qtyValue, 10) || 1;
      
      // Get subtotal
      const subtotalElement = row.locator(WooSelectors.CART_ITEM_SUBTOTAL);
      const subtotalText = await this.getTrimmedText(subtotalElement);
      const subtotal = parseMoney(subtotalText).amount;

      items.push({ name, price, quantity, subtotal });
    }

    return items;
  }

  /**
   * Get a specific cart item by product name.
   */
  async getCartItem(productName: string): Promise<CartItem | null> {
    const items = await this.getCartItems();
    return items.find(item => item.name.includes(productName)) || null;
  }

  /**
   * Update quantity for a specific product.
   */
  async updateQuantity(productName: string, newQuantity: number): Promise<void> {
    const row = this.locator(WooSelectors.CART_ITEM_ROW)
      .filter({ hasText: productName });
    
    const qtyInput = row.locator(WooSelectors.CART_ITEM_QUANTITY);
    await qtyInput.clear();
    await qtyInput.fill(newQuantity.toString());
    
    // Click update cart button
    await this.clickUpdateCart();
    this.logger.step(`Updated "${productName}" quantity to ${newQuantity}`);
  }

  /**
   * Click the Update Cart button.
   */
  async clickUpdateCart(): Promise<void> {
    const updateButton = this.getByRole('button', { name: /update cart/i })
      .or(this.locator(WooSelectors.UPDATE_CART_BUTTON));
    
    // Wait for button to be enabled (WooCommerce disables it until changes)
    await updateButton.waitFor({ state: 'visible' });
    
    // Sometimes button needs to be enabled first
    try {
      await expect(updateButton).toBeEnabled({ timeout: 2000 });
    } catch {
      // Button might auto-enable after interaction
    }
    
    await updateButton.click();
    await waitForCartUpdate(this.page);
    await this.waitForWooAjax();
  }

  /**
   * Remove item from cart.
   */
  async removeItem(productName: string): Promise<void> {
    const row = this.locator(WooSelectors.CART_ITEM_ROW)
      .filter({ hasText: productName });
    
    const removeButton = row.locator('.remove, a.remove');
    await removeButton.click();
    await this.waitForWooAjax();
    await this.waitForPageReady();
    this.logger.step(`Removed "${productName}" from cart`);
  }

  /**
   * Apply a coupon code.
   */
  async applyCoupon(couponCode: string): Promise<void> {
    const couponInput = this.locator(WooSelectors.COUPON_INPUT);
    await couponInput.fill(couponCode);
    
    const applyButton = this.getByRole('button', { name: /apply coupon/i })
      .or(this.locator(WooSelectors.APPLY_COUPON));
    
    await applyButton.click();
    await this.waitForWooAjax();
    await waitForCartUpdate(this.page);
    this.logger.step(`Applied coupon: ${couponCode}`);
  }

  /**
   * Check if coupon was applied successfully.
   */
  async isCouponApplied(couponCode: string): Promise<boolean> {
    // Check for success message first
    const successMessage = this.locator('.woocommerce-message').filter({ hasText: /coupon.*applied|applied.*successfully/i });
    if (await successMessage.count() > 0) {
      return true;
    }
    
    // Check for discount row in cart totals
    const couponRow = this.locator(WooSelectors.CART_DISCOUNT)
      .or(this.locator(`.coupon-${couponCode.toLowerCase()}`))
      .or(this.locator(`tr.cart-discount, tr[data-title="Coupon"], .cart-discount`));
    
    if (await couponRow.count() > 0) {
      return true;
    }
    
    // Check if any discount is visible in the cart totals
    const discountRow = this.locator('tr.cart-discount .woocommerce-Price-amount');
    return await discountRow.count() > 0;
  }

  /**
   * Get the discount amount from applied coupon.
   */
  async getCouponDiscount(): Promise<number> {
    const discountElement = this.locator(WooSelectors.CART_DISCOUNT);
    
    if (await discountElement.count() > 0) {
      const discountText = await this.getTrimmedText(discountElement);
      // Discount is usually shown as negative
      const amount = parseMoney(discountText).amount;
      return Math.abs(amount);
    }
    
    return 0;
  }

  /**
   * Remove an applied coupon.
   */
  async removeCoupon(couponCode: string): Promise<void> {
    const removeLink = this.locator(`.coupon-${couponCode.toLowerCase()} .woocommerce-remove-coupon`)
      .or(this.locator(`a[data-coupon="${couponCode.toLowerCase()}"]`));
    
    if (await removeLink.count() > 0) {
      await removeLink.click();
      await this.waitForWooAjax();
      await waitForCartUpdate(this.page);
      this.logger.step(`Removed coupon: ${couponCode}`);
    }
  }

  /**
   * Get cart subtotal.
   */
  async getSubtotal(): Promise<number> {
    const subtotalElement = this.locator(WooSelectors.CART_SUBTOTAL);
    const subtotalText = await this.getTrimmedText(subtotalElement);
    return parseMoney(subtotalText).amount;
  }

  /**
   * Get cart total.
   */
  async getTotal(): Promise<number> {
    const totalElement = this.locator(WooSelectors.CART_TOTAL);
    const totalText = await this.getTrimmedText(totalElement);
    return parseMoney(totalText).amount;
  }

  /**
   * Get all cart totals.
   */
  async getCartTotals(): Promise<CartTotals> {
    const totals: CartTotals = {
      subtotal: 0,
      total: 0,
    };

    // Scroll to cart totals section to make sure it's visible
    const cartTotalsSection = this.locator('.cart_totals, .cart-collaterals');
    if (await cartTotalsSection.count() > 0) {
      await cartTotalsSection.first().scrollIntoViewIfNeeded();
      await this.page.waitForTimeout(500);
    }

    // Subtotal
    totals.subtotal = await this.getSubtotal();

    // Shipping (may not be visible until address entered)
    const shippingElement = this.locator('.woocommerce-shipping-totals td .woocommerce-Price-amount');
    if (await shippingElement.count() > 0) {
      const shippingText = await this.getTrimmedText(shippingElement.first());
      totals.shipping = parseMoney(shippingText).amount;
    }

    // Tax
    const taxElement = this.locator('.tax-rate .woocommerce-Price-amount, .tax-total .woocommerce-Price-amount');
    if (await taxElement.count() > 0) {
      const taxText = await this.getTrimmedText(taxElement.first());
      totals.tax = parseMoney(taxText).amount;
    }

    // Discount - check multiple possible selectors
    const discountSelectors = [
      WooSelectors.CART_DISCOUNT,
      '.cart-discount .woocommerce-Price-amount bdi',
      '.cart-discount td .woocommerce-Price-amount',
      'tr.cart-discount .woocommerce-Price-amount',
      '[data-title="Coupon"] .woocommerce-Price-amount',
    ];
    
    for (const selector of discountSelectors) {
      const discountElement = this.locator(selector);
      if (await discountElement.count() > 0) {
        const discountText = await this.getTrimmedText(discountElement.first());
        totals.discount = Math.abs(parseMoney(discountText).amount);
        break;
      }
    }

    // Total
    totals.total = await this.getTotal();

    return totals;
  }

  /**
   * Proceed to checkout.
   */
  async proceedToCheckout(): Promise<void> {
    const checkoutButton = this.getByRole('link', { name: /checkout/i })
      .or(this.locator(WooSelectors.PROCEED_TO_CHECKOUT_BUTTON));
    
    await checkoutButton.first().click();
    await this.waitForPageReady();
    this.logger.step('Proceeded to checkout');
  }

  /**
   * Continue shopping (go back to shop).
   */
  async continueShopping(): Promise<void> {
    const continueLink = this.getByRole('link', { name: /continue shopping|return to shop/i });
    await continueLink.first().click();
    await this.waitForPageReady();
    this.logger.step('Continuing shopping');
  }

  /**
   * Assert cart contains expected items.
   */
  async assertCartContains(expectedProducts: string[]): Promise<void> {
    const items = await this.getCartItems();
    const itemNames = items.map(item => item.name);

    for (const product of expectedProducts) {
      const found = itemNames.some(name => name.includes(product));
      expect(found, `Expected cart to contain "${product}"`).toBe(true);
    }
  }

  /**
   * Assert cart total matches expected value.
   */
  async assertTotal(expectedTotal: number, tolerance = 0.01): Promise<void> {
    const actualTotal = await this.getTotal();
    expect(
      moneyEquals(actualTotal, expectedTotal, tolerance),
      `Expected total ${expectedTotal}, got ${actualTotal}`
    ).toBe(true);
  }

  /**
   * Assert subtotal matches expected value.
   */
  async assertSubtotal(expectedSubtotal: number, tolerance = 0.01): Promise<void> {
    const actualSubtotal = await this.getSubtotal();
    expect(
      moneyEquals(actualSubtotal, expectedSubtotal, tolerance),
      `Expected subtotal ${expectedSubtotal}, got ${actualSubtotal}`
    ).toBe(true);
  }

  /**
   * Check for any error messages on the cart page.
   */
  async getErrorMessages(): Promise<string[]> {
    const errors: string[] = [];
    const errorElements = this.locator(WooSelectors.WOO_ERROR);
    
    const count = await errorElements.count();
    for (let i = 0; i < count; i++) {
      const text = await errorElements.nth(i).textContent();
      if (text) {
        errors.push(text.trim());
      }
    }
    
    return errors;
  }

  /**
   * Check for success messages.
   */
  async getSuccessMessages(): Promise<string[]> {
    const messages: string[] = [];
    const messageElements = this.locator(WooSelectors.WOO_MESSAGE);
    
    const count = await messageElements.count();
    for (let i = 0; i < count; i++) {
      const text = await messageElements.nth(i).textContent();
      if (text) {
        messages.push(text.trim());
      }
    }
    
    return messages;
  }
}
