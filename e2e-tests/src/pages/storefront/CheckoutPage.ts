import { Page, expect, Locator } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';
import { parseMoney, moneyEquals } from '../../utils/money';
import { waitForCheckoutProcessing, waitForWooAjax } from '../../utils/waits';

/**
 * Billing/shipping address data structure.
 */
export interface AddressData {
  firstName: string;
  lastName: string;
  company?: string;
  country: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  postcode: string;
  phone: string;
  email: string;
}

/**
 * Order review totals as displayed on checkout.
 */
export interface OrderReviewTotals {
  subtotal: number;
  shipping: number;
  tax: number;
  discount: number;
  total: number;
}

/**
 * Checkout page object.
 */
export class CheckoutPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/checkout`);
    await this.waitForCheckoutReady();
    this.logger.step('Navigated to checkout page');
  }

  getUrlPattern(): string | RegExp {
    return /\/checkout\/?/;
  }

  /**
   * Wait for checkout page to be fully loaded and interactive.
   */
  private async waitForCheckoutReady(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for checkout form to be visible
    const checkoutForm = this.locator(WooSelectors.CHECKOUT_FORM);
    await checkoutForm.waitFor({ state: 'visible', timeout: 15000 });
    
    // Wait for any initial AJAX to complete
    await this.waitForWooAjax();
  }

  /**
   * Fill billing address fields.
   */
  async fillBillingAddress(address: AddressData): Promise<void> {
    this.logger.step('Filling billing address');

    // First name
    await this.safeFill(this.locator(WooSelectors.BILLING_FIRST_NAME), address.firstName);
    
    // Last name
    await this.safeFill(this.locator(WooSelectors.BILLING_LAST_NAME), address.lastName);
    
    // Company (optional)
    if (address.company) {
      const companyField = this.locator(WooSelectors.BILLING_COMPANY);
      if (await companyField.isVisible()) {
        await this.safeFill(companyField, address.company);
      }
    }

    // Country - needs special handling for select2
    await this.selectCountry('billing', address.country);

    // Address line 1
    await this.safeFill(this.locator(WooSelectors.BILLING_ADDRESS_1), address.address1);
    
    // Address line 2 (optional)
    if (address.address2) {
      const address2Field = this.locator(WooSelectors.BILLING_ADDRESS_2);
      if (await address2Field.isVisible()) {
        await this.safeFill(address2Field, address.address2);
      }
    }

    // City
    await this.safeFill(this.locator(WooSelectors.BILLING_CITY), address.city);

    // State/County - needs special handling for select2
    await this.selectState('billing', address.state);

    // Postcode
    await this.safeFill(this.locator(WooSelectors.BILLING_POSTCODE), address.postcode);

    // Phone
    await this.safeFill(this.locator(WooSelectors.BILLING_PHONE), address.phone);

    // Email
    await this.safeFill(this.locator(WooSelectors.BILLING_EMAIL), address.email);

    // Wait for any address validation
    await this.waitForWooAjax();
    this.logger.step('Billing address filled');
  }

  /**
   * Map of common ISO country codes to full country names for Select2 search.
   */
  private static readonly countryNames: Record<string, string> = {
    'GB': 'United Kingdom',
    'US': 'United States',
    'DE': 'Germany',
    'FR': 'France',
    'ES': 'Spain',
    'IT': 'Italy',
    'CA': 'Canada',
    'AU': 'Australia',
    'NL': 'Netherlands',
    'BE': 'Belgium',
    'IE': 'Ireland',
    'AT': 'Austria',
    'CH': 'Switzerland',
  };

  /**
   * Select country handling WooCommerce Select2 dropdowns.
   */
  private async selectCountry(type: 'billing' | 'shipping', countryCode: string): Promise<void> {
    const selectId = `${type}_country`;
    const select = this.getById(selectId);
    
    // Get country name for searching
    const searchTerm = CheckoutPage.countryNames[countryCode.toUpperCase()] || countryCode;
    
    // Check if it's a select2 enhanced dropdown
    const select2Container = this.locator(`#select2-${selectId}-container`);
    
    if (await select2Container.isVisible({ timeout: 5000 }).catch(() => false)) {
      // Click to open select2 dropdown
      await select2Container.click();
      
      // Wait for dropdown to be visible
      await this.page.waitForSelector('.select2-dropdown', { state: 'visible', timeout: 5000 });
      
      // Type to search using country name
      const searchInput = this.locator('.select2-search__field');
      if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
        await searchInput.fill(searchTerm);
        // Wait for search results
        await this.page.waitForTimeout(500);
      }
      
      // Wait for options to load
      await this.page.waitForSelector('.select2-results__option', { state: 'visible', timeout: 5000 });
      
      // Find and click the matching option - try highlighted first, then any matching
      const highlightedOption = this.locator('.select2-results__option--highlighted');
      const matchingOption = this.locator('.select2-results__option')
        .filter({ hasText: new RegExp(searchTerm, 'i') })
        .first();
      
      if (await highlightedOption.isVisible({ timeout: 1000 }).catch(() => false)) {
        await highlightedOption.click();
      } else if (await matchingOption.isVisible({ timeout: 1000 }).catch(() => false)) {
        await matchingOption.click();
      } else {
        // Fallback: press Enter to select the first option
        await searchInput.press('Enter');
      }
    } else {
      // Regular select - use value attribute
      await select.selectOption({ value: countryCode });
    }
    
    await this.waitForWooAjax();
  }

  /**
   * Select state/province handling WooCommerce Select2 or text input.
   */
  private async selectState(type: 'billing' | 'shipping', state: string): Promise<void> {
    const selectId = `${type}_state`;
    const stateField = this.getById(selectId);
    
    // Wait for state field to be ready (may change after country selection)
    await this.page.waitForTimeout(500);
    
    // Check if state field exists and is visible
    if (!await stateField.isVisible({ timeout: 3000 }).catch(() => false)) {
      // Some countries don't have states - skip
      return;
    }
    
    // State field might be a select or text input depending on country
    const tagName = await stateField.evaluate(el => el.tagName.toLowerCase());
    
    if (tagName === 'select') {
      // Check if it's a select2
      const select2Container = this.locator(`#select2-${selectId}-container`);
      
      if (await select2Container.isVisible({ timeout: 2000 }).catch(() => false)) {
        await select2Container.click();
        
        // Wait for dropdown
        await this.page.waitForSelector('.select2-dropdown', { state: 'visible', timeout: 5000 });
        
        const searchInput = this.locator('.select2-search__field');
        if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
          await searchInput.fill(state);
          await this.page.waitForTimeout(300);
        }
        
        // Wait for options and select
        await this.page.waitForSelector('.select2-results__option', { state: 'visible', timeout: 5000 });
        
        const highlightedOption = this.locator('.select2-results__option--highlighted');
        const matchingOption = this.locator('.select2-results__option')
          .filter({ hasText: new RegExp(state, 'i') })
          .first();
        
        if (await highlightedOption.isVisible({ timeout: 1000 }).catch(() => false)) {
          await highlightedOption.click();
        } else if (await matchingOption.isVisible({ timeout: 1000 }).catch(() => false)) {
          await matchingOption.click();
        } else {
          // Press Enter to select first option
          await searchInput.press('Enter');
        }
      } else {
        await stateField.selectOption({ label: state });
      }
    } else {
      // Text input
      await this.safeFill(stateField, state);
    }
    
    await this.waitForWooAjax();
  }

  /**
   * Enable shipping to a different address.
   */
  async enableShipToDifferentAddress(): Promise<void> {
    const checkbox = this.locator(WooSelectors.SHIP_TO_DIFFERENT);
    await this.safeCheck(checkbox);
    await this.waitForWooAjax();
    this.logger.step('Enabled ship to different address');
  }

  /**
   * Set whether to ship to a different address.
   */
  async setShipToDifferentAddress(enable: boolean): Promise<void> {
    const checkbox = this.locator(WooSelectors.SHIP_TO_DIFFERENT);
    
    if (await checkbox.count() > 0) {
      const isChecked = await checkbox.isChecked();
      
      if (enable && !isChecked) {
        await this.safeCheck(checkbox);
        this.logger.step('Enabled ship to different address');
      } else if (!enable && isChecked) {
        await checkbox.uncheck();
        this.logger.step('Disabled ship to different address');
      }
      
      await this.waitForWooAjax();
    }
  }

  /**
   * Fill shipping address (when different from billing).
   */
  async fillShippingAddress(address: Omit<AddressData, 'phone' | 'email'>): Promise<void> {
    await this.enableShipToDifferentAddress();
    this.logger.step('Filling shipping address');

    await this.safeFill(this.locator(WooSelectors.SHIPPING_FIRST_NAME), address.firstName);
    await this.safeFill(this.locator(WooSelectors.SHIPPING_LAST_NAME), address.lastName);
    
    await this.selectCountry('shipping', address.country);
    await this.safeFill(this.locator('#shipping_address_1'), address.address1);
    
    if (address.address2) {
      await this.safeFill(this.locator('#shipping_address_2'), address.address2);
    }
    
    await this.safeFill(this.locator('#shipping_city'), address.city);
    await this.selectState('shipping', address.state);
    await this.safeFill(this.locator('#shipping_postcode'), address.postcode);

    await this.waitForWooAjax();
    this.logger.step('Shipping address filled');
  }

  /**
   * Add order notes.
   */
  async addOrderNotes(notes: string): Promise<void> {
    const notesField = this.locator(WooSelectors.ORDER_COMMENTS);
    if (await notesField.isVisible()) {
      await this.safeFill(notesField, notes);
      this.logger.step('Added order notes');
    }
  }

  /**
   * Get available payment methods.
   */
  async getAvailablePaymentMethods(): Promise<string[]> {
    const methods: string[] = [];
    const paymentItems = this.locator(`${WooSelectors.PAYMENT_METHODS} li.wc_payment_method`);
    
    const count = await paymentItems.count();
    for (let i = 0; i < count; i++) {
      const label = await paymentItems.nth(i).locator('label').textContent();
      if (label) {
        methods.push(label.trim());
      }
    }
    
    return methods;
  }

  /**
   * Select a payment method by ID or label.
   */
  async selectPaymentMethod(methodIdOrLabel: string): Promise<void> {
    // Try by ID first (e.g., 'cheque', 'bacs', 'stripe', 'wc_checkout_com_flow')
    const radioById = this.locator(`input[id="payment_method_${methodIdOrLabel}"]`);
    
    if (await radioById.count() > 0) {
      await radioById.check({ force: true });
      await this.waitForWooAjax();
      this.logger.step(`Selected payment method by ID: ${methodIdOrLabel}`);
      return;
    }
    
    // Try by label text - be more specific with the selector
    const paymentMethodItem = this.locator('.wc_payment_method, li.payment_method')
      .filter({ hasText: new RegExp(methodIdOrLabel, 'i') });
    
    if (await paymentMethodItem.count() > 0) {
      const radioInput = paymentMethodItem.first().locator('input[type="radio"]');
      if (await radioInput.count() > 0) {
        await radioInput.check({ force: true });
        await this.waitForWooAjax();
        this.logger.step(`Selected payment method by label: ${methodIdOrLabel}`);
        return;
      }
    }
    
    // If specific method not found, just use the first available (already selected by default)
    this.logger.step(`Payment method "${methodIdOrLabel}" not found, using default`);
  }

  /**
   * Fill credit card details for various payment gateways.
   * Supports Checkout.com Flow, Stripe, and generic card inputs.
   */
  async fillCardDetails(card: {
    number: string;
    expiry: string;
    cvc: string;
  }): Promise<void> {
    this.logger.step('Filling card details');
    
    // Wait for payment form to be ready
    await this.page.waitForTimeout(1000);
    
    // 1. Try Checkout.com Flow (iframe-based)
    const flowContainer = this.locator('#flow-container');
    if (await flowContainer.isVisible({ timeout: 3000 }).catch(() => false)) {
      await this.fillCheckoutComFlowCard(card);
      return;
    }
    
    // 2. Try Stripe Elements (iframe-based)
    if (await this.page.locator('iframe[name*="__privateStripeFrame"]').count() > 0) {
      await this.fillStripeCard(card);
      return;
    }
    
    // 3. Try generic direct card inputs
    await this.fillGenericCardInputs(card);
  }

  /**
   * Fill card details in Checkout.com Flow component.
   * Flow uses iframe-based inputs with name patterns like:
   * - cardholder-name__<uuid>
   * - card-number__<uuid>
   * - card-expiry-date__<uuid>
   * - card-cvv__<uuid>
   * 
   * Inside each iframe, the primary input can be identified by data-testid.
   */
  private async fillCheckoutComFlowCard(card: {
    number: string;
    expiry: string;
    cvc: string;
    name?: string;
  }): Promise<void> {
    this.logger.step('Filling Checkout.com Flow card');
    
    // Wait for Flow iframes to initialize
    await this.page.waitForSelector('iframe[name^="card-number__"]', { state: 'visible', timeout: 15000 });
    await this.page.waitForTimeout(2000); // Give Flow more time to initialize
    
    // Cardholder name iframe - required for most configurations
    try {
      const cardholderFrame = this.page.frameLocator('iframe[name^="cardholder-name__"]');
      const cardholderInput = cardholderFrame.getByTestId('cardholder-name');
      await cardholderInput.waitFor({ state: 'visible', timeout: 5000 });
      await cardholderInput.clear();
      await cardholderInput.type(card.name || 'Test User', { delay: 50 });
      this.logger.step('Filled cardholder name');
    } catch (e) {
      this.logger.warn('Cardholder name iframe not found: ' + (e as Error).message);
    }
    
    // Card Number iframe - use type() for better simulation
    try {
      const cardNumberFrame = this.page.frameLocator('iframe[name^="card-number__"]');
      const cardNumberInput = cardNumberFrame.getByTestId('card-number');
      await cardNumberInput.waitFor({ state: 'visible', timeout: 5000 });
      await cardNumberInput.clear();
      await cardNumberInput.type(card.number, { delay: 30 });
      this.logger.step('Filled card number');
    } catch (e) {
      this.logger.warn('Card number iframe not found: ' + (e as Error).message);
    }
    
    // Expiry date iframe - use type() for better simulation
    try {
      const expiryFrame = this.page.frameLocator('iframe[name^="card-expiry-date__"]');
      const expiryInput = expiryFrame.getByTestId('card-expiry-date');
      await expiryInput.waitFor({ state: 'visible', timeout: 5000 });
      await expiryInput.clear();
      // Flow expects MM/YY format
      await expiryInput.type(card.expiry, { delay: 50 });
      this.logger.step('Filled expiry date');
    } catch (e) {
      this.logger.warn('Expiry iframe not found: ' + (e as Error).message);
    }
    
    // CVV iframe - use type() for better simulation
    try {
      const cvvFrame = this.page.frameLocator('iframe[name^="card-cvv__"]');
      const cvvInput = cvvFrame.getByTestId('card-cvv');
      await cvvInput.waitFor({ state: 'visible', timeout: 5000 });
      await cvvInput.clear();
      await cvvInput.type(card.cvc, { delay: 50 });
      this.logger.step('Filled CVV');
    } catch (e) {
      this.logger.warn('CVV iframe not found: ' + (e as Error).message);
    }
    
    // Wait for Flow validation to complete
    await this.page.waitForTimeout(1500);
    this.logger.step('Checkout.com Flow card filled');
  }

  /**
   * Fill card details in Stripe Elements.
   */
  private async fillStripeCard(card: {
    number: string;
    expiry: string;
    cvc: string;
  }): Promise<void> {
    this.logger.step('Filling Stripe card');
    
    // Stripe has multiple frame patterns
    const cardNumberFrame = this.page.frameLocator('iframe[name*="__privateStripeFrame"][title*="card number" i]').first()
      .or(this.page.frameLocator('iframe[name*="stripe"]').first());
    
    try {
      await cardNumberFrame.locator('input[name="cardnumber"]').fill(card.number);
      await cardNumberFrame.locator('input[name="exp-date"]').fill(card.expiry);
      await cardNumberFrame.locator('input[name="cvc"]').fill(card.cvc);
    } catch {
      this.logger.warn('Stripe iframe fields not found');
    }
    
    this.logger.step('Stripe card filled');
  }

  /**
   * Fill generic card inputs (non-iframe).
   */
  private async fillGenericCardInputs(card: {
    number: string;
    expiry: string;
    cvc: string;
  }): Promise<void> {
    this.logger.step('Filling generic card inputs');
    
    const cardNumberInput = this.locator('input[id*="card-number"], input[name*="card_number"], input[id*="cardNumber"]');
    if (await cardNumberInput.count() > 0) {
      await cardNumberInput.fill(card.number);
    }
    
    const expiryInput = this.locator('input[id*="card-expiry"], input[name*="expiry"], input[id*="expiryDate"]');
    if (await expiryInput.count() > 0) {
      await expiryInput.fill(card.expiry);
    }
    
    const cvcInput = this.locator('input[id*="card-cvc"], input[name*="cvc"], input[name*="cvv"], input[id*="cvv"]');
    if (await cvcInput.count() > 0) {
      await cvcInput.fill(card.cvc);
    }
    
    this.logger.step('Generic card inputs filled');
  }

  /**
   * Get order review line items.
   */
  async getOrderReviewItems(): Promise<Array<{ name: string; quantity: number; total: number }>> {
    const items: Array<{ name: string; quantity: number; total: number }> = [];
    const rows = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} tbody tr.cart_item`);
    
    const count = await rows.count();
    for (let i = 0; i < count; i++) {
      const row = rows.nth(i);
      
      const nameCell = row.locator('.product-name');
      const nameText = await this.getTrimmedText(nameCell);
      
      // Parse name and quantity (format: "Product Name × 2")
      const match = nameText.match(/(.+?)\s*×\s*(\d+)/);
      const name = match ? match[1].trim() : nameText;
      const quantity = match ? parseInt(match[2], 10) : 1;
      
      const totalCell = row.locator('.product-total .woocommerce-Price-amount');
      const totalText = await this.getTrimmedText(totalCell);
      const total = parseMoney(totalText).amount;
      
      items.push({ name, quantity, total });
    }
    
    return items;
  }

  /**
   * Get order review totals.
   */
  async getOrderReviewTotals(): Promise<OrderReviewTotals> {
    const totals: OrderReviewTotals = {
      subtotal: 0,
      shipping: 0,
      tax: 0,
      discount: 0,
      total: 0,
    };

    // Subtotal
    const subtotalElement = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} .cart-subtotal .woocommerce-Price-amount`);
    if (await subtotalElement.count() > 0) {
      totals.subtotal = parseMoney(await this.getTrimmedText(subtotalElement)).amount;
    }

    // Shipping
    const shippingElement = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} .woocommerce-shipping-totals .woocommerce-Price-amount`);
    if (await shippingElement.count() > 0) {
      totals.shipping = parseMoney(await this.getTrimmedText(shippingElement.first())).amount;
    }

    // Tax
    const taxElement = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} .tax-rate .woocommerce-Price-amount, ${WooSelectors.ORDER_REVIEW_TABLE} .tax-total .woocommerce-Price-amount`);
    if (await taxElement.count() > 0) {
      totals.tax = parseMoney(await this.getTrimmedText(taxElement.first())).amount;
    }

    // Discount
    const discountElement = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} .cart-discount .woocommerce-Price-amount`);
    if (await discountElement.count() > 0) {
      totals.discount = Math.abs(parseMoney(await this.getTrimmedText(discountElement)).amount);
    }

    // Total
    const totalElement = this.locator(`${WooSelectors.ORDER_REVIEW_TABLE} .order-total .woocommerce-Price-amount`);
    totals.total = parseMoney(await this.getTrimmedText(totalElement)).amount;

    return totals;
  }

  /**
   * Select a shipping method.
   */
  async selectShippingMethod(methodLabel: string): Promise<void> {
    const shippingMethod = this.locator('.woocommerce-shipping-methods label')
      .filter({ hasText: new RegExp(methodLabel, 'i') });
    
    await shippingMethod.click();
    await this.waitForWooAjax();
    this.logger.step(`Selected shipping method: ${methodLabel}`);
  }

  /**
   * Accept terms and conditions if checkbox is present.
   */
  async acceptTermsAndConditions(): Promise<void> {
    // Common terms checkbox selectors
    const termsSelectors = [
      '#terms',
      '#agree-me',
      'input[name="terms"]',
      'input[name="agree-me"]',
    ];
    
    for (const selector of termsSelectors) {
      const checkbox = this.locator(selector);
      if (await checkbox.count() > 0 && await checkbox.isVisible({ timeout: 1000 }).catch(() => false)) {
        const isChecked = await checkbox.isChecked();
        if (!isChecked) {
          // Use JavaScript to directly set the checked state - most reliable for styled checkboxes
          await checkbox.evaluate((el: HTMLInputElement) => {
            el.checked = true;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.dispatchEvent(new Event('click', { bubbles: true }));
          });
          this.logger.step('Accepted terms and conditions');
        }
        return;
      }
    }
    
    // No terms checkbox found - that's ok, not all stores have one
  }

  /**
   * Place the order.
   */
  async placeOrder(): Promise<void> {
    this.logger.step('Placing order');
    
    // Accept terms if present
    await this.acceptTermsAndConditions();
    
    const placeOrderButton = this.getByRole('button', { name: /place order/i })
      .or(this.locator(WooSelectors.PLACE_ORDER_BUTTON));
    
    // Ensure button is visible and enabled
    await expect(placeOrderButton).toBeVisible();
    await expect(placeOrderButton).toBeEnabled();
    
    await placeOrderButton.click();
    
    // Wait for checkout processing
    await waitForCheckoutProcessing(this.page);
    
    this.logger.step('Order placed, waiting for result');
  }

  /**
   * Check if there are checkout errors.
   */
  async hasCheckoutErrors(): Promise<boolean> {
    const errorList = this.locator(WooSelectors.CHECKOUT_ERROR);
    return await errorList.count() > 0;
  }

  /**
   * Get checkout error messages.
   */
  async getCheckoutErrors(): Promise<string[]> {
    const errors: string[] = [];
    const errorList = this.locator(`${WooSelectors.CHECKOUT_ERROR} li`);
    
    const count = await errorList.count();
    for (let i = 0; i < count; i++) {
      const text = await errorList.nth(i).textContent();
      if (text) {
        errors.push(text.trim());
      }
    }
    
    // Also check for single error messages
    if (errors.length === 0) {
      const singleError = this.locator(WooSelectors.CHECKOUT_ERROR);
      if (await singleError.count() > 0) {
        const text = await singleError.textContent();
        if (text) {
          errors.push(text.trim());
        }
      }
    }
    
    return errors;
  }

  /**
   * Assert checkout total matches expected.
   */
  async assertTotal(expectedTotal: number, tolerance = 0.01): Promise<void> {
    const totals = await this.getOrderReviewTotals();
    expect(
      moneyEquals(totals.total, expectedTotal, tolerance),
      `Expected checkout total ${expectedTotal}, got ${totals.total}`
    ).toBe(true);
  }

  /**
   * Wait for order received page (successful order).
   */
  async waitForOrderReceived(timeout = 60000): Promise<void> {
    await this.page.waitForURL(/order-received|checkout\/order-received/, { timeout });
    await this.waitForPageReady();
    this.logger.step('Order received page loaded');
  }

  /**
   * Check if we're on the order received page.
   */
  async isOnOrderReceivedPage(): Promise<boolean> {
    const url = this.page.url();
    return url.includes('order-received') || url.includes('checkout/order-received');
  }

  /**
   * Apply a coupon code on checkout.
   */
  async applyCoupon(couponCode: string): Promise<void> {
    // Click to show coupon field if hidden
    const couponToggle = this.getByText(/have a coupon|click here to enter/i);
    if (await couponToggle.count() > 0) {
      await couponToggle.click();
    }
    
    const couponInput = this.locator('#coupon_code');
    await couponInput.fill(couponCode);
    
    const applyButton = this.getByRole('button', { name: /apply coupon/i });
    await applyButton.click();
    
    await this.waitForWooAjax();
    this.logger.step(`Applied coupon on checkout: ${couponCode}`);
  }

  /**
   * Check for payment processing indicators.
   */
  async isProcessingPayment(): Promise<boolean> {
    const processingIndicators = [
      '.blockUI.blockOverlay',
      '.woocommerce-checkout-payment.processing',
      '.processing',
    ];

    for (const selector of processingIndicators) {
      if (await this.locator(selector).count() > 0) {
        return true;
      }
    }

    return false;
  }

  /**
   * Wait for and handle 3DS/SCA authentication.
   * For Checkout.com sandbox, the password is 'Checkout1!'
   * 
   * The 3DS modal appears as a fullscreen iframe overlay - need to wait for it to appear after place order
   */
  async handle3DSAuthentication(password = 'Checkout1!', timeout = 30000): Promise<void> {
    this.logger.step('Waiting for 3DS authentication modal...');
    
    // Wait a bit longer for 3DS modal to appear after clicking Place Order
    // The modal is rendered dynamically by Checkout.com's SDK
    await this.page.waitForTimeout(5000);
    
    // The 3DS challenge appears in a fullscreen iframe/modal overlay
    // Look specifically for the Checkout.com 3DS challenge frame
    // It typically loads from a different domain (checkout.com 3DS simulator)
    
    try {
      // First, check if we already redirected to order-received (no 3DS needed)
      const currentUrl = this.page.url();
      if (currentUrl.includes('order-received')) {
        this.logger.step('Already on order-received page - no 3DS needed');
        return;
      }
      
      // Look for any visible iframe that might contain the 3DS challenge
      // The 3DS modal usually takes over the full page
      const allFrames = this.page.frames();
      this.logger.step(`Found ${allFrames.length} frames on page`);
      
      for (const frame of allFrames) {
        const frameUrl = frame.url();
        const frameName = frame.name();
        
        // Skip main frame and non-3DS frames
        if (frameUrl.includes('checkout.com') && 
            (frameUrl.includes('3ds') || frameUrl.includes('challenge') || frameUrl.includes('authenticate') || frameUrl.includes('simulator'))) {
          this.logger.step(`Found potential 3DS frame: ${frameName || 'unnamed'}, url: ${frameUrl.substring(0, 80)}`);
          
          // Look for password input in this frame
          const passwordInput = frame.locator('input[type="password"]');
          if (await passwordInput.count() > 0) {
            this.logger.step('3DS password input found in frame');
            await passwordInput.fill(password);
            await this.page.waitForTimeout(500);
            
            // Click Continue button
            const continueBtn = frame.locator('button:has-text("Continue"), button[type="submit"]');
            if (await continueBtn.count() > 0) {
              await continueBtn.first().click();
              this.logger.step('3DS password submitted');
              await this.page.waitForTimeout(5000);
              return;
            }
          }
        }
      }
      
      // Also try looking for a fullscreen iframe that appeared after place order
      // Checkout.com often creates an iframe for the 3DS challenge overlay
      const threeDSIframe = this.page.locator('iframe').filter({ has: this.page.locator('html') });
      const iframeCount = await this.page.locator('iframe').count();
      this.logger.step(`Total iframes on page: ${iframeCount}`);
      
      // Try each iframe to find the one with password input
      for (let i = 0; i < iframeCount; i++) {
        try {
          const iframe = this.page.locator('iframe').nth(i);
          const src = await iframe.getAttribute('src').catch(() => null);
          const name = await iframe.getAttribute('name').catch(() => null);
          
          // Only process iframes that might be 3DS related
          if (!src && !name) continue;
          
          // Create frame locator - try by index
          const frameLocator = this.page.frameLocator(`iframe >> nth=${i}`);
          
          // Check for password input
          const pwdInput = frameLocator.locator('input[type="password"]');
          const inputVisible = await pwdInput.isVisible({ timeout: 2000 }).catch(() => false);
          
          if (inputVisible) {
            this.logger.step(`Found 3DS password input in iframe ${i} (name: ${name}, src: ${src?.substring(0, 50)})`);
            await pwdInput.fill(password);
            
            // Find and click Continue/Submit button
            const submitBtn = frameLocator.locator('button:has-text("Continue"), button:has-text("Submit"), button[type="submit"]');
            if (await submitBtn.count() > 0) {
              await submitBtn.first().click();
              this.logger.step('3DS challenge completed');
              await this.page.waitForTimeout(5000);
              return;
            }
          }
        } catch (e) {
          // Continue to next iframe
        }
      }
      
    } catch (e) {
      this.logger.step('3DS handling error: ' + (e as Error).message);
    }
    
    this.logger.step('No 3DS authentication found or handled');
  }

  /**
   * Complete order with 3DS handling.
   * Combines placeOrder and handle3DSAuthentication.
   */
  async placeOrderWith3DS(threeDSPassword = 'Checkout1!'): Promise<void> {
    // Click place order but don't wait for redirect - 3DS might interrupt
    await this.acceptTermsAndConditions();
    
    const placeOrderButton = this.getById('place_order');
    await placeOrderButton.click();
    this.logger.step('Clicked place order, checking for 3DS...');
    
    // Wait for either order-received page OR 3DS challenge
    // Poll for 3DS modal or redirect
    const startTime = Date.now();
    const maxWaitTime = 30000;
    let handled3DS = false;
    
    while (Date.now() - startTime < maxWaitTime) {
      // Check if we're on order-received page
      const currentUrl = this.page.url();
      if (currentUrl.includes('order-received')) {
        this.logger.step('Order completed successfully (no 3DS)');
        return;
      }
      
      // Look for 3DS iframe - it should appear after place order
      // The modal is rendered inside an iframe that takes over the page
      try {
        // Get all frames
        const frames = this.page.frames();
        
        for (const frame of frames) {
          // Skip main frame
          if (frame === this.page.mainFrame()) continue;
          
          const url = frame.url();
          
          // Check if this is a 3DS frame
          if (url.includes('checkout.com') || url.includes('3ds') || url.includes('simulator')) {
            // Look for password input
            const pwdInput = frame.locator('input[type="password"], input[placeholder*="password"]');
            const pwdCount = await pwdInput.count();
            
            if (pwdCount > 0 && await pwdInput.first().isVisible({ timeout: 1000 }).catch(() => false)) {
              this.logger.step('Found 3DS password input in frame: ' + url.substring(0, 60));
              
              // Fill password
              await pwdInput.first().fill(threeDSPassword);
              await this.page.waitForTimeout(500);
              
              // Click Continue/Submit
              const submitBtn = frame.locator('button:has-text("Continue"), button:has-text("Submit"), input[type="submit"], button[type="submit"]');
              if (await submitBtn.count() > 0) {
                await submitBtn.first().click();
                this.logger.step('Submitted 3DS password');
                handled3DS = true;
                
                // Wait for 3DS to complete
                await this.page.waitForTimeout(5000);
                break;
              }
            }
          }
        }
        
        if (handled3DS) break;
        
      } catch (e) {
        // Continue polling
      }
      
      await this.page.waitForTimeout(1000);
    }
    
    if (!handled3DS) {
      this.logger.step('No 3DS challenge detected or already completed');
    }
  }
}
