<?php
/**
 * APMs templates class.
 *
 * @package wc_checkout_com
 */

/**
 * Class for APMs related templates.
 */
class WC_Checkoutcom_Apm_Templates extends WC_Checkoutcom_Api_Request {

	/**
	 * Render boleto form on checkout.
	 *
	 * @return void
	 */
	public static function get_boleto_details() {
		?>
		<div data-role="content" class="boleto-content">
			<div class="input-group">
				<label class="icon" for="name">
					<span class="ckojs ckojs-card"></label>
				<input type="text" id="name" name="name" placeholder="<?php echo ( __( 'Nome', 'checkout-com-unified-payments-api' ) ); ?>" class="input-control" required style="width: 100%;">
			</div>
			<div class="input-group">
				<label class="icon" for="cpf">
					<span class="ckojs ckojs-card"></label>
				<input type="text" id="cpf" name="cpf" placeholder="<?php echo ( __( 'Cadastro de Pessoas Físicas', 'checkout-com-unified-payments-api' ) ); ?>" class="input-control" required style="width: 100%;">
			</div>
		</div>
		<?php
	}

	/**
	 * Render sepa form on checkout.
	 *
	 * @param WP_User $current_user WP_User instance.
	 *
	 * @return void
	 */
	public static function get_sepa_details( $current_user ) {
		?>
		<!-- Sepa details-->
		<div class="sepa-content">
			<div class="input-group">
                <p class="sepa-example">Example: GB33BUKB20201555555555 / DE75512108001245126199 / FR7630006000011234567890189</p>
				<label style="display: block;padding-top: 20px;" class="icon" for="sepa-iban"><?php esc_html_e( 'IBAN.', 'checkout-com-unified-payments-api' ); ?><span class="ckojs ckojs-card"></label>
				<input type="text" id="sepa-iban" name="sepa-iban" placeholder="<?php esc_attr_e( 'DE00 0000 0000 0000 0000 00', 'checkout-com-unified-payments-api' ); ?>" class="input-control" required style="width: 100%;">
			</div>
			<div class="sepa-continue-btn">
				<input type="button" id="sepa-continue" name="sepa-continue" value="<?php esc_attr_e( 'Continue', 'checkout-com-unified-payments-api' ); ?>">
			</div>

			<?php
			self::get_sepa_mandate( $current_user );
			$alert = esc_html__( 'Please fill in the required fields.', 'checkout-com-unified-payments-api' );
			?>
		</div>

		<script type="text/javascript">
			jQuery('#sepa-continue').click(function(){

				if(jQuery('#sepa-iban').val().length > 0) {
					jQuery('.sepa-mandate-card').show();
				} else {
					alert('<?php echo $alert; ?>')
				}

			})

            jQuery( '#sepa-iban' ).on( 'paste', (event) => {
                const clipboardData = event.clipboardData || event.originalEvent.clipboardData || window.clipboardData;

                if ( clipboardData ){
                    let text = clipboardData.getData('text');
                    text = text.toLocaleUpperCase();
                    text = text.replace(/[^a-zA-Z0-9]/g, '');
                    event.target.value = text;
                    event.preventDefault();
                }
            })

            jQuery( '#sepa-iban' ).on( 'keypress', function (event) {
                const evt = event || window.event;

                // Reject input if not a-z or A-Z or 0-9 .
                const regex = new RegExp("^[a-zA-Z0-9]+$");
                const key   = String.fromCharCode( ! event.charCode ? event.which : event.charCode );
                if ( ! regex.test(key) ) {
                    event.preventDefault();
                    return false;
                }

                // Ensure we only handle printable keys, excluding enter and space.
                const charCode = typeof evt.which == "number" ? evt.which : evt.keyCode;
                if (charCode && charCode > 32) {
                    const keyChar = String.fromCharCode(charCode);

                    // Transform typed character.
                    let mappedChar = keyChar.toLocaleUpperCase();
                    let start, end;
                    if ( typeof this.selectionStart == "number" && typeof this.selectionEnd == "number" ) {

                        start = this.selectionStart;
                        end   = this.selectionEnd;

                        this.value = this.value.slice( 0, start ) + mappedChar + this.value.slice( end );

                        // Move the caret.
                        this.selectionStart = this.selectionEnd = start + 1;
                    }
                }

                return false;
            });
		</script>

		<?php
	}

	/**
	 * Render sepa mandate content on checkout.
	 *
	 * @param WP_User $current_user WP_User instance.
	 *
	 * @return void
	 */
	private static function get_sepa_mandate( $current_user ) {
		global $wp;

		?>
	<div class="sepa-mandate-card" style="display: none;">
		<div class="sepa-card-header">
			<div class="sepa-card-header-text">
				<div class="sepa-card-title">
					<h4 style="font-weight: bold;"><?php esc_html_e( 'SEPA Direct Debit Mandate for single payment', 'checkout-com-unified-payments-api' ); ?></h4>
				</div>
			</div>
		</div>
		<div class="sepa-mandate-content">
			<div class="sepa-creditor">
				<h4 style="margin: unset;"><?php esc_html_e( 'Creditor', 'checkout-com-unified-payments-api' ); ?></h4>
				<h4 style="margin: unset; font-weight: bold; "><?php esc_html_e( 'b4payment GmbH', 'checkout-com-unified-payments-api' ); ?></h4>
				<p style="margin: unset;"><?php esc_html_e( 'Obermünsterstraße&nbsp;14', 'checkout-com-unified-payments-api' ); ?></p>
				<p style="margin: unset;"><?php esc_html_e( '93047&nbsp;Regensburg', 'checkout-com-unified-payments-api' ); ?></p>
				<p style="margin: unset;"><?php esc_html_e( 'GERMANY', 'checkout-com-unified-payments-api' ); ?></p>
				<br>
				<p style="margin: unset;" class="monospace"><?php esc_html_e( 'Creditor ID: DE36ZZZ00001690322', 'checkout-com-unified-payments-api' ); ?></p>
			</div>
			<div class="sepa-debitor">
				<h4 style="margin: unset;"><?php esc_html_e( 'Debtor', 'checkout-com-unified-payments-api' ); ?></h4>
				<h4 style="margin: unset; font-weight: bold; "><div class="customerName"></div></h4>
				<div class="address" style="margin: unset;">
					<p style="margin: unset;" class="address1"></p>
					<p style="margin: unset;" class="address2"></p>
					<p style="margin: unset;" class="country"></p>
				</div>
				<br>
				<p class="monospace" style="margin: unset;" id="sepa-dd-bic"></p>
				<p class="monospace" style="margin: unset;" id="sepa-dd-iban"></p>
			</div>
		</div>
		<div class="sepa-par">
			<hr style="opacity: 0.2;max-width: inherit;">
			<p><?php esc_html_e( 'By accepting this mandate form, you authorise (A) b4payment GmbH to send instructions to your bank to debit your account (B) your bank to debit your account in accordance with the instructions from b4payment GmbH.', 'checkout-com-unified-payments-api' ); ?></p>
			<p><?php esc_html_e( 'As part of your rights, you are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'checkout-com-unified-payments-api' ); ?>
			</p>
			<div class="sepa-checkbox-container" id="sepa-checkbox-container">
				<label class="sepa-checkbox-layout" for="sepa-checkbox-input">
					<div class="sepa-checkbox-inner-container">
						<input class="sepa-checkbox-input" type="checkbox" name="sepa-checkbox-input" id="sepa-checkbox-input" required>
					</div>
					<span class="sepa-checkbox-layout">
						<span style="display:none">&nbsp;</span>
						<h4 style="font-size: 12px;font-weight: 500;"><?php esc_html_e( 'I accept the mandate for a single payment', 'checkout-com-unified-payments-api' ); ?></h4>
					</span>
				</label>
			</div>
		</div>

		<div class="sepa-right">
			<hr style="opacity: 0.2;max-width: inherit;margin-bottom: 22px;">
			<div class="sepa-card-footer">
				<div class="sepa-card-footer-text">
					<div class="sepa-footer-title">
						<?php esc_html_e( 'Your rights regarding the above mandate are explained in a statement that you can obtain from your bank.', 'checkout-com-unified-payments-api' ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php

		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			$order_id = wc_clean( $wp->query_vars['order-pay'] );
			$order    = wc_get_order( $order_id );

			if ( $order ) {
				?>
				<script type="text/javascript">
					jQuery(document).ready(function(){
						var customerName = "<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>";
						jQuery('.customerName').html(customerName)
						var address1 = "<?php echo esc_html( $order->get_billing_address_1() ); ?>";
						jQuery('.address1').html(address1)
						var address2 = "<?php echo esc_html( $order->get_billing_address_2() ); ?>";
						var city = "<?php echo esc_html( $order->get_billing_city() ); ?>";
						jQuery('.address2').html(address2 + ' ' + city)
						var billingCountry = "<?php echo esc_html( WC()->countries->countries[ $order->get_billing_country() ] ); ?>";
						var country = billingCountry.toUpperCase();
						jQuery('.country').html(country)
					})
				</script>
				<?php
			}
		} else {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					var customerName = jQuery('#billing_first_name').val() + " " + jQuery('#billing_last_name').val();
					jQuery('.customerName').html(customerName)
					var address1 = jQuery('#billing_address_1').val();
					jQuery('.address1').html(address1)
					var address2 = jQuery('#billing_address_2').val();
					var city = jQuery('#billing_city').val();
					jQuery('.address2').html(address2 + ' ' + city)
					var billingCountry = jQuery("#billing_country option:selected").html();
					var country = billingCountry.toUpperCase();
					jQuery('.country').html(country)

				})
			</script>
			<?php
		}
	}
}
