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
				<input type="text" id="name" name="name" placeholder="<?php echo esc_attr__( 'Nome', 'checkout-com-unified-payments-api' ); ?>" class="input-control" required style="width: 100%;">
			</div>
			<div class="input-group">
				<label class="icon" for="cpf">
					<span class="ckojs ckojs-card"></label>
				<input type="text" id="cpf" name="cpf" placeholder="<?php echo esc_attr__( 'Cadastro de Pessoas Físicas', 'checkout-com-unified-payments-api' ); ?>" class="input-control" required style="width: 100%;">
			</div>
		</div>
		<?php
	}

}
