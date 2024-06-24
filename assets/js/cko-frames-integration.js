/* global Frames, cko_frames_vars */
jQuery(function () {

  if ( 'yes' === cko_frames_vars['is-add-payment-method'] ) {
    jQuery("#wc-wc_checkout_com_cards-payment-token-new").prop( "checked", true );
  }


  // Set default ul to auto
  jQuery(".payment_box.payment_method_wc_checkout_com_cards > ul").css( "margin", "auto" );

  if (typeof Frames != "undefined") {
    Frames.removeAllEventHandlers();
  }

  function initFrames() {
    let localization;

    if ( ! document.getElementById( "public-key" ) ) {
        return;
    }

    try {
      localization = JSON.parse( document.getElementById("localization").value );
    } catch ( e ) {
      localization = document.getElementById("localization")?.value;
    }

    Frames.init({
      debug: document.getElementById( "debug" )?.value === "yes",
      publicKey: document.getElementById("public-key")?.value,
      localization: localization,
      schemeChoice: true,
      modes: [ Frames.modes.FEATURE_FLAG_SCHEME_CHOICE ],
      style: {
        base: {
          borderRadius: '3px'
        }
      }
    });

    if ( jQuery( ".payment_box.payment_method_wc_checkout_com_cards" )
        .children( "ul.woocommerce-SavedPaymentMethods.wc-saved-payment-methods" )
        .attr( "data-count" ) === '0'
    ) {
      jQuery( ".cko-form" ).show();
      checkUserLoggedIn();
      jQuery( ".cko-cvv" ).hide();
    }

  }

  initFrames();

  jQuery( document.body ).on( 'updated_checkout', function() {
    initFrames();

    // Show CC input if new card is selected.
    if ( ! jQuery("#wc-wc_checkout_com_cards-payment-token-new").length || jQuery("#wc-wc_checkout_com_cards-payment-token-new").is(':checked') ) {
      jQuery(".cko-form").show();
      checkUserLoggedIn();
      jQuery(".cko-cvv").hide();
    }

  } );

  // Triggers when new card details filled to update name for tokenization.
  Frames.addEventHandler(
    Frames.Events.CARD_VALIDATION_CHANGED,
    function (event) {
      var valid = Frames.isCardValid()
        if (valid) {
            let billingFirstName = document.getElementById('billing_first_name')?.value;
            let billingLastName  = document.getElementById('billing_last_name')?.value;

            let cardholderName = [ billingFirstName, billingLastName ].join(' ').trim();

            if(cardholderName.length > 0) {
                // Add the cardholder name
                Frames.cardholder = {
                   name: cardholderName
                };
            }
        }
    }
  );

  Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, onCardTokenized);

  Frames.addEventHandler(Frames.Events.CARD_BIN_CHANGED, function ( event ) {

      // Show hide co badged label.
      jQuery( '.cko-co-brand-label' ).css( 'display', event?.isCoBadged ? 'inline' : 'none' );

      if ( event?.isCoBadged ) {
          jQuery( '.cko-information-icon-tip' ).tipTip( {
              attribute: 'data-tip',
              fadeIn: 50,
              fadeOut: 50,
              delay: 200,
              maxWidth: '300px',
              keepAlive: true,
          } );
      }

  })

  function onCardTokenized(event) {
    // 2. After card tokenized this event fires.
    if (
      document.getElementById("cko-card-token").value.length === 0 ||
      document.getElementById("cko-card-token").value != event.token
    ) {
      document.getElementById("cko-card-token").value = event.token;
      document.getElementById("cko-card-bin").value = event.bin;
      document.getElementById("cko-card-scheme").value = event.preferred_scheme;
      // 3. This again click place order.
      jQuery("#place_order").trigger("click");
      document.getElementById("cko-card-token").value = "";
      document.getElementById("cko-card-scheme").value = "";
      Frames.enableSubmitForm();
    }
  }

  if ( document.getElementById("multiFrame")?.value == 1 ) {
    var logos = generateLogos();

    function generateLogos() {
      var logos = {};
      logos["card-number"] = {
        src: "card",
        alt: "card number logo",
      };
      logos["expiry-date"] = {
        src: "exp-date",
        alt: "expiry date logo",
      };
      logos["cvv"] = {
        src: "cvv",
        alt: "cvv logo",
      };
      return logos;
    }
    var errors = {};
    if ( cko_frames_vars['card-number'] ) {
      errors["card-number"] = cko_frames_vars["card-number"];
      errors["expiry-date"] = cko_frames_vars["expiry-date"];
      errors["cvv"] = cko_frames_vars["cvv"];
    } else {
      errors["card-number"] = "Please enter a valid card number";
      errors["expiry-date"] = "Please enter a valid expiry date";
      errors["cvv"] = "Please enter a valid cvv code";
    }


    Frames.addEventHandler(
      Frames.Events.FRAME_VALIDATION_CHANGED,
      onValidationChanged
    );

    function onValidationChanged(event) {
      var e = event.element;

      if (event.isValid || event.isEmpty) {
        if (e == "card-number" && !event.isEmpty) {
          showPaymentMethodIcon();
        }
        setDefaultIcon(e);
        clearErrorIcon(e);
      } else {
        if (e == "card-number") {
          clearPaymentMethodIcon();
        }
        setDefaultErrorIcon(e);
        setErrorIcon(e);
      }
    }

    function clearErrorMessage(el) {
      var selector = ".error-message__" + el;
      var message = document.querySelector(selector);
      message.textContent = "";
    }

    function clearErrorIcon(el) {
      var logo = document.getElementById("icon-" + el + "-error");
      logo.style.removeProperty("display");
    }

    function showPaymentMethodIcon(parent, pm) {
      if (parent) parent.classList.add("show");

      var logo = document.getElementById("logo-payment-method");

      if (pm) {
        var name = pm.toLowerCase();
        var test = document.getElementById("cko-icons").value;

        logo.setAttribute("src", test + name + ".svg");
        logo.setAttribute("alt", pm || "payment method");
      }
      logo.style.removeProperty("display");
    }

    function clearPaymentMethodIcon(parent) {
      if (parent) parent.classList.remove("show");

      var logo = document.getElementById("logo-payment-method");
      logo.style.setProperty("display", "none");
    }

    function setErrorMessage(el) {
      var selector = ".error-message__" + el;
      var message = document.querySelector(selector);
      message.textContent = errors[el];
    }

    function setDefaultIcon(el) {
      var selector = "icon-" + el;
      var logo = document.getElementById(selector);
      var test = document.getElementById("cko-icons").value;
      logo.setAttribute("src", test + logos[el].src + ".svg");
      logo.setAttribute("alt", logos[el].alt);
    }

    function setDefaultErrorIcon(el) {
      var selector = "icon-" + el;
      var logo = document.getElementById(selector);
      var test = document.getElementById("cko-icons").value;
      logo.setAttribute("src", test + logos[el].src + "-error.svg");
      logo.setAttribute("alt", logos[el].alt);
    }

    function setErrorIcon(el) {
      var logo = document.getElementById("icon-" + el + "-error");
      logo.style.setProperty("display", "block");
    }

    Frames.addEventHandler(
      Frames.Events.PAYMENT_METHOD_CHANGED,
      paymentMethodChanged
    );

    function paymentMethodChanged(event) {
      var pm = event.paymentMethod;
      let container = document.querySelector(".icon-container.payment-method");

      if (!pm) {
        clearPaymentMethodIcon(container);
      } else {
        clearErrorIcon("card-number");
        showPaymentMethodIcon(container, pm);
      }
    }
  }

  setTimeout(function () {
    // check if saved card exist
    if (
      jQuery(".payment_box.payment_method_wc_checkout_com_cards")
        .children("ul.woocommerce-SavedPaymentMethods.wc-saved-payment-methods")
        .attr("data-count") > 0
    ) {
      jQuery(".cko-form").hide();

      jQuery(document).on( 'change', "input[type=radio][name=wc-wc_checkout_com_cards-payment-token]", function () {
        // if ( this.value === "new" ) {
        if ( this.value === "new" && jQuery(this).is(':checked') ) {
          // display frames if new card is selected
          jQuery(".cko-form").show();
          checkUserLoggedIn();
          jQuery(".cko-cvv").hide();
        } else {
          jQuery(".cko-form").hide();
          jQuery(".cko-save-card-checkbox").hide();
          jQuery(".cko-cvv").show();

          if ( jQuery( this ).is( ':checked' ) && 'woocommerce-SavedPaymentMethods-token' === this.parentElement.getAttribute( 'class' ) ) {
              jQuery( ".cko-cvv" ).appendTo( this.parentElement );
          }

          if (document.getElementById("is-mada").value === 1) {
            if (this.value === document.getElementById("mada-token")) {
              jQuery(".cko-form").hide();
              jQuery(".cko-cvv").show();
            } else {
              jQuery(".cko-cvv").hide();
            }
          }
        }
      });
    } else {
      jQuery(".cko-form").show();
      checkUserLoggedIn();
      jQuery(
        "input[type=radio][name=wc-wc_checkout_com_cards-payment-token]"
      ).prop("checked", true);
    }

    // check if add-payment-method exist
    if (jQuery("#add_payment_method").length > 0) {
      jQuery(
        ".woocommerce-SavedPaymentMethods.wc-saved-payment-methods"
      ).hide();
      jQuery(".cko-save-card-checkbox").hide();
      jQuery(".cko-form").show();
    }

    // hook place order button
    jQuery(document.body).on("click","#place_order", function (e) {
      // check if checkout.com is selected
      if (jQuery("#payment_method_wc_checkout_com_cards").is(":checked")) {
        // check if new card exist
        if (jQuery("#wc-wc_checkout_com_cards-payment-token-new").length > 0) {
          // check if new card is selected else process with saved card
          if (
            jQuery("#wc-wc_checkout_com_cards-payment-token-new").is(":checked")
          ) {
            if (document.getElementById("cko-card-token").value.length > 0) { // 4. after tokenize check for token in HTML.
              return true;
            } else if (Frames.isCardValid()) { // 1. On place order first come here. then goto onCardTokenized()
              Frames.submitCard();
            } else if (!Frames.isCardValid()) {
              alert(document.getElementById("card-validation-alert").value);
            }
          } else if (jQuery("#add_payment_method").length > 0) {
            // check if card is valid from add-payment-method
            if (
              jQuery("#payment_method_wc_checkout_com_cards").is(":checked")
            ) {
              if (Frames.isCardValid()) {
                Frames.submitCard();
              } else {
                alert(document.getElementById("card-validation-alert").value);
              }
            }
          } else {
            return true;
          }
        } else {
          if (document.getElementById("cko-card-token").value.length > 0) { // 4. after tokenize check for token in HTML.
            return true;
          } else if (Frames.isCardValid()) {
            Frames.submitCard();
          } else if (!Frames.isCardValid()) {
            alert(document.getElementById("card-validation-alert").value);
          }
        }

        return false;
      }
    });
  }, 0);

  /**
   * function to show saved card checkbox based on logged-in user
   */
  function checkUserLoggedIn() {
    if ( document.getElementById("user-logged-in")?.value ) {
      jQuery(".cko-save-card-checkbox").show();
    } else {
      jQuery(".cko-save-card-checkbox").hide();
    }
  }
});
