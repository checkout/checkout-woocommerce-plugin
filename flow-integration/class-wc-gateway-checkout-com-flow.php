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
		$this->title              = !empty( $core_settings['title'] ) ? __( $core_settings['title'], 'checkout-com-unified-payments-api' ) : __( 'Checkout.com', 'checkout-com-unified-payments-api' );
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
									console.log('[FLOW] Hiding saved cards accordion for MOTO order');
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
									console.log('[FLOW] Hiding save to account checkbox for MOTO order');
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
									
									console.log('[FLOW PHP] Payment method label enhanced');
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
									console.log('[FLOW PHP] Save card label customized');
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
									
									console.log('[FLOW PHP] Save card checkbox styled with Flow colors');
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
								console.log('[FLOW PHP] Removed "Use a new payment method" button');
							}
							
							// Remove immediately and after checkout updates
							removeNewPaymentMethodButton();
							$(document.body).on('updated_checkout', function() {
								setTimeout(removeNewPaymentMethodButton, 100);
							});
							
							// Apply Flow customization colors to payment label and saved cards
							function applyFlowCustomization() {
								if (typeof window.appearance === 'undefined') {
									console.log('[FLOW PHP] Waiting for appearance settings...');
									setTimeout(applyFlowCustomization, 100);
									return;
								}
								
								const colors = window.appearance;
								const borderRadius = colors.borderRadius ? colors.borderRadius[0] : '8px';
								
								console.log('[FLOW PHP] Applying Flow customization colors:', colors);
								
								// Apply to payment method label
								const $label = $('.payment_method_wc_checkout_com_flow > label[for="payment_method_wc_checkout_com_flow"]');
								if ($label.length) {
									$label.css({
										'background-color': colors.colorFormBackground || '#ffffff',
										'border-color': colors.colorPrimary || '#186aff',
										'border-radius': borderRadius,
									});
									
									// Apply to title text
									$label.find('.payment-method-title').css({
										'color': colors.colorPrimary || '#1a1a1a',
										'font-family': colors.label?.fontFamily || 'inherit',
										'font-size': colors.label?.fontSize || '15px',
										'font-weight': colors.label?.fontWeight || '600',
									});
									
									// Apply to subtitle text
									$label.find('.payment-method-subtitle').css({
										'color': colors.colorSecondary || '#666',
										'font-family': colors.footnote?.fontFamily || 'inherit',
										'font-size': colors.footnote?.fontSize || '13px',
										'font-weight': colors.footnote?.fontWeight || '400',
									});
								}
								
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
								
								console.log('[FLOW PHP] Flow customization colors applied');
							}
							
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
				console.log('[FLOW PHP] Display order:', displayOrder, 'Total saved cards:', totalCount);
				console.log('[FLOW PHP] CSS will control saved cards visibility via data-saved-payment-order attribute');

				// Wrap saved payment methods in styled accordion container
				function wrapSavedCardsInAccordion() {
					if (totalCount > 0 && !$('.saved-cards-accordion-container').length && $savedMethods.length > 0) {
						// Wait for Flow container or use payment method container as fallback
						let $insertionPoint = $('#flow-container');
						let $fallbackPoint = $('.payment_method_wc_checkout_com_flow').first();
						
						if (!$insertionPoint.length) {
							$insertionPoint = $fallbackPoint;
						}
						
						console.log('[FLOW PHP] Insertion point found:', $insertionPoint.length > 0, 'Display order:', displayOrder);
						
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
									console.log('[FLOW PHP] Accordion inserted AFTER label (saved_cards_first)');
								} else {
									// Fallback: insert before first div
									const $firstDiv = $paymentMethodLi.children('div').first();
									if ($firstDiv.length) {
										$firstDiv.before(accordionHTML);
										console.log('[FLOW PHP] Accordion inserted BEFORE div (saved_cards_first fallback)');
									}
								}
							} else {
								// new_payment_first: Insert accordion AFTER the payment_box div (which contains flow-container)
								const $paymentBox = $paymentMethodLi.find('.payment_box').first();
								if ($paymentBox.length) {
									$paymentBox.after(accordionHTML);
									console.log('[FLOW PHP] Accordion inserted AFTER payment_box (new_payment_first)');
								} else {
									// Fallback: insert after label
									if ($label.length) {
										$label.after(accordionHTML);
										console.log('[FLOW PHP] Accordion inserted AFTER label (new_payment_first fallback)');
									}
								}
							}
							
							// Move saved payment methods into the accordion panel
							$savedMethods.each(function() {
								$(this).appendTo('.saved-cards-accordion-panel');
							});
							
							console.log('[FLOW PHP] Saved cards wrapped in accordion');
							
						// In saved_cards_first mode, auto-select the default or first saved card
						console.log('[FLOW PHP] Display order check:', displayOrder, '=== saved_cards_first?', displayOrder === 'saved_cards_first');
						if (displayOrder === 'saved_cards_first') {
							// First, check if there's a default card (WooCommerce marks it with checked="checked")
							let $defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"][checked="checked"]:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							
							// If no default found, try finding one that's already checked
							if (!$defaultCardRadio.length) {
								$defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							}
							
							// If still no default, select the first saved card
							if (!$defaultCardRadio.length) {
								$defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							}
							
							if ($defaultCardRadio.length) {
								$defaultCardRadio.prop('checked', true).trigger('change');
								const isDefault = $defaultCardRadio.attr('checked') === 'checked' || $defaultCardRadio.prop('defaultChecked');
								console.log('[FLOW PHP] Auto-selected ' + (isDefault ? 'default' : 'first') + ' saved card in saved_cards_first mode');
								console.log('[FLOW PHP] Selected card ID:', $defaultCardRadio.attr('id'));
								
								// CRITICAL: Set a flag to indicate saved card is selected
								window.flowSavedCardSelected = true;
								window.flowUserInteracted = false; // Reset interaction flag
							}
						} else if (displayOrder === 'new_payment_first') {
							// In new_payment_first mode, ensure no saved cards are auto-selected
							console.log('[FLOW PHP] new_payment_first mode - ensuring no saved cards are auto-selected');
							$('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').prop('checked', false);
							$('#wc-wc_checkout_com_flow-payment-token-new').prop('checked', true);
							window.flowSavedCardSelected = false;
							window.flowUserInteracted = false;
							console.log('[FLOW PHP] Unchecked all saved cards and selected new payment method');
						}
							
							// CRITICAL: After moving cards, ensure visibility rules are applied
							// Remove any inline display styles that might have been set during the move
							$('.saved-cards-accordion-container').each(function() {
								// Remove inline style to let CSS handle visibility
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from accordion container');
								}
							});
							
							$('.saved-cards-accordion-panel').each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from accordion panel');
								}
							});
							
							$savedMethods.each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from saved methods');
								}
							});
							
							console.log('[FLOW PHP] CSS will handle visibility via data-saved-payment-order:', displayOrder);
						} else {
							console.log('[FLOW PHP] No insertion point found, retrying...');
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
						console.log('[FLOW PHP] Accordion recreation already in progress, skipping...');
						return;
					}
					
					console.log('[FLOW PHP] updated_checkout fired - re-wrapping saved cards...');
					accordionRecreationInProgress = true;
					
					setTimeout(function() {
						// Check if accordion AND panel exist, and if saved methods are inside
						const $existingAccordion = $('.saved-cards-accordion-container');
						const $existingPanel = $('.saved-cards-accordion-panel');
						const $panelMethods = $('.saved-cards-accordion-panel .woocommerce-SavedPaymentMethods');
						const $allMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
						
						console.log('[FLOW PHP] After updated_checkout:', {
							accordion: $existingAccordion.length,
							panel: $existingPanel.length,
							methodsInPanel: $panelMethods.length,
							totalMethods: $allMethods.length
						});
						
						// Recreate if accordion doesn't exist OR panel is missing OR methods not in panel
						if ($existingAccordion.length === 0 || $existingPanel.length === 0 || ($allMethods.length > 0 && $panelMethods.length === 0)) {
							console.log('[FLOW PHP] Accordion incomplete after updated_checkout, recreating...');
							// Remove any incomplete accordion first
							$existingAccordion.remove();
							// Recreate with fresh saved methods
							wrapSavedCardsInAccordion();
						} else {
							console.log('[FLOW PHP] Accordion complete after updated_checkout');
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
								console.log('[FLOW PHP] Save card label customized (section 2)');
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
								
								console.log('[FLOW PHP] Save card checkbox styled with Flow colors (section 2)');
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
							console.log('[FLOW PHP] Removed "Use a new payment method" button (section 2)');
						}
						
						// Remove immediately and after checkout updates
						removeNewPaymentMethodButton();
						$(document.body).on('updated_checkout', function() {
							setTimeout(removeNewPaymentMethodButton, 100);
						});
						
						// Apply Flow customization colors to payment label and saved cards
						function applyFlowCustomization() {
							if (typeof window.appearance === 'undefined') {
								console.log('[FLOW PHP] Waiting for appearance settings (section 2)...');
								setTimeout(applyFlowCustomization, 100);
								return;
							}
							
							const colors = window.appearance;
							const borderRadius = colors.borderRadius ? colors.borderRadius[0] : '8px';
							
							console.log('[FLOW PHP] Applying Flow customization colors (section 2):', colors);
							
							// Apply to payment method label
							const $label = $('.payment_method_wc_checkout_com_flow > label[for="payment_method_wc_checkout_com_flow"]');
							if ($label.length) {
								$label.css({
									'background-color': colors.colorFormBackground || '#ffffff',
									'border-color': colors.colorPrimary || '#186aff',
									'border-radius': borderRadius,
								});
								
								// Apply to title text
								$label.find('.payment-method-title').css({
									'color': colors.colorPrimary || '#1a1a1a',
									'font-family': colors.label?.fontFamily || 'inherit',
									'font-size': colors.label?.fontSize || '15px',
									'font-weight': colors.label?.fontWeight || '600',
								});
								
								// Apply to subtitle text
								$label.find('.payment-method-subtitle').css({
									'color': colors.colorSecondary || '#666',
									'font-family': colors.footnote?.fontFamily || 'inherit',
									'font-size': colors.footnote?.fontSize || '13px',
									'font-weight': colors.footnote?.fontWeight || '400',
								});
							}
							
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
							
							console.log('[FLOW PHP] Flow customization colors applied (section 2)');
						}
						
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
				console.log('[FLOW PHP] Display order:', displayOrder, 'Total saved cards:', totalCount);
				console.log('[FLOW PHP] CSS will control saved cards visibility via data-saved-payment-order attribute');

				// Wrap saved payment methods in styled accordion container
				function wrapSavedCardsInAccordion() {
					if (totalCount > 0 && !$('.saved-cards-accordion-container').length && $savedMethods.length > 0) {
						// Wait for Flow container or use payment method container as fallback
						let $insertionPoint = $('#flow-container');
						let $fallbackPoint = $('.payment_method_wc_checkout_com_flow').first();
						
						if (!$insertionPoint.length) {
							$insertionPoint = $fallbackPoint;
						}
						
						console.log('[FLOW PHP] Insertion point found:', $insertionPoint.length > 0, 'Display order:', displayOrder);
						
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
									console.log('[FLOW PHP] Accordion inserted AFTER label (saved_cards_first)');
								} else {
									// Fallback: insert before first div
									const $firstDiv = $paymentMethodLi.children('div').first();
									if ($firstDiv.length) {
										$firstDiv.before(accordionHTML);
										console.log('[FLOW PHP] Accordion inserted BEFORE div (saved_cards_first fallback)');
									}
								}
							} else {
								// new_payment_first: Insert accordion AFTER the payment_box div (which contains flow-container)
								const $paymentBox = $paymentMethodLi.find('.payment_box').first();
								if ($paymentBox.length) {
									$paymentBox.after(accordionHTML);
									console.log('[FLOW PHP] Accordion inserted AFTER payment_box (new_payment_first)');
								} else {
									// Fallback: insert after label
									if ($label.length) {
										$label.after(accordionHTML);
										console.log('[FLOW PHP] Accordion inserted AFTER label (new_payment_first fallback)');
									}
								}
							}
							
							// Move saved payment methods into the accordion panel
							$savedMethods.each(function() {
								$(this).appendTo('.saved-cards-accordion-panel');
							});
							
							console.log('[FLOW PHP] Saved cards wrapped in accordion');
							
						// In saved_cards_first mode, auto-select the default or first saved card
						console.log('[FLOW PHP] Display order check:', displayOrder, '=== saved_cards_first?', displayOrder === 'saved_cards_first');
						if (displayOrder === 'saved_cards_first') {
							// First, check if there's a default card (WooCommerce marks it with checked="checked")
							let $defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"][checked="checked"]:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							
							// If no default found, try finding one that's already checked
							if (!$defaultCardRadio.length) {
								$defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							}
							
							// If still no default, select the first saved card
							if (!$defaultCardRadio.length) {
								$defaultCardRadio = $('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
							}
							
							if ($defaultCardRadio.length) {
								$defaultCardRadio.prop('checked', true).trigger('change');
								const isDefault = $defaultCardRadio.attr('checked') === 'checked' || $defaultCardRadio.prop('defaultChecked');
								console.log('[FLOW PHP] Auto-selected ' + (isDefault ? 'default' : 'first') + ' saved card in saved_cards_first mode');
								console.log('[FLOW PHP] Selected card ID:', $defaultCardRadio.attr('id'));
								
								// CRITICAL: Set a flag to indicate saved card is selected
								window.flowSavedCardSelected = true;
								window.flowUserInteracted = false; // Reset interaction flag
							}
						} else if (displayOrder === 'new_payment_first') {
							// In new_payment_first mode, ensure no saved cards are auto-selected
							console.log('[FLOW PHP] new_payment_first mode - ensuring no saved cards are auto-selected');
							$('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"]:not(#wc-wc_checkout_com_flow-payment-token-new)').prop('checked', false);
							$('#wc-wc_checkout_com_flow-payment-token-new').prop('checked', true);
							window.flowSavedCardSelected = false;
							window.flowUserInteracted = false;
							console.log('[FLOW PHP] Unchecked all saved cards and selected new payment method');
						}
							
							// CRITICAL: After moving cards, ensure visibility rules are applied
							// Remove any inline display styles that might have been set during the move
							$('.saved-cards-accordion-container').each(function() {
								// Remove inline style to let CSS handle visibility
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from accordion container');
								}
							});
							
							$('.saved-cards-accordion-panel').each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from accordion panel');
								}
							});
							
							$savedMethods.each(function() {
								if (this.style.display) {
									this.style.removeProperty('display');
									console.log('[FLOW PHP] Removed inline display style from saved methods');
								}
							});
							
							console.log('[FLOW PHP] CSS will handle visibility via data-saved-payment-order:', displayOrder);
						} else {
							console.log('[FLOW PHP] No insertion point found, retrying...');
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
						console.log('[FLOW PHP] Accordion recreation already in progress, skipping...');
						return;
					}
					
					console.log('[FLOW PHP] updated_checkout fired - re-wrapping saved cards...');
					accordionRecreationInProgress = true;
					
					setTimeout(function() {
						// Check if accordion AND panel exist, and if saved methods are inside
						const $existingAccordion = $('.saved-cards-accordion-container');
						const $existingPanel = $('.saved-cards-accordion-panel');
						const $panelMethods = $('.saved-cards-accordion-panel .woocommerce-SavedPaymentMethods');
						const $allMethods = $('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
						
						console.log('[FLOW PHP] After updated_checkout:', {
							accordion: $existingAccordion.length,
							panel: $existingPanel.length,
							methodsInPanel: $panelMethods.length,
							totalMethods: $allMethods.length
						});
						
						// Recreate if accordion doesn't exist OR panel is missing OR methods not in panel
						if ($existingAccordion.length === 0 || $existingPanel.length === 0 || ($allMethods.length > 0 && $panelMethods.length === 0)) {
							console.log('[FLOW PHP] Accordion incomplete after updated_checkout, recreating...');
							// Remove any incomplete accordion first
							$existingAccordion.remove();
							// Recreate with fresh saved methods
							wrapSavedCardsInAccordion();
						} else {
							console.log('[FLOW PHP] Accordion complete after updated_checkout');
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
		
		(function() {
			var displayOrder = '<?php echo esc_js( $flow_saved_card ); ?>';
			
			// CRITICAL: Set data attribute on body immediately for CSS targeting
			document.body.setAttribute('data-saved-payment-order', displayOrder);
			console.log('[FLOW] Body data attribute set:', displayOrder);
			
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
				const targetNode = document.body;

				// Hide Saved payment method for non logged in users.
				// IMPORTANT: Hide the accordion container, not just the list inside
				const observer = new MutationObserver((mutationsList, observer) => {
					// Hide the accordion container (which contains the saved cards)
					const $accordion = jQuery('.saved-cards-accordion-container');
					if ($accordion.length) {
						$accordion.hide();
						console.log('[FLOW] Hiding saved cards accordion for non-logged-in user');
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

				observer.observe(targetNode, config);

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

		if ( ! session_id() ) {
			session_start();
		}

		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ========== ENTRY POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order ID: ' . $order_id );
		
		if ( empty( $order_id ) ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: No order ID provided' );
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
			return;
		}

		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] ERROR: Order ' . $order_id . ' does not exist' );
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'Order not found. Please try again.', 'checkout-com-unified-payments-api' ), 'error' );
			return;
		}
		
		WC_Checkoutcom_Utility::logger( '[PROCESS PAYMENT] Order found - ID: ' . $order->get_id() . ', Status: ' . $order->get_status() . ', Payment Method: ' . $order->get_payment_method() );
		
		// DUPLICATE PREVENTION: Check if this order has already been processed
		$existing_transaction_id = $order->get_transaction_id();
		$flow_payment_id = isset( $_POST['cko-flow-payment-id'] ) ? $_POST['cko-flow-payment-id'] : '';
		
		if ( ! empty( $existing_transaction_id ) ) {
			WC_Checkoutcom_Utility::logger( 'DUPLICATE PREVENTION: Order ' . $order_id . ' already has transaction ID: ' . $existing_transaction_id . ' - skipping processing' );
			
			// Return success to prevent error, but don't process again
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
		
		// DUPLICATE PREVENTION: Check if this payment ID has already been processed
		if ( ! empty( $flow_payment_id ) ) {
			$existing_payment = $order->get_meta( '_cko_payment_id' );
			if ( $existing_payment === $flow_payment_id ) {
				WC_Checkoutcom_Utility::logger( 'DUPLICATE PREVENTION: Order ' . $order_id . ' already processed with payment ID: ' . $flow_payment_id . ' - skipping processing' );
				
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
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK not initialized - cannot fetch payment details for: ' . $flow_payment_id );
			throw new Exception( 'Payment gateway not properly configured. Please contact support.' );
		}
		
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
		
		// Set transaction ID and payment ID in order meta
			$order->set_transaction_id( $result['action_id'] );
			$order->update_meta_data( '_cko_payment_id', $flow_payment_id );
			$order->update_meta_data( '_cko_flow_payment_id', $flow_payment_id );
			$flow_payment_type = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : 'card';
			$order->update_meta_data( '_cko_flow_payment_type', $flow_payment_type );
			// Store order number/reference for webhook lookup (works with Sequential Order Numbers plugins)
			$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
			
			// CRITICAL: Save order immediately so webhooks can find it (especially for fast APM payments)
			$order->save();
			WC_Checkoutcom_Utility::logger( 'Order meta saved immediately for webhook lookup (3DS return) - Order ID: ' . $order_id . ', Payment ID: ' . $flow_payment_id );
			
		// Set variables for card saving logic below
		$flow_pay_id = $flow_payment_id;
		
		// Set status and message for the order (was missing!)
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s', 'checkout-com-unified-payments-api' ), $flow_payment_type );
		
		// Check if payment was flagged
		if ( isset( $result['risk']['flagged'] ) && $result['risk']['flagged'] ) {
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged (3DS return) - Payment ID: %s', 'checkout-com-unified-payments-api' ), $flow_payment_id );
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

			$order_status = $order->get_status();

			if ( 'pending' === $order_status || 'failed' === $order_status ) {
				$order->update_meta_data( 'cko_payment_authorized', true );
			}
		}
	else {
		$flow_pay_id = isset( $_POST['cko-flow-payment-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';

		// Check if $flow_pay_id is not empty.
		if ( empty( $flow_pay_id ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment. Please complete the payment.', 'checkout-com-unified-payments-api' ), 'error' );

			return;
		}

	$flow_payment_type = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '';

	if ( "card" === $flow_payment_type ) {
		$subs_payment_type = $flow_payment_type;
	}

	$order->update_meta_data( '_cko_payment_id', $flow_pay_id );
	$order->update_meta_data( '_cko_flow_payment_id', $flow_pay_id );
	$order->update_meta_data( '_cko_flow_payment_type', $flow_payment_type );
	// Store order number/reference for webhook lookup (works with Sequential Order Numbers plugins)
	$order->update_meta_data( '_cko_order_reference', $order->get_order_number() );
	
	// Store payment session ID for 3DS return lookup
	// Priority: 1) POST data (from form), 2) Payment metadata (if payment already exists)
	$payment_session_id = isset( $_POST['cko-flow-payment-session-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-session-id'] ) : '';
	WC_Checkoutcom_Utility::logger( 'Payment session ID from POST: ' . ( ! empty( $payment_session_id ) ? $payment_session_id : 'EMPTY' ) );
	
	if ( empty( $payment_session_id ) && ! empty( $flow_pay_id ) ) {
		// Try to get payment session ID from payment metadata
		WC_Checkoutcom_Utility::logger( 'Payment session ID not in POST, fetching from payment details...' );
		try {
			$checkout = new Checkout_SDK();
			$builder = $checkout->get_builder();
			if ( $builder ) {
				$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $flow_pay_id );
				$payment_session_id = isset( $payment_details['metadata']['cko_payment_session_id'] ) ? $payment_details['metadata']['cko_payment_session_id'] : '';
				WC_Checkoutcom_Utility::logger( 'Payment session ID from payment metadata: ' . ( ! empty( $payment_session_id ) ? $payment_session_id : 'EMPTY' ) );
			}
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( 'Could not fetch payment session ID from payment details: ' . $e->getMessage() );
		}
	}
	
	// Always save payment session ID if we have it (even if empty, log it)
	if ( ! empty( $payment_session_id ) ) {
		$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
		WC_Checkoutcom_Utility::logger( '✅ Saved payment session ID to order - Order ID: ' . $order_id . ', Payment Session ID: ' . $payment_session_id );
	} else {
		WC_Checkoutcom_Utility::logger( '⚠️ WARNING: Payment session ID is empty - Order ID: ' . $order_id . ', Payment ID: ' . $flow_pay_id );
		WC_Checkoutcom_Utility::logger( '⚠️ This may cause issues with 3DS return order lookup' );
	}
	
	// CRITICAL: Save order immediately so webhooks can find it (especially for fast APM payments)
	$order->save();
	WC_Checkoutcom_Utility::logger( 'Order meta saved immediately for webhook lookup - Order ID: ' . $order_id . ', Payment ID: ' . $flow_pay_id );

		if ( ! in_array( $flow_payment_type, array( 'card', 'googlepay', 'applepay' ), true ) ) {
				$order->update_meta_data( 'cko_payment_authorized', true );
			}

			// translators: %s: payment type (e.g., card, applepay).
			$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - using FLOW : %s', 'checkout-com-unified-payments-api' ), $flow_payment_type );

			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

			// Get source ID for the payment id for subscription product.
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->get_id() ) ) {
				error_log('hereu7');
				$request  = new \WP_REST_Request( 'GET', '/ckoplugin/v1/payment-status' );
				$request->set_query_params( [ 'paymentId' => $flow_pay_id ] );
				$result = rest_do_request( $request );
				
				if ( is_wp_error( $result ) ) {
					$error_message = $result->get_error_message();
					WC_Checkoutcom_Utility::logger( "There was an error in saving cards: $error_message" ); // phpcs:ignore
				} else {
					$flow_result = $result->get_data();
					error_log(print_r($flow_result,true));
				}
		}
	}

	// Card saving logic (runs for both normal Flow payments and 3DS returns)
	// Check if $flow_pay_id is set (it's set in both the 3DS return handler and normal Flow payment)
	if ( isset( $flow_pay_id ) && ! empty( $flow_pay_id ) ) {
		$flow_payment_type_for_save = isset( $flow_payment_type ) ? $flow_payment_type : ( isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '' );
		
		// Check if customer wants to save card
		// Priority: 1. Hidden field (survives 3DS), 2. POST data, 3. Session
		$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		
		// Check hidden field first (persists across 3DS redirects)
		$save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
		
		// Fallback to POST checkbox
		$save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
		
		// Fallback to session
		$save_card_session = WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );
		
		// Determine if checkbox was checked (priority: hidden field > POST > session)
		$save_card_checkbox = false;
		if ( 'yes' === $save_card_hidden ) {
			$save_card_checkbox = true;
		} elseif ( 'true' === $save_card_post || 'yes' === $save_card_post ) {
			$save_card_checkbox = true;
		} elseif ( 'yes' === $save_card_session ) {
			$save_card_checkbox = true;
	}

	if ( 'card' === $flow_payment_type_for_save && $save_card_enabled && $save_card_checkbox ) {
		$this->flow_save_cards( $order, $flow_pay_id );
		// Clear the session variable after processing
		WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
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
		$order->update_status( $status );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Get return URL before emptying cart
		$return_url = $this->get_return_url( $order );
		
		// Check if this is a MOTO order and log additional info
		$is_moto_order = $order->is_created_via( 'admin' );
		if ( $is_moto_order ) {
			WC_Checkoutcom_Utility::logger( 'MOTO order detected - Order ID: ' . $order_id . ', Created via: ' . $order->get_created_via() );
		}
		
		// Log the redirect URL for debugging
		WC_Checkoutcom_Utility::logger( 'Flow payment successful - redirecting to: ' . $return_url . ' (MOTO: ' . ( $is_moto_order ? 'YES' : 'NO' ) . ')' );
		WC_Checkoutcom_Utility::logger( 'Order status updated to: ' . $status . ', Order ID: ' . $order_id );
		WC_Checkoutcom_Utility::logger( 'Transaction ID set to: ' . $order->get_transaction_id() );

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thank you page.
		$redirect_result = array(
			'result'   => 'success',
			'redirect' => $return_url,
		);
		
		WC_Checkoutcom_Utility::logger( 'Returning redirect result: ' . print_r( $redirect_result, true ) );
		
		return $redirect_result;
	}

	
	/**
	 * Handle 3DS return via WC API endpoint (similar to PayPal approach).
	 * Processes payment and redirects directly to order-received page.
	 * 
	 * @return void
	 */
	public function handle_3ds_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ========== ENTRY POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Request URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'N/A' ) );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] GET params: ' . print_r( $_GET, true ) );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] POST params: ' . print_r( $_POST, true ) );
		
		$payment_id = isset( $_GET['cko-payment-id'] ) ? sanitize_text_field( $_GET['cko-payment-id'] ) : '';
		$payment_session_id_from_url = isset( $_GET['cko-payment-session-id'] ) ? sanitize_text_field( $_GET['cko-payment-session-id'] ) : '';
		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order_key  = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
		
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Extracted values - Payment ID: ' . $payment_id . ', Payment Session ID (from URL): ' . $payment_session_id_from_url . ', Order ID: ' . $order_id . ', Order Key: ' . ( ! empty( $order_key ) ? 'SET' : 'EMPTY' ) );
		
		if ( empty( $payment_id ) ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Missing payment ID' );
			error_log( '[FLOW 3DS API] ERROR: Missing payment ID in GET params' );
			wp_die( esc_html__( 'Missing payment ID', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
		}
		
		$order = null;
		
		// If order_id and order_key are provided (order-pay page), use them
		if ( ! empty( $order_id ) && ! empty( $order_key ) ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order ID and key provided - loading order: ' . $order_id );
			$order = wc_get_order( $order_id );
			
			if ( ! $order ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Order not found - Order ID: ' . $order_id );
				error_log( '[FLOW 3DS API] ERROR: Order not found' );
				wp_die( esc_html__( 'Order not found', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
			}
			
			$order_key_from_order = $order->get_order_key();
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found - Order Key from order: ' . $order_key_from_order . ', Order Key from URL: ' . $order_key );
			
			if ( $order_key_from_order !== $order_key ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Order key mismatch' );
				error_log( '[FLOW 3DS API] ERROR: Order key mismatch' );
				wp_die( esc_html__( 'Invalid order key', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
			}
		} else {
			// For regular checkout, look up order by payment session ID
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order ID/key not provided - will look up order by payment session ID' );
			
			try {
				// Get payment session ID from URL first (faster), then from payment metadata if needed
				$payment_session_id = $payment_session_id_from_url;
				$payment_details = null;
				
				if ( empty( $payment_session_id ) ) {
					// Fallback: fetch payment details to get the payment session ID
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment session ID not in URL, fetching from payment details...' );
					$checkout = new Checkout_SDK();
					$builder = $checkout->get_builder();
					
					if ( ! $builder ) {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: SDK builder not initialized for order lookup' );
						error_log( '[FLOW 3DS API] ERROR: SDK builder not initialized' );
						wp_die( esc_html__( 'Payment processing error', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
					}
					
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fetching payment details to get payment session ID...' );
					$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $payment_id );
					
					// Get payment session ID from metadata
					$payment_session_id = isset( $payment_details['metadata']['cko_payment_session_id'] ) ? $payment_details['metadata']['cko_payment_session_id'] : '';
					
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment session ID from payment metadata: ' . $payment_session_id );
				} else {
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Using payment session ID from URL: ' . $payment_session_id );
					// Still fetch payment details for fallback lookup methods
					$checkout = new Checkout_SDK();
					$builder = $checkout->get_builder();
					if ( $builder ) {
						$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $payment_id );
					}
				}
				
				if ( empty( $payment_session_id ) ) {
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Payment session ID not found in URL or payment metadata' );
					error_log( '[FLOW 3DS API] ERROR: Payment session ID not found' );
					// Don't die yet - try fallback methods
				} else {
					
					// Look up order by payment session ID
					WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Looking up order by payment session ID: ' . $payment_session_id );
					$orders = wc_get_orders( array(
						'meta_key'   => '_cko_payment_session_id',
						'meta_value' => $payment_session_id,
						'limit'      => 1,
						'orderby'    => 'date',
						'order'      => 'DESC',
					) );
					
					if ( ! empty( $orders ) ) {
						$order = $orders[0];
						$order_id = $order->get_id();
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found by payment session ID - Order ID: ' . $order_id );
					} else {
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order not found by payment session ID, trying fallback methods...' );
						
						// Fallback 1: Try to find order by payment ID (in case payment was already processed)
						WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fallback: Looking for order by payment ID: ' . $payment_id );
						$orders_by_payment = wc_get_orders( array(
							'meta_key'   => '_cko_payment_id',
							'meta_value' => $payment_id,
							'limit'      => 1,
							'orderby'    => 'date',
							'order'      => 'DESC',
						) );
						
						if ( ! empty( $orders_by_payment ) ) {
							$order = $orders_by_payment[0];
							$order_id = $order->get_id();
							WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found by payment ID (fallback) - Order ID: ' . $order_id );
						} else {
							// Fallback 2: Try to find most recent pending order for this customer
							WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fallback: Looking for most recent pending order...' );
							if ( $payment_details && isset( $payment_details['customer']['email'] ) ) {
								$customer_email = $payment_details['customer']['email'];
								if ( ! empty( $customer_email ) ) {
									$orders_by_email = wc_get_orders( array(
										'billing_email' => $customer_email,
										'status'        => array( 'pending', 'on-hold', 'processing' ),
										'limit'         => 5,
										'orderby'       => 'date',
										'order'         => 'DESC',
									) );
									
									WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Found ' . count( $orders_by_email ) . ' pending orders for email: ' . $customer_email );
									
									// Check if any of these orders match the payment amount
									$payment_amount = isset( $payment_details['amount'] ) ? $payment_details['amount'] : 0;
									foreach ( $orders_by_email as $potential_order ) {
										$order_amount = (int) round( $potential_order->get_total() * 100 ); // Convert to cents
										if ( $order_amount === $payment_amount ) {
											$order = $potential_order;
											$order_id = $order->get_id();
											WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order found by email and amount match (fallback) - Order ID: ' . $order_id );
											break;
										}
									}
								}
							}
							
							if ( ! $order ) {
								// Last resort: Create order from cart and payment details
								// This happens when 3DS redirect occurs before form submission
								WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order not found - creating order from cart and payment details' );
								
								if ( ! WC()->cart || WC()->cart->is_empty() ) {
									WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Cannot create order - cart is empty' );
									error_log( '[FLOW 3DS API] ERROR: Cart is empty, cannot create order' );
									wp_die( esc_html__( 'Order not found and cart is empty. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
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
								
								// Create order
								WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Creating order - Customer ID: ' . $customer_id . ', Email: ' . $customer_email );
								$order = wc_create_order( array( 'customer_id' => $customer_id ) );
								
								if ( ! $order || is_wp_error( $order ) ) {
									WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: Failed to create order' );
									error_log( '[FLOW 3DS API] ERROR: Failed to create order' );
									wp_die( esc_html__( 'Failed to create order. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
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
											}
										}
									}
								}
								
								// Calculate totals
								$order->calculate_totals();
								
								// Set payment method
								$order->set_payment_method( $this->id );
								$order->set_payment_method_title( $this->get_title() );
								
								// Save payment session ID
								if ( ! empty( $payment_session_id ) ) {
									$order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
								}
								
								// Set status to pending
								$order->set_status( 'pending' );
								$order->save();
								
								$order_id = $order->get_id();
								WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Order created successfully - Order ID: ' . $order_id );
							}
						}
					}
				}
			} catch ( Exception $e ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Exception during order lookup: ' . $e->getMessage() );
				error_log( '[FLOW 3DS API] EXCEPTION during order lookup: ' . $e->getMessage() );
				wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
			}
		}
		
		// Check if payment is already processed
		$existing_payment = $order->get_meta( '_cko_payment_id' );
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Checking existing payment - Existing: ' . $existing_payment . ', New: ' . $payment_id );
		
		if ( $existing_payment === $payment_id ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment already processed, redirecting to order-received' );
			$return_url = $this->get_return_url( $order );
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Redirect URL: ' . $return_url );
			wp_safe_redirect( $return_url );
			exit;
		}
		
		// Fetch payment details from Checkout.com
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Fetching payment details from Checkout.com - Payment ID: ' . $payment_id );
		try {
			$checkout = new Checkout_SDK();
			$builder = $checkout->get_builder();
			
			if ( ! $builder ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ERROR: SDK builder not initialized' );
				error_log( '[FLOW 3DS API] ERROR: SDK builder not initialized' );
				throw new Exception( 'Checkout.com SDK not initialized' );
			}
			
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] SDK initialized, fetching payment details...' );
			$payment_details = $builder->getPaymentsClient()->getPaymentDetails( $payment_id );
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment details received: ' . print_r( $payment_details, true ) );
			
			// Check if payment is approved
			$is_approved = isset( $payment_details['approved'] ) ? $payment_details['approved'] : false;
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment approved status: ' . ( $is_approved ? 'YES' : 'NO' ) );
			
			if ( ! $is_approved ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment not approved: ' . print_r( $payment_details, true ) );
				error_log( '[FLOW 3DS API] Payment not approved' );
				wp_die( esc_html__( 'Payment was not approved. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Failed', 'checkout-com-unified-payments-api' ), array( 'response' => 400 ) );
			}
			
			// Process the payment by simulating POST data and calling process_payment
			$payment_type = isset( $payment_details['source']['type'] ) ? $payment_details['source']['type'] : 'card';
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Setting POST data - Payment ID: ' . $payment_id . ', Payment Type: ' . $payment_type );
			
			// Set POST data for process_payment method
			$_POST['cko-flow-payment-id'] = $payment_id;
			$_POST['cko-flow-payment-type'] = $payment_type;
			
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Calling process_payment for order: ' . $order_id );
			// Process the payment
			$result = $this->process_payment( $order_id );
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] process_payment result: ' . print_r( $result, true ) );
			
			if ( isset( $result['result'] ) && 'success' === $result['result'] && isset( $result['redirect'] ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment processed successfully, redirecting to: ' . $result['redirect'] );
				error_log( '[FLOW 3DS API] SUCCESS - Redirecting to: ' . $result['redirect'] );
				wp_safe_redirect( $result['redirect'] );
				exit;
			} else {
				WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Payment processing failed: ' . print_r( $result, true ) );
				error_log( '[FLOW 3DS API] ERROR - Payment processing failed: ' . print_r( $result, true ) );
				wp_die( esc_html__( 'Payment processing failed. Please contact support.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
			}
			
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Exception: ' . $e->getMessage() );
			WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] Exception trace: ' . $e->getTraceAsString() );
			error_log( '[FLOW 3DS API] EXCEPTION: ' . $e->getMessage() );
			error_log( '[FLOW 3DS API] Exception trace: ' . $e->getTraceAsString() );
			wp_die( esc_html__( 'An error occurred while processing your payment. Please try again.', 'checkout-com-unified-payments-api' ), esc_html__( 'Payment Error', 'checkout-com-unified-payments-api' ), array( 'response' => 500 ) );
		}
		
		WC_Checkoutcom_Utility::logger( '[FLOW 3DS API] ========== EXIT POINT ==========' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
		// Only render the save card checkbox div if save card feature is enabled
		if ( ! $save_card ) {
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
		WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Flow gateway process_refund called for order $order_id, amount: $amount, reason: $reason" );
		
		$order  = wc_get_order( $order_id );
		WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Order loaded. Payment method: " . $order->get_payment_method() );
		
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );
		WC_Checkoutcom_Utility::logger( "REFUND DEBUG: API refund result: " . print_r( $result, true ) );

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Error in refund result: " . $result['error'] );
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return false;
		}

		// Set action id as woo transaction id.
		WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Setting transaction ID: " . $result['action_id'] );
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( 'cko_payment_refunded', true );
		$order->save();

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Partial refund completed" );
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Partially refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		// add note for order.
		WC_Checkoutcom_Utility::logger( "REFUND DEBUG: Full refund completed" );
		$order->add_order_note( $message );

		// when true is returned, status is changed to refunded automatically.
		return true;
	}

	/**
	 * Deactivate Classic methods when FLOW is active.
	 */
	public static function flow_enabled() {

		$flow_settings = get_option( 'woocommerce_wc_checkout_com_flow_settings' );

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode    = $checkout_setting['ckocom_checkout_mode'];
	
		$apm_settings      = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
		$gpay_settings     = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );
		$applepay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings' );
		$paypal_settings   = get_option( 'woocommerce_wc_checkout_com_paypal_settings' );
	
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

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Starting order lookup process' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Webhook data structure: ' . print_r($data, true) );
		}

		// Method 1: Try order_id from metadata (order-pay page has this)
		if ( ! empty( $data->data->metadata->order_id ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by metadata order_id: ' . $data->data->metadata->order_id );
			}
			$order = wc_get_order( $data->data->metadata->order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by metadata order_id: ' . ($order ? 'YES (ID: ' . $order->get_id() . ')' : 'NO') );
			}
		}
		
		// Method 2: Try payment session ID (works for regular checkout)
		if ( ! $order && ! empty( $data->data->metadata->cko_payment_session_id ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by payment session ID: ' . $data->data->metadata->cko_payment_session_id );
			}
			
			$orders = wc_get_orders( array(
				'limit'      => 1,
				'meta_key'   => '_cko_payment_session_id',
				'meta_value' => $data->data->metadata->cko_payment_session_id,
				'return'     => 'objects',
			) );
			
			if ( ! empty( $orders ) ) {
				$order = $orders[0];
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment session ID: YES (ID: ' . $order->get_id() . ')' );
				}
				
				// Add order_id to metadata so processing functions can find it
				if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
					$data->data->metadata->order_id = $order->get_id();
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Set metadata order_id to: ' . $order->get_id() . ' (from payment session ID lookup)' );
					}
				} else {
					// If metadata is missing or not an object, create it.
					$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Created metadata object with order_id: ' . $order->get_id() . ' (from payment session ID lookup)' );
					}
				}
			} else {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment session ID: NO' );
				}
			}
		}
	
		// Method 3: Try reference (order number) via our stored meta
		if ( ! $order && ! empty( $data->data->reference ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by reference: ' . $data->data->reference );
			}
			
			// Try direct lookup first (works for numeric order IDs)
			$order = wc_get_order( $data->data->reference );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Direct lookup result: ' . ($order ? 'YES (ID: ' . $order->get_id() . ')' : 'NO') );
			}
			
			// Try our stored reference meta (works with ANY order number format including Sequential Order Numbers)
			if ( ! $order ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Searching by _cko_order_reference meta: ' . $data->data->reference );
				}
				
				$orders = wc_get_orders( array(
					'limit'      => 1,
					'meta_key'   => '_cko_order_reference',
					'meta_value' => $data->data->reference,
					'return'     => 'objects',
				) );
				
				if ( ! empty( $orders ) ) {
					$order = $orders[0];
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _cko_order_reference meta: YES (ID: ' . $order->get_id() . ')' );
					}
				} else {
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _cko_order_reference meta: NO' );
					}
				}
			}
			
			// If still not found, try the Sequential Order Numbers plugin meta key
			if ( ! $order ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Searching by _order_number meta: ' . $data->data->reference );
				}
				
				$orders = wc_get_orders( array(
					'limit'      => 1,
					'meta_key'   => '_order_number',
					'meta_value' => $data->data->reference,
					'return'     => 'objects',
				) );
				
				if ( ! empty( $orders ) ) {
					$order = $orders[0];
					if ( $webhook_debug_enabled ) {
						WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by _order_number meta: YES (ID: ' . $order->get_id() . ')' );
					}
				} else {
					// Try searching by post name (order number might be stored there)
					global $wpdb;
					$post_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('shop_order', 'shop_order_placehold') AND post_name = %s LIMIT 1",
						sanitize_title( $data->data->reference )
					) );
					
					if ( $post_id ) {
						$order = wc_get_order( $post_id );
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by post_name: YES (ID: ' . $order->get_id() . ')' );
						}
					} else {
						if ( $webhook_debug_enabled ) {
							WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order NOT found by any method' );
						}
					}
				}
			}

			if ( $order && isset( $data->data->metadata ) ) {
				$data->data->metadata->order_id = $order->get_id();
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Set metadata order_id to: ' . $order->get_id() );
				}
			} elseif ( $order ) {
				$data->data->metadata           = new StdClass();
				$data->data->metadata->order_id = $order->get_id();
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Created metadata object with order_id: ' . $order->get_id() );
				}
			}
		} else {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: No order_id in metadata and no reference found' );
			}
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order lookup result: ' . ($order ? 'FOUND (ID: ' . $order->get_id() . ')' : 'NOT FOUND') );
		}

		// Method 4: Try payment ID (works when order has been processed)
		if ( ! $order && ! empty( $data->data->id ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Looking for order by payment ID: ' . $data->data->id );
			}
			
			$orders = wc_get_orders( array(
				'limit'        => 1,
				'meta_key'     => '_cko_flow_payment_id',
				'meta_value'   => $data->data->id,
				'return'       => 'objects',
			) );

			if ( ! empty( $orders ) ) {
				$order = $orders[0];
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
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found by payment ID: NO' );
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: This is normal for webhooks that arrive before process_payment() completes' );
				}
			}
		}

		if ( $order ) {
			$payment_id = $order->get_meta( '_cko_payment_id' ) ?? null;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order found, getting payment ID from meta' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order ID: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order status: ' . $order->get_status() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Order transaction ID: ' . $order->get_transaction_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: All order meta data: ' . print_r($order->get_meta_data(), true) );
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

		// For Flow payments, be more flexible with payment ID matching
		// Flow payments might not have payment ID set yet, or might have different ID format
		if ( $order && is_null( $payment_id ) ) {
			// If no payment ID is set, try to set it from the webhook
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'Flow webhook: No payment ID found in order, setting from webhook: ' . $data->data->id );
			}
			$order->set_transaction_id( $data->data->id );
			$order->update_meta_data( '_cko_payment_id', $data->data->id );
			$order->update_meta_data( '_cko_flow_payment_id', $data->data->id );
			$order->save();
			$payment_id = $data->data->id;
		} elseif ( $order && $payment_id !== $data->data->id ) {
			// Payment ID exists but doesn't match - log but don't fail for Flow payments
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'Flow webhook: Payment ID mismatch - Order: ' . $payment_id . ', Webhook: ' . $data->data->id . ' - Continuing processing' );
			}
		} elseif ( ! $order ) {
			// No order found - this is a critical error for webhook processing (always log errors)
			WC_Checkoutcom_Utility::logger( 'Flow webhook: CRITICAL - No order found for webhook processing. Payment ID: ' . ($data->data->id ?? 'NULL') );
			http_response_code( 404 );
			wp_die( 'Order not found', 'Webhook Error', array( 'response' => 404 ) );
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
				break;
			case 'payment_captured':
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: Processing payment_captured event' );
				}
				$response = WC_Checkout_Com_Webhook::capture_payment( $data );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK DEBUG: payment_captured response: ' . print_r($response, true) );
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
}