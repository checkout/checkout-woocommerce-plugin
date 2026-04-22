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
	 * Cache for individual workflow details.
	 *
	 * @var array Cache for workflow details.
	 */
	private $workflow_details_cache = [];

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

		$core_settings   = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$environment     = ( 'sandbox' === ( $core_settings['ckocom_environment'] ?? 'sandbox' ) );
		$region          = isset( $core_settings['ckocom_region'] ) ? sanitize_text_field( $core_settings['ckocom_region'] ) : '';
		
		// Valid region values: 'global', 'ksa', or empty string
		// Filter out invalid values like '--' or other placeholders
		$valid_regions = array( 'global', 'ksa' );
		if ( ! empty( $region ) && ! in_array( $region, $valid_regions, true ) ) {
			// Invalid region value, default to global (no subdomain)
			$region = '';
		}
		
		$subdomain_check = ! empty( $region ) && 'global' !== $region;

		$secret_key_raw = $core_settings['ckocom_sk'] ?? '';
		$this->secret_key = cko_is_nas_account() ? 'Bearer ' . $secret_key_raw : $secret_key_raw;

		if ( $subdomain_check && ! empty( $region ) ) {
			$this->url = $environment ? 'https://' . $region . '.api.sandbox.checkout.com/workflows' : 'https://' . $region . '.api.checkout.com/workflows';
		} else {
			$this->url = $environment ? 'https://api.sandbox.checkout.com/workflows' : 'https://api.checkout.com/workflows';
		}

		$this->checkout = new Checkout_SDK();
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
	 * Generate a short workflow name for the webhook.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return string
	 */
	private function generate_workflow_name( $url ): string {
		$parsed = wp_parse_url( $url );
		$host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		$host   = str_replace( 'www.', '', $host );

		return $host ? sprintf( 'WC-CKO %s', $host ) : 'WC-CKO';
	}

	/**
	 * Fetch individual workflow details including actions.
	 *
	 * @param string $workflow_id Workflow ID.
	 *
	 * @return array|false Workflow details with actions, or false on failure.
	 */
	public function fetch_workflow_details( $workflow_id ) {
		if ( empty( $workflow_id ) || empty( $this->secret_key ) ) {
			return false;
		}

		// Check cache first
		if ( isset( $this->workflow_details_cache[ $workflow_id ] ) ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] Using cached details for workflow ID: ' . $workflow_id );
			}
			return $this->workflow_details_cache[ $workflow_id ];
		}

		// Build individual workflow endpoint URL
		$workflow_url = rtrim( $this->url, '/' ) . '/' . $workflow_id;
		
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
		
		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] Fetching details for workflow ID: ' . $workflow_id );
		}

		// Make API call to get individual workflow details
		$response = wp_remote_get(
			$workflow_url,
			array(
				'headers' => array(
					'Authorization' => $this->secret_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] Error fetching workflow ' . $workflow_id . ': ' . $response->get_error_message() );
			}
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			if ( $gateway_debug ) {
				$body = wp_remote_retrieve_body( $response );
				WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] Failed to fetch workflow ' . $workflow_id . ' - Status: ' . $response_code . ', Body: ' . $body );
			}
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$workflow_data = json_decode( $body, true );

		// Check for JSON decode errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] JSON decode error for workflow ' . $workflow_id . ': ' . json_last_error_msg() );
			}
			return false;
		}

		if ( isset( $workflow_data['id'] ) ) {
			// Cache the result
			$this->workflow_details_cache[ $workflow_id ] = $workflow_data;
			
			if ( $gateway_debug ) {
				$has_actions = isset( $workflow_data['actions'] ) && is_array( $workflow_data['actions'] );
				WC_Checkoutcom_Utility::logger( '[WORKFLOW FETCH] Successfully fetched workflow ' . $workflow_id . ' - Has actions: ' . ( $has_actions ? 'YES (' . count( $workflow_data['actions'] ) . ')' : 'NO' ) );
			}
			return $workflow_data;
		}

		return false;
	}

	/**
	 * Extract entity IDs from workflow conditions.
	 *
	 * @param array $item Workflow item.
	 *
	 * @return array Array of entity IDs.
	 */
	private function extract_entity_ids( array $item ): array {
		$entity_ids = [];
		$conditions = isset( $item['conditions'] ) && is_array( $item['conditions'] ) ? $item['conditions'] : [];

		foreach ( $conditions as $condition ) {
			if ( isset( $condition['type'] ) && 'entity' === $condition['type'] ) {
				if ( isset( $condition['entities'] ) && is_array( $condition['entities'] ) ) {
					$entity_ids = array_merge( $entity_ids, $condition['entities'] );
				}
			}
		}

		// Sort and remove duplicates
		$entity_ids = array_unique( $entity_ids );
		sort( $entity_ids );

		return $entity_ids;
	}

	/**
	 * Extract action URLs from a workflow item.
	 *
	 * @param array $item Workflow item.
	 *
	 * @return array
	 */
	public function extract_action_urls( array $item ): array {
		$urls    = [];
		$actions = isset( $item['actions'] ) && is_array( $item['actions'] ) ? $item['actions'] : [];

		foreach ( $actions as $action ) {
			if ( isset( $action['url'] ) ) {
				$urls[] = $action['url'];
				continue;
			}
			if ( isset( $action['configuration']['url'] ) ) {
				$urls[] = $action['configuration']['url'];
				continue;
			}
			if ( isset( $action['configuration']['destination']['url'] ) ) {
				$urls[] = $action['configuration']['destination']['url'];
			}
		}

		return $urls;
	}

	/**
	 * Find matching workflows by webhook URL.
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return array
	 */
	public function get_matching_workflows( $url ): array {
		$matches    = [];
		$workflows  = $this->get_list();
		$target_url = $this->normalize_webhook_url( $url );
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Original target URL: ' . $url );
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Normalized target URL: ' . $target_url );
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Total workflows returned: ' . count( $workflows ) );
		}

		if ( empty( $target_url ) || empty( $workflows ) ) {
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Early return - target_url empty: ' . ( empty( $target_url ) ? 'YES' : 'NO' ) . ', workflows empty: ' . ( empty( $workflows ) ? 'YES' : 'NO' ) );
			}
			return [];
		}

		foreach ( $workflows as $workflow_index => $item ) {
			// Check if actions array is missing (list endpoint doesn't include it)
			$has_actions = isset( $item['actions'] ) && is_array( $item['actions'] ) && ! empty( $item['actions'] );
			
			// If actions are missing, fetch individual workflow details
			if ( ! $has_actions && ! empty( $item['id'] ) ) {
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Actions missing for workflow ' . $item['id'] . ', fetching details...' );
				}
				$workflow_details = $this->fetch_workflow_details( $item['id'] );
				if ( $workflow_details && isset( $workflow_details['actions'] ) ) {
					// Merge the fetched actions into the item
					$item['actions'] = $workflow_details['actions'];
					$has_actions = true;
					
					// Also merge conditions if available (needed for entity extraction)
					if ( isset( $workflow_details['conditions'] ) && is_array( $workflow_details['conditions'] ) ) {
						$item['conditions'] = $workflow_details['conditions'];
						if ( $gateway_debug ) {
							$entity_count = 0;
							foreach ( $workflow_details['conditions'] as $cond ) {
								if ( isset( $cond['type'] ) && 'entity' === $cond['type'] && isset( $cond['entities'] ) ) {
									$entity_count += count( $cond['entities'] );
								}
							}
							WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Merged conditions - Entity conditions: ' . $entity_count );
						}
					}
					
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Successfully fetched ' . count( $item['actions'] ) . ' actions for workflow ' . $item['id'] );
					}
				} else {
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Failed to fetch actions for workflow ' . $item['id'] );
					}
				}
			}
			
			$action_urls = $this->extract_action_urls( $item );
			$entity_ids = $this->extract_entity_ids( $item );
			
			if ( $gateway_debug ) {
				$action_count = is_array( $item['actions'] ?? null ) ? count( $item['actions'] ) : 0;
				WC_Checkoutcom_Utility::logger( sprintf( 
					'[WORKFLOW MATCH] Workflow #%d - ID: %s, Name: %s, Actions: %d, Action URLs: %d, Entity IDs: %s',
					$workflow_index + 1,
					$item['id'] ?? 'N/A',
					$item['name'] ?? 'N/A',
					$action_count,
					count( $action_urls ),
					! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE'
				) );
			}
			
			$matched = false;
			foreach ( $action_urls as $original_action_url ) {
				$normalized_action_url = $this->normalize_webhook_url( $original_action_url );
				
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( sprintf( 
						'[WORKFLOW MATCH] Compare - Original: %s, Normalized: %s, Match: %s',
						$original_action_url,
						$normalized_action_url,
						( '' !== $normalized_action_url && $normalized_action_url === $target_url ) ? 'YES' : 'NO'
					) );
				}
				
				// Exact match
				if ( '' !== $normalized_action_url && $normalized_action_url === $target_url ) {
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ✅ EXACT MATCH FOUND - Adding workflow to matches' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow ID: ' . ( $item['id'] ?? 'N/A' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow Name: ' . ( $item['name'] ?? 'N/A' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action URL: ' . $original_action_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Normalized Action URL: ' . $normalized_action_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Normalized Target URL: ' . $target_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Entity IDs: ' . ( ! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
					}
					$matches[] = [
						'id'         => $item['id'] ?? '',
						'name'       => $item['name'] ?? '',
						'url'        => $original_action_url,
						'entity_ids' => $entity_ids, // Include entity IDs for comparison
					];
					$matched = true;
					break;
				}
				
				// Try flexible matching: compare without query parameters
				$target_without_query = strtok( $target_url, '?' );
				$action_without_query = strtok( $normalized_action_url, '?' );
				if ( '' !== $action_without_query && $action_without_query === $target_without_query ) {
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ✅ MATCH FOUND (without query params) - Adding workflow to matches' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow ID: ' . ( $item['id'] ?? 'N/A' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action URL: ' . $original_action_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Target without query: ' . $target_without_query );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action without query: ' . $action_without_query );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Entity IDs: ' . ( ! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
					}
					$matches[] = [
						'id'         => $item['id'] ?? '',
						'name'       => $item['name'] ?? '',
						'url'        => $original_action_url,
						'entity_ids' => $entity_ids,
					];
					$matched = true;
					break;
				}
				
				// Try matching just the path and query (ONLY for localhost/dev/test environments)
				// This prevents false matches between different production domains
				$target_parsed = wp_parse_url( $url );
				$action_parsed = wp_parse_url( $original_action_url );
				if ( $target_parsed && $action_parsed ) {
					$target_host = isset( $target_parsed['host'] ) ? strtolower( $target_parsed['host'] ) : '';
					$action_host = isset( $action_parsed['host'] ) ? strtolower( $action_parsed['host'] ) : '';
					
					// Only use path+query matching for localhost/dev/test environments
					$is_localhost_target = $this->is_localhost_or_dev( $target_host );
					$is_localhost_action = $this->is_localhost_or_dev( $action_host );
					
					// Only match if BOTH are localhost/dev OR if hosts match
					if ( ( $is_localhost_target && $is_localhost_action ) || $target_host === $action_host ) {
						$target_path_query = ( $target_parsed['path'] ?? '/' ) . ( isset( $target_parsed['query'] ) ? '?' . $target_parsed['query'] : '' );
						$action_path_query = ( $action_parsed['path'] ?? '/' ) . ( isset( $action_parsed['query'] ) ? '?' . $action_parsed['query'] : '' );
						
						// Normalize paths
						$target_path_query = '/' . ltrim( $target_path_query, '/' );
						$action_path_query = '/' . ltrim( $action_path_query, '/' );
						
						if ( $target_path_query === $action_path_query ) {
							if ( $gateway_debug ) {
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ✅ MATCH FOUND (path+query only) - Adding workflow to matches' );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow ID: ' . ( $item['id'] ?? 'N/A' ) );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action URL: ' . $original_action_url );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Target host: ' . $target_host . ' (localhost/dev: ' . ( $is_localhost_target ? 'YES' : 'NO' ) . ')' );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action host: ' . $action_host . ' (localhost/dev: ' . ( $is_localhost_action ? 'YES' : 'NO' ) . ')' );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Target path+query: ' . $target_path_query );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action path+query: ' . $action_path_query );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Entity IDs: ' . ( ! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE' ) );
								WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
							}
							$matches[] = [
								'id'         => $item['id'] ?? '',
								'name'       => $item['name'] ?? '',
								'url'        => $original_action_url,
								'entity_ids' => $entity_ids,
							];
							$matched = true;
							break;
						}
					} elseif ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Skipping path+query match - different production domains' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Target host: ' . $target_host . ' (localhost/dev: ' . ( $is_localhost_target ? 'YES' : 'NO' ) . ')' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Action host: ' . $action_host . ' (localhost/dev: ' . ( $is_localhost_action ? 'YES' : 'NO' ) . ')' );
					}
				}
			}

			// Fallback: some older workflows store the URL in the name field.
			// Also check if name contains hostname that matches target URL
			// Check name field even if we have action URLs, as name might be more accurate
			if ( ! $matched && ! empty( $item['name'] ) ) {
				$workflow_name = $item['name'];
				
				// Try normalizing the name as a URL
				$name_url = $this->normalize_webhook_url( $workflow_name );
				if ( $gateway_debug ) {
					WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Checking name field - Name: ' . $workflow_name . ', Normalized: ' . $name_url );
				}
				
				// Check if normalized name matches target URL exactly
				if ( '' !== $name_url && $name_url === $target_url ) {
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ✅ MATCH FOUND via name field (exact) - Adding workflow to matches' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow ID: ' . ( $item['id'] ?? 'N/A' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow Name: ' . $workflow_name );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Normalized name URL: ' . $name_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Normalized target URL: ' . $target_url );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
					}
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Entity IDs: ' . ( ! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE' ) );
					}
					// Use actual action URL if available, otherwise use name
					$match_url = ! empty( $action_urls ) ? $action_urls[0] : $workflow_name;
					$matches[] = [
						'id'         => $item['id'] ?? '',
						'name'       => $workflow_name,
						'url'        => $match_url,
						'entity_ids' => $entity_ids,
					];
					$matched = true;
					continue;
				}
				
				// Check if name contains hostname that matches target URL hostname
				$target_parsed = wp_parse_url( $url );
				$target_host = isset( $target_parsed['host'] ) ? strtolower( str_replace( 'www.', '', $target_parsed['host'] ) ) : '';
				
				// Extract hostname from workflow name (format: "WC-CKO hostname" or just "hostname")
				if ( preg_match( '/WC-CKO\s+(.+)/i', $workflow_name, $name_matches ) ) {
					$name_host = strtolower( trim( $name_matches[1] ) );
				} elseif ( preg_match( '/^https?:\/\/([^\/\?]+)/i', $workflow_name, $url_matches ) ) {
					$name_host = strtolower( str_replace( 'www.', '', $url_matches[1] ) );
				} else {
					// Assume the name itself might be a hostname
					$name_host = strtolower( str_replace( 'www.', '', trim( $workflow_name ) ) );
				}
				
				if ( ! empty( $target_host ) && ! empty( $name_host ) && $name_host === $target_host ) {
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ✅ MATCH FOUND via name hostname - Adding workflow to matches' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow ID: ' . ( $item['id'] ?? 'N/A' ) );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Workflow Name: ' . $workflow_name );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Name hostname: ' . $name_host );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Target hostname: ' . $target_host );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   ⚠️ NOTE: This is a hostname-only match - should verify actual action URL' );
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Total matches so far: ' . ( count( $matches ) + 1 ) );
					}
					// Note: This is a potential match, but we should still try to fetch the actual URL from actions
					// For now, we'll add it as a match but log that we should verify
					if ( $gateway_debug ) {
						WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH]   Entity IDs: ' . ( ! empty( $entity_ids ) ? implode( ', ', $entity_ids ) : 'NONE' ) );
					}
					// Use actual action URL if available, otherwise use target URL
					$match_url = ! empty( $action_urls ) ? $action_urls[0] : $url;
					$matches[] = [
						'id'         => $item['id'] ?? '',
						'name'       => $workflow_name,
						'url'        => $match_url,
						'entity_ids' => $entity_ids,
					];
					$matched = true;
				}
			}
		}

		if ( $gateway_debug ) {
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ========================================' );
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] FINAL RESULT: ' . count( $matches ) . ' match(es) found' );
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Target URL: ' . $url );
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Normalized Target URL: ' . $target_url );
			
			if ( ! empty( $matches ) ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] Matched workflows:' );
				foreach ( $matches as $index => $match ) {
					$match_entities = isset( $match['entity_ids'] ) && is_array( $match['entity_ids'] ) && ! empty( $match['entity_ids'] )
						? implode( ', ', $match['entity_ids'] )
						: 'NONE';
					WC_Checkoutcom_Utility::logger( sprintf( 
						'  [%d] ID: %s, Name: %s, URL: %s, Entities: %s',
						$index + 1,
						$match['id'] ?? 'NO ID',
						$match['name'] ?? 'NO NAME',
						$match['url'] ?? 'NO URL',
						$match_entities
					) );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] No matches found. Registered workflow URLs:' );
				foreach ( $workflows as $index => $item ) {
					$action_urls = $this->extract_action_urls( $item );
					WC_Checkoutcom_Utility::logger( sprintf( 
						'  [%d] Workflow: %s (ID: %s)',
						$index + 1,
						$item['name'] ?? 'NO NAME',
						$item['id'] ?? 'NO ID'
					) );
					foreach ( $action_urls as $action_url ) {
						$normalized_action = $this->normalize_webhook_url( $action_url );
						WC_Checkoutcom_Utility::logger( '    → Original: ' . $action_url );
						WC_Checkoutcom_Utility::logger( '    → Normalized: ' . $normalized_action );
						WC_Checkoutcom_Utility::logger( '    → Match: ' . ( $normalized_action === $target_url ? 'YES' : 'NO' ) );
					}
					if ( empty( $action_urls ) ) {
						WC_Checkoutcom_Utility::logger( '    → (No action URLs found)' );
					}
				}
			}
			WC_Checkoutcom_Utility::logger( '[WORKFLOW MATCH] ========================================' );
		}

		return $matches;
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
		if ( empty( $url ) ) {
			$url = WC_Checkoutcom_Webhook::get_instance()->generate_current_webhook_url();
		}

		$matches = $this->get_matching_workflows( $url );
		if ( 1 === count( $matches ) && ! empty( $matches[0]['name'] ) ) {
			$this->url_is_registered = $matches[0]['name'];
			return $this->url_is_registered;
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

		// Use direct API call instead of SDK
		if ( empty( $this->secret_key ) || empty( $this->url ) ) {
			return array();
		}

		// Validate URL before making request (prevent malformed URLs like ".api.sandbox.checkout.com")
		if ( false === filter_var( $this->url, FILTER_VALIDATE_URL ) ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( 'Workflow API URL is invalid: ' . $this->url );
			}
			return array();
		}

		// Make direct API call to workflows endpoint
		$response = wp_remote_get(
			$this->url,
			array(
				'headers' => array(
					'Authorization' => $this->secret_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( 'Workflow API request error: ' . $response->get_error_message() . ' (URL: ' . $this->url . ')' );
			}
			return array();
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				$body = wp_remote_retrieve_body( $response );
				WC_Checkoutcom_Utility::logger( 'Workflow API request failed with status ' . $response_code . ': ' . $body );
			}
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check for JSON decode errors
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW GET_LIST] JSON decode error: ' . json_last_error_msg() );
			}
			return array();
		}

		if ( isset( $data['data'] ) && ! empty( $data['data'] ) ) {
			$this->list = $data['data'];
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
			if ( $gateway_debug ) {
				WC_Checkoutcom_Utility::logger( '[WORKFLOW GET_LIST] Retrieved ' . count( $this->list ) . ' workflows' );
			}
			return $this->list;
		}

		return array();
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

		// Check if SDK classes are available
		if ( ! class_exists( 'Checkout\Workflows\Actions\WebhookSignature' ) ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK Workflow classes not found - cannot create webhook workflow' );
			return array();
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
				// 'payment_authorized',
				// 'payment_retry_scheduled',
				// 'payment_returned',
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
		$workflow_request->name       = $this->generate_workflow_name( $url );
		$workflow_request->active     = true;

		$workflows = [];
		try {
			$builder = $this->checkout->get_builder();
			
			// Check if SDK was properly initialized
			if ( ! $builder ) {
				// Only log this error once per hour in admin context to avoid log spam
				$transient_key = 'cko_workflows_create_sdk_error_logged';
				if ( is_admin() && ! get_transient( $transient_key ) ) {
					WC_Checkoutcom_Utility::logger( 'Checkout.com SDK not initialized - cannot create workflow. Please ensure vendor/autoload.php is loaded and API keys are configured.' );
					set_transient( $transient_key, true, HOUR_IN_SECONDS );
				}
				return array( 'error' => 'Payment gateway not properly configured. Please contact support.' );
			}
			
			$workflows = $builder->getWorkflowsClient()->createWorkflow( $workflow_request );

			// Validate the response
			if ( is_wp_error( $workflows ) ) {
				$error_msg = $workflows->get_error_message();
				WC_Checkoutcom_Utility::logger( 'Workflow registration WP_Error: ' . $error_msg );
				return array( 'error' => 'Workflow registration failed: ' . $error_msg );
			}
			
			if ( empty( $workflows ) ) {
				WC_Checkoutcom_Utility::logger( 'Workflow registration returned empty response' );
				return array( 'error' => 'Workflow registration failed: Empty response from API.' );
			}
			
			// Convert to array if it's an object
			if ( is_object( $workflows ) ) {
				$workflows = (array) $workflows;
			}
			
			// Check if response indicates an error
			if ( isset( $workflows['error'] ) || isset( $workflows['error_type'] ) ) {
				$error_msg = isset( $workflows['error'] ) ? $workflows['error'] : ( isset( $workflows['error_type'] ) ? $workflows['error_type'] : 'Unknown error' );
				WC_Checkoutcom_Utility::logger( 'Workflow registration failed: ' . $error_msg );
				return array( 'error' => 'Workflow registration failed: ' . $error_msg );
			}
			
			// Verify ID exists
			if ( ! isset( $workflows['id'] ) ) {
				WC_Checkoutcom_Utility::logger( 'Workflow registration response missing ID. Response keys: ' . implode( ', ', array_keys( (array) $workflows ) ) );
				return array( 'error' => 'Workflow registration failed: Response missing workflow ID.' );
			}

			return $workflows;
			
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
			$error_message = esc_html__( 'An unexpected error occurred while processing workflow request.', 'checkout-com-unified-payments-api' );
			
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
}
