<?php

use Checkout\CheckoutApiException;
use Checkout\Workflows\Actions\WebhookSignature;
use Checkout\Workflows\Actions\WebhookWorkflowActionRequest;
use Checkout\Workflows\Conditions\EventWorkflowConditionRequest;
use Checkout\Workflows\CreateWorkflowRequest;

/**
 * Class WC_Checkoutcom_Workflows
 */
class WC_Checkoutcom_Workflows {

	private static $instance = null;

	private $checkout = null;

	private $URL;

	private $SECRET_KEY;

	private $list = [];

	private $url_is_registered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = $core_settings['ckocom_environment'] === 'sandbox';

		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

		$this->SECRET_KEY = $core_settings['ckocom_sk'];
		$this->URL        = $environment ? 'https://api.sandbox.checkout.com/workflows' : 'https://api.checkout.com/workflows';

		$this->checkout = new Checkout_SDK();
	}

	/**
	 * Get singleton instance of class
	 *
	 * @return WC_Checkoutcom_Workflows
	 */
	public static function get_instance(): WC_Checkoutcom_Workflows {
		if ( self::$instance == null ) {
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
	 * @param $args
	 *
	 * @return array|object
	 */
	private function get_request_args( $args = [] ) {

		$defaults = array(
			'headers' => array(
				'Authorization' => $this->SECRET_KEY,
				'Content-Type'  => 'application/json;charset=utf-8',
			),
			'timeout' => 30,
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Register new workflow.
	 *
	 * @param $url
	 *
	 * @return array|WP_Error
	 */
	public function create( $url ) {

		if ( empty( $url ) ) {
			$url = WC_Checkoutcom_Webhook::get_instance()->generate_current_webhook_url();
		}

		$signature = new WebhookSignature();
		$signature->key = $this->SECRET_KEY;
		$signature->method = 'HMACSHA256';

		$actionRequest = new WebhookWorkflowActionRequest();
		$actionRequest->url = $url;
		$actionRequest->signature = $signature;

		$eventWorkflowConditionRequest = new EventWorkflowConditionRequest();
		$eventWorkflowConditionRequest->events = [
			'gateway'     => array(
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
			),
			'dispute'     => array(
				'dispute_canceled',
				'dispute_evidence_required',
				'dispute_expired',
				'dispute_lost',
				'dispute_resolved',
				'dispute_won',
			),
			'mbccards'    => array(
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
			),
			'card_payout' => array(
				'payment_approved',
				'payment_declined',
			),
		];

		$workflowRequest             = new CreateWorkflowRequest();
		$workflowRequest->actions    = array( $actionRequest );
		$workflowRequest->conditions = array( $eventWorkflowConditionRequest );
		$workflowRequest->name       = $url;
		$workflowRequest->active     = true;

		$workflows = [];
		try {
			$workflows = $this->checkout->get_builder()->getWorkflowsClient()->createWorkflow( $workflowRequest );

			if ( ! is_wp_error( $workflows ) && ! empty( $workflows ) ) {

				if ( isset( $workflows['id'] ) ) {
					return $workflows;
				}
			}

		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'wc_checkout_com' );

			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}

		return $workflows;
	}

}
