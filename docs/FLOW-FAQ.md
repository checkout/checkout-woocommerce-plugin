# Checkout.com WooCommerce — Flow Integration FAQ

**Plugin:** Checkout.com Payment Gateway (`checkout-com-unified-payments-api`)
**Applies to:** v5.1.3.x (Flow checkout mode)
**Audience:** Part A = merchants / support / QA · Part B = developers

> Flow is Checkout.com's hosted Web Component checkout. The plugin runs in one of two modes —
> **Flow** (`ckocom_checkout_mode = flow`) or **Classic** (legacy iframe/card fields). This FAQ
> covers **Flow mode**. The two modes are mutually exclusive per store.

---

## Part A — Functional FAQ

### General

**Q: What is "Flow mode" and how do I enable it?**
Flow renders the Checkout.com Web Component on the checkout page. Enable it under
**WooCommerce → Settings → Payments → Checkout.com**, setting the checkout mode to *Flow*. When Flow
is on, the Classic card gateway is off (and vice-versa).

**Q: Which payment methods does Flow support?**
Cards plus any Alternative Payment Methods (APMs) enabled in Flow settings (PayPal, Apple Pay,
Google Pay, Klarna, etc., subject to your account configuration). Cards are always available.

**Q: Does Flow support 3DS?**
Yes. 3DS is driven by the component; on a challenge the customer is redirected to the issuer and
returns to a dedicated endpoint that finalizes the order. Frictionless (no-challenge) payments also
return through the same endpoint.

---

### Saving cards

**Q: How do customers save a card?**
Two ways:
1. **At checkout** — tick "Save card for future purchases" while paying. The card is tokenised and
   stored against their account.
2. **My Account → Payment methods → Add payment method** — add a card without making a purchase
   (see below).

**Q: What happens on the "Add payment method" page?**
The Flow card form renders. When the customer submits, the plugin runs a **zero-amount card
verification** (no charge), tokenises the card, and saves it to **My Account → Payment methods**.
A best-effort void is issued for the verification authorisation. The saved-cards list and the
"save card" checkbox are intentionally hidden on this page (its only job is to add one new card).

**Q: Will the customer be charged when adding a card?**
No. It's an `amount = 0` verification (auth only, capture disabled). No funds are captured.

**Q: Saved cards from the old Classic gateway — do they still work under Flow?**
Yes. The plugin reads tokens from **both** the Flow and Classic Cards gateways, so customers
upgrading from Classic keep their saved cards with no migration.

---

### Subscriptions — buying

**Q: How is the first subscription payment processed?**
As a **Customer-Initiated Transaction (CIT)**: the initial payment is sent with
`payment_type = Recurring` (so the card is stored as a reusable credential). The `merchant_initiated`
flag is **not** sent on the initial Flow payment (the customer is present).

**Q: How are renewals processed?**
As **Merchant-Initiated Transactions (MIT)**: `payment_type = Recurring`, `merchant_initiated = true`,
with `previous_payment_id` referencing the original payment. Renewals charge the stored source
(`_cko_source_id`) saved on the subscription.

**Q: A subscription was created but the first renewal failed — why (historically)?**
This was a bug (fixed in 5.1.3.x): on a 3DS subscription, the source id wasn't persisted, so the
first renewal had nothing to charge. The plugin now reliably saves `_cko_source_id` on the
subscription after the 3DS return.

---

### Subscriptions — cancelling

**Q: When a customer cancels, does it cancel immediately or need admin approval?**
**Automatic — no admin approval.** WooCommerce Subscriptions handles it: a customer cancel moves the
subscription to **Pending Cancellation** (they keep access through the period already paid for), then
it auto-transitions to **Cancelled** at the end of that billing period. Admins can cancel immediately
from the subscription edit screen if needed.

**Q: Why does it show "Pending Cancellation" instead of "Cancelled"?**
That's the expected, correct WCS behavior for a mid-term customer cancel — no further renewals are
charged, and it flips to Cancelled at period end automatically.

**Q: Does the plugin do anything special on cancellation?**
Only cleanup: for **SEPA** it cancels the mandate. For cards it does nothing extra. (A fatal error on
every cancel path was fixed in 5.1.3.x.)

---

### Subscriptions — changing the card

**Q: How does a customer change the card on an active subscription?**
My Account → Subscriptions → open one → **Change payment method** → enter a new card → submit. The
plugin runs a **zero-amount verification** of the new card (no charge), saves the new source to the
subscription, and the subscription **stays Active**. The next renewal uses the new card.

**Q: Is the customer charged when changing the card?**
No — it's an `amount = 0` verification.

**Q: Can a customer pick one of their already-saved cards on the change-payment page?**
No — the saved-cards list is hidden there. They enter a fresh card. (Selecting a saved token here
previously applied a stale source, so it was removed in favour of the reliable new-card path.)

**Q: What about "Use this payment method for all of my current subscriptions"?**
That checkbox is **hidden** in Flow mode. Our change flow completes via a redirect, where
WooCommerce's built-in "apply to all" propagation can't run reliably (risk of applying the wrong
source across subscriptions). Customers should update each subscription individually.

**Q: Can an admin change/view the card on a subscription?**
Yes. The subscription edit screen shows the Checkout.com source (`_cko_source_id`) via the standard
WCS "payment method" fields, and admins can edit it.

---

### Webhooks & order status

**Q: What is the typical order status flow?**
Authorized → **On hold** (default "authorised" status) → on capture → **Processing** → **Completed**.
Statuses are configurable in settings (Order Authorised / Order Captured).

**Q: I see a `card_verified` webhook with "no order" in the logs — is that a problem?**
No. Add-payment-method and change-payment verifications are intentionally **detached** from any order
(they're standalone $0 verifications). The webhook acknowledges them with an info log; the token/source
is saved by the plugin's own return handler, not by the webhook.

---

### Level 2 / Level 3 data

**Q: Does the plugin send Level 2 / Level 3 enhanced scheme data?**
Not currently. The plugin sends an itemised `items[]` array for APM/Flow display, but **not** the
dedicated L2/L3 structure (the `processing` object, `commodity_code`, `unit_of_measure`, reconciliation
model). L2/L3 is a scoped future enhancement — see Part B.

---

## Part B — Technical FAQ

### Architecture

**Q: How does the Flow payment session work end to end?**
It's a **two-step** flow against Checkout.com's Payment Sessions API:
1. **Create** — `ajax_create_payment_session()` posts the session request (amount, currency, items,
   customer, billing/shipping, `payment_type`, etc.) to `/payment-sessions`.
2. **Submit** — when the customer pays, `ajax_submit_payment_session()` calls
   `/payment-sessions/{id}/submit` with the component's `session_data`.

> **Critical gotcha:** the **submit step re-derives amount, reference, and capture from the order/cart**
> and overrides whatever was set at create. Any server-side enforcement (amount, capture, reference)
> must be applied in **both** steps, or submit silently undoes it.

**Q: Where is the Flow component mounted, and how is submission triggered?**
The component is created with `showPayButton: false`, so it renders **no** button. Submission is
triggered by the page's button: on checkout it's `#place_order`; on the add-payment-method page WC's
"Add payment method" button **also** has `id="place_order"`, so the same handler runs — it detects the
add-PM page and calls `ckoFlow.flowComponent.submit()` directly.

**Q: What's the 3DS return path?**
Flow redirects to the WC API endpoint `wc_checkoutcom_flow_process` →
`handle_3ds_return()`, which loads the order and delegates to `process_payment()`. Add-payment-method
returns to `wc_checkoutcom_flow_add_payment_method` → `handle_add_payment_method_return()`.

---

### CIT / MIT (subscriptions)

**Q: Where are the recurring flags set?**
- **CIT (initial, Flow):** in `ajax_create_payment_session()` — if the cart/order contains a
  subscription, `payment_type` is forced to `Recurring` and `merchant_initiated` is removed
  (customer present).
- **MIT (renewals):** handled server-side in `WC_Checkoutcom_Api_Request` —
  `merchant_initiated = true`, `payment_type = Recurring`, `previous_payment_id` from the parent order.

**Q: Where is the subscription source id stored, and how does it survive 3DS?**
On the subscription/order meta `_cko_source_id`. After a 3DS return, `handle_3ds_return()` fetches
payment details and calls `WC_Checkoutcom_Subscription::save_source_id()`. The save guards recognise a
`WC_Subscription` object (via `wcs_is_subscription()`), which is required for the change-payment path.

---

### The $0-verification pattern (add-PM + change-PM)

**Q: How does "add a card / change a card with no charge" work technically?**
Both build an `amount = 0`, `capture = false` card-verification session (forced in **both** create and
submit), then:
- **Add payment method:** `handle_add_payment_method_return()` fetches the `source.id`, saves a
  `WC_Payment_Token_CC` against the user (with fingerprint dedupe), best-effort voids, redirects.
- **Change payment method:** `process_payment()` / `handle_3ds_return()` detect a `WC_Subscription`
  and route to `handle_subscription_payment_method_change()`, which saves `_cko_source_id` to the
  subscription and returns success — **without** running order status/stock/cart logic.

**Q: Why is the verification "detached" from the order, and how?**
A $0 verification still fires `card_verified`/authorize webhooks. If those resolved the subscription as
an "order", they'd flip its status (→ on-hold) or fatal. To prevent that we:
- clear `metadata.order_id`, and
- use a **non-numeric** reference (`cko-card-change-<id>` / `cko-add-payment-method-<uid>`).
The webhook resolves orders by `metadata.order_id` then a **numeric** reference fallback — a
non-numeric reference means no order is matched. As defense-in-depth, the webhook handlers also skip
any resolved object where `wcs_is_subscription()` is true.

**Q: Why must amount/capture be forced in the submit step too?**
Because `ajax_submit_payment_session()` re-derives amount/reference/capture from the order. Without the
guard there, a card change would re-acquire the full recurring amount and capture it (a real charge),
and re-attach to the subscription. Both steps now force `amount = 0`, `capture = false`, detached
reference for verification flows.

---

### Webhooks

**Q: How does the webhook match a payment to an order?**
In order of attempt: `metadata.order_id` → numeric `reference` → session-id + payment-id combos →
payment-id in `_cko_payment_attempts` → fuzzy session-id + amount. Detached verifications match none of
these by design.

**Q: How are subscriptions protected from payment webhooks?**
Every status-changing handler (`authorize_payment`, `capture_payment`, `void_payment`,
`refund_payment`, `card_verified`, `capture_declined`, `decline_payment`) returns early (acknowledged,
no status change) when the resolved object is a `WC_Subscription`. Subscription status is owned by WCS.

**Q: What signing key does the NAS webhook HMAC use?**
The HMAC signing key is the string `"Bearer sk_xxx"` (the Authorization header value), **not** the raw
secret key. (See the webhook signature verification.)

---

### Gotchas / things to know

**Q: Booleans passed from PHP to JS via `wp_localize_script` arrive as strings.**
PHP `true` becomes the string `"1"`. Never compare `cko_flow_vars.<flag> === true` in JS — compare
against `true || '1' || 1`. (This silently broke an add-payment-method flag for a full debug cycle.)

**Q: Why `cko_get_raw_option()` instead of `get_option()` for settings?**
Polylang intercepts `get_option()` and can return stale, language-specific copies of plugin settings.
Critical settings reads use `cko_get_raw_option()` to read the raw DB value.

**Q: Asset caching after a release?**
JS/CSS are versioned by `WC_CHECKOUTCOM_PLUGIN_VERSION` (`?ver=`). Bumping the plugin version
cache-busts assets. If you ship code changes **without** bumping the version, browsers may serve stale
`payment-session.js` — so always bump the version (or hard-refresh when testing).

**Q: The loading overlay is clipped/boxed in some themes — why?**
A fixed-position overlay is clipped by any ancestor with a CSS `transform`/`filter`/`perspective`
(common in themes). A safe fix (mounting once on `document.body` without being re-created by
`updated_checkout` fragment refresh) is a pending enhancement; a naive re-parent conflicts with the
checkout re-render and must be avoided.

---

### Key files & symbols

| Area | Location |
|---|---|
| Flow gateway | `flow-integration/class-wc-gateway-checkout-com-flow.php` |
| Session create | `ajax_create_payment_session()` |
| Session submit | `ajax_submit_payment_session()` |
| 3DS return | `handle_3ds_return()` (`wc_checkoutcom_flow_process`) |
| Add-PM return | `handle_add_payment_method_return()` (`wc_checkoutcom_flow_add_payment_method`) |
| Change-PM handler | `handle_subscription_payment_method_change()` |
| Void helper | `cko_flow_void_payment()` |
| Payment fields / saved cards | `payment_fields()`, `saved_payment_methods()` |
| WCS payment meta | `add_payment_meta_field()` (maps `_cko_source_id`) |
| Client logic | `flow-integration/assets/js/payment-session.js` |
| Validation module | `flow-integration/assets/js/modules/flow-validation.js` |
| Webhooks | `includes/class-wc-checkout-com-webhook.php` |
| MIT / API requests | `includes/api/class-wc-checkoutcom-api-request.php` |
| Subscription helpers | `includes/subscription/class-wc-checkoutcom-subscription.php` |
| Build script | `bin/build.sh` (outputs `checkout-com-unified-payments-api.zip`) |

---

### Level 2 / Level 3 — scope for a future build

**Q: What would adding L2/L3 require?**
- A top-level `processing` object: `order_id`, `tax_amount`, `discount_amount`, `shipping_amount`,
  `shipping_tax_amount`, `duty_amount`.
- `items[]` with `commodity_code` and `unit_of_measure` (Measure-code enum, e.g. `EACH`), plus name
  (≤26 chars Visa/MC; ≤12 Amex), reference (≤12, not blank/zeros), `total_amount` **excluding tax**.
- Exact reconciliation: `amount = Σ(items[].total_amount) − discount_amount + tax_amount +
  shipping_amount`, all in minor units.
- Item caps: Visa/MC ≤ 50, Amex ≤ 4.
- Submit at payment **or** capture (capture overrides; **partial captures must carry it at capture**).

**Q: Recommended integration point?**
**Capture time** (`WC_Checkoutcom_Api_Request::capture_payment()`), built from the WooCommerce order —
works for both Flow and Classic, satisfies the partial-capture rule, and avoids reworking the
display-oriented Flow session items.

**Q: Prerequisites?**
US-only; commercial cards only (Business/Corporate/Purchasing/Fleet, Credit/Prepaid funded); account
must be enabled for enhanced data; `processing_channel_id` required. Ineligible transactions have the
extra data dropped automatically by Checkout.com. As of Apr 18, 2026: **Visa = L3 only**,
**Mastercard = L2 + L3**, **Amex = L2 only**. `commodity_code`/`unit_of_measure` don't exist natively
in WooCommerce → need product meta (or sensible defaults).

---

*Version history relevant to this FAQ:*
- *5.1.3 — webhook signature fix, Polylang compatibility*
- *5.1.3.1 — Apple Pay first-tap*
- *5.1.3.3 — subscription source-id persistence, cancellation fatal fix, save-card 503 fix, `$supports`
  parity, CIT flags, Add Payment Method, Change Payment Method*
- *5.1.3.4 — change-payment page UX cleanup (hide saved cards / save-card / "use for all"),
  card_verified log fix*
