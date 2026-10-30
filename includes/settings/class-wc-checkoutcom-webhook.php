<?php
/**
 * Webhook class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

use Checkout\CheckoutApiException;

require_once __DIR__ . '/class-wc-checkoutcom-workflows.php';

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
	private $account_type = 'NAS';

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'wp_ajax_wc_checkoutcom_register_webhook', [ $this, 'ajax_register_webhook' ] );
		add_action( 'wp_ajax_wc_checkoutcom_check_webhook', [ $this, 'ajax_check_webhook' ] );

		$this->account_type = 'NAS';

		$this->checkout = new Checkout_SDK();
	}

	/**
	 * Check if hostname is localhost, dev, test, or IP address.
	 *
	 * @param string $host Hostname.
	 *
	 * @return bool
	 */
	private function is_localhost_or_dev( $host ): bool {
		if ( empty( $host ) ) {
			return false;
		}
		
		$host = strtolower( trim( $host ) );
		
		// Check for localhost variations
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return true;
		}
		
		// Check for IP addresses (likely dev/test)
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}
		
		// Check for common dev/test patterns
		$dev_patterns = [
			'localhost',
			'127.0.0.1',
			'.local',
			'.dev',
			'.test',
			'dev.',
			'test.',
			'staging.',
			'local.',
			'ngrok',
			'tunnel',
		];
		
		foreach ( $dev_patterns as $pattern ) {
			if ( strpos( $host, $pattern ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Normalize a webhook URL for comparison.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return string
	 */
	private function normalize_webhook_url( $url ): string {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( false === $parsed ) {
			return $url;
		}

		$host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		$host   = str_replace( 'www.', '', $host );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		$query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}

		$normalized = $host . $path;
		if ( '' !== $query ) {
			$normalized .= '?' . $query;
		}

		return $normalized;
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

		// SECURITY: a nonce proves the request came from our screen, not that the user is allowed
		// to manage the merchant's Checkout.com webhooks. Require the WooCommerce management cap.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'checkout-com-unified-payments-api' ) ], 403 );
		}

		// Prevent any output that might corrupt JSON response
		ob_start();

		$w_id          = false;
		$error_message = '';
		$webhook_url   = $this->generate_current_webhook_url();

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
		
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Starting registration check for URL: ' . $webhook_url );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Account type: ' . $this->account_type );
		}

		$matches = WC_Checkoutcom_Workflows::get_instance()->get_matching_workflows( $webhook_url );

		// Filter matches by entity conditions (for workflows)
		// Workflows with same URL but different entities are NOT duplicates
		$filtered_matches = [];
		$entity_groups = [];
		
		foreach ( $matches as $match ) {
			$match_entity_ids = isset( $match['entity_ids'] ) && is_array( $match['entity_ids'] ) ? $match['entity_ids'] : [];
			$entity_key = ! empty( $match_entity_ids ) ? implode( ',', $match_entity_ids ) : 'NO_ENTITIES';
			
			// Group by entity IDs
			if ( ! isset( $entity_groups[ $entity_key ] ) ) {
				$entity_groups[ $entity_key ] = [];
			}
			$entity_groups[ $entity_key ][] = $match;
		}
		
		// Find groups with multiple workflows (true duplicates)
		$duplicate_groups = [];
		foreach ( $entity_groups as $entity_key => $group_matches ) {
			if ( count( $group_matches ) > 1 ) {
				$duplicate_groups[ $entity_key ] = $group_matches;
			} else {
				// Single match per entity group - not a duplicate
				$filtered_matches = array_merge( $filtered_matches, $group_matches );
			}
		}
		
		$match_count = count( $filtered_matches );
		$duplicate_count = 0;
		foreach ( $duplicate_groups as $group ) {
			$duplicate_count += count( $group );
		}
		
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Total matches: ' . count( $matches ) );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] After entity filtering: ' . $match_count . ' unique match(es)' );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Duplicate groups: ' . $duplicate_count . ' workflow(s) in ' . count( $duplicate_groups ) . ' group(s)' );
			
			if ( ! empty( $matches ) ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] All matched workflows/webhooks:' );
				foreach ( $matches as $index => $match ) {
					$match_id = isset( $match['id'] ) ? $match['id'] : 'NO ID';
					$match_name = isset( $match['name'] ) ? $match['name'] : 'NO NAME';
					$match_url = isset( $match['url'] ) ? $match['url'] : 'NO URL';
					$match_entities = isset( $match['entity_ids'] ) && is_array( $match['entity_ids'] ) && ! empty( $match['entity_ids'] ) 
						? implode( ', ', $match['entity_ids'] ) 
						: 'NONE';
					WC_Checkoutcom_Utility::logger( sprintf( 
						'  [%d] ID: %s, Name: %s, URL: %s, Entities: %s',
						$index + 1,
						$match_id,
						$match_name,
						$match_url,
						$match_entities
					) );
				}
			}
			
			if ( ! empty( $duplicate_groups ) ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Duplicate groups (same URL + same entities):' );
				foreach ( $duplicate_groups as $entity_key => $group ) {
					WC_Checkoutcom_Utility::logger( '  Entity group: ' . ( $entity_key === 'NO_ENTITIES' ? 'NO ENTITIES' : $entity_key ) . ' - ' . count( $group ) . ' workflow(s)' );
					foreach ( $group as $match ) {
						WC_Checkoutcom_Utility::logger( '    - ' . ( $match['id'] ?? 'NO ID' ) . ': ' . ( $match['name'] ?? 'NO NAME' ) );
					}
				}
			}
		}
		
		// Check for duplicates (same URL + same entities)
		if ( $duplicate_count > 0 ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] ❌ DUPLICATES DETECTED - ' . $duplicate_count . ' workflows/webhooks with same URL and entity conditions' );
			}
			ob_clean();
			wp_send_json_error(
				[
					'message' => __( 'Multiple webhooks registered. Please delete duplicates and keep only one.', 'checkout-com-unified-payments-api' ),
				],
				400
			);
		}

		// Check if any workflow already exists for this URL
		// Since Checkout.com auto-assigns entities and we can't predict which entity a new workflow will get,
		// we should prevent registration if ANY workflow with this URL already exists
		if ( $match_count >= 1 ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] ✅ Already registered - ' . $match_count . ' workflow/webhook matches found' );
				if ( $match_count > 1 ) {
					WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Multiple workflows exist with different entities - registration prevented to avoid potential duplicate' );
				}
			}
			ob_clean();
			$message = $match_count === 1 
				? __( 'Webhook already registered for this URL. No action needed.', 'checkout-com-unified-payments-api' )
				: sprintf(
					/* translators: %d: number of duplicate webhooks found. */
					__( 'Multiple webhooks already registered for this URL (%d found). Please delete duplicates before registering a new one.', 'checkout-com-unified-payments-api' ),
					$match_count
				);
			wp_send_json_error(
				[
					'message' => $message,
				],
				400
			);
		}

			$workflow_response = WC_Checkoutcom_Workflows::get_instance()->create( $webhook_url );
			
			// Convert to array for consistent handling
			if ( is_object( $workflow_response ) ) {
				$workflow_response = (array) $workflow_response;
			} elseif ( ! is_array( $workflow_response ) ) {
				$workflow_response = array();
			}

			// Check for explicit error in response
			if ( isset( $workflow_response['error'] ) ) {
				$error_message = $workflow_response['error'];
				WC_Checkoutcom_Utility::logger( 'Workflow registration failed: ' . $error_message, null );
			} elseif ( empty( $workflow_response ) || empty( $workflow_response['id'] ) ) {
				$error_message = __( 'Failed to create workflow. The API returned an invalid response.', 'checkout-com-unified-payments-api' );
				$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
				if ( $gateway_debug ) {
					$error_message .= ' Response keys: ' . implode( ', ', array_keys( (array) $workflow_response ) );
				}
				WC_Checkoutcom_Utility::logger( $error_message, null );
			} else {
				$w_id = $workflow_response['id'];
				WC_Checkoutcom_Utility::logger( 'Workflow registered successfully with ID: ' . $w_id, null );
			}

		// Clean any output
		ob_clean();

		if ( false === $w_id ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] ❌ Registration failed' );
			}
			wp_send_json_error( [
				'message' => $error_message ? $error_message : __( 'Failed to register webhook. Please check logs for details.', 'checkout-com-unified-payments-api' )
			], 400 );
		} else {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] ✅ Registration successful - Webhook/Workflow ID: ' . $w_id );
			}
			wp_send_json_success( [
				'message' => __( 'Webhook registered successfully.', 'checkout-com-unified-payments-api' )
			] );
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

		// SECURITY: require the WooCommerce management capability (nonce alone is not authorization).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'checkout-com-unified-payments-api' ) ], 403 );
		}

		// Prevent any output that might corrupt JSON response
		ob_start();

		$webhook_url = $this->generate_current_webhook_url();
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

		// Check configuration before attempting webhook check
		$core_settings = function_exists( 'cko_get_raw_option' )
			? cko_get_raw_option( 'woocommerce_wc_checkout_com_cards_settings' )
			: get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$secret_key  = $core_settings['ckocom_sk'] ?? '';
		$environment = $core_settings['ckocom_environment'] ?? 'sandbox';

		// Validate configuration
		if ( empty( $core_settings ) ) {
			ob_clean();
			$message = esc_html__( 'Payment gateway settings not found. Please configure the Checkout.com gateway settings first.', 'checkout-com-unified-payments-api' );
			wp_send_json_error( [ 'message' => $message ], 400 );
			return;
		}

		if ( empty( $secret_key ) ) {
			ob_clean();
			$message = esc_html__( 'Secret key is not configured. Please enter your Checkout.com secret key in the gateway settings.', 'checkout-com-unified-payments-api' );
			wp_send_json_error( [ 'message' => $message ], 400 );
			return;
		}

		$matches = WC_Checkoutcom_Workflows::get_instance()->get_matching_workflows( $webhook_url );
		$message = esc_html__( 'Webhook is configured with this name:', 'checkout-com-unified-payments-api' );

		// Filter matches by entity conditions (for workflows)
		// Workflows with same URL but different entities are NOT duplicates
		$filtered_matches = [];
		$entity_groups = [];
		$duplicate_groups = [];
		
		foreach ( $matches as $match ) {
			$match_entity_ids = isset( $match['entity_ids'] ) && is_array( $match['entity_ids'] ) ? $match['entity_ids'] : [];
			$entity_key = ! empty( $match_entity_ids ) ? implode( ',', $match_entity_ids ) : 'NO_ENTITIES';
			
			// Group by entity IDs
			if ( ! isset( $entity_groups[ $entity_key ] ) ) {
				$entity_groups[ $entity_key ] = [];
			}
			$entity_groups[ $entity_key ][] = $match;
		}
		
		// Find groups with multiple workflows (true duplicates)
		foreach ( $entity_groups as $entity_key => $group_matches ) {
			if ( count( $group_matches ) > 1 ) {
				$duplicate_groups[ $entity_key ] = $group_matches;
			} else {
				// Single match per entity group - not a duplicate
				$filtered_matches = array_merge( $filtered_matches, $group_matches );
			}
		}
		
		$match_count = count( $filtered_matches );
		$duplicate_count = 0;
		foreach ( $duplicate_groups as $group ) {
			$duplicate_count += count( $group );
		}
		
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK CHECK] Total matches: ' . count( $matches ) );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK CHECK] After entity filtering: ' . $match_count . ' unique match(es)' );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK CHECK] Duplicate groups: ' . $duplicate_count . ' workflow(s) in ' . count( $duplicate_groups ) . ' group(s)' );
		}
		
		if ( $duplicate_count > 0 ) {
			$message = esc_html__( 'Multiple webhooks registered. Please delete duplicates and keep only one.', 'checkout-com-unified-payments-api' );
		} elseif ( $match_count >= 1 ) {
			// Webhook is configured (1 or more matches with different entities is valid)
				$matched_name = isset( $filtered_matches[0]['name'] ) ? $filtered_matches[0]['name'] : '';
				$matched_url  = isset( $filtered_matches[0]['url'] ) ? $filtered_matches[0]['url'] : $webhook_url;
				if ( $match_count > 1 ) {
					if ( '' !== $matched_name ) {
						$message = sprintf(
							'%s <code>%s</code> (<code>%s</code>) - %d workflow(s) found for different entities',
							$message,
							esc_html( $matched_name ),
							esc_html( $matched_url ),
							$match_count
						);
					} else {
						$message = sprintf( 
							'%s <code>%s</code> (%d workflow(s) found for different entities)',
							$message,
							esc_html( $matched_url ),
							$match_count
						);
					}
				} else {
					if ( '' !== $matched_name ) {
						$message = sprintf(
							'%s <code>%s</code> (<code>%s</code>)',
							$message,
							esc_html( $matched_name ),
							esc_html( $matched_url )
						);
					} else {
						$message = sprintf( '%s <code>%s</code>', $message, esc_html( $matched_url ) );
					}
				}
		} else {
			// Provide more specific error message
			$base_message = esc_html__( 'Webhook is not configured with the current site.', 'checkout-com-unified-payments-api' );
			$suggestions = array();
			
			// Check if we can reach the API
				// NAS account
				$all_workflows = WC_Checkoutcom_Workflows::get_instance()->get_list();
				if ( empty( $all_workflows ) ) {
					$suggestions[] = esc_html__( 'Unable to retrieve workflows from Checkout.com API. Please verify:', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '1. Your secret key is correct', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '2. Your environment (sandbox/live) matches your API key', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '3. Your server can connect to Checkout.com API', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '4. Enable "Gateway Responses" logging to see detailed error messages', 'checkout-com-unified-payments-api' );
				} else {
					/* translators: %d: number of registered workflows. */
					$suggestions[] = sprintf( esc_html__( 'Found %d workflow(s) registered, but none match your site URL:', 'checkout-com-unified-payments-api' ), count( $all_workflows ) );
					$suggestions[] = '<strong>' . esc_html__( 'Your site URL:', 'checkout-com-unified-payments-api' ) . '</strong> <code>' . esc_html( $webhook_url ) . '</code>';
					
					if ( $gateway_debug ) {
						$suggestions[] = '<br><strong>' . esc_html__( 'Registered workflow URLs:', 'checkout-com-unified-payments-api' ) . '</strong>';
						$workflows_instance = WC_Checkoutcom_Workflows::get_instance();
						foreach ( $all_workflows as $index => $workflow ) {
							$workflow_name = isset( $workflow['name'] ) ? $workflow['name'] : 'NO NAME';
							$workflow_id = isset( $workflow['id'] ) ? $workflow['id'] : 'NO ID';
							
							// Check if actions array is missing (list endpoint doesn't include it)
							$has_actions = isset( $workflow['actions'] ) && is_array( $workflow['actions'] ) && ! empty( $workflow['actions'] );
							
							// If actions are missing, fetch individual workflow details
							if ( ! $has_actions && ! empty( $workflow_id ) ) {
								$workflow_details = $workflows_instance->fetch_workflow_details( $workflow_id );
								if ( $workflow_details && isset( $workflow_details['actions'] ) ) {
									$workflow['actions'] = $workflow_details['actions'];
									$has_actions = true;
								}
							}
							
							$action_urls = $workflows_instance->extract_action_urls( $workflow );
							$suggestions[] = sprintf( 
								'  [%d] %s (ID: %s)',
								$index + 1,
								esc_html( $workflow_name ),
								esc_html( $workflow_id )
							);
							if ( ! empty( $action_urls ) ) {
								foreach ( $action_urls as $action_url ) {
									$suggestions[] = '    → <code>' . esc_html( $action_url ) . '</code>';
								}
							} else {
								$suggestions[] = '    → (No action URLs found)';
							}
						}
					} else {
						$suggestions[] = esc_html__( 'Enable "Gateway Responses" logging to see all registered workflow URLs.', 'checkout-com-unified-payments-api' );
					}
					
					$suggestions[] = '<br>' . esc_html__( 'Please register a workflow for your site URL using the "Register Webhook" button, or update an existing workflow to match your site URL.', 'checkout-com-unified-payments-api' );
				}
			
			$message = $base_message . '<br><br>' . implode( '<br>', $suggestions );
			
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK CHECK] Failed - Account type: ' . $this->account_type . ', URL: ' . $webhook_url );
			}
		}

		// Clean any output
		ob_clean();

		// Only send error if there are duplicates (same URL + same entities)
		// Multiple matches with different entities is valid and should be success
		if ( $duplicate_count > 0 ) {
			wp_send_json_error( [ 'message' => $message ], 400 );
		}

		wp_send_json_success( [ 'message' => $message ] );
	}

}

WC_Checkoutcom_Webhook::get_instance();
