<?php

class WC_Checkoutcom_Admin
{
    public static function generate_links($key, $value)
    {
        ?>
        <div class="test" style="padding-bottom: 20px;    padding-top: 10px;">
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ); ?>"
                       style="text-decoration: none"> <h2><?php _e( 'Core Settings', 'wc_checkout_com' );  ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=card_settings' ); ?>"
                       style="text-decoration: none"><?php _e( 'Card Settings', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=orders_settings' ); ?>"
                       style="text-decoration: none"><?php _e( 'Order Settings', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_google_pay' ); ?>"
                       style="text-decoration: none"><?php _e( 'Google Pay', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_apple_pay' ); ?>"
                       style="text-decoration: none"><?php _e( 'Apple Pay', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_alternative_payments' ); ?>"
                       style="text-decoration: none"><?php _e( 'Alternative Payments', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=debug_settings' ); ?>"
                       style="text-decoration: none"><?php _e( 'Debug Settings', 'wc_checkout_com' ); ?></a>
                    <p style="display: unset;opacity: 0.5;"><?php echo '|'; ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td colspan="2" class="forminp forminp-screen_button">
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=webhook' ); ?>"
                            style="text-decoration: none"><?php _e( 'Webhook', 'wc_checkout_com' ); ?></a>
                </td>
            </tr>
        </div>
        <?php
    }
}
