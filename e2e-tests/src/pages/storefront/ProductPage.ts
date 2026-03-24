import { Page, expect } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';
import { parseMoney } from '../../utils/money';

/**
 * Product details page object.
 */
export class ProductPage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    throw new Error('ProductPage requires a product slug. Use goToProduct(slug) instead.');
  }

  getUrlPattern(): string | RegExp {
    return /\/product\//;
  }

  /**
   * Navigate to a specific product by slug.
   */
  async goToProduct(productSlug: string): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/product/${productSlug}`);
    await this.waitForProductPageReady();
    this.logger.step(`Navigated to product: ${productSlug}`);
  }

  /**
   * Wait for product page to be fully loaded.
   */
  private async waitForProductPageReady(): Promise<void> {
    await this.waitForPageReady();
    
    // Wait for key product elements
    const productTitle = this.locator(WooSelectors.PRODUCT_TITLE);
    await productTitle.waitFor({ state: 'visible', timeout: 10000 });
  }

  /**
   * Get the product title.
   */
  async getProductTitle(): Promise<string> {
    const title = this.locator(WooSelectors.PRODUCT_TITLE);
    return this.getTrimmedText(title);
  }

  /**
   * Get the product price.
   */
  async getProductPrice(): Promise<number> {
    const priceElement = this.locator(WooSelectors.PRODUCT_PRICE).first();
    const priceText = await this.getTrimmedText(priceElement);
    return parseMoney(priceText).amount;
  }

  /**
   * Get sale price if product is on sale.
   */
  async getSalePrice(): Promise<number | null> {
    const salePrice = this.locator('.price ins .woocommerce-Price-amount');
    if (await salePrice.count() > 0) {
      const priceText = await this.getTrimmedText(salePrice.first());
      return parseMoney(priceText).amount;
    }
    return null;
  }

  /**
   * Get regular price (may be crossed out if on sale).
   */
  async getRegularPrice(): Promise<number | null> {
    const regularPrice = this.locator('.price del .woocommerce-Price-amount');
    if (await regularPrice.count() > 0) {
      const priceText = await this.getTrimmedText(regularPrice.first());
      return parseMoney(priceText).amount;
    }
    return null;
  }

  /**
   * Check if product is on sale.
   */
  async isOnSale(): Promise<boolean> {
    const saleTag = this.locator('.onsale');
    return await saleTag.count() > 0;
  }

  /**
   * Get current quantity value.
   */
  async getQuantity(): Promise<number> {
    const qtyInput = this.locator(WooSelectors.QUANTITY_INPUT);
    const value = await qtyInput.inputValue();
    return parseInt(value, 10) || 1;
  }

  /**
   * Set the product quantity.
   */
  async setQuantity(quantity: number): Promise<void> {
    const qtyInput = this.locator(WooSelectors.QUANTITY_INPUT);
    await qtyInput.clear();
    await qtyInput.fill(quantity.toString());
    this.logger.step(`Set quantity to: ${quantity}`);
  }

  /**
   * Increment quantity using + button if available.
   */
  async incrementQuantity(times = 1): Promise<void> {
    const incrementButton = this.locator('.plus, button[aria-label*="increase"], .quantity-plus');
    
    for (let i = 0; i < times; i++) {
      if (await incrementButton.count() > 0) {
        await incrementButton.click();
      } else {
        const currentQty = await this.getQuantity();
        await this.setQuantity(currentQty + 1);
        break;
      }
    }
  }

  /**
   * Select a product variation (for variable products).
   */
  async selectVariation(attributeName: string, value: string): Promise<void> {
    // Try to find by label first
    const selectByLabel = this.getByLabel(new RegExp(attributeName, 'i'));
    
    if (await selectByLabel.count() > 0) {
      await selectByLabel.selectOption({ label: value });
    } else {
      // Fallback to attribute-based selector
      const select = this.locator(`select[name*="${attributeName.toLowerCase()}"], #${attributeName.toLowerCase()}`);
      await select.selectOption({ label: value });
    }
    
    await this.waitForWooAjax();
    this.logger.step(`Selected variation: ${attributeName} = ${value}`);
  }

  /**
   * Select multiple variations.
   */
  async selectVariations(variations: Record<string, string>): Promise<void> {
    for (const [attribute, value] of Object.entries(variations)) {
      await this.selectVariation(attribute, value);
    }
  }

  /**
   * Click the Add to Cart button.
   */
  async addToCart(): Promise<void> {
    const addToCartButton = this.getByRole('button', { name: /add to cart/i });
    
    // Fallback to CSS selector
    const button = addToCartButton.or(this.locator(WooSelectors.ADD_TO_CART_BUTTON));
    
    await button.first().click();
    await this.waitForWooAjax();
    this.logger.step('Clicked Add to Cart');
  }

  /**
   * Add product to cart and wait for confirmation.
   */
  async addToCartWithConfirmation(): Promise<void> {
    await this.addToCart();
    
    // Wait for "added to cart" message or view cart button
    const viewCartLink = this.getByRole('link', { name: /view cart/i });
    const successMessage = this.locator('.woocommerce-message');
    
    await Promise.race([
      viewCartLink.waitFor({ state: 'visible', timeout: 10000 }),
      successMessage.waitFor({ state: 'visible', timeout: 10000 }),
    ]).catch(() => {
      // Some themes redirect directly to cart
    });
    
    this.logger.step('Product added to cart with confirmation');
  }

  /**
   * Check if product is in stock.
   */
  async isInStock(): Promise<boolean> {
    const outOfStock = this.locator('.out-of-stock');
    const inStock = this.locator('.in-stock');
    
    if (await outOfStock.count() > 0) {
      return false;
    }
    
    if (await inStock.count() > 0) {
      return true;
    }
    
    // Check if add to cart button is available
    const addToCartButton = this.locator(WooSelectors.ADD_TO_CART_BUTTON);
    return await addToCartButton.isEnabled();
  }

  /**
   * Get stock status text.
   */
  async getStockStatus(): Promise<string> {
    const stockElement = this.locator('.stock');
    if (await stockElement.count() > 0) {
      return this.getTrimmedText(stockElement);
    }
    return '';
  }

  /**
   * Get product SKU.
   */
  async getSku(): Promise<string> {
    const skuElement = this.locator('.sku_wrapper .sku, .sku');
    if (await skuElement.count() > 0) {
      return this.getTrimmedText(skuElement);
    }
    return '';
  }

  /**
   * Get product categories.
   */
  async getCategories(): Promise<string[]> {
    const categoryLinks = this.locator('.posted_in a');
    const categories: string[] = [];
    
    const count = await categoryLinks.count();
    for (let i = 0; i < count; i++) {
      const text = await categoryLinks.nth(i).textContent();
      if (text) {
        categories.push(text.trim());
      }
    }
    
    return categories;
  }

  /**
   * Get product short description.
   */
  async getShortDescription(): Promise<string> {
    const shortDesc = this.locator('.woocommerce-product-details__short-description');
    if (await shortDesc.count() > 0) {
      return this.getTrimmedText(shortDesc);
    }
    return '';
  }

  /**
   * Check if view cart link is visible (after adding to cart).
   */
  async isViewCartLinkVisible(): Promise<boolean> {
    const viewCartLink = this.getByRole('link', { name: /view cart/i });
    return await viewCartLink.isVisible().catch(() => false);
  }

  /**
   * Click view cart link after adding product.
   */
  async goToCartFromProduct(): Promise<void> {
    const viewCartLink = this.getByRole('link', { name: /view cart/i });
    await viewCartLink.click();
    await this.waitForPageReady();
    this.logger.step('Navigated to cart from product page');
  }

  /**
   * Assert product is displayed correctly.
   */
  async assertProductDisplayed(expectedTitle: string): Promise<void> {
    const title = await this.getProductTitle();
    expect(title).toContain(expectedTitle);
    
    // Verify price is visible
    const priceElement = this.locator(WooSelectors.PRODUCT_PRICE);
    await expect(priceElement.first()).toBeVisible();
  }
}
