<?php
/**
 * Class for manipulate customer credit cards
 *
 * Class WC_Checkout_Non_Pci_Customer_Card
 *
 * @version 20160317
 */
class WC_Checkout_Non_Pci_Customer_Card
{
    const CUSTOMER_CARDS_TABLE_NAME = 'checkout_customer_cards';

    /**
     * Save customer card details
     *
     * @param $response
     * @param $customerId
     * @return bool
     *
     * @version 20160317
     */
    public static function saveCard($response, $customerId, $saveCardChecked) {
        global $wpdb;

        if (empty($response) || !is_object($response) || !$customerId) {
            return false;
        }

        $last4      = $response->getCard()->getLast4();
        $cardId     = $response->getCard()->getId();
        $cardType   = $response->getCard()->getPaymentMethod();
        $ckoCustomerId = $response->getCard()->getCustomerId();

        if (empty($last4) || empty($cardId) || empty($cardType) || empty($ckoCustomerId)) {
            return false;
        }

        if (self::isExists($customerId, $cardId, $cardType) && $saveCardChecked ) {
            $wpdb->update(self::getCustomerCardsTableName(),
                array(
                    'card_enabled'  => esc_sql($saveCardChecked)
                ),
                array(
                    'customer_id'   => esc_sql($customerId),
                    'card_id'       => esc_sql($cardId),
                )
            );
        }

        if(self::isCkoCustomerIdExists($customerId, $cardId)){
            $wpdb->update(self::getCustomerCardsTableName(),
                array(
                    'cko_customer_id'  => esc_sql($ckoCustomerId),
                ),
                array(
                    'customer_id'   => esc_sql($customerId),
                    'card_id'       => esc_sql($cardId),
                )
            );
        }

        $wpdb->insert(self::getCustomerCardsTableName(),
            array(
                'customer_id'   => esc_sql($customerId),
                'card_id'       => esc_sql($cardId),
                'card_number'   => esc_sql($last4),
                'card_type'     => esc_sql($cardType),
                'card_enabled'  => esc_sql($saveCardChecked),
            )
        );

        return true;
    }

     /**
    * Return true if cko_customer_id does not exist
    *
    * @param $customerId
    * @param $cardId
    * @return bool
    *
    * @version 20180214
    */
    public static function isCkoCustomerIdExists($customerId, $cardId) {
        global $wpdb;

        $tableName  = self::getCustomerCardsTableName();
        $sql        = $wpdb->prepare("SELECT * FROM {$tableName} WHERE customer_id = '%s' AND card_id = '%s' AND cko_customer_id = '';", $customerId, $cardId);

        $result = $wpdb->get_results($sql);

        return empty($result) ? false : true;
    }

    /**
     * Return true if card already added
     *
     * @param $customerId
     * @param $cardId
     * @param $cardType
     * @return bool
     *
     * @version 20160317
     */
    public static function isExists($customerId, $cardId, $cardType) {
        global $wpdb;

        $tableName  = self::getCustomerCardsTableName();
        $sql        = $wpdb->prepare("SELECT * FROM {$tableName} WHERE customer_id = '%s'
          AND card_id = '%s' AND card_type ='%s';", $customerId, $cardId, $cardType);

        $result = $wpdb->get_results($sql);

        return empty($result) ? false : true;
    }

    /**
     * Get customer cards table name
     *
     * @return string
     *
     * @version 20160317
     */
    public static function getCustomerCardsTableName() {
        global $wpdb;

        return $wpdb->prefix . self::CUSTOMER_CARDS_TABLE_NAME;
    }

    /**
     * Return table with customers cards
     *
     * @param $customerId
     * @return string
     *
     * @version 20160318
     */
    public static function getCustomerCardListHtml($customerId) {
        $cardList   = self::getCustomerCardList($customerId);
        $actionUrl  = get_site_url() . '/wp-content/plugins/woocommerce-checkout-non-pci-gateway/controllers/customer/card/delete.php';
        $result     = '';

        if (empty($cardList)) {
            return $result;
        }

        $result = "<div id='checkout-card-list'>";
        $result .= "<h2>" . __("My Saved Cards (Checkout.com)", 'woocommerce-checkout-non-pci') . "</h2>";
        $result .= "<table class='shop_table shop_table_responsive my_account_checkout_cards my_account_orders'>";
        /* START: Head */
        $result .= "<thead>";
        $result .= "<tr>";
        $result .= "<th class='card-number'>" . __("Card #", 'woocommerce-checkout-non-pci') . "</th>";
        $result .= "<th class='card-type'>" . __("Type", 'woocommerce-checkout-non-pci') . "</th>";
        $result .= "<th class='card-actions'></th>";
        $result .= "</tr>";
        $result .= "</thead>";
        /* END: Head */

        /* START: Body */
        $result .= "<tbody>";

        foreach($cardList as $row) {
            $result .= "<tr>";
            $result .= "<td>" . sprintf('xxxx-%s', $row->card_number) . "</td>";
            $result .= "<td>{$row->card_type}</td>";
            $result .= "<td class='order-actions'><a class='button view' onclick='checkoutRemoveCard({$row->entity_id})'>Delete</a></td>";
            $result .= "</tr>";
        }

        $result .= "</tbody>";
        /* END: Body */
        $result .= "</table>";
        $result .= "</div>";

        /* START: js */
        $result .= "<script type='application/javascript'>";
        $result .= "function checkoutRemoveCard(id) {
            if (!confirm('Are you sure you want to delete this card?')) {
                return false;
            }

            jQuery.post('{$actionUrl}?card=' + id, function(data) {
                var response = jQuery.parseJSON(data);

                if (response.status == 'error') {
                    alert(response.message);
                    return false;
                }

                jQuery('#checkout-card-list').html(response.message);
            });
        }";
        $result .= "</script>";
        /* END: js */

        return $result;
    }

    /**
     * Get customer card list
     *
     * @param $customerId
     * @return array|null|object
     *
     * @version 20160317
     */
    public static function getCustomerCardList($customerId) {
        global $wpdb;

        $tableName  = self::getCustomerCardsTableName();
        $sql        = $wpdb->prepare("SELECT * FROM {$tableName} WHERE customer_id = '%s' AND card_enabled = 1;", $customerId);

        $result = $wpdb->get_results($sql);

        return $result;
    }

    /**
     * Remove customer credit card
     *
     * @param $customerId
     * @param $entityId
     * @return bool
     *
     * @version 20160318
     */
    public static function removeCustomerCard($customerId, $entityId) {
        global $wpdb;

        $tableName  = self::getCustomerCardsTableName();
        $sql        = $wpdb->prepare("DELETE FROM {$tableName} WHERE customer_id = '%s' AND entity_id = '%s';", $customerId, $entityId);

        $wpdb->query($sql);

        return true;
    }

    /**
     * Get customer card data by secret code
     *
     * @param $secretCard
     * @param $customerId
     * @return array
     *
     * @version 20160321
     */
    public static function getCustomerCardData($secretCard, $customerId) {
        $result     = array();
        $cardList   = self::getCustomerCardList($customerId);

        if (!count($cardList)) {
            return $result;
        }

        foreach($cardList as $entity) {
            $secret = md5($entity->entity_id . '_' . $entity->card_number . '_' . $entity->card_type);

            if ($secretCard === $secret) {
                $result = $entity;
                break;
            }
        }

        return $result;
    }
}