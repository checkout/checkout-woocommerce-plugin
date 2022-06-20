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
	 * Render available ideal bank list on checkout.
	 *
	 * @return void
	 */
	public static function get_ideal_bank() {
		$ideal_banks = WC_Checkoutcom_Api_Request::get_ideal_bank();

		$country = $ideal_banks['countries'];
		$issuers = $country[0]['issuers'];

		?>
			<div class="ideal-bank-info" id="ideal-bank-info">
				<div class="ideal-heading">
					<label><?php esc_html_e( 'Your Bank', 'checkout-com-unified-payments-api' ); ?></label>
				</div>
				<label for="issuer-id">

					<input name="issuer-id" list="issuer-id" style="width: 80%;">
					<datalist id="issuer-id">
						<?php foreach ( $issuers as $value ) { ?>
							<option value="<?php echo $value['bic']; ?>"><?php echo $value['name']; ?></option>
						<?php } ?>
					</datalist>
					</input>
				</label>
			</div>
		<?php
	}

	/**
	 * Render available klarna list on checkout.
	 *
	 * @param string $client_token Client token.
	 * @param array  $payment_method_categories Payment method categories.
	 *
	 * @return void
	 */
	public static function get_klarna( $client_token, $payment_method_categories ) {
		?>
		<div class="klarna-details">
			<div class="klarna_widgets">
				<?php if ( ! empty( $payment_method_categories ) ) { ?>
					<?php foreach ( $payment_method_categories as $key => $value ) { ?>
						<ul style="margin-bottom: 0px;margin-top: 0px;"><li>
							<label class="test">
								<input type="radio" class="input-radio" id="<?php echo $value['identifier']; ?>" name="klarna_widget" value="<?php echo $value['identifier']; ?>"/>
								<?php echo esc_html( $value['name'] ); ?>
							</label>
						</li></ul>
					<?php } ?>
					<?php
				} else {
					echo  __( 'Klarna is not offering any payment options for this purchase. Please choose another payment method.', 'checkout-com-unified-payments-api' );
				}
				?>
			</div>
		</div>
		<div id="klarna_container"></div>
		<?php
	}

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
				<label class="icon" for="sepa-iban">
					<span class="ckojs ckojs-card"></label>
				<input type="text" id="sepa-iban" name="sepa-iban" placeholder="<?php echo ( __( 'IBAN', 'checkout-com-unified-payments-api' ) ); ?>" class="input-control" required style="width: 100%;">
			</div>
			<div class="sepa-continue-btn">
				<input type="button" id="sepa-continue" name="sepa-continue" value="Continue">
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
		?>
	<div class="sepa-mandate-card" style="display: none;">
		<div class="sepa-card-header">
			<div class="sepa-card-header-text">
				<div class="sepa-card-title">
					<h4 style="font-weight: bold;">SEPA Direct Debit Mandate for single payment</h4>
				</div>
			</div>
		</div>
		<div class="sepa-mandate-content">
			<div class="sepa-creditor">
				<h4 style="margin: unset;">Creditor</h4>
				<h4 style="margin: unset; font-weight: bold; ">b4payment GmbH</h4>
				<p style="margin: unset;">Obermünsterstraße&nbsp;14</p>
				<p style="margin: unset;">93047&nbsp;Regensburg</p>
				<p style="margin: unset;">GERMANY</p>
				<br>
				<p style="margin: unset;" class="monospace">Creditor ID: DE36ZZZ00001690322</p>
			</div>
			<div class="sepa-debitor">
				<h4 style="margin: unset;">Debtor</h4>
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
			<p>By accepting this mandate form, you authorise (A) b4payment GmbH to send instructions to your bank to debit your account (B) your bank to debit your account in accordance with the instructions from b4payment GmbH.</p>
			<p>As part of your rights, you are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.
			</p>
			<div class="sepa-checkbox-container" id="sepa-checkbox-container">
				<label class="sepa-checkbox-layout" for="sepa-checkbox-input">
					<div class="sepa-checkbox-inner-container">
						<input class="sepa-checkbox-input" type="checkbox" name="sepa-checkbox-input" id="sepa-checkbox-input" required>
					</div>
					<span class="sepa-checkbox-layout">
						<span style="display:none">&nbsp;</span>
						<h4 style="font-size: 12px;font-weight: 500">I accept the mandate for a single payment</h4>
					</span>
				</label>
			</div>
		</div>

		<div class="sepa-right">
			<hr style="opacity: 0.2;max-width: inherit;margin-bottom: 22px;">
			<div class="sepa-card-footer">
				<div class="sepa-card-footer-text">
					<div class="sepa-footer-title">
						Your rights regarding the above mandate are explained in a statement that you can obtain from your bank.
					</div>
				</div>
			</div>
		</div>
	</div>

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
