/**
 * Money/currency parsing and formatting utilities.
 * Handles WooCommerce's various currency display formats.
 */

export interface ParsedMoney {
  amount: number;
  currency: string;
  formatted: string;
}

/**
 * Currency symbols and their codes.
 */
const CURRENCY_MAP: Record<string, string> = {
  '$': 'USD',
  '£': 'GBP',
  '€': 'EUR',
  '¥': 'JPY',
  '₹': 'INR',
  'A$': 'AUD',
  'C$': 'CAD',
  'kr': 'SEK',
  'CHF': 'CHF',
  'R$': 'BRL',
  '₽': 'RUB',
  '₩': 'KRW',
  '฿': 'THB',
  '₫': 'VND',
  'zł': 'PLN',
};

/**
 * Parse a WooCommerce price string into a numeric value.
 * Handles various formats:
 * - $1,234.56
 * - £1.234,56 (European)
 * - €1 234,56 (Space separator)
 * - 1,234.56 USD
 */
export function parseMoney(priceString: string): ParsedMoney {
  if (!priceString || typeof priceString !== 'string') {
    return { amount: 0, currency: '', formatted: '' };
  }

  const original = priceString.trim();
  let currency = '';

  // Extract currency symbol/code
  for (const [symbol, code] of Object.entries(CURRENCY_MAP)) {
    if (original.includes(symbol)) {
      currency = code;
      break;
    }
  }

  // Also check for currency codes at end (e.g., "1,234.56 USD")
  const currencyCodeMatch = original.match(/\b([A-Z]{3})\b/);
  if (currencyCodeMatch && !currency) {
    currency = currencyCodeMatch[1];
  }

  // Remove currency symbols, letters, and normalize
  let numericString = original
    .replace(/[^\d.,\-\s]/g, '') // Keep only digits, dots, commas, minus, spaces
    .replace(/\s/g, '')          // Remove spaces
    .trim();

  // Determine decimal separator
  // If both , and . exist, the last one is likely the decimal separator
  const lastCommaIndex = numericString.lastIndexOf(',');
  const lastDotIndex = numericString.lastIndexOf('.');

  if (lastCommaIndex > lastDotIndex && lastCommaIndex > 0) {
    // European format: 1.234,56 or 1234,56
    numericString = numericString
      .replace(/\./g, '')  // Remove thousand separators
      .replace(',', '.');  // Convert decimal comma to dot
  } else if (lastDotIndex > lastCommaIndex && lastDotIndex > 0) {
    // US format: 1,234.56 or 1234.56
    numericString = numericString.replace(/,/g, ''); // Remove thousand separators
  } else if (lastCommaIndex > 0 && lastDotIndex === -1) {
    // Only comma present - could be decimal
    const parts = numericString.split(',');
    if (parts.length === 2 && parts[1].length <= 2) {
      // Likely decimal: 1234,56
      numericString = numericString.replace(',', '.');
    } else {
      // Likely thousand separator: 1,234
      numericString = numericString.replace(/,/g, '');
    }
  }

  const amount = parseFloat(numericString) || 0;

  return {
    amount,
    currency,
    formatted: original,
  };
}

/**
 * Parse multiple prices from text (e.g., "Was: $100.00 Now: $80.00")
 */
export function parseMultiplePrices(text: string): ParsedMoney[] {
  const priceRegex = /[£$€¥₹][\d,.\s]+|[\d,.\s]+[£$€¥₹]|[\d,.\s]+\s*(?:USD|GBP|EUR)/gi;
  const matches = text.match(priceRegex) || [];
  return matches.map(parseMoney);
}

/**
 * Format a number as currency.
 */
export function formatMoney(
  amount: number,
  currency = 'USD',
  locale = 'en-US'
): string {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
  }).format(amount);
}

/**
 * Compare two money amounts with tolerance for floating-point errors.
 */
export function moneyEquals(
  amount1: number,
  amount2: number,
  tolerance = 0.01
): boolean {
  return Math.abs(amount1 - amount2) <= tolerance;
}

/**
 * Calculate expected total from line items.
 */
export interface OrderLineItem {
  unitPrice: number;
  quantity: number;
  lineTotal?: number;
}

export interface OrderTotals {
  subtotal: number;
  shipping: number;
  tax: number;
  discount: number;
  total: number;
}

export function calculateExpectedTotal(
  items: OrderLineItem[],
  shipping = 0,
  tax = 0,
  discount = 0
): OrderTotals {
  const subtotal = items.reduce((sum, item) => {
    const lineTotal = item.lineTotal ?? item.unitPrice * item.quantity;
    return sum + lineTotal;
  }, 0);

  const total = subtotal + shipping + tax - discount;

  return {
    subtotal: Math.round(subtotal * 100) / 100,
    shipping: Math.round(shipping * 100) / 100,
    tax: Math.round(tax * 100) / 100,
    discount: Math.round(discount * 100) / 100,
    total: Math.round(total * 100) / 100,
  };
}

/**
 * Extract numeric price from a WooCommerce element's text.
 */
export async function extractPrice(getText: () => Promise<string>): Promise<number> {
  const text = await getText();
  return parseMoney(text).amount;
}
