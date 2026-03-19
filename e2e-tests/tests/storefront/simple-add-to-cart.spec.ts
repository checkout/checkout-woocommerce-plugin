import { test, expect } from '@playwright/test';
import { getEnvConfig } from '../../src/config/env-loader';

const config = getEnvConfig();

/**
 * Simple test to verify basic store functionality.
 * Run with: npx playwright test tests/storefront/simple-add-to-cart.spec.ts --headed
 */
test.describe('Basic Store Test', () => {

  test('Step 1: Add Album to cart', async ({ page }) => {
    // Go to the Album product page
    console.log('Going to Album product page...');
    await page.goto(`${config.BASE_URL}/product/album/`);
    
    // Wait for page to load
    await page.waitForLoadState('domcontentloaded');
    
    // Take screenshot to see what's on the page
    await page.screenshot({ path: 'test-results/1-product-page.png' });
    
    // Find and click Add to Cart button
    console.log('Looking for Add to Cart button...');
    const addToCartButton = page.locator('button[name="add-to-cart"], .single_add_to_cart_button');
    await expect(addToCartButton).toBeVisible({ timeout: 10000 });
    
    await addToCartButton.click();
    console.log('Clicked Add to Cart');
    
    // Wait for cart to update
    await page.waitForTimeout(2000);
    await page.screenshot({ path: 'test-results/2-after-add-to-cart.png' });
    
    console.log('✅ Step 1 complete: Product added to cart');
  });

  test('Step 2: Go to checkout and fill billing', async ({ page }) => {
    // First add product to cart
    await page.goto(`${config.BASE_URL}/product/album/`);
    await page.waitForLoadState('domcontentloaded');
    
    const addToCartButton = page.locator('button[name="add-to-cart"], .single_add_to_cart_button');
    await addToCartButton.click();
    await page.waitForTimeout(2000);
    
    // Go to checkout
    console.log('Going to checkout...');
    await page.goto(`${config.BASE_URL}/checkout/`);
    await page.waitForLoadState('domcontentloaded');
    await page.screenshot({ path: 'test-results/3-checkout-page.png' });
    
    // Wait for checkout form
    console.log('Waiting for checkout form...');
    await page.waitForTimeout(2000);
    
    // Fill billing details one by one with logging
    console.log('Filling First Name...');
    const firstNameField = page.locator('#billing_first_name');
    if (await firstNameField.isVisible()) {
      await firstNameField.fill('John');
      console.log('✓ First name filled');
    } else {
      console.log('✗ First name field not found');
    }

    console.log('Filling Last Name...');
    const lastNameField = page.locator('#billing_last_name');
    if (await lastNameField.isVisible()) {
      await lastNameField.fill('Doe');
      console.log('✓ Last name filled');
    } else {
      console.log('✗ Last name field not found');
    }

    console.log('Filling Address...');
    const addressField = page.locator('#billing_address_1');
    if (await addressField.isVisible()) {
      await addressField.fill('123 Test Street');
      console.log('✓ Address filled');
    } else {
      console.log('✗ Address field not found');
    }

    console.log('Filling City...');
    const cityField = page.locator('#billing_city');
    if (await cityField.isVisible()) {
      await cityField.fill('London');
      console.log('✓ City filled');
    } else {
      console.log('✗ City field not found');
    }

    console.log('Filling Postcode...');
    const postcodeField = page.locator('#billing_postcode');
    if (await postcodeField.isVisible()) {
      await postcodeField.fill('W1A 1AA');
      console.log('✓ Postcode filled');
    } else {
      console.log('✗ Postcode field not found');
    }

    console.log('Filling Phone...');
    const phoneField = page.locator('#billing_phone');
    if (await phoneField.isVisible()) {
      await phoneField.fill('07700900123');
      console.log('✓ Phone filled');
    } else {
      console.log('✗ Phone field not found');
    }

    console.log('Filling Email...');
    const emailField = page.locator('#billing_email');
    if (await emailField.isVisible()) {
      await emailField.fill('test@example.com');
      console.log('✓ Email filled');
    } else {
      console.log('✗ Email field not found');
    }

    // Take screenshot after filling
    await page.screenshot({ path: 'test-results/4-checkout-filled.png' });
    
    // Log what fields are visible on the page
    console.log('\n--- Checking what fields exist on checkout ---');
    const allInputs = await page.locator('input[type="text"], input[type="email"], input[type="tel"]').all();
    for (const input of allInputs) {
      const id = await input.getAttribute('id');
      const name = await input.getAttribute('name');
      const placeholder = await input.getAttribute('placeholder');
      console.log(`Field: id="${id}" name="${name}" placeholder="${placeholder}"`);
    }
    
    console.log('\n✅ Step 2 complete: Checkout form inspection done');
    console.log('Check test-results/ folder for screenshots');
  });

});
