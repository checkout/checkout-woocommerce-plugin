# WooCommerce E2E Test Automation Framework

A production-ready end-to-end test automation framework for WooCommerce stores using Playwright and TypeScript.

## Features

- **Page Object Model (POM)** - Clean, maintainable test architecture
- **Multi-Project Support** - Separate storefront, admin, and e2e test suites
- **Robust Selectors** - Accessible, stable selectors (role, label, text-based)
- **No Flaky Sleeps** - Condition-based waits for reliable tests
- **CI-Ready** - GitHub Actions workflow included
- **Rich Reporting** - HTML reports, screenshots, videos, and traces
- **API Verification** - Optional WooCommerce REST API layer
- **Environment Validation** - Zod-powered config with clear errors

## Project Structure

```
e2e-tests/
├── src/
│   ├── pages/                    # Page Object Models
│   │   ├── storefront/           # Customer-facing pages
│   │   │   ├── HomePage.ts
│   │   │   ├── ProductPage.ts
│   │   │   ├── CartPage.ts
│   │   │   ├── CheckoutPage.ts
│   │   │   ├── OrderReceivedPage.ts
│   │   │   └── MyAccountPage.ts
│   │   └── admin/                # WP-Admin pages
│   │       ├── AdminLoginPage.ts
│   │       ├── AdminOrdersPage.ts
│   │       └── AdminOrderDetailsPage.ts
│   ├── fixtures/                 # Test fixtures and setup
│   ├── utils/                    # Utilities (selectors, waits, money, logging)
│   ├── config/                   # Environment configuration
│   ├── test-data/                # Products, customers, coupons, etc.
│   ├── assertions/               # Shared assertion helpers
│   └── api/                      # WooCommerce REST API client
├── tests/
│   ├── storefront/               # Customer flow tests
│   ├── admin/                    # Admin verification tests
│   └── e2e/                      # Combined end-to-end flows
├── playwright.config.ts
├── package.json
└── .env.example
```

## Quick Start

### Prerequisites

- Node.js 18+
- npm or yarn
- Access to a WooCommerce test store

### Installation

```bash
# Navigate to the e2e-tests directory
cd e2e-tests

# Install dependencies
npm install

# Install Playwright browsers
npx playwright install
```

### Environment Setup

1. Copy the example environment file:

```bash
cp .env.example .env
```

2. Configure your `.env` file:

```env
# Required: Store URLs
BASE_URL=https://your-test-store.com
ADMIN_URL=https://your-test-store.com/wp-admin

# Required: Admin credentials
ADMIN_USER=admin
ADMIN_PASS=your-password

# Optional: Customer credentials (for logged-in tests)
CUSTOMER_USER=customer@example.com
CUSTOMER_PASS=customer-password

# Optional: WooCommerce REST API (for API verification)
WC_CONSUMER_KEY=ck_xxxx
WC_CONSUMER_SECRET=cs_xxxx

# Optional: Payment test flags
PAYMENT_TEST_MODE=true
PAYMENT_3DS_REQUIRED=false
PAYMENT_FORCE_FAILURE=false
```

### Running Tests

```bash
# Run all tests
npm test

# Run specific test suites
npm run test:storefront     # Customer flow tests only
npm run test:admin          # Admin verification tests only
npm run test:e2e            # Combined end-to-end tests

# Run in headed mode (see browser)
npm run test:headed

# Run in debug mode
npm run test:debug

# Open Playwright UI
npm run test:ui
```

### Viewing Reports

```bash
# Open the HTML report
npm run report

# View a trace file
npm run trace -- ./test-results/some-trace.zip
```

## Test Suites

### Storefront Tests (`tests/storefront/`)

Customer-facing purchase flows:

- **Guest Checkout** - Complete purchase without account
- **Logged-In Checkout** - Purchase with saved address
- **Coupon/Discount** - Apply and verify coupons
- **Payment Failure** - Handle declined cards, 3DS

### Admin Tests (`tests/admin/`)

WooCommerce admin verification:

- Order list navigation and search
- Order details verification
- Status updates
- Order notes

### E2E Tests (`tests/e2e/`)

Complete flows with admin verification:

- Purchase on storefront → verify in admin
- Multi-item orders
- Coupon discounts
- Payment method verification

## Configuration

### Test Products

Configure test products in `src/test-data/products.ts`:

```typescript
export const testProducts = {
  simple: {
    name: 'Simple Product',
    slug: 'simple-product',
    sku: 'SIMPLE-001',
    price: 25.00,
    type: 'simple',
    inStock: true,
  },
  // Add more products...
};
```

### Test Coupons

Configure coupons in `src/test-data/coupons.ts`:

```typescript
export const testCoupons = {
  percent10: {
    code: 'PERCENT10',
    type: 'percent',
    amount: 10,
    description: '10% off entire order',
  },
  // Add more coupons...
};
```

### Payment Methods

Configure payment methods in `src/test-data/payment-methods.ts`:

```typescript
export const paymentMethods = {
  cod: {
    id: 'cod',
    title: 'Cash on delivery',
    requiresCard: false,
  },
  stripe: {
    id: 'stripe',
    title: 'Credit Card (Stripe)',
    requiresCard: true,
    testCards: [/* test card details */],
  },
};
```

## Writing New Tests

### Using Fixtures

```typescript
import { test, expect } from '../../src/fixtures';

test('my new test', async ({
  productPage,
  cartPage,
  checkoutPage,
  billingAddress,
  logger,
}) => {
  logger.step('Add product to cart');
  await productPage.goToProduct('my-product');
  await productPage.addToCart();
  
  // Continue with test...
});
```

### Using Page Objects Directly

```typescript
import { test } from '@playwright/test';
import { ProductPage, CartPage } from '../../src/pages';

test('custom test', async ({ page }) => {
  const productPage = new ProductPage(page);
  const cartPage = new CartPage(page);
  
  await productPage.goToProduct('my-product');
  await productPage.addToCart();
  await cartPage.goto();
  // ...
});
```

## Selector Best Practices

### Preferred (Stable)

```typescript
// Role-based (most accessible)
this.getByRole('button', { name: 'Add to Cart' });

// Label-based
this.getByLabel('Email address');

// Text-based
this.getByText('Order received');

// Test ID
this.getByTestId('checkout-form');
```

### Avoid (Brittle)

```typescript
// Avoid: Deep CSS selectors
this.locator('.main > div.content > .product-list > .item:nth-child(2)');

// Avoid: Dynamic class names
this.locator('.btn-xyz123');

// Avoid: Positional selectors
this.locator('input').nth(5);
```

### WooCommerce-Specific

Use the selectors defined in `src/utils/selectors.ts`:

```typescript
import { WooSelectors } from '../utils/selectors';

// Use predefined WooCommerce selectors
this.locator(WooSelectors.ADD_TO_CART_BUTTON);
this.locator(WooSelectors.CHECKOUT_FORM);
```

## Debugging

### Trace Viewer

On test failure, traces are automatically captured. View them:

```bash
npx playwright show-trace test-results/path-to-trace.zip
```

### Debug Mode

```bash
# Run with Playwright Inspector
npm run test:debug

# Run with headed browser and slow motion
SLOW_MO=500 npm run test:headed
```

### Screenshots

Take screenshots in tests:

```typescript
await page.screenshot({ path: 'debug.png' });
await productPage.screenshot('product-page');
```

## CI/CD Integration

### GitHub Actions

The included workflow (`.github/workflows/e2e-tests.yml`) runs:

1. **Lint & Type Check** - Validates code quality
2. **Storefront Tests** - Customer flow tests
3. **Admin Tests** - Admin verification tests
4. **E2E Tests** - Full integration tests

### Required Secrets

Configure these secrets in your GitHub repository:

| Secret | Description |
|--------|-------------|
| `STORE_BASE_URL` | WooCommerce store URL |
| `STORE_ADMIN_URL` | WordPress admin URL |
| `ADMIN_USER` | Admin username |
| `ADMIN_PASS` | Admin password |
| `CUSTOMER_USER` | (Optional) Customer email |
| `CUSTOMER_PASS` | (Optional) Customer password |
| `WC_CONSUMER_KEY` | (Optional) REST API key |
| `WC_CONSUMER_SECRET` | (Optional) REST API secret |

### Manual Trigger

Run tests manually via GitHub Actions:

1. Go to Actions → E2E Tests
2. Click "Run workflow"
3. Select test project and options

## WooCommerce REST API Verification

Enable API verification for a third layer of order validation:

1. Generate API keys: WooCommerce → Settings → Advanced → REST API
2. Add to `.env`:
   ```env
   WC_CONSUMER_KEY=ck_xxxx
   WC_CONSUMER_SECRET=cs_xxxx
   ```

3. Use in tests:
   ```typescript
   import { getWcApi } from '../api';
   
   const api = getWcApi();
   if (api.isAvailable()) {
     const order = await api.getOrder(orderNumber);
     const verification = await api.verifyOrderTotals(orderNumber, {
       total: 99.99,
       subtotal: 89.99,
       shipping: 10.00,
     });
   }
   ```

## Visual Testing (Applitools)

To enable Applitools visual testing:

1. Install the SDK:
   ```bash
   npm install @applitools/eyes-playwright
   ```

2. Configure your API key:
   ```env
   APPLITOOLS_API_KEY=your-api-key
   ```

3. Add visual checks to tests:
   ```typescript
   import { Eyes, Target } from '@applitools/eyes-playwright';
   
   const eyes = new Eyes();
   await eyes.open(page, 'WooCommerce', 'Checkout Page');
   await eyes.check('Checkout', Target.window().fully());
   await eyes.close();
   ```

## Troubleshooting

### Common Issues

**Tests timeout on checkout**
- Increase `timeout` in playwright.config.ts
- Check for slow network or payment gateway

**Selectors not finding elements**
- Verify WooCommerce theme compatibility
- Add custom selectors to `WooSelectors` in `src/utils/selectors.ts`

**Authentication failures**
- Verify credentials in `.env`
- Check for 2FA or security plugins blocking login

**Cart not persisting**
- Clear browser storage between tests: `await context.clearCookies()`
- Check WooCommerce session settings

### Getting Help

1. Check the Playwright docs: https://playwright.dev/docs
2. WooCommerce REST API docs: https://woocommerce.github.io/woocommerce-rest-api-docs/
3. Review test output and traces for detailed error information

## License

ISC
