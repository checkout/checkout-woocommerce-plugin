# Manual release test: Checkout.com for WooCommerce (Flow)

Run this on a **staging store reachable from the internet** (so Checkout.com can deliver webhooks), with **sandbox** keys. Record pass/fail and the order number for each row.

## 0. Setup

| # | Step | Expected |
|---|---|---|
| 0.1 | Install the release zip on a clean site (Plugins → Add New → Upload). Activate. | Activates with no PHP errors or warnings. |
| 0.2 | **Upgrade test**: on a site running the previous version, upload the zip over it. | Settings (keys, Client ID, Processing Channel ID, Flow options) are kept. |
| 0.3 | WooCommerce → Settings → Payments → Checkout.com: Checkout mode **Flow**, sandbox keys, Client ID, Processing Channel ID (if your account needs one). Save, reload. | Values persist after reload. Secret key field is masked. |
| 0.4 | Register the webhook from the plugin settings. In the Checkout.com dashboard, confirm a workflow points at `https://<store>/?wc-api=wc_checkoutcom_webhook`. | Workflow exists and is active. |
| 0.5 | Enable "Save cards". Leave 3DS on (default). | — |

**Test cards** (any future expiry, CVV `100`):
- `4242 4242 4242 4242`: approves. Usually shows a **3DS challenge**; the password is `Checkout1!`.
- `4544 2491 6767 3670`: **declined** (Insufficient Funds).
- 3DS failure: use `4242…` and enter a wrong password **3 times**.

For every paid order, check **three places**:
1. The shopper sees the thank-you page.
2. The WooCommerce order status and notes are right.
3. Checkout.com shows the payment with the **same amount and currency** as the order.

## 1. Checkout: classic (shortcode) and Blocks

Do sections 1–3 once with the **classic** checkout page (`[woocommerce_checkout]`) and once with the **Checkout block**.

| # | Scenario | Expected |
|---|---|---|
| 1.1 | Guest pays with `4242`, completes 3DS. | Thank-you page. Order **On hold**, then **Processing** within ~1 minute (capture webhook). Notes show "Payment Authorized" and "Payment Captured". |
| 1.2 | Logged-in customer pays with `4242`. | As 1.1, order belongs to the customer. |
| 1.3 | Apply a coupon, then pay. | The amount charged at Checkout.com equals the **discounted** order total. |
| 1.4 | Physical product with paid shipping. | The amount charged includes shipping. |
| 1.5 | Change the billing address/postcode, **wait 3 seconds**, then pay. | Payment completes normally (no stuck "pending" order). |

## 2. Failures

| # | Scenario | Expected |
|---|---|---|
| 2.1 | Pay with the decline card. | A clear error is shown. No thank-you page. The order is **Failed** (or stays Pending), never On hold/Processing. |
| 2.2 | Fail 3DS (3 wrong passwords). | An error is shown. The order is not authorised. |
| 2.3 | Invalid card number `4242 4242 4242 4241`. | "Card number is incomplete/invalid". No payment attempted. |
| 2.4 | Expired date (e.g. 01/20) / 1-digit CVV. | Field error. No payment attempted. |
| 2.5 | After 2.1, pay again with `4242` on the page you are left on. | Success. **Exactly one** paid order for this purchase. |

## 3. Saved cards (logged-in customer)

| # | Scenario | Expected |
|---|---|---|
| 3.1 | Pay with `4242` **without** ticking "Save". | My Account → Payment methods: no new card. |
| 3.2 | Pay with `4242` and tick "Save". | The card appears in Payment methods. |
| 3.3 | New order, choose the saved card, Place order. | Order has a **Transaction ID / Payment ID**, goes On hold, then **Processing** after the capture webhook. *(This was a bug: saved-card orders used to stay On hold with no payment id.)* |
| 3.4 | As 3.3, on a store/setting where the saved card is **not** 3DS-challenged. | Same as 3.3. |
| 3.5 | My Account → Add payment method with `4242`. | "Payment method successfully added". |
| 3.6 | My Account → Add payment method with the decline card. | An error is shown. No card saved. |

## 4. Admin actions

| # | Scenario | Expected |
|---|---|---|
| 4.1 | On a **Processing** order: Refund → full amount → "Refund via Checkout.com". | Order **Refunded**. Checkout.com shows the refund. |
| 4.2 | Partial refund (e.g. 2.00). | The refund line shows 2.00. Order stays Processing. Checkout.com shows a partial refund of 2.00. |
| 4.3 | On an **On hold** order (authorised, not yet captured): change the status to **Cancelled**. Wait for any capture webhook. | The order **stays Cancelled**. A note says the webhook was ignored because the order is closed. |
| 4.4 | Void/cancel an authorised payment from the Checkout.com dashboard. | The order goes **Cancelled** and stays Cancelled. |
| 4.5 | Create an order in admin and send the customer a payment link; pay it. | Order paid; three-place check passes. |
| 4.6 | Open a **Failed** order's "Pay for this order" link and pay with `4242`. | Order completes. |
| 4.7 | Open a **Cancelled** order's payment link (if reachable) and try to pay. | "This order can no longer be paid". No payment is applied. |

## 5. Wallets (cannot be automated)

| # | Scenario | Expected |
|---|---|---|
| 5.1 | Apple Pay inside Flow: Safari on a real iPhone/Mac with a sandbox Apple Pay card. | Payment completes; three-place check passes. |
| 5.2 | Apple Pay on Chrome. | Apple Pay is not shown (or works if "Apple Pay on all browsers" is enabled). |
| 5.3 | Google Pay inside Flow (Chrome, a Google account with a test card). | Payment completes; three-place check passes. |
| 5.4 | Express buttons (cart/product page), if the merchant uses them. | Order total includes shipping/coupons. |

## 6. Subscriptions (only if WooCommerce Subscriptions is used)

| # | Scenario | Expected |
|---|---|---|
| 6.1 | Buy a subscription with `4242`. | The parent order is paid; the subscription is Active. |
| 6.2 | Free-trial subscription ($0 today). | Card verified; subscription Active; nothing charged. |
| 6.3 | Subscription → "Renew now" (or WooCommerce → Status → Scheduled Actions → run renewal). | The renewal order is paid without the customer. |
| 6.4 | My Account → Subscription → Change payment method. | New card saved on the subscription; next renewal uses it. |

## 7. Logs and health

| # | Check | Expected |
|---|---|---|
| 7.1 | With `WP_DEBUG_LOG` on, browse the shop, cart and checkout a few times. Search `wp-content/debug.log` for `sk_`, `Bearer`, `Duplicate entry`. | **No matches.** |
| 7.2 | WooCommerce → Status → Logs: open the Checkout.com logs. | No secret key in them. |
| 7.3 | WooCommerce → Status → Scheduled Actions: look for failed `checkout` actions. | None failing repeatedly. |

## Sign-off

- Tester, date, plugin version, WooCommerce version, WordPress version, PHP version.
- List every failed row with its order number and a screenshot of the order notes.
