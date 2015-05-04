<?php 

interface models_InterfacePayment
{
	public function admin_options();
	public function init_form_fields();
	public function payment_fields();
	public function process_payment($order_id);
}
