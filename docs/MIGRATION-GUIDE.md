# Migration Guide — Subscriptions & Saved Cards (Checkout.com)

Moving your WooCommerce **subscriptions** and **saved cards** to Checkout.com. Written to be quick to read;
the exact field names for whoever runs the import are at the end.

---

## The idea in one minute

Your old provider hands your card data to Checkout.com. Checkout.com stores the cards and gives you back a
new ID for each one — the **`source_id`** (looks like `src_xxxx`). That ID is what Checkout.com charges from
now on. You then put each `source_id` onto the right subscription and into your saved-cards list.

One extra thing for subscriptions: card networks want every renewal to point back to the **first payment**.
That reference is called the **scheme transaction ID**. You must get these from your old provider too, or some
renewals can be declined.

---

## What you need before you start

1. **`source_id`s** — Checkout.com gives you these (after vaulting your cards). *Your old provider does not.*
2. **Scheme transaction IDs** — ask your **old provider** for these (one per card). Needed so renewals aren't declined.
3. Know your mode: **Flow** (gateway `wc_checkout_com_flow`) or **Classic** (`wc_checkout_com_cards`).
4. **Back up your database**, and test on staging first.

---

## Step 1 — Migrate subscriptions

For each subscription, set these values **on the subscription**:

| Set this | To this |
|---|---|
| Source ID | the card's `source_id` (`src_xxxx`) |
| Payment method | `wc_checkout_com_flow` (or `wc_checkout_com_cards` for Classic) |
| First-payment reference | the **scheme transaction ID** from your old provider |
| Auto-renew | on (manual renewal **off**) |
| Status | active |

That's it — renewals will then charge the new card automatically.

> ✅ **New subscriptions created on Checkout.com need none of this** — it's automatic.
> ⚠️ The **scheme transaction ID** matters: without it, the **first renewal may be declined** even though the card is fine.

---

## Step 2 — Migrate saved cards

So customers see their cards under **My Account → Payment methods**, add each card with:

| Set this | To this |
|---|---|
| Token | the card's `source_id` (`src_xxxx`) |
| Gateway | `wc_checkout_com_flow` (or `wc_checkout_com_cards`) |
| Customer | the customer's user account |
| Card scheme | e.g. `visa`, `mastercard` |
| Card details | last 4 digits, expiry month, expiry year |

---

## Step 3 — Check it worked

- Open a migrated subscription → **process a renewal** → it charges the new card. ✅
- Log in as a customer → **My Account → Payment methods** → the cards appear. ✅
- Place a test order with a saved card → it charges. ✅

If a renewal is **declined**, the most common cause is a **missing scheme transaction ID** (Step 1) — go back and add it.

---

## Quick FAQ

**Does my old provider give me the `source_id`s?** No — **Checkout.com** does, after it vaults your cards.

**What if a subscription has no original order?** That's fine — put everything on the subscription itself. You do **not** need to create a fake order.

**Do I need the scheme transaction ID for brand-new Checkout.com subscriptions?** No — only for migrated ones.

**Will my old saved cards stop working?** Run both providers **in parallel** during the switch: old cards on the old gateway, new/migrated cards on Checkout.com. No downtime.

---
---

## Technical reference (for whoever runs the import)

> Plugin v5.1.3.x. Use the WooCommerce API (WP-CLI/PHP) where possible — it writes to the right place
> automatically whether or not **HPOS** (High-Performance Order Storage) is enabled. Use raw SQL only if you
> know your storage mode.

### Field map
| Concept | WooCommerce field | Notes |
|---|---|---|
| Subscription source | meta `_cko_source_id` **on the subscription** | what renewals charge |
| Subscription payment method | `payment_method` / `payment_method_title` | `wc_checkout_com_flow` / `Checkout.com` |
| Subscription auto-renew | meta `_requires_manual_renewal` = `false` | |
| Subscription first-payment ref | meta `_cko_payment_id` **on the subscription** | scheme transaction ID; sent as `previous_payment_id` on renewals (**v5.1.3.5+**) |
| Saved card | `wp_woocommerce_payment_tokens`: `token`=`source_id`, `gateway_id`, `user_id`, `type`=`CC`, `is_default` | standard WC tables (HPOS-independent) |
| Saved card details | `wp_woocommerce_payment_tokenmeta`: `last4`, `expiry_month` (2-digit), `expiry_year`, `card_type` | optional `fingerprint` for de-dup |

### Where subscription meta lives
- **HPOS on:** `wp_wc_orders_meta` (`order_id`, `meta_key`, `meta_value`); `payment_method` is a column in `wp_wc_orders`.
- **HPOS off:** `wp_postmeta` (`post_id`, `meta_key`, `meta_value`); payment method is meta `_payment_method` / `_payment_method_title`.

### Recommended import method (WP-CLI / PHP, HPOS-safe)
```php
// Subscriptions — wp eval-file migrate-subscriptions.php
// rows: subscription_id => [ source_id, scheme_transaction_id ]
$rows = [ 1234 => [ 'src_xxx', 'scheme_txn_id_xxx' ] ];
foreach ( $rows as $id => $r ) {
    $sub = wcs_get_subscription( $id );
    if ( ! $sub ) { continue; }
    $sub->update_meta_data( '_cko_source_id', $r[0] );
    $sub->update_meta_data( '_cko_payment_id', $r[1] ); // scheme transaction id (previous_payment_id)
    $sub->set_payment_method( 'wc_checkout_com_flow' );
    $sub->set_payment_method_title( 'Checkout.com' );
    $sub->set_requires_manual_renewal( false );
    $sub->save();
}
```
```php
// Saved cards — wp eval-file migrate-cards.php
// rows: [ user_id, source_id, scheme, last4, exp_month, exp_year, is_default ]
$rows = [ [ 1, 'src_xxx', 'visa', '4242', '10', '2029', true ] ];
foreach ( $rows as $r ) {
    list( $user_id, $source, $scheme, $last4, $mm, $yyyy, $default ) = $r;
    $t = new WC_Payment_Token_CC();
    $t->set_token( $source );
    $t->set_gateway_id( 'wc_checkout_com_flow' );
    $t->set_user_id( $user_id );
    $t->set_card_type( strtolower( $scheme ) );
    $t->set_last4( $last4 );
    $t->set_expiry_month( $mm );
    $t->set_expiry_year( $yyyy );
    if ( $default ) { $t->set_default( true ); }
    $t->save();
}
```
> After any bulk SQL, run `wp cache flush`. The saved-card `gateway_id` can be `wc_checkout_com_flow` or
> `wc_checkout_com_cards` — under Flow the plugin reads/charges tokens from both. Subscription renewals
> don't depend on the token at all; they use the subscription's `_cko_source_id` + `_cko_payment_id`.

### Notes
- `source_id` must be valid on your CKO account/environment (production `src_` → production store).
- The scheme transaction ID is **required-in-practice** for migrated subscriptions (renewals may decline without it). Confirm with your CKO migration contact whether to send it as `previous_payment_id` or whether it was associated with the `source_id` at vault import.
- Token-migration flow: incumbent exports card data + scheme transaction IDs → CKO vaults → CKO returns `source_id`s → you import. Run in **parallel** to avoid downtime.
