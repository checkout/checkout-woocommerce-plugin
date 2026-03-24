import { test, expect } from '@playwright/test';
import { getEnvConfig } from '../../src/config/env-loader';

/**
 * Diagnostic test to understand Checkout.com Flow card input structure.
 */
test.describe('Checkout.com Flow Card Test', () => {
  test('inspect Flow card input structure', async ({ page }) => {
    const config = getEnvConfig();
    
    // Add a product to cart
    console.log('Going to Album product page...');
    await page.goto(`${config.BASE_URL}/product/album/`);
    
    // Add to cart
    const addToCartButton = page.locator('button[name="add-to-cart"], .single_add_to_cart_button');
    await expect(addToCartButton).toBeVisible({ timeout: 10000 });
    await addToCartButton.click();
    console.log('Added to cart');
    
    // Go to checkout
    await page.goto(`${config.BASE_URL}/checkout/`);
    await page.waitForLoadState('networkidle');
    console.log('On checkout page');
    
    // Wait for checkout form
    await expect(page.locator('form.checkout, form.woocommerce-checkout')).toBeVisible({ timeout: 15000 });
    
    // Fill minimal billing info
    await page.fill('#billing_first_name', 'Test');
    await page.fill('#billing_last_name', 'User');
    await page.fill('#billing_address_1', '123 Test St');
    await page.fill('#billing_city', 'London');
    await page.fill('#billing_postcode', 'W1A 1AA');
    await page.fill('#billing_phone', '07700900000');
    await page.fill('#billing_email', 'test@example.com');
    console.log('Filled billing info');
    
    // Wait for AJAX
    await page.waitForTimeout(2000);
    
    // Select Checkout.com Flow payment method
    const flowRadio = page.locator('input#payment_method_wc_checkout_com_flow');
    if (await flowRadio.isVisible({ timeout: 5000 })) {
      await flowRadio.check({ force: true });
      console.log('Selected Checkout.com Flow payment');
    } else {
      console.log('Checkout.com Flow payment method not visible');
      // List available payment methods
      const methods = await page.locator('.wc_payment_method label').allTextContents();
      console.log('Available payment methods:', methods);
    }
    
    // Wait for Flow to load
    await page.waitForTimeout(3000);
    
    // Check for flow container
    const flowContainer = page.locator('#flow-container');
    const flowVisible = await flowContainer.isVisible();
    console.log('Flow container visible:', flowVisible);
    
    // List all iframes in the flow container
    const iframes = await page.locator('#flow-container iframe').all();
    console.log('Number of iframes in flow container:', iframes.length);
    
    for (let i = 0; i < iframes.length; i++) {
      const iframe = iframes[i];
      const name = await iframe.getAttribute('name');
      const title = await iframe.getAttribute('title');
      const src = await iframe.getAttribute('src');
      console.log(`Iframe ${i}: name="${name}", title="${title}", src="${src?.substring(0, 100)}..."`);
    }
    
    // Try to find card inputs directly in page (not iframe)
    const directInputs = await page.locator('#flow-container input').all();
    console.log('Direct inputs in flow container:', directInputs.length);
    for (const input of directInputs) {
      const id = await input.getAttribute('id');
      const name = await input.getAttribute('name');
      const type = await input.getAttribute('type');
      console.log(`Direct input: id="${id}", name="${name}", type="${type}"`);
    }
    
    // Take screenshot
    await page.screenshot({ path: 'test-results/flow-card-structure.png', fullPage: true });
    console.log('Screenshot saved');
    
    // Log the HTML structure of flow container
    if (flowVisible) {
      const flowHtml = await flowContainer.innerHTML();
      console.log('Flow container HTML (first 2000 chars):', flowHtml.substring(0, 2000));
    }
  });
});
