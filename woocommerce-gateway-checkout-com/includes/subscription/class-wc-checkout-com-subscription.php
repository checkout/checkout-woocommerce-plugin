<?php

include_once __DIR__."/../api/class-wc-checkoutcom-api-request.php";

/**
 *  This class handles the payment for subscription renewal
 */
class WC_Checkoutcom_Subscription {

    /**
     *  Save source id for each order containing subscription
     *  @param $order_id
     *  @param object $order
     *  @param string $source_id
     */
    public static function save_source_id($order_id, $order, $source_id) {
        
        // update source id for subscription payment method change
        if($order instanceof WC_Subscription) {
            update_post_meta($order->id, '_cko_source_id', $source_id);
        }

        // check for subscription and save source id
        if ( WC_Subscriptions_Order::order_contains_subscription( $order_id )) { 
            $subscriptions = wcs_get_subscriptions_for_order( $order );
            
            foreach($subscriptions as $subscription_obj) {
                update_post_meta($subscription_obj->id, '_cko_source_id', $source_id);
            }
        }
    }
}