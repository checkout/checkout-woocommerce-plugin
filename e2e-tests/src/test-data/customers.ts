import { AddressData } from '../pages/storefront/CheckoutPage';
import { getRunEmailSuffix } from '../config/env-loader';

/**
 * Test customer data for wc-cko.net store (UK-based).
 */

export interface TestCustomer {
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  billingAddress: AddressData;
  shippingAddress?: Omit<AddressData, 'phone' | 'email'>;
}

/**
 * Generate a unique email for the current test run.
 * This ensures order isolation between test runs.
 */
export function generateUniqueEmail(baseEmail = 'test'): string {
  const suffix = getRunEmailSuffix();
  const timestamp = Date.now();
  return `${baseEmail}+${suffix}-${timestamp}@example.com`;
}

/**
 * Default UK customer for testing (matches wc-cko.net store).
 */
export const ukCustomer: TestCustomer = {
  firstName: 'John',
  lastName: 'Doe',
  email: 'test@example.com',
  phone: '07700 900123',
  billingAddress: {
    firstName: 'John',
    lastName: 'Doe',
    company: '',
    country: 'GB',
    address1: '123 Test Street',
    address2: '',
    city: 'London',
    state: '',
    postcode: 'W1A 1AA',
    phone: '07700 900123',
    email: 'test@example.com',
  },
};

/**
 * US customer for testing.
 */
export const usCustomer: TestCustomer = {
  firstName: 'Jane',
  lastName: 'Smith',
  email: 'test@example.com',
  phone: '555-123-4567',
  billingAddress: {
    firstName: 'Jane',
    lastName: 'Smith',
    company: '',
    country: 'US',
    address1: '123 Test Street',
    address2: 'Suite 100',
    city: 'New York',
    state: 'NY',
    postcode: '10001',
    phone: '555-123-4567',
    email: 'test@example.com',
  },
};

/**
 * EU customer (Germany) for testing.
 */
export const euCustomer: TestCustomer = {
  firstName: 'Hans',
  lastName: 'Mueller',
  email: 'test@example.de',
  phone: '+49 30 1234567',
  billingAddress: {
    firstName: 'Hans',
    lastName: 'Mueller',
    company: '',
    country: 'DE',
    address1: 'Teststraße 123',
    address2: '',
    city: 'Berlin',
    state: '',
    postcode: '10115',
    phone: '+49 30 1234567',
    email: 'test@example.de',
  },
};

/**
 * Customer with different shipping address.
 */
export const splitAddressCustomer: TestCustomer = {
  firstName: 'Sarah',
  lastName: 'Johnson',
  email: 'test@example.com',
  phone: '07700 900456',
  billingAddress: {
    firstName: 'Sarah',
    lastName: 'Johnson',
    company: '',
    country: 'GB',
    address1: '456 Billing Lane',
    address2: 'Flat 2B',
    city: 'Manchester',
    state: '',
    postcode: 'M1 1AA',
    phone: '07700 900456',
    email: 'test@example.com',
  },
  shippingAddress: {
    firstName: 'Sarah',
    lastName: 'Johnson',
    company: 'Office Building',
    country: 'GB',
    address1: '789 Shipping Ave',
    address2: 'Floor 5',
    city: 'Birmingham',
    state: '',
    postcode: 'B1 1AA',
  },
};

/**
 * Get a test customer with unique email for the run.
 * Defaults to UK customer for wc-cko.net store.
 */
export function getTestCustomer(
  baseCustomer: TestCustomer = ukCustomer,
  uniqueEmail = true
): TestCustomer {
  const customer = { ...baseCustomer };
  
  if (uniqueEmail) {
    const email = generateUniqueEmail(baseCustomer.firstName.toLowerCase());
    customer.email = email;
    customer.billingAddress = {
      ...customer.billingAddress,
      email,
    };
  }
  
  return customer;
}

/**
 * Get billing address with unique email.
 * Defaults to UK address for wc-cko.net store.
 */
export function getBillingAddress(
  customer: TestCustomer = ukCustomer,
  uniqueEmail = true
): AddressData {
  const address = { ...customer.billingAddress };
  
  if (uniqueEmail) {
    address.email = generateUniqueEmail(customer.firstName.toLowerCase());
  }
  
  return address;
}

/**
 * Minimal required billing address (for faster tests).
 * Uses UK address format for wc-cko.net.
 */
export function getMinimalBillingAddress(uniqueEmail = true): AddressData {
  return {
    firstName: 'Test',
    lastName: 'User',
    country: 'GB',
    address1: '123 Test St',
    city: 'London',
    state: '',
    postcode: 'W1A 1AA',
    phone: '07700 900000',
    email: uniqueEmail ? generateUniqueEmail('test') : 'test@example.com',
  };
}

/**
 * Get shipping address (different from billing).
 * Defaults to UK address for wc-cko.net store.
 */
export function getShippingAddress(
  customer: TestCustomer = splitAddressCustomer
): Omit<AddressData, 'phone' | 'email'> {
  if (customer.shippingAddress) {
    return { ...customer.shippingAddress };
  }
  
  // Return a default shipping address if customer doesn't have one
  return {
    firstName: customer.billingAddress.firstName,
    lastName: customer.billingAddress.lastName,
    company: 'Shipping Company',
    country: 'GB',
    address1: '789 Shipping Lane',
    address2: 'Unit 5',
    city: 'Birmingham',
    state: '',
    postcode: 'B1 2AA',
  };
}
