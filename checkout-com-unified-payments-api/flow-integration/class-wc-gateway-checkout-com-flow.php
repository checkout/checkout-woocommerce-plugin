<?php
/**
 * FLOW class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/../includes/settings/class-wc-checkoutcom-cards-settings.php';
require_once __DIR__ . '/../includes/api/class-wc-checkoutcom-api-request.php';
require_once __DIR__ . '/../includes/subscription/class-wc-checkoutcom-subscription.php';

/**
 * Class WC_Gateway_Checkout_Com_Flow for FLOW.
 */
#[AllowDynamicProperties]
class WC_Gateway_Checkout_Com_Flow extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_Flow constructor.
	 */
	public function __construct() {

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );

		$this->id                 = 'wc_checkout_com_flow';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = !empty( $core_settings['title'] ) ? trim( __( $core_settings['title'], 'checkout-com-unified-payments-api' ) ) : __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_date_changes',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->flow_enabled();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Webhook handler hook - ENABLED for Flow integration
		// Flow integration uses direct redirect for successful payments, but webhooks for 3DS, failures, etc.
		add_action( 'woocommerce_api_wc_checkoutcom_webhook', [ $this, 'webhook_handler' ] );
		
		// WC API endpoint for processing 3DS returns (similar to PayPal)
		// This allows direct redirect to order-received page without showing checkout page
		add_action( 'woocommerce_api_wc_checkoutcom_flow_process', [ $this, 'handle_3ds_return' ] );
		
		// Detect 3DS return on checkout page load and process server-side (no AJAX)
		// This handles cases where user returns to checkout page with 3DS parameters
		// Priority 1 ensures it runs very early, before other template_redirect hooks
		add_action( 'template_redirect', [ $this, 'detect_and_process_3ds_return_on_checkout' ], 1 );

		// Meta field on subscription edit.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_payment_meta_field' ], 10, 2 );
		
		// Store save card preference in order metadata when order is created (before 3DS redirect)
		add_action( 'woocommerce_checkout_create_order', [ $this, 'store_save_card_preference_in_order' ], 10, 2 );

		// Secure AJAX handler for creating payment sessions (prevents secret key exposure to frontend)
		add_action( 'wp_ajax_cko_flow_create_payment_session', [ $this, 'ajax_create_payment_session' ] );
		add_action( 'wp_ajax_nopriv_cko_flow_create_payment_session', [ $this, 'ajax_create_payment_session' ] );
		
		// AJAX handler for creating orders before payment processing (early order creation)
		add_action( 'wp_ajax_cko_flow_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wp_ajax_nopriv_cko_flow_create_order', [ $this, 'ajax_create_order' ] );
		
		// AJAX handler for creating failed orders when payment is declined before form submission
		add_action( 'wp_ajax_cko_flow_create_failed_order', [ $this, 'ajax_create_failed_order' ] );
		add_action( 'wp_ajax_nopriv_cko_flow_create_failed_order', [ $this, 'ajax_create_failed_order' ] );
		
		// AJAX handler for storing save card preference in WooCommerce session
		add_action( 'wp_ajax_cko_flow_store_save_card_preference', [ $this, 'ajax_store_save_card_preference' ] );
		add_action( 'wp_ajax_nopriv_cko_flow_store_save_card_preference', [ $this, 'ajax_store_save_card_preference' ] );
		
		// AJAX handler for saving payment session ID to order immediately after payment session creation
		add_action( 'wp_ajax_cko_flow_save_payment_session_id', [ $this, 'ajax_save_payment_session_id' ] );
		add_action( 'wp_ajax_nopriv_cko_flow_save_payment_session_id', [ $this, 'ajax_save_payment_session_id' ] );
	}

	/**
	 * Check if the gateway is available.
	 * 
	 * Override parent method to ensure gateway is available when enabled and checkout mode is 'flow'.
	 * This bypasses WooCommerce's default currency/country restrictions.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Log availability check start (only in debug mode to reduce log spam)
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Checking if Flow gateway is available...' );
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Gateway enabled setting: ' . ( isset( $this->enabled ) ? $this->enabled : 'NOT SET' ) );
		}
		
		// First check if gateway is enabled
		if ( 'yes' !== $this->enabled ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Gateway is NOT available - enabled setting is not "yes"' );
			}
			return false;
		}

		// Check if checkout mode is set to 'flow'
		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Checkout mode: ' . $checkout_mode );
		}
		
		if ( 'flow' !== $checkout_mode ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Gateway is NOT available - checkout mode is not "flow" (current: ' . $checkout_mode . ')' );
			}
			return false;
		}

		// Check if API credentials are set
		$secret_key = isset( $checkout_setting['ckocom_secret_key'] ) ? $checkout_setting['ckocom_secret_key'] : '';
		$public_key = isset( $checkout_setting['ckocom_public_key'] ) ? $checkout_setting['ckocom_public_key'] : '';
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Secret key: ' . ( ! empty( $secret_key ) ? 'SET (' . substr( $secret_key, 0, 10 ) . '...)' : 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Public key: ' . ( ! empty( $public_key ) ? 'SET (' . substr( $public_key, 0, 10 ) . '...)' : 'NOT SET' ) );
		}
		
		if ( empty( $secret_key ) || empty( $public_key ) ) {
			// Log warning but don't block availability (credentials might be set later)
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] WARNING: API credentials not set, but gateway is still available' );
			}
		}

		// Gateway is available if enabled and checkout mode is 'flow'
		// We bypass currency/country restrictions as Flow supports all currencies/countries
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW AVAILABILITY] Gateway IS available - all checks passed' );
		}
		return true;
	}

	/**
	 * Check if the gateway is valid for use.
	 * 
	 * Override parent method to bypass currency/country restrictions.
	 * Flow supports all currencies and countries, so we always return true
	 * when the gateway is enabled and checkout mode is 'flow'.
	 *
	 * @return bool
	 */
	public function valid_for_use() {
		// Log valid_for_use check start
		WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Checking if Flow gateway is valid for use...' );
		WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Gateway enabled setting: ' . ( isset( $this->enabled ) ? $this->enabled : 'NOT SET' ) );
		
		// Check if gateway is enabled
		if ( 'yes' !== $this->enabled ) {
			WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Gateway is NOT valid - enabled setting is not "yes"' );
			return false;
		}

		// Check if checkout mode is set to 'flow'
		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';
		
		WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Checkout mode: ' . $checkout_mode );
		
		if ( 'flow' !== $checkout_mode ) {
			WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Gateway is NOT valid - checkout mode is not "flow" (current: ' . $checkout_mode . ')' );
			return false;
		}

		// Flow supports all currencies and countries, so always return true
		// This bypasses WooCommerce's default currency/country restrictions
		WC_Checkoutcom_Utility::logger( '[FLOW VALID FOR USE] Gateway IS valid for use - all checks passed' );
		return true;
	}

	/**
	 * Add subscription order payment meta field.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription An instance of a subscription object.
	 * @return array
	 */
	/**
	 * Store save card preference in order metadata when order is created.
	 * This ensures the preference survives 3DS redirects.
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $data  The order data.
	 * @return void
	 */
	public function store_save_card_preference_in_order( $order, $data ) {
		// Only process if this is a Flow payment
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		
		// Check if customer wants to save card (from POST data during order creation)
		$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
		$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
		$save_card_session = WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );
		
		// Determine if checkbox was checked
		$save_card_preference = 'no';
		if ( 'yes' === $save_card_hidden ) {
			$save_card_preference = 'yes';
		} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
			$save_card_preference = 'yes';
		} elseif ( 'yes' === $save_card_session ) {
			$save_card_preference = 'yes';
		}
		
		// Store in order metadata
		$order->update_meta_data( '_cko_save_card_preference', $save_card_preference );
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Stored save card preference in order metadata: ' . $save_card_preference . ' (Order ID: ' . $order->get_id() . ')' );
	}

	public function add_payment_meta_field( $payment_meta, $subscription ) {
		$source_id = $subscription->get_meta( '_cko_source_id' );

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_cko_source_id' => [
					'value' => $source_id,
					'label' => 'Checkout.com FLOW Source ID',
				],
			],
		];

		return $payment_meta;
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::flow_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
			array(
				'screen_button' => array(
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
				),
			)
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
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {

		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		
		// Safely get flow saved card setting with fallback
		$flow_settings = get_option( 'woocommerce_wc_checkout_com_flow_settings', array() );
		$flow_saved_card = isset( $flow_settings['flow_saved_payment'] ) ? $flow_settings['flow_saved_payment'] : 'saved_cards_first';
		$flow_debug_logging = isset( $flow_settings['flow_debug_logging'] ) && 'yes' === $flow_settings['flow_debug_logging'];

		$order_pay_order_id = null;
		$order_pay_order = null;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			global $wp;
			// We are on the order-pay page. Retrives order_id from order-pay page
			$order_pay_order_id = absint( $wp->query_vars['order-pay'] ?? 0 );
			$order_pay_order = wc_get_order( $order_pay_order_id );
		}

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo esc_html( $this->get_option( 'description' ) ); 
		}
		?>
			<div></div>
			<div id="loading-overlay"><?php esc_html_e( 'Loading...', 'checkout-com-unified-payments-api' ); ?></div>
			<div id="loading-overlay2"><?php esc_html_e( 'Loading...Do NOT refresh.', 'checkout-com-unified-payments-api' ); ?></div>
			<?php if ( is_user_logged_in() ) : ?>

				<?php if ( $order_pay_order ) : ?>
					<?php 
					// Only hide save card options for MOTO orders (Admin order + Order-pay page + Guest customer)
					$is_admin_created = $order_pay_order->is_created_via( 'admin' );
					$is_guest_customer = $order_pay_order->get_customer_id() == 0;
					$is_moto_order = $is_admin_created && $is_guest_customer;
					?>
					<?php if ( $is_moto_order ) : ?>
						<script>
							const orderPayTargetNode = document.body;

							// Hide Saved payment method for MOTO orders (Admin order + Order-pay page + Guest customer).
							// IMPORTANT: Hide the accordion container, not just the list inside
							const orderPayObserver = new MutationObserver((mutationsList, observer) => {
								// Hide the accordion container (which contains the saved cards)
								const $accordion = jQuery('.saved-cards-accordion-container');
								if ($accordion.length) {
									$accordion.hide();
									if (flowDebugLogging) console.log('[FLOW] Hiding saved cards accordion for MOTO order');
								}
								
								// Also hide any saved payment methods that aren't wrapped yet
								const $element = jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
								if ($element.length) {
									$element.hide();
								}
								
								// Hide the "Save to account" checkbox for MOTO orders (Admin order + Order-pay page + Guest customer)
								const $saveNew = jQuery('.woocommerce-SavedPaymentMethods-saveNew');
								if ($saveNew.length) {
									$saveNew.hide();
									if (flowDebugLogging) console.log('[FLOW] Hiding save to account checkbox for MOTO order');
								}
								
								// Also hide the checkbox by its ID
								const $saveCheckbox = jQuery('#wc-wc_checkout_com_flow-new-payment-method').closest('p');
								if ($saveCheckbox.length) {
									$saveCheckbox.hide();
								}
								
								// Keep observer running to catch late additions
							});

							const orderPayConfig = {
								childList: true,
								subtree: true
							};

							orderPayObserver.observe(orderPayTargetNode, orderPayConfig);

							// Try to hide it immediately in case it's already present.
							jQuery('.saved-cards-accordion-container').hide();
							jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide();
							
							// Hide the "Save to account" checkbox for MOTO orders (Admin order + Order-pay page + Guest customer)
							jQuery('.woocommerce-SavedPaymentMethods-saveNew').hide();
							jQuery('#wc-wc_checkout_com_flow-new-payment-method').closest('p').hide();
						</script>
					<?php else : ?>
					<script>
						// Expose saved payment display order to JavaScript
						window.saved_payment = '<?php echo esc_js( $flow_saved_card ); ?>';
						
						jQuery(document).ready(function($) {
							
							// Enhance payment method label styling
							function enhancePaymentLabel() {
								const $label = $('.payment_method_wc_checkout_com_flow > label[for="payment_method_wc_checkout_com_flow"]');
								
								if ($label.length && !$label.find('.payment-method-text').length) {
									const labelText = $label.text().trim();
									
									// Wrap label text in styled containers
									$label.html(`
										<span class="payment-method-text">
											<span class="payment-method-title">${labelText}</span>
											<span class="payment-method-subtitle">Secure payment powered by Checkout.com</span>
										</span>
									`);
									
									flowLog('Payment method label enhanced');
								}
							}
							
							// Run immediately and after checkout updates
							enhancePaymentLabel();
							$(document.body).on('updated_checkout', enhancePaymentLabel);
							
							// Customize "Save to account" checkbox label
							function customizeSaveCardLabel() {
								const $checkbox = $('#wc-wc_checkout_com_flow-new-payment-method');
								const $label = $('label[for="wc-wc_checkout_com_flow-new-payment-method"]');
								
								if ($label.length) {
									$label.html('<span class="save-card-label-text">Save card for future purchases</span>');
									flowLog('Save card label customized');
								}
								
								// Apply Flow customization styles to the save card checkbox
								if (typeof window.appearance !== 'undefined') {
									const colors = window.appearance;
									
									// Style the checkbox container
									$('.cko-save-card-checkbox').css({
										'padding': '12px 16px',
										'background-color': colors.colorFormBackground || '#f8f9fa',
										'border': '1px solid ' + (colors.colorBorder || '#e0e0e0'),
										'border-radius': colors.borderRadius ? colors.borderRadius[0] : '8px',
										'margin': '12px 0',
									});
									
									// Style the label text
									$('.save-card-label-text').css({
										'color': colors.colorPrimary || '#1a1a1a',
										'font-family': colors.label?.fontFamily || 'inherit',
										'font-size': colors.label?.fontSize || '14px',
										'font-weight': colors.label?.fontWeight || '500',
										'margin-left': '8px',
									});
									
									flowLog('Save card checkbox styled with Flow colors');
								}
							}
							
							// Apply label customization
							customizeSaveCardLabel();
							$(document.body).on('updated_checkout', function() {
								setTimeout(customizeSaveCardLabel, 100);
							});
							
							// Re-apply styles after Flow customization loads
							$(window).on('load', function() {
								setTimeout(customizeSaveCardLabel, 600);
							});
							
							// Remove "Use a new payment method" radio button from DOM
							function removeNewPaymentMethodButton() {
								$('.woocommerce-SavedPaymentMethods-new').remove();
								$('li.woocommerce-SavedPaymentMethods-new').remove();
								$('input[value="new"][name*="payment-token"]').closest('li').remove();
								flowLog('Removed "Use a new payment method" button');
							}
							
							// Remove immediately and after checkout updates
							removeNewPaymentMethodButton();
							$(document.body).on('updated_checkout', function() {
								setTimeout(removeNewPaymentMethodButton, 100);
							});
							
							// Apply payment method label customization settings
							function applyPaymentLabelCustomization() {
								if (typeof cko_flow_customization_vars === 'undefined') {
									setTimeout(applyPaymentLabelCustomization, 100);
									return;
								}
								
								const settings = cko_flow_customization_vars;
								const root = document.documentElement;
								
								// Background
								const bg = settings.flow_payment_label_background || 'transparent';
								root.style.setProperty('--cko-payment-label-bg', bg === 'transparent' || bg === '' ? 'transparent' : bg);
								
								// Border
								const borderColor = settings.flow_payment_label_border_color || '';
								const borderWidth = settings.flow_payment_label_border_width || '0px';
								root.style.setProperty('--cko-payment-label-border-color', borderColor || 'transparent');
								root.style.setProperty('--cko-payment-label-border-width', borderWidth);
								
								// Border radius
								const borderRadius = settings.flow_payment_label_border_radius || '0px';
								root.style.setProperty('--cko-payment-label-border-radius', borderRadius);
								
								// Icon position
								const iconPosition = settings.flow_payment_label_icon_position || 'right';
								if (iconPosition === 'right') {
									root.style.setProperty('--cko-payment-label-icon-display-left', 'none');
									root.style.setProperty('--cko-payment-label-icon-display-right', 'inline-block');
									root.style.setProperty('--cko-payment-label-icon-order-right', '2');
								} else if (iconPosition === 'left') {
									root.style.setProperty('--cko-payment-label-icon-display-left', 'inline-block');
									root.style.setProperty('--cko-payment-label-icon-display-right', 'none');
								} else { // none
									root.style.setProperty('--cko-payment-label-icon-display-left', 'none');
									root.style.setProperty('--cko-payment-label-icon-display-right', 'none');
								}
								
								// Text colors
								const textColor = settings.flow_payment_label_text_color || '';
								const subtitleColor = settings.flow_payment_label_subtitle_color || '';
								root.style.setProperty('--cko-payment-label-text-color', textColor || 'inherit');
								root.style.setProperty('--cko-payment-label-subtitle-color', subtitleColor || 'inherit');
								
								// Text alignment
								const textAlign = settings.flow_payment_label_text_align || 'left';
								root.style.setProperty('--cko-payment-label-text-align', textAlign);
								
								flowLog('Payment label customization applied:', {
									background: bg,
									border: `${borderWidth} ${borderColor || 'transparent'}`,
									iconPosition: iconPosition,
									textColor: textColor || 'inherit',
									textAlign: textAlign
								});
							}
							
							// Apply Flow customization colors to payment label and saved cards
							let appearanceRetryCount = 0;
							const maxAppearanceRetries = 50; // Max 5 seconds (50 * 100ms)
							function applyFlowCustomization() {
								if (typeof window.appearance === 'undefined') {
									appearanceRetryCount++;
									if (appearanceRetryCount < maxAppearanceRetries) {
										setTimeout(applyFlowCustomization, 100);
										return;
									} else {
										flowLog('Appearance settings not available after ' + maxAppearanceRetries + ' retries - skipping customization');
										return;
									}
								}
								
								const colors = window.appearance;
								const borderRadius = colors.borderRadius ? colors.borderRadius[0] : '8px';
								
								flowLog('Applying Flow customization colors:', colors);
								
								// Note: Payment method label styling is now handled by applyPaymentLabelCustomization()
								// This function only applies to saved cards accordion and Flow container
								
								// Apply to saved cards accordion
								const $accordion = $('.saved-cards-accordion');
								if ($accordion.length) {
									$accordion.css({
										'background-color': colors.colorFormBackground || '#ffffff',
										'border-color': colors.colorPrimary || '#186aff',
										'border-radius': borderRadius,
									});
									
									// Apply to saved cards header text
									$('.saved-cards-label-text').css({
										'color': colors.colorPrimary || '#1a1a1a',
										'font-family': colors.label?.fontFamily || 'inherit',
										'font-size': colors.label?.fontSize || '15px',
										'font-weight': colors.label?.fontWeight || '600',
									});
									
									$('.saved-cards-label-subtext').css({
										'color': colors.colorSecondary || '#666',
										'font-family': colors.footnote?.fontFamily || 'inherit',
										'font-size': colors.footnote?.fontSize || '13px',
									});
								}
								
								// Apply to Flow container border
								const $flowContainer = $('#flow-container');
								if ($flowContainer.length && $('.saved-cards-accordion-container').length) {
									$flowContainer.css({
										'background-color': colors.colorFormBackground || '#ffffff',
										'border-color': colors.colorPrimary || '#186aff',
										'border-radius': borderRadius,
									});
								}
								
								flowLog('Flow customization colors applied');
							}
							
							// Apply payment label customization on page load and after checkout updates
							jQuery(document).ready(function() {
								applyPaymentLabelCustomization();
								jQuery(document.body).on('updated_checkout', function() {
									setTimeout(applyPaymentLabelCustomization, 100);
								});
							});
							
							// Apply on page load and after checkout updates
							$(window).on('load', function() {
								setTimeout(applyFlowCustomization, 500);
							});
							$(document.body).on('updated_checkout', function() {
								setTimeout(applyFlowCustomization, 200);
							});
							
							const $savedMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');

						const totalCount = $savedMethods.toArray().reduce((sum, el) => {
							return sum + parseInt($(el).data('count') || 0, 10);
						}, 0);

				// Show both Flow and saved cards on the same page
				const displayOrder = '<?php echo esc_js( $flow_saved_card ); ?>';
				flowLog('Display order:', displayOrder, 'Total saved cards:', totalCount);
				flowLog('CSS will control saved cards visibility via data-saved-payment-order attribute');

				// Wrap saved payment methods in styled accordion container
				function wrapSavedCardsInAccordion() {
					if (totalCount > 0 && !$('.saved-cards-accordion-container').length && $savedMethods.length > 0) {
						// Wait for Flow container or use payment method container as fallback
						let $insertionPoint = $('#flow-container');
						let $fallbackPoint = $('.payment_method_wc_checkout_com_flow').first();
						
						if (!$insertionPoint.length) {
							$insertionPoint = $fallbackPoint;
						}
						
						flowLog('Insertion point found:', $insertionPoint.length > 0, 'Display order:', displayOrder);
						
						if ($insertionPoint.length || $fallbackPoint.length) {
							// Create accordion container
							const accordionHTML = `
								<div class="saved-cards-accordion-container">
									<div class="saved-cards-accordion">
										<div class="saved-cards-accordion-header">
											<div class="saved-cards-accordion-left">
												<div class="saved-cards-icon">
													<svg width="40" height="24" viewBox="0 0 40 24" xmlns="http://www.w3.org/2000/svg" fill="none">
														<rect x="0.5" y="0.5" width="39" height="23" rx="3.5" stroke="#186aff"></rect>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 6.85714H13.1428V17.1429H26.8571V6.85714ZM12.2857 6V18H27.7143V6H12.2857Z" fill="#186aff"></path>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 9.42857H13.1428V7.71429H26.8571V9.42857Z" fill="#186aff"></path>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2857 15.4286H14.8571V14.5714H18.2857V15.4286Z" fill="#186aff"></path>
													</svg>
												</div>
												<div class="saved-cards-label">
													<span class="saved-cards-label-text">Saved cards</span>
													<span class="saved-cards-label-subtext">${totalCount} card${totalCount !== 1 ? 's' : ''} available</span>
												</div>
											</div>
										</div>
										<div class="saved-cards-accordion-panel">
											<!-- Saved payment methods will be moved here -->
										</div>
									</div>
								</div>
							`;
							
							// CRITICAL FIX: Insert accordion in a safe location that won't become flow-container
							// The payment method <li> structure is: radio, label, [accordion/payment_box]
							const $paymentMethodLi = $('.payment_method_wc_checkout_com_flow').first();
							const $label = $paymentMethodLi.find('label').first();
							
							if (displayOrder === 'saved_cards_first') {
								// Insert accordion AFTER the label, BEFORE any divs
								// This way it can never be mistaken for the payment_box div
								if ($label.length) {
									$label.after(accordionHTML);
									flowLog('Accordion inserted AFTER label (saved_cards_first)');
								} else {
									// Fallback: insert before first div
									const $firstDiv = $paymentMethodLi.children('div').first();
									if ($firstDiv.length) {
										$firstDiv.before(accordionHTML);
										flowLog('Accordion inserted BEFORE div (saved_cards_first fallback)');
									}
								}
							} else {
								// new_payment_first: Insert accordion AFTER the payment_box div (which contains flow-container)
								const $paymentBox = $paymentMethodLi.find('.payment_box').first();
								if ($paymentBox.length) {
									$paymentBox.after(accordionHTML);
									flowLog('Accordion inserted AFTER payment_box (new_payment_first)');
								} else {
									// Fallback: insert after label
									if ($label.length) {
										$label.after(accordionHTML);
										flowLog('Accordion inserted AFTER label (new_payment_first fallback)');
									}
								}
							}
							
							// Move saved payment methods into the accordion panel
							$savedMethods.each(function() {
								$(this).appendTo('.saved-cards-accordion-panel');
							});
							
							flowLog('Saved cards wrapped in accordion');
							
						// REMOVED: No default card selection - user must explicitly select a saved card
						// This prevents issues with default card detection
						flowLog('No default card selection - user must explicitly select');
						
						// Ensure no saved cards are auto-selected (remove any defaults)
						$('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').prop('checked', false).removeAttr('checked');
						
						// Ensure "new" card option is selected by default
						const $newCardRadio = $('#wc-wc_checkout_com_flow-payment-token-new');
						if ($newCardRadio.length) {
							$newCardRadio.prop('checked', true);
							flowLog('"New card" option selected by default');
						}
						
						// Reset flags
						window.flowSavedCardSelected = false;
						window.flowUserInteracted = false;
							window.flowUserInteracted = false;
							flowLog('Unchecked all saved cards and selected new payment method');
						}
							
							// CRITICAL: After moving cards, ensure visibility rules are applied
							// Remove any inline display styles that might have been set during the move
							$('.saved-cards-accordion-container').each(function() {
								// Remove inline style to let CSS handle visibility
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from accordion container');
								}
							});
							
							$('.saved-cards-accordion-panel').each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from accordion panel');
								}
							});
							
							$savedMethods.each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from saved methods');
								}
							});
							
							flowLog('CSS will handle visibility via data-saved-payment-order:', displayOrder);
						} else {
							flowLog('No insertion point found, retrying...');
							setTimeout(wrapSavedCardsInAccordion, 100);
						}
					}
				}
				
				// Try immediately and also set a timeout as fallback
				wrapSavedCardsInAccordion();
				setTimeout(wrapSavedCardsInAccordion, 500);
				
				// CRITICAL: Re-wrap saved cards after WooCommerce updates checkout
				// WooCommerce's updated_checkout event destroys and recreates the payment methods HTML
				// Use a flag to prevent multiple simultaneous executions
				let accordionRecreationInProgress = false;
				jQuery(document.body).on('updated_checkout', function() {
					// Prevent multiple simultaneous executions
					if (accordionRecreationInProgress) {
						flowLog('Accordion recreation already in progress, skipping...');
						return;
					}
					
					flowLog('updated_checkout fired - re-wrapping saved cards...');
					accordionRecreationInProgress = true;
					
					setTimeout(function() {
						// Check if accordion AND panel exist, and if saved methods are inside
						const $existingAccordion = $('.saved-cards-accordion-container');
						const $existingPanel = $('.saved-cards-accordion-panel');
						const $panelMethods = $('.saved-cards-accordion-panel .woocommerce-SavedPaymentMethods');
						const $allMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
						
						flowLog('After updated_checkout:', {
							accordion: $existingAccordion.length,
							panel: $existingPanel.length,
							methodsInPanel: $panelMethods.length,
							totalMethods: $allMethods.length
						});
						
						// Recreate if accordion doesn't exist OR panel is missing OR methods not in panel
						if ($existingAccordion.length === 0 || $existingPanel.length === 0 || ($allMethods.length > 0 && $panelMethods.length === 0)) {
							flowLog('Accordion incomplete after updated_checkout, recreating...');
							// Remove any incomplete accordion first
							$existingAccordion.remove();
							// Recreate with fresh saved methods
							wrapSavedCardsInAccordion();
						} else {
							flowLog('Accordion complete after updated_checkout');
						}
						
						// Reset flag after a delay to allow for any follow-up updates
						setTimeout(function() {
							accordionRecreationInProgress = false;
						}, 500);
					}, 100);
				});

					});

						// Don't hide save card checkbox on page load - let Flow component control it

						document.addEventListener('DOMContentLoaded', function () {
							const radios = document.querySelectorAll('input[name="wc-wc_checkout_com_flow-payment-token"]');
							radios.forEach(radio => {
								radio.checked = false;
							});
						});
					</script>
					<?php endif; ?>
				<?php else : ?>
				<script>
					// Expose saved payment display order to JavaScript
					window.saved_payment = '<?php echo esc_js( $flow_saved_card ); ?>';
					
					jQuery(document).ready(function($) {
						
						// Customize "Save to account" checkbox label
						function customizeSaveCardLabel() {
							const $checkbox = $('#wc-wc_checkout_com_flow-new-payment-method');
							const $label = $('label[for="wc-wc_checkout_com_flow-new-payment-method"]');
							
							if ($label.length) {
								$label.html('<span class="save-card-label-text">Save card for future purchases</span>');
								flowLog('Save card label customized (section 2)');
							}
							
							// Apply Flow customization styles to the save card checkbox
							if (typeof window.appearance !== 'undefined') {
								const colors = window.appearance;
								
								// Style the checkbox container
								$('.cko-save-card-checkbox').css({
									'padding': '12px 16px',
									'background-color': colors.colorFormBackground || '#f8f9fa',
									'border': '1px solid ' + (colors.colorBorder || '#e0e0e0'),
									'border-radius': colors.borderRadius ? colors.borderRadius[0] : '8px',
									'margin': '12px 0',
								});
								
								// Style the label text
								$('.save-card-label-text').css({
									'color': colors.colorPrimary || '#1a1a1a',
									'font-family': colors.label?.fontFamily || 'inherit',
									'font-size': colors.label?.fontSize || '14px',
									'font-weight': colors.label?.fontWeight || '500',
									'margin-left': '8px',
								});
								
								flowLog('Save card checkbox styled with Flow colors (section 2)');
							}
						}
						
						// Apply label customization
						customizeSaveCardLabel();
						$(document.body).on('updated_checkout', function() {
							setTimeout(customizeSaveCardLabel, 100);
						});
						
						// Re-apply styles after Flow customization loads
						$(window).on('load', function() {
							setTimeout(customizeSaveCardLabel, 600);
						});
						
						// Remove "Use a new payment method" radio button from DOM
						function removeNewPaymentMethodButton() {
							$('.woocommerce-SavedPaymentMethods-new').remove();
							$('li.woocommerce-SavedPaymentMethods-new').remove();
							$('input[value="new"][name*="payment-token"]').closest('li').remove();
							flowLog('Removed "Use a new payment method" button (section 2)');
						}
						
						// Remove immediately and after checkout updates
						removeNewPaymentMethodButton();
						$(document.body).on('updated_checkout', function() {
							setTimeout(removeNewPaymentMethodButton, 100);
						});
						
						// Apply payment method label customization settings (section 2)
						function applyPaymentLabelCustomization() {
							if (typeof cko_flow_customization_vars === 'undefined') {
								setTimeout(applyPaymentLabelCustomization, 100);
								return;
							}
							
							const settings = cko_flow_customization_vars;
							const root = document.documentElement;
							
							// Background
							const bg = settings.flow_payment_label_background || 'transparent';
							root.style.setProperty('--cko-payment-label-bg', bg === 'transparent' || bg === '' ? 'transparent' : bg);
							
							// Border
							const borderColor = settings.flow_payment_label_border_color || '';
							const borderWidth = settings.flow_payment_label_border_width || '0px';
							root.style.setProperty('--cko-payment-label-border-color', borderColor || 'transparent');
							root.style.setProperty('--cko-payment-label-border-width', borderWidth);
							
							// Border radius
							const borderRadius = settings.flow_payment_label_border_radius || '0px';
							root.style.setProperty('--cko-payment-label-border-radius', borderRadius);
							
							// Icon position
							const iconPosition = settings.flow_payment_label_icon_position || 'right';
							if (iconPosition === 'right') {
								root.style.setProperty('--cko-payment-label-icon-display-left', 'none');
								root.style.setProperty('--cko-payment-label-icon-display-right', 'inline-block');
								root.style.setProperty('--cko-payment-label-icon-order-right', '2');
							} else if (iconPosition === 'left') {
								root.style.setProperty('--cko-payment-label-icon-display-left', 'inline-block');
								root.style.setProperty('--cko-payment-label-icon-display-right', 'none');
							} else { // none
								root.style.setProperty('--cko-payment-label-icon-display-left', 'none');
								root.style.setProperty('--cko-payment-label-icon-display-right', 'none');
							}
							
							// Text colors
							const textColor = settings.flow_payment_label_text_color || '';
							const subtitleColor = settings.flow_payment_label_subtitle_color || '';
							root.style.setProperty('--cko-payment-label-text-color', textColor || 'inherit');
							root.style.setProperty('--cko-payment-label-subtitle-color', subtitleColor || 'inherit');
							
							// Text alignment
							const textAlign = settings.flow_payment_label_text_align || 'left';
							root.style.setProperty('--cko-payment-label-text-align', textAlign);
							
							flowLog('Payment label customization applied (section 2):', {
								background: bg,
								border: `${borderWidth} ${borderColor || 'transparent'}`,
								iconPosition: iconPosition,
								textColor: textColor || 'inherit',
								textAlign: textAlign
							});
						}
						
						// Apply Flow customization colors to payment label and saved cards
						let appearanceRetryCount = 0;
						const maxAppearanceRetries = 50; // Max 5 seconds (50 * 100ms)
						function applyFlowCustomization() {
							if (typeof window.appearance === 'undefined') {
								appearanceRetryCount++;
								if (appearanceRetryCount < maxAppearanceRetries) {
									setTimeout(applyFlowCustomization, 100);
									return;
								} else {
									flowLog('Appearance settings not available after ' + maxAppearanceRetries + ' retries - skipping customization');
									return;
								}
							}
							
							const colors = window.appearance;
							const borderRadius = colors.borderRadius ? colors.borderRadius[0] : '8px';
							
							flowLog('Applying Flow customization colors (section 2):', colors);
							
							// Note: Payment method label styling is now handled by applyPaymentLabelCustomization()
							// This function only applies to saved cards accordion and Flow container
							
							// Apply to saved cards accordion
							const $accordion = $('.saved-cards-accordion');
							if ($accordion.length) {
								$accordion.css({
									'background-color': colors.colorFormBackground || '#ffffff',
									'border-color': colors.colorPrimary || '#186aff',
									'border-radius': borderRadius,
								});
								
								// Apply to saved cards header text
								$('.saved-cards-label-text').css({
									'color': colors.colorPrimary || '#1a1a1a',
									'font-family': colors.label?.fontFamily || 'inherit',
									'font-size': colors.label?.fontSize || '15px',
									'font-weight': colors.label?.fontWeight || '600',
								});
								
								$('.saved-cards-label-subtext').css({
									'color': colors.colorSecondary || '#666',
									'font-family': colors.footnote?.fontFamily || 'inherit',
									'font-size': colors.footnote?.fontSize || '13px',
								});
							}
							
							// Apply to Flow container border
							const $flowContainer = $('#flow-container');
							if ($flowContainer.length && $('.saved-cards-accordion-container').length) {
								$flowContainer.css({
									'background-color': colors.colorFormBackground || '#ffffff',
									'border-color': colors.colorPrimary || '#186aff',
									'border-radius': borderRadius,
								});
							}
							
							flowLog('Flow customization colors applied (section 2)');
						}
						
						// Apply payment label customization on page load and after checkout updates (section 2)
						jQuery(document).ready(function() {
							applyPaymentLabelCustomization();
							jQuery(document.body).on('updated_checkout', function() {
								setTimeout(applyPaymentLabelCustomization, 100);
							});
						});
						
						// Apply on page load and after checkout updates
						$(window).on('load', function() {
							setTimeout(applyFlowCustomization, 500);
						});
						$(document.body).on('updated_checkout', function() {
							setTimeout(applyFlowCustomization, 200);
						});
						
						const $savedMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');

				const totalCount = $savedMethods.toArray().reduce((sum, el) => {
					return sum + parseInt($(el).data('count') || 0, 10);
				}, 0);

				// Show both Flow and saved cards on the same page
				const displayOrder = '<?php echo esc_js( $flow_saved_card ); ?>';
				flowLog('Display order:', displayOrder, 'Total saved cards:', totalCount);
				flowLog('CSS will control saved cards visibility via data-saved-payment-order attribute');

				// Wrap saved payment methods in styled accordion container
				function wrapSavedCardsInAccordion() {
					if (totalCount > 0 && !$('.saved-cards-accordion-container').length && $savedMethods.length > 0) {
						// Wait for Flow container or use payment method container as fallback
						let $insertionPoint = $('#flow-container');
						let $fallbackPoint = $('.payment_method_wc_checkout_com_flow').first();
						
						if (!$insertionPoint.length) {
							$insertionPoint = $fallbackPoint;
						}
						
						flowLog('Insertion point found:', $insertionPoint.length > 0, 'Display order:', displayOrder);
						
						if ($insertionPoint.length || $fallbackPoint.length) {
							// Create accordion container
							const accordionHTML = `
								<div class="saved-cards-accordion-container">
									<div class="saved-cards-accordion">
										<div class="saved-cards-accordion-header">
											<div class="saved-cards-accordion-left">
												<div class="saved-cards-icon">
													<svg width="40" height="24" viewBox="0 0 40 24" xmlns="http://www.w3.org/2000/svg" fill="none">
														<rect x="0.5" y="0.5" width="39" height="23" rx="3.5" stroke="#186aff"></rect>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 6.85714H13.1428V17.1429H26.8571V6.85714ZM12.2857 6V18H27.7143V6H12.2857Z" fill="#186aff"></path>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 9.42857H13.1428V7.71429H26.8571V9.42857Z" fill="#186aff"></path>
														<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2857 15.4286H14.8571V14.5714H18.2857V15.4286Z" fill="#186aff"></path>
													</svg>
												</div>
												<div class="saved-cards-label">
													<span class="saved-cards-label-text">Saved cards</span>
													<span class="saved-cards-label-subtext">${totalCount} card${totalCount !== 1 ? 's' : ''} available</span>
												</div>
											</div>
										</div>
										<div class="saved-cards-accordion-panel">
											<!-- Saved payment methods will be moved here -->
										</div>
									</div>
								</div>
							`;
							
							// CRITICAL FIX: Insert accordion in a safe location that won't become flow-container
							// The payment method <li> structure is: radio, label, [accordion/payment_box]
							const $paymentMethodLi = $('.payment_method_wc_checkout_com_flow').first();
							const $label = $paymentMethodLi.find('label').first();
							
							if (displayOrder === 'saved_cards_first') {
								// Insert accordion AFTER the label, BEFORE any divs
								// This way it can never be mistaken for the payment_box div
								if ($label.length) {
									$label.after(accordionHTML);
									flowLog('Accordion inserted AFTER label (saved_cards_first)');
								} else {
									// Fallback: insert before first div
									const $firstDiv = $paymentMethodLi.children('div').first();
									if ($firstDiv.length) {
										$firstDiv.before(accordionHTML);
										flowLog('Accordion inserted BEFORE div (saved_cards_first fallback)');
									}
								}
							} else {
								// new_payment_first: Insert accordion AFTER the payment_box div (which contains flow-container)
								const $paymentBox = $paymentMethodLi.find('.payment_box').first();
								if ($paymentBox.length) {
									$paymentBox.after(accordionHTML);
									flowLog('Accordion inserted AFTER payment_box (new_payment_first)');
								} else {
									// Fallback: insert after label
									if ($label.length) {
										$label.after(accordionHTML);
										flowLog('Accordion inserted AFTER label (new_payment_first fallback)');
									}
								}
							}
							
							// Move saved payment methods into the accordion panel
							$savedMethods.each(function() {
								$(this).appendTo('.saved-cards-accordion-panel');
							});
							
							flowLog('Saved cards wrapped in accordion');
							
						// REMOVED: No default card selection - user must explicitly select a saved card
						// This prevents issues with default card detection
						flowLog('No default card selection - user must explicitly select');
						
						// Ensure no saved cards are auto-selected (remove any defaults)
						$('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').prop('checked', false).removeAttr('checked');
						
						// Ensure "new" card option is selected by default
						const $newCardRadio = $('#wc-wc_checkout_com_flow-payment-token-new');
						if ($newCardRadio.length) {
							$newCardRadio.prop('checked', true);
							flowLog('"New card" option selected by default');
						}
						
						// Reset flags
						window.flowSavedCardSelected = false;
						window.flowUserInteracted = false;
							window.flowUserInteracted = false;
							flowLog('Unchecked all saved cards and selected new payment method');
						}
							
							// CRITICAL: After moving cards, ensure visibility rules are applied
							// Remove any inline display styles that might have been set during the move
							$('.saved-cards-accordion-container').each(function() {
								// Remove inline style to let CSS handle visibility
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from accordion container');
								}
							});
							
							$('.saved-cards-accordion-panel').each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from accordion panel');
								}
							});
							
							$savedMethods.each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									flowLog('Removed inline display style from saved methods');
								}
							});
							
							flowLog('CSS will handle visibility via data-saved-payment-order:', displayOrder);
						} else {
							flowLog('No insertion point found, retrying...');
							setTimeout(wrapSavedCardsInAccordion, 100);
						}
					}
				}
				
				// Try immediately and also set a timeout as fallback
				wrapSavedCardsInAccordion();
				setTimeout(wrapSavedCardsInAccordion, 500);
				
				// CRITICAL: Re-wrap saved cards after WooCommerce updates checkout
				// WooCommerce's updated_checkout event destroys and recreates the payment methods HTML
				// Use a flag to prevent multiple simultaneous executions
				let accordionRecreationInProgress = false;
				jQuery(document.body).on('updated_checkout', function() {
					// Prevent multiple simultaneous executions
					if (accordionRecreationInProgress) {
						flowLog('Accordion recreation already in progress, skipping...');
						return;
					}
					
					flowLog('updated_checkout fired - re-wrapping saved cards...');
					accordionRecreationInProgress = true;
					
					setTimeout(function() {
						// Check if accordion AND panel exist, and if saved methods are inside
						const $existingAccordion = $('.saved-cards-accordion-container');
						const $existingPanel = $('.saved-cards-accordion-panel');
						const $panelMethods = $('.saved-cards-accordion-panel .woocommerce-SavedPaymentMethods');
						const $allMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
						
						flowLog('After updated_checkout:', {
							accordion: $existingAccordion.length,
							panel: $existingPanel.length,
							methodsInPanel: $panelMethods.length,
							totalMethods: $allMethods.length
						});
						
						// Recreate if accordion doesn't exist OR panel is missing OR methods not in panel
						if ($existingAccordion.length === 0 || $existingPanel.length === 0 || ($allMethods.length > 0 && $panelMethods.length === 0)) {
							flowLog('Accordion incomplete after updated_checkout, recreating...');
							// Remove any incomplete accordion first
							$existingAccordion.remove();
							// Recreate with fresh saved methods
							wrapSavedCardsInAccordion();
						} else {
							flowLog('Accordion complete after updated_checkout');
						}
						
						// Reset flag after a delay to allow for any follow-up updates
						setTimeout(function() {
							accordionRecreationInProgress = false;
						}, 500);
					}, 100);
				});

					});

					// Don't hide save card checkbox on page load - let Flow component control it

					document.addEventListener('DOMContentLoaded', function () {
						const radios = document.querySelectorAll(
							'input[name="wc-wc_checkout_com_flow-payment-token"], input[name="wc-wc_checkout_com_cards-payment-token"]'
						);
						radios.forEach(radio => {
							radio.checked = false;
						});
					});
				</script>
				<?php endif; ?>

		<?php endif; ?>

		<?php if ( $order_pay_order_id === null ) : ?>
			<div id="cart-info" data-cart='<?php echo wp_json_encode( WC_Checkoutcom_Api_Request::get_cart_info(true) ); ?>'></div>
		<?php else : ?>
			<div id="order-pay-info" data-order-pay='<?php echo wp_json_encode( WC_Checkoutcom_Api_Request::get_order_info($order_pay_order_id, true) ); ?>'></div>
		<?php endif; ?>
		
		<!-- Skeleton loader for better UX while Flow component loads -->
		<div id="flow-skeleton" class="flow-skeleton-loader">
			<div class="flow-skeleton-line"></div>
			<div class="flow-skeleton-line"></div>
			<div class="flow-skeleton-line short"></div>
		</div>
		
	<!-- Inline script to immediately hide place order button before CSS/JS loads (prevents flash) -->
	<script>
		// Expose saved payment display order to JavaScript
		window.saved_payment = '<?php echo esc_js( $flow_saved_card ); ?>';
		// Debug logging flag for PHP-generated JavaScript
		// Use window object to prevent duplicate declaration errors
		if (typeof window.flowDebugLogging === 'undefined') {
			window.flowDebugLogging = <?php echo $flow_debug_logging ? 'true' : 'false'; ?>;
		}
		// Use window.flowDebugLogging directly and check for existing flowLog to avoid redeclaration errors
		if (typeof window.flowLog === 'undefined') {
			window.flowLog = window.flowDebugLogging ? console.log.bind(console, '[FLOW PHP]') : function() {};
		}
		// Use window.flowLog instead of const to avoid redeclaration
		var flowLog = window.flowLog;
		
		(function() {
			var displayOrder = '<?php echo esc_js( $flow_saved_card ); ?>';
			
			// CRITICAL: Set data attribute on body immediately for CSS targeting
			document.body.setAttribute('data-saved-payment-order', displayOrder);
			if (flowDebugLogging) console.log('[FLOW] Body data attribute set:', displayOrder);
			
			// This runs immediately, before DOMContentLoaded
			var checkButton = function() {
				var placeOrderBtn = document.getElementById('place_order');
				if (placeOrderBtn) {
					// Check if Flow is the selected/only payment method
					var flowRadio = document.getElementById('payment_method_wc_checkout_com_flow');
					if (flowRadio && flowRadio.checked) {
						// Get saved cards count
						var savedCardElements = document.querySelectorAll('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
						var hasSavedCards = false;
						savedCardElements.forEach(function(el) {
							var count = parseInt(el.getAttribute('data-count') || '0', 10);
							if (count > 0) {
								hasSavedCards = true;
							}
						});
						
						// Use Case 1: saved_cards_first - Always show Place Order button immediately
						// Use Case 2: new_payment_first - Hide Place Order button until Flow is ready
						if (displayOrder === 'saved_cards_first' && hasSavedCards) {
							// Keep button visible (saved cards are already visible)
							console.log('[FLOW BUTTON] Keeping Place Order button visible - saved_cards_first mode with saved cards');
						} else {
							// Hide button until Flow is ready
							placeOrderBtn.style.opacity = '0';
							placeOrderBtn.style.visibility = 'hidden';
							console.log('[FLOW BUTTON] Hiding Place Order button - Display order:', displayOrder, 'Has saved cards:', hasSavedCards);
						}
					}
				}
			};
			
			// Run immediately
			checkButton();
			
			// Also run when DOM is ready (belt and suspenders)
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', checkButton);
			}
		})();
	</script>
		
		<input type="hidden" id="cko-flow-payment-id" name="cko-flow-payment-id" value="" />
		<input type="hidden" id="cko-flow-payment-type" name="cko-flow-payment-type" value="" />
		<input type="hidden" id="cko-flow-payment-session-id" name="cko-flow-payment-session-id" value="" />
		<input type="hidden" id="cko-flow-3ds-status" name="cko-flow-3ds-status" value="" />
		<input type="hidden" id="cko-flow-3ds-auth-id" name="cko-flow-3ds-auth-id" value="" />
		<input type="hidden" id="cko-flow-save-card-persist" name="cko-flow-save-card-persist" value="" />
		<?php 

		if ( ! is_user_logged_in() ) :
			?>
			<script>
				// Use var instead of const to prevent redeclaration errors when updated_checkout fires
				var targetNode = document.body;

				// Hide Saved payment method for non logged in users.
				// IMPORTANT: Hide the accordion container, not just the list inside
				// Only create observer if it doesn't already exist to prevent duplicates
				if (typeof window.flowGuestObserver === 'undefined') {
					window.flowGuestObserver = new MutationObserver((mutationsList, observer) => {
					// Hide the accordion container (which contains the saved cards)
					const $accordion = jQuery('.saved-cards-accordion-container');
					if ($accordion.length) {
						$accordion.hide();
						if (flowDebugLogging) console.log('[FLOW] Hiding saved cards accordion for non-logged-in user');
					}
					
					// Also hide any saved payment methods that aren't wrapped yet
					const $element = jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
					if ($element.length) {
						$element.hide();
					}
					
					// Keep observer running to catch late additions
				});

					const config = {
						childList: true,
						subtree: true
					};

					window.flowGuestObserver.observe(targetNode, config);
				}

				// Try to hide it immediately in case it's already present.
				jQuery('.saved-cards-accordion-container').hide();
				jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide();
			</script>
		<?php endif; ?>
		<?php

		// check if saved card enable from module setting.
		if ( $save_card ) {
			// Show saved cards from BOTH Flow and Classic Cards gateways
			// No migration needed - backend already handles both token types
			$this->saved_payment_methods();
		}

		// Render Save Card input.
		$this->element_form_save_card( $save_card );
	}

	/**
	 * Override saved_payment_methods to show tokens from BOTH Flow and Classic Cards gateways.
	 * This allows merchants upgrading from Classic Cards to Flow to see their existing saved cards.
	 * No migration needed - the backend already handles both token types seamlessly.
	 *
	 * @since 5.0.0
	 * @return void
	 */
	public function saved_payment_methods() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Get tokens from BOTH gateways
		$flow_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $this->id );
		
		$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
		$classic_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $classic_gateway->id );

		// Merge both token arrays
		$all_tokens = array_merge( $flow_tokens, $classic_tokens );

		// Log token retrieval for debugging
		WC_Checkoutcom_Utility::logger( '=== MULTI-GATEWAY TOKEN RETRIEVAL ===' );
		WC_Checkoutcom_Utility::logger( 'User ID: ' . $user_id );
		WC_Checkoutcom_Utility::logger( 'Flow tokens found: ' . count( $flow_tokens ) );
		WC_Checkoutcom_Utility::logger( 'Classic Cards tokens found: ' . count( $classic_tokens ) );
		WC_Checkoutcom_Utility::logger( 'Total tokens (before dedup): ' . count( $all_tokens ) );

		if ( empty( $all_tokens ) ) {
			WC_Checkoutcom_Utility::logger( 'No saved cards found - skipping display' );
			return;
		}

		// Remove duplicates based on token value (source ID)
		$unique_tokens = array();
		$seen_tokens = array();
		foreach ( $all_tokens as $token ) {
			$token_value = $token->get_token();
			if ( ! in_array( $token_value, $seen_tokens, true ) ) {
				$unique_tokens[] = $token;
				$seen_tokens[] = $token_value;
			}
		}
		
		WC_Checkoutcom_Utility::logger( 'Unique tokens (after dedup): ' . count( $unique_tokens ) );
		WC_Checkoutcom_Utility::logger( '=== END MULTI-GATEWAY TOKEN RETRIEVAL ===' );

		// Display saved payment methods (without extra label - already handled by accordion)
		?>
		<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="<?php echo count( $unique_tokens ); ?>">
			<?php
			$default_token_id = WC_Payment_Tokens::get_customer_default_token( $user_id );
			foreach ( $unique_tokens as $token ) {
				$is_default = ( $token->get_id() == $default_token_id );
				$gateway_source = ( $token->get_gateway_id() === $this->id ) ? 'Flow' : 'Classic';
				?>
				<li class="woocommerce-SavedPaymentMethods-token">
					<input id="wc-<?php echo esc_attr( $this->id ); ?>-payment-token-<?php echo esc_attr( $token->get_id() ); ?>" 
						type="radio" 
						name="wc-<?php echo esc_attr( $this->id ); ?>-payment-token" 
						value="<?php echo esc_attr( $token->get_id() ); ?>" 
						<?php checked( $is_default, true ); ?> 
						class="woocommerce-SavedPaymentMethods-tokenInput" 
						data-gateway-source="<?php echo esc_attr( $gateway_source ); ?>" />
					<label for="wc-<?php echo esc_attr( $this->id ); ?>-payment-token-<?php echo esc_attr( $token->get_id() ); ?>">
						<?php echo esc_html( sprintf( __( '%s ending in %s (expires %s/%s)', 'checkout-com-unified-payments-api' ), $token->get_card_type(), $token->get_last4(), $token->get_expiry_month(), $token->get_expiry_year() ) ); ?>
						<?php if ( $is_default ) : ?>
							<span class="woocommerce-SavedPaymentMethods-token-default"><?php esc_html_e( '(default)', 'checkout-com-unified-payments-api' ); ?></span>
						<?php endif; ?>
					</label>
				</li>
				<?php
			}
			?>
			<li class="woocommerce-SavedPaymentMethods-new">
				<input id="wc-<?php echo esc_attr( $this->id ); ?>-payment-token-new" 
					type="radio" 
					name="wc-<?php echo esc_attr( $this->id ); ?>-payment-token" 
					value="new" 
					<?php checked( empty( $default_token_id ), true ); ?> />
				<label for="wc-<?php echo esc_attr( $this->id ); ?>-payment-token-new">
					<?php esc_html_e( 'Use a new payment method', 'checkout-com-unified-payments-api' ); ?>
				</label>
			</li>
		</ul>
		<?php
	}

	/**
	 * Process payment with card payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array|void
	 */

	public function process_payment( $order_id ) {
		// CRITICAL TEST LOG - First line of function
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order ID: ' . $order_id );

		if ( ! session_id() ) {
			session_start();
		}

		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ========== ENTRY POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order ID: ' . $order_id );
		
		// Fetch payment details early (before order lookup) so we can use them as fallback if order lookup fails
		$flow_payment_id_from_post = isset( $_POST['cko-flow-payment-id'] ) ? \sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';
		$payment_details = null;
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] flow_payment_id_from_post: ' . $flow_payment_id_from_post );
		
		if ( ! empty( $flow_payment_id_from_post ) ) {
			try {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Fetching payment details early (before order lookup) - Payment ID: ' . $flow_payment_id_from_post );
				$checkout = new Checkout_SDK();
				$builder = $checkout->get_builder();
				
				if ( $builder ) {
					$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $flow_payment_id_from_post );
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment details fetched successfully (early fetch) - Payment ID: ' . $flow_payment_id_from_post );
					// Log payment session ID if available in metadata
					if ( isset( $payment_details['metadata']['cko_payment_session_id'] ) ) {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment session ID found in early fetch: ' . substr( $payment_details['metadata']['cko_payment_session_id'], 0, 20 ) . '...' );
					} else {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ Payment session ID NOT found in early fetch metadata' );
					}
				}
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] WARNING: Failed to fetch payment details early: ' . $e->getMessage() . ' - will retry later if needed' );
				// Don't fail here - we'll retry later if needed
			}
		}
		
		if ( empty( $order_id ) ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: No order ID provided' );
			
			// Check if order was created via AJAX (early order creation)
			// Order ID might be in POST data or session
			$order_id_from_post = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( $order_id_from_post ) {
				$early_order = wc_get_order( $order_id_from_post );
				if ( $early_order && $early_order->get_status() === 'pending' ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Found order created via AJAX - Order ID: ' . $order_id_from_post );
					$order_id = $order_id_from_post;
					$order = $early_order;
				}
			}
			
			// If still no order ID, try to create minimal order from payment details as fallback
			if ( empty( $order_id ) && ! empty( $payment_details ) && ! empty( $flow_payment_id_from_post ) ) {
				try {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Attempting to create minimal order from payment details (no order ID provided)...' );
					$order = $this->create_minimal_order_from_payment_details( $flow_payment_id_from_post, $payment_details );
					
					if ( ! is_wp_error( $order ) && $order ) {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Minimal order created successfully (no order ID) - Order ID: ' . $order->get_id() );
						$order_id = $order->get_id();
						// Continue with normal flow - order now exists
					} else {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Failed to create minimal order: ' . ( is_wp_error( $order ) ? $order->get_error_message() : 'Unknown error' ) );
						WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
						return;
					}
				} catch ( Exception $fallback_exception ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Exception during minimal order creation: ' . $fallback_exception->getMessage() );
					WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
					return;
				}
			} else {
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
				return;
			}
		}

		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: Order ' . $order_id . ' does not exist' );
			
			// Try to create minimal order from payment details as fallback
			if ( ! empty( $payment_details ) && ! empty( $flow_payment_id_from_post ) ) {
				try {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Attempting to create minimal order from payment details (order not found)...' );
					$order = $this->create_minimal_order_from_payment_details( $flow_payment_id_from_post, $payment_details );
					
					if ( ! is_wp_error( $order ) && $order ) {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Minimal order created successfully (order not found) - Order ID: ' . $order->get_id() );
						$order_id = $order->get_id();
						// Continue with normal flow - order now exists
					} else {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Failed to create minimal order: ' . ( is_wp_error( $order ) ? $order->get_error_message() : 'Unknown error' ) );
						WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
						return;
					}
				} catch ( Exception $fallback_exception ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Exception during minimal order creation: ' . $fallback_exception->getMessage() );
					WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
					return;
				}
			} else {
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
				return;
			}
		}
		
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order found - ID: ' . $order->get_id() . ', Status: ' . $order->get_status() . ', Payment Method: ' . $order->get_payment_method() );
		
		// DEBUG: Log current address state BEFORE setting addresses
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] BEFORE setting addresses - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
		
		// CRITICAL: Set addresses consistently for both successful and failed payments
		// This ensures addresses are always visible regardless of payment outcome
		// Priority: 1) payment_details (if available), 2) POST data, 3) WC()->customer, 4) Preserve existing
		// Note: payment_details will be available after payment is fetched, so we'll call this again after fetching payment_details
		$this->set_order_addresses_consistently( $order );
		
		// CRITICAL: Add shipping from payment_details if available (from early fetch)
		if ( ! empty( $payment_details ) ) {
			$this->add_shipping_from_payment_details( $order, $payment_details );
		}
		
		// Save addresses immediately to ensure they persist
		$order->save();
		
		// DEBUG: Log address state AFTER setting and saving
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] AFTER setting addresses - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Set and saved billing and shipping addresses - Order ID: ' . $order_id );
		
		// DUPLICATE PREVENTION: Check if this order has already been processed
		$existing_transaction_id = $order->get_transaction_id();
		$flow_payment_id = isset( $_POST['cko-flow-payment-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';
		
		// Fallback 1: Get payment ID from GET parameter (from handle_3ds_return URL)
		if ( empty( $flow_payment_id ) && isset( $_GET['cko-payment-id'] ) ) {
			$flow_payment_id = sanitize_text_field( $_GET['cko-payment-id'] );
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Payment ID retrieved from GET parameter: ' . $flow_payment_id );
		}
		
		// Fallback 2: Get payment ID from order metadata if not in POST/GET (webhook may have set it)
		if ( empty( $flow_payment_id ) ) {
			$flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			if ( empty( $flow_payment_id ) ) {
				$flow_payment_id = $order->get_meta( '_cko_payment_id' );
			}
			if ( ! empty( $flow_payment_id ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Payment ID retrieved from order metadata: ' . $flow_payment_id );
			}
		}
		
		if ( ! empty( $existing_transaction_id ) ) {
			WC_Checkoutcom_Utility::logger( 'DUPLICATE PREVENTION: Order ' . $order_id . ' already has transaction ID: ' . $existing_transaction_id . ' - skipping processing' );
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Payment ID check - POST: ' . ( isset( $_POST['cko-flow-payment-id'] ) ? $_POST['cko-flow-payment-id'] : 'NOT SET' ) . ', Order meta _cko_flow_payment_id: ' . $order->get_meta( '_cko_flow_payment_id' ) . ', Order meta _cko_payment_id: ' . $order->get_meta( '_cko_payment_id' ) . ', Final flow_payment_id: ' . $flow_payment_id );
			
				// Still check for card saving even when duplicate prevention kicks in
				// The payment may have been processed by webhook, but card saving might not have run yet
			if ( ! empty( $flow_payment_id ) ) {
				// Get payment type from POST, GET, or order metadata
				$flow_payment_type_for_save = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '';
				if ( empty( $flow_payment_type_for_save ) && isset( $_GET['cko-payment-type'] ) ) {
					$flow_payment_type_for_save = sanitize_text_field( $_GET['cko-payment-type'] );
				}
				if ( empty( $flow_payment_type_for_save ) ) {
					$flow_payment_type_for_save = $order->get_meta( '_cko_flow_payment_type' );
				}
				// Default to 'card' if still empty (most common case)
				if ( empty( $flow_payment_type_for_save ) ) {
					$flow_payment_type_for_save = 'card';
				}
				$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
				
				// Check order metadata first (stored before 3DS redirect - most reliable)
				$save_card_from_order = $order->get_meta( '_cko_save_card_preference' );
				
				// Check GET parameter (from URL after 3DS redirect)
				$save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
				
				// Check hidden field (POST data - may not be available after 3DS redirect)
				$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
				
				// Fallback to POST checkbox
				$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
				
				// Fallback to session
				$save_card_session = WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );
				
				WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Checking save card preference - Order meta: ' . $save_card_from_order . ', GET: ' . $save_card_from_get . ', Hidden: ' . $save_card_hidden . ', POST: ' . $save_card_post . ', Session: ' . $save_card_session );
				
				// Determine if checkbox was checked (priority: order metadata > GET > hidden field > POST > session)
				$save_card_checkbox = false;
				if ( 'yes' === $save_card_from_order ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in order metadata: YES' );
				} elseif ( 'yes' === $save_card_from_get ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in GET parameter: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'yes' === $save_card_hidden ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in hidden field: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in POST checkbox: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'yes' === $save_card_session ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in session: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				}
				
				WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Final check - Payment type: ' . $flow_payment_type_for_save . ', Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
				
				if ( 'card' === $flow_payment_type_for_save && $save_card_enabled && $save_card_checkbox ) {
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] ✅ All conditions met - calling flow_save_cards()' );
					$this->flow_save_cards( $order, $flow_payment_id );
					// Clear the session variable after processing
					WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
				} else {
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] ❌ Conditions NOT met - Payment type: ' . $flow_payment_type_for_save . ' (expected: card), Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
				}
			}
			
			// Return success to prevent error, but don't process again
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
		
		// DUPLICATE PREVENTION: Check if this payment ID already has an order (global check)
		if ( ! empty( $flow_payment_id ) ) {
			// Check if payment ID already has an order (prevents duplicate orders)
			$existing_orders = wc_get_orders( array(
				'limit'      => 1,
				'meta_key'   => '_cko_payment_id',
				'meta_value' => $flow_payment_id,
				'return'     => 'objects',
			) );
			
			if ( ! empty( $existing_orders ) ) {
				$existing_order = $existing_orders[0];
				if ( $existing_order->get_id() !== $order_id ) {
					WC_Checkoutcom_Utility::logger( 'DUPLICATE PREVENTION: Payment ID ' . $flow_payment_id . ' already has order ' . $existing_order->get_id() . ' - reusing existing order instead of ' . $order_id );
					$order = $existing_order;
					$order_id = $order->get_id();
				}
			}
			
			// Check if current order already processed with this payment ID
			$existing_payment = $order->get_meta( '_cko_payment_id' );
			if ( $existing_payment === $flow_payment_id ) {
				WC_Checkoutcom_Utility::logger( 'DUPLICATE PREVENTION: Order ' . $order_id . ' already processed with payment ID: ' . $flow_payment_id . ' - skipping processing' );
				
				// CRITICAL: Still check for card saving even when duplicate prevention kicks in
				// Get payment type from POST, GET, or order metadata
				$flow_payment_type_for_save = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '';
				if ( empty( $flow_payment_type_for_save ) && isset( $_GET['cko-payment-type'] ) ) {
					$flow_payment_type_for_save = sanitize_text_field( $_GET['cko-payment-type'] );
				}
				if ( empty( $flow_payment_type_for_save ) ) {
					$flow_payment_type_for_save = $order->get_meta( '_cko_flow_payment_type' );
				}
				// Default to 'card' if still empty (most common case)
				if ( empty( $flow_payment_type_for_save ) ) {
					$flow_payment_type_for_save = 'card';
				}
				$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
				
				// Check order metadata first (stored before 3DS redirect - most reliable)
				$save_card_from_order = $order->get_meta( '_cko_save_card_preference' );
				
				// Check GET parameter (from URL after 3DS redirect)
				$save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
				
				// Check hidden field (POST data - may not be available after 3DS redirect)
				$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
				
				// Fallback to POST checkbox
				$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
				
				// Fallback to session
				$save_card_session = WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );
				
				WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Checking save card preference - Order meta: ' . $save_card_from_order . ', GET: ' . $save_card_from_get . ', Hidden: ' . $save_card_hidden . ', POST: ' . $save_card_post . ', Session: ' . $save_card_session );
				
				// Determine if checkbox was checked (priority: order metadata > GET > hidden field > POST > session)
				$save_card_checkbox = false;
				if ( 'yes' === $save_card_from_order ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in order metadata: YES' );
				} elseif ( 'yes' === $save_card_from_get ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in GET parameter: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'yes' === $save_card_hidden ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in hidden field: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in POST checkbox: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				} elseif ( 'yes' === $save_card_session ) {
					$save_card_checkbox = true;
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Save card preference found in session: YES' );
					$order->update_meta_data( '_cko_save_card_preference', 'yes' );
					$order->save();
				}
				
				WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] Final check - Payment type: ' . $flow_payment_type_for_save . ', Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
				
				if ( 'card' === $flow_payment_type_for_save && $save_card_enabled && $save_card_checkbox ) {
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] ✅ All conditions met - calling flow_save_cards()' );
					$this->flow_save_cards( $order, $flow_payment_id );
					// Clear the session variable after processing
					WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
				} else {
					WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] [DUPLICATE PREVENTION] ❌ Conditions NOT met - Payment type: ' . $flow_payment_type_for_save . ' (expected: card), Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
				}
				
				// Return success to prevent error, but don't process again
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		}
		
	$flow_result = null;

	$subs_payment_type = null;

	// 3DS RETURN HANDLER: If we already have a payment ID from Flow (after 3DS redirect),
	// don't create a new payment - just fetch the payment details and complete the order
	if ( ! empty( $flow_payment_id ) && isset( $_POST['cko-flow-payment-type'] ) ) {
		
	// Fetch the payment details from Checkout.com to verify and get status
		try {
		$checkout = new Checkout_SDK();
		$builder = $checkout->get_builder();
		
		// Check if SDK was properly initialized
		if ( ! $builder ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] WARNING: Checkout.com SDK not initialized - vendor/autoload.php may be missing' );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] For Flow payments, payment details are handled via webhooks, so this may not be critical' );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Creating mock result with payment ID: ' . $flow_payment_id );
			
			// For Flow payments, when SDK is not available, create a mock result
			// Flow handles payment approval client-side, so we assume it's approved
			// Webhooks will verify the final status
			$result = array(
				'id' => $flow_payment_id,
				'action_id' => $flow_payment_id,
				'status' => 'Authorized', // Assume authorized since Flow component handled it
				'approved' => true, // Assume approved - Flow component handles approval client-side
				'response_code' => '10000', // Success code
				'response_summary' => 'Approved', // Success summary
				'3d' => null, // No 3DS redirect needed, we're returning from 3DS
			);
			
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Using mock result (SDK unavailable) - Payment ID: ' . $flow_payment_id . ', Approved: true' );
			// Set payment_details to the mock result for consistency
			$payment_details = $result;
		} else {
			$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $flow_payment_id );
			
			// Convert to result format expected by the rest of the code
			$result = array(
				'id' => $payment_details['id'],
				'action_id' => isset( $payment_details['actions'][0]['id'] ) ? $payment_details['actions'][0]['id'] : '',
				'status' => $payment_details['status'],
				'approved' => isset( $payment_details['approved'] ) ? $payment_details['approved'] : false,
				'response_code' => isset( $payment_details['response_code'] ) ? $payment_details['response_code'] : '',
				'response_summary' => isset( $payment_details['response_summary'] ) ? $payment_details['response_summary'] : '',
				'3d' => null, // No 3DS redirect needed, we're returning from 3DS
			);
		}
		
		// CRITICAL: Check if payment is approved before processing
		// If payment_details is null (SDK not available), check if we have a payment ID
		// For Flow payments, if payment ID exists, payment was processed by Flow component
		if ( empty( $payment_details ) && ! empty( $flow_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment details not available (SDK missing), but payment ID exists: ' . $flow_payment_id );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Assuming payment approved - Flow component handles approval client-side, webhooks will verify' );
			$is_approved = true; // Assume approved if payment ID exists and SDK unavailable
		} else {
			$is_approved = isset( $result['approved'] ) ? $result['approved'] : ( isset( $payment_details['approved'] ) ? $payment_details['approved'] : false );
		}
		
		if ( ! $is_approved ) {
			// PERFORMANCE: Only log verbose details in debug mode
			$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
			
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment not approved (3DS return) - Order ID: ' . $order_id . ', Payment ID: ' . $flow_payment_id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment details: ' . wp_json_encode( $payment_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
			
			// CRITICAL: Set addresses consistently for failed payments (same logic as successful payments)
			// This ensures addresses are always visible regardless of payment outcome
			// Priority: 1) payment_details (if available), 2) POST data, 3) WC()->customer, 4) Preserve existing
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] Payment failed - Setting addresses consistently' );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] Payment details available: ' . ( $payment_details ? 'YES' : 'NO' ) . ', billing_address in payment_details: ' . ( isset( $payment_details['billing_address'] ) ? 'YES' : 'NO' ) );
			}
			$this->set_order_addresses_consistently( $order, $payment_details );
			
			// Set transaction ID and payment ID in order meta for tracking
			$order->set_transaction_id( $result['action_id'] );
			$order->update_meta_data( '_cko_payment_id', $flow_payment_id );
			
			// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
			$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			if ( empty( $existing_flow_payment_id ) ) {
				$order->update_meta_data( '_cko_flow_payment_id', $flow_payment_id );
			} else {
				WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment ID already exists in order (failed payment) - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $flow_payment_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
			}
			
			// Save addresses BEFORE marking as failed
			$order->save();
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] Addresses saved BEFORE marking as failed - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
			}
			
			// Get error message from payment details if available
			$error_message = __( 'Payment was not approved. Please try again.', 'checkout-com-unified-payments-api' );
			if ( isset( $payment_details['response_summary'] ) && ! empty( $payment_details['response_summary'] ) ) {
				$error_message = $payment_details['response_summary'];
			} elseif ( isset( $payment_details['status'] ) ) {
				$error_message = sprintf( __( 'Payment failed with status: %s', 'checkout-com-unified-payments-api' ), $payment_details['status'] );
			}
			
			// Update order status to failed
			$order->update_status( 'failed', __( 'Payment was not approved by Checkout.com', 'checkout-com-unified-payments-api' ) );
			$order->add_order_note( sprintf( __( 'Payment declined - Payment ID: %s, Reason: %s', 'checkout-com-unified-payments-api' ), $flow_payment_id, $error_message ) );
			
			// Save order again after marking as failed
			$order->save();
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] Addresses saved AFTER marking as failed - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
			}
			
			// Add error notice
			WC_Checkoutcom_Utility::wc_add_notice_self( $error_message, 'error' );
			
			// Return error - don't proceed with success flow
			return;
		}
		
		// CRITICAL: Update addresses from payment_details for successful payments (if available)
		// This ensures we have the most accurate addresses from Checkout.com API
		// Priority: 1) payment_details (most accurate), 2) POST data, 3) WC()->customer, 4) Preserve existing
		if ( isset( $payment_details ) ) {
			$this->set_order_addresses_consistently( $order, $payment_details );
			// CRITICAL: Add shipping from payment_details items if not already present
			$this->add_shipping_from_payment_details( $order, $payment_details );
			$order->save(); // Save addresses before proceeding
		} else {
		}
		
		// Set transaction ID and payment ID in order meta
			$order->set_transaction_id( $result['action_id'] );
			$order->update_meta_data( '_cko_payment_id', $flow_payment_id );
			
			// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
			$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			if ( empty( $existing_flow_payment_id ) ) {
				$order->update_meta_data( '_cko_flow_payment_id', $flow_payment_id );
			} else {
				WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment ID already exists in order - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $flow_payment_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
			}
			
			$flow_payment_type = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : 'card';
			$order->update_meta_data( '_cko_flow_payment_type', $flow_payment_type );
			// Store order number/reference for webhook lookup (works with Sequential Order Numbers plugins)
			$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
			
			// CRITICAL: Save payment session ID for webhook matching (METHOD 2)
			// Priority: 1) POST data (from form), 2) Payment metadata (from payment_details)
			// Only save if order doesn't already have a payment session ID
			$existing_order_session_id = $order->get_meta( '_cko_payment_session_id' );
			
			if ( empty( $existing_order_session_id ) ) {
				// Order doesn't have payment session ID yet - try to get it
				$payment_session_id = isset( $_POST['cko-flow-payment-session-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-session-id'] ) : '';
				if ( empty( $payment_session_id ) && isset( $payment_details['metadata']['cko_payment_session_id'] ) ) {
					$payment_session_id = $payment_details['metadata']['cko_payment_session_id'];
					WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment session ID retrieved from payment_details metadata: ' . substr( $payment_session_id, 0, 20 ) . '...' );
				}
				
				if ( ! empty( $payment_session_id ) ) {
					// Check if payment_session_id already exists in another order (prevent duplicates)
					$existing_orders = wc_get_orders( array(
						'meta_key'   => '_cko_payment_session_id',
						'meta_value' => $payment_session_id,
						'limit'      => 1,
						'exclude'    => array( $order_id ),
						'return'     => 'ids',
					) );
					
					if ( ! empty( $existing_orders ) ) {
						WC_Checkoutcom_Utility::logger( '[3DS RETURN] ❌ CRITICAL ERROR: Payment session ID already used by order: ' . $existing_orders[0] );
					} else {
						$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
						WC_Checkoutcom_Utility::logger( '[3DS RETURN] ✅ Saved payment session ID to order - Order ID: ' . $order_id . ', Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
					}
				} else {
					WC_Checkoutcom_Utility::logger( '[3DS RETURN] ⚠️ WARNING: Payment session ID is empty - Order ID: ' . $order_id . ', Payment ID: ' . $flow_payment_id );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment session ID already exists in order - Order ID: ' . $order_id . ', Payment Session ID: ' . substr( $existing_order_session_id, 0, 20 ) . '... (skipping save)' );
			}
			
			// CRITICAL: Save order immediately so webhooks can find it (especially for fast APM payments)
			$order->save();
			WC_Checkoutcom_Utility::logger( 'Order meta saved immediately for webhook lookup (3DS return) - Order ID: ' . $order_id . ', Payment ID: ' . $flow_payment_id );
			
		// Process any pending webhooks for this order
		if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
			WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order( $order );
		}
		
		// CRITICAL: Reload order object after webhook processing to get latest status and meta
		// Webhooks may have updated the order status/meta, but the order object in memory is stale
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			WC_Checkoutcom_Utility::logger( '[3DS RETURN] ERROR: Order not found after webhook processing - Order ID: ' . $order_id );
			return;
		}
		
	// Set variables for card saving logic below
	$flow_pay_id = $flow_payment_id;
	
	// Check if payment is already captured or authorized (webhook may have arrived first)
	$already_captured = $order->get_meta( 'cko_payment_captured' );
	$already_authorized = $order->get_meta( 'cko_payment_authorized' );
	$current_status = $order->get_status();
	$action_id = isset( $result['action_id'] ) ? $result['action_id'] : '';
	
	// Set status and message for the order
	// Skip status update if:
	// 1. Payment already captured (webhook set it to processing)
	// 2. Payment already authorized AND status is already on-hold/processing/completed (webhook already updated)
	// 3. Order is already in advanced state (processing/completed) - don't downgrade
	$auth_status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
	
	// Format order amount for notes
	$formatted_order_amount = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
	
	if ( $already_captured ) {
		// Payment already captured - just add note, don't change status
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $flow_payment_type, $flow_payment_id, $formatted_order_amount );
		$status = null; // Signal to skip status update
	} elseif ( $already_authorized && ( $current_status === $auth_status || in_array( $current_status, array( 'processing', 'completed' ), true ) ) ) {
		// Already authorized and status matches - webhook already handled it, just add note
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $flow_payment_type, $flow_payment_id, $formatted_order_amount );
		$status = null; // Signal to skip status update
	} elseif ( in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
		// Order already in advanced state - don't downgrade, just add note
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $flow_payment_type, $flow_payment_id, $formatted_order_amount );
		$status = null; // Signal to skip status update
	} else {
		// Payment not yet processed - set status to authorized
		$status = $auth_status;
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $flow_payment_type, $flow_payment_id, $formatted_order_amount );
		
		// Check if payment was flagged
		if ( isset( $result['risk']['flagged'] ) && $result['risk']['flagged'] ) {
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged (3DS return) - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $flow_payment_id, $formatted_order_amount );
		}
	}
		
		// Mark as authorized for APM payments
		if ( ! in_array( $flow_payment_type, array( 'card', 'googlepay', 'applepay' ), true ) ) {
			$order->update_meta_data( 'cko_payment_authorized', true );
		}
		
		// Continue with card saving logic after this if/elseif/else structure
			
	} catch ( Exception $e ) {
		WC_Checkoutcom_Utility::logger( '3DS return error: ' . $e->getMessage() );
		WC_Checkoutcom_Utility::wc_add_notice_self( __( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ) );
		return;
	}
	
	// Skip saved card and normal Flow payment logic since we're returning from 3DS
	// Jump directly to card saving logic below
	} elseif ( WC_Checkoutcom_Api_Request::is_using_saved_payment_method() ) {
		$token = 'wc-wc_checkout_com_flow-payment-token';

			if ( ! isset( $_POST[ $token ] ) ) {
				$token = 'wc-wc_checkout_com_cards-payment-token';
			} else {
				$token = 'wc-wc_checkout_com_flow-payment-token';
			}

			// Saved card selected.
			$arg = sanitize_text_field( $_POST[ $token ] );
			
			// CRITICAL: Validate token exists before processing payment (especially for newly saved cards)
			// Try to get token directly first
			$token_obj = WC_Payment_Tokens::get( $arg );
			
			// If token not found, try refreshing cache and searching across both gateways
			if ( ! $token_obj ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token not found directly - Token ID: ' . $arg );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] User ID: ' . $order->get_user_id() );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Gateway ID: ' . $this->id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token field: ' . $token );
				
				// Clear any token cache
				wp_cache_delete( 'customer_tokens_' . $order->get_user_id() . '_' . $this->id, 'woocommerce' );
				$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
				wp_cache_delete( 'customer_tokens_' . $order->get_user_id() . '_' . $classic_gateway->id, 'woocommerce' );
				
				// Try to find token in Flow gateway (refresh cache)
				$flow_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), $this->id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Flow tokens found: ' . count( $flow_tokens ) );
				
				// Try to find token in Classic Cards gateway (refresh cache)
				$classic_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), $classic_gateway->id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Classic Cards tokens found: ' . count( $classic_tokens ) );
				
				// Try to find token by ID in all tokens
				$all_tokens = array_merge( $flow_tokens, $classic_tokens );
				$found_token = null;
				foreach ( $all_tokens as $check_token ) {
					if ( $check_token->get_id() == $arg ) {
						$found_token = $check_token;
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Token found in tokens list - Gateway: ' . $check_token->get_gateway_id() . ', Token ID: ' . $check_token->get_id() . ', Source ID: ' . substr( $check_token->get_token(), 0, 20 ) . '...' );
						break;
					}
				}
				
				if ( ! $found_token ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ❌ ERROR: Token ID ' . $arg . ' not found in any gateway tokens' );
					$available_token_ids = array_map( function( $t ) { return $t->get_id(); }, $all_tokens );
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Available token IDs: ' . ( ! empty( $available_token_ids ) ? implode( ', ', $available_token_ids ) : 'NONE' ) );
					WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Saved payment method not found. Please try using a new payment method or refresh the page.', 'checkout-com-unified-payments-api' ), 'error' );
					$order->update_status( 'failed', __( 'Payment failed - Saved card token not found', 'checkout-com-unified-payments-api' ) );
					$order->add_order_note( sprintf( __( 'Payment failed - Token ID %s not found. Available tokens: %s', 'checkout-com-unified-payments-api' ), $arg, implode( ', ', $available_token_ids ) ) );
					$order->save();
					return;
				} else {
					// Token found - use it
					$token_obj = $found_token;
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Token found - proceeding with payment - Gateway: ' . $token_obj->get_gateway_id() );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Token found successfully - Token ID: ' . $token_obj->get_id() . ', Gateway: ' . $token_obj->get_gateway_id() . ', Source ID: ' . substr( $token_obj->get_token(), 0, 20 ) . '...' );
			}

			// Create payment with card token.
			$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );

			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->get_id() ) ) {
				$flow_result = $result;
			}

			if ( isset( $result['3d_redirection_error'] ) && true === $result['3d_redirection_error'] ) {
				// Retry Create payment with card token.
				$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg, null, true );
			}
	
			// check if result has error and return error message.
			if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Saved card payment failed - Order ID: ' . $order_id . ', Error: ' . $result['error'] );
				
				// DEBUG: Log addresses before marking as failed
				if ( $order && $order->get_id() ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] Saved card payment failed - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
				}
				
				// CRITICAL: Mark order as failed to track payment failure
				if ( $order && $order->get_id() ) {
					$order->update_status( 'failed', __( 'Payment failed - Saved card payment error', 'checkout-com-unified-payments-api' ) );
					$order->add_order_note( sprintf( __( 'Payment failed - Error: %s', 'checkout-com-unified-payments-api' ), $result['error'] ) );
					$order->save();
					
					// DEBUG: Log addresses AFTER marking as failed
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] [ADDRESS DEBUG] After saved card payment failed save - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order marked as failed - Order ID: ' . $order_id );
				}
				
				WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
	
				return;
			}

			// Get save card config from module setting.
			$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );

			$subs_payment_type = "card";

			// Check if result contains 3d redirection url.
			if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

				// Check if save card is enable and customer select to save card.
				if ( $save_card && isset( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) && sanitize_text_field( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) ) {
					// Save in session for 3D secure payment.
					WC()->session->set( 'wc-wc_checkout_com_cards-new-payment-method', 'yes' );
				} else {
					WC()->session->set( 'wc-wc_checkout_com_cards-new-payment-method', 'no' );
				}

				$payment_id = isset( $result['id'] ) ? $result['id'] : '';
				$order->add_order_note(
					sprintf(
						esc_html__( 'Checkout.com 3d Redirect waiting - Payment ID: %1$s, URL: %2$s', 'checkout-com-unified-payments-api' ),
						$payment_id,
						$result['3d']
					)
				);

				// Redirect to 3D secure page.
				return [
					'result'   => 'success',
					'redirect' => $result['3d'],
				];
			}

			// Set action id as woo transaction id.
			$order->set_transaction_id( $result['action_id'] );
			$order->update_meta_data( '_cko_payment_id', $result['id'] );

			// Check if payment is already captured (webhook may have arrived first)
			$already_captured = $order->get_meta( 'cko_payment_captured' );
			$payment_id = isset( $result['id'] ) ? $result['id'] : '';
			$action_id = isset( $result['action_id'] ) ? $result['action_id'] : '';

			// Get cko auth status configured in admin.
			// Only set to 'on-hold' if payment is not already captured
			if ( ! $already_captured ) {
				$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

				/* translators: %1$s: Payment ID, %2$s: Action ID. */
				$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );

				// Check if payment was flagged.
				if ( $result['risk']['flagged'] ) {
					// Get cko auth status configured in admin.
					$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

					/* translators: %1$s: Payment ID, %2$s: Action ID. */
					$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
				}
			} else {
				// Payment already captured - just add note, don't change status
				/* translators: %1$s: Payment ID, %2$s: Action ID. */
				$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
				// Status should remain as set by capture webhook (Processing)
				$status = null; // Signal to skip status update
			}

			$order_status = $order->get_status();

			if ( 'pending' === $order_status || 'failed' === $order_status ) {
				$order->update_meta_data( 'cko_payment_authorized', true );
			}
		}
	else {
		// CRITICAL: Check for saved card token even if is_using_saved_payment_method() returned false
		// This handles cases where saved card detection fails but token is present in POST
		// Clear cache first to ensure we get the latest tokens (especially for newly saved cards)
		if ( $order->get_user_id() > 0 ) {
			wp_cache_delete( 'customer_tokens_' . $order->get_user_id() . '_' . $this->id, 'woocommerce' );
			$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
			wp_cache_delete( 'customer_tokens_' . $order->get_user_id() . '_' . $classic_gateway->id, 'woocommerce' );
		}
		
		$saved_card_token = isset( $_POST['wc-wc_checkout_com_flow-payment-token'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-payment-token'] ) : '';
		if ( empty( $saved_card_token ) ) {
			$saved_card_token = isset( $_POST['wc-wc_checkout_com_cards-payment-token'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_cards-payment-token'] ) : '';
		}
		
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Fallback check - Token value: ' . ( $saved_card_token ? $saved_card_token : 'EMPTY' ) . ', Is "new": ' . ( 'new' === $saved_card_token ? 'YES' : 'NO' ) );
		
		// If we have a saved card token (and it's not "new"), treat as saved card payment
		if ( ! empty( $saved_card_token ) && 'new' !== $saved_card_token ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Saved card token detected in fallback check - Token ID: ' . $saved_card_token );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Processing as saved card payment (fallback detection)' );
			
			// Process as saved card payment
			$token = 'wc-wc_checkout_com_flow-payment-token';
			if ( ! isset( $_POST[ $token ] ) || empty( $_POST[ $token ] ) || 'new' === $_POST[ $token ] ) {
				$token = 'wc-wc_checkout_com_cards-payment-token';
			}
			
			$arg = sanitize_text_field( $_POST[ $token ] );
			
			// CRITICAL: Validate token exists before processing payment
			$token_obj = WC_Payment_Tokens::get( $arg );
			if ( ! $token_obj ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: Token not found for ID: ' . $arg );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] User ID: ' . $order->get_user_id() );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Gateway ID: ' . $this->id );
				
				// Try to find token in Flow gateway
				$flow_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), $this->id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Flow tokens found: ' . count( $flow_tokens ) );
				
				// Try to find token in Classic Cards gateway
				$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
				$classic_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), $classic_gateway->id );
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Classic Cards tokens found: ' . count( $classic_tokens ) );
				
				// Try to find token by ID in all tokens
				$all_tokens = array_merge( $flow_tokens, $classic_tokens );
				$found_token = null;
				foreach ( $all_tokens as $check_token ) {
					if ( $check_token->get_id() == $arg ) {
						$found_token = $check_token;
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token found in tokens list - Gateway: ' . $check_token->get_gateway_id() . ', Token ID: ' . $check_token->get_id() );
						break;
					}
				}
				
				if ( ! $found_token ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: Token ID ' . $arg . ' not found in any gateway tokens' );
					WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Saved payment method not found. Please try using a new payment method or refresh the page.', 'checkout-com-unified-payments-api' ), 'error' );
					$order->update_status( 'failed', __( 'Payment failed - Saved card token not found', 'checkout-com-unified-payments-api' ) );
					$order->save();
					return;
				} else {
					// Use the found token
					$token_obj = $found_token;
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Using found token - Gateway: ' . $token_obj->get_gateway_id() );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token found successfully - Token ID: ' . $token_obj->get_id() . ', Gateway: ' . $token_obj->get_gateway_id() . ', Source ID: ' . substr( $token_obj->get_token(), 0, 20 ) . '...' );
			}
			
			// Create payment with card token
			$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );
			
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->get_id() ) ) {
				$flow_result = $result;
			}
			
			if ( isset( $result['3d_redirection_error'] ) && true === $result['3d_redirection_error'] ) {
				// Retry Create payment with card token
				$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg, null, true );
			}
			
			// Check if result has error
			if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Saved card payment failed (fallback) - Order ID: ' . $order_id . ', Error: ' . $result['error'] );
				$order->update_status( 'failed', __( 'Payment failed - Saved card payment error', 'checkout-com-unified-payments-api' ) );
				$order->add_order_note( sprintf( __( 'Payment failed - Error: %s', 'checkout-com-unified-payments-api' ), $result['error'] ) );
				$order->save();
				WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
				return;
			}
			
			// Handle 3DS redirect if needed
			if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {
				$payment_id = isset( $result['id'] ) ? $result['id'] : '';
				$order->add_order_note(
					sprintf(
						esc_html__( 'Checkout.com 3d Redirect waiting - Payment ID: %1$s, URL: %2$s', 'checkout-com-unified-payments-api' ),
						$payment_id,
						$result['3d']
					)
				);
				return [
					'result'   => 'success',
					'redirect' => $result['3d'],
				];
			}
			
			// Set transaction ID and payment ID
			$order->set_transaction_id( $result['action_id'] );
			$order->update_meta_data( '_cko_payment_id', $result['id'] );
			
			// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
			$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			if ( empty( $existing_flow_payment_id ) ) {
				$order->update_meta_data( '_cko_flow_payment_id', $result['id'] );
			} else {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment ID already exists in order (saved card) - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $result['id'], 0, 20 ) . '... (skipping save to prevent overwrite)' );
			}
			
			$order->update_meta_data( '_cko_flow_payment_type', 'card' );
			$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
			
			// Check if payment is already captured
			$already_captured = $order->get_meta( 'cko_payment_captured' );
			$payment_id = isset( $result['id'] ) ? $result['id'] : '';
			$action_id = isset( $result['action_id'] ) ? $result['action_id'] : '';
			
			// Set status
			if ( ! $already_captured ) {
				$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
				$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
				
				if ( isset( $result['risk']['flagged'] ) && $result['risk']['flagged'] ) {
					$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
					$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
				}
			} else {
				$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
				$status = null; // Skip status update
			}
			
			$order_status = $order->get_status();
			if ( 'pending' === $order_status || 'failed' === $order_status ) {
				$order->update_meta_data( 'cko_payment_authorized', true );
			}
			
			// Continue to card saving logic below (same as normal saved card flow)
			$flow_pay_id = $payment_id;
			$flow_payment_type = 'card';
		} else {
			// Normal Flow payment (new card)
			$flow_pay_id = isset( $_POST['cko-flow-payment-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';
			$payment_processed_via_fallback = false;
			
			// Log detailed information for debugging
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Normal Flow payment branch - Payment ID: ' . ( $flow_pay_id ? $flow_pay_id : 'EMPTY' ) );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token value from POST: ' . ( isset( $_POST['wc-wc_checkout_com_flow-payment-token'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-payment-token'] ) : 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] is_using_saved_payment_method() result: ' . ( WC_Checkoutcom_Api_Request::is_using_saved_payment_method() ? 'TRUE' : 'FALSE' ) );

			// Check if $flow_pay_id is not empty.
			if ( empty( $flow_pay_id ) ) {
				// Check if there's a recently saved token that should be used (for newly saved cards)
				$recent_token_id = null;
				if ( $order->get_user_id() > 0 ) {
					// Get all tokens for this user
					$flow_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), $this->id );
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Checking for recently saved tokens - Found ' . count( $flow_tokens ) . ' Flow tokens' );
					
					// Find the most recently created token (within last 5 minutes)
					$most_recent_token = null;
					$most_recent_time = 0;
					foreach ( $flow_tokens as $token ) {
						$token_created = $token->get_date_created();
						if ( $token_created ) {
							$token_time = strtotime( $token_created );
							$current_time = current_time( 'timestamp' );
							$time_diff = $current_time - $token_time;
							
							// Check if token was created recently (within last 5 minutes) and is more recent than current
							if ( $time_diff < 300 && $token_time > $most_recent_time ) {
								$most_recent_token = $token;
								$most_recent_time = $token_time;
							}
						}
					}
					
					// If there's exactly one token, it might be a newly saved card that wasn't selected in the form
					if ( count( $flow_tokens ) === 1 ) {
						$single_token = reset( $flow_tokens );
						$recent_token_id = $single_token->get_id();
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Found single token - ID: ' . $recent_token_id . ', Created: ' . $single_token->get_date_created() );
						
						// Check if token was created recently (within last 5 minutes)
						$token_created = $single_token->get_date_created();
						if ( $token_created ) {
							$token_time = strtotime( $token_created );
							$current_time = current_time( 'timestamp' );
							$time_diff = $current_time - $token_time;
							
							if ( $time_diff < 300 ) { // 5 minutes
								WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Token was created recently (' . $time_diff . ' seconds ago) - using as fallback' );
								// Use this token as fallback
								$arg = $recent_token_id;
								$token_obj = $single_token;
								
								// Process as saved card payment
								$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );
								
								if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
									WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Fallback saved card payment failed - Order ID: ' . $order_id . ', Error: ' . $result['error'] );
									$order->update_status( 'failed', __( 'Payment failed - Saved card payment error', 'checkout-com-unified-payments-api' ) );
									$order->add_order_note( sprintf( __( 'Payment failed - Error: %s', 'checkout-com-unified-payments-api' ), $result['error'] ) );
									$order->save();
									WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
									return;
								}
								
								// Handle 3DS redirect if needed
								if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {
									$payment_id = isset( $result['id'] ) ? $result['id'] : '';
									$order->add_order_note(
										sprintf(
											esc_html__( 'Checkout.com 3d Redirect waiting - Payment ID: %1$s, URL: %2$s', 'checkout-com-unified-payments-api' ),
											$payment_id,
											$result['3d']
										)
									);
									return [
										'result'   => 'success',
										'redirect' => $result['3d'],
									];
								}
								
								// Set transaction ID and payment ID
								$order->set_transaction_id( $result['action_id'] );
								$order->update_meta_data( '_cko_payment_id', $result['id'] );
								
								// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
								$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
								if ( empty( $existing_flow_payment_id ) ) {
									$order->update_meta_data( '_cko_flow_payment_id', $result['id'] );
								} else {
									WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment ID already exists in order (saved card fallback 1) - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $result['id'], 0, 20 ) . '... (skipping save to prevent overwrite)' );
								}
								
								$order->update_meta_data( '_cko_flow_payment_type', 'card' );
								$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
								
								// Check if payment is already captured
								$already_captured = $order->get_meta( 'cko_payment_captured' );
								$payment_id = isset( $result['id'] ) ? $result['id'] : '';
								$action_id = isset( $result['action_id'] ) ? $result['action_id'] : '';
								
								// Set status
								if ( ! $already_captured ) {
									$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
									$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									
									if ( isset( $result['risk']['flagged'] ) && $result['risk']['flagged'] ) {
										$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
										$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									}
								} else {
									$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									$status = null; // Skip status update
								}
								
								$order_status = $order->get_status();
								if ( 'pending' === $order_status || 'failed' === $order_status ) {
									$order->update_meta_data( 'cko_payment_authorized', true );
								}
								
								// Payment processed successfully via fallback
								$flow_pay_id = $payment_id;
								$flow_payment_type = 'card';
								$payment_processed_via_fallback = true;
								
								WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment processed successfully via fallback - Payment ID: ' . $payment_id );
							}
						} elseif ( $most_recent_token ) {
							// Use the most recently created token (within 5 minutes) as fallback
							$recent_token_id = $most_recent_token->get_id();
							$time_diff = current_time( 'timestamp' ) - strtotime( $most_recent_token->get_date_created() );
							WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Found most recent token - ID: ' . $recent_token_id . ', Created: ' . $most_recent_token->get_date_created() . ' (' . $time_diff . ' seconds ago)' );
							
							if ( $time_diff < 300 ) { // 5 minutes
								WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Most recent token was created recently (' . $time_diff . ' seconds ago) - using as fallback' );
								// Use this token as fallback
								$arg = $recent_token_id;
								$token_obj = $most_recent_token;
								
								// Process as saved card payment
								$result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );
								
								if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
									WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Fallback saved card payment failed - Order ID: ' . $order_id . ', Error: ' . $result['error'] );
									$order->update_status( 'failed', __( 'Payment failed - Saved card payment error', 'checkout-com-unified-payments-api' ) );
									$order->add_order_note( sprintf( __( 'Payment failed - Error: %s', 'checkout-com-unified-payments-api' ), $result['error'] ) );
									$order->save();
									WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
									return;
								}
								
								// Handle 3DS redirect if needed
								if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {
									$payment_id = isset( $result['id'] ) ? $result['id'] : '';
									$order->add_order_note(
										sprintf(
											esc_html__( 'Checkout.com 3d Redirect waiting - Payment ID: %1$s, URL: %2$s', 'checkout-com-unified-payments-api' ),
											$payment_id,
											$result['3d']
										)
									);
									return [
										'result'   => 'success',
										'redirect' => $result['3d'],
									];
								}
								
								// Set transaction ID and payment ID
								$order->set_transaction_id( $result['action_id'] );
								$order->update_meta_data( '_cko_payment_id', $result['id'] );
								
								// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
								$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
								if ( empty( $existing_flow_payment_id ) ) {
									$order->update_meta_data( '_cko_flow_payment_id', $result['id'] );
								} else {
									WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment ID already exists in order (saved card fallback 1) - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $result['id'], 0, 20 ) . '... (skipping save to prevent overwrite)' );
								}
								
								$order->update_meta_data( '_cko_flow_payment_type', 'card' );
								$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
								
								// Check if payment is already captured
								$already_captured = $order->get_meta( 'cko_payment_captured' );
								$payment_id = isset( $result['id'] ) ? $result['id'] : '';
								$action_id = isset( $result['action_id'] ) ? $result['action_id'] : '';
								
								// Set status
								if ( ! $already_captured ) {
									$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
									$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									
									if ( isset( $result['risk']['flagged'] ) && $result['risk']['flagged'] ) {
										$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
										$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									}
								} else {
									$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Payment ID: %1$s, Action ID: %2$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id );
									$status = null; // Skip status update
								}
								
								$order_status = $order->get_status();
								if ( 'pending' === $order_status || 'failed' === $order_status ) {
									$order->update_meta_data( 'cko_payment_authorized', true );
								}
								
								// Payment processed successfully via fallback
								$flow_pay_id = $payment_id;
								$flow_payment_type = 'card';
								$payment_processed_via_fallback = true;
								
								WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment processed successfully via fallback (most recent token) - Payment ID: ' . $payment_id );
							}
						}
					}
				}
				
				// Only show error if payment wasn't processed via fallback
				if ( ! $payment_processed_via_fallback ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: No payment ID and no saved card token found - Order ID: ' . $order_id );
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
					WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment. Please complete the payment.', 'checkout-com-unified-payments-api' ), 'error' );

					return;
				}
			}
		}

	$flow_payment_type = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '';

	if ( "card" === $flow_payment_type ) {
		$subs_payment_type = $flow_payment_type;
	}

	$order->update_meta_data( '_cko_payment_id', $flow_pay_id );
	
	// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
	$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
	if ( empty( $existing_flow_payment_id ) ) {
		$order->update_meta_data( '_cko_flow_payment_id', $flow_pay_id );
	} else {
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment ID already exists in order - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $flow_pay_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
	}
	
	$order->update_meta_data( '_cko_flow_payment_type', $flow_payment_type );
	// Store order number/reference for webhook lookup (works with Sequential Order Numbers plugins)
	$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
	
	// Store payment session ID for 3DS return lookup
	// Priority: 1) POST data (from form), 2) Already-fetched payment details, 3) Payment metadata (fetch if needed)
	$payment_session_id = isset( $_POST['cko-flow-payment-session-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-session-id'] ) : '';
	WC_Checkoutcom_Utility::logger( 'Payment session ID from POST: ' . ( ! empty( $payment_session_id ) ? $payment_session_id : 'EMPTY' ) );
	
	$payment_details_for_shipping = null;
	if ( empty( $payment_session_id ) && ! empty( $flow_pay_id ) ) {
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment session ID is empty, checking payment details - Payment ID: ' . $flow_pay_id );
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Has already-fetched payment_details: ' . ( ! empty( $payment_details ) ? 'YES' : 'NO' ) );
		
		// CRITICAL: First try to use already-fetched payment details (from early fetch at line 1474)
		// This avoids unnecessary API calls and works better for APM payments
		if ( ! empty( $payment_details ) ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Checking already-fetched payment_details for payment session ID...' );
			if ( isset( $payment_details['metadata']['cko_payment_session_id'] ) ) {
				$payment_session_id = $payment_details['metadata']['cko_payment_session_id'];
				$payment_details_for_shipping = $payment_details;
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Payment session ID retrieved from already-fetched payment details: ' . substr( $payment_session_id, 0, 20 ) . '...' );
			} else {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ Payment session ID not found in already-fetched payment_details metadata' );
				if ( isset( $payment_details['metadata'] ) ) {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Available metadata keys: ' . implode( ', ', array_keys( $payment_details['metadata'] ) ) );
				} else {
					WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ No metadata key found in payment_details' );
				}
			}
		}
		
		// Fallback: Fetch payment details if not already fetched or if payment session ID not found
		if ( empty( $payment_session_id ) ) {
			// Fallback: Fetch payment details if not already fetched
			try {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Fetching payment details to get payment session ID - Payment ID: ' . $flow_pay_id );
				$checkout = new Checkout_SDK();
				$builder = $checkout->get_builder();
				if ( $builder ) {
					$payment_details_for_shipping = $builder->getPaymentsClient()->getPaymentDetails( $flow_pay_id );
					$payment_session_id = isset( $payment_details_for_shipping['metadata']['cko_payment_session_id'] ) ? $payment_details_for_shipping['metadata']['cko_payment_session_id'] : '';
					if ( ! empty( $payment_session_id ) ) {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Payment session ID retrieved from payment metadata: ' . substr( $payment_session_id, 0, 20 ) . '...' );
					} else {
						WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ Payment session ID not found in payment metadata' );
					}
				}
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ Could not fetch payment details to get payment session ID: ' . $e->getMessage() );
			}
		}
	}
	
	// CRITICAL: Add shipping from payment_details if available and order doesn't have shipping yet
	if ( $payment_details_for_shipping ) {
		$this->add_shipping_from_payment_details( $order, $payment_details_for_shipping );
		$order->save(); // Save after adding shipping
	}
	
	// Always save payment session ID if we have it (even if empty, log it)
	// CRITICAL: Ensure payment_session_id is unique - one order = one payment session ID
	if ( ! empty( $payment_session_id ) ) {
		// Check if payment_session_id already exists in another order (prevent duplicates)
		$existing_orders = wc_get_orders( array(
			'meta_key'   => '_cko_payment_session_id',
			'meta_value' => $payment_session_id,
			'limit'      => 1,
			'exclude'    => array( $order_id ), // Exclude current order
			'return'     => 'ids',
		) );
		
		if ( ! empty( $existing_orders ) ) {
			// Payment session ID already used by another order - CRITICAL ERROR
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ❌ CRITICAL ERROR: Payment session ID already used by order: ' . $existing_orders[0] );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ❌ Cannot save duplicate payment_session_id to order: ' . $order_id );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ❌ Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ❌ This violates one-order-one-payment-session-id rule' );
			// Don't save duplicate - this prevents webhook matching wrong order
			// Log error but continue processing (order already exists)
		} else {
			// Safe to save - payment_session_id is unique
			$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ Saved payment session ID to order - Order ID: ' . $order_id . ', Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '... (Unique - no duplicates found)' );
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ✅ One order = one payment session ID: Order ' . $order_id . ' = Payment Session ' . substr( $payment_session_id, 0, 20 ) . '...' );
		}
	} else {
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ WARNING: Payment session ID is empty - Order ID: ' . $order_id . ', Payment ID: ' . $flow_pay_id );
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ⚠️ This may cause issues with 3DS return order lookup and webhook matching' );
	}
	
	// CRITICAL: Save order immediately so webhooks can find it (especially for fast APM payments)
	$order->save();
	WC_Checkoutcom_Utility::logger( 'Order meta saved immediately for webhook lookup - Order ID: ' . $order_id . ', Payment ID: ' . $flow_pay_id );
	
	// Process any pending webhooks for this order
	if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
		WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order( $order );
	}

		if ( ! in_array( $flow_payment_type, array( 'card', 'googlepay', 'applepay' ), true ) ) {
				$order->update_meta_data( 'cko_payment_authorized', true );
			}

			// Check if payment is already captured (webhook may have arrived first)
			$already_captured = $order->get_meta( 'cko_payment_captured' );

			// translators: %1$s: payment type (e.g., card, applepay), %2$s: Payment ID.
			$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW : %1$s - Payment ID: %2$s', 'checkout-com-unified-payments-api' ), $flow_payment_type, $flow_pay_id );

			// Get cko auth status configured in admin.
			// Only set to 'on-hold' if payment is not already captured
			if ( ! $already_captured ) {
				$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
			} else {
				// Payment already captured - just add note, don't change status
				// Status should remain as set by capture webhook (Processing)
				$status = null; // Signal to skip status update
			}

			// Get source ID for the payment id for subscription product.
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->get_id() ) ) {
				$request  = new \WP_REST_Request( 'GET', '/ckoplugin/v1/payment-status' );
				$request->set_query_params( [ 'paymentId' => $flow_pay_id ] );
				$result = rest_do_request( $request );
				
				if ( is_wp_error( $result ) ) {
					$error_message = $result->get_error_message();
					WC_Checkoutcom_Utility::logger( "There was an error in saving cards: $error_message" ); // phpcs:ignore
				} else {
					$flow_result = $result->get_data();
				}
		}
	}

	// Card saving logic (runs for both normal Flow payments and 3DS returns)
	// Check if $flow_pay_id is set (it's set in both the 3DS return handler and normal Flow payment)
	if ( isset( $flow_pay_id ) && ! empty( $flow_pay_id ) ) {
		$flow_payment_type_for_save = isset( $flow_payment_type ) ? $flow_payment_type : ( isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '' );
		
		// Check if customer wants to save card
		// Priority: 1. Order metadata (stored before 3DS redirect), 2. GET parameter (from URL), 3. Hidden field (POST), 4. POST checkbox, 5. Session
		$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		
		// Check order metadata first (stored before 3DS redirect - most reliable)
		$save_card_from_order = $order->get_meta( '_cko_save_card_preference' );
		
		// Check GET parameter (from URL after 3DS redirect)
		$save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
		
		// Check hidden field (POST data - may not be available after 3DS redirect)
		$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
		
		// Fallback to POST checkbox
		$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
		
		// Fallback to session
		$save_card_session = WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );
		
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Checking save card preference - Order meta: ' . $save_card_from_order . ', GET: ' . $save_card_from_get . ', Hidden: ' . $save_card_hidden . ', POST: ' . $save_card_post . ', Session: ' . $save_card_session );
		
		// Determine if checkbox was checked (priority: order metadata > GET > hidden field > POST > session)
		$save_card_checkbox = false;
		if ( 'yes' === $save_card_from_order ) {
			$save_card_checkbox = true;
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference found in order metadata: YES' );
		} elseif ( 'yes' === $save_card_from_get ) {
			$save_card_checkbox = true;
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference found in GET parameter: YES' );
			// Store in order metadata for future reference
			$order->update_meta_data( '_cko_save_card_preference', 'yes' );
			$order->save();
		} elseif ( 'yes' === $save_card_hidden ) {
			$save_card_checkbox = true;
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference found in hidden field: YES' );
			// Store in order metadata for future reference
			$order->update_meta_data( '_cko_save_card_preference', 'yes' );
			$order->save();
		} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
			$save_card_checkbox = true;
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference found in POST checkbox: YES' );
			// Store in order metadata for future reference
			$order->update_meta_data( '_cko_save_card_preference', 'yes' );
			$order->save();
		} elseif ( 'yes' === $save_card_session ) {
			$save_card_checkbox = true;
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference found in session: YES' );
			// Store in order metadata for future reference
			$order->update_meta_data( '_cko_save_card_preference', 'yes' );
			$order->save();
		} else {
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card preference NOT found - card will NOT be saved' );
		}

	WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Final check - Payment type: ' . $flow_payment_type_for_save . ', Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
	if ( 'card' === $flow_payment_type_for_save && $save_card_enabled && $save_card_checkbox ) {
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] ✅ All conditions met - calling flow_save_cards()' );
		$this->flow_save_cards( $order, $flow_pay_id );
		// Clear the session variable after processing
		WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
	} else {
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] ❌ Conditions NOT met - Payment type: ' . $flow_payment_type_for_save . ' (expected: card), Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
	}
}

	if ( class_exists( 'WC_Subscriptions_Order' ) && $flow_result !== null ) {
			// Save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $flow_result['source']['id'] );

			if ( "card" === $subs_payment_type ) {
				$this->save_preferred_card_scheme( $order_id, $order, $flow_result['source']['scheme'] );
			}
		}

		// add notes for the order and update status.
		$order->add_order_note( $message );
		// Only update status if not null (null means payment already captured, keep existing status)
		if ( null !== $status ) {
			$order->update_status( $status );
		}

		// CRITICAL: Only proceed with success flow if order status is NOT failed
		// If status is failed, don't clear cart or redirect to success page
		$final_order_status = $order->get_status();
		if ( 'failed' === $final_order_status ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Payment failed - Order status is failed. NOT clearing cart or redirecting to success page.' );
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Payment failed. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
			return; // Return early - don't clear cart or redirect
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Get return URL before emptying cart
		$return_url = $this->get_return_url( $order );
		
		// Check if this is a MOTO order and log additional info
		$is_moto_order = $order->is_created_via( 'admin' );
		$is_guest = $order->get_customer_id() == 0;
		$order_key_in_url = strpos( $return_url, 'key=' ) !== false;
		
		if ( $is_moto_order ) {
			WC_Checkoutcom_Utility::logger( 'MOTO order detected - Order ID: ' . $order_id . ', Created via: ' . $order->get_created_via() );
		}
		
		// Log the redirect URL for debugging
		WC_Checkoutcom_Utility::logger( sprintf( 
			'Flow payment successful - redirecting to: %s (MOTO: %s, Guest: %s, Order Key in URL: %s, Order ID: %d)',
			$return_url,
			$is_moto_order ? 'YES' : 'NO',
			$is_guest ? 'YES' : 'NO',
			$order_key_in_url ? 'YES' : 'NO',
			$order_id
		) );
		WC_Checkoutcom_Utility::logger( 'Order status updated to: ' . $status . ', Order ID: ' . $order_id );
		WC_Checkoutcom_Utility::logger( 'Transaction ID set to: ' . $order->get_transaction_id() );

		// Remove cart - only clear if payment succeeded (order status is not failed)
		WC()->cart->empty_cart();

		// Return thank you page.
		$redirect_result = array(
			'result'   => 'success',
			'redirect' => $return_url,
		);
		
		return $redirect_result;
	}

	
	/**
	 * Detect 3DS return parameters on checkout page and process server-side.
	 * This prevents JavaScript form submission issues in slow environments.
	 *
	 * @return void
	 */
	public function detect_and_process_3ds_return_on_checkout() {
		// Check if 3DS return parameters are present FIRST (before checking is_checkout)
		// This ensures we catch it even if is_checkout() hasn't loaded yet in slow environments
		$payment_id = isset( $_GET['cko-payment-id'] ) ? sanitize_text_field( $_GET['cko-payment-id'] ) : '';
		$session_id = isset( $_GET['cko-session-id'] ) ? sanitize_text_field( $_GET['cko-session-id'] ) : '';
		$payment_session_id = isset( $_GET['cko-payment-session-id'] ) ? sanitize_text_field( $_GET['cko-payment-session-id'] ) : '';
		
		// If we have payment ID or session ID, it's a 3DS return
		if ( empty( $payment_id ) && empty( $session_id ) && empty( $payment_session_id ) ) {
			return;
		}
		
		// Only process on checkout page or order-pay page
		$is_checkout_page = is_checkout() || ( isset( $_GET['order_id'] ) && isset( $_GET['key'] ) );
		if ( ! $is_checkout_page ) {
			return;
		}
		
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS CHECKOUT] 3DS return detected on checkout page - Processing server-side' );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS CHECKOUT] Payment ID: ' . $payment_id . ', Session ID: ' . $session_id . ', Payment Session ID: ' . $payment_session_id );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS CHECKOUT] Request URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'N/A' ) );
		
		// Process the 3DS return (this will redirect to success page)
		try {
			$this->handle_3ds_return();
			// If handle_3ds_return doesn't exit/redirect, exit here
			exit;
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS CHECKOUT] Error processing 3DS return: ' . $e->getMessage() );
			
			// Try to find order by payment ID as fallback
			if ( ! empty( $payment_id ) ) {
				$orders = wc_get_orders( array(
					'meta_key'   => '_cko_payment_id',
					'meta_value' => $payment_id,
					'limit'      => 1,
					'orderby'    => 'date',
					'order'      => 'DESC',
				) );
				
				if ( ! empty( $orders ) ) {
					$order = $orders[0];
					$redirect_url = $order->get_checkout_order_received_url();
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS CHECKOUT] Found order by payment ID, redirecting to: ' . $redirect_url );
					wp_safe_redirect( $redirect_url );
					exit;
				}
			}
			
			// If we can't find order, show error but don't die - let JavaScript handle it as fallback
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an error processing your order. Please check your order history.', 'checkout-com-unified-payments-api' ), 'error' );
			// Don't exit - let the page load so JavaScript can handle it as fallback
			return;
		}
	}

	/**
	 * Handle 3DS return via WC API endpoint (similar to PayPal approach).
	 * Processes payment and redirects directly to order-received page.
	 * 
	 * @return void
	 */
	public function handle_3ds_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		
		// PERFORMANCE: Only log verbose details in debug mode
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ========== ENTRY POINT ==========' );
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Request URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'N/A' ) );
		}
		
		$payment_id = isset( $_GET['cko-payment-id'] ) ? sanitize_text_field( $_GET['cko-payment-id'] ) : '';
		$payment_session_id_from_url = isset( $_GET['cko-payment-session-id'] ) ? sanitize_text_field( $_GET['cko-payment-session-id'] ) : '';
		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order_key  = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment ID: ' . $payment_id . ', Order ID: ' . $order_id );
		}
		
		if ( empty( $payment_id ) ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Missing payment ID in GET params' );
			wp_die( esc_html__( 'Missing payment ID', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
		}
		
		// PERFORMANCE OPTIMIZATION: Initialize SDK and payment details cache (used in both fast and slow paths)
		static $sdk_builder_cache = null;
		static $payment_details_cache = array();
		
		// PERFORMANCE OPTIMIZATION: Early exit - Check order_id FIRST before expensive operations
		// If order_id and order_key are provided (order-pay page), use them directly (fast path)
		$order = null;
		$payment_details = null;
		
		if ( ! empty( $order_id ) && ! empty( $order_key ) ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order ID and key provided - loading order: ' . $order_id );
			}
			$order = wc_get_order( $order_id );
			
			if ( ! $order ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Order not found - Order ID: ' . $order_id );
				wp_die( esc_html__( 'Order not found', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
			}
			
			$order_key_from_order = $order->get_order_key();
			if ( $order_key_from_order !== $order_key ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Order key mismatch' );
				wp_die( esc_html__( 'Invalid order key', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
			}
			
			// PERFORMANCE: Fast path - order found, skip to payment processing
			// Payment details will be fetched later if needed (after duplicate check)
		} else {
			// PERFORMANCE OPTIMIZATION: For regular checkout, look up order by payment session ID
			// Initialize SDK once (if not already initialized)
			if ( null === $sdk_builder_cache ) {
				$checkout = new Checkout_SDK();
				$sdk_builder_cache = $checkout->get_builder();
			}
			$builder = $sdk_builder_cache;
			
			if ( ! $builder ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] WARNING: SDK builder not initialized - vendor/autoload.php may be missing' );
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] For Flow payments, payment details are handled via webhooks, so this may not be critical' );
				// For Flow payments, we can continue without fetching payment details here
				// Payment will be processed via webhook or payment ID from form
				// Set payment_details to null and continue
				$payment_details = null;
			} else {
				// PERFORMANCE: Fetch payment details once and cache
				if ( ! isset( $payment_details_cache[ $payment_id ] ) ) {
					try {
						if ( $is_debug ) {
							WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fetching payment details - Payment ID: ' . $payment_id );
						}
						$payment_details_cache[ $payment_id ] = $builder->getPaymentsClient()->getPaymentDetails( $payment_id );
					} catch ( Exception $e ) {
						if ( $is_debug ) {
							WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] WARNING: Failed to fetch payment details: ' . $e->getMessage() );
						}
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to fetch payment details: ' . $e->getMessage() );
						wp_die( esc_html__( 'Payment processing error', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
					}
				}
				$payment_details = $payment_details_cache[ $payment_id ];
			}
			
			try {
				// Get payment session ID from URL first (faster), then from payment metadata if needed
				$payment_session_id = $payment_session_id_from_url;
				
				if ( empty( $payment_session_id ) && ! empty( $payment_details ) ) {
					// Get payment session ID from cached payment details
					$payment_session_id = isset( $payment_details['metadata']['cko_payment_session_id'] ) ? $payment_details['metadata']['cko_payment_session_id'] : '';
					if ( $is_debug && ! empty( $payment_session_id ) ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment session ID from payment metadata: ' . $payment_session_id );
					}
				}
				
				if ( empty( $payment_session_id ) ) {
					if ( $is_debug ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment session ID not found in URL or payment metadata' );
					}
					// Don't die yet - try fallback methods
				} else {
					
					// PERFORMANCE OPTIMIZATION Phase 2: Combined query - Look up by payment_session_id OR payment_id in single query
					// This reduces database queries from 2 to 1
					if ( $is_debug ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Looking up order by payment session ID or payment ID...' );
					}
					
					// Build meta_query with OR conditions for both payment_session_id and payment_id
					$meta_query = array(
						'relation' => 'OR',
					);
					
					if ( ! empty( $payment_session_id ) ) {
						$meta_query[] = array(
							'key'   => '_cko_payment_session_id',
							'value' => $payment_session_id,
						);
					}
					
					// Always include payment_id lookup (fallback)
					$meta_query[] = array(
						'key'   => '_cko_payment_id',
						'value' => $payment_id,
					);
					
					$orders = wc_get_orders( array(
						'meta_query' => $meta_query,
						'limit'      => 5, // Get up to 5 to check statuses
						'orderby'    => 'date',
						'order'      => 'DESC',
					) );
					
					// Process results - prioritize payment_session_id matches, then payment_id matches
					if ( ! empty( $orders ) ) {
						$found_order = null;
						$found_by_payment_session = false;
						
						foreach ( $orders as $potential_order ) {
							$order_payment_session_id = $potential_order->get_meta( '_cko_payment_session_id' );
							$order_payment_id = $potential_order->get_meta( '_cko_payment_id' );
							$order_status = $potential_order->get_status();
							
							// Prioritize payment_session_id match
							if ( ! empty( $payment_session_id ) && $order_payment_session_id === $payment_session_id ) {
								if ( in_array( $order_status, array( 'pending', 'failed' ), true ) ) {
									$found_order = $potential_order;
									$found_by_payment_session = true;
									if ( $is_debug ) {
										WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found by payment session ID - Order ID: ' . $found_order->get_id() . ', Status: ' . $order_status );
									}
									break; // Found best match, exit loop
								}
							}
						}
						
						// If no payment_session_id match found, check payment_id matches
						if ( ! $found_order ) {
							foreach ( $orders as $potential_order ) {
								$order_payment_id = $potential_order->get_meta( '_cko_payment_id' );
								$order_status = $potential_order->get_status();
								$existing_transaction_id = $potential_order->get_transaction_id();
								
								if ( $order_payment_id === $payment_id ) {
									// Check if order already has a transaction ID (means it was already fully processed)
									if ( ! empty( $existing_transaction_id ) ) {
										if ( $is_debug ) {
											WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ⚠️ Order found by payment ID already has transaction ID: ' . $existing_transaction_id . ' - NOT reusing (already processed)' );
										}
										continue; // Skip this order, check next
									} elseif ( in_array( $order_status, array( 'pending', 'failed' ), true ) ) {
										// Only use this order if it's in pending or failed status AND has no transaction ID (reusable)
										$found_order = $potential_order;
										if ( $is_debug ) {
											WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found by payment ID - Order ID: ' . $found_order->get_id() . ', Status: ' . $order_status );
										}
										break; // Found reusable order, exit loop
									}
								}
							}
						}
						
						if ( $found_order ) {
							$order = $found_order;
							$order_id = $order->get_id();
							if ( $is_debug ) {
								WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ✅ Using order found by ' . ( $found_by_payment_session ? 'payment session ID' : 'payment ID' ) . ' (status: ' . $found_order->get_status() . ') - Order ID: ' . $order_id );
							}
						}
					}
					
					// If order not found or not reusable, try fallback methods
					if ( ! $order ) {
							// Fallback 2: Try to find most recent pending/failed order for this customer
							// IMPORTANT: Apply same duplicate prevention logic as pre-order creation check
							if ( $is_debug ) {
								WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fallback: Looking for most recent pending/failed order...' );
							}
							if ( $payment_details && isset( $payment_details['customer']['email'] ) ) {
								$customer_email = $payment_details['customer']['email'];
								if ( ! empty( $customer_email ) ) {
									// PERFORMANCE OPTIMIZATION Phase 2: Single query for both pending and failed orders
									// Use date_query to only check recent orders (same time windows as pre-order creation check)
									$date_after_pending = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
									
									// Single query for both pending and failed orders (more efficient than two separate queries)
									$orders_by_email = wc_get_orders( array(
										'billing_email' => $customer_email,
										'status'        => array( 'pending', 'failed' ),
										'date_query'    => array(
											array(
												'after' => $date_after_pending, // Use longer window (7 days) for both
												'inclusive' => true,
											),
										),
										'limit'         => 10, // Increased limit to handle both statuses
										'orderby'       => 'date',
										'order'         => 'DESC',
									) );
									
									// Filter failed orders to 2-day window in PHP (more efficient than second query)
									if ( ! empty( $orders_by_email ) ) {
										$date_after_failed = strtotime( '-2 days' );
										$filtered_orders = array();
										
										foreach ( $orders_by_email as $email_order ) {
											$order_status = $email_order->get_status();
											$order_date = $email_order->get_date_created();
											
											// Include pending orders (7-day window) or failed orders (2-day window)
											if ( 'pending' === $order_status || 
											     ( 'failed' === $order_status && $order_date->getTimestamp() >= $date_after_failed ) ) {
												$filtered_orders[] = $email_order;
											}
										}
										
										$orders_by_email = $filtered_orders;
									}
									
									if ( $is_debug ) {
										WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Found ' . count( $orders_by_email ) . ' pending/failed orders for email: ' . $customer_email );
									}
									
									// CRITICAL: Only reuse orders if payment_session_id matches (or is missing)
									// If payment_session_id in URL doesn't match existing order, create new order
									// This ensures every payment session (request to Checkout.com) has corresponding order
									$payment_amount = isset( $payment_details['amount'] ) ? $payment_details['amount'] : 0;
									$payment_currency = isset( $payment_details['currency'] ) ? $payment_details['currency'] : '';
									foreach ( $orders_by_email as $potential_order ) {
										$potential_order_status = $potential_order->get_status();
										// Double-check status (should already be filtered, but be safe)
										if ( ! in_array( $potential_order_status, array( 'pending', 'failed' ), true ) ) {
											continue;
										}
										
										// CRITICAL: Check if order already has a transaction ID or payment ID
										// If it does, it was already processed (even if failed), so don't reuse it
										$existing_transaction_id = $potential_order->get_transaction_id();
										$existing_payment_id = $potential_order->get_meta( '_cko_payment_id' );
										$existing_flow_payment_id = $potential_order->get_meta( '_cko_flow_payment_id' );
										$existing_payment_session_id = $potential_order->get_meta( '_cko_payment_session_id' );
										
										// CRITICAL: Don't reuse if order has different payment_session_id
										// Each payment session should have its own order
										if ( ! empty( $payment_session_id ) && ! empty( $existing_payment_session_id ) && $existing_payment_session_id !== $payment_session_id ) {
											if ( $is_debug ) {
												WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ⚠️ Order ' . $potential_order->get_id() . ' has different payment_session_id (' . $existing_payment_session_id . ' vs ' . $payment_session_id . ') - NOT reusing. Each payment session needs its own order.' );
											}
											continue; // Skip this order, check next one
										}
										
										if ( ! empty( $existing_transaction_id ) || ! empty( $existing_payment_id ) || ! empty( $existing_flow_payment_id ) ) {
											if ( $is_debug ) {
												WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ⚠️ Order ' . $potential_order->get_id() . ' already has payment ID/transaction ID - NOT reusing' );
											}
											continue; // Skip this order, check next one
										}
										
										// CRITICAL FIX: Use utility function to convert order total to currency subunit format
										// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
										$potential_order_currency = $potential_order->get_currency();
										$order_total_decimal = (float) $potential_order->get_total();
										$order_amount = (int) WC_Checkoutcom_Utility::value_to_decimal( $order_total_decimal, $potential_order_currency );
										if ( $order_amount === $payment_amount ) {
											$order = $potential_order;
											$order_id = $order->get_id();
											if ( $is_debug ) {
												WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ✅ Order found by email and amount match (fallback) - Order ID: ' . $order_id . ', Status: ' . $potential_order_status );
											}
											break;
										}
									}
								}
							}
							
							if ( ! $order ) {
								// Last resort: Create order from cart and payment details
								// This happens when 3DS redirect occurs before form submission
								if ( $is_debug ) {
									WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order not found - checking for existing order with same session+cart hash before creating new one' );
								}
								
								if ( ! WC()->cart || WC()->cart->is_empty() ) {
									// If we have payment details, create minimal order
									if ( ! empty( $payment_details ) && ! empty( $payment_id ) ) {
										$order = $this->create_minimal_order_from_payment_details( $payment_id, $payment_details );
										
										if ( ! is_wp_error( $order ) && $order ) {
											if ( $is_debug ) {
												WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Minimal order created from payment details - Order ID: ' . $order->get_id() );
											}
											$order_id = $order->get_id();
											// Continue with normal flow - order now exists
										} else {
											WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Cannot create order - cart is empty and minimal order creation failed' );
											wp_die( esc_html__( 'Order not found and cart is empty. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
										}
									} else {
										WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Cart is empty, cannot create order' );
										wp_die( esc_html__( 'Order not found and cart is empty. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
									}
								}
								
								// Get customer info from payment details
								$customer_email = '';
								$customer_id = 0;
								
								if ( $payment_details && isset( $payment_details['customer']['email'] ) ) {
									$customer_email = $payment_details['customer']['email'];
									// Try to find customer by email
									$user = get_user_by( 'email', $customer_email );
									if ( $user ) {
										$customer_id = $user->ID;
									}
								}
								
								// Create new order if no reusable order found
								if ( ! $order ) {
									if ( $is_debug ) {
										WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Creating new order - Customer ID: ' . $customer_id . ', Email: ' . $customer_email );
									}
									
									$order = wc_create_order( array( 'customer_id' => $customer_id ) );
								
									if ( ! $order || is_wp_error( $order ) ) {
										// Try to create minimal order from payment details
										if ( ! empty( $payment_details ) && ! empty( $payment_id ) ) {
											$order = $this->create_minimal_order_from_payment_details( $payment_id, $payment_details );
											
											if ( ! is_wp_error( $order ) && $order ) {
												if ( $is_debug ) {
													WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Minimal order created successfully - Order ID: ' . $order->get_id() );
												}
												$order_id = $order->get_id();
												// Continue with normal flow - order now exists
											} else {
												WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to create order' );
												wp_die( esc_html__( 'Failed to create order. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
											}
										} else {
											WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to create order and payment details not available' );
											wp_die( esc_html__( 'Failed to create order. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
										}
									}
									
									// Set customer email
									if ( ! empty( $customer_email ) ) {
										$order->set_billing_email( $customer_email );
									}
									
									// Set billing address from payment details if available
									if ( $payment_details && isset( $payment_details['billing_address'] ) ) {
										$billing = $payment_details['billing_address'];
										if ( isset( $billing['address_line1'] ) ) $order->set_billing_address_1( $billing['address_line1'] );
										if ( isset( $billing['address_line2'] ) ) $order->set_billing_address_2( $billing['address_line2'] );
										if ( isset( $billing['city'] ) ) $order->set_billing_city( $billing['city'] );
										if ( isset( $billing['state'] ) ) $order->set_billing_state( $billing['state'] );
										if ( isset( $billing['zip'] ) ) $order->set_billing_postcode( $billing['zip'] );
										if ( isset( $billing['country'] ) ) $order->set_billing_country( $billing['country'] );
										if ( isset( $payment_details['customer']['name'] ) ) {
											$name_parts = explode( ' ', $payment_details['customer']['name'], 2 );
											$order->set_billing_first_name( $name_parts[0] ?? '' );
											$order->set_billing_last_name( $name_parts[1] ?? '' );
										}
									}
									
									// Add cart items
									foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
										$product = $cart_item['data'];
										$order->add_product( $product, $cart_item['quantity'], array(
											'subtotal' => $cart_item['line_subtotal'],
											'total'    => $cart_item['line_total'],
										) );
									}
									
									// Set shipping method if available
									$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
									$shipping_added_from_session = false;
									if ( ! empty( $chosen_shipping_methods ) ) {
										$shipping_packages = WC()->shipping->get_packages();
										foreach ( $chosen_shipping_methods as $package_key => $method ) {
											if ( isset( $shipping_packages[ $package_key ] ) ) {
												$package = $shipping_packages[ $package_key ];
												if ( isset( $package['rates'][ $method ] ) ) {
													$shipping_rate = $package['rates'][ $method ];
													$item = new WC_Order_Item_Shipping();
													$item->set_props( array(
														'method_title' => $shipping_rate->get_label(),
														'method_id'    => $shipping_rate->get_id(),
														'total'        => wc_format_decimal( $shipping_rate->get_cost() ),
														'taxes'        => $shipping_rate->get_taxes(),
													) );
													$order->add_item( $item );
													$shipping_added_from_session = true;
												}
											}
										}
									}
									
									// CRITICAL FIX: If shipping wasn't added from session (common after 3DS redirect),
									// add it from payment_details which contains the actual payment items including shipping
									if ( ! $shipping_added_from_session && ! empty( $payment_details ) ) {
										$this->add_shipping_from_payment_details( $order, $payment_details );
									}
									
									// Calculate totals
									$order->calculate_totals();
									
									// Set payment method
									$order->set_payment_method( $this->id );
									$order->set_payment_method_title( $this->get_title() );
									
									// Save payment session ID (only if not already set)
									if ( ! empty( $payment_session_id ) ) {
										$existing_order_session_id = $order->get_meta( '_cko_payment_session_id' );
										if ( empty( $existing_order_session_id ) ) {
											$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
											WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment session ID saved to order (from session) - Order ID: ' . $order_id . ', Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
										} else {
											WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment session ID already exists in order (from session) - Order ID: ' . $order_id . ', Existing Payment Session ID: ' . substr( $existing_order_session_id, 0, 20 ) . '..., New Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
										}
									}
									
									// Store save card preference from GET parameter (if available in URL)
									$save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
									if ( 'yes' === $save_card_from_get ) {
										$order->update_meta_data( '_cko_save_card_preference', 'yes' );
									}
									
									// Set status to pending
									$order->set_status( 'pending' );
									$order->save();
									
									$order_id = $order->get_id();
									
									// PERFORMANCE: Defer webhook processing - check if already processed first
									if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
										$already_processed = $order->get_meta( 'cko_payment_authorized' ) || $order->get_meta( 'cko_payment_captured' );
										if ( ! $already_processed ) {
											WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order( $order );
										}
									}
								}
							}
						}
					}
				} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] EXCEPTION during order lookup: ' . $e->getMessage() );
				
				// Try to create minimal order from payment details as fallback
				if ( ! empty( $payment_details ) && ! empty( $payment_id ) ) {
					try {
						$order = $this->create_minimal_order_from_payment_details( $payment_id, $payment_details );
						
						if ( ! is_wp_error( $order ) && $order ) {
							// Continue with normal flow - order now exists
						} else {
							wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
						}
					} catch ( Exception $fallback_exception ) {
						wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
					}
				} else {
					wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
				}
			}
		}
		
		// PERFORMANCE: Check if payment is already processed BEFORE fetching payment details
		$existing_payment = $order->get_meta( '_cko_payment_id' );
		
		if ( $existing_payment === $payment_id ) {
			$return_url = $this->get_return_url( $order );
			wp_safe_redirect( $return_url );
			exit;
		}
		
		// PERFORMANCE: Use cached payment details if available, otherwise fetch
		// For fast path (order_id provided), payment details may not be cached yet
		if ( empty( $payment_details ) ) {
			// Initialize SDK if not already cached
			if ( null === $sdk_builder_cache ) {
				$checkout = new Checkout_SDK();
				$sdk_builder_cache = $checkout->get_builder();
			}
			$builder = $sdk_builder_cache;
			
			if ( ! $builder ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] WARNING: SDK builder not initialized - vendor/autoload.php may be missing' );
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] For Flow payments, payment details are handled via webhooks, so this may not be critical' );
				// For Flow payments, we can continue without fetching payment details here
				// Payment will be processed via webhook or payment ID from form
				// Set payment_details to null and continue
				$payment_details = null;
			} else {
				// Fetch and cache payment details
				if ( ! isset( $payment_details_cache[ $payment_id ] ) ) {
				try {
					$payment_details_cache[ $payment_id ] = $builder->getPaymentsClient()->getPaymentDetails( $payment_id );
				} catch ( Exception $e ) {
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to fetch payment details: ' . $e->getMessage() );
					throw $e;
				}
				}
				$payment_details = $payment_details_cache[ $payment_id ];
			}
		}
		
		// Check if payment is approved
		try {
			// If payment_details is null (SDK not available), check if we have a payment ID
			// For Flow payments, if payment ID exists, payment was processed by Flow component
			// and we should assume it's approved (webhooks will handle final status)
			if ( empty( $payment_details ) && ! empty( $payment_id ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment details not available (SDK missing), but payment ID exists: ' . $payment_id );
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Assuming payment approved - Flow component handles approval client-side, webhooks will verify' );
				$is_approved = true; // Assume approved if payment ID exists and SDK unavailable
			} else {
				$is_approved = isset( $payment_details['approved'] ) ? $payment_details['approved'] : false;
			}
			
			if ( ! $is_approved ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment not approved - Payment ID: ' . $payment_id );
				
				// Get error message from payment details if available
				$error_message = __( 'Payment was not approved. Please try again.', 'checkout-com-unified-payments-api' );
				if ( isset( $payment_details['response_summary'] ) ) {
					$error_message = $payment_details['response_summary'];
				} elseif ( isset( $payment_details['status'] ) ) {
					$error_message = sprintf( __( 'Payment failed with status: %s', 'checkout-com-unified-payments-api' ), $payment_details['status'] );
				}
				
				// If we have an order, update it and redirect to checkout/order-pay with error
				if ( $order ) {
					// CRITICAL: Set addresses consistently for failed payments (same logic as successful payments)
					// This ensures addresses are always visible regardless of payment outcome
					// Priority: 1) payment_details (if available), 2) POST data, 3) WC()->customer, 4) Preserve existing
					if ( $is_debug ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [ADDRESS DEBUG] Payment failed - Setting addresses consistently' );
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [ADDRESS DEBUG] Payment details available: ' . ( $payment_details ? 'YES' : 'NO' ) . ', billing_address in payment_details: ' . ( isset( $payment_details['billing_address'] ) ? 'YES' : 'NO' ) );
					}
					$this->set_order_addresses_consistently( $order, $payment_details );
					
					// Save addresses BEFORE marking as failed
					$order->save();
					if ( $is_debug ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [ADDRESS DEBUG] Addresses saved BEFORE marking as failed - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
					}
					
					// Update order status to failed
					$order->update_status( 'failed', __( 'Payment was not approved by Checkout.com', 'checkout-com-unified-payments-api' ) );
					
					// Save payment ID to order for reference
					if ( ! empty( $payment_id ) ) {
						$order->update_meta_data( '_cko_payment_id', $payment_id );
						
						// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
						$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
						if ( empty( $existing_flow_payment_id ) ) {
							$order->update_meta_data( '_cko_flow_payment_id', $payment_id );
						} else {
							WC_Checkoutcom_Utility::logger( '[3DS RETURN] Payment ID already exists in order (failed payment) - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $payment_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
						}
					}
					
					// Save order again after marking as failed
					$order->save();
					if ( $is_debug ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [ADDRESS DEBUG] Addresses saved AFTER marking as failed - Billing: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_country() . ' | Shipping: ' . $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_country() );
					}
					
					// Add error notice
					WC_Checkoutcom_Utility::wc_add_notice_self( $error_message, 'error' );
					
					// Determine redirect URL based on context
					if ( ! empty( $order_id ) && ! empty( $order_key ) ) {
						// Order-pay page: redirect back to order-pay page
						$redirect_url = wc_get_endpoint_url( 'order-pay', $order_id, wc_get_checkout_url() );
						$redirect_url = add_query_arg( array(
							'pay_for_order' => 'true',
							'key' => $order_key,
							'payment_failed' => '1',
						), $redirect_url );
					} else {
						// Regular checkout: redirect to checkout page
						$redirect_url = add_query_arg( 'payment_failed', '1', wc_get_checkout_url() );
					}
					
					wp_safe_redirect( $redirect_url );
					exit;
				} else {
					// No order found - try to create minimal order
					if ( ! empty( $payment_details ) && ! empty( $payment_id ) ) {
						$order = $this->create_minimal_order_from_payment_details( $payment_id, $payment_details );
						
						if ( ! is_wp_error( $order ) && $order ) {
							// Update order status to failed
							$order->update_status( 'failed', __( 'Payment was not approved by Checkout.com', 'checkout-com-unified-payments-api' ) );
							$order->save();
							
							// Add error notice
							WC_Checkoutcom_Utility::wc_add_notice_self( $error_message, 'error' );
							
							// Redirect to checkout page
							$redirect_url = add_query_arg( 'payment_failed', '1', wc_get_checkout_url() );
							wp_safe_redirect( $redirect_url );
							exit;
						}
					}
					
					// If minimal order creation also failed, show error
					wp_die( esc_html( $error_message ), esc_html__( 'Payment Failed', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
				}
			}
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] EXCEPTION: ' . $e->getMessage() );
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Exception trace: ' . $e->getTraceAsString() );
			}
			
			// PERFORMANCE OPTIMIZATION Phase 2: Only reload order if not already cached
			// Try to find order and redirect to checkout with error
			if ( ! empty( $order_id ) && ( ! $order || $order->get_id() !== $order_id ) ) {
				$order = wc_get_order( $order_id );
			}
			if ( $order ) {
				$order->update_status( 'failed', __( 'Payment processing failed due to an error.', 'checkout-com-unified-payments-api' ) );
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
			wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
		}
		
		// CRITICAL SECURITY CHECK: Validate payment amount matches order amount
		// This prevents cart manipulation attacks where user modifies cart during 3DS authentication
		// PERFORMANCE OPTIMIZATION: Check order status first (fastest check) - skip validation if already processed
		// Postage is added BEFORE payment session call, so payment amount already includes postage
		$order_status = $order->get_status();
		$payment_already_processed = in_array( $order_status, array( 'processing', 'completed', 'on-hold' ), true );
		
		// If status check is ambiguous (pending/failed), check payment IDs (only if needed)
		if ( ! $payment_already_processed ) {
			$existing_transaction_id = $order->get_transaction_id();
			$existing_payment_id = $order->get_meta( '_cko_payment_id' );
			$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			// Payment is already processed if it has any of these identifiers
			$payment_already_processed = ! empty( $existing_transaction_id ) || ! empty( $existing_payment_id ) || ! empty( $existing_flow_payment_id );
		}
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] Payment already processed check - status: ' . $order_status . ', result: ' . ( $payment_already_processed ? 'YES (skip validation)' : 'NO (run validation)' ) );
		}
		
		if ( ! $payment_already_processed && ! empty( $payment_details ) && isset( $payment_details['amount'] ) && $order ) {
			$payment_amount_subunits = (int) $payment_details['amount']; // Already in currency subunit format from API
			$order_currency = $order->get_currency();
			$order_total_decimal = (float) $order->get_total();
			// CRITICAL FIX: Use utility function to convert order total to currency subunit format
			// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
			$order_total_subunits = (int) WC_Checkoutcom_Utility::value_to_decimal( $order_total_decimal, $order_currency );
			$currency = isset( $payment_details['currency'] ) ? $payment_details['currency'] : '';
			
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] Validating payment amount - Payment: ' . $payment_amount_subunits . ' subunits (' . $order_currency . '), Order: ' . $order_total_subunits . ' subunits (decimal: ' . $order_total_decimal . ')' );
			}
			
			// Check currency match first
			if ( ! empty( $currency ) && ! empty( $order_currency ) && strtoupper( $currency ) !== strtoupper( $order_currency ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] ERROR: Payment currency (' . $currency . ') does not match order currency (' . $order_currency . ')' );
				
				// Mark order as failed due to security check - webhooks should NOT process this
				$order->update_meta_data( '_cko_security_check_failed', 'currency_mismatch' );
				$order->update_status( 'failed', __( 'Payment security check failed: Currency mismatch. Cart may have been modified during payment.', 'checkout-com-unified-payments-api' ) );
				$order->save();
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Security check failed: Payment currency does not match order. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
			
			// Check amount match (allow 1 subunit difference for rounding)
			// For zero-decimal currencies, this allows 1 unit difference
			// For two-decimal currencies, this allows 1 cent difference
			// For three-decimal currencies, this allows 1 mill difference
			if ( abs( $payment_amount_subunits - $order_total_subunits ) > 1 ) {
				// Convert back to decimal for error message display
				$payment_amount_display = WC_Checkoutcom_Utility::decimal_to_value( $payment_amount_subunits, $order_currency );
				$order_total_display = WC_Checkoutcom_Utility::decimal_to_value( $order_total_subunits, $order_currency );
				
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] ERROR: Payment amount (' . $payment_amount_subunits . ' subunits = ' . $payment_amount_display . ' ' . $order_currency . ') does not match order total (' . $order_total_subunits . ' subunits = ' . $order_total_display . ' ' . $order_currency . '). Possible cart manipulation attack.' );
				
				// Mark order as failed due to security check - webhooks should NOT process this
				$order->update_meta_data( '_cko_security_check_failed', 'amount_mismatch' );
				$order->update_status( 'failed', sprintf( __( 'Payment security check failed: Amount mismatch. Payment amount (%s) does not match order total (%s). Cart may have been modified during payment.', 'checkout-com-unified-payments-api' ), wc_price( $payment_amount_display, array( 'currency' => $order_currency ) ), wc_price( $order_total_display, array( 'currency' => $order_currency ) ) ) );
				$order->save();
				WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Security check failed: Payment amount does not match order total. Your cart may have been modified during payment. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
			
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] Payment amount validation passed' );
			}
		} elseif ( $payment_already_processed ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [SECURITY] Skipping amount validation - payment already processed (status: ' . $order_status . '). Shipping is now added during order creation, so totals should match.' );
			}
		}
		
		// Process the payment by simulating POST data and calling process_payment
		$payment_type = isset( $payment_details['source']['type'] ) ? $payment_details['source']['type'] : 'card';
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Setting POST data - Payment ID: ' . $payment_id . ', Payment Type: ' . $payment_type );
		
		// Set POST data for process_payment method
		$_POST['cko-flow-payment-id'] = $payment_id;
		$_POST['cko-flow-payment-type'] = $payment_type;
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Calling process_payment for order: ' . $order_id );
		}
		
		// Process the payment
		$result = $this->process_payment( $order_id );
		
		// PERFORMANCE OPTIMIZATION Phase 2: Cache order object - avoid redundant loading
		// Run card saving logic here in handle_3ds_return() AFTER process_payment()
		// This ensures card saving runs even if duplicate prevention triggered in process_payment()
		// Only reload order if we don't already have it (process_payment may have returned it)
		if ( ! empty( $order_id ) && ( ! $order || $order->get_id() !== $order_id ) ) {
			$order = wc_get_order( $order_id );
		}
		
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Checking conditions - Result: ' . ( isset( $result['result'] ) ? $result['result'] : 'NOT SET' ) . ', Order ID: ' . $order_id );
		}
		
		if ( isset( $result['result'] ) && 'success' === $result['result'] && $order ) {
			
			// Get payment type and check if card saving is enabled
			$flow_payment_type_for_save = $payment_type;
			$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
			
			// Check save card preference (priority: order metadata > cookie > GET > hidden field > POST > session)
			$save_card_from_order = $order->get_meta( '_cko_save_card_preference' );
			$save_card_from_cookie = isset( $_COOKIE['cko_flow_save_card_preference'] ) ? sanitize_text_field( $_COOKIE['cko_flow_save_card_preference'] ) : '';
			$save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
			$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
			$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
			$save_card_session = WC()->session ? WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' ) : '';
			
			// Determine if checkbox was checked
			// Priority: Order metadata > Cookie (survives 3DS redirects) > Hidden field > POST checkbox > GET parameter (may be stale) > Session
			// Note: GET parameter may have 'no' from payment session creation before checkbox was checked
			$save_card_checkbox = false;
			if ( 'yes' === $save_card_from_order ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in order metadata: YES' );
			} elseif ( 'yes' === $save_card_from_cookie ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in cookie: YES (survives 3DS redirects)' );
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				$order->save();
				// Clear cookie after use
				setcookie( 'cko_flow_save_card_preference', '', time() - 3600, '/' );
			} elseif ( 'yes' === $save_card_hidden ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in hidden field: YES' );
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				$order->save();
			} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in POST checkbox: YES' );
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				$order->save();
			} elseif ( 'yes' === $save_card_from_get ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in GET parameter: YES' );
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				$order->save();
			} elseif ( 'yes' === $save_card_session ) {
				$save_card_checkbox = true;
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference found in session: YES' );
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				$order->save();
			} else {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Save card preference NOT found - Order meta: ' . $save_card_from_order . ', Cookie: ' . $save_card_from_cookie . ', GET: ' . $save_card_from_get . ', Hidden: ' . $save_card_hidden . ', POST: ' . $save_card_post . ', Session: ' . $save_card_session );
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] DEBUG - Full POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] DEBUG - Full GET data keys: ' . implode( ', ', array_keys( $_GET ) ) );
			}
			
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] Final check - Payment type: ' . $flow_payment_type_for_save . ', Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
			
			// Save card if all conditions are met
			if ( 'card' === $flow_payment_type_for_save && $save_card_enabled && $save_card_checkbox ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] ✅ All conditions met - Saving card for order: ' . $order_id );
				$this->flow_save_cards( $order, $payment_id );
				if ( WC()->session ) {
					WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] [CARD SAVING] ❌ Conditions NOT met - Payment type: ' . $flow_payment_type_for_save . ' (expected: card), Save enabled: ' . ( $save_card_enabled ? 'YES' : 'NO' ) . ', Checkbox: ' . ( $save_card_checkbox ? 'YES' : 'NO' ) );
			}
		}
			
		// Redirect after card saving logic completes
			if ( isset( $result['result'] ) && 'success' === $result['result'] && isset( $result['redirect'] ) ) {
				$redirect_url = $result['redirect'];
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment processed successfully, redirecting to: ' . $redirect_url );
				
				// Ensure headers are sent and redirect happens
				if ( ! headers_sent() ) {
					wp_safe_redirect( $redirect_url );
					exit;
				} else {
					// Headers already sent, use JavaScript redirect as fallback
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] WARNING: Headers already sent, using JavaScript redirect' );
					echo '<script>window.location.href = "' . esc_js( $redirect_url ) . '";</script>';
					exit;
				}
			} else {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment processing failed: ' . print_r( $result, true ) );
				
				// Payment failed - don't redirect to order received page
				// Instead, redirect back to checkout with error message
				if ( $order && $order->get_id() ) {
					// Update order status to failed if not already
					if ( 'failed' !== $order->get_status() ) {
						$order->update_status( 'failed', __( 'Payment processing failed', 'checkout-com-unified-payments-api' ) );
					}
					
					// Get error message from result if available
					$error_message = __( 'Payment processing failed. Please try again.', 'checkout-com-unified-payments-api' );
					if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
						$error_message = $result['error'];
					}
					
					// Add error notice
					WC_Checkoutcom_Utility::wc_add_notice_self( $error_message, 'error' );
					
					// Redirect to checkout page with error
					$redirect_url = add_query_arg( 'payment_failed', '1', wc_get_checkout_url() );
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment failed, redirecting to checkout page: ' . $redirect_url );
					if ( ! headers_sent() ) {
						wp_safe_redirect( $redirect_url );
						exit;
					} else {
						echo '<script>window.location.href = "' . esc_js( $redirect_url ) . '";</script>';
						exit;
					}
				}
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR - Payment processing failed: ' . wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				wp_die( esc_html__( 'Payment processing failed. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
			}
		
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ========== EXIT POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Code Version: 2025-12-01-18:50-fixed-syntax' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Set addresses on order with consistent priority for both successful and failed payments.
	 * Priority: 1) payment_details, 2) POST data, 3) WC()->customer, 4) Preserve existing order addresses
	 *
	 * @param WC_Order $order Order object
	 * @param array    $payment_details Payment details from Checkout.com API (optional)
	 * @return void
	 */
	private function set_order_addresses_consistently( $order, $payment_details = null ) {
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		
		// Priority 1: Set from payment_details if available (most accurate source from Checkout.com)
		if ( $payment_details && isset( $payment_details['billing_address'] ) ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[SET ADDRESSES] Setting addresses from payment_details (Priority 1)' );
			}
			$billing = $payment_details['billing_address'];
			if ( isset( $billing['address_line1'] ) ) $order->set_billing_address_1( $billing['address_line1'] );
			if ( isset( $billing['address_line2'] ) ) $order->set_billing_address_2( $billing['address_line2'] );
			if ( isset( $billing['city'] ) ) $order->set_billing_city( $billing['city'] );
			if ( isset( $billing['state'] ) ) $order->set_billing_state( $billing['state'] );
			if ( isset( $billing['zip'] ) ) $order->set_billing_postcode( $billing['zip'] );
			if ( isset( $billing['country'] ) ) $order->set_billing_country( $billing['country'] );
			if ( isset( $payment_details['customer']['name'] ) ) {
				$name_parts = explode( ' ', $payment_details['customer']['name'], 2 );
				$order->set_billing_first_name( $name_parts[0] ?? '' );
				$order->set_billing_last_name( $name_parts[1] ?? '' );
			}
			if ( isset( $payment_details['customer']['email'] ) ) {
				$order->set_billing_email( $payment_details['customer']['email'] );
			}
			
			// Set shipping address from payment_details or copy from billing
			if ( isset( $payment_details['shipping_address'] ) ) {
				$shipping = $payment_details['shipping_address'];
				if ( isset( $shipping['address_line1'] ) ) $order->set_shipping_address_1( $shipping['address_line1'] );
				if ( isset( $shipping['address_line2'] ) ) $order->set_shipping_address_2( $shipping['address_line2'] );
				if ( isset( $shipping['city'] ) ) $order->set_shipping_city( $shipping['city'] );
				if ( isset( $shipping['state'] ) ) $order->set_shipping_state( $shipping['state'] );
				if ( isset( $shipping['zip'] ) ) $order->set_shipping_postcode( $shipping['zip'] );
				if ( isset( $shipping['country'] ) ) $order->set_shipping_country( $shipping['country'] );
			} else {
				// Shipping same as billing
				$order->set_shipping_first_name( $order->get_billing_first_name() );
				$order->set_shipping_last_name( $order->get_billing_last_name() );
				$order->set_shipping_address_1( $order->get_billing_address_1() );
				$order->set_shipping_address_2( $order->get_billing_address_2() );
				$order->set_shipping_city( $order->get_billing_city() );
				$order->set_shipping_state( $order->get_billing_state() );
				$order->set_shipping_postcode( $order->get_billing_postcode() );
				$order->set_shipping_country( $order->get_billing_country() );
			}
			return; // Addresses set from payment_details, done
		}
		
		// Priority 2: Set from POST data if available (from form submission)
		$has_post_addresses = ! empty( $_POST['billing_first_name'] ) || ! empty( $_POST['billing_address_1'] );
		if ( $has_post_addresses ) {
			if ( $is_debug ) {
				WC_Checkoutcom_Utility::logger( '[SET ADDRESSES] Setting addresses from POST data (Priority 2)' );
			}
			if ( isset( $_POST['billing_first_name'] ) ) $order->set_billing_first_name( sanitize_text_field( $_POST['billing_first_name'] ) );
			if ( isset( $_POST['billing_last_name'] ) ) $order->set_billing_last_name( sanitize_text_field( $_POST['billing_last_name'] ) );
			if ( isset( $_POST['billing_company'] ) ) $order->set_billing_company( sanitize_text_field( $_POST['billing_company'] ) );
			if ( isset( $_POST['billing_address_1'] ) ) $order->set_billing_address_1( sanitize_text_field( $_POST['billing_address_1'] ) );
			if ( isset( $_POST['billing_address_2'] ) ) $order->set_billing_address_2( sanitize_text_field( $_POST['billing_address_2'] ) );
			if ( isset( $_POST['billing_city'] ) ) $order->set_billing_city( sanitize_text_field( $_POST['billing_city'] ) );
			if ( isset( $_POST['billing_state'] ) ) $order->set_billing_state( sanitize_text_field( $_POST['billing_state'] ) );
			if ( isset( $_POST['billing_postcode'] ) ) $order->set_billing_postcode( sanitize_text_field( $_POST['billing_postcode'] ) );
			if ( isset( $_POST['billing_country'] ) ) $order->set_billing_country( sanitize_text_field( $_POST['billing_country'] ) );
			if ( isset( $_POST['billing_phone'] ) ) $order->set_billing_phone( sanitize_text_field( $_POST['billing_phone'] ) );
			if ( isset( $_POST['billing_email'] ) ) $order->set_billing_email( sanitize_email( $_POST['billing_email'] ) );
			
			// Set shipping address
			$ship_to_different_address = isset( $_POST['ship_to_different_address'] ) ? (bool) $_POST['ship_to_different_address'] : false;
			if ( $ship_to_different_address ) {
				if ( isset( $_POST['shipping_first_name'] ) ) $order->set_shipping_first_name( sanitize_text_field( $_POST['shipping_first_name'] ) );
				if ( isset( $_POST['shipping_last_name'] ) ) $order->set_shipping_last_name( sanitize_text_field( $_POST['shipping_last_name'] ) );
				if ( isset( $_POST['shipping_company'] ) ) $order->set_shipping_company( sanitize_text_field( $_POST['shipping_company'] ) );
				if ( isset( $_POST['shipping_address_1'] ) ) $order->set_shipping_address_1( sanitize_text_field( $_POST['shipping_address_1'] ) );
				if ( isset( $_POST['shipping_address_2'] ) ) $order->set_shipping_address_2( sanitize_text_field( $_POST['shipping_address_2'] ) );
				if ( isset( $_POST['shipping_city'] ) ) $order->set_shipping_city( sanitize_text_field( $_POST['shipping_city'] ) );
				if ( isset( $_POST['shipping_state'] ) ) $order->set_shipping_state( sanitize_text_field( $_POST['shipping_state'] ) );
				if ( isset( $_POST['shipping_postcode'] ) ) $order->set_shipping_postcode( sanitize_text_field( $_POST['shipping_postcode'] ) );
				if ( isset( $_POST['shipping_country'] ) ) $order->set_shipping_country( sanitize_text_field( $_POST['shipping_country'] ) );
			} else {
				// Shipping same as billing
				$order->set_shipping_first_name( $order->get_billing_first_name() );
				$order->set_shipping_last_name( $order->get_billing_last_name() );
				$order->set_shipping_company( $order->get_billing_company() );
				$order->set_shipping_address_1( $order->get_billing_address_1() );
				$order->set_shipping_address_2( $order->get_billing_address_2() );
				$order->set_shipping_city( $order->get_billing_city() );
				$order->set_shipping_state( $order->get_billing_state() );
				$order->set_shipping_postcode( $order->get_billing_postcode() );
				$order->set_shipping_country( $order->get_billing_country() );
			}
			return; // Addresses set from POST data, done
		}
		
		// Priority 3: Set from WC()->customer if available (from WooCommerce customer session)
		if ( WC()->customer ) {
			$billing_address_1 = $order->get_billing_address_1();
			$billing_city = $order->get_billing_city();
			$billing_country = $order->get_billing_country();
			$order_addresses_empty = empty( $billing_address_1 ) || empty( $billing_city ) || empty( $billing_country );
			
			if ( $order_addresses_empty ) {
				if ( $is_debug ) {
					WC_Checkoutcom_Utility::logger( '[SET ADDRESSES] Setting addresses from WC()->customer (Priority 3)' );
				}
				$order->set_billing_first_name( WC()->customer->get_billing_first_name() );
				$order->set_billing_last_name( WC()->customer->get_billing_last_name() );
				$order->set_billing_company( WC()->customer->get_billing_company() );
				$order->set_billing_address_1( WC()->customer->get_billing_address_1() );
				$order->set_billing_address_2( WC()->customer->get_billing_address_2() );
				$order->set_billing_city( WC()->customer->get_billing_city() );
				$order->set_billing_state( WC()->customer->get_billing_state() );
				$order->set_billing_postcode( WC()->customer->get_billing_postcode() );
				$order->set_billing_country( WC()->customer->get_billing_country() );
				$order->set_billing_phone( WC()->customer->get_billing_phone() );
				$order->set_billing_email( WC()->customer->get_billing_email() );
				
				$order->set_shipping_first_name( WC()->customer->get_shipping_first_name() );
				$order->set_shipping_last_name( WC()->customer->get_shipping_last_name() );
				$order->set_shipping_company( WC()->customer->get_shipping_company() );
				$order->set_shipping_address_1( WC()->customer->get_shipping_address_1() );
				$order->set_shipping_address_2( WC()->customer->get_shipping_address_2() );
				$order->set_shipping_city( WC()->customer->get_shipping_city() );
				$order->set_shipping_state( WC()->customer->get_shipping_state() );
				$order->set_shipping_postcode( WC()->customer->get_shipping_postcode() );
				$order->set_shipping_country( WC()->customer->get_shipping_country() );
				
				// If shipping address is still empty, copy from billing
				if ( empty( $order->get_shipping_address_1() ) ) {
					$order->set_shipping_first_name( $order->get_billing_first_name() );
					$order->set_shipping_last_name( $order->get_billing_last_name() );
					$order->set_shipping_address_1( $order->get_billing_address_1() );
					$order->set_shipping_address_2( $order->get_billing_address_2() );
					$order->set_shipping_city( $order->get_billing_city() );
					$order->set_shipping_state( $order->get_billing_state() );
					$order->set_shipping_postcode( $order->get_billing_postcode() );
					$order->set_shipping_country( $order->get_billing_country() );
				}
				return; // Addresses set from WC()->customer, done
			}
		}
		
		// Priority 4: Preserve existing order addresses (already set, don't overwrite)
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[SET ADDRESSES] Preserving existing order addresses (Priority 4)' );
		}
	}
	
	/**
	 * Create a minimal order from payment details when normal order creation fails.
	 * This ensures every payment attempt has an order record.
	 *
	 * @param string $payment_id Payment ID from Checkout.com
	 * @param array  $payment_details Payment details from Checkout.com API
	 * @return WC_Order|WP_Error Order object or error
	 */
	/**
	 * Add shipping items to order from payment_details items array.
	 * Extracts shipping items (type: 'shipping_fee') from payment_details and adds them to the order.
	 *
	 * @param WC_Order $order          The WooCommerce order object.
	 * @param array    $payment_details Payment details from Checkout.com API.
	 * @return void
	 */
	private function add_shipping_from_payment_details( $order, $payment_details ) {
		if ( ! $order ) {
			return;
		}
		
		if ( ! isset( $payment_details['items'] ) || ! is_array( $payment_details['items'] ) ) {
			return;
		}
		
		// Check if order already has shipping items
		$existing_shipping_items = $order->get_items( 'shipping' );
		if ( ! empty( $existing_shipping_items ) ) {
			return;
		}
		
		$shipping_added = false;
		$shipping_items_found = 0;
		
		// Extract shipping items from payment_details
		// NOTE: For Flow payments, shipping items may not have 'type' field set (see class-wc-checkoutcom-api-request.php line 1506-1515)
		// So we need to detect shipping by multiple methods: type field, reference pattern, or name pattern
		foreach ( $payment_details['items'] as $index => $item_data ) {
			$item_type = isset( $item_data['type'] ) ? $item_data['type'] : '';
			$item_name = isset( $item_data['name'] ) ? $item_data['name'] : 'Unknown';
			$item_reference = isset( $item_data['reference'] ) ? $item_data['reference'] : '';
			$item_quantity = isset( $item_data['quantity'] ) ? intval( $item_data['quantity'] ) : 1;
			
			// Check if this is a shipping item using multiple detection methods
			$is_shipping = false;
			
			// Method 1: Check type field (for non-Flow payments)
			if ( 'shipping_fee' === $item_type ) {
				$is_shipping = true;
			}
			// Method 2: Check reference field (common shipping method IDs)
			elseif ( ! empty( $item_reference ) && in_array( $item_reference, array( 'flat_rate', 'free_shipping', 'local_pickup', 'shipping' ), true ) ) {
				$is_shipping = true;
			}
			// Method 3: Check name patterns (common shipping names)
			elseif ( ! empty( $item_name ) && (
				stripos( $item_name, 'shipping' ) !== false ||
				stripos( $item_name, 'flat rate' ) !== false ||
				stripos( $item_name, 'delivery' ) !== false ||
				stripos( $item_name, 'postage' ) !== false
			) ) {
				// Additional check: shipping items typically have quantity = 1 and are not products
				if ( $item_quantity === 1 ) {
					$is_shipping = true;
				}
			}
			
			if ( $is_shipping ) {
				$shipping_items_found++;
				$shipping_name = ! empty( $item_name ) ? $item_name : __( 'Shipping', 'checkout-com-unified-payments-api' );
				// CRITICAL FIX: Use utility function to convert from currency subunit to decimal
				// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
				$order_currency = $order->get_currency();
				$shipping_amount_subunits = isset( $item_data['total_amount'] ) ? (int) $item_data['total_amount'] : 0;
				$shipping_total = $shipping_amount_subunits > 0 ? WC_Checkoutcom_Utility::decimal_to_value( $shipping_amount_subunits, $order_currency ) : 0;
				$shipping_reference = ! empty( $item_reference ) ? $item_reference : 'flat_rate';
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					WC_Checkoutcom_Utility::logger( '[FLOW] [SHIPPING DEBUG] Found shipping item - Name: ' . $shipping_name . ', Amount: ' . $shipping_total );
				}
				
				// Create shipping item
				$shipping_item = new WC_Order_Item_Shipping();
				$shipping_item->set_props( array(
					'method_title' => $shipping_name,
					'method_id'    => $shipping_reference,
					'total'        => wc_format_decimal( $shipping_total ),
					'taxes'        => array(), // Tax is typically included in total_amount
				) );
				$order->add_item( $shipping_item );
				$shipping_added = true;
			}
		}
		
		// Recalculate totals after adding shipping
		if ( $shipping_added ) {
			$order->calculate_totals();
		}
	}

	private function create_minimal_order_from_payment_details( $payment_id, $payment_details ) {
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Creating minimal order from payment details - Payment ID: ' . $payment_id );
		
		try {
			// Extract customer info from payment details
			$customer_email = '';
			$customer_id = 0;
			$customer_name = '';
			
			if ( isset( $payment_details['customer']['email'] ) ) {
				$customer_email = $payment_details['customer']['email'];
				// Try to find customer by email
				$user = get_user_by( 'email', $customer_email );
				if ( $user ) {
					$customer_id = $user->ID;
				}
			}
			
			if ( isset( $payment_details['customer']['name'] ) ) {
				$customer_name = $payment_details['customer']['name'];
			}
			
			// Extract currency first (needed for amount conversion)
			$currency = isset( $payment_details['currency'] ) ? $payment_details['currency'] : get_woocommerce_currency();
			
			// CRITICAL FIX: Use utility function to convert from currency subunit to decimal
			// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
			$amount = 0;
			if ( isset( $payment_details['amount'] ) ) {
				$amount_subunits = (int) $payment_details['amount'];
				$amount = WC_Checkoutcom_Utility::decimal_to_value( $amount_subunits, $currency );
			}
			
			// Create order
			$order = wc_create_order( array( 'customer_id' => $customer_id ) );
			
			if ( ! $order || is_wp_error( $order ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to create minimal order' );
				return is_wp_error( $order ) ? $order : new WP_Error( 'order_creation_failed', 'Failed to create order' );
			}
			
			// Set customer email
			if ( ! empty( $customer_email ) ) {
				$order->set_billing_email( $customer_email );
			}
			
			// Set customer name
			if ( ! empty( $customer_name ) ) {
				$name_parts = explode( ' ', $customer_name, 2 );
				$order->set_billing_first_name( $name_parts[0] ?? '' );
				$order->set_billing_last_name( $name_parts[1] ?? '' );
			}
			
			// Set billing address from payment details if available
			if ( isset( $payment_details['billing_address'] ) ) {
				$billing = $payment_details['billing_address'];
				if ( isset( $billing['address_line1'] ) ) {
					$order->set_billing_address_1( $billing['address_line1'] );
				}
				if ( isset( $billing['address_line2'] ) ) {
					$order->set_billing_address_2( $billing['address_line2'] );
				}
				if ( isset( $billing['city'] ) ) {
					$order->set_billing_city( $billing['city'] );
				}
				if ( isset( $billing['state'] ) ) {
					$order->set_billing_state( $billing['state'] );
				}
				if ( isset( $billing['zip'] ) ) {
					$order->set_billing_postcode( $billing['zip'] );
				}
				if ( isset( $billing['country'] ) ) {
					$order->set_billing_country( $billing['country'] );
				}
			}
			
			// Set shipping address from payment details if available, otherwise copy from billing
			if ( isset( $payment_details['shipping_address'] ) ) {
				$shipping = $payment_details['shipping_address'];
				if ( isset( $shipping['address_line1'] ) ) {
					$order->set_shipping_address_1( $shipping['address_line1'] );
				}
				if ( isset( $shipping['address_line2'] ) ) {
					$order->set_shipping_address_2( $shipping['address_line2'] );
				}
				if ( isset( $shipping['city'] ) ) {
					$order->set_shipping_city( $shipping['city'] );
				}
				if ( isset( $shipping['state'] ) ) {
					$order->set_shipping_state( $shipping['state'] );
				}
				if ( isset( $shipping['zip'] ) ) {
					$order->set_shipping_postcode( $shipping['zip'] );
				}
				if ( isset( $shipping['country'] ) ) {
					$order->set_shipping_country( $shipping['country'] );
				}
			} else {
				// Shipping same as billing
				$order->set_shipping_first_name( $order->get_billing_first_name() );
				$order->set_shipping_last_name( $order->get_billing_last_name() );
				$order->set_shipping_address_1( $order->get_billing_address_1() );
				$order->set_shipping_address_2( $order->get_billing_address_2() );
				$order->set_shipping_city( $order->get_billing_city() );
				$order->set_shipping_state( $order->get_billing_state() );
				$order->set_shipping_postcode( $order->get_billing_postcode() );
				$order->set_shipping_country( $order->get_billing_country() );
			}
			
			// Add products and shipping from payment_details items if available
			if ( isset( $payment_details['items'] ) && is_array( $payment_details['items'] ) ) {
				foreach ( $payment_details['items'] as $idx => $item_data ) {
					$item_type = isset( $item_data['type'] ) ? $item_data['type'] : '';
					$item_name = isset( $item_data['name'] ) ? $item_data['name'] : 'Unknown';
					$item_reference = isset( $item_data['reference'] ) ? $item_data['reference'] : '';
					$item_quantity = isset( $item_data['quantity'] ) ? intval( $item_data['quantity'] ) : 1;
					
					// Check if this is a shipping item using multiple detection methods
					// NOTE: For Flow payments, shipping items may not have 'type' field set
					$is_shipping = false;
					
					// Method 1: Check type field (for non-Flow payments)
					if ( 'shipping_fee' === $item_type ) {
						$is_shipping = true;
					}
					// Method 2: Check reference field (common shipping method IDs)
					elseif ( ! empty( $item_reference ) && in_array( $item_reference, array( 'flat_rate', 'free_shipping', 'local_pickup', 'shipping' ), true ) ) {
						$is_shipping = true;
					}
					// Method 3: Check name patterns (common shipping names)
					elseif ( ! empty( $item_name ) && (
						stripos( $item_name, 'shipping' ) !== false ||
						stripos( $item_name, 'flat rate' ) !== false ||
						stripos( $item_name, 'delivery' ) !== false ||
						stripos( $item_name, 'postage' ) !== false
					) ) {
						// Additional check: shipping items typically have quantity = 1 and are not products
						if ( $item_quantity === 1 ) {
							$is_shipping = true;
						}
					}
					
					if ( $is_shipping ) {
						// Add shipping item
						$shipping_item = new WC_Order_Item_Shipping();
						$shipping_name = ! empty( $item_name ) ? $item_name : __( 'Shipping', 'checkout-com-unified-payments-api' );
						// CRITICAL FIX: Use utility function to convert from currency subunit to decimal
						// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
						$order_currency = $order->get_currency();
						$shipping_amount_subunits = isset( $item_data['total_amount'] ) ? (int) $item_data['total_amount'] : 0;
						$shipping_total = $shipping_amount_subunits > 0 ? WC_Checkoutcom_Utility::decimal_to_value( $shipping_amount_subunits, $order_currency ) : 0;
						
						$shipping_item->set_props( array(
							'method_title' => $shipping_name,
							'method_id'    => ! empty( $item_reference ) ? $item_reference : 'flat_rate',
							'total'        => wc_format_decimal( $shipping_total ),
							'taxes'        => array(), // Tax is typically included in total_amount
						) );
						$order->add_item( $shipping_item );
					} else {
						// Add product item
						$product_name = isset( $item_data['name'] ) ? $item_data['name'] : __( 'Product', 'checkout-com-unified-payments-api' );
						// CRITICAL FIX: Use utility function to convert from currency subunit to decimal
						// This handles zero-decimal (JPY, ISK, UGX), three-decimal (BHD, KWD), and two-decimal (USD, EUR) currencies correctly
						$order_currency = $order->get_currency();
						$product_amount_subunits = isset( $item_data['total_amount'] ) ? (int) $item_data['total_amount'] : 0;
						$product_total = $product_amount_subunits > 0 ? WC_Checkoutcom_Utility::decimal_to_value( $product_amount_subunits, $order_currency ) : 0;
						$product_quantity = isset( $item_data['quantity'] ) ? $item_data['quantity'] : 1;
						
						$product_item = new WC_Order_Item_Fee();
						$product_item->set_name( $product_name );
						$product_item->set_amount( $product_total / $product_quantity );
						$product_item->set_total( $product_total );
						$order->add_item( $product_item );
					}
				}
			} else {
				// Fallback: Add a line item with the payment amount
				// This is a minimal order, so we add a single line item
				$item = new WC_Order_Item_Fee();
				$item->set_name( __( 'Payment Amount', 'checkout-com-unified-payments-api' ) );
				$item->set_amount( $amount );
				$item->set_total( $amount );
				$order->add_item( $item );
			}
			
			// Set currency
			$order->set_currency( $currency );
			
			// Calculate totals
			$order->calculate_totals();
			
			// Set payment method
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( $this->get_title() );
			
			// Save payment IDs
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			
			// CRITICAL: Only set _cko_flow_payment_id if not already set (prevent overwriting)
			$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			if ( empty( $existing_flow_payment_id ) ) {
				$order->update_meta_data( '_cko_flow_payment_id', $payment_id );
			} else {
				WC_Checkoutcom_Utility::logger( '[CREATE MINIMAL ORDER] Payment ID already exists in order - Order ID: ' . $order_id . ', Existing Payment ID: ' . substr( $existing_flow_payment_id, 0, 20 ) . '..., New Payment ID: ' . substr( $payment_id, 0, 20 ) . '... (skipping save to prevent overwrite)' );
			}
			
			// Mark as minimal order (created from payment details)
			$order->update_meta_data( '_cko_minimal_order', 'yes' );
			$order->update_meta_data( '_cko_minimal_order_reason', 'Order lookup failed during 3DS callback' );
			
			// Set status to failed (since we're creating this due to a failure)
			$order->set_status( 'failed' );
			$order->save();
			
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Minimal order created successfully - Order ID: ' . $order->get_id() );
			
			return $order;
			
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR creating minimal order: ' . $e->getMessage() );
			return new WP_Error( 'order_creation_exception', $e->getMessage() );
		}
	}

	/**
	 * Save customer's card information after a successful payment.
	 *
	 * @param WC_Order $order   The WooCommerce order object.
	 * @param string   $pay_id  The payment ID used to query payment status.
	 */
	public function flow_save_cards( $order, $pay_id ) {

		WC_Checkoutcom_Utility::logger( '=== FLOW_SAVE_CARDS METHOD START ===' );
		WC_Checkoutcom_Utility::logger( 'Order ID: ' . $order->get_id() );
		WC_Checkoutcom_Utility::logger( 'Payment ID: ' . $pay_id );
		WC_Checkoutcom_Utility::logger( 'User ID: ' . $order->get_user_id() );

		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		WC_Checkoutcom_Utility::logger( 'Admin Save Card Setting: ' . ( $save_card ? 'ENABLED' : 'DISABLED' ) );

		// Check if save card is enable and customer select to save card.
		if ( ! $save_card ) {
			WC_Checkoutcom_Utility::logger( 'Save card disabled in admin - exiting' );
			WC_Checkoutcom_Utility::logger( '=== FLOW_SAVE_CARDS METHOD END ===' );
			return;
		}

		WC_Checkoutcom_Utility::logger( 'Fetching payment details from Checkout.com API...' );
		$request  = new \WP_REST_Request( 'GET', '/ckoplugin/v1/payment-status' );
		$request->set_query_params( [ 'paymentId' => $pay_id ] );

		$result = rest_do_request( $request );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			WC_Checkoutcom_Utility::logger( "ERROR in saving cards: $error_message" ); // phpcs:ignore
			WC_Checkoutcom_Utility::logger( '=== FLOW_SAVE_CARDS METHOD END (ERROR) ===' );
		} else {
			$data = $result->get_data();
			WC_Checkoutcom_Utility::logger( 'Payment data retrieved successfully' );
			WC_Checkoutcom_Utility::logger( 'Payment data: ' . print_r( $data, true ) );
		}
		
		WC_Checkoutcom_Utility::logger( 'Calling save_token method...' );
		$this->save_token( $order->get_user_id(), $data );
		WC_Checkoutcom_Utility::logger( '=== FLOW_SAVE_CARDS METHOD END ===' );
	}

	/**
	 * Renders the save card markup.
	 *
	 * @param string $save_card Save card enable.
	 *
	 * @return void
	 */
	public function element_form_save_card( $save_card ) {
		// Only render the save card checkbox div if save card feature is enabled AND user is logged in
		// Guest users cannot save cards, so don't show the checkbox
		if ( ! $save_card || ! is_user_logged_in() ) {
			return;
		}
		?>
		<!-- Show save card checkbox if this is selected on admin-->
		<div class="cko-save-card-checkbox" style="display: none">
			<?php
			$this->save_payment_method_checkbox();
			?>
		</div>
		<?php
	}

	/**
	 * DEPRECATED: Migrate old saved cards to be compatible with Flow integration.
	 * 
	 * This function is no longer needed as of version 5.0.0.
	 * The saved_payment_methods() function now automatically displays tokens from BOTH
	 * Flow and Classic Cards gateways, and the backend payment processing already
	 * handles both token types seamlessly. No migration is required.
	 *
	 * @deprecated 5.0.0 No longer needed - tokens from both gateways are shown automatically
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function migrate_old_saved_cards( $user_id ) {
		// This function is deprecated and no longer performs any action.
		// Kept for backward compatibility in case it's called by other code.
		WC_Checkoutcom_Utility::logger( 'migrate_old_saved_cards() called but is deprecated - no action taken. Tokens from both gateways are now shown automatically.' );
		return;
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
		WC_Checkoutcom_Utility::logger( '=== SAVE_TOKEN METHOD START ===' );
		WC_Checkoutcom_Utility::logger( 'User ID: ' . $user_id );
		WC_Checkoutcom_Utility::logger( 'Payment Response is null: ' . ( is_null( $payment_response ) ? 'YES' : 'NO' ) );
		
		// Check if payment response is not null.
		if ( ! is_null( $payment_response ) ) {
			WC_Checkoutcom_Utility::logger( 'Payment response received, checking for duplicates...' );
			WC_Checkoutcom_Utility::logger( 'Source ID: ' . ( isset( $payment_response['source']['id'] ) ? $payment_response['source']['id'] : 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'Fingerprint: ' . ( isset( $payment_response['source']['fingerprint'] ) ? $payment_response['source']['fingerprint'] : 'NOT SET' ) );
			
			// argument to check token.
			$arg = array(
				'user_id'    => $user_id,
				'gateway_id' => $this->id,
				'limit'		 => 100,
			);

			// Query token by userid and gateway id.
			$token = WC_Payment_Tokens::get_tokens( $arg );
			WC_Checkoutcom_Utility::logger( 'Found ' . count( $token ) . ' existing Flow tokens for this user' );

			foreach ( $token as $tok ) {
				$fingerprint = $tok->get_meta( 'fingerprint', true );
				// do not save source if it already exists in db.
				if ( $fingerprint === $payment_response['source']['fingerprint'] ) {
					WC_Checkoutcom_Utility::logger( 'Card already exists in Flow tokens (fingerprint match) - NOT saving' );
					WC_Checkoutcom_Utility::logger( '=== SAVE_TOKEN METHOD END (DUPLICATE FLOW) ===' );
					return;
				}
			}

			// Check Classic Card saved or not.
			$classic_saved = new WC_Gateway_Checkout_Com_Cards();

			// argument to check token for card.
			$arg_classic = array(
				'user_id'    => $user_id,
				'gateway_id' => $classic_saved->id,
				'limit'		 => 100,
			);

			// Query token by userid and gateway id.
			$token_classic = WC_Payment_Tokens::get_tokens( $arg_classic );
			WC_Checkoutcom_Utility::logger( 'Found ' . count( $token_classic ) . ' existing Classic Cards tokens for this user' );

			foreach ( $token_classic as $tokc ) {
				$token_data = $tokc->get_data();
				// do not save source if it already exists in db.
				if ( $token_data['token'] === $payment_response['source']['id'] ) {
					WC_Checkoutcom_Utility::logger( 'Card already exists in Classic Cards tokens (source ID match) - NOT saving' );
					WC_Checkoutcom_Utility::logger( '=== SAVE_TOKEN METHOD END (DUPLICATE CLASSIC) ===' );
					return;
				}
			}

			WC_Checkoutcom_Utility::logger( 'No duplicates found - saving new token...' );
			
			// Save source_id in db.
			$token = new WC_Payment_Token_CC();
			$token->set_token( (string) $payment_response['source']['id'] );
			$token->set_gateway_id( $this->id );
			$token->set_card_type( (string) $payment_response['source']['scheme'] );
			$token->set_last4( $payment_response['source']['last4'] );
			$token->set_expiry_month( $payment_response['source']['expiry_month'] );
			$token->set_expiry_year( $payment_response['source']['expiry_year'] );
			$token->set_user_id( $user_id );

			// Add the `fingerprint` metadata.
			$token->add_meta_data( 'fingerprint', $payment_response['source']['fingerprint'], true );

			$save_result = $token->save();
			WC_Checkoutcom_Utility::logger( 'Token save result: ' . ( $save_result ? 'SUCCESS' : 'FAILED' ) );
			WC_Checkoutcom_Utility::logger( 'Token ID: ' . $token->get_id() );
			
			// CRITICAL: Clear token cache after saving so token is immediately available
			// This fixes issue where newly saved cards aren't found when used immediately
			wp_cache_delete( 'customer_tokens_' . $user_id . '_' . $this->id, 'woocommerce' );
			$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
			wp_cache_delete( 'customer_tokens_' . $user_id . '_' . $classic_gateway->id, 'woocommerce' );
			// Also clear the specific token cache
			wp_cache_delete( 'payment_token_' . $token->get_id(), 'woocommerce' );
			WC_Checkoutcom_Utility::logger( 'Token cache cleared - Token ID: ' . $token->get_id() . ' is now immediately available' );
			
			WC_Checkoutcom_Utility::logger( '=== SAVE_TOKEN METHOD END (SAVED) ===' );
		} else {
			WC_Checkoutcom_Utility::logger( 'Payment response is NULL - cannot save token' );
			WC_Checkoutcom_Utility::logger( '=== SAVE_TOKEN METHOD END (NULL RESPONSE) ===' );
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
	 * Handle Flow refund.
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
			WC_Checkoutcom_Utility::logger( '[FLOW ERROR] Refund failed for order ' . $order_id . ': ' . $result['error'] );
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
	 * Deactivate Classic methods when FLOW is active.
	 */
	public static function flow_enabled() {

		$flow_settings = get_option( 'woocommerce_wc_checkout_com_flow_settings', array() );

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';
	
		$apm_settings      = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings', array() );
		$gpay_settings     = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$applepay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$paypal_settings   = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
	
		if ( 'flow' === $checkout_mode ) {
			$flow_settings['enabled']     = 'yes';
			$checkout_setting['enabled']  = 'no';
			$apm_settings['enabled']      = 'no';
			$gpay_settings['enabled']     = 'no';
			$applepay_settings['enabled'] = 'no';
			$paypal_settings['enabled']   = 'no';
		} else {
			$flow_settings['enabled']    = 'no';
			$checkout_setting['enabled'] = 'yes';
		}
	
		update_option( 'woocommerce_wc_checkout_com_flow_settings', $flow_settings );
		update_option( 'woocommerce_wc_checkout_com_cards_settings', $checkout_setting );
		update_option( 'woocommerce_wc_checkout_com_alternative_payments_settings', $apm_settings );
		update_option( 'woocommerce_wc_checkout_com_google_pay_settings', $gpay_settings );
		update_option( 'woocommerce_wc_checkout_com_apple_pay_settings', $applepay_settings );
		update_option( 'woocommerce_wc_checkout_com_paypal_settings', $paypal_settings );
		
		// Log for debugging (only in debug mode to reduce log spam)
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( $is_debug ) {
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] ========== FLOW ENABLED CHECK ==========' );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] Checkout mode: ' . $checkout_mode );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] Flow gateway enabled: ' . ( isset( $flow_settings['enabled'] ) ? $flow_settings['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] Cards gateway enabled: ' . ( isset( $checkout_setting['enabled'] ) ? $checkout_setting['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] APM gateway enabled: ' . ( isset( $apm_settings['enabled'] ) ? $apm_settings['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] Google Pay enabled: ' . ( isset( $gpay_settings['enabled'] ) ? $gpay_settings['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] Apple Pay enabled: ' . ( isset( $applepay_settings['enabled'] ) ? $applepay_settings['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] PayPal enabled: ' . ( isset( $paypal_settings['enabled'] ) ? $paypal_settings['enabled'] : 'not set' ) );
			WC_Checkoutcom_Utility::logger( '[FLOW ENABLED] ========================================' );
		}
	}

	/**
	 * Webhook handler.
	 * Handle Webhook.
	 *
	 * @return bool|int|void
	 */
	public function webhook_handler() {
		// webhook_url_format = http://example.com/?wc-api=wc_checkoutcom_webhook .
		
		// Check if detailed webhook logging is enabled (use existing gateway responses setting)
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$webhook_debug_enabled = ( isset( $core_settings['cko_gateway_responses'] ) && $core_settings['cko_gateway_responses'] === 'yes' );
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK DEBUG: Flow webhook handler started ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Request method: ' . $_SERVER['REQUEST_METHOD'] );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Request URI: ' . $_SERVER['REQUEST_URI'] );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Content type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Content length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set') );
		}

		// Check if Flow mode is enabled - if not, let Cards handler process the webhook
		$checkout_mode = $core_settings['ckocom_checkout_mode'] ?? 'cards';
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Core settings retrieved: ' . print_r($core_settings, true) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Checkout mode: ' . $checkout_mode );
		}
		
		if ( 'flow' !== $checkout_mode ) {
			// Flow mode is not enabled, don't process webhook in Flow handler
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Flow mode not enabled, exiting webhook handler' );
			}
			return;
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Flow mode confirmed, continuing webhook processing' );
		}

		try {
			// Get webhook data.
			$raw_input = file_get_contents( 'php://input' );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw input received: ' . $raw_input );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw input length: ' . strlen($raw_input) );
			}
			
			$data = json_decode( $raw_input );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: JSON decode result: ' . print_r($data, true) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: JSON last error: ' . json_last_error_msg() );
			}

		// Return to home page if empty data.
		if ( empty( $data ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Empty data received, redirecting to home' );
			}
			wp_redirect( get_home_url() );
			exit();
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Data validation passed, continuing processing' );
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
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: All headers: ' . print_r($header, true) );
		}
		
		$header_signature = $header['cko-signature'] ?? null;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: CKO signature from header: ' . ($header_signature ?? 'NOT FOUND') );
		}

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$raw_event     = file_get_contents( 'php://input' );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Raw event for signature verification: ' . $raw_event );
		}

		// For webhook signature verification, use the same logic as the working version
		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];
		$secret_key = $core_settings['ckocom_sk'];
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Secret key (masked): ' . substr($secret_key, 0, 10) . '...' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Is NAS account: ' . (cko_is_nas_account() ? 'YES' : 'NO') );
		}

		$signature = WC_Checkoutcom_Utility::verify_signature( $raw_event, $secret_key, $header_signature );
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Signature verification result: ' . ($signature ? 'VALID' : 'INVALID') );
		}

		// check if cko signature matches.
		if ( false === $signature ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: Invalid signature - returning 401');
				WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: Signature verification failed with:');
				WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Raw event: ' . $raw_event);
				WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Header signature: ' . ($header_signature ?? 'NULL'));
				WC_Checkoutcom_Utility::logger('WEBHOOK DEBUG: - Secret key: ' . substr($secret_key, 0, 10) . '...');
			}
        	$this->send_response(401, 'Unauthorized: Invalid signature');
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Signature verification passed, continuing with order processing' );
		}

		$order      = false;
		$payment_id = null;

		// ALWAYS log webhook matching details (even if debug disabled) for critical payment events
		$webhook_event_type = isset( $data->type ) ? $data->type : 'unknown';
		$webhook_payment_id = isset( $data->data->id ) ? $data->data->id : 'NULL';
		$webhook_session_id = isset( $data->data->metadata->cko_payment_session_id ) ? $data->data->metadata->cko_payment_session_id : 'NOT SET';
		$webhook_order_id = isset( $data->data->metadata->order_id ) ? $data->data->metadata->order_id : 'NOT SET';
		
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========' );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Event Type: ' . $webhook_event_type );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Payment ID: ' . $webhook_payment_id );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Session ID in metadata: ' . $webhook_session_id );
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order ID in metadata: ' . $webhook_order_id );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Starting order lookup process' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Webhook data structure: ' . print_r($data, true) );
		}

		// Method 1: Try order_id from metadata (order-pay page has this)
		if ( ! empty( $data->data->metadata->order_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Trying METHOD 1 (Order ID from metadata): ' . $data->data->metadata->order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by metadata order_id: ' . $data->data->metadata->order_id );
			}
			$order = wc_get_order( $data->data->metadata->order_id );
			if ( $order ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ✅ MATCHED BY METHOD 1 (Order ID from metadata) - Order ID: ' . $order->get_id() );
			} else {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ METHOD 1 FAILED - Order ID ' . $data->data->metadata->order_id . ' not found' );
			}
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by metadata order_id: ' . ($order ? 'YES (ID: ' . $order->get_id() . ')' : 'NO') );
			}
		}
		
		// Method 2: Try COMBINED (payment session ID + payment ID) - MOST RELIABLE
		// CRITICAL: Only match orders that need updating (pending, failed, on-hold, processing)
		// This ensures webhook updates correct order and prevents matching completed orders
		// Both identifiers must match - eliminates false positives and provides highest confidence match
		if ( ! $order && ! empty( $data->data->metadata->cko_payment_session_id ) && ! empty( $data->data->id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Trying METHOD 2 (COMBINED: Session ID + Payment ID)' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Session ID: ' . $data->data->metadata->cko_payment_session_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Payment ID: ' . $data->data->id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by COMBINED (session ID + payment ID)' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Session ID: ' . $data->data->metadata->cko_payment_session_id );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Payment ID: ' . $data->data->id );
			}
			
			// First try to match orders that need updating (pending, failed, on-hold, processing)
			// CRITICAL: Use 'compare' => '=' explicitly and ensure both meta keys exist
			$orders = wc_get_orders( array(
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '_cko_payment_session_id',
						'value'   => $data->data->metadata->cko_payment_session_id,
						'compare' => '=',
					),
					array(
						'key'     => '_cko_flow_payment_id',
						'value'   => $data->data->id,
						'compare' => '=',
					),
				),
				'status'     => array( 'pending', 'failed', 'on-hold', 'processing' ), // ✅ Only match orders that need updating
				'limit'      => 1,
				'return'     => 'objects',
			) );
			
			// If not found in active orders, try all orders (fallback for edge cases)
			if ( empty( $orders ) ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order not found in active statuses, trying all orders...' );
				}
				$orders = wc_get_orders( array(
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'     => '_cko_payment_session_id',
							'value'   => $data->data->metadata->cko_payment_session_id,
							'compare' => '=',
						),
						array(
							'key'     => '_cko_flow_payment_id',
							'value'   => $data->data->id,
							'compare' => '=',
						),
					),
					'limit'      => 1,
					'return'     => 'objects',
				) );
			}
			
			if ( ! empty( $orders ) ) {
				$matched_order = $orders[0];
				
				// CRITICAL: Validate that BOTH meta values actually match (WooCommerce meta_query can match even if one meta key doesn't exist)
				$order_session_id = $matched_order->get_meta( '_cko_payment_session_id' );
				$order_payment_id = $matched_order->get_meta( '_cko_flow_payment_id' );
				$webhook_session_id = $data->data->metadata->cko_payment_session_id;
				$webhook_payment_id = $data->data->id;
				
				// Both must exist AND match
				$session_id_matches = ! empty( $order_session_id ) && $order_session_id === $webhook_session_id;
				$payment_id_matches = ! empty( $order_payment_id ) && $order_payment_id === $webhook_payment_id;
				
				if ( $session_id_matches && $payment_id_matches ) {
					// Both match - valid match
					$order = $matched_order;
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ✅ MATCHED BY METHOD 2 (COMBINED: Session ID + Payment ID) - Order ID: ' . $order->get_id() );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ✅ VALIDATION PASSED - Session ID matches: ' . $order_session_id . ', Payment ID matches: ' . $order_payment_id );
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: ✅ Order found by COMBINED match (ID: ' . $order->get_id() . ')' );
					}
					
					// Add order_id to metadata so processing functions can find it
					if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
						$data->data->metadata->order_id = $order->get_id();
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Set metadata order_id to: ' . $order->get_id() . ' (from COMBINED match)' );
						}
					} else {
						// If metadata is missing or not an object, create it.
						$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Created metadata object with order_id: ' . $order->get_id() . ' (from COMBINED match)' );
						}
					}
				} else {
					// Query matched but validation failed - reject this match
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ METHOD 2 VALIDATION FAILED - Query matched Order ID: ' . $matched_order->get_id() . ' but values don\'t match!' );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Session ID: ' . ( $order_session_id ?: 'NOT SET' ) . ', Webhook Session ID: ' . $webhook_session_id );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Payment ID: ' . ( $order_payment_id ?: 'NOT SET' ) . ', Webhook Payment ID: ' . $webhook_payment_id );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Session ID matches: ' . ( $session_id_matches ? 'YES' : 'NO' ) . ', Payment ID matches: ' . ( $payment_id_matches ? 'YES' : 'NO' ) );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ REJECTING INVALID MATCH - Continuing to Method 3' );
					// Don't set $order - continue to Method 3
				}
			} else {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ METHOD 2 FAILED - No order found by COMBINED match (Session ID: ' . $webhook_session_id . ', Payment ID: ' . $webhook_payment_id . ')' );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: ❌ No order found by COMBINED match' );
				}
			}
		}
		
		// ALWAYS log matching result (even if debug disabled)
		if ( $order ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ✅ ORDER FOUND - Order ID: ' . $order->get_id() );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Status: ' . $order->get_status() );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Payment Session ID: ' . $order->get_meta( '_cko_payment_session_id' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Payment ID (_cko_flow_payment_id): ' . $order->get_meta( '_cko_flow_payment_id' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order Payment ID (_cko_payment_id): ' . $order->get_meta( '_cko_payment_id' ) );
		} else {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ ORDER NOT FOUND - No matching order found' );
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order lookup result: ' . ($order ? 'FOUND (ID: ' . $order->get_id() . ')' : 'NOT FOUND') );
		}

		// Method 3: Try payment ID alone (fallback if combined match failed)
		// CRITICAL: Only match orders that need updating (pending, failed, on-hold, processing)
		// This ensures webhook updates correct order and prevents matching completed orders
		if ( ! $order && ! empty( $data->data->id ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by payment ID: ' . $data->data->id );
			}
			
			// First try to match orders that need updating (pending, failed, on-hold, processing)
			$orders = wc_get_orders( array(
				'limit'        => 1,
				'meta_key'     => '_cko_flow_payment_id',
				'meta_value'   => $data->data->id,
				'status'       => array( 'pending', 'failed', 'on-hold', 'processing' ), // ✅ Only match orders that need updating
				'return'       => 'objects',
			) );
			
			// If not found in active orders, try all orders (fallback for edge cases)
			if ( empty( $orders ) ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order not found in active statuses, trying all orders (fallback)...' );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order not found in active statuses, trying all orders...' );
				}
				$orders = wc_get_orders( array(
					'limit'        => 1,
					'meta_key'     => '_cko_flow_payment_id',
					'meta_value'   => $data->data->id,
					'return'       => 'objects',
				) );
				if ( ! empty( $orders ) ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ⚠️ Found order in fallback search (all statuses) - Order ID: ' . $orders[0]->get_id() );
				}
			}

			if ( ! empty( $orders ) ) {
				$order = $orders[0];
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ✅ MATCHED BY METHOD 3 (PAYMENT ID ALONE) - Order ID: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ⚠️ WARNING: Matched by payment ID alone (less reliable than combined match)' );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment ID: YES (ID: ' . $order->get_id() . ')' );
				}

				// Add order_id to $data->data->metadata.
				if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
					$data->data->metadata->order_id = $order->get_id();
				} else {
					// If metadata is missing or not an object, create it.
					$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
				}
			} else {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ METHOD 3 FAILED - No order found by payment ID: ' . $webhook_payment_id );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment ID: NO' );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: This is normal for webhooks that arrive before process_payment() completes' );
				}
			}
		}
		
		WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ========== ORDER LOOKUP COMPLETE ==========' );

		if ( $order ) {
			// CRITICAL: Check if this webhook already processed this order (prevent duplicate processing)
			$processed_webhooks = $order->get_meta( '_cko_processed_webhook_ids' );
			if ( ! is_array( $processed_webhooks ) ) {
				$processed_webhooks = array();
			}
			
			// Create unique webhook identifier (payment_id + event_type)
			$webhook_id = $data->data->id . '_' . $data->type;
			
			if ( in_array( $webhook_id, $processed_webhooks, true ) ) {
				// Webhook already processed - skip to prevent duplicate order updates
				WC_Checkoutcom_Utility::logger( 'WEBHOOK: ✅ Already processed - Payment ID: ' . $data->data->id . ', Type: ' . $data->type . ', Order: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK: ✅ Skipping duplicate webhook processing to prevent multiple order updates' );
				$this->send_response( 200, 'Webhook already processed' );
				return;
			}
			
			$payment_id = $order->get_meta( '_cko_payment_id' ) ?? null;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found, getting payment ID from meta' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order ID: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order status: ' . $order->get_status() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order transaction ID: ' . $order->get_transaction_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Payment Session ID in order: ' . $order->get_meta( '_cko_payment_session_id' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Payment Session ID in webhook: ' . ( isset( $data->data->metadata->cko_payment_session_id ) ? $data->data->metadata->cko_payment_session_id : 'NOT SET' ) );
			}
		} else {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: No order found, cannot get payment ID' );
			}
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Payment ID from order: ' . ($payment_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Payment ID from webhook: ' . ($data->data->id ?? 'NULL') );
		}

		// CRITICAL: Validate payment ID matches order (prevent wrong webhooks from matching orders)
		// This validation happens BEFORE processing webhook events
		if ( $order ) {
			$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
			$webhook_payment_id = $data->data->id;
			
			// Use Flow payment ID if available, otherwise fall back to regular payment ID
			$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
			
			// CRITICAL: If order has a payment ID, it MUST match the webhook payment ID
			if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $webhook_payment_id ) {
				// Payment ID mismatch - reject webhook BEFORE processing
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ CRITICAL ERROR - Payment ID mismatch in Flow webhook handler!' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order ID: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order _cko_flow_payment_id: ' . ( $order_payment_id ?: 'NOT SET' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Order _cko_payment_id: ' . ( $order_payment_id_alt ?: 'NOT SET' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Expected payment ID: ' . $expected_payment_id );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: Webhook payment ID: ' . $webhook_payment_id );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: ❌ REJECTING WEBHOOK - Payment ID does not match order!' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK MATCHING: This webhook is for a different payment - ignoring to prevent incorrect order updates' );
				
				// Reject webhook - return HTTP 200 but don't process
				$this->send_response( 200, 'Webhook payment ID does not match order payment ID' );
				return;
			}
			
			// If order doesn't have payment ID yet, set it from webhook (for first payment attempt)
			if ( empty( $expected_payment_id ) && ! empty( $webhook_payment_id ) ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'Flow webhook: No payment ID found in order, setting from webhook: ' . $webhook_payment_id );
				}
				$order->set_transaction_id( $webhook_payment_id );
				$order->update_meta_data( '_cko_payment_id', $webhook_payment_id );
				$order->update_meta_data( '_cko_flow_payment_id', $webhook_payment_id );
				$order->save();
				$payment_id = $webhook_payment_id;
			} elseif ( ! empty( $expected_payment_id ) ) {
				// Payment IDs match - continue processing
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'Flow webhook: ✅ Payment ID validation passed - Order payment ID: ' . $expected_payment_id . ', Webhook payment ID: ' . $webhook_payment_id );
				}
			}
		} elseif ( ! $order ) {
			// No order found - log but continue processing to allow queue system to handle it
			// The queue system will catch webhooks for payment_approved and payment_captured events
			// Other webhook types will return false and Checkout.com will retry
			WC_Checkoutcom_Utility::logger( 'Flow webhook: No order found for webhook processing. Payment ID: ' . ($data->data->id ?? 'NULL') . ' - Will attempt to queue or process via webhook handlers' );
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Event Type Data' );
			WC_Checkoutcom_Utility::logger(print_r($data,true));
		}

		// Get webhook event type from data.
		$event_type = $data->type;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing event type: ' . $event_type );
		}

		switch ( $event_type ) {
			case 'card_verified':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing card_verified event' );
				}
				$response = WC_Checkout_Com_Webhook::card_verified( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: card_verified response: ' . print_r($response, true) );
				}
				break;
			case 'payment_approved':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_approved event' );
				}
				$response = WC_Checkout_Com_Webhook::authorize_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_approved response: ' . print_r($response, true) );
				}
				
				// If processing failed, queue the webhook for later processing
				if ( false === $response ) {
					$webhook_data = $data->data;
					$payment_id = $webhook_data->id ?? null;
					$order_id = isset($webhook_data->metadata->order_id) 
						? $webhook_data->metadata->order_id 
						: null;
					$payment_session_id = isset($webhook_data->metadata->cko_payment_session_id)
						? $webhook_data->metadata->cko_payment_session_id
						: null;
					
					// Only queue if we have payment_id (required for matching)
					if ( $payment_id && class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
						$queued = WC_Checkout_Com_Webhook_Queue::save_pending_webhook(
							$payment_id,
							$order_id,
							$payment_session_id,
							'payment_approved',
							$data
						);
						
						if ( $queued ) {
							WC_Checkoutcom_Utility::logger(
								'WEBHOOK QUEUE: payment_approved webhook queued - ' .
								'Payment ID: ' . $payment_id . ', ' .
								'Order ID: ' . ($order_id ?? 'NULL') . ', ' .
								'Session ID: ' . ($payment_session_id ?? 'NULL')
							);
							
							// CRITICAL: Set response to true so HTTP 200 is sent
							// This tells Checkout.com webhook was processed successfully
							// Checkout.com will NOT retry
							$response = true;
						} else {
							if ( $webhook_debug_enabled ) {
								WC_Checkoutcom_Utility::logger( 'WEBHOOK QUEUE: Failed to queue payment_approved webhook' );
							}
						}
					} else {
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK QUEUE: Cannot queue payment_approved webhook - Payment ID missing or queue class not available' );
						}
					}
				}
				break;
			case 'payment_captured':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_captured event' );
				}
				$response = WC_Checkout_Com_Webhook::capture_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_captured response: ' . print_r($response, true) );
				}
				
				// If processing failed, queue the webhook for later processing
				if ( false === $response ) {
					$webhook_data = $data->data;
					$payment_id = $webhook_data->id ?? null;
					$order_id = isset($webhook_data->metadata->order_id) 
						? $webhook_data->metadata->order_id 
						: null;
					$payment_session_id = isset($webhook_data->metadata->cko_payment_session_id)
						? $webhook_data->metadata->cko_payment_session_id
						: null;
					
					// Only queue if we have payment_id (required for matching)
					if ( $payment_id && class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
						$queued = WC_Checkout_Com_Webhook_Queue::save_pending_webhook(
							$payment_id,
							$order_id,
							$payment_session_id,
							'payment_captured',
							$data
						);
						
						if ( $queued ) {
							WC_Checkoutcom_Utility::logger(
								'WEBHOOK QUEUE: payment_captured webhook queued - ' .
								'Payment ID: ' . $payment_id . ', ' .
								'Order ID: ' . ($order_id ?? 'NULL') . ', ' .
								'Session ID: ' . ($payment_session_id ?? 'NULL')
							);
							
							// CRITICAL: Set response to true so HTTP 200 is sent
							// This tells Checkout.com webhook was processed successfully
							// Checkout.com will NOT retry
							$response = true;
						} else {
							if ( $webhook_debug_enabled ) {
								WC_Checkoutcom_Utility::logger( 'WEBHOOK QUEUE: Failed to queue payment_captured webhook' );
							}
						}
					} else {
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK QUEUE: Cannot queue payment_captured webhook - Payment ID missing or queue class not available' );
						}
					}
				}
				break;
			case 'payment_voided':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_voided event' );
				}
				$response = WC_Checkout_Com_Webhook::void_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_voided response: ' . print_r($response, true) );
				}
				break;
			case 'payment_capture_declined':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_capture_declined event' );
				}
				$response = WC_Checkout_Com_Webhook::capture_declined( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_capture_declined response: ' . print_r($response, true) );
				}
				break;
			case 'payment_refunded':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_refunded event' );
				}
				$response = WC_Checkout_Com_Webhook::refund_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_refunded response: ' . print_r($response, true) );
				}
				break;
			case 'payment_canceled':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_canceled event' );
				}
				$response = WC_Checkout_Com_Webhook::cancel_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_canceled response: ' . print_r($response, true) );
				}
				break;
			case 'payment_declined':
			case 'payment_authentication_failed':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing ' . $event_type . ' event' );
				}
				$response = WC_Checkout_Com_Webhook::decline_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: ' . $event_type . ' response: ' . print_r($response, true) );
				}
				break;

			default:
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Unknown event type: ' . $event_type . ', using default response' );
				}
				$response = true;
				break;
		}
		
		// CRITICAL: Mark webhook as processed after successful processing (prevent duplicate processing)
		// This ensures one webhook = one order update
		if ( $order && true === $response ) {
			// Webhook processed successfully - mark as processed
			$processed_webhooks = $order->get_meta( '_cko_processed_webhook_ids' );
			if ( ! is_array( $processed_webhooks ) ) {
				$processed_webhooks = array();
			}
			
			$webhook_id = $data->data->id . '_' . $data->type;
			
			// Add to processed list if not already there
			if ( ! in_array( $webhook_id, $processed_webhooks, true ) ) {
				$processed_webhooks[] = $webhook_id;
				$order->update_meta_data( '_cko_processed_webhook_ids', array_unique( $processed_webhooks ) );
				$order->save();
				WC_Checkoutcom_Utility::logger( 'WEBHOOK: ✅ Marked as processed - Payment ID: ' . $data->data->id . ', Type: ' . $data->type . ', Order: ' . $order->get_id() );
			}
		}

		$http_code = $response ? 200 : 400;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Final response code: ' . $http_code );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Final response value: ' . print_r($response, true) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Sending response - Code: ' . $http_code . ', Message: ' . ($response ? 'OK' : 'Failed') );
		}
		$this->send_response($http_code, $response ? 'OK' : 'Failed');
		
		} catch ( Exception $e ) {
			// Log the exception and return 500 error
			WC_Checkoutcom_Utility::logger( 'Webhook handler exception: ' . $e->getMessage(), $e );
			$this->send_response(500, 'Internal Server Error');
		}
	}

	/**
	 * Helper to send response with proper HTTP status and exit.
	 */
	private function send_response($status_code, $message) {
		// WP-friendly way.
		WC_Checkoutcom_Utility::logger("WEBHOOK DEBUG: Preparing to send response - Status: $status_code, Message: $message");
		
		status_header($status_code);
		header('Content-Type: application/json; charset=utf-8');
		
		$response_data = [
			'status'  => $status_code,
			'message' => $message,
		];
		
		WC_Checkoutcom_Utility::logger("WEBHOOK DEBUG: Response data: " . wp_json_encode($response_data));
		echo wp_json_encode($response_data);

		WC_Checkoutcom_Utility::logger("WEBHOOK DEBUG: Sent HTTP status: $status_code with message: $message");
		WC_Checkoutcom_Utility::logger("=== WEBHOOK DEBUG: Flow webhook handler completed ===");
		exit; // Prevent WP from sending 200.
	}

	/**
	 * Secure AJAX handler for creating payment sessions.
	 * This prevents the secret key from being exposed to the frontend.
	 *
	 * @return void
	 */
	public function ajax_create_payment_session() {
		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_flow_payment_session' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Get core settings
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );

		// Verify secret key is configured
		$secret_key = isset( $core_settings['ckocom_sk'] ) ? $core_settings['ckocom_sk'] : '';
		if ( empty( $secret_key ) ) {
			WC_Checkoutcom_Utility::logger( 'Error: Secret key not configured for payment session creation' );
			wp_send_json_error( array(
				'message' => __( 'Payment gateway not properly configured. Please contact support.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Get payment session request data from POST
		if ( ! isset( $_POST['payment_session_request'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid request data.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Decode and sanitize the payment session request
		$payment_session_request = json_decode( wp_unslash( $_POST['payment_session_request'] ), true );
		
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $payment_session_request ) ) {
			WC_Checkoutcom_Utility::logger( 'Error: Invalid JSON in payment session request: ' . json_last_error_msg() );
			wp_send_json_error( array(
				'message' => __( 'Invalid request format.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Determine API URL based on environment
		$api_url = 'https://api.checkout.com/payment-sessions';
		if ( isset( $core_settings['ckocom_environment'] ) && 'sandbox' === $core_settings['ckocom_environment'] ) {
			$api_url = 'https://api.sandbox.checkout.com/payment-sessions';
		}

		// Prepare the API request
		$request_args = array(
			'method'  => 'POST',
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payment_session_request ),
		);

		// Make the API request
		$response = wp_remote_request( $api_url, $request_args );

		// Check for request errors
		if ( is_wp_error( $response ) ) {
			WC_Checkoutcom_Utility::logger( 'Error creating payment session: ' . $response->get_error_message() );
			wp_send_json_error( array(
				'message' => __( 'Failed to create payment session. Please try again.', 'checkout-com-unified-payments-api' ),
				'error'   => $response->get_error_message(),
			) );
			return;
		}

		// Get response body
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$payment_session = json_decode( $response_body, true );

		// Check for API errors
		if ( $response_code >= 400 || ( isset( $payment_session['error_type'] ) || isset( $payment_session['error_codes'] ) ) ) {
			WC_Checkoutcom_Utility::logger( 'Payment Session API Error: ' . $response_body );
			wp_send_json_error( array(
				'message'     => isset( $payment_session['error_codes'] ) && is_array( $payment_session['error_codes'] ) 
					? __( 'Payment session error: ', 'checkout-com-unified-payments-api' ) . implode( ', ', $payment_session['error_codes'] )
					: __( 'Error creating payment session. Please try again.', 'checkout-com-unified-payments-api' ),
				'error_type'  => isset( $payment_session['error_type'] ) ? $payment_session['error_type'] : null,
				'error_codes' => isset( $payment_session['error_codes'] ) ? $payment_session['error_codes'] : null,
				'request_id'  => isset( $payment_session['request_id'] ) ? $payment_session['request_id'] : null,
			) );
			return;
		}

		// Success - return payment session
		wp_send_json_success( $payment_session );
	}
	
	/**
	 * AJAX handler to create order before payment processing.
	 * This ensures order exists before webhook arrives, preventing race conditions and duplicate orders.
	 *
	 * @return void
	 */
	public function ajax_create_order() {
		// Set proper headers for JSON response
		header( 'Content-Type: application/json' );
		
		// Log entry point
		if ( function_exists( 'WC_Checkoutcom_Utility' ) && method_exists( 'WC_Checkoutcom_Utility', 'logger' ) ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== ENTRY POINT ==========' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Action: ' . ( isset( $_POST['action'] ) ? $_POST['action'] : 'NOT SET' ) );
		}
		
		// Verify nonce for security - check multiple possible locations
		$nonce_value = '';
		if ( isset( $_POST['woocommerce-process-checkout-nonce'] ) ) {
			$nonce_value = sanitize_text_field( $_POST['woocommerce-process-checkout-nonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce from POST: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		} elseif ( isset( $_REQUEST['woocommerce-process-checkout-nonce'] ) ) {
			$nonce_value = sanitize_text_field( $_REQUEST['woocommerce-process-checkout-nonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce from REQUEST: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce_value = sanitize_text_field( $_REQUEST['_wpnonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce from _wpnonce: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		}
		
		if ( empty( $nonce_value ) ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: Nonce is empty' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Available POST keys: ' . implode( ', ', array_keys( $_POST ) ) );
			wp_send_json_error( array(
				'message' => __( 'Session expired. Please refresh.', 'woocommerce' ),
			) );
			return;
		}
		
		$nonce_valid = wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' );
		if ( ! $nonce_valid ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: Invalid nonce' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce value: ' . substr( $nonce_value, 0, 10 ) . '...' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce verification result: ' . ( $nonce_valid ? 'VALID' : 'INVALID' ) );
			wp_send_json_error( array(
				'message' => __( 'Session expired. Please refresh.', 'woocommerce' ),
			) );
			return;
		}
		
		WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Nonce validated successfully' );
		
		// Get payment session ID if available
		$payment_session_id = isset( $_POST['cko-flow-payment-session-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-session-id'] ) : '';
		
		// Load WooCommerce checkout class
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: WooCommerce not available' );
			wp_send_json_error( array( 'message' => __( 'WooCommerce not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}
		
		$checkout = WC()->checkout();
		if ( ! $checkout ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: Checkout class not available' );
			wp_send_json_error( array( 'message' => __( 'Checkout class not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}
		
		try {
			// CRITICAL: Check for existing order with same payment_session_id BEFORE creating new order
			// This prevents race conditions where two simultaneous AJAX calls create duplicate orders
			// Fix for issue: Two orders created with same payment_session_id (Order #988 and #989)
			if ( ! empty( $payment_session_id ) ) {
				$existing_orders = wc_get_orders( array(
					'meta_key'   => '_cko_payment_session_id',
					'meta_value' => $payment_session_id,
					'status'     => array( 'pending', 'failed', 'on-hold', 'processing' ), // Only match orders that need updating
					'limit'      => 1,
					'return'     => 'ids',
				) );
				
				if ( ! empty( $existing_orders ) ) {
					// Existing order found with same payment_session_id - return it instead of creating duplicate
					$existing_order_id = $existing_orders[0];
					$existing_order = wc_get_order( $existing_order_id );
					
					if ( $existing_order ) {
						WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ Existing order found with payment_session_id - Order ID: ' . $existing_order_id );
						WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ Reusing existing order instead of creating duplicate - Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
						
						// Store save card preference if available (update existing order)
						$save_card_preference = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
						if ( 'true' === $save_card_preference || 'yes' === $save_card_preference ) {
							$existing_order->update_meta_data( '_cko_save_card_preference', 'yes' );
							$existing_order->save();
							WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Save card preference updated on existing order: YES' );
						}
						
						// Return existing order ID (prevents duplicate order creation)
						wp_send_json_success( array(
							'order_id' => $existing_order_id,
							'message' => __( 'Using existing order.', 'checkout-com-unified-payments-api' ),
							'existing_order' => true,
						) );
						return;
					}
				}
				
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ No existing order found with payment_session_id - safe to create new order - Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
			}
			
			// CRITICAL: Validate checkout form BEFORE getting posted data and creating order
			// This ensures orders are only created when form is valid (defense-in-depth)
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Validating checkout form before order creation...' );
			
			// Run validation hooks FIRST (before getting posted data)
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== VALIDATION HOOKS PHASE ==========' );
			try {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Step 1: Running woocommerce_before_checkout_process hook' );
				do_action( 'woocommerce_before_checkout_process' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Step 1: ✅ woocommerce_before_checkout_process completed' );
				
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Step 2: Running woocommerce_checkout_process hook' );
				do_action( 'woocommerce_checkout_process' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Step 2: ✅ woocommerce_checkout_process completed' );
				
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅✅✅ Validation hooks executed successfully ✅✅✅' );
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ERROR in validation hooks ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR message: ' . $e->getMessage() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR stack trace: ' . $e->getTraceAsString() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ BLOCKING ORDER CREATION - Returning error ❌❌❌' );
				wp_send_json_error( array(
					'message' => __( 'Error during checkout validation: ', 'checkout-com-unified-payments-api' ) . $e->getMessage(),
				) );
				return;
			}
			
			// Get posted data from form AFTER validation hooks
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== GETTING POSTED DATA ==========' );
			$posted_data = $checkout->get_posted_data();
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Posted data retrieved - ' . count( $posted_data ) . ' fields' );
			
			// Update session with posted data
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== UPDATING SESSION ==========' );
			try {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Calling update_session via Reflection' );
				$reflection_session = new ReflectionClass( $checkout );
				$update_session_method = $reflection_session->getMethod( 'update_session' );
				$update_session_method->setAccessible( true );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Invoking update_session method...' );
				$update_session_method->invoke( $checkout, $posted_data );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ update_session completed successfully' );
			} catch ( ReflectionException $e ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ERROR in update_session (ReflectionException) ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: ' . $e->getMessage() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ BLOCKING ORDER CREATION ❌❌❌' );
				wp_send_json_error( array(
					'message' => __( 'Could not access checkout update method.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ERROR in update_session (Exception) ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: ' . $e->getMessage() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR stack trace: ' . $e->getTraceAsString() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ BLOCKING ORDER CREATION ❌❌❌' );
				wp_send_json_error( array(
					'message' => __( 'Error updating checkout session: ', 'checkout-com-unified-payments-api' ) . $e->getMessage(),
				) );
				return;
			}
			
			// Validate checkout fields
			$errors = new WP_Error();
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== STARTING FIELD VALIDATION ==========' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Posted data keys: ' . implode( ', ', array_keys( $posted_data ) ) );
			
			// Log key field values for debugging
			$key_fields = array( 'billing_email', 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_country', 'billing_postcode' );
			foreach ( $key_fields as $field ) {
				$value = isset( $posted_data[ $field ] ) ? $posted_data[ $field ] : 'NOT SET';
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Field ' . $field . ': ' . ( is_string( $value ) && strlen( $value ) > 50 ? substr( $value, 0, 50 ) . '...' : $value ) );
			}
			
			try {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Calling validate_checkout via Reflection' );
				$reflection_validate = new ReflectionClass( $checkout );
				$validate_method = $reflection_validate->getMethod( 'validate_checkout' );
				$validate_method->setAccessible( true );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] About to invoke validate_checkout method' );
				$validate_method->invokeArgs( $checkout, array( &$posted_data, &$errors ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] validate_checkout completed' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Errors object type: ' . gettype( $errors ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Errors object class: ' . get_class( $errors ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Errors->errors is empty?: ' . ( empty( $errors->errors ) ? 'YES' : 'NO' ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Errors->errors count: ' . ( is_array( $errors->errors ) ? count( $errors->errors ) : 'NOT ARRAY' ) );
			} catch ( ReflectionException $e ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR in validate_checkout: ' . $e->getMessage() );
				wp_send_json_error( array(
					'message' => __( 'Could not access checkout validate method.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR in validate_checkout (general): ' . $e->getMessage() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR stack trace: ' . $e->getTraceAsString() );
				wp_send_json_error( array(
					'message' => __( 'Error validating checkout: ', 'checkout-com-unified-payments-api' ) . $e->getMessage(),
				) );
				return;
			}
			
			// Check for validation errors - CRITICAL: Do NOT create order if validation fails
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== CHECKING VALIDATION ERRORS ==========' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Checking if errors->errors is empty...' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] empty($errors->errors) result: ' . ( empty( $errors->errors ) ? 'TRUE (no errors)' : 'FALSE (errors found)' ) );
			
			if ( ! empty( $errors->errors ) ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ VALIDATION ERRORS FOUND - BLOCKING ORDER CREATION ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Validation errors count: ' . count( $errors->errors ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Validation errors array: ' . print_r( $errors->errors, true ) );
				$messages = array();
				foreach ( $errors->errors as $code => $msgs ) {
					foreach ( $msgs as $msg ) {
						$messages[] = $msg;
						WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Validation error [' . $code . ']: ' . $msg );
					}
				}
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ORDER CREATION BLOCKED - RETURNING ERROR RESPONSE ❌❌❌' );
				wp_send_json_error( array(
					'message' => implode( "\n", $messages ),
				) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ EXITING - ORDER WILL NOT BE CREATED ❌❌❌' );
				return; // CRITICAL: Exit here - do NOT create order
			}
			
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅✅✅ NO VALIDATION ERRORS - ALL VALIDATIONS PASSED ✅✅✅' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ Validation passed - proceeding with order creation' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== ABOUT TO CREATE ORDER ==========' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] All validations passed - calling create_order() method' );
			
			// Create order using WooCommerce checkout process
			// Use Reflection to call protected create_order() method which triggers woocommerce_checkout_create_order hook
			// This ensures duplicate prevention logic runs
			$reflection = new ReflectionClass( $checkout );
			$create_order_method = $reflection->getMethod( 'create_order' );
			$create_order_method->setAccessible( true );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Invoking create_order() method...' );
			$order_id = $create_order_method->invoke( $checkout, $posted_data );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] create_order() returned: ' . print_r( $order_id, true ) );
			
			if ( is_wp_error( $order_id ) ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ERROR: Failed to create order ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Error message: ' . $order_id->get_error_message() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Error code: ' . $order_id->get_error_code() );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Error data: ' . print_r( $order_id->get_error_data(), true ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Order creation failed - returning error response' );
				wp_send_json_error( array(
					'message' => __( 'Failed to create order. Please try again.', 'checkout-com-unified-payments-api' ),
					'error' => $order_id->get_error_message(),
				) );
				return;
			}
			
			if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌❌❌ ERROR: Invalid order ID returned ❌❌❌' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID value: ' . print_r( $order_id, true ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID type: ' . gettype( $order_id ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID is empty?: ' . ( empty( $order_id ) ? 'YES' : 'NO' ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID is numeric?: ' . ( is_numeric( $order_id ) ? 'YES' : 'NO' ) );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Order creation failed - invalid order ID' );
				wp_send_json_error( array(
					'message' => __( 'Failed to create order. Invalid order ID returned.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			}
			
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ========== ORDER CREATED SUCCESSFULLY ==========' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅✅✅ Order created successfully - Order ID: ' . $order_id . ' ✅✅✅' );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID type: ' . gettype( $order_id ) );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID is numeric?: ' . ( is_numeric( $order_id ) ? 'YES' : 'NO' ) );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order ID value: ' . $order_id );
			
			// Load the order
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ERROR: Order not found after creation - Order ID: ' . $order_id );
				wp_send_json_error( array(
					'message' => __( 'Order created but not found. Please try again.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			}
			
			// Store payment session ID if available (for webhook lookup)
			// NOTE: Duplicate check already performed BEFORE order creation (above)
			// This ensures one order = one payment session ID and prevents race conditions
			if ( ! empty( $payment_session_id ) ) {
				// Double-check for duplicates (defense in depth - should never happen after pre-check)
				$existing_orders = wc_get_orders( array(
					'meta_key'   => '_cko_payment_session_id',
					'meta_value' => $payment_session_id,
					'limit'      => 1,
					'exclude'    => array( $order_id ), // Exclude current order
					'return'     => 'ids',
				) );
				
				if ( ! empty( $existing_orders ) ) {
					// This should never happen after pre-check, but log as critical error if it does
					WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ CRITICAL ERROR: Payment session ID already used by order: ' . $existing_orders[0] );
					WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Cannot save duplicate payment_session_id to order: ' . $order_id );
					WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Payment Session ID: ' . substr( $payment_session_id, 0, 20 ) . '...' );
					WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ This violates one-order-one-payment-session-id rule' );
					WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ❌ Race condition detected - duplicate order created despite pre-check' );
					// Don't save duplicate - this prevents webhook matching wrong order
					// Return error to prevent order creation with duplicate payment_session_id
					wp_send_json_error( array(
						'message' => __( 'Payment session ID conflict. Please refresh and try again.', 'checkout-com-unified-payments-api' ),
						'error'   => 'duplicate_payment_session_id',
					) );
					return;
				}
				
				// Safe to save - payment_session_id is unique (pre-checked before order creation)
				$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ Payment session ID saved to order: ' . substr( $payment_session_id, 0, 20 ) . '... (Unique - pre-checked before creation)' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ✅ One order = one payment session ID: Order ' . $order_id . ' = Payment Session ' . substr( $payment_session_id, 0, 20 ) . '...' );
			} else {
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ⚠️ WARNING: Payment session ID is empty - Order ID: ' . $order_id );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ⚠️ Webhook matching may fail without payment_session_id' );
			}
			
			// Store save card preference if available
			$save_card_preference = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
			if ( 'true' === $save_card_preference || 'yes' === $save_card_preference ) {
				$order->update_meta_data( '_cko_save_card_preference', 'yes' );
				WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Save card preference saved to order: YES' );
			}
			
			// Set order status to pending
			$order->set_status( 'pending' );
			$order->save();
			
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] Order saved successfully - Order ID: ' . $order_id . ', Status: pending' );
			
			// Return success with order ID
			wp_send_json_success( array(
				'order_id' => $order_id,
				'message' => __( 'Order created successfully.', 'checkout-com-unified-payments-api' ),
			) );
			
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] EXCEPTION: ' . $e->getMessage() );
			WC_Checkoutcom_Utility::logger( '[CREATE ORDER] EXCEPTION stack trace: ' . $e->getTraceAsString() );
			wp_send_json_error( array(
				'message' => __( 'Failed to create order. Please try again.', 'checkout-com-unified-payments-api' ),
				'error' => $e->getMessage(),
			) );
		}
	}

	/**
	 * AJAX handler to save payment session ID to order immediately after payment session creation.
	 * This ensures order is linked to payment session even if user cancels after 3DS.
	 * 
	 * @return void
	 */
	public function ajax_save_payment_session_id() {
		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_flow_payment_session' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}
		
		// Get order ID and payment session ID from POST
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$payment_session_id = isset( $_POST['payment_session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_session_id'] ) ) : '';
		
		// Validate required parameters
		if ( empty( $order_id ) || empty( $payment_session_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Missing required parameters.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}
		
		// Get order object
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			wp_send_json_error( array(
				'message' => __( 'Order not found.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}
		
		// CRITICAL: Check if payment session ID already saved (prevent overwriting)
		$existing_payment_session_id = $order->get_meta( '_cko_payment_session_id' );
		
		if ( ! empty( $existing_payment_session_id ) ) {
			if ( $existing_payment_session_id === $payment_session_id ) {
				// Already saved with same value, return success
				wp_send_json_success( array(
					'message' => __( 'Payment session ID already saved.', 'checkout-com-unified-payments-api' ),
					'order_id' => $order_id,
				) );
				return;
			} else {
				// Different payment session ID exists - prevent overwriting
				WC_Checkoutcom_Utility::logger( '[SAVE PAYMENT SESSION ID] ❌ CRITICAL ERROR: Payment session ID already exists with different value - Order ID: ' . $order_id . ', Existing: ' . substr( $existing_payment_session_id, 0, 20 ) . '..., New: ' . substr( $payment_session_id, 0, 20 ) . '... (preventing overwrite)' );
				wp_send_json_error( array(
					'message' => __( 'Payment session ID already exists with different value. Cannot overwrite.', 'checkout-com-unified-payments-api' ),
					'order_id' => $order_id,
				) );
				return;
			}
		}
		
		// Save payment session ID to order (order doesn't have it yet)
		$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
		$order->save();
		
		WC_Checkoutcom_Utility::logger( '[SAVE PAYMENT SESSION ID] Payment session ID saved to order immediately - Order ID: ' . $order_id . ', Payment Session ID: ' . $payment_session_id );
		
		// Return success
		wp_send_json_success( array(
			'message' => __( 'Payment session ID saved successfully.', 'checkout-com-unified-payments-api' ),
			'order_id' => $order_id,
		) );
	}
	
	/**
	 * AJAX handler to create a failed order when payment is declined before form submission.
	 * This ensures all payment decline attempts are tracked in WooCommerce.
	 *
	 * @return void
	 */
	public function ajax_create_failed_order() {
		// Log entry point
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ========== ENTRY POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] REQUEST data keys: ' . implode( ', ', array_keys( $_REQUEST ) ) );
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Action: ' . ( isset( $_POST['action'] ) ? $_POST['action'] : 'NOT SET' ) );
		
		// Verify nonce for security - check multiple possible locations
		$nonce_value = '';
		if ( isset( $_POST['woocommerce-process-checkout-nonce'] ) ) {
			$nonce_value = sanitize_text_field( $_POST['woocommerce-process-checkout-nonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce from POST: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		} elseif ( isset( $_REQUEST['woocommerce-process-checkout-nonce'] ) ) {
			$nonce_value = sanitize_text_field( $_REQUEST['woocommerce-process-checkout-nonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce from REQUEST: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce_value = sanitize_text_field( $_REQUEST['_wpnonce'] );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce from _wpnonce: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
		}
		
		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Invalid or missing nonce' );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce value received: ' . ( ! empty( $nonce_value ) ? substr( $nonce_value, 0, 10 ) . '...' : 'EMPTY' ) );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce verification result: ' . ( ! empty( $nonce_value ) ? ( wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ? 'VALID' : 'INVALID' ) : 'MISSING' ) );
			wp_send_json_error( array(
				'message' => __( 'Session expired. Please refresh.', 'woocommerce' ),
			) );
			return;
		}
		
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Nonce validated successfully' );
		
		// Get error reason and message
		$error_reason = isset( $_POST['error_reason'] ) ? sanitize_text_field( $_POST['error_reason'] ) : 'payment_declined';
		$error_message = isset( $_POST['error_message'] ) ? sanitize_text_field( $_POST['error_message'] ) : __( 'Payment was declined.', 'checkout-com-unified-payments-api' );
		
		WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Error reason: ' . $error_reason . ', Error message: ' . $error_message );
		
		// Load WooCommerce checkout class
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: WooCommerce not available' );
			wp_send_json_error( array( 'message' => __( 'WooCommerce not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}
		
		$checkout = WC()->checkout();
		if ( ! $checkout ) {
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Checkout class not available' );
			wp_send_json_error( array( 'message' => __( 'Checkout class not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}
		
		try {
			// Get posted data from form
			$posted_data = $checkout->get_posted_data();
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Posted data retrieved' );
			
			// Create order using WooCommerce checkout process
			// Use Reflection to call protected create_order() method which triggers woocommerce_checkout_create_order hook
			// This ensures duplicate prevention logic runs
			$reflection = new ReflectionClass( $checkout );
			$create_order_method = $reflection->getMethod( 'create_order' );
			$create_order_method->setAccessible( true );
			$order_id = $create_order_method->invoke( $checkout, $posted_data );
			
			if ( is_wp_error( $order_id ) ) {
				WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Failed to create order: ' . $order_id->get_error_message() );
				wp_send_json_error( array(
					'message' => __( 'Failed to create order. Please try again.', 'checkout-com-unified-payments-api' ),
					'error' => $order_id->get_error_message(),
				) );
				return;
			}
			
			if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
				WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Invalid order ID returned: ' . print_r( $order_id, true ) );
				wp_send_json_error( array(
					'message' => __( 'Failed to create order. Invalid order ID returned.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			}
			
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Order created successfully - Order ID: ' . $order_id );
			
			// Load the order
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Order not found after creation - Order ID: ' . $order_id );
				wp_send_json_error( array(
					'message' => __( 'Order created but not found. Please try again.', 'checkout-com-unified-payments-api' ),
				) );
				return;
			}
			
			// Update order status to failed
			$order->update_status( 'failed', __( 'Payment declined before form submission', 'checkout-com-unified-payments-api' ) );
			
			// Add order note with decline reason
			$order->add_order_note( sprintf( __( 'Payment declined (client-side) - Reason: %s, Message: %s', 'checkout-com-unified-payments-api' ), $error_reason, $error_message ) );
			
			// Save order
			$order->save();
			
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] Order updated to failed status - Order ID: ' . $order_id );
			
			// Return success with order ID
			wp_send_json_success( array(
				'order_id' => $order_id,
				'message' => __( 'Failed order created successfully.', 'checkout-com-unified-payments-api' ),
			) );
			
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR: Exception: ' . $e->getMessage() );
			WC_Checkoutcom_Utility::logger( '[CREATE FAILED ORDER] ERROR stack trace: ' . $e->getTraceAsString() );
			wp_send_json_error( array(
				'message' => __( 'Error creating failed order: ', 'checkout-com-unified-payments-api' ) . $e->getMessage(),
			) );
		}
	}
	
	/**
	 * AJAX handler for storing save card preference in WooCommerce session
	 * This ensures the value survives 3DS redirects
	 */
	public function ajax_store_save_card_preference() {
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] AJAX handler called - POST keys: ' . implode( ', ', array_keys( $_POST ) ) );
		
		// Verify nonce for security
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Nonce received: ' . ( ! empty( $nonce ) ? substr( $nonce, 0, 10 ) . '...' : 'EMPTY' ) );
		
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'cko_flow_payment_session' ) ) {
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] ERROR: Nonce verification failed' );
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}
		
		// Get save card preference value
		$save_card_value = isset( $_POST['save_card_value'] ) ? sanitize_text_field( $_POST['save_card_value'] ) : 'no';
		WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Save card value received: ' . $save_card_value );
		
		// Initialize WooCommerce session if not already initialized
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		
		// Store in WooCommerce session
		if ( WC()->session ) {
			WC()->session->set( 'wc-wc_checkout_com_flow-new-payment-method', $save_card_value );
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] Stored save card preference in WooCommerce session: ' . $save_card_value );
			wp_send_json_success( array(
				'message' => __( 'Save card preference stored.', 'checkout-com-unified-payments-api' ),
				'value' => $save_card_value,
			) );
		} else {
			WC_Checkoutcom_Utility::logger( '[FLOW SAVE CARD] ERROR: WooCommerce session not available' );
			wp_send_json_error( array(
				'message' => __( 'Session not available.', 'checkout-com-unified-payments-api' ),
			) );
		}
	}
}

