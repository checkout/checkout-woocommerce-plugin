<?php
/**
 * Workflow class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\Workflows\Actions\WebhookSignature;
use Checkout\Workflows\Actions\WebhookWorkflowActionRequest;
use Checkout\Workflows\Conditions\EventWorkflowConditionRequest;
use Checkout\Workflows\CreateWorkflowRequest;

/**
 * Class WC_Checkoutcom_Workflows
 */
class WC_Checkoutcom_Workflows {

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
	 * Checkout's workflow URL.
	 *
	 * This will be different based on the value of ckocom_environment settings.
	 *
	 * @var $url Checkout's workflow URL.
	 */
	private $url;

	/**
	 * Checkout account's secret key set in the core settings section.
	 *
	 * @var $secret_key Checkout account's secret key set in the core settings section.
	 */
	private $secret_key;

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
	 * Constructor.
	 */
	public function __construct() {

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = ( 'sandbox' === $core_settings['ckocom_environment'] );

		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

		$this->secret_key = $core_settings['ckocom_sk'];
		$this->url        = $environment ? 'https://api.sandbox.checkout.com/workflows' : 'https://api.checkout.com/workflows';

		$this->checkout = new Checkout_SDK();
	}

	/**
	 * Get singleton instance of class
	 *
	 * @return WC_Checkoutcom_Workflows
	 */
	public static function get_instance(): WC_Checkoutcom_Workflows {
		if ( null === self::$instance ) {
			self::$instance = new WC_Checkoutcom_Workflows();
		}

		return self::$instance;
	}

	/**
	 * Check if webhook is registered.
	 *
	 * @param string $url Workflow URL.
	 *
	 * @return string|null
	 */
	public function is_registered( $url = '' ): string {
		$webhooks = $this->get_list();

		if ( empty( $url ) ) {
			$url = home_url( '/' );
		}

		foreach ( $webhooks as $item ) {

			if ( false !== strpos( $item['name'], $url ) ) {
				$this->url_is_registered = $item['name'];

				return $this->url_is_registered;
			}
		}

		return $this->url_is_registered;
	}

	/**
	 * Get list of all workflow.
	 *
	 * @return array|mixed
	 */
	public function get_list() {

		if ( $this->list ) {
			return $this->list;
		}

		try {

			$workflows = $this->checkout->get_builder()->getWorkflowsClient()->getWorkflows();

			if ( ! is_wp_error( $workflows ) && ! empty( $workflows ) ) {

				if ( isset( $workflows['data'] ) && ! empty( $workflows['data'] ) ) {
					$this->list = $workflows['data'];

					return $this->list;
				}
			}
		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = 'An error has occurred while processing workflow request. ';

			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}

		return $this->list;
	}

	/**
	 * Get request args.
	 *
	 * @param array $args Request Arguments.
	 *
	 * @return array|object
	 */
	private function get_request_args( $args = [] ) {

		$defaults = [
			'headers' => [
				'Authorization' => $this->secret_key,
				'Content-Type'  => 'application/json;charset=utf-8',
			],
			'timeout' => 30,
		];

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Register new workflow.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return array|WP_Error
	 */
	public function create( $url ) {

		if ( empty( $url ) ) {
			$url = WC_Checkoutcom_Webhook::get_instance()->generate_current_webhook_url();
		}

		$signature         = new WebhookSignature();
		$signature->key    = $this->secret_key;
		$signature->method = 'HMACSHA256';

		$action_request            = new WebhookWorkflowActionRequest();
		$action_request->url       = $url;
		$action_request->signature = $signature;

		$event_workflow_condition_request         = new EventWorkflowConditionRequest();
		$event_workflow_condition_request->events = [
			'gateway'     => [
				'card_verification_declined',
				'card_verified',
				'payment_approved',
				'payment_canceled',
				'payment_capture_declined',
				'payment_capture_pending',
				'payment_captured',
				'payment_declined',
				'payment_expired',
				'payment_paid',
				'payment_pending',
				'payment_refund_declined',
				'payment_refund_pending',
				'payment_refunded',
				'payment_void_declined',
				'payment_voided',
				'payment_authentication_failed',
//				'payment_authorized',
//				'payment_retry_scheduled',
//				'payment_returned',
			],
			'dispute'     => [
				'dispute_canceled',
				'dispute_evidence_required',
				'dispute_expired',
				'dispute_lost',
				'dispute_resolved',
				'dispute_won',
			],
			'mbccards'    => [
				'card_verification_declined',
				'card_verified',
				'payment_approved',
				'payment_capture_declined',
				'payment_captured',
				'payment_declined',
				'payment_refund_declined',
				'payment_refunded',
				'payment_void_declined',
				'payment_voided',
			],
			'card_payout' => [
				'payment_approved',
				'payment_declined',
			],
		];

		$workflow_request             = new CreateWorkflowRequest();
		$workflow_request->actions    = [ $action_request ];
		$workflow_request->conditions = [ $event_workflow_condition_request ];
		$workflow_request->name       = $url;
		$workflow_request->active     = true;

		$workflows = [];
		try {
			$workflows = $this->checkout->get_builder()->getWorkflowsClient()->createWorkflow( $workflow_request );

			if ( ! is_wp_error( $workflows ) && ! empty( $workflows ) ) {

				if ( isset( $workflows['id'] ) ) {
					return $workflows;
				}
			}
		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'checkout-com-unified-payments-api' );

			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}

		return $workflows;
	}

}
