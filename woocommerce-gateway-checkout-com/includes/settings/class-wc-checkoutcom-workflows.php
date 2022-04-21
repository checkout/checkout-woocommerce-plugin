<?php

/**
 * Class WC_Checkoutcom_Workflows
 */
class WC_Checkoutcom_Workflows {

	private static $instance = null;

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

			if ( str_contains( $item['name'], $url ) ) {
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

		$response = wp_remote_get( $this->URL, $this->get_request_args() );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WC_Checkoutcom_Utility::logger(
				sprintf(
					'An error has occurred while processing webhook request. Response code: %s',
					wp_remote_retrieve_response_code( $response )
				),
				null
			);

			return $this->list;
		}

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$result = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $result['data'] ) && ! empty( $result['data'] ) ) {
				$this->list = $result['data'];

				return $this->list;
			}
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

		$event_types = array(
			'name'       => $url,
			'conditions' => array(
				array(
					'type'   => 'event',
					'events' => array(
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
					),
				),
			),
			'actions'    => array(
				array(
					'type'      => 'webhook',
					'url'       => $url,
					'signature' => array(
						'method' => 'HMACSHA256',
						'key'    => $this->SECRET_KEY,
					),
				),
			),
		);

		$response = wp_remote_post( $this->URL, $this->get_request_args( array( 'body' => json_encode( $event_types ) ) ) );

		if ( is_wp_error( $response ) || 201 !== wp_remote_retrieve_response_code( $response ) ) {
			WC_Checkoutcom_Utility::logger(
				sprintf(
					'An error has occurred while processing webhook request. Response code: %s',
					wp_remote_retrieve_response_code( $response )
				),
				null
			);
		}

		if ( ! is_wp_error( $response ) && 201 === wp_remote_retrieve_response_code( $response ) ) {
			$result = json_decode( wp_remote_retrieve_body( $response ), true );

			error_log( var_export( $result, true ) );

			if ( isset( $result['id'] ) ) {
				return $response;
			}
		}

		return $response;
	}

}
