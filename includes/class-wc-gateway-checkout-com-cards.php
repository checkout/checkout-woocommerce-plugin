<?php
/**
 * Card payment method main class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\Common\CustomerRequest;
use Checkout\Payments\Source\RequestTokenSource;
use Checkout\Payments\ThreeDsRequest;

include_once dirname( __DIR__ ) . '/lib/class-checkout-sdk.php';

if ( is_readable( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require dirname( __DIR__ ) . '/vendor/autoload.php';
}
include_once( 'settings/class-wc-checkoutcom-cards-settings.php' );
include_once( 'settings/class-wc-checkoutcom-webhook.php' );
include_once( 'settings/admin/class-wc-checkoutcom-admin.php' );
include_once( 'api/class-wc-checkoutcom-api-request.php' );
include_once( 'class-wc-checkout-com-webhook.php' );
include_once( 'subscription/class-wc-checkoutcom-subscription.php' );

/**
 * Class WC_Gateway_Checkout_Com_Cards for Card payment method.
 */
class WC_Gateway_Checkout_Com_Cards extends WC_Payment_Gateway_CC {

	/**
	 * WC_Gateway_Checkout_Com_Cards constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_cards';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Cards payment and general configuration', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		];

		$this->new_method_label = __( 'Use a new card', 'checkout-com-unified-payments-api' );

		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Redirection hook.
		add_action( 'woocommerce_api_wc_checkoutcom_callback', [ $this, 'callback_handler' ] );

		// Webhook handler hook.
		add_action( 'woocommerce_api_wc_checkoutcom_webhook', [ $this, 'webhook_handler' ] );
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = ( new WC_Checkoutcom_Cards_Settings )->core_settings();

		$this->form_fields = array_merge(
			$this->form_fields,
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
				],
			]
		);
	}

	/**
	 * Generate links for the admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_screen_button_html( $key, $value ) {
		WC_Checkoutcom_Admin::generate_links( $key, $value );
	}

	/**
	 * Show module settings links.
	 */
	public function admin_options() {
		if ( ! isset( $_GET['screen'] ) || '' === sanitize_text_field( $_GET['screen'] ) ) {
			parent::admin_options();
		} else {

			$screen = sanitize_text_field( $_GET['screen'] );

			$test = [
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Settings', 'checkout-com-unified-payments-api' ),
				],
			];

			echo '<h3>' . $this->method_title . ' </h3>';
			echo '<p>' . $this->method_description . ' </p>';
			$this->generate_screen_button_html( 'screen_button', $test );

			if ( 'orders_settings' === $screen ) {
				echo '<table class="form-table">';
				WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::order_settings() );
				echo '</table>';
			} elseif ( 'card_settings' === $screen ) {

				echo '<table class="form-table">';
				WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::cards_settings() );
				echo '</table>';
			} elseif ( 'debug_settings' === $screen ) {

				echo '<table class="form-table">';
				WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::debug_settings() );
				echo '</table>';
			} elseif ( 'webhook' === $screen ) {

				echo '<table class="form-table">';
				WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::webhook_settings() );
				echo '</table>';
			} else {

				echo '<table class="form-table">';
				WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::core_settings() );
				echo '</table>';
			}
		}
	}

	/**
	 * Save module settings in  woocommerce db.
	 *
	 * @return void
	 */
	public function process_admin_options() {
		if ( isset( $_GET['screen'] ) && '' !== $_GET['screen'] ) {
			if ( 'card_settings' === $_GET['screen'] ) {
				WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::cards_settings() );
			} elseif ( 'orders_settings' === $_GET['screen'] ) {
				WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::order_settings() );
			} elseif ( 'debug_settings' === $_GET['screen'] ) {
				WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::debug_settings() );
			} else {
				WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::core_settings() );
			}
			do_action( 'woocommerce_update_options_' . $this->id );
		} else {
			parent::process_admin_options();
			do_action( 'woocommerce_update_options_' . $this->id );
		}
	}

	/**
	 * Show frames js on checkout page
	 */
	public function payment_fields() {
		$save_card             = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		$mada_enable           = '1' === WC_Admin_Settings::get_option( 'ckocom_card_mada', '0' );
		$require_cvv           = WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' );
		$is_mada_token         = false;
		$card_validation_alert = __( 'Please enter your card details.', 'checkout-com-unified-payments-api' );
		$iframe_style          = WC_Admin_Settings::get_option( 'ckocom_iframe_style', '0' );

		?>
<input type="hidden" id="debug" value='<?php echo WC_Admin_Settings::get_option( 'cko_console_logging' ); ?>' ;></input>
<input type="hidden" id="public-key" value='<?php echo $this->get_option( 'ckocom_pk' ); ?>'></input>
<input type="hidden" id="localization" value='<?php echo $this->get_localisation(); ?>'></input>
<input type="hidden" id="multiFrame" value='<?php echo $iframe_style; ?>'></input>
<input type="hidden" id="cko-icons"
	value='<?php echo  WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/'; ?>'></input>
<input type="hidden" id="is-mada" value='<?php echo $mada_enable; ?>'></input>
<input type="hidden" id="mada-token" value='<?php echo $is_mada_token; ?>'></input>
<input type="hidden" id="user-logged-in" value='<?php echo is_user_logged_in(); ?>'></input>
<input type="hidden" id="card-validation-alert" value='<?php echo $card_validation_alert; ?>'></input>
		<?php

		// check if user is logged-in or a guest.
		if ( ! is_user_logged_in() ) {
			?>
<script>
jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide()
</script>
			<?php
		}

		// check if saved card enable from module setting.
		if ( $save_card ) {
			// Show available saved cards.
			$this->saved_payment_methods();

			// check if mada enable in module settings.
			if ( $mada_enable ) {
				foreach ( $this->get_tokens() as $item ) {
					$token_id = $item->get_id();
					$token    = WC_Payment_Tokens::get( $token_id );
					// check if token is mada.
					$is_mada = $token->get_meta( 'is_mada' );
					if ( $is_mada ) {
						$is_mada_token = $token_id;
					}
				}
			}
		}

		// Check if require cvv or mada is enabled from module setting.
		if ( $require_cvv || $mada_enable ) {
			?>
<div class="cko-cvv" style="display: none;padding-top: 10px;">
	<p class="validate-required" id="cko-cvv" data-priority="10">
		<label for="cko-cvv"><?php esc_html_e( 'Card Code', 'checkout-com-unified-payments-api' ); ?> <span
				class="required">*</span></label>
		<input id="cko-cvv" type="text" autocomplete="off" class="input-text"
			placeholder="<?php esc_attr_e( 'CVV', 'checkout-com-unified-payments-api' ); ?>"
			name="<?php echo esc_attr( $this->id ); ?>-card-cvv" />
	</p>
</div>
<?php } ?>
<div class="cko-form" style="display: none; padding-top: 10px;padding-bottom: 5px;">
	<input type="hidden" id="cko-card-token" name="cko-card-token" value="" />
	<input type="hidden" id="cko-card-bin" name="cko-card-bin" value="" />

		<?php
		if ( '0' === $iframe_style ) {
			?>
	<div class="one-liner">
		<!-- frames will be loaded here -->
		<div class="card-frame"></div>
	</div>
			<?php
		} else {
			?>
	<div class="multi-frame">
		<div class="input-container card-number">
			<div class="icon-container">
				<img id="icon-card-number"
					src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/card.svg'; ?>"
					alt="PAN" />
			</div>
			<div class="card-number-frame"></div>
			<div class="icon-container payment-method">
				<img id="logo-payment-method" />
			</div>
			<div class="icon-container">
				<img id="icon-card-number-error"
					src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg'; ?>" />
			</div>
		</div>

		<div class="date-and-code">
			<div>
				<div class="input-container expiry-date">
					<div class="icon-container">
						<img id="icon-expiry-date"
							src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/exp-date.svg'; ?>"
							alt="Expiry date" />
					</div>
					<div class="expiry-date-frame"></div>
					<div class="icon-container">
						<img id="icon-expiry-date-error"
							src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg'; ?>" />
					</div>
				</div>
			</div>

			<div>
				<div class="input-container cvv">
					<div class="icon-container">
						<img id="icon-cvv"
							src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/cvv.svg'; ?>"
							alt="CVV" />
					</div>
					<div class="cvv-frame"></div>
					<div class="icon-container">
						<img id="icon-cvv-error"
							src="<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg'; ?>" />
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php } ?>

	<!-- frame integration js file -->
	<script src='<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-frames-integration.js'; ?>'></script>

</div>

<!-- Show save card checkbox if this is selected on admin-->
<div class="cko-save-card-checkbox" style="display: none">
		<?php
		if ( $save_card ) {
			$this->save_payment_method_checkbox();
		}
		?>
</div>
		<?php
	}

	/**
	 * Process payment with card payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		global $woocommerce;
		$order = wc_get_order( $order_id );

		// Check if card token or token_id exist.
		if ( sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] ) ) {
			if ( 'new' === sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] ) ) {
				$arg = sanitize_text_field( $_POST['cko-card-token'] );
			} else {
				$arg = sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] );
			}
		}

		// Check if empty card token and empty token_id.
		if ( empty( $arg ) ) {
			// check if card token exist.
			if ( sanitize_text_field( $_POST['cko-card-token'] ) ) {
				$arg = sanitize_text_field( $_POST['cko-card-token'] );
			} else {
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'checkout-com-unified-payments-api' ), 'error' );

				return;
			}
		}

		// Create payment with card token.
		$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );

			return;
		}

		// Get save card config from module setting.
		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );

		// Check if result contains 3d redirection url.
		if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

			// Check if save card is enable and customer select to save card.
			if ( $save_card && isset( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) && sanitize_text_field( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) ) {
				// Save in session for 3D secure payment.
				$_SESSION['wc-wc_checkout_com_cards-new-payment-method'] = isset( $_POST['wc-wc_checkout_com_cards-new-payment-method'] );
			}

			// Redirect to 3D secure page.
			return [
				'result'   => 'success',
				'redirect' => $result['3d'],
			];
		}

		// Save card in db.
		if ( $save_card && isset( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) && sanitize_text_field( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) ) {
			$this->save_token( get_current_user_id(), $result );
		}

		// Save source id for subscription.
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
		}

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $result['action_id'] );
		update_post_meta( $order_id, '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// Check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );
		}

		// Add notes for the order.
		$order->add_order_note( $message );

		$order_status = $order->get_status();

		if ( 'pending' === $order_status || 'failed' === $order_status ) {
			update_post_meta( $order_id, 'cko_payment_authorized', true );
			$order->update_status( $status );
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		// Return thank you page.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Handle redirection callback.
	 */
	public function callback_handler() {
		if ( ! session_id() ) {
			session_start();
		}

		global $woocommerce;

		if ( $_REQUEST['cko-session-id'] ) {
			$cko_session_id = $_REQUEST['cko-session-id'];
		}

		// Verify session id.
		$result = (array) ( new WC_Checkoutcom_Api_Request )->verify_session( $cko_session_id );

		// Redirect to cart if an error occurred.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			wp_redirect( wc_get_checkout_url() );
			exit();
		}

		$order_id = $result['metadata']['order_id'];
		$action   = $result['actions'];

		// Get object as an instance of WC_Subscription.
		$subscription_object = wc_get_order( $order_id );

		$order = new WC_Order( $order_id );

		// Query order by order number to check if order exist.
		if ( ! $order ) {
			$orders = wc_get_orders(
				[
					'order_number' => $order_id,
				]
			);

			$order    = $orders[0];
			$order_id = $order->get_id();
		}

		// Redirect to my-account/payment-method if card verification failed.
		// show error to customer.
		if ( 'error' === isset( $result['card_verification'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Unable to add payment method to your account.', 'checkout-com-unified-payments-api' ), 'error' );
			wp_redirect( $result['redirection_url'] );
			exit;
		}

		// Redirect to my-account/payment-method if card verification successful.
		// show notice to customer.
		if ( 'Card Verified' === isset( $result['status'] ) && isset( $result['metadata']['card_verification'] ) ) {

			$this->save_token( get_current_user_id(), $result );

			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Payment method successfully added.', 'checkout-com-unified-payments-api' ), 'notice' );
			wp_redirect( $result['metadata']['redirection_url'] );
			exit;
		}

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $action['0']['id'] );

		// if no action id and source is boleto.
		if ( null == $action['0']['id'] && 'boleto' === $result['source']['type'] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Deliberate loose comparison.
			update_post_meta( $order_id, '_transaction_id', $result['id'] );
		}

		update_post_meta( $order_id, '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $action['0']['id'] );

		// check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $action['0']['id'] );
		}

		if ( 'Canceled' === $result['status'] ) {
			$status = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Canceled - Action ID : %s', 'checkout-com-unified-payments-api' ), $action['0']['id'] );
		}

		if ( 'Captured' === $result['status'] ) {
			update_post_meta( $order_id, 'cko_payment_captured', true );
			$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Captured - Action ID : %s', 'checkout-com-unified-payments-api' ), $action['0']['id'] );
		}

		// save card to db.
		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		if ( $save_card && isset( $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] ) && $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] ) {
			$this->save_token( $order->get_user_id(), $result );
			unset( $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] );
		}

		// save source id for subscription.
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $subscription_object, $result['source']['id'] );
		}

		$order_status = $order->get_status();

		$order->add_order_note( $message );

		if ( 'pending' === $order_status || 'failed' === $order_status ) {
			update_post_meta( $order_id, 'cko_payment_authorized', true );
			$order->update_status( $status );
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		$url = esc_url( $order->get_checkout_order_received_url() );
		wp_redirect( $url );

		exit();
	}

	/**
	 * Handle add Payment Method from My Account page.
	 *
	 * @return array
	 */
	public function add_payment_method() {
		// Check if cko card token is not empty.
		if ( empty( $_POST['cko-card-token'] ) ) {
			return [
				'result'   => 'failure',
				'redirect' => wc_get_endpoint_url( 'payment-methods' ),
			];
		}

		$gateway_debug = 'yes' === WC_Admin_Settings::get_option( 'cko_gateway_responses', 'no' );

		// Load method with card token.
		$method        = new RequestTokenSource();
		$method->token = sanitize_text_field( $_POST['cko-card-token'] );

		// Initialize the Checkout Api.
		$checkout          = new Checkout_SDK();
		$payment           = $checkout->get_payment_request();
		$payment->source   = $method;
		$payment->currency = get_woocommerce_currency();

		// Load current user.
		$current_user = wp_get_current_user();

		// Set customer email and name to payment request.
		$customer        = new CustomerRequest();
		$customer->email = $current_user->user_email;
		$customer->name  = $current_user->first_name . ' ' . $current_user->last_name;

		$payment->customer = $customer;

		// Set Metadata in card verification request to use in callback handler.
		$payment->metadata = [
			'card_verification' => true,
			'redirection_url'   => wc_get_endpoint_url( 'payment-methods' ),
		];

		// Set redirection url in payment request.
		$redirection_url      = add_query_arg( 'wc-api', 'wc_checkoutcom_callback', home_url( '/' ) );
		$payment->success_url = $redirection_url;
		$payment->failure_url = $redirection_url;

		// to remove.
		$three_ds          = new ThreeDsRequest();
		$three_ds->enabled = true;

		$payment->three_ds = $three_ds;
		// end to remove.

		try {
			$response = $checkout->get_builder()->getPaymentsClient()->requestPayment( $payment );

			// Check if payment successful.
			if ( WC_Checkoutcom_Utility::is_successful( $response ) ) {

				// Check if payment is 3D secure.
				if ( WC_Checkoutcom_Utility::is_pending( $response ) ) {
					// Check if redirection link exist.
					if ( WC_Checkoutcom_Utility::get_redirect_url( $response ) ) {
						// Return 3d redirection url.
						wp_redirect( WC_Checkoutcom_Utility::get_redirect_url( $response ) );
						exit();

					} else {
						return [
							'result'   => 'failure',
							'redirect' => wc_get_endpoint_url( 'payment-methods' ),
						];
					}
				} else {
					$this->save_token( $current_user->ID, (array) $response );

					return [
						'result' => 'success',
					];
				}
			} else {
				return [
					'result'   => 'failure',
					'redirect' => wc_get_endpoint_url( 'payment-methods' ),
				];
			}
		} catch ( CheckoutApiException $ex ) {
			$error_message = esc_html__( 'An error has occurred while processing your cancel request. ', 'checkout-com-unified-payments-api' );

			// check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return [
				'result'   => 'failure',
				'redirect' => wc_get_endpoint_url( 'payment-methods' ),
			];
		}
	}

	/**
	 * Save card.
	 *
	 * @param int   $user_id User id.
	 * @param array $payment_response Payment response.
	 *
	 * @return void
	 */
	public function save_token( $user_id, $payment_response ) {
		// Check if payment response is not null.
		if ( ! is_null( $payment_response ) ) {
			// argument to check token.
			$arg = [
				'user_id'    => $user_id,
				'gateway_id' => $this->id,
			];

			// Query token by userid and gateway id.
			$token = WC_Payment_Tokens::get_tokens( $arg );

			foreach ( $token as $tok ) {
				$token_data = $tok->get_data();
				// do not save source if it already exists in db.
				if ( $token_data['token'] === $payment_response['source']['id'] ) {
					return;
				}
			}

			// Save source_id in db.
			$token = new WC_Payment_Token_CC();
			$token->set_token( (string) $payment_response['source']['id'] );
			$token->set_gateway_id( $this->id );
			$token->set_card_type( (string) $payment_response['source']['scheme'] );
			$token->set_last4( $payment_response['source']['last4'] );
			$token->set_expiry_month( $payment_response['source']['expiry_month'] );
			$token->set_expiry_year( $payment_response['source']['expiry_year'] );
			$token->set_user_id( $user_id );

			// Check if session has is mada and set token metadata.
			if ( isset( $_SESSION['cko-is-mada'] ) ) {
				$token->add_meta_data( 'is_mada', true, true );
				unset( $_SESSION['cko-is-mada'] );
			}

			$token->save();
		}
	}

	/**
	 * Handle Card refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param null   $amount  Amount to refund.
	 * @param string $reason Reason for refund.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return false;
		}

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $result['action_id'] );
		update_post_meta( $order_id, 'cko_payment_refunded', true );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Partially refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		// add note for order.
		$order->add_order_note( $message );

		// when true is returned, status is changed to refunded automatically.
		return true;
	}

	/**
	 * Webhook handler.
	 * Handle Webhook.
	 *
	 * @return bool|int|void
	 */
	public function webhook_handler() {
		// webhook_url_format = http://localhost/wordpress-5.0.2/wordpress/?wc-api=wc_checkoutcom_webhook .

		// Get webhook data.
		$data = json_decode( file_get_contents( 'php://input' ) );

		// Return to home page if empty data.
		if ( empty( $data ) ) {
			wp_redirect( get_home_url() );
			exit();
		}

		// Create apache function if not exist to get header authorization.
		if ( ! function_exists( 'apache_request_headers' ) ) {
			/**
			 * Get request headers.
			 *
			 * @return array
			 */
			function apache_request_headers() {
				$arh     = [];
				$rx_http = '/\AHTTP_/';
				foreach ( $_SERVER as $key => $val ) {
					if ( preg_match( $rx_http, $key ) ) {
						$arh_key    = preg_replace( $rx_http, '', $key );
						$rx_matches = [];
						$rx_matches = explode( '_', $arh_key );
						if ( count( $rx_matches ) > 0 and strlen( $arh_key ) > 2 ) {
							foreach ( $rx_matches as $ak_key => $ak_val ) {
								$rx_matches[ $ak_key ] = ucfirst( $ak_val );
							}
							$arh_key = implode( '-', $rx_matches );
						}
						$arh[ $arh_key ] = $val;
					}
				}
				return( $arh );
			}
		}

		$header           = array_change_key_case( apache_request_headers(), CASE_LOWER );
		$header_signature = $header['cko-signature'];

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$raw_event     = file_get_contents( 'php://input' );

		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

		$signature = WC_Checkoutcom_Utility::verify_signature( $raw_event, $core_settings['ckocom_sk'], $header_signature );

		// check if cko signature matches.
		if ( false === $signature ) {
			return http_response_code( 401 );
		}

		$payment_id = get_post_meta( $data->data->metadata->order_id, '_cko_payment_id', true );

		// check if payment ID matches that of the webhook.
		if ( $payment_id !== $data->data->id ) {
			/* translators: 1: Payment ID, 2: Webhook ID. */
			$message = sprintf( esc_html__( 'Order payment Id (%1$s) does not match that of the webhook (%2$s)', 'checkout-com-unified-payments-api' ), $payment_id, $data->data->id );

			WC_Checkoutcom_Utility::logger( $message, null );

			return http_response_code( 422 );
		}

		// Get webhook event type from data.
		$event_type = $data->type;

		switch ( $event_type ) {
			case 'card_verified':
				$response = WC_Checkout_Com_Webhook::card_verified( $data );
				break;
			case 'payment_approved':
				$response = WC_Checkout_Com_Webhook::authorize_payment( $data );
				break;
			case 'payment_captured':
				$response = WC_Checkout_Com_Webhook::capture_payment( $data );
				break;
			case 'payment_voided':
				$response = WC_Checkout_Com_Webhook::void_payment( $data );
				break;
			case 'payment_capture_declined':
				$response = WC_Checkout_Com_Webhook::capture_declined( $data );
				break;
			case 'payment_refunded':
				$response = WC_Checkout_Com_Webhook::refund_payment( $data );
				break;
			case 'payment_canceled':
				$response = WC_Checkout_Com_Webhook::cancel_payment( $data );
				break;
			case 'payment_declined':
				$response = WC_Checkout_Com_Webhook::decline_payment( $data );
				break;

			default:
				$response = true;
				break;
		}

		$http_code = $response ? 200 : 400;

		return http_response_code( $http_code );
	}

	/**
	 * Get localisation.
	 *
	 * @return string
	 */
	public function get_localisation() {
		$woo_locale = str_replace( '_', '-', get_locale() );
		$locale     = substr( $woo_locale, 0, 2 );

		switch ( $locale ) {
			case 'en':
				$localization = 'EN-GB';
				break;
			case 'it':
				$localization = 'IT-IT';
				break;
			case 'nl':
				$localization = 'NL-NL';
				break;
			case 'fr':
				$localization = 'FR-FR';
				break;
			case 'de':
				$localization = 'DE-DE';
				break;
			case 'kr':
				$localization = 'KR-KR';
				break;
			case 'es':
				$localization = 'ES-ES';
				break;
			default:
				$localization = WC_Admin_Settings::get_option( 'ckocom_language_fallback', 'EN-GB' );
		}

		return $localization;
	}

}
