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
	 * Find matching webhooks by URL.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return array
	 */
	public function get_matching_webhooks( $url ): array {
		$matches    = [];
		$webhooks   = $this->get_list();
		$target_url = $this->normalize_webhook_url( $url );
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Original target URL: ' . $url );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Normalized target URL: ' . $target_url );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Total webhooks returned: ' . count( $webhooks ) );
		}

		if ( empty( $target_url ) || empty( $webhooks ) ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Early return - target_url empty: ' . ( empty( $target_url ) ? 'YES' : 'NO' ) . ', webhooks empty: ' . ( empty( $webhooks ) ? 'YES' : 'NO' ) );
			}
			return [];
		}

		foreach ( $webhooks as $index => $item ) {
			$original_item_url = isset( $item['url'] ) ? $item['url'] : '';
			$item_url = ! empty( $original_item_url ) ? $this->normalize_webhook_url( $original_item_url ) : '';
			
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( sprintf( 
					'[WEBHOOK MATCH] Webhook #%d - Original: %s, Normalized: %s, Match: %s',
					$index + 1,
					$original_item_url,
					$item_url,
					( '' !== $item_url && $item_url === $target_url ) ? 'YES' : 'NO'
				) );
			}
			
			// Exact match
			if ( '' !== $item_url && $item_url === $target_url ) {
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] ✅ EXACT MATCH FOUND - Adding webhook to matches' );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Webhook ID: ' . ( isset( $item['id'] ) ? $item['id'] : 'N/A' ) );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Original URL: ' . $original_item_url );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Normalized URL: ' . $item_url );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Normalized Target: ' . $target_url );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
				}
				$matches[] = $item;
				continue;
			}
			
			// Try flexible matching: compare without query parameters
			$target_without_query = strtok( $target_url, '?' );
			$item_without_query = strtok( $item_url, '?' );
			if ( '' !== $item_without_query && $item_without_query === $target_without_query ) {
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] ✅ MATCH FOUND (without query params) - Adding webhook to matches' );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Webhook ID: ' . ( isset( $item['id'] ) ? $item['id'] : 'N/A' ) );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Original URL: ' . $original_item_url );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Target without query: ' . $target_without_query );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Item without query: ' . $item_without_query );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
				}
				$matches[] = $item;
				continue;
			}
			
			// Try matching just the path and query (ONLY for localhost/dev/test environments)
			// This prevents false matches between different production domains
			$target_parsed = wp_parse_url( $url );
			$item_parsed = wp_parse_url( $original_item_url );
			if ( $target_parsed && $item_parsed ) {
				$target_host = isset( $target_parsed['host'] ) ? strtolower( $target_parsed['host'] ) : '';
				$item_host = isset( $item_parsed['host'] ) ? strtolower( $item_parsed['host'] ) : '';
				
				// Only use path+query matching for localhost/dev/test environments
				$is_localhost_target = $this->is_localhost_or_dev( $target_host );
				$is_localhost_item = $this->is_localhost_or_dev( $item_host );
				
				// Only match if BOTH are localhost/dev OR if hosts match
				if ( ( $is_localhost_target && $is_localhost_item ) || $target_host === $item_host ) {
					$target_path_query = ( $target_parsed['path'] ?? '/' ) . ( isset( $target_parsed['query'] ) ? '?' . $target_parsed['query'] : '' );
					$item_path_query = ( $item_parsed['path'] ?? '/' ) . ( isset( $item_parsed['query'] ) ? '?' . $item_parsed['query'] : '' );
					
					// Normalize paths
					$target_path_query = '/' . ltrim( $target_path_query, '/' );
					$item_path_query = '/' . ltrim( $item_path_query, '/' );
					
					if ( $target_path_query === $item_path_query ) {
						if ( $gateway_debug ) {
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] ✅ MATCH FOUND (path+query only) - Adding webhook to matches' );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Webhook ID: ' . ( isset( $item['id'] ) ? $item['id'] : 'N/A' ) );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Original URL: ' . $original_item_url );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Target host: ' . $target_host . ' (localhost/dev: ' . ( $is_localhost_target ? 'YES' : 'NO' ) . ')' );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Item host: ' . $item_host . ' (localhost/dev: ' . ( $is_localhost_item ? 'YES' : 'NO' ) . ')' );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Target path+query: ' . $target_path_query );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Item path+query: ' . $item_path_query );
							WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
						}
						$matches[] = $item;
					}
				} elseif ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Skipping path+query match - different production domains' );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Target host: ' . $target_host . ' (localhost/dev: ' . ( $is_localhost_target ? 'YES' : 'NO' ) . ')' );
					WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH]   Item host: ' . $item_host . ' (localhost/dev: ' . ( $is_localhost_item ? 'YES' : 'NO' ) . ')' );
				}
			}
		}

		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] ========================================' );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] FINAL RESULT: ' . count( $matches ) . ' match(es) found' );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Target URL: ' . $url );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Normalized Target URL: ' . $target_url );
			
			if ( ! empty( $matches ) ) {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] Matched webhooks:' );
				foreach ( $matches as $index => $match ) {
					WC_Checkoutcom_Utility::logger( sprintf( 
						'  [%d] ID: %s, URL: %s',
						$index + 1,
						isset( $match['id'] ) ? $match['id'] : 'NO ID',
						isset( $match['url'] ) ? $match['url'] : 'NO URL'
					) );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] No matches found. Registered webhook URLs:' );
				foreach ( $webhooks as $index => $item ) {
					$item_url_check = isset( $item['url'] ) ? $item['url'] : 'NO URL';
					$normalized_item_check = ! empty( $item_url_check ) && $item_url_check !== 'NO URL' ? $this->normalize_webhook_url( $item_url_check ) : 'N/A';
					WC_Checkoutcom_Utility::logger( sprintf( 
						'  [%d] Original: %s',
						$index + 1,
						$item_url_check
					) );
					if ( $normalized_item_check !== 'N/A' ) {
						WC_Checkoutcom_Utility::logger( '      Normalized: ' . $normalized_item_check );
						WC_Checkoutcom_Utility::logger( '      Match: ' . ( $normalized_item_check === $target_url ? 'YES' : 'NO' ) );
					}
				}
			}
			WC_Checkoutcom_Utility::logger( '[WEBHOOK MATCH] ========================================' );
		}

		return $matches;
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

		$w_id          = false;
		$error_message = '';
		$webhook_url   = $this->generate_current_webhook_url();

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
		
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Starting registration check for URL: ' . $webhook_url );
			WC_Checkoutcom_Utility::logger( '[WEBHOOK REGISTER] Account type: ' . $this->account_type );
		}

		if ( 'ABC' === $this->account_type ) {
			$matches = $this->get_matching_webhooks( $webhook_url );
		} else {
			$matches = WC_Checkoutcom_Workflows::get_instance()->get_matching_workflows( $webhook_url );
		}

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

		if ( 'ABC' === $this->account_type ) {
			$webhook_response = $this->create( $webhook_url );
			
			// Convert to array for consistent handling
			if ( is_object( $webhook_response ) ) {
				$webhook_response = (array) $webhook_response;
			} elseif ( ! is_array( $webhook_response ) ) {
				$webhook_response = array();
			}

			// Check for explicit error in response
			if ( isset( $webhook_response['error'] ) ) {
				$error_message = $webhook_response['error'];
				if ( isset( $webhook_response['exception_message'] ) ) {
					$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
					if ( $gateway_debug ) {
						$error_message .= ' Details: ' . $webhook_response['exception_message'];
					}
				}
				WC_Checkoutcom_Utility::logger( 'Webhook registration failed: ' . $error_message, null );
			} elseif ( empty( $webhook_response ) || empty( $webhook_response['id'] ) ) {
				$error_message = __( 'Failed to create webhook. The API returned an invalid response.', 'checkout-com-unified-payments-api' );
				$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
				if ( $gateway_debug ) {
					$error_message .= ' Response: ' . wc_print_r( $webhook_response, true );
				}
				WC_Checkoutcom_Utility::logger( $error_message, null );
			} else {
				$w_id = $webhook_response['id'];
				WC_Checkoutcom_Utility::logger( 'Webhook registered successfully with ID: ' . $w_id, null );
			}

		} else {

			// NAS account type.
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
					$error_message .= ' Response: ' . wc_print_r( $workflow_response, true );
				}
				WC_Checkoutcom_Utility::logger( $error_message, null );
			} else {
				$w_id = $workflow_response['id'];
				WC_Checkoutcom_Utility::logger( 'Workflow registered successfully with ID: ' . $w_id, null );
			}
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

			$result = $builder->getWebhooksClient()->registerWebhook( $webhook_request );
			
			// Validate the response
			if ( empty( $result ) ) {
				WC_Checkoutcom_Utility::logger( 'Webhook registration returned empty response' );
				return array( 'error' => 'Webhook registration failed: Empty response from API.' );
			}
			
			// Convert to array if it's an object
			if ( is_object( $result ) ) {
				$result = (array) $result;
			}
			
			// Check if response indicates an error
			if ( isset( $result['error'] ) || isset( $result['error_type'] ) ) {
				$error_msg = isset( $result['error'] ) ? $result['error'] : ( isset( $result['error_type'] ) ? $result['error_type'] : 'Unknown error' );
				WC_Checkoutcom_Utility::logger( 'Webhook registration failed: ' . $error_msg );
				return array( 'error' => 'Webhook registration failed: ' . $error_msg );
			}
			
			return $result;

		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while processing webhook request.', 'checkout-com-unified-payments-api' );

			if ( $gateway_debug ) {
				$error_message .= ' ' . $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
			
			// CRITICAL FIX: Return error array so calling code can detect failure
			return array( 
				'error' => $error_message,
				'exception_message' => $ex->getMessage(),
				'exception_code' => $ex->getCode()
			);
		} catch ( \Exception $ex ) {
			// Catch any other exceptions
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			$error_message = esc_html__( 'An unexpected error occurred while processing webhook request.', 'checkout-com-unified-payments-api' );
			
			if ( $gateway_debug ) {
				$error_message .= ' ' . $ex->getMessage();
			}
			
			WC_Checkoutcom_Utility::logger( $error_message, $ex );
			
			return array( 
				'error' => $error_message,
				'exception_message' => $ex->getMessage(),
				'exception_code' => $ex->getCode()
			);
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

		$webhook_url = $this->generate_current_webhook_url();
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

		// Check configuration before attempting webhook check
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$secret_key = $core_settings['ckocom_sk'] ?? '';
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

		if ( 'ABC' === $this->account_type ) {
			$matches = $this->get_matching_webhooks( $webhook_url );
			$message = esc_html__( 'Webhook is configured at this URL:', 'checkout-com-unified-payments-api' );
			
			// Check if API call failed by checking if get_list() returned empty
			$all_webhooks = $this->get_list();
			if ( empty( $all_webhooks ) && empty( $matches ) ) {
				// Try to get more details about why get_list() failed
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WEBHOOK CHECK] No webhooks found. Checking API connectivity...' );
				}
			}
		} else {
			// NAS account type.
			$matches = WC_Checkoutcom_Workflows::get_instance()->get_matching_workflows( $webhook_url );
			$message = esc_html__( 'Webhook is configured with this name:', 'checkout-com-unified-payments-api' );
		}

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
			if ( 'ABC' === $this->account_type ) {
				$matched_url = isset( $filtered_matches[0]['url'] ) ? $filtered_matches[0]['url'] : $webhook_url;
				if ( $match_count > 1 ) {
					$message = sprintf( 
						'%s <code>%s</code> (%d webhook(s) found for different entities)',
						$message,
						esc_html( $matched_url ),
						$match_count
					);
				} else {
					$message = sprintf( '%s <code>%s</code>', $message, esc_html( $matched_url ) );
				}
			} else {
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
			}
		} else {
			// Provide more specific error message
			$base_message = esc_html__( 'Webhook is not configured with the current site.', 'checkout-com-unified-payments-api' );
			$suggestions = array();
			
			// Check if we can reach the API
			if ( 'ABC' === $this->account_type ) {
				$all_webhooks = $this->get_list();
				if ( empty( $all_webhooks ) ) {
					$suggestions[] = esc_html__( 'Unable to retrieve webhooks from Checkout.com API. Please verify:', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '1. Your secret key is correct', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '2. Your environment (sandbox/live) matches your API key', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '3. Your server can connect to Checkout.com API', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '4. Enable "Gateway Responses" logging to see detailed error messages', 'checkout-com-unified-payments-api' );
				} else {
					$suggestions[] = sprintf( esc_html__( 'Found %d webhook(s) registered, but none match your site URL:', 'checkout-com-unified-payments-api' ), count( $all_webhooks ) );
					$suggestions[] = '<strong>' . esc_html__( 'Your site URL:', 'checkout-com-unified-payments-api' ) . '</strong> <code>' . esc_html( $webhook_url ) . '</code>';
					
					if ( $gateway_debug ) {
						$suggestions[] = '<br><strong>' . esc_html__( 'Registered webhook URLs:', 'checkout-com-unified-payments-api' ) . '</strong>';
						foreach ( $all_webhooks as $index => $hook ) {
							$hook_url = isset( $hook['url'] ) ? $hook['url'] : 'NO URL';
							$hook_id = isset( $hook['id'] ) ? $hook['id'] : 'NO ID';
							$suggestions[] = sprintf( 
								'  [%d] <code>%s</code> (ID: %s)',
								$index + 1,
								esc_html( $hook_url ),
								esc_html( $hook_id )
							);
						}
					} else {
						$suggestions[] = esc_html__( 'Enable "Gateway Responses" logging to see all registered webhook URLs.', 'checkout-com-unified-payments-api' );
					}
					
					$suggestions[] = '<br>' . esc_html__( 'Please register a webhook for your site URL using the "Register Webhook" button, or update an existing webhook to match your site URL.', 'checkout-com-unified-payments-api' );
				}
			} else {
				// NAS account
				$all_workflows = WC_Checkoutcom_Workflows::get_instance()->get_list();
				if ( empty( $all_workflows ) ) {
					$suggestions[] = esc_html__( 'Unable to retrieve workflows from Checkout.com API. Please verify:', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '1. Your secret key is correct', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '2. Your environment (sandbox/live) matches your API key', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '3. Your server can connect to Checkout.com API', 'checkout-com-unified-payments-api' );
					$suggestions[] = esc_html__( '4. Enable "Gateway Responses" logging to see detailed error messages', 'checkout-com-unified-payments-api' );
				} else {
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

	/**
	 * Check if webhook is registered.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return string|null
	 */
	public function is_registered( $url = '' ): string {
		$webhooks = $this->get_matching_webhooks( $url ? $url : $this->generate_current_webhook_url() );

		if ( 1 === count( $webhooks ) && isset( $webhooks[0]['url'] ) ) {
			$this->url_is_registered = $webhooks[0]['url'];
			return $this->url_is_registered;
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

		// Check for JSON decode errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( 'Webhook API JSON decode error: ' . json_last_error_msg() );
			}
			return array();
		}

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( 'Webhook API response: ' . wc_print_r( $data, true ) );
		}

		if ( isset( $data['items'] ) && ! empty( $data['items'] ) ) {
			$this->list = $data['items'];
			return $this->list;
		}

		return array();
	}
}

WC_Checkoutcom_Webhook::get_instance();
