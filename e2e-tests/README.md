# Checkout.com WooCommerce — E2E tests

Playwright tests that drive real checkouts against a **pinned, disposable WooCommerce store**
(wp-env) and the **Checkout.com sandbox**.

## Quick start

```bash
# from the repo root: build the Blocks assets the plugin loads (build/ is not committed)
npm ci && npm run build

cd e2e-tests
npm ci
npx playwright install chromium
cp .env.example .env          # add your Checkout.com SANDBOX keys

npm run env:start             # WordPress + WooCommerce on http://localhost:8890
npm run env:seed              # store settings, products, customer, gateway config (re-runnable)
npm test                      # all projects (classic + blocks)
npm run test:smoke            # @smoke only
npm run report                # open the HTML report
```

Stop with `npm run env:stop`; wipe the store with `npm run env:destroy`, then start and seed again.

## What is pinned

`/.wp-env.json` pins WordPress 7.1.4, WooCommerce 11.1.0 and PHP 8.3, and mounts the
plugin from this repository. Ports 8890/8891 avoid clashing with other local wp-env sites.

## How the suite is organised

| Path | Purpose |
|---|---|
| `bin/e2e/seed.sh` | Idempotent store setup from env vars (keys never committed). |
| `bin/e2e/checkout-layout.sh` | Switches the checkout page between the classic shortcode and the Checkout block. |
| `src/fixtures.ts` | Switches the layout once per worker; `checkout`, `addToCart`, `loginAsCustomer` fixtures. |
| `src/pages/` | `FlowComponent` (Flow iframes), `ThreeDSChallenge` (sandbox simulator), classic/Blocks `CheckoutPage`. |
| `src/support/assertions.ts` | `expectOrderPaidByCheckoutCom()` — the three-place check. |
| `tests/checkout/` | Runs once per project: `classic` and `blocks`. |

## Test catalogue

| Project | Spec | Covers |
|---|---|---|
| classic, blocks | `checkout/card-payment` | Guest and logged-in card payment (3DS challenge completed) — `@smoke` |
| classic, blocks | `checkout/card-failures` | Declined card · failed 3DS (3 wrong passwords) · invalid number / expired / short CVV rejected before payment · retry after decline ends with one paid order |
| classic, blocks | `checkout/saved-cards` | Card saved only when ticked · pay with saved card · My Account add card (approved / fails verification) |
| server | `server/webhooks` | Signed webhooks: capture, duplicate delivery, under-capture, out-of-order approved, full/partial refund, void, decline · unsigned/forged rejected · event for another order's payment ignored |
| server | `server/security` | Regression tests for the payment-verification fixes: return URL without/with wrong key, unknown/reused/under-amount/other-currency/other-order payments, stored ids don't skip verification, fail closed without SDK, wallet result gate |
| server | `server/refunds` | Admin full and partial refund to Checkout.com |
| server | `server/live-webhooks` | Real Checkout.com webhooks complete an order (`@webhook-live`, opt-in) |

Server tests build paid orders without a browser (`src/support/orders.ts`): a pending order,
a real sandbox payment for its exact amount, then the shopper's return URL — so the plugin's
own verification authorises it.

### Known bugs (tests marked `test.fail`)

A test that describes correct behaviour for a known, not-yet-fixed bug is marked `test.fail()`.
Playwright reports it green while it fails and red the moment it starts passing - then remove
the `test.fail()` line. There are currently **none**: the bugs found while building the suite
(saved-card payment id, webhook order lookup, late approval reopening cancelled orders,
programmatic refund amount) are fixed on `fix/payment-verification`.

`security › a saved card can only be used by the customer who owns it` is `test.fixme`: its
WP-CLI call could not run in the session that wrote it. Run it from a normal terminal and
remove the `fixme`.

### Sandbox test data

Verified against the sandbox: `4242 4242 4242 4242` approves (and is 3DS-challenged because
the plugin forces 3DS; password `Checkout1!`), `4544 2491 6767 3670` declines (20051).
This sandbox does **not** simulate declines by amount.

## Live webhooks (tunnel)

The store is on `localhost`, so Checkout.com cannot deliver webhooks to it. To test real
delivery: expose it (`cloudflared tunnel --url http://localhost:8890`), register
`<tunnel-url>/?wc-api=wc_checkoutcom_webhook` as a Checkout.com workflow (the plugin's
`WC_Checkoutcom_Workflows::create( $url )` does this with the plugin's events and signing key),
then run with `E2E_LIVE_WEBHOOKS=1`. While live delivery is on, real events race the signed
webhook tests; the one test that this breaks (`payment_voided`) is skipped. **Delete the
workflow when done** — and prefer a sandbox account dedicated to testing: a shared account
sends every integration's events to your tunnel.

## Rules for writing tests

- **Assert in three places.** A thank-you page proves nothing on its own. Every payment test
  must also check the WooCommerce order and the Checkout.com payment (amount, currency,
  status) — use `expectOrderPaidByCheckoutCom()`.
- **Wait on signals, not time.** Flow is ready when `#flow-container` has
  `data-cko-flow-ready="1"` (set by the plugin from the SDK's `onReady`). Classic checkout is
  settled when no AJAX/overlay has been seen for 1.5s. No fixed `waitForTimeout` for readiness.
- **Fail loudly.** A missing iframe/field is a failure, never a logged warning.
- **Own your data.** Each test creates its own order with a unique email; tests run serially
  (`workers: 1`) because they share one store.
- **3DS is part of the normal flow.** The plugin forces 3DS on; the page objects complete the
  sandbox challenge (password `Checkout1!`) automatically.

## CI

`.github/workflows/e2e.yml` runs `@smoke` on every PR (Chromium, classic + Blocks) and uploads
the report and traces. It needs these repository secrets (use a **dedicated** sandbox account):
`E2E_CKO_PUBLIC_KEY`, `E2E_CKO_SECRET_KEY`, and optionally `E2E_CKO_CLIENT_ID`,
`E2E_CKO_PROCESSING_CHANNEL_ID`. Without them (e.g. PRs from forks) the job skips with a notice.
