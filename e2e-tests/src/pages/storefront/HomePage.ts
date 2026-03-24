import { Page } from '@playwright/test';
import { BasePage } from '../BasePage';
import { getEnvConfig } from '../../config/env-loader';
import { WooSelectors } from '../../utils/selectors';

/**
 * Home/Shop page object for the WooCommerce storefront.
 */
export class HomePage extends BasePage {
  private readonly config = getEnvConfig();

  constructor(page: Page) {
    super(page);
  }

  async goto(): Promise<void> {
    await this.navigateTo(this.config.BASE_URL);
    this.logger.step('Navigated to home page');
  }

  getUrlPattern(): string | RegExp {
    return new RegExp(`^${this.config.BASE_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/?$`);
  }

  /**
   * Navigate to the shop/products page.
   */
  async goToShop(): Promise<void> {
    const shopLink = this.getByRole('link', { name: /shop/i });
    
    // Try role-based first, fallback to CSS
    if (await shopLink.count() > 0) {
      await shopLink.first().click();
    } else {
      await this.locator(WooSelectors.SHOP_LINK).first().click();
    }
    
    await this.waitForPageReady();
    this.logger.step('Navigated to shop page');
  }

  /**
   * Navigate to cart page.
   */
  async goToCart(): Promise<void> {
    const cartLink = this.getByRole('link', { name: /cart/i });
    
    if (await cartLink.count() > 0) {
      await cartLink.first().click();
    } else {
      await this.locator(WooSelectors.CART_LINK).first().click();
    }
    
    await this.waitForPageReady();
    this.logger.step('Navigated to cart page');
  }

  /**
   * Navigate to My Account page.
   */
  async goToMyAccount(): Promise<void> {
    const accountLink = this.getByRole('link', { name: /account|login|sign in/i });
    
    if (await accountLink.count() > 0) {
      await accountLink.first().click();
    } else {
      await this.locator(WooSelectors.ACCOUNT_LINK).first().click();
    }
    
    await this.waitForPageReady();
    this.logger.step('Navigated to My Account page');
  }

  /**
   * Search for a product.
   */
  async searchProduct(query: string): Promise<void> {
    const searchInput = this.getByRole('searchbox').or(
      this.getByPlaceholder(/search/i)
    ).or(
      this.locator('input[name="s"]')
    );

    await searchInput.first().fill(query);
    await searchInput.first().press('Enter');
    await this.waitForPageReady();
    this.logger.step(`Searched for product: ${query}`);
  }

  /**
   * Click on a product by name from the shop/home page.
   */
  async clickProduct(productName: string): Promise<void> {
    const productLink = this.getByRole('link', { name: productName });
    
    if (await productLink.count() > 0) {
      await productLink.first().click();
    } else {
      // Fallback: Find by text within product listing
      const productTitle = this.locator('.woocommerce-loop-product__title, .product-title')
        .filter({ hasText: productName });
      await productTitle.first().click();
    }
    
    await this.waitForPageReady();
    this.logger.step(`Clicked on product: ${productName}`);
  }

  /**
   * Navigate directly to a product by slug.
   */
  async goToProduct(productSlug: string): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/product/${productSlug}`);
    this.logger.step(`Navigated to product: ${productSlug}`);
  }

  /**
   * Navigate directly to a product by ID.
   */
  async goToProductById(productId: number): Promise<void> {
    await this.navigateTo(`${this.config.BASE_URL}/?p=${productId}`);
    this.logger.step(`Navigated to product ID: ${productId}`);
  }

  /**
   * Add a product to cart directly from the shop page (for simple products).
   */
  async addToCartFromListing(productName: string): Promise<void> {
    const productCard = this.locator('.product, .wc-block-grid__product')
      .filter({ hasText: productName });
    
    const addToCartButton = productCard.locator(
      'button.add_to_cart_button, a.add_to_cart_button, .add_to_cart_button'
    );

    await addToCartButton.click();
    await this.waitForWooAjax();
    this.logger.step(`Added "${productName}" to cart from listing`);
  }

  /**
   * Get the number of items in the cart (from header mini-cart).
   */
  async getCartItemCount(): Promise<number> {
    const cartCount = this.locator('.cart-contents-count, .cart-count, .mini-cart-count');
    
    if (await cartCount.count() > 0) {
      const text = await cartCount.first().textContent();
      return parseInt(text || '0', 10);
    }
    
    return 0;
  }

  /**
   * Check if user is logged in (by checking for logout/account links).
   */
  async isUserLoggedIn(): Promise<boolean> {
    const logoutLink = this.getByRole('link', { name: /log ?out/i });
    return await logoutLink.count() > 0;
  }

  /**
   * Get featured products from the homepage.
   */
  async getFeaturedProductNames(): Promise<string[]> {
    const productTitles = this.locator(
      '.woocommerce-loop-product__title, .wc-block-grid__product-title'
    );
    
    const titles: string[] = [];
    const count = await productTitles.count();
    
    for (let i = 0; i < count; i++) {
      const title = await productTitles.nth(i).textContent();
      if (title) {
        titles.push(title.trim());
      }
    }
    
    return titles;
  }
}
