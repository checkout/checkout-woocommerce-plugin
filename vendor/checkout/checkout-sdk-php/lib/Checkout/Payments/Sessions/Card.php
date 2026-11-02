<?php

namespace Checkout\Payments\Sessions;

use Checkout\Common\AccountHolder;

class Card
{
    /**
     * Specifies whether you intend to store the cardholder's payment details.
     * Values: disabled, enabled, collect_consent
     * @var string value of StorePaymentDetailsType
     */
    public $store_payment_details;

    /**
     * The account holder's details.
     * @var AccountHolder
     */
    public $account_holder;
}
