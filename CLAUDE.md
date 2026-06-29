# Project Specification: Checkout.com WooCommerce Plugin Standalone Payment Components

You are an expert woocommerce developer. Use this file as your source of truth for refactoring, and maintaining this specific feature. Adhere strictly to the architecture, WooCommerce/WordPress guidelines, and the Checkout.com Flow solution defined below.

---

## 1. Tech Stack & Architecture

* **Frontend:** WooCommerce.
* **Backend:** WooCommerce Settings Payments, Orders etc, adhere to WooCommerce payment plugin guidelines and existing Checkout.com payment plugin architecure
* **Payment Processor:** Checkout.com (NAS platform, utilizing secret keys server-side and public keys client-side).

---

## 2. Backend Settings (Payments)

The existing layout must remain as is. The backend payment setting acts as a secure way of configuring the Checkout.com front end payments, handling Public Key and Secret Key under "Quick Setup".

### Required Enhancements/ Features


| Card Settings | Express Payments | Order Settings| Advanced | Flow Settings
  Do not refactor any of the above mentioned setting unless the feature I am including impacts the section, if it does, raise this with me clearly stating how the intended feature which I plan to add to Quick Setup will have an impact on | Card Settings | Express Payments | Order Settings| Advanced | Flow Settings and the level of refactoring that would be required.  


| Quick Setup | 
  Under "Quick Setup" in the 'Checkout Mode' Instead of a drop down to select "Flow" replace with tick boxes (with description) so customer can tick their prefered Checkout Mode
    [] Flow (Description => By selecting 'Flow' all payment methods enabled on your Checkout account will be available for your customers)
    [] Card (Description => By selecting 'Card' only Card payment methods would be displayed, Google Pay and Appple Pay will not be available)
    [] Google Pay  (Description => By selecting 'Google Pay' only Google Payment payment methods would be displayed)
    [] Apple Pay (Description => By selecting 'Google Pay' only Google Payment payment methods would be displayed)
  Add Display Order to enable client to be able to order the display of the payment method at the checkout/front-end to the customer
  Example. If the client selects 'Flow' the payment method order display is by default owned by Checkout, client should not have an option to select the ordering.

  Example If the client selects 'Card', provide a selection next to Card (Display 'First', 'Second', 'Third'), if client selects 'First' then the front end UI should have create card component at the top,
  If client selects Google Pay provide a selection next to Google Pay (Display 'First', 'Second', 'Third'), if client selects 'First' then the front end UI should have create googlepay component at the top,
  If Apple Pay provide a selection next to Apple Pay (Display 'First', 'Second', 'Third'), if client selects 'First' then the front end UI should have create applepay component at the top,
  Condition, the design is such that if client selects a payment method as 'First' they cannot have the option for selecting 'First' for other payment methods, the available options remaining would be 'Second', 'Third' or throw an display a warning to select 'Second' etc


---

### >>> CLAUDE'S RECOMMENDATION (for your review — not yet implemented) <<<

**Feasibility:** The goal (let merchants choose which payment components display, and in what order) is achievable.
But there is ONE design conflict I must raise before implementing, plus a frontend refactor cost.

**Conflict — "Checkout Mode" currently means Flow vs Classic, which are two different integration engines:**
- `ckocom_checkout_mode` today = `flow` (modern CheckoutWebComponents) OR `classic` (legacy integration:
  Alternative Payment Methods via `ckocom_apms_selector`, plus the separate express gateways).
- The value `classic` is read across the codebase: `assets/js/admin-checkout-mode-toggle.js` (shows/hides the
  Flow methods field vs the Classic APM field), `includes/express/*` (apple/google/paypal express classes),
  `includes/api/class-wc-checkoutcom-utility.php`, and `woocommerce-gateway-checkout-com.php`.
- **Card / Google Pay / Apple Pay are all Flow components** — sub-selections *within* Flow, not peers of Classic.
  Replacing the Checkout Mode dropdown with `[Flow][Card][Google Pay][Apple Pay]` tickboxes would DROP the Classic
  option from the UI and break the meaning of a setting the whole plugin depends on. That is a large, risky
  regression unrelated to the actual goal.

**Recommended design — keep the two concepts on separate axes:**
1. **Leave "Checkout Mode" (Flow / Classic) intact.** Do not replace it.
2. Add a NEW control under Quick Setup, shown only when Flow is selected: the payment-method tickboxes
   `[Flow] [Card] [Google Pay] [Apple Pay]` + per-method Display Order (First / Second / Third with
   mutual-exclusion). This is the new feature, and it lives on the Quick Setup page — which already renders via
   `WC_Admin_Settings::output_fields()` and therefore supports native `checkbox`/`radio`/`select` fields
   (the Flow Settings page does NOT — it uses `WC_Settings_API`, which has no radio renderer; that is why the
   earlier radio attempt rendered as a broken empty control).
3. Behaviour: ticking **Flow** = the existing all-in-one component (Checkout owns ordering; order selectors hidden
   and ignored). Ticking **Card / Google Pay / Apple Pay** (Flow unticked) = render those as standalone components
   in the chosen order. Persist as an ordered list in `woocommerce_wc_checkout_com_flow_settings`.

**Frontend refactor cost (required, no longer deferrable under this design):**
`flow-integration/assets/js/payment-session.js` (~3300 lines) is built around a SINGLE `ckoFlow.flowComponent`:
one mount point (`#flow-container`), one `selectedType`, and one shared 3DS / save-card / amount-update lifecycle.
Rendering multiple components simultaneously requires: separate containers (`#card-container`,
`#googlepay-container`, `#applepay-container`), one component instance each, `isAvailable()`-gating + `mount()` in
the configured order, and generalising the lifecycle (3DS redirect, save-card, amount/address updates,
selectedType) to operate per component. This is a substantial, higher-risk change that should be planned and
tested as its own phase.

**Open questions for you:**
- OK to keep Checkout Mode (Flow/Classic) and add the tickboxes as a separate Flow-only control (recommended),
  rather than replacing the Checkout Mode dropdown?
- Is the existing Quick Setup `flow_enabled_payment_methods` multiselect meant to be REPLACED by these tickboxes,
  or kept (it currently filters methods inside a Flow session)?
- Display Order UI: per-method First/Second/Third dropdowns with mutual-exclusion (matches your wording), correct?

### >>> END CLAUDE'S RECOMMENDATION <<<

---


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
