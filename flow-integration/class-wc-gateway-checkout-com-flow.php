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

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );

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

		// Webhook handler hook.
		add_action( 'woocommerce_api_wc_checkoutcom_webhook', [ $this, 'webhook_handler' ] );

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
		$flow_saved_card = get_option( 'woocommerce_wc_checkout_com_flow_settings' )['flow_saved_payment'];

		$order_pay_order_id = null;
		$order_pay_order = null;
		if ( is_checkout() && isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) ) {
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
					<?php if ( $order_pay_order->is_created_via( 'admin' ) ) : ?>
						<script>
							const orderPayTargetNode = document.body;

							// Hide Saved payment method for admin-created orders (MOTO).
							// IMPORTANT: Hide the accordion container, not just the list inside
							const orderPayObserver = new MutationObserver((mutationsList, observer) => {
								// Hide the accordion container (which contains the saved cards)
								const $accordion = jQuery('.saved-cards-accordion-container');
								if ($accordion.length) {
									$accordion.hide();
									console.log('[FLOW] Hiding saved cards accordion for admin-created order');
								}
								
								// Also hide any saved payment methods that aren't wrapped yet
								const $element = jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
								if ($element.length) {
									$element.hide();
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
						</script>
					<?php else : ?>
<!-- Show Saved Payment Methods button is hidden when showing both Flow and saved cards together -->
					<div class="button-container" style="display: none;">
					<label class="wp-style-button" style="display: none;" id="show-saved-methods-btn">
						<input type="radio" name="payment_method_selector" onclick="toggleRadio(this, handleShowSavedMethods)"/>
						<?php esc_html_e( 'Show Saved Payment Methods', 'checkout-com-unified-payments-api' ); ?>
					</label>
					</div>

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
							const $showSavedBtn = $('#show-saved-methods-btn');

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
				jQuery(document.body).on('updated_checkout', function() {
					console.log('[FLOW PHP] updated_checkout fired - re-wrapping saved cards...');
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
					}, 100);
				});

						window.toggleRadio = function(radio, callback) {
								radio.checked = false;
								if (typeof callback === 'function') {
									callback();
								}
							};
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
<!-- Show Saved Payment Methods button is hidden when showing both Flow and saved cards together -->
					<div class="button-container" style="display: none;">
					<label class="wp-style-button" style="display: none;" id="show-saved-methods-btn">
						<input type="radio" name="payment_method_selector" onclick="toggleRadio(this, handleShowSavedMethods)"/>
						<?php esc_html_e( 'Show Saved Payment Methods', 'checkout-com-unified-payments-api' ); ?>
					</label>
					</div>

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
						const $showSavedBtn = $('#show-saved-methods-btn');

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
				jQuery(document.body).on('updated_checkout', function() {
					console.log('[FLOW PHP] updated_checkout fired - re-wrapping saved cards...');
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
					}, 100);
				});

				window.toggleRadio = function(radio, callback) {
							radio.checked = false;
							if (typeof callback === 'function') {
								callback();
							}
						};
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
		<input type="hidden" id="cko-flow-3ds-status" name="cko-flow-3ds-status" value="" />
		<input type="hidden" id="cko-flow-3ds-auth-id" name="cko-flow-3ds-auth-id" value="" />
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
			// Migrate old saved cards for logged-in users (only once)
			if ( is_user_logged_in() ) {
				$user_id = get_current_user_id();
				$this->migrate_old_saved_cards( $user_id );
			}

			// Only show Flow saved cards to avoid duplicates
			// The classic cards gateway saved cards are now migrated to Flow
			$this->saved_payment_methods();
		}

		// Render Save Card input.
		$this->element_form_save_card( $save_card );
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

		$order = new WC_Order( $order_id );

		$flow_result = null;

		$subs_payment_type = null;

		WC_Checkoutcom_Utility::logger(print_r($_POST,true));

		if ( WC_Checkoutcom_Api_Request::is_using_saved_payment_method() ) {
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
			$flow_3ds_status = isset( $_POST['cko-flow-3ds-status'] ) ? sanitize_text_field( $_POST['cko-flow-3ds-status'] ) : '';
			$flow_3ds_auth_id = isset( $_POST['cko-flow-3ds-auth-id'] ) ? sanitize_text_field( $_POST['cko-flow-3ds-auth-id'] ) : '';

			if ( "card" === $flow_payment_type ) {
				$subs_payment_type = $flow_payment_type;
			}

			// Debug: Log all possible checkbox field names
			WC_Checkoutcom_Utility::logger( '=== SAVE CARD DEBUG ===' );
			WC_Checkoutcom_Utility::logger( 'Flow Payment Type: ' . $flow_payment_type );
			WC_Checkoutcom_Utility::logger( 'Checking for save card checkbox...' );
			WC_Checkoutcom_Utility::logger( 'wc-wc_checkout_com_flow-new-payment-method: ' . ( isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? $_POST['wc-wc_checkout_com_flow-new-payment-method'] : 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'wc_checkout_com_flow-new-payment-method: ' . ( isset( $_POST['wc_checkout_com_flow-new-payment-method'] ) ? $_POST['wc_checkout_com_flow-new-payment-method'] : 'NOT SET' ) );
			
			// Check multiple possible checkbox names
			$save_card_checkbox = false;
			if ( isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) && $_POST['wc-wc_checkout_com_flow-new-payment-method'] === 'true' ) {
				$save_card_checkbox = true;
			} elseif ( isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) && $_POST['wc-wc_checkout_com_flow-new-payment-method'] === 'on' ) {
				$save_card_checkbox = true;
			} elseif ( isset( $_POST['wc_checkout_com_flow-new-payment-method'] ) ) {
				$save_card_checkbox = true;
			}
			
			WC_Checkoutcom_Utility::logger( 'Save card checkbox result: ' . ( $save_card_checkbox ? 'TRUE' : 'FALSE' ) );

			if ( 'card' === $flow_payment_type && $save_card_checkbox ) {
				WC_Checkoutcom_Utility::logger( 'Calling flow_save_cards()...' );
				$this->flow_save_cards( $order, $flow_pay_id );
			} else {
				WC_Checkoutcom_Utility::logger( 'NOT calling flow_save_cards() - Type: ' . $flow_payment_type . ', Checkbox: ' . ( $save_card_checkbox ? 'TRUE' : 'FALSE' ) );
			}

			$order->update_meta_data( '_cko_payment_id', $flow_pay_id );
			$order->update_meta_data( '_cko_flow_payment_id', $flow_pay_id );
			$order->update_meta_data( '_cko_flow_payment_type', $flow_payment_type );
			
			// Store 3DS authentication data if present
			if ( ! empty( $flow_3ds_status ) ) {
				$order->update_meta_data( '_cko_flow_3ds_status', $flow_3ds_status );
			}
			if ( ! empty( $flow_3ds_auth_id ) ) {
				$order->update_meta_data( '_cko_flow_3ds_auth_id', $flow_3ds_auth_id );
			}

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

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thank you page.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Save customer's card information after a successful payment.
	 *
	 * @param WC_Order $order   The WooCommerce order object.
	 * @param string   $pay_id  The payment ID used to query payment status.
	 */
	public function flow_save_cards( $order, $pay_id ) {

		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );

		// Check if save card is enable and customer select to save card.
		if ( ! $save_card ) {
			return;
		}

		$request  = new \WP_REST_Request( 'GET', '/ckoplugin/v1/payment-status' );
		$request->set_query_params( [ 'paymentId' => $pay_id ] );

		$result = rest_do_request( $request );

		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			WC_Checkoutcom_Utility::logger( "There was an error in saving cards: $error_message" ); // phpcs:ignore
		} else {
			$data = $result->get_data();
		}
		
		$this->save_token( $order->get_user_id(), $data );
	}

	/**
	 * Renders the save card markup.
	 *
	 * @param string $save_card Save card enable.
	 *
	 * @return void
	 */
	public function element_form_save_card( $save_card ) {
		?>
		<!-- Show save card checkbox if this is selected on admin-->
		<div class="cko-save-card-checkbox" style="display: none">
			<?php
			if ( $save_card ) {
				$this->save_payment_method_checkbox();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Migrate old saved cards to be compatible with Flow integration.
	 * This function helps users with saved cards from previous plugin versions.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function migrate_old_saved_cards( $user_id ) {
		// Check if migration has already been done for this user
		$migration_done = get_user_meta( $user_id, '_cko_flow_migration_done', true );
		if ( $migration_done ) {
			return; // Migration already completed
		}

		// Get all tokens for this user from the classic cards gateway
		$classic_gateway = new WC_Gateway_Checkout_Com_Cards();
		$tokens = WC_Payment_Tokens::get_tokens( array(
			'user_id'    => $user_id,
			'gateway_id' => $classic_gateway->id,
			'limit'      => 100,
		) );

		if ( empty( $tokens ) ) {
			// No old tokens to migrate, mark as done
			update_user_meta( $user_id, '_cko_flow_migration_done', true );
			return;
		}

		// Get existing Flow tokens to avoid duplicates
		$flow_tokens = WC_Payment_Tokens::get_tokens( array(
			'user_id'    => $user_id,
			'gateway_id' => $this->id,
			'limit'      => 100,
		) );

		$existing_flow_tokens = array();
		foreach ( $flow_tokens as $flow_token ) {
			$existing_flow_tokens[] = $flow_token->get_token();
		}

		$migrated_count = 0;
		foreach ( $tokens as $token ) {
			// Skip if token already exists in Flow gateway
			if ( in_array( $token->get_token(), $existing_flow_tokens, true ) ) {
				continue;
			}

			// Create new token for Flow gateway
			$new_token = new WC_Payment_Token_CC();
			$new_token->set_token( $token->get_token() );
			$new_token->set_gateway_id( $this->id );
			$new_token->set_card_type( $token->get_card_type() );
			$new_token->set_last4( $token->get_last4() );
			$new_token->set_expiry_month( $token->get_expiry_month() );
			$new_token->set_expiry_year( $token->get_expiry_year() );
			$new_token->set_user_id( $user_id );

			// Copy metadata from old token
			$old_meta = $token->get_meta_data();
			foreach ( $old_meta as $meta ) {
				$new_token->add_meta_data( $meta->key, $meta->value, true );
			}

			$new_token->save();
			$migrated_count++;
		}

		// Mark migration as completed
		update_user_meta( $user_id, '_cko_flow_migration_done', true );
		
		if ( $migrated_count > 0 ) {
			WC_Checkoutcom_Utility::logger( 'Migrated ' . $migrated_count . ' old saved cards to Flow gateway for user: ' . $user_id );
		}
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
		// Check if payment response is not null.
		if ( ! is_null( $payment_response ) ) {
			// argument to check token.
			$arg = array(
				'user_id'    => $user_id,
				'gateway_id' => $this->id,
				'limit'		 => 100,
			);

			// Query token by userid and gateway id.
			$token = WC_Payment_Tokens::get_tokens( $arg );

			foreach ( $token as $tok ) {
				$fingerprint = $tok->get_meta( 'fingerprint', true );
				// do not save source if it already exists in db.
				if ( $fingerprint === $payment_response['source']['fingerprint'] ) {
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

			foreach ( $token_classic as $tokc ) {
				$token_data = $tokc->get_data();
				// do not save source if it already exists in db.
				if ( $token_data['token'] === $payment_response['source']['id'] ) {
					return;
				}
			}

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

			$token->save();
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

		// Check if Flow mode is enabled - if not, let Cards handler process the webhook
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode = $core_settings['ckocom_checkout_mode'] ?? 'cards';
		
		if ( 'flow' !== $checkout_mode ) {
			// Flow mode is not enabled, don't process webhook in Flow handler
			return;
		}

		try {
			// Get webhook data.
			$data = json_decode( file_get_contents( 'php://input' ) );

		// Return to home page if empty data.
		if ( empty( $data ) ) {
			wp_redirect( get_home_url() );
			exit();
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
		$header_signature = $header['cko-signature'];

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$raw_event     = file_get_contents( 'php://input' );

		// For webhook signature verification, use the same logic as the working version
		$core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];
		$secret_key = $core_settings['ckocom_sk'];


		$signature = WC_Checkoutcom_Utility::verify_signature( $raw_event, $secret_key, $header_signature );

		// check if cko signature matches.
		if ( false === $signature ) {
			WC_Checkoutcom_Utility::logger('Invalid signature - returning 401');
        	$this->send_response(401, 'Unauthorized: Invalid signature');
		}

		$order      = false;
		$payment_id = null;

		if ( ! empty( $data->data->metadata->order_id ) ) {
			$order = wc_get_order( $data->data->metadata->order_id );
		} elseif ( ! empty( $data->data->reference ) ) {
			$order = wc_get_order( $data->data->reference );

			if ( isset( $data->data->metadata ) ) {
				$data->data->metadata->order_id = $data->data->reference;
			} else {
				$data->data->metadata           = new StdClass();
				$data->data->metadata->order_id = $data->data->reference;
			}
		}

		// If order is still not found, try using _cko_flow_payment_id.
		if ( ! $order && ! empty( $data->data->id ) ) {
			$orders = wc_get_orders( array(
				'limit'        => 1,
				'meta_key'     => '_cko_flow_payment_id',
				'meta_value'   => $data->data->id,
				'return'       => 'objects',
			) );

			if ( ! empty( $orders ) ) {
				$order = $orders[0];

				// Add order_id to $data->data->metadata.
				if ( isset( $data->data->metadata ) && is_object( $data->data->metadata ) ) {
					$data->data->metadata->order_id = $order->get_id();
				} else {
					// If metadata is missing or not an object, create it.
					$data->data->metadata = (object) array( 'order_id' => $order->get_id() );
				}
			} else {
				WC_Checkoutcom_Utility::logger( 'Order not found.' );
			}
		}

		if ( $order ) {
			$payment_id = $order->get_meta( '_cko_payment_id' ) ?? null;
		}

		WC_Checkoutcom_Utility::logger( '$payment_id: ' . $payment_id );
		WC_Checkoutcom_Utility::logger( '$data->data->id: ' . $data->data->id );

		// check if payment ID matches that of the webhook.
		if ( is_null( $payment_id ) || $payment_id !== $data->data->id ) {

			$gateway_debug = 'yes' === WC_Admin_Settings::get_option( 'cko_gateway_responses', 'no' );
			if ( $gateway_debug ) {
				/* translators: 1: Payment ID, 2: Webhook ID. */
				$message = sprintf( esc_html__( 'Order payment Id (%1$s) does not match that of the webhook (%2$s)', 'checkout-com-unified-payments-api' ), $payment_id, $data->data->id );

				WC_Checkoutcom_Utility::logger( $message, null );
			}

			WC_Checkoutcom_Utility::logger( 'DEBUG: Returning HTTP 422 from payment ID mismatch condition or is null.', null );
			$this->send_response(422, 'Unprocessable Entity: Payment ID mismatch');
		}

		WC_Checkoutcom_Utility::logger( 'DEBUG: Event Type Data' );
		WC_Checkoutcom_Utility::logger(print_r($data,true));

		// Get webhook event type from data.
		$event_type = $data->type;

		switch ( $event_type ) {
			case 'card_verified':
				$response = WC_Checkout_Com_Webhook::card_verified( $data );
				break;
			case 'payment_approved':
				$response = WC_Checkout_Com_Webhook::authorize_payment( $data );
				break;
			case 'payment_captured':
				$response = WC_Checkout_Com_Webhook::capture_payment( $data );
				break;
			case 'payment_voided':
				$response = WC_Checkout_Com_Webhook::void_payment( $data );
				break;
			case 'payment_capture_declined':
				$response = WC_Checkout_Com_Webhook::capture_declined( $data );
				break;
			case 'payment_refunded':
				$response = WC_Checkout_Com_Webhook::refund_payment( $data );
				break;
			case 'payment_canceled':
				$response = WC_Checkout_Com_Webhook::cancel_payment( $data );
				break;
			case 'payment_declined':
			case 'payment_authentication_failed':
				$response = WC_Checkout_Com_Webhook::decline_payment( $data );
				break;

			default:
				$response = true;
				break;
		}

		$http_code = $response ? 200 : 400;

		WC_Checkoutcom_Utility::logger( $event_type . 'with response' . $http_code );

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
		status_header($status_code);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode([
			'status'  => $status_code,
			'message' => $message,
		]);

		WC_Checkoutcom_Utility::logger("Sent HTTP status: $status_code with message: $message");
		exit; // Prevent WP from sending 200.
	}
}