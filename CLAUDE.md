# Project Specification: Checkout.com WooCommerce Plugin Standalone Payment Components

You are an expert woocommerce developer. Use this file as your source of truth for refactoring, and maintaining this repository. Adhere strictly to the architecture, WooCommerce/WordPress guidelines, and the Checkout.com Flow solution defined below.

---

## 1. Tech Stack & Architecture

* **Frontend:** WooCommerce.
* **Backend:** WooCommerce Settings Payments, Orders etc, adhere to WooCommerce payment plugin guidelines and existing Checkout.com payment plugin architecure
* **Payment Processor:** Checkout.com (NAS platform, utilizing secret keys server-side and public keys client-side).

---

## 2. Backend Settings (Payments)

The existing layout must remain as is. The backend payment setting acts as a secure way of configuring the Checkout.com front end payments, handling Public Key and Secret Key under "Quick Setup".

### Required Enhancements/ Features

| Quick Setup | 
     Do not refactor unless the feature I am including impacts the Quick Setup behiour, if it does, raise this with me clearly stating how the intended feature which I plan to add under "Flow Setting" have an impact on "Quick Setup" current design and workflow.

| Card Settings | 
    Do not refactor unless the feature I am including impacts the Card Settings  section, if it does, raise this with me clearly stating how the intended feature which I plan to add under "Flow Setting" may have an impact on the "Card Settings" current design and workflow.

| Express Payments | 
   Do not refactor unless the feature I am including impacts the "Express Payments"  section, if it does, raise this with me clearly stating how the intended feature which I plan to add under "Flow Setting" may have an impact on the "Express Payments" current design and workflow.


| Order Settings | 
    Do not refactor unless the feature I am including impacts the "Order Settings" section, if it does, raise this with me clearly stating how the intended feature which I plan to add under "Flow Setting" may have an impact on the "Order Settings" current design and workflow.


| Advanced | 
  Do not refactor unless the feature I am including impacts the "Advanced" section, if it does, raise this with me clearly stating how the intended feature which I plan to add under "Advance" may have an impact on the "Advanced" current design and workflow.


| Flow Settings | 
  Under "Flow Payment method" add tick boxes (with description) so customer can tick the Payment method they want to display
    [] Flow (Description => By selecting 'Card' all payment methods enabled on your Checkout account will be available for your customers)
    [] Card (Description => By selecting 'Card' only Card payment methods would be displayed, Google Pay and Appple Pay will not be available)
    [] Google Pay  (Description => By selecting 'Google Pay' only Google Payment payment methods would be displayed)
    [] Apple Pay (Description => By selecting 'Google Pay' only Google Payment payment methods would be displayed)
  Add Display Order to enable client to be able to order the display of the payment method at the checkout/front-end to the customer
  E.g. If the select 'Flow' the order is as defined by checkout
  Example If the client selects Card, provide a means via the UI by asking or givent the option as to whether Card should be displayed as first payment method (selection 1st, 2nd, 3rd), then the front end UI should have create card at the top,
  If customer selects Google Pay and or Apple Pay give them the option to select 1, 2nd, 3rd, , then the front end UI should have create card at the top,



## 3. Implementation Guidelines

### Backend Payment settings Patterns
* **Security:** Adhere to WooCommerce payment plugin guidelines


### Frontend Patterns
* **Flow:** If client selects Flow under "Quick Setup" in the backend Payment settings the existing code should work as designed by displaying all the available payment methods as defined in the ckoFlow flowComponent in payment-session.js and related wc-checkoutcom classes

* **Flow Payment Method:**If client selects Flow under "Quick Setup" in the backend Payment settings and selects "Card only" under "Flow settings" -> Flow Payment method display/create only Card component.
Example code
    const checkout = await CheckoutWebComponents({
      publicKey: cko_flow_vars.PKey,
      environment: cko_flow_vars.env,
      locale: window.locale,
      paymentSession,
      appearance: window.appearance,
      componentOptions: window.componentOptions,
      translations: window.translations,
      onReady,
      onPaymentCompleted,
      onSubmit,
      onChange,
      handleClick,
      handleSubmit, 
      onError
    });


    ckoFlow.checkoutInstance = checkout;
    ckoFlow.initialSessionAmount = amount; 
    ckoFlow.initialSessionEmail = email; 
    ckoFlow.pendingAmountUpdate = null; 
    
    ckoLogger.debug('[INIT] Stored initial session email for comparison:', {
      email: email
    });
    

    ckoFlow.initialBillingAddress = {
      address_line1: address1,
      address_line2: address2,
      city: city,
      state: state,
      zip: zip,
      country: country,
      phone: phone
    };
    ckoFlow.initialShippingAddress = {
      address_line1: shippingAddress1,
      address_line2: shippingAddress2,
      city: shippingCity,
      state: shippingState,
      zip: shippingZip,
      country: shippingCountry,
      phone: phone  
    };
    

    ckoLogger.debug('[INIT] Initial addresses stored for handleSubmit comparison:', {
      billing: ckoFlow.initialBillingAddress,
      shipping: ckoFlow.initialShippingAddress
    });


    ckoLogger.debug('SDK initialized:', {
      hasCreate: typeof checkout.create === 'function',
      hasUpdate: typeof checkout.update === 'function',
      paymentSessionId: paymentSession.id,
      environment: cko_flow_vars.env,
      initialAmount: amount
    });


        // Ensure component name is defined 'card'
        const componentName = window.componentName || 'card';
        ckoLogger.debug('Creating Flow component with name:', componentName);
        
  
        if (typeof window.ckoSetCardholderName === 'function') {
          window.ckoSetCardholderName();
        }
        
        let flowComponent;
        try {
          flowComponent = checkout.create(componentName, {
            showPayButton: false,
          });
          
          ckoLogger.debug('Flow component created successfully:', {
            componentName: componentName,
            componentType: flowComponent.type
          });
        } catch (error) {
          ckoLogger.error('Error creating Flow component:', error);
          showError('Failed to initialize payment component. Please try again.');
          return;
        }

        // Performance: Track component creation
        const componentInitEnd = performance.now();
        const componentInitDuration = componentInitEnd - componentInitStart;
        if (ckoFlow.performanceMetrics.enableLogging) {
          ckoLogger.performance(`Component initialized in ${componentInitDuration.toFixed(2)}ms (${(componentInitDuration / 1000).toFixed(2)}s)`);
        }

        ckoFlow.flowComponent = flowComponent;

        /*
         * Check if the component is available. Mount component only if available.
         */
        flowComponent.isAvailable().then((available) => {
          // Log component availability (debug mode only)
          ckoLogger.debug('Component availability:', {
            available: available,
            componentType: flowComponent.type,
            environment: cko_flow_vars.env
          });
          
          if (available) {

            ckoFlow.mountWithRetry(flowComponent);
          } else {
            // Hide loading overlay.
            hideLoadingOverlay();
            ckoLogger.error("Component is not available.");
            console.error('[FLOW UI ERROR] Component is not available - showing error message');
            console.error('[FLOW UI ERROR] This is a CLIENT-SIDE (JavaScript) error');

            showError(
              wp.i18n.__(
                "The selected payment method is not available at this time.",
                "checkout-com-unified-payments-api"
              )
            );
          }
        });
      }


## 4. Code Generation 
Adhere to the current code structure and supported PHP version

## Expected output
Separate the payment methods instead of the default 'flow' which displays all payment methods configured on merchant account. This is achievable as follows. 
  const applePayComponent = checkout.create('applepay');
   if (await googlePayComponent.isAvailable()) {
    googlePayComponent.mount('#applepay-container');
  }

  const googlePayComponent = checkout.create('googlepay');
   if (await googlePayComponent.isAvailable()) {
    googlePayComponent.mount('#googlepay-container');
  }

  const cardComponent = checkout.create('card');
   if (await cardComponent.isAvailable()) {
    cardComponent.mount('#card-container');
   }
## Checkout.com Flow Documentation Guide 
  https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website
  https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/flow-library-reference/flowcomponent

  https://api-reference.checkout.com/tag/Flow?_gl=1*12x8yui*_gcl_au*MTUzMzQwNjc3MS4xNzgxNzE0MDY2*_ga*MjA4MDI4NjA1Ni4xNzY0MDgyNTU2*_ga_B9CRR7CRMP*czE3ODI1NjI4NjYkbzE2JGcxJHQxNzgyNTYyOTUxJGo0MiRsMCRoMA..#operation/CreatePaymentSession
