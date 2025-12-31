<?php
/**
 * Webhook class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\Webhooks\Previous\WebhookRequest;

require_once 'class-wc-checkoutcom-workflows.php';

/**
 * Class WC_Checkoutcom_Webhook
 */
class WC_Checkoutcom_Webhook {

	/**
	 * Current instance of this class.
	 *
	 * @var $instance Current instance of this class.
	 */
	private static $instance = null;

	/**
	 * Instance of Checkout_SDK class.
	 *
	 * @var $checkout Instance of Checkout_SDK class.
	 */
	private $checkout = null;

	/**
	 * List of all webhooks.
	 *
	 * @var $list List of all webhooks.
	 */
	private $list = [];

	/**
	 * The webhooks URL which is registered to the checkout account's detail entered by user.
	 *
	 * @var $url_is_registered The webhooks URL which is registered to the checkout account's detail entered by user.
	 */
	private $url_is_registered = false;

	/**
	 * Account type.
	 *
	 * @var $account_type Account type.
	 */
	private $account_type = 'ABC';

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'wp_ajax_wc_checkoutcom_register_webhook', [ $this, 'ajax_register_webhook' ] );
		add_action( 'wp_ajax_wc_checkoutcom_check_webhook', [ $this, 'ajax_check_webhook' ] );

		$this->account_type = cko_is_nas_account() ? 'NAS' : 'ABC';

		$this->checkout = new Checkout_SDK();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Checkoutcom_Webhook
	 */
	public static function get_instance(): WC_Checkoutcom_Webhook {
		if ( null === self::$instance ) {
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

		// Prevent any output that might corrupt JSON response
		ob_start();

		$w_id = false;
		$error_message = '';

		if ( 'ABC' === $this->account_type ) {
			$webhook_response = (array) $this->create( $this->generate_current_webhook_url() );

			if ( empty( $webhook_response ) || empty( $webhook_response['id'] ) ) {
				$error_message = __( 'Failed to create webhook. Response: ', 'checkout-com-unified-payments-api' ) . wc_print_r( $webhook_response, true );
				WC_Checkoutcom_Utility::logger( $error_message, null );
			} else {
				$w_id = $webhook_response['id'];
			}

		} else {

			// NAS account type.
			$workflow_response = WC_Checkoutcom_Workflows::get_instance()->create( $this->generate_current_webhook_url() );

			if ( empty( $workflow_response ) || empty( $workflow_response['id'] ) ) {
				$error_message = __( 'Failed to create workflow. Response: ', 'checkout-com-unified-payments-api' ) . wc_print_r( $workflow_response, true );
				WC_Checkoutcom_Utility::logger( $error_message, null );
			} else {
				$w_id = $workflow_response['id'];
			}
		}

		// Clean any output
		ob_clean();

		if ( false === $w_id ) {
			wp_send_json_error( [
				'message' => $error_message ? $error_message : __( 'Failed to register webhook. Please check logs for details.', 'checkout-com-unified-payments-api' )
			], 400 );
		} else {
			wp_send_json_success( [
				'message' => __( 'Webhook registered successfully.', 'checkout-com-unified-payments-api' )
			] );
		}
	}

	/**
	 * Register new webhook.
	 *
	 * @param string $url Webhook URL.
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
			'payment_authentication_failed',
			// 'payment_authorized',
			// 'payment_retry_scheduled',
			// 'payment_returned',
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
			// Check if SDK classes are available
			if ( ! class_exists( 'Checkout\Webhooks\Previous\WebhookRequest' ) ) {
				WC_Checkoutcom_Utility::logger( 'Checkout.com SDK Webhook classes not found - cannot create webhook' );
				return array( 'error' => 'Payment gateway not properly configured. Please contact support.' );
			}
			
			$webhook_request               = new WebhookRequest();
			$webhook_request->url          = $url;
			$webhook_request->content_type = 'json';
			$webhook_request->event_types  = $event_types;
			$webhook_request->active       = true;

			$builder = $this->checkout->get_builder();
			if ( ! $builder ) {
				// Only log this error once per hour in admin context to avoid log spam
				$transient_key = 'cko_webhook_register_sdk_error_logged';
				if ( is_admin() && ! get_transient( $transient_key ) ) {
					WC_Checkoutcom_Utility::logger( 'Checkout.com SDK not initialized - cannot register webhook. Please ensure vendor/autoload.php is loaded and API keys are configured.' );
					set_transient( $transient_key, true, HOUR_IN_SECONDS );
				}
				return array( 'error' => 'Payment gateway not properly configured. Please contact support.' );
			}

			return $builder->getWebhooksClient()->registerWebhook( $webhook_request );

		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'checkout-com-unified-payments-api' );

			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
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
	 * @return string|void
	 */
	public function ajax_check_webhook() {

		check_ajax_referer( 'checkoutcom_check_webhook', 'security' );

		// Prevent any output that might corrupt JSON response
		ob_start();

		if ( 'ABC' === $this->account_type ) {
			$webhook_is_ready = $this->is_registered();

			$message = esc_html__( 'Webhook is configured at this URL:', 'checkout-com-unified-payments-api' );
		} else {
			// NAS account type.
			// @todo: Use SDK to get webhooks or workflows.
			$webhook_is_ready = WC_Checkoutcom_Workflows::get_instance()->is_registered();

			$message = esc_html__( 'Webhook is configured with this name:', 'checkout-com-unified-payments-api' );
		}

		if ( $webhook_is_ready ) {

			$message = $message ? $message : esc_html__( 'Webhook is configured at this URL:', 'checkout-com-unified-payments-api' );
			$message = sprintf( '%s <code>%s</code>', $message, $webhook_is_ready );
		} else {

			$message = esc_html__( 'Webhook is not configured with the current site or there is some issue with connection, Please check logs or try again.', 'checkout-com-unified-payments-api' );
		}

		// Clean any output
		ob_clean();

		wp_send_json_success( [ 'message' => $message ] );
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
			if ( false !== strpos( $item['url'], $url ) ) {
				$this->url_is_registered = $item['url'];

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

		// Use direct API call instead of SDK
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		if ( empty( $core_settings ) ) {
			return array();
		}

		$environment = ( 'sandbox' === ( $core_settings['ckocom_environment'] ?? 'sandbox' ) );
		$secret_key = $core_settings['ckocom_sk'] ?? '';
		
		if ( empty( $secret_key ) ) {
			return array();
		}

		// Build API URL for ABC accounts (webhooks endpoint)
		$base_url = $environment ? 'https://api.sandbox.checkout.com' : 'https://api.checkout.com';
		$api_url = $base_url . '/webhooks';

		// Prepare authorization header
		$secret_key_clean = str_replace( 'Bearer ', '', trim( $secret_key ) );
		// ABC accounts use direct key (no Bearer prefix)
		$auth_header = $secret_key_clean;

		// Make direct API call
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( 'Webhook API request error: ' . $response->get_error_message() . ' (URL: ' . $api_url . ')' );
			}
			return array();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				$body = wp_remote_retrieve_body( $response );
				WC_Checkoutcom_Utility::logger( 'Webhook API request failed with status ' . $response_code . ': ' . $body . ' (URL: ' . $api_url . ')' );
			}
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( 'Webhook API response: ' . print_r( $data, true ) );
		}

		if ( isset( $data['items'] ) && ! empty( $data['items'] ) ) {
			$this->list = $data['items'];
			return $this->list;
		}

		return array();
	}
}

WC_Checkoutcom_Webhook::get_instance();
