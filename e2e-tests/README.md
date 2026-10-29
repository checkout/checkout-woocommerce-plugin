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
