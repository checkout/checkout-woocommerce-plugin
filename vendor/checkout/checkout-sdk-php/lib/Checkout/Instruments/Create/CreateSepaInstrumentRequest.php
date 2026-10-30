<?php

namespace Checkout\Instruments\Create;

use Checkout\Common\InstrumentType;

/**
 * Stores SEPA account details as a payment instrument.
 */
class CreateSepaInstrumentRequest extends CreateInstrumentRequest
{
    public function __construct()
    {
        parent::__construct(InstrumentType::$sepa);
    }

    /**
     * The details of the SEPA account.
     * [Required]
     * @var CreateSepaInstrumentData
     */
    public $instrument_data;

    /**
     * The account holder details.
     * [Required]
     * @var CreateSepaAccountHolder
     */
    public $account_holder;

    /**
     * The customer details. Associates the instrument with an existing or new customer.
     * [Optional]
     * @var CreateCustomerInstrumentRequest
     */
    public $customer;
}
