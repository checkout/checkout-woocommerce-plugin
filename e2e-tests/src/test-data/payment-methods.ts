/**
 * Test payment method data.
 */

export interface TestPaymentMethod {
  id: string;
  title: string;
  requiresCard?: boolean;
  testCards?: TestCard[];
  threeDSPassword?: string;
}

export interface TestCard {
  number: string;
  expiry: string;
  cvc: string;
  name: string;
  scenario: 'success' | 'decline' | '3ds' | 'insufficient_funds' | 'expired';
}

/**
 * Available payment methods.
 * Update these to match your test store configuration.
 */
export const paymentMethods: Record<string, TestPaymentMethod> = {
  cod: {
    id: 'cod',
    title: 'Cash on delivery',
    requiresCard: false,
  },
  
  bacs: {
    id: 'bacs',
    title: 'Direct bank transfer',
    requiresCard: false,
  },
  
  cheque: {
    id: 'cheque',
    title: 'Check payments',
    requiresCard: false,
  },
  
  stripe: {
    id: 'stripe',
    title: 'Credit Card (Stripe)',
    requiresCard: true,
    testCards: [
      {
        number: '4242424242424242',
        expiry: '12/30',
        cvc: '123',
        name: 'Success Card',
        scenario: 'success',
      },
      {
        number: '4000000000000002',
        expiry: '12/30',
        cvc: '123',
        name: 'Decline Card',
        scenario: 'decline',
      },
      {
        number: '4000002500003155',
        expiry: '12/30',
        cvc: '123',
        name: '3DS Required Card',
        scenario: '3ds',
      },
      {
        number: '4000000000009995',
        expiry: '12/30',
        cvc: '123',
        name: 'Insufficient Funds',
        scenario: 'insufficient_funds',
      },
      {
        number: '4000000000000069',
        expiry: '12/30',
        cvc: '123',
        name: 'Expired Card',
        scenario: 'expired',
      },
    ],
  },
  
  paypal: {
    id: 'ppcp-gateway',
    title: 'PayPal',
    requiresCard: false,
  },
  
  // Checkout.com Flow (if applicable)
  checkoutFlow: {
    id: 'wc_checkout_com_flow',
    title: 'Checkout.com',
    requiresCard: true,
    testCards: [
      {
        number: '4242424242424242',
        expiry: '12/30',
        cvc: '100',
        name: 'Success Card',
        scenario: 'success',
      },
      {
        number: '4000000000000002',
        expiry: '12/30',
        cvc: '100',
        name: 'Decline Card',
        scenario: 'decline',
      },
      {
        number: '4000000000003220',
        expiry: '12/30',
        cvc: '100',
        name: '3DS2 Challenge',
        scenario: '3ds',
      },
    ],
    // 3DS password for Checkout.com sandbox
    threeDSPassword: 'Checkout1!',
  },
};

/**
 * Get payment method by ID.
 */
export function getPaymentMethod(id: string): TestPaymentMethod | undefined {
  return Object.values(paymentMethods).find(m => m.id === id);
}

/**
 * Get test card for a specific scenario.
 */
export function getTestCard(
  paymentMethodId: string,
  scenario: TestCard['scenario']
): TestCard | undefined {
  const method = getPaymentMethod(paymentMethodId);
  return method?.testCards?.find(card => card.scenario === scenario);
}

/**
 * Get success card for a payment method.
 */
export function getSuccessCard(paymentMethodId: string): TestCard | undefined {
  return getTestCard(paymentMethodId, 'success');
}

/**
 * Get decline card for a payment method.
 */
export function getDeclineCard(paymentMethodId: string): TestCard | undefined {
  return getTestCard(paymentMethodId, 'decline');
}

/**
 * Get 3DS card for a payment method.
 */
export function get3DSCard(paymentMethodId: string): TestCard | undefined {
  return getTestCard(paymentMethodId, '3ds');
}

/**
 * Default payment method for quick tests.
 * For wc-cko.net store, use Checkout.com Flow.
 */
export const defaultPaymentMethod = paymentMethods.checkoutFlow;

/**
 * Get all non-card payment methods (for quick guest tests).
 */
export function getNonCardPaymentMethods(): TestPaymentMethod[] {
  return Object.values(paymentMethods).filter(m => !m.requiresCard);
}
