# Webhook Implementation Guide

## Overview
The Checkout.com WooCommerce plugin uses webhooks to receive real-time payment status updates from Checkout.com. This document explains how webhooks are implemented and processed.

## Webhook Endpoint Registration

### 1. Endpoint URL
The webhook endpoint is registered using WooCommerce's API system:

```57:61:flow-integration/class-wc-gateway-checkout-com-flow.php
add_action( 'woocommerce_api_wc_checkoutcom_webhook', [ $this, 'webhook_handler' ] );
```

**Webhook URL Format:**
```
https://your-site.com/?wc-api=wc_checkoutcom_webhook
```

### 2. Registration Location
The webhook handler is registered in the `__construct()` method of:
- `WC_Gateway_Checkout_Com_Flow` (for Flow mode)
- `WC_Gateway_Checkout_Com_Cards` (for Classic/Cards mode)

## Webhook Processing Flow

### Step 1: Receiving the Webhook
```2658:2720:flow-integration/class-wc-gateway-checkout-com-flow.php
public function webhook_handler() {
	// webhook_url_format = http://example.com/?wc-api=wc_checkoutcom_webhook .
	
	// Check if detailed webhook logging is enabled (use existing gateway responses setting)
	$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
	$webhook_debug_enabled = ( isset( $core_settings['cko_gateway_responses'] ) && $core_settings['cko_gateway_responses'] === 'yes' );
	
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( '=== WEBHOOK DEBUG: Flow webhook handler started ===' );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Request method: ' . $_SERVER['REQUEST_METHOD'] );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Request URI: ' . $_SERVER['REQUEST_URI'] );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Content type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set') );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Content length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set') );
	}

	// Check if Flow mode is enabled - if not, let Cards handler process the webhook
	$checkout_mode = $core_settings['ckocom_checkout_mode'] ?? 'cards';
	
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Core settings retrieved: ' . print_r($core_settings, true) );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Checkout mode: ' . $checkout_mode );
	}
	
	if ( 'flow' !== $checkout_mode ) {
		// Flow mode is not enabled, don't process webhook in Flow handler
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Flow mode not enabled, exiting webhook handler' );
		}
		return;
	}
	
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Flow mode confirmed, continuing webhook processing' );
	}

	try {
		// Get webhook data.
		$raw_input = file_get_contents( 'php://input' );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw input received: ' . $raw_input );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw input length: ' . strlen($raw_input) );
		}
		
		$data = json_decode( $raw_input );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: JSON decode result: ' . print_r($data, true) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: JSON last error: ' . json_last_error_msg() );
		}

	// Return to home page if empty data.
	if ( empty( $data ) ) {
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Empty data received, redirecting to home' );
		}
		wp_redirect( get_home_url() );
		exit();
	}
	
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Data validation passed, continuing processing' );
	}
```

### Step 2: Signature Verification
The webhook signature is verified to ensure the request is from Checkout.com:

```2749:2788:flow-integration/class-wc-gateway-checkout-com-flow.php
$header           = array_change_key_case( apache_request_headers(), CASE_LOWER );
if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: All headers: ' . print_r($header, true) );
}

$header_signature = $header['cko-signature'] ?? null;
if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: CKO signature from header: ' . ($header_signature ?? 'NOT FOUND') );
}

$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
$raw_event     = file_get_contents( 'php://input' );
if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw event for signature verification: ' . $raw_event );
}

// For webhook signature verification, use the same logic as the working version
$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];
$secret_key = $core_settings['ckocom_sk'];
if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Secret key (masked): ' . substr($secret_key, 0, 10) . '...' );
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Is NAS account: ' . (cko_is_nas_account() ? 'YES' : 'NO') );
}

$signature = WC_Checkoutcom_Utility::verify_signature( $raw_event, $secret_key, $header_signature );
if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Signature verification result: ' . ($signature ? 'VALID' : 'INVALID') );
}

// check if cko signature matches.
if ( false === $signature ) {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: Invalid signature - returning 401');
		WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: Signature verification failed with:');
		WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Raw event: ' . $raw_event);
		WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Header signature: ' . ($header_signature ?? 'NULL'));
		WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Secret key: ' . substr($secret_key, 0, 10) . '...');
	}
	$this->send_response(401, 'Unauthorized: Invalid signature');
}
```

### Step 3: Order Lookup
The webhook handler uses multiple methods to find the associated order:

```2802:2982:flow-integration/class-wc-gateway-checkout-com-flow.php
// Method 1: Try order_id from metadata (order-pay page has this)
if ( ! empty( $data->data->metadata->order_id ) ) {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by metadata order_id: ' . $data->data->metadata->order_id );
	}
	$order = wc_get_order( $data->data->metadata->order_id );
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by metadata order_id: ' . ($order ? 'YES (ID: ' . $order->get_id() . ')' : 'NO') );
	}
}

// Method 2: Try payment session ID (works for regular checkout)
if ( ! $order && ! empty( $data->data->metadata->cko_payment_session_id ) ) {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by payment session ID: ' . $data->data->metadata->cko_payment_session_id );
	}
	
	$orders = wc_get_orders( array(
		'limit'      => 1,
		'meta_key'   => '_cko_payment_session_id',
		'meta_value' => $data->data->metadata->cko_payment_session_id,
		'return'     => 'objects',
	) );
	
	if ( ! empty( $orders ) ) {
		$order = $orders[0];
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment session ID: YES (ID: ' . $order->get_id() . ')' );
		}
		
		// Add order_id to metadata so processing functions can find it
		if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
			$data->data->metadata->order_id = $order->get_id();
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Set metadata order_id to: ' . $order->get_id() . ' (from payment session ID lookup)' );
			}
		} else {
			// If metadata is missing or not an object, create it.
			$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Created metadata object with order_id: ' . $order->get_id() . ' (from payment session ID lookup)' );
			}
		}
	} else {
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment session ID: NO' );
		}
	}
}

// Method 3: Try reference (order number) via our stored meta
if ( ! $order && ! empty( $data->data->reference ) ) {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by reference: ' . $data->data->reference );
	}
	
	// Try direct lookup first (works for numeric order IDs)
	$order = wc_get_order( $data->data->reference );
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Direct lookup result: ' . ($order ? 'YES (ID: ' . $order->get_id() . ')' : 'NO') );
	}
	
	// Try our stored reference meta (works with ANY order number format including Sequential Order Numbers)
	if ( ! $order ) {
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Searching by _cko_order_reference meta: ' . $data->data->reference );
		}
		
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_key'   => '_cko_order_reference',
			'meta_value' => $data->data->reference,
			'return'     => 'objects',
		) );
		
		if ( ! empty( $orders ) ) {
			$order = $orders[0];
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _cko_order_reference meta: YES (ID: ' . $order->get_id() . ')' );
			}
		} else {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _cko_order_reference meta: NO' );
			}
		}
	}
	
	// If still not found, try the Sequential Order Numbers plugin meta key
	if ( ! $order ) {
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Searching by _order_number meta: ' . $data->data->reference );
		}
		
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_key'   => '_order_number',
			'meta_value' => $data->data->reference,
			'return'     => 'objects',
		) );
		
		if ( ! empty( $orders ) ) {
			$order = $orders[0];
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _order_number meta: YES (ID: ' . $order->get_id() . ')' );
			}
		} else {
			// Try searching by post name (order number might be stored there)
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('shop_order', 'shop_order_placehold') AND post_name = %s LIMIT 1",
				sanitize_title( $data->data->reference )
			) );
			
			if ( $post_id ) {
				$order = wc_get_order( $post_id );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by post_name: YES (ID: ' . $order->get_id() . ')' );
				}
			} else {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order NOT found by any method' );
				}
			}
		}
	}

	if ( $order && isset( $data->data->metadata ) ) {
		$data->data->metadata->order_id = $order->get_id();
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Set metadata order_id to: ' . $order->get_id() );
		}
	} elseif ( $order ) {
		$data->data->metadata           = new StdClass();
		$data->data->metadata->order_id = $order->get_id();
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Created metadata object with order_id: ' . $order->get_id() );
		}
	}
} else {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: No order_id in metadata and no reference found' );
	}
}

if ( $webhook_debug_enabled ) {
	WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order lookup result: ' . ($order ? 'FOUND (ID: ' . $order->get_id() . ')' : 'NOT FOUND') );
}

// Method 4: Try payment ID (works when order has been processed)
if ( ! $order && ! empty( $data->data->id ) ) {
	if ( $webhook_debug_enabled ) {
		WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by payment ID: ' . $data->data->id );
	}
	
	$orders = wc_get_orders( array(
		'limit'        => 1,
		'meta_key'     => '_cko_flow_payment_id',
		'meta_value'   => $data->data->id,
		'return'       => 'objects',
	) );

	if ( ! empty( $orders ) ) {
		$order = $orders[0];
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment ID: YES (ID: ' . $order->get_id() . ')' );
		}

		// Add order_id to $data->data->metadata.
		if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
			$data->data->metadata->order_id = $order->get_id();
		} else {
			// If metadata is missing or not an object, create it.
			$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
		}
	} else {
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment ID: NO' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: This is normal for webhooks that arrive before process_payment() completes' );
		}
	}
}
```

### Step 4: Event Type Routing
Different webhook events are routed to specific processing functions:

```3039:3103:flow-integration/class-wc-gateway-checkout-com-flow.php
switch ( $event_type ) {
	case 'card_verified':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing card_verified event' );
		}
		$response = WC_Checkout_Com_Webhook::card_verified( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: card_verified response: ' . print_r($response, true) );
		}
		break;
	case 'payment_approved':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_approved event' );
		}
		$response = WC_Checkout_Com_Webhook::authorize_payment( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_approved response: ' . print_r($response, true) );
		}
		break;
	case 'payment_captured':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_captured event' );
		}
		$response = WC_Checkout_Com_Webhook::capture_payment( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_captured response: ' . print_r($response, true) );
		}
		break;
	case 'payment_voided':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_voided event' );
		}
		$response = WC_Checkout_Com_Webhook::void_payment( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_voided response: ' . print_r($response, true) );
		}
		break;
	case 'payment_capture_declined':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_capture_declined event' );
		}
		$response = WC_Checkout_Com_Webhook::capture_declined( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_capture_declined response: ' . print_r($response, true) );
		}
		break;
	case 'payment_refunded':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_refunded event' );
		}
		$response = WC_Checkout_Com_Webhook::refund_payment( $data );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_refunded response: ' . print_r($response, true) );
		}
		break;
	case 'payment_canceled':
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_canceled event' );
		}
		$response = WC_Checkout_Com_Webhook::cancel_payment( $data );
```

## Webhook Event Processing Functions

All webhook event processing functions are in `includes/class-wc-checkout-com-webhook.php`:

### 1. `authorize_payment()` - Payment Approved
- **Event Type:** `payment_approved`
- **Purpose:** Updates order status when payment is authorized
- **Actions:**
  - Validates order ID from metadata
  - Checks if already authorized/captured
  - Sets transaction ID
  - Updates order status to configured "authorized" status (default: `on-hold`)
  - Adds order note

### 2. `capture_payment()` - Payment Captured
- **Event Type:** `payment_captured`
- **Purpose:** Updates order when payment is captured
- **Actions:**
  - Validates order ID
  - Checks if already captured
  - Sets transaction ID
  - Updates order status to configured "captured" status (default: `processing`)
  - Adds order note
  - Reduces stock levels

### 3. `card_verified()` - Card Verified
- **Event Type:** `card_verified`
- **Purpose:** Handles card verification events
- **Actions:**
  - Updates order status
  - Sets transaction ID

### 4. `void_payment()` - Payment Voided
- **Event Type:** `payment_voided`
- **Purpose:** Handles voided payments
- **Actions:**
  - Updates order status to cancelled
  - Adds order note

### 5. `refund_payment()` - Payment Refunded
- **Event Type:** `payment_refunded`
- **Purpose:** Handles refunds
- **Actions:**
  - Creates WooCommerce refund
  - Updates order status
  - Adds order note

### 6. `cancel_payment()` - Payment Canceled
- **Event Type:** `payment_canceled`
- **Purpose:** Handles cancelled payments
- **Actions:**
  - Updates order status to cancelled
  - Adds order note

## Security Features

### 1. Signature Verification
- Uses `WC_Checkoutcom_Utility::verify_signature()` to verify the `cko-signature` header
- Compares webhook payload with secret key
- Returns 401 if signature is invalid

### 2. Mode Check
- Flow handler only processes webhooks when Flow mode is enabled
- Cards handler processes when Classic mode is enabled
- Prevents cross-mode webhook processing

## Logging

### Conditional Logging
Webhook logging is controlled by the `cko_gateway_responses` setting:
- **Enabled:** Detailed debug logs for all webhook processing steps
- **Disabled:** Only error logs are written

### Log Locations
- WooCommerce logs: `/wp-content/uploads/wc-logs/`
- PHP error logs: Server error log or `/wp-content/debug.log`

### Key Log Prefixes
- `[WEBHOOK DEBUG]` - Detailed debug information (when enabled)
- `[WEBHOOK PROCESS]` - Event processing logs
- `WEBHOOK PROCESS: ERROR` - Error logs (always logged)

## Order Lookup Methods (Priority Order)

1. **Metadata `order_id`** - Direct order ID from webhook metadata (fastest)
2. **Payment Session ID** - Lookup by `_cko_payment_session_id` meta (for regular checkout)
3. **Reference/Order Number** - Multiple fallback methods:
   - Direct order ID lookup
   - `_cko_order_reference` meta
   - `_order_number` meta (Sequential Order Numbers plugin)
   - Post name lookup
4. **Payment ID** - Lookup by `_cko_flow_payment_id` meta (when order already processed)

## Webhook Response Codes

- **200 OK** - Webhook processed successfully
- **401 Unauthorized** - Invalid signature
- **404 Not Found** - Order not found
- **500 Internal Server Error** - Processing error

## Configuration

### Enable Webhook Debug Logging
1. Go to WooCommerce > Settings > Checkout.com
2. Enable "Debug Logging" or "Gateway Responses"
3. Logs will appear in `/wp-content/uploads/wc-logs/`

### Webhook URL Setup
1. In Checkout.com Dashboard, go to Webhooks
2. Add webhook URL: `https://your-site.com/?wc-api=wc_checkoutcom_webhook`
3. Select events to receive:
   - `payment_approved`
   - `payment_captured`
   - `payment_declined`
   - `payment_voided`
   - `payment_refunded`
   - `payment_canceled`
   - `card_verified`

## Common Issues

### Issue: Order Not Found
**Solution:** The webhook handler uses multiple fallback methods. Ensure:
- Payment session ID is saved to order meta (`_cko_payment_session_id`)
- Order reference is saved (`_cko_order_reference`)
- Order ID is included in payment metadata when creating payment session

### Issue: Invalid Signature
**Solution:** 
- Verify secret key is correct in plugin settings
- Check if NAS account requires "Bearer " prefix
- Ensure webhook URL is accessible and not blocked

### Issue: Webhook Not Processing
**Solution:**
- Check if correct checkout mode is enabled (Flow vs Cards)
- Verify webhook endpoint is registered
- Check WooCommerce API is enabled
- Review logs for errors










