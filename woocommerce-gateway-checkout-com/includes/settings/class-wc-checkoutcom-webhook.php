<?php

include_once 'class-wc-checkoutcom-workflows.php';

use Checkout\CheckoutApi;
use Checkout\Models\Webhooks\Webhook;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;

/**
 * Class WC_Checkoutcom_Webhook
 */
class WC_Checkoutcom_Webhook {

	private static $instance = null;

	private $checkout = null;

	private $list = [];

	private $url_is_registered = false;

	private $ACCOUNT_TYPE = 'ABC';

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'wp_ajax_wc_checkoutcom_register_webhook', array( $this, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_wc_checkoutcom_check_webhook', array( $this, 'ajax_check_webhook' ) );

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = $core_settings['ckocom_environment'] === 'sandbox';

		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

		$this->ACCOUNT_TYPE = isset( $core_settings['ckocom_account_type'] ) ? $core_settings['ckocom_account_type'] : 'ABC';

		$this->checkout = new CheckoutApi( $core_settings['ckocom_sk'], $environment );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Checkoutcom_Webhook
	 */
	public static function get_instance(): WC_Checkoutcom_Webhook {
		if ( self::$instance == null ) {
			self::$instance = new WC_Checkoutcom_Webhook();
		}

		return self::$instance;
	}

	/**
	 * AJAX request handler for registering webhook.
	 *
	 * @return void
	 */
	public function ajax_register_webhook() {

		check_ajax_referer( 'checkoutcom_register_webhook', 'security' );

		$http_code = false;

		if ( 'ABC' === $this->ACCOUNT_TYPE ) {
			$webhook_response = (array) $this->create( $this->generate_current_webhook_url() );

			if ( isset( $webhook_response['error_codes'] ) && ! empty( $webhook_response['error_codes'] ) ) {
				WC_Checkoutcom_Utility::wc_add_notice_self( $webhook_response['error_codes'], 'error' );

				return;
			}

			$http_code = $webhook_response['http_code'];

		} else {

			// NAS account type.
			// @todo: Use SDK to add webhooks or workflows.
			$webhook_response = (array) WC_Checkoutcom_Workflows::get_instance()->create( $this->generate_current_webhook_url() );

			if ( isset( $webhook_response['response']['code'] ) && ! empty( $webhook_response['response']['code'] ) ) {
				WC_Checkoutcom_Utility::wc_add_notice_self( $webhook_response['response']['code'], 'error' );

				return;
			}

			$http_code = $webhook_response['response']['code'];
		}

		if ( 201 === $http_code ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( null, 400 );
		}

		wp_die();
	}

	/**
	 * Register new webhook.
	 *
	 * @param $url
	 *
	 * @return mixed|WP_Error
	 */
	public function create( $url ) {

		if ( empty( $url ) ) {
			$url = $this->generate_current_webhook_url();
		}

		$event_types = [
			'card_verification_declined',
			'card_verified',
			'dispute_canceled',
			'dispute_evidence_required',
			'dispute_expired',
			'dispute_lost',
			'dispute_resolved',
			'dispute_won',
			'payment_approved',
			'payment_canceled',
			'payment_capture_declined',
			'payment_capture_pending',
			'payment_captured',
			'payment_chargeback',
			'payment_declined',
			'payment_expired',
			'payment_paid',
			'payment_pending',
			'payment_refund_declined',
			'payment_refund_pending',
			'payment_refunded',
			'payment_retrieval',
			'payment_void_declined',
			'payment_voided',
			'source_updated',
		];

		try {
			$webhook = new Webhook( $url );

			return $this->checkout->webhooks()->register( $webhook, $event_types );

		} catch ( CheckoutModelException $ex ) {

			$error_message = esc_html__( 'An error has occurred while processing your payment.', 'wc_checkout_com' );

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		} catch ( CheckoutHttpException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'wc_checkout_com' );

			if ( $gateway_debug ) {
				$error_message .= esc_html__( $ex->getMessage(), 'wc_checkout_com' );
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}
	}

	/**
	 * Get current webhook url.
	 *
	 * @return string
	 */
	public static function generate_current_webhook_url(): string {
		return add_query_arg( 'wc-api', 'wc_checkoutcom_webhook', home_url( '/' ) );
	}

	/**
	 * AJAX request handler for checking webhook.
	 *
	 * @return string
	 */
	public function ajax_check_webhook() {

		check_ajax_referer( 'checkoutcom_check_webhook', 'security' );


		if ( 'ABC' === $this->ACCOUNT_TYPE ) {
			$webhook_is_ready = $this->is_registered();

			$message = esc_html__( 'Webhook is configured at this URL:', 'wc_checkout_com' );
		} else {
			// NAS account type.
			// @todo: Use SDK to get webhooks or workflows.
			$webhook_is_ready = WC_Checkoutcom_Workflows::get_instance()->is_registered();

			$message = esc_html__( 'Webhook is configured with this name:', 'wc_checkout_com' );
		}

		if ( $webhook_is_ready ) {

			$message = $message ? $message : esc_html__( 'Webhook is configured at this URL:', 'wc_checkout_com' );
			$message = sprintf( '%s <code>%s</code>', $message, $webhook_is_ready );
		} else {

			$message = esc_html__( 'Webhook is not configured with the current site or there is some issue with connection, Please check logs or try again.', 'wc_checkout_com' );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Check if webhook is registered.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return string|null
	 */
	public function is_registered( $url = '' ): string {
		$webhooks = $this->get_list();

		if ( empty( $url ) ) {
			$url = home_url( '/' );
		}

		foreach ( $webhooks as $item ) {
			if ( str_contains( $item->url, $url ) ) {
				$this->url_is_registered = $item->url;

				return $this->url_is_registered;
			}
		}

		return $this->url_is_registered;
	}

	/**
	 * Get list of all webhooks.
	 *
	 * @return array|mixed
	 */
	public function get_list(): array {

		if ( $this->list ) {
			return $this->list;
		}

		try {
			$webhooks = $this->checkout->webhooks()->retrieve();

			if ( isset( $webhooks->list ) && ! empty( $webhooks->list ) ) {
				$this->list = $webhooks->list;

				return $this->list;
			}

		} catch ( CheckoutHttpException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'wc_checkout_com' );

			if ( $gateway_debug ) {
				$error_message .= __( $ex->getMessage(), 'wc_checkout_com' );
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}

		return $this->list;
	}
}

WC_Checkoutcom_Webhook::get_instance();
