jQuery(function ($) {
  googlePayUiController = (function () {
    var DOMStrings = {
      buttonId: "upycom_googlePay",
      buttonClass: "google-pay-button",
      googleButtonArea: "method_wc_uprise_payment_google_pay",
      buttonArea: ".form-row.place-order",
      placeOrder: "#place_order",
      paymentOptionLabel: "#dt_method_uprisepaygooglepay > label:nth-child(2)",
      iconSpacer: "cko-wallet-icon-spacer",
      token: "google-cko-card-token",
      paymentMethodName: "wc_uprise_payment_google_pay",
    };

    return {
      hideDefaultPlaceOrder: function () {
        jQuery(document).on(
          "change",
          "input[name='payment_method']",
          function (e) {
            if (jQuery(this).val() === DOMStrings.paymentMethodName) {
              // Google Pay selected.
              jQuery("#upycom_googlePay").show();

              jQuery(DOMStrings.placeOrder).hide();
              jQuery("#place_order").prop("disabled", true);
            } else if ("wc_uprise_payment_apple_pay" === this.value) {
              jQuery("#upycom_googlePay").hide();
              jQuery("#place_order").hide();
            } else {
              jQuery("#upycom_googlePay").hide();

              jQuery("#place_order").prop("disabled", false);
              jQuery(DOMStrings.placeOrder).show();
            }
          }
        );
      },
      addGooglePayButton: function (type) {
        if (jQuery("#upycom_googlePay").length) {
          return;
        }

        // Create the Google Pay Button.
        var button = document.createElement("button");
        button.id = DOMStrings.buttonId;
        // Add button class based on the user configuration.
        button.className = DOMStrings.buttonClass + " " + type;
        // Append the Google Pay button to the GooglePay area.
        jQuery("#payment").append(button);
        // Hide Google Pay button
        jQuery("#upycom_googlePay").hide();

        // On page load if Google Pay is selected, show the button.
        if (
          jQuery("#payment_method_wc_uprise_payment_google_pay").is(":checked")
        ) {
          // Disable place order button.
          jQuery("#place_order").hide();
          // Show Google Pay button.
          jQuery("#upycom_googlePay").show();
        }
      },
      addIconSpacer: function () {
        jQuery(DOMStrings.paymentOptionLabel).append(
          "<div class='" + iconSpacer + "'></div>"
        );
      },
      getElements: function () {
        return {
          googlePayButtonId: jQuery(DOMStrings.buttonId),
          googlePayButtonClass: jQuery(DOMStrings.buttonClass),
          placeOrder: jQuery(DOMStrings.defaultPlaceOrder),
          buttonArea: jQuery(DOMStrings.buttonArea),
        };
      },
      getSelectors: function () {
        return {
          googlePayButtonId: DOMStrings.buttonId,
          googlePayButtonClass: DOMStrings.buttonClass,
          placeOrder: DOMStrings.defaultPlaceOrder,
          buttonArea: DOMStrings.buttonArea,
          token: DOMStrings.token,
        };
      },
    };
  })();

  googlePayTransactionController = (function (googlePayUiController) {
    var environment = upy_google_pay_vars.environment;
    var publicKey = upy_google_pay_vars.public_key;
    var merchantId = upy_google_pay_vars.merchant_id;
    var currencyCode = upy_google_pay_vars.currency_code;
    var totalPrice = upy_google_pay_vars.total_price;
    var buttonType = upy_google_pay_vars.button_type;

    var allowedPaymentMethods = ["CARD", "TOKENIZED_CARD"];
    var allowedCardNetworks = ["AMEX", "DISCOVER", "JCB", "MASTERCARD", "VISA"];

    var _setupClickListeners = function () {
      jQuery(document).off(
        "click",
        "#" + googlePayUiController.getSelectors().googlePayButtonId
      );

      jQuery(document).on(
        "click",
        "#" + googlePayUiController.getSelectors().googlePayButtonId,
        function (e) {
          e.preventDefault();
          _startPaymentDataRequest();
        }
      );
    };

    var _getGooglePaymentDataConfiguration = function () {
      return {
        merchantId: merchantId,
        paymentMethodTokenizationParameters: {
          tokenizationType: "PAYMENT_GATEWAY",
          parameters: {
            gateway: "checkoutltd",
            gatewayMerchantId: publicKey,
          },
        },
        allowedPaymentMethods: allowedPaymentMethods,
        cardRequirements: {
          allowedCardNetworks: allowedCardNetworks,
        },
      };
    };

    var _getGoogleTransactionInfo = function () {
      return {
        currencyCode: currencyCode,
        totalPriceStatus: "FINAL",
        totalPrice: totalPrice,
      };
    };

    var _getGooglePaymentsClient = function () {
      return new google.payments.api.PaymentsClient({
        environment: environment,
      });
    };

    var _startPaymentDataRequest = function () {
      var paymentDataRequest = _getGooglePaymentDataConfiguration();
      paymentDataRequest.transactionInfo = _getGoogleTransactionInfo();

      var paymentsClient = _getGooglePaymentsClient();
      paymentsClient
        .loadPaymentData(paymentDataRequest)
        .then(function (paymentData) {
          document.getElementById("cko-google-signature").value = JSON.parse(
            paymentData.paymentMethodToken.token
          ).signature;
          document.getElementById("cko-google-protocolVersion").value =
            JSON.parse(paymentData.paymentMethodToken.token).protocolVersion;
          document.getElementById("cko-google-signedMessage").value =
            JSON.parse(paymentData.paymentMethodToken.token).signedMessage;

          jQuery("#place_order").prop("disabled", false);
          jQuery("#place_order").trigger("click");
        })
        .catch(function (err) {
          console.error(err);
        });
    };

    return {
      init: function () {
        _setupClickListeners();
        googlePayUiController.hideDefaultPlaceOrder();
        googlePayUiController.addGooglePayButton(buttonType);
      },
    };
  })(googlePayUiController);

  jQuery(document.body).on("updated_checkout", function () {
    googlePayTransactionController.init();
  });

  // Initialise Google Pay.
  googlePayTransactionController.init();

  // Check if Google Pay method is checked.
  if (jQuery("#payment_method_wc_uprise_payment_google_pay").is(":checked")) {
    // Disable place order button.
    jQuery("#place_order").prop("disabled", true);
    jQuery("#place_order").hide();
    jQuery("#upycom_googlePay").show();
  } else {
    // Enable place order button if not Google Pay.
    jQuery("#place_order").prop("disabled", false);
    jQuery("#place_order").show();
    jQuery("#upycom_googlePay").hide();
  }
});
