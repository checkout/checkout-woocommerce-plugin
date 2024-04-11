<?php
/**
 * Card payment method main class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\Common\CustomerRequest;
use Checkout\Payments\Request\Source\RequestTokenSource;
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

		// Payment scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		// Meta field on subscription edit.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_payment_meta_field' ], 10, 2 );
	}

	/**
	 * Add subscription order payment meta field.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription An instance of a subscription object.
	 * @return array
	 */
	public function add_payment_meta_field( $payment_meta, $subscription ) {
		$source_id = $subscription->get_meta( '_cko_source_id' );

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_cko_source_id' => [
					'value' => $source_id,
					'label' => 'Checkout.com Card Source ID',
				],
			],
		];

		return $payment_meta;
	}

	/**
	 * Outputs scripts used for checkout payment.
	 */
	public function payment_scripts() {

		$core_settings    = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$card_pay_enabled = ! empty( $core_settings['enabled'] ) && 'yes' === $core_settings['enabled'];
		$suffix           = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Return if card payment is disabled.
		if ( ! $card_pay_enabled ) {
			return;
		}

		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

        if ( is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }

		// Styles.
		if ( WC_Admin_Settings::get_option( 'ckocom_iframe_style', '0' ) ) {
			wp_register_style( 'frames_style', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/multi-iframe.css', [], WC_CHECKOUTCOM_PLUGIN_VERSION );
		} else {
			wp_register_style( 'frames_style', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/style.css', [], WC_CHECKOUTCOM_PLUGIN_VERSION );
		}

		wp_enqueue_style( 'frames_style' );

		// Scripts.
		wp_register_script( 'cko-frames-script', 'https://cdn.checkout.com/js/framesv2.min.js', [ 'jquery' ], WC_CHECKOUTCOM_PLUGIN_VERSION );
		wp_enqueue_script( 'cko-frames-script' );

		$vars = [
			'card-number'           => esc_html__( 'Please enter a valid card number', 'checkout-com-unified-payments-api' ),
			'expiry-date'           => esc_html__( 'Please enter a valid expiry date', 'checkout-com-unified-payments-api' ),
			'cvv'                   => esc_html__( 'Please enter a valid cvv code', 'checkout-com-unified-payments-api' ),
			'is-add-payment-method' => is_wc_endpoint_url( 'add-payment-method' ) ? 'yes' : 'no',
		];

		wp_register_script(
			'cko-jquery-tiptip',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		wp_localize_script( 'cko-frames-script', 'cko_frames_vars', $vars );

		wp_register_script(
			'cko-frames-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-frames-integration.js',
			[ 'cko-frames-script', 'jquery', 'cko-jquery-tiptip' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);
		wp_enqueue_script( 'cko-frames-integration-script' );
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
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {
		$save_card             = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		$mada_enable           = '1' === WC_Admin_Settings::get_option( 'ckocom_card_mada', '0' );
		$require_cvv           = WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' );
		$is_mada_token         = false;
		$card_validation_alert = __( 'Please enter your card details.', 'checkout-com-unified-payments-api' );
		$iframe_style          = WC_Admin_Settings::get_option( 'ckocom_iframe_style', '0' );

		?>
		<input type="hidden" id="debug" value='<?php echo WC_Admin_Settings::get_option( 'cko_console_logging' ); ?>' />
		<input type="hidden" id="public-key" value='<?php echo $this->get_option( 'ckocom_pk' ); ?>'/>
		<input type="hidden" id="localization" value='<?php echo $this->get_localisation(); ?>'/>
		<input type="hidden" id="multiFrame" value='<?php echo $iframe_style; ?>'/>
		<input type="hidden" id="cko-icons" value='<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/'; ?>'/>
		<input type="hidden" id="is-mada" value='<?php echo $mada_enable; ?>'/>
		<input type="hidden" id="mada-token" value='<?php echo $is_mada_token; ?>'/>
		<input type="hidden" id="user-logged-in" value='<?php echo is_user_logged_in(); ?>'/>
		<input type="hidden" id="card-validation-alert" value='<?php echo $card_validation_alert; ?>'/>

		<?php if ( ! is_user_logged_in() ) : ?>
		<script>
			jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide()
		</script>
		<?php endif; ?>
		<?php

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

		// Check require cvv or mada is enabled from module setting.
		if ( $require_cvv || $mada_enable ) {
			$this->element_form_cvv();
		}

		// Render Card input fields.
		$this->element_form( $iframe_style );

		// Render Save Card input.
		$this->element_form_save_card( $save_card );
	}

	/**
	 * Renders the checkout frame elements form.
	 *
	 * @param string $iframe_style Type of iFrame style.
	 *
	 * @return void
	 */
	public function element_form( $iframe_style ) {
		?>
		<div class="cko-form" style="display: none; padding-top: 10px;padding-bottom: 5px;">
			<input type="hidden" id="cko-card-token" name="cko-card-token" value="" />
			<input type="hidden" id="cko-card-bin" name="cko-card-bin" value="" />
			<input type="hidden" id="cko-card-scheme" name="cko-card-scheme" value="" />

			<?php if ( '0' === $iframe_style ) : ?>
				<div class="one-liner">
					<!-- frames will be loaded here -->
					<div class="card-frame"></div>
				</div>
			<?php else : ?>
				<div class="multi-frame">
					<div class="input-container card-number">
						<div class="icon-container">
							<img id="icon-card-number" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/card.svg' ); ?>" alt="<?php esc_attr_e( 'PAN', 'checkout-com-unified-payments-api' ); ?>"/>
						</div>
						<div class="card-number-frame"></div>
						<div class="icon-container payment-method">
							<img id="logo-payment-method" />
						</div>
						<div class="icon-container">
							<img id="icon-card-number-error" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg' ); ?>" alt="<?php esc_attr_e( 'PAN error', 'checkout-com-unified-payments-api' ); ?>"/>
						</div>
					</div>

					<div class="date-and-code">
						<div>
							<div class="input-container expiry-date">
								<div class="icon-container">
									<img id="icon-expiry-date" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/exp-date.svg' ); ?>" alt="<?php esc_attr_e( 'Expiry date', 'checkout-com-unified-payments-api' ); ?>" />
								</div>
								<div class="expiry-date-frame"></div>
								<div class="icon-container">
									<img id="icon-expiry-date-error" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg' ); ?>" alt="<?php esc_attr_e( 'Expiry error', 'checkout-com-unified-payments-api' ); ?>"/>
								</div>
							</div>
						</div>

						<div>
							<div class="input-container cvv">
								<div class="icon-container">
									<img id="icon-cvv" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/cvv.svg' ); ?>" alt="<?php esc_attr_e( 'CVV', 'checkout-com-unified-payments-api' ); ?>" />
								</div>
								<div class="cvv-frame"></div>
								<div class="icon-container">
									<img id="icon-cvv-error" src="<?php echo esc_url( WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/card-icons/error.svg' ); ?>" alt="<?php esc_attr_e( 'CVV error', 'checkout-com-unified-payments-api' ); ?>" />
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<span class="cko-co-brand-label"><?php esc_html_e( 'Select your preferred card brand', 'checkout-com-unified-payments-api' ); ?>
				<span class="cko-information-icon-tip" data-tip="<?php esc_html_e( 'Your card has two brands and you can choose your preferred brand for this payment, if you don\'t the merchant preferred brand will be selected.', 'checkout-com-unified-payments-api' ); ?>"></span>
			</span>
		</div>
		<?php
	}

	/**
	 * Renders the cvv elements form.
	 *
	 * @return void
	 */
	public function element_form_cvv() {
		?>
		<div class="cko-cvv" style="display: none;">
			<p class="validate-required" id="cko-cvv" data-priority="10">
				<label for="cko-cvv"><?php esc_html_e( 'Card Code', 'checkout-com-unified-payments-api' ); ?> <span class="required">*</span></label>
				<input id="cko-cvv" type="text" autocomplete="off" class="input-text" placeholder="<?php esc_attr_e( 'CVV', 'checkout-com-unified-payments-api' ); ?>" name="<?php echo esc_attr( $this->id ); ?>-card-cvv"/>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the save card markup.
	 *
	 * @param string $save_card Save card enable.
	 *
	 * @return void
	 */
	public function element_form_save_card( $save_card ) {
		?>
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

		$order = wc_get_order( $order_id );

		// Check if card token or token_id exist.
		if ( WC_Checkoutcom_Api_Request::is_using_saved_payment_method() ) {
			// Saved card selected.
			$arg = sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] );
		} elseif (
			isset( $_POST['wc-wc_checkout_com_cards-payment-token'] ) &&
			'new' === sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] )
		) {
			// New card selected.
			$arg = sanitize_text_field( $_POST['cko-card-token'] );
		} elseif (
			! isset( $_POST['wc-wc_checkout_com_cards-payment-token'] ) &&
			! empty( $_POST['cko-card-token'] )
		) {
			// New card with stripe enabled.
			$arg = sanitize_text_field( $_POST['cko-card-token'] );
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

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			$card_scheme = sanitize_text_field( $_POST['cko-card-scheme'] );

			if ( ! empty( $card_scheme ) ) {
				WC()->session->set( '_cko_preferred_scheme', $card_scheme );
			}
		}

		// Create payment with card token.
		$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );

		if ( isset( $result['3d_redirection_error'] ) && true === $result['3d_redirection_error'] ) {
			// Retry Create payment with card token.
			$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg, null, true );
		}

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
				WC()->session->set( 'wc-wc_checkout_com_cards-new-payment-method', 'yes' );
			} else {
				WC()->session->set( 'wc-wc_checkout_com_cards-new-payment-method', 'no' );
			}

			$order->add_order_note(
				sprintf(
					esc_html__( 'Checkout.com 3d Redirect waiting. URL : %s', 'checkout-com-unified-payments-api' ),
					$result['3d']
				)
			);

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

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );

			// save Cartes Bancaires card scheme for subscription.
			$card_scheme = WC()->session->get( '_cko_preferred_scheme' );

			if ( ! empty( $card_scheme ) ) {
				$this->save_preferred_card_scheme( $order_id, $order, $card_scheme );
				WC()->session->__unset( '_cko_preferred_scheme' );
			}
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( '_cko_payment_id', $result['id'] );

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
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->update_status( $status );
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

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
		if ( isset( $result['card_verification'] ) && 'error' === $result['card_verification'] ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Unable to add payment method to your account.', 'checkout-com-unified-payments-api' ), 'error' );
			wp_redirect( $result['redirection_url'] );
			exit;
		}

		// Redirect to my-account/payment-method if card verification successful.
		// show notice to customer.
		if ( 'Card Verified' === $result['status'] && isset( $result['metadata']['card_verification'] ) ) {

			$this->save_token( get_current_user_id(), $result );

			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Payment method successfully added.', 'checkout-com-unified-payments-api' ), 'notice' );
			wp_redirect( $result['metadata']['redirection_url'] );
			exit;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action['0']['id'] );

		// if no action id and source is boleto or paypal.
		if ( null == $action['0']['id'] && in_array( $result['source']['type'], [ 'paypal', 'boleto' ], true ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Deliberate loose comparison.
			$order->set_transaction_id( $result['id'] );
		}

		$order->update_meta_data( '_cko_payment_id', $result['id'] );

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
			$order->update_meta_data( 'cko_payment_captured', true );
			$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Captured - Action ID : %s', 'checkout-com-unified-payments-api' ), $action['0']['id'] );
		}

		// save card to db.
		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		if ( $save_card && 'yes' === WC()->session->get( 'wc-wc_checkout_com_cards-new-payment-method' ) ) {
			$this->save_token( $order->get_user_id(), $result );
			WC()->session->__unset( 'wc-wc_checkout_com_cards-new-payment-method' );
		}

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $subscription_object, $result['source']['id'] );

			// save Cartes Bancaires card scheme for subscription.
			$card_scheme = WC()->session->get( '_cko_preferred_scheme' );

			if ( ! empty( $card_scheme ) ) {
				$this->save_preferred_card_scheme( $order_id, $order, $card_scheme );
				WC()->session->__unset( '_cko_preferred_scheme' );
			}
		}

		$order_status = $order->get_status();

		$order->add_order_note( $message );

		if ( 'pending' === $order_status || 'failed' === $order_status ) {
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->update_status( $status );
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

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

			$card_scheme = WC()->session->get( '_cko_preferred_scheme' );

			if ( $card_scheme ) {
				$token->add_meta_data( 'preferred_scheme', $card_scheme, true );
			}

			$token->save();
		}
	}

	/**
	 * This function save card scheme for recurring payment like subscription renewal.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order Order object.
	 * @param string   $card_scheme Card Scheme.
	 *
	 * @return void
	 */
	public function save_preferred_card_scheme( $order_id, $order, $card_scheme ) {

		if ( empty( $card_scheme ) ) {
			return;
		}

		if ( $order instanceof WC_Subscription ) {
			$order->update_meta_data( '_cko_preferred_scheme', $card_scheme );
			$order->save();
		}

		// Check for subscription and save source id.
		if ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );

				foreach ( $subscriptions as $subscription_obj ) {
					$subscription_obj->update_meta_data( '_cko_preferred_scheme', $card_scheme );
					$subscription_obj->save();
				}
			}
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
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( 'cko_payment_refunded', true );
		$order->save();

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
		// webhook_url_format = http://example.com/?wc-api=wc_checkoutcom_webhook .

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

		$order      = false;
		$payment_id = null;

        if ( ! empty( $data->data->metadata->order_id ) ) {
	        $order = wc_get_order( $data->data->metadata->order_id );
        } elseif ( ! empty( $data->data->reference ) ) {
	        $order = wc_get_order( $data->data->reference );

            if ( isset( $data->data->metadata ) ) {
	            $data->data->metadata->order_id = $data->data->reference;
            } else {
	            $data->data->metadata = new StdClass();
	            $data->data->metadata->order_id = $data->data->reference;
            }
        }

        if ( $order ) {
            $payment_id = $order->get_meta( '_cko_payment_id' ) ?? null;
        }

		// check if payment ID matches that of the webhook.
		if ( is_null( $payment_id ) || $payment_id !== $data->data->id ) {

			$gateway_debug = 'yes' === WC_Admin_Settings::get_option( 'cko_gateway_responses', 'no' );
			if ( $gateway_debug ) {
				/* translators: 1: Payment ID, 2: Webhook ID. */
				$message = sprintf( esc_html__( 'Order payment Id (%1$s) does not match that of the webhook (%2$s)', 'checkout-com-unified-payments-api' ), $payment_id, $data->data->id );

				WC_Checkoutcom_Utility::logger( $message, null );
			}

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
			case 'payment_authentication_failed':
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

		$ckocom_language_type = WC_Admin_Settings::get_option( 'ckocom_language_type', '0' );

		if ( '0' === $ckocom_language_type ) {
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
		} else {
			$localization = [
				'cardNumberPlaceholder'  => WC_Admin_Settings::get_option( 'ckocom_card_number_placeholder', 'Card number' ),
				'expiryMonthPlaceholder' => WC_Admin_Settings::get_option( 'ckocom_card_expiry_month_placeholder', 'MM' ),
				'expiryYearPlaceholder'  => WC_Admin_Settings::get_option( 'ckocom_card_expiry_year_placeholder', 'YY' ),
				'cvvPlaceholder'         => WC_Admin_Settings::get_option( 'ckocom_card_cvv_placeholder', 'CVV' ),
				'cardSchemeLink'         => WC_Admin_Settings::get_option( 'ckocom_card_scheme_link_placeholder', 'Click here to update your type of card' ),
				'cardSchemeHeader'       => WC_Admin_Settings::get_option( 'ckocom_card_scheme_header_placeholder', 'Choose your type of card' ),
			];

			$localization = json_encode( $localization );
		}

		return $localization;
	}

}
