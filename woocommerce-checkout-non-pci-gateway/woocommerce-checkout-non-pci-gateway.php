<?php
/*
Plugin Name: Checkout Non PCI - WooCommerce Gateway
Plugin URI: https://www.checkout.com/
Description: Extends WooCommerce by Adding the Checkout Non PCI Gateway.
Version:2.5.4
Author: Checkout.com
Author URI: https://www.checkout.com/
*/
if (!session_id()) session_start();

add_action( 'plugins_loaded', 'checkout_non_pci_init', 0 );
function checkout_non_pci_init() {

    if (!class_exists('WC_Payment_Gateway')) return;

    // If we made it this far, then include our Gateway Class
    include_once('woocommerce-checkout-non-pci.php');

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'checkout_add_non_pci_gateway' );
    function checkout_add_non_pci_gateway( $methods ) {
        $methods[] = 'WC_Checkout_Non_Pci';
        return $methods;
    }
}

/* START: Add settings link */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'checkout_non_pci_action_links' );

function checkout_non_pci_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_non_pci' ) . '">' . __( 'Settings', 'woocommerce-checkout-non-pci' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
}
/* END: Add settings link */

/* START: Add Capture Charge link to order */
add_action('woocommerce_order_actions', 'checkout_non_pci_add_order_meta_box_actions');

function checkout_non_pci_add_order_meta_box_actions( $actions ) {
    $actions['checkout_non_pci_capture']    = __('Capture with Checkout.com (Non PCI Version)');
    $actions['checkout_non_pci_void']       = __('Void with Checkout.com (Non PCI Version)');
    return $actions;
}

add_action('woocommerce_order_action_checkout_non_pci_capture', 'process_order_meta_box_actions_non_pci_capture');
add_action('woocommerce_order_action_checkout_non_pci_void', 'process_order_meta_box_actions_non_pci_void');

/**
 * Capture Order
 *
 * @param $order
 * @return bool
 *
 * @version 20160315
 */
function process_order_meta_box_actions_non_pci_capture($order) {
    include_once('includes/class-wc-gateway-checkout-non-pci-request.php');

    $request = new WC_Checkout_Non_Pci_Request(new WC_Checkout_Non_Pci());

    if (!$request->canCapture($order)) {
        $_SESSION['checkout_pci_admin_error'] = 'This order cannot be captured by Checkout.com PCI method.';
        return false;
    }

    $result = $request->capture($order);

    if ($result['status'] === 'error') {
        $_SESSION['checkout_pci_admin_error'] = $result['message'];
    } else {
        $_SESSION['checkout_pci_admin_success'] = $result['message'];
    }

    return true;
}

/**
 * Void order
 *
 * @param $order
 * @return bool
 *
 * @version 20160316
 */
function process_order_meta_box_actions_non_pci_void($order) {
    include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
    $request = new WC_Checkout_Non_Pci_Request(new WC_Checkout_Non_Pci());

    if (!$request->canVoid($order)) {
        $_SESSION['checkout_non_pci_admin_error'] = 'This order cannot be voided by Checkout.com PCI method.';
        return false;
    }

    $result = $request->void($order);

    if ($result['status'] === 'error') {
        $_SESSION['checkout_non_pci_admin_error'] = $result['message'];
    } else {
        $_SESSION['checkout_non_pci_admin_success'] = $result['message'];
    }

    return true;
}

/* END: Add Capture Charge link to order */

/* START: Admin messages */
function checkout_non_pci_admin_notice_error() {
    if (empty($_SESSION['checkout_non_pci_admin_error'])) {
        return;
    }

    $class = 'notice notice-error';
    $message = __($_SESSION['checkout_non_pci_admin_error']);

    printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message);

    unset($_SESSION['checkout_non_pci_admin_error']);
}

add_action('admin_notices', 'checkout_non_pci_admin_notice_error');

function checkout_non_pci_admin_notice_success() {
    if (empty($_SESSION['checkout_non_pci_admin_success'])) {
        return;
    }

    $class = 'notice notice-success';
    $message = __($_SESSION['checkout_non_pci_admin_success']);

    printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message);

    unset($_SESSION['checkout_non_pci_admin_success']);
}

add_action('admin_notices', 'checkout_non_pci_admin_notice_success');
/* END: Admin messages */

/* START: Create table script */
register_activation_hook(__FILE__, 'checkout_non_pci_customer_cards_table_install');

global $checkoutDbVersion;
$checkoutDbVersion = "1.0";

function checkout_non_pci_customer_cards_table_install() {
    global $wpdb;
    global $checkoutDbVersion;

    $tableName = $wpdb->prefix . "checkout_customer_cards";

    if($wpdb->get_var("show tables like '$tableName'") != $tableName) {
        $sql = "CREATE TABLE {$tableName} (
	        `entity_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
	        `customer_id` INT(11) NOT NULL COMMENT 'Customer ID from Woo',
	        `card_id` VARCHAR(100) NOT NULL COMMENT 'Card ID from Checkout API',
	        `card_number` VARCHAR(4) NOT NULL COMMENT 'Short Customer Credit Card Number',
	        `card_type` VARCHAR(20) NOT NULL COMMENT 'Credit Card Type',
	        `card_enabled` BIT NOT NULL DEFAULT 1 COMMENT 'Credit Card Enabled',
	        PRIMARY KEY (`entity_id`),
	        UNIQUE INDEX `UNQ_CHECKOUT_CUSTOMER_CARDS_CUSTOMER_ID_CARD_ID_CARD_TYPE` (`customer_id`, `card_id`, `card_type`)
	    )
        COMMENT='Table for store data about customer card id from Checkout.com'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB
        AUTO_INCREMENT=3
	    ;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('checkoutDbVersion', $checkoutDbVersion);
    }

    /* Add card_enabled column if not present when updating the plugin */
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                                 WHERE table_name = '$tableName' AND column_name = 'card_enabled'");

    if(empty($row)){
        $wpdb->query("ALTER TABLE {$tableName} ADD `card_enabled` BIT NOT NULL DEFAULT 1 COMMENT 'Credit Card Enabled'");
    }
}
/* END: Create table script */

/* START: Show customer card list */
add_action('woocommerce_after_my_account', 'checkout_non_pci_customer_cards_content');

function checkout_non_pci_customer_cards_content() {
    include_once('includes/class-wc-gateway-checkout-non-pci-customer-card.php');

    $result = false;

    if (!is_user_logged_in()) {
        return $result;
    }

    $result = WC_Checkout_Non_Pci_Customer_Card::getCustomerCardListHtml(get_current_user_id());

    echo $result;

    return true;
}
/* END: Show customer card list */
