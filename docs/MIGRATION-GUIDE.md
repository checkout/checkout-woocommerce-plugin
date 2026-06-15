# Migration Guide — Subscriptions & Saved Cards (Checkout.com WooCommerce)

How to migrate WooCommerce **subscriptions** and **saved cards** from another PSP to Checkout.com (CKO),
so that renewals and saved-card payments work on the CKO plugin.

> **Revised for current plugins (v5.1.3.x).** The key change vs. older guides: the plugin now supports
> **HPOS (High-Performance Order Storage)**, which changes *where* subscription meta is stored. Saved-card
> tables are unchanged. Read the **HPOS** note in the Subscriptions section.

---

## 0. Prerequisites

- You must have, **per saved card / per subscription**, the Checkout.com **`source_id`** (`src_xxxxxxxx…`).
  This is the reusable card credential CKO charges against. Your previous PSP / CKO migration team
  provides these (e.g. via a token-migration export).
- Decide which CKO gateway the store runs:
  - **Flow mode** → gateway id `wc_checkout_com_flow`
  - **Classic mode** → gateway id `wc_checkout_com_cards`
  - (Under Flow, the plugin reads and charges tokens from **both** ids, so either works — but prefer the
    active mode's id.)
- **Back up the database** before any bulk insert.
- Find out whether **HPOS** is enabled: **WooCommerce → Settings → Advanced → Features →
  "High-performance order storage"**. This determines the subscription storage location (Section 1).

> **Recommendation:** prefer the **WP-CLI / PHP API** methods below over raw SQL. The WooCommerce API
> writes to the correct location automatically (HPOS columns/tables vs. legacy post meta) and validates
> data. Use raw SQL only if you understand your storage mode.

---

## 1. Subscription migration

### What the plugin needs
For each migrated subscription to **auto-renew** through CKO, the **subscription** (not the parent order)
must have:

| Field | Value | Notes |
|---|---|---|
| `_cko_source_id` (meta) | the `source_id` (`src_…`) | **CKO-specific** — what renewals charge |
| `payment_method` | `wc_checkout_com_flow` or `wc_checkout_com_cards` | so WCS routes renewals to CKO |
| `payment_method_title` | e.g. `Checkout.com` | display only |
| `requires_manual_renewal` | `false` | so WCS auto-charges instead of asking the customer |
| status | `active` | a chargeable subscription |

> ⚠️ Older guides only mention `_cko_source_id`. That alone is **not enough** — without
> `payment_method` pointing at a CKO gateway (and manual renewal off), WooCommerce Subscriptions won't
> hand the renewal to the CKO plugin.

### Previous payment id / scheme transaction IDs — required for MIT renewals
Subscription renewals are **Merchant-Initiated Transactions (MIT)** with stored credentials (recurring /
UCOF). Card schemes require these to reference the **initiating transaction** via a **scheme transaction
ID** — surfaced in the Checkout.com API through `previous_payment_id`. It is **strongly recommended**:
although not strictly "mandatory", **renewals may be declined** by the issuer if it is missing.

How this is satisfied:

- **Native CKO subscriptions** (the first payment happened on CKO): the plugin auto-populates
  `previous_payment_id` from the subscription's **parent order `_cko_payment_id`** (the initial
  `pay_xxx`). CKO derives the scheme transaction ID from that prior payment. **No action needed.**

- **Migrated subscriptions** (the first payment happened at the previous PSP): there is **no CKO
  `pay_xxx`** to reference. You must obtain the **"scheme transaction IDs"** from the incumbent processor
  and provide them to Checkout.com **during the vault/token import**, so the imported `source_id` carries
  the credential-on-file history. Once imported with the scheme transaction ID, MIT renewals on that
  source succeed; the plugin sends the `source_id` and CKO applies the associated scheme transaction ID.

> **Action for migrations:** explicitly request **scheme transaction IDs** (a.k.a. network/original
> transaction identifiers) from the incumbent alongside the raw card details, and pass them to CKO with
> the card import. If they are omitted, migrated subscriptions risk **soft declines on the first
> renewal** even though the `source_id` is valid.

**Token-migration flow (high level):** incumbent exports raw card details **+ scheme transaction IDs** →
delivered securely to CKO → CKO imports into the vault → CKO exports `source_id`s → merchant imports the
`source_id`s (this guide). Run **in parallel** (legacy tokens on the legacy gateway, new/migrated cards on
CKO) to avoid downtime and double-migration. If using **network tokens**, decide before vs. after import
(provisioning cost vs. async first-use provisioning).

### Where `_cko_source_id` is stored (HPOS vs legacy)
- **HPOS enabled:** subscription meta is in **`wp_wc_orders_meta`** — columns `order_id`, `meta_key`,
  `meta_value`. `payment_method` / `payment_method_title` are **columns** in **`wp_wc_orders`**.
- **HPOS disabled (legacy):** subscription meta is in **`wp_postmeta`** — `post_id`, `meta_key`,
  `meta_value`. `payment_method` is post meta `_payment_method` / `_payment_method_title`.

This storage difference is exactly why the API method below is recommended.

### Method A — WP-CLI / PHP (recommended, HPOS-safe) ✅
Build a CSV/array of `subscription_id => source_id` and run:

```php
// wp eval-file migrate-subscriptions.php
// $rows = [ subscription_id => 'src_xxx', ... ];
$rows = [
    1234 => 'src_ekhbm6rkh65ehb6e74gzn5qtaq',
    // ...
];

$gateway_id    = 'wc_checkout_com_flow'; // or 'wc_checkout_com_cards' for Classic mode
$gateway_title = 'Checkout.com';

foreach ( $rows as $subscription_id => $source_id ) {
    $subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : false;
    if ( ! $subscription ) {
        WP_CLI::warning( "Subscription {$subscription_id} not found" );
        continue;
    }

    $subscription->update_meta_data( '_cko_source_id', $source_id );
    $subscription->set_payment_method( $gateway_id );
    $subscription->set_payment_method_title( $gateway_title );
    $subscription->set_requires_manual_renewal( false );
    $subscription->save();

    WP_CLI::log( "Migrated subscription {$subscription_id} -> {$source_id}" );
}
```
Run: `wp eval-file migrate-subscriptions.php`
This works **identically** whether or not HPOS is enabled.

### Method B — direct SQL (only if you know your storage mode)

**Legacy (HPOS off):**
```sql
-- source id
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES (1234, '_cko_source_id', 'src_ekhbm6rkh65ehb6e74gzn5qtaq');
-- route renewals to CKO + auto-renew
INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (1234, '_payment_method', 'wc_checkout_com_flow');
INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (1234, '_payment_method_title', 'Checkout.com');
INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (1234, '_requires_manual_renewal', 'false');
```

**HPOS enabled:**
```sql
-- source id (meta table)
INSERT INTO wp_wc_orders_meta (order_id, meta_key, meta_value)
VALUES (1234, '_cko_source_id', 'src_ekhbm6rkh65ehb6e74gzn5qtaq');
INSERT INTO wp_wc_orders_meta (order_id, meta_key, meta_value) VALUES (1234, '_requires_manual_renewal', 'false');
-- payment method lives in COLUMNS on wp_wc_orders, not meta
UPDATE wp_wc_orders
   SET payment_method = 'wc_checkout_com_flow', payment_method_title = 'Checkout.com'
 WHERE id = 1234;
```
> Replace the `wp_` prefix with your actual table prefix. After bulk SQL on HPOS, clear caches
> (`wp cache flush`) so WooCommerce re-reads the orders.

---

## 2. Saved cards migration

Saved cards live in **two standard WooCommerce tables** — **not affected by HPOS**, so these steps are
unchanged across storage modes.

### `wp_woocommerce_payment_tokens`
| Column | Value for CKO |
|---|---|
| `token_id` | primary key (auto) |
| `gateway_id` | `wc_checkout_com_flow` (Flow store) or `wc_checkout_com_cards` (Classic). Both are accepted under Flow. |
| `token` | the **`source_id`** (`src_…`) |
| `user_id` | the WordPress user the card belongs to |
| `type` | `CC` (token type) |
| `is_default` | `1` for the default card, else `0` |

> Note: `type` in this table is the **token type** (`CC`). The **card scheme** (Visa/Mastercard) goes in
> the `card_type` tokenmeta below.

### `wp_woocommerce_payment_tokenmeta`
For each `token_id`, add these `meta_key` / `meta_value` rows:

| `meta_key` | Example | Notes |
|---|---|---|
| `last4` | `4242` | |
| `expiry_month` | `10` | two digits |
| `expiry_year` | `2029` | four digits |
| `card_type` | `visa` | lowercase scheme |
| `fingerprint` | *(optional)* | CKO source fingerprint; used to de-duplicate cards — include if available |

### Method A — PHP / WP-CLI (recommended) ✅
```php
// wp eval-file migrate-cards.php
// rows: [ user_id, source_id, scheme, last4, exp_month, exp_year, is_default ]
$rows = [
    [ 1, 'src_ekhbm6rkh65ehb6e74gzn5qtaq', 'visa',       '4242', '10', '2025', true  ],
    [ 1, 'src_pg3rmzoeqm4e7lqqgtcv4vpmwi', 'mastercard', '6378', '10', '2029', false ],
];
$gateway_id = 'wc_checkout_com_flow'; // or 'wc_checkout_com_cards'

foreach ( $rows as $r ) {
    list( $user_id, $source_id, $scheme, $last4, $mm, $yyyy, $is_default ) = $r;

    $token = new WC_Payment_Token_CC();
    $token->set_token( $source_id );
    $token->set_gateway_id( $gateway_id );
    $token->set_user_id( $user_id );
    $token->set_card_type( strtolower( $scheme ) );
    $token->set_last4( $last4 );
    $token->set_expiry_month( $mm );
    $token->set_expiry_year( $yyyy );
    if ( $is_default ) {
        $token->set_default( true );
    }
    $token->save();
    WP_CLI::log( "Saved card {$last4} for user {$user_id}" );
}
```
Run: `wp eval-file migrate-cards.php`
This writes both tables correctly and is the safest option.

### Method B — direct SQL
```sql
-- 1) the token
INSERT INTO wp_woocommerce_payment_tokens (gateway_id, token, user_id, type, is_default)
VALUES ('wc_checkout_com_flow', 'src_ekhbm6rkh65ehb6e74gzn5qtaq', 1, 'CC', 1);
-- note the new token_id, then:
INSERT INTO wp_woocommerce_payment_tokenmeta (payment_token_id, meta_key, meta_value) VALUES
  (<token_id>, 'last4', '4242'),
  (<token_id>, 'expiry_month', '10'),
  (<token_id>, 'expiry_year', '2025'),
  (<token_id>, 'card_type', 'visa');
```

---

## 3. Verification

- **Subscriptions:** open a migrated subscription in admin → it shows the CKO gateway and (under
  "Edit payment method") the **`_cko_source_id`**. Run a **manual renewal** (Subscriptions list → row
  action "Process renewal") → it should charge the source successfully.
- **Saved cards:** log in as the customer → **My Account → Payment methods** → the migrated cards appear
  with correct last4/expiry. Place a test order using a saved card → it charges.
- **Logs:** WooCommerce → Status → Logs → `wc_checkoutcom_gateway_log` confirms the source being used.

---

## 4. Notes & caveats

- **`source_id` must be valid and reusable on your CKO account** (same processing channel/environment).
  A source tied to a different account/environment will fail at renewal.
- **Scheme transaction IDs are required for migrated subscriptions** — obtain them from the incumbent and
  include them in the CKO vault import, or first renewals may be declined (see the "Previous payment id /
  scheme transaction IDs" section). Native CKO subscriptions handle this automatically via the parent
  order's `_cko_payment_id`.
- **One subscription = one `_cko_source_id`** on the **subscription** object (not the parent order).
- **Sandbox vs production:** migrate production `src_` ids only into a production-configured store.
- **Caches:** after bulk SQL, run `wp cache flush` (and any object-cache/CDN purge) so WooCommerce
  re-reads orders/tokens.
- **Test on staging first** with a small batch, verify a renewal + a saved-card checkout, then run the
  full migration.

---

### What changed vs. the previous guide
| Topic | Previous guide | Now |
|---|---|---|
| Subscription `_cko_source_id` key | ✅ correct | ✅ unchanged |
| Subscription meta **location** | only `wp_postmeta` | `wp_postmeta` (legacy) **or** `wp_wc_orders_meta` (HPOS) — prefer WC API |
| Subscription `payment_method` / manual renewal | not mentioned | **required** for renewals to route to CKO |
| `previous_payment_id` / scheme transaction ID | not mentioned | **request scheme transaction IDs** from incumbent for migrated subs (recommended; renewals may decline without it) |
| Token tables/keys | ✅ correct | ✅ unchanged (HPOS-independent) |
| `gateway_id` | `wc_checkout_com_cards` | `wc_checkout_com_flow` for Flow stores (both accepted under Flow) |
| `fingerprint` tokenmeta | not mentioned | optional; aids de-duplication |
