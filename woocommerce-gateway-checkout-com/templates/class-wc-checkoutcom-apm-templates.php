<?php

class WC_Checkoutcom_Apm_Templates extends WC_Checkoutcom_Api_request
{
    public static function get_ideal_bank()
    {
        $ideal_banks = WC_Checkoutcom_Api_request::get_ideal_bank();

        $country = $ideal_banks->countries;
        $issuers = $country[0]['issuers'];

        foreach ($issuers as $key => $value) {
            $ideal_bank_bic = $value['bic'];
            $ideal_bank_name = $value['name'];
        }

        ?>
            <div class="ideal-bank-info" id="ideal-bank-info" style="display: none;">
                <div class="ideal-heading">
                    <label>Your Bank</label>
                </div>
                <label for="issuer-id">

                    <input name="issuer-id" list="issuer-id" style="width: 80%;">
                    <datalist id="issuer-id">
                        <?php foreach ($issuers as $value) { ?>
                            <option value="<?php echo $value["bic"]; ?>"><?php echo $value["name"];?></option>
                        <?php } ?>
                    </datalist>
                    </input>
                </label>
            </div>
        <?php
    }

    public static function get_giropay_bank()
    {
        $giropay_banks = WC_Checkoutcom_Api_request::get_giropay_bank();
        $banks = $giropay_banks->banks;

        ?>
        <div class="giropay-bank-info" id="giropay-bank-info" style="display: none;">
            <div class="giropay-heading">
                <label> Your Bank</label>
            </div>
            <label for="giropay-bank-id">
                <input name="giropay-bank-details" list="giropay-bank-details" style="width: 80%;">
                    <datalist id="giropay-bank-details">
                        <?php foreach ($banks as $key => $value) { ?>
                            <option value="<?php echo $key; ?>"><?php echo $value;?></option>
                        <?php } ?>
                    </datalist>
                </input>
            </label>
        </div>
        <?php
    }

    public static function get_klarna($client_token, $payment_method_categories)
    {
        ?>
        <div class="klarna-details">
            <div class="klarna_widgets" style="display: none">
                <?php if (!empty($payment_method_categories)) { ?>
                    <?php foreach ($payment_method_categories as $key => $value){ ?>
                        <ul style="margin-bottom: 0px;margin-top: 0px;"><li>
                            <label class="test">
                                <input type="radio" class="input-radio" id="<?php echo $value['identifier']; ?>" name="klarna_widget" value="<?php echo $value['identifier']; ?>"/>
                                <?php echo esc_html($value['name']); ?>
                            </label>
                        </li></ul>
                    <?php }?>
                <?php } ?>
            </div>
        </div>
        <div id="klarna_container"></div>
        <?php
    }

    public static function get_boleto_details()
    {
        ?>
        <div data-role="content" class="boleto-content">
            <div class="input-group">
                <label class="icon" for="name">
                    <span class="ckojs ckojs-card"></label>
                <input type="text" id="name" name="name" placeholder="<?= (__('Nome')); ?>" class="input-control" required style="width: 100%;">
            </div>
            <div class="input-group">
                <label class="icon" for="cpf">
                    <span class="ckojs ckojs-card"></label>
                <input type="text" id="cpf" name="cpf" placeholder="<?= (__('Cadastro de Pessoas Físicas')); ?>" class="input-control" required style="width: 100%;">
            </div>
            <div class="input-group">
                <label class="icon" for="birthDate">
                    <span class="ckojs ckojs-calendar"></label>
                <input type="text" id="birthDate" name="birthDate" placeholder="<?= (__('Data de Nascimento (YYYY-MM-DD)')); ?>" class="input-control" pattern="^\d{4}[\-\/\s]?((((0[13578])|(1[02]))[\-\/\s]?(([0-2][0-9])|(3[01])))|(((0[469])|(11))[\-\/\s]?(([0-2][0-9])|(30)))|(02[\-\/\s]?[0-2][0-9]))$" required style="width: 100%;">
            </div>
        </div>
        <?php
    }
}