/**
 * WooCommerce-specific selector utilities.
 * Prioritizes accessible, stable selectors over brittle CSS.
 */

/**
 * Common data-testid patterns used in WooCommerce themes.
 */
export const TestIds = {
  // Cart
  CART_FORM: 'cart-form',
  CART_ITEM: 'cart-item',
  CART_TOTAL: 'cart-total',
  UPDATE_CART: 'update-cart',
  PROCEED_TO_CHECKOUT: 'proceed-to-checkout',

  // Checkout
  CHECKOUT_FORM: 'checkout-form',
  BILLING_FIELDS: 'billing-fields',
  SHIPPING_FIELDS: 'shipping-fields',
  PAYMENT_METHOD: 'payment-method',
  PLACE_ORDER: 'place-order',
  ORDER_REVIEW: 'order-review',

  // Product
  ADD_TO_CART: 'add-to-cart',
  PRODUCT_QUANTITY: 'product-quantity',
  PRODUCT_PRICE: 'product-price',
} as const;

/**
 * WooCommerce-specific CSS selectors.
 * Use as fallback when better selectors aren't available.
 */
export const WooSelectors = {
  // Navigation
  SHOP_LINK: '.menu-item a[href*="shop"]',
  CART_LINK: '.cart-contents, a[href*="cart"]',
  ACCOUNT_LINK: '.menu-item a[href*="my-account"]',

  // Product Page
  SINGLE_PRODUCT: '.single-product',
  PRODUCT_TITLE: '.product_title, h1.entry-title',
  PRODUCT_PRICE: '.price .woocommerce-Price-amount',
  ADD_TO_CART_BUTTON: 'button[name="add-to-cart"], .single_add_to_cart_button',
  QUANTITY_INPUT: 'input.qty, input[name="quantity"]',
  VARIATION_SELECT: '.variations select',

  // Cart Page
  CART_TABLE: '.shop_table.cart, .woocommerce-cart-form__contents',
  CART_ITEM_ROW: '.cart_item, .woocommerce-cart-form__cart-item',
  CART_ITEM_NAME: '.product-name',
  CART_ITEM_PRICE: '.product-price .woocommerce-Price-amount',
  CART_ITEM_QUANTITY: '.product-quantity input.qty',
  CART_ITEM_SUBTOTAL: '.product-subtotal .woocommerce-Price-amount',
  CART_SUBTOTAL: '.cart-subtotal .woocommerce-Price-amount',
  CART_TOTAL: '.order-total .woocommerce-Price-amount',
  COUPON_INPUT: '#coupon_code',
  APPLY_COUPON: 'button[name="apply_coupon"]',
  UPDATE_CART_BUTTON: 'button[name="update_cart"]',
  PROCEED_TO_CHECKOUT_BUTTON: '.checkout-button, a.wc-proceed-to-checkout',
  CART_DISCOUNT: '.cart-discount .woocommerce-Price-amount',

  // Checkout Page
  CHECKOUT_FORM: 'form.checkout, form.woocommerce-checkout',
  BILLING_FIRST_NAME: '#billing_first_name',
  BILLING_LAST_NAME: '#billing_last_name',
  BILLING_COMPANY: '#billing_company',
  BILLING_COUNTRY: '#billing_country',
  BILLING_ADDRESS_1: '#billing_address_1',
  BILLING_ADDRESS_2: '#billing_address_2',
  BILLING_CITY: '#billing_city',
  BILLING_STATE: '#billing_state',
  BILLING_POSTCODE: '#billing_postcode',
  BILLING_PHONE: '#billing_phone',
  BILLING_EMAIL: '#billing_email',
  SHIP_TO_DIFFERENT: '#ship-to-different-address-checkbox',
  SHIPPING_FIRST_NAME: '#shipping_first_name',
  SHIPPING_LAST_NAME: '#shipping_last_name',
  ORDER_COMMENTS: '#order_comments',
  PAYMENT_METHODS: '.wc_payment_methods, #payment ul.payment_methods',
  PAYMENT_METHOD_ITEM: '.wc_payment_method',
  PLACE_ORDER_BUTTON: '#place_order',
  ORDER_REVIEW_TABLE: '.woocommerce-checkout-review-order-table',
  CHECKOUT_ERROR: '.woocommerce-error',
  CHECKOUT_NOTICE: '.woocommerce-notice',

  // Order Totals (checkout/cart)
  SUBTOTAL_ROW: '.cart-subtotal',
  SHIPPING_ROW: '.woocommerce-shipping-totals, .shipping',
  TAX_ROW: '.tax-rate, .tax-total',
  DISCOUNT_ROW: '.cart-discount',
  TOTAL_ROW: '.order-total',

  // Order Received/Thank You Page
  ORDER_RECEIVED: '.woocommerce-order-received',
  ORDER_NUMBER: '.woocommerce-order-overview__order strong, .order-number',
  ORDER_DATE: '.woocommerce-order-overview__date strong',
  ORDER_TOTAL: '.woocommerce-order-overview__total strong',
  ORDER_PAYMENT_METHOD: '.woocommerce-order-overview__payment-method strong',
  ORDER_DETAILS_TABLE: '.woocommerce-table--order-details',
  ORDER_ITEM_ROW: '.woocommerce-table__line-item',

  // My Account
  LOGIN_FORM: '.woocommerce-form-login',
  LOGIN_USERNAME: '#username',
  LOGIN_PASSWORD: '#password',
  LOGIN_BUTTON: 'button[name="login"], input[name="login"]',
  ACCOUNT_ORDERS: '.woocommerce-orders-table',

  // Messages
  WOO_MESSAGE: '.woocommerce-message',
  WOO_ERROR: '.woocommerce-error',
  WOO_INFO: '.woocommerce-info',

  // Admin (WP-Admin)
  ADMIN_LOGIN_FORM: '#loginform',
  ADMIN_USERNAME: '#user_login',
  ADMIN_PASSWORD: '#user_pass',
  ADMIN_SUBMIT: '#wp-submit',
  ADMIN_MENU: '#adminmenu',
  WC_ORDERS_TABLE: '.wp-list-table.orders',
  WC_ORDER_ROW: 'tr.type-shop_order',
  WC_ORDER_STATUS: '.order-status',
  WC_ORDER_TOTAL: '.order-total',
  WC_ORDER_ACTIONS: '.wc-order-actions',
} as const;

/**
 * Build a role-based locator string for Playwright.
 */
export function byRole(role: string, options: { name?: string | RegExp; exact?: boolean } = {}): string {
  let selector = `role=${role}`;
  if (options.name) {
    const nameStr = options.name instanceof RegExp ? options.name.source : options.name;
    selector += `[name="${nameStr}"${options.exact ? ' exact' : ''}]`;
  }
  return selector;
}

/**
 * Build a label-based locator string.
 */
export function byLabel(label: string | RegExp): string {
  const labelStr = label instanceof RegExp ? label.source : label;
  return `label:has-text("${labelStr}")`;
}

/**
 * Build a text-based locator string.
 */
export function byText(text: string | RegExp, exact = false): string {
  const textStr = text instanceof RegExp ? text.source : text;
  return exact ? `text="${textStr}"` : `text=${textStr}`;
}

/**
 * Build a data-testid locator string.
 */
export function byTestId(testId: string): string {
  return `[data-testid="${testId}"]`;
}
