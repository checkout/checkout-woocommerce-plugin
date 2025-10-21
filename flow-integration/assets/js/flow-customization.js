/**
 * Appearance, Button, Footnote, Label, Border and Subheading settings setion.
 */

window.appearance = {
	colorAction: cko_flow_customization_vars.flow_appearance_color_action,
	colorBackground: cko_flow_customization_vars.flow_appearance_color_background,
	colorBorder: cko_flow_customization_vars.flow_appearance_color_border,
	colorDisabled: cko_flow_customization_vars.flow_appearance_color_disabled,
	colorError: cko_flow_customization_vars.flow_appearance_color_error,
	colorFormBackground:
		cko_flow_customization_vars.flow_appearance_color_form_background,
	colorFormBorder:
		cko_flow_customization_vars.flow_appearance_color_form_border,
	colorInverse: cko_flow_customization_vars.flow_appearance_color_inverse,
	colorOutline: cko_flow_customization_vars.flow_appearance_color_outline,
	colorPrimary: cko_flow_customization_vars.flow_appearance_color_primary,
	colorSecondary: cko_flow_customization_vars.flow_appearance_color_secondary,
	colorSuccess: cko_flow_customization_vars.flow_appearance_color_success,
	button: {
		fontFamily: cko_flow_customization_vars.flow_button_font_family,
		fontSize: cko_flow_customization_vars.flow_button_font_size,
		fontWeight: cko_flow_customization_vars.flow_button_font_weight,
		letterSpacing: cko_flow_customization_vars.flow_button_letter_spacing,
		lineHeight: cko_flow_customization_vars.flow_button_line_height,
	},
	footnote: {
		fontFamily: cko_flow_customization_vars.flow_footnote_font_family,
		fontSize: cko_flow_customization_vars.flow_footnote_font_size,
		fontWeight: cko_flow_customization_vars.flow_footnote_font_weight,
		letterSpacing: cko_flow_customization_vars.flow_footnote_letter_spacing,
		lineHeight: cko_flow_customization_vars.flow_footnote_line_height,
	},
	label: {
		fontFamily: cko_flow_customization_vars.flow_label_font_family,
		fontSize: cko_flow_customization_vars.flow_label_font_size,
		fontWeight: cko_flow_customization_vars.flow_label_font_weight,
		letterSpacing: cko_flow_customization_vars.flow_label_letter_spacing,
		lineHeight: cko_flow_customization_vars.flow_label_line_height,
	},
	subheading: {
		fontFamily: cko_flow_customization_vars.flow_subheading_font_family,
		fontSize: cko_flow_customization_vars.flow_subheading_font_size,
		fontWeight: cko_flow_customization_vars.flow_subheading_font_weight,
		letterSpacing: cko_flow_customization_vars.flow_subheading_letter_spacing,
		lineHeight: cko_flow_customization_vars.flow_subheading_line_height,
	},
	borderRadius: [
		cko_flow_customization_vars.flow_form_border_radius,
		cko_flow_customization_vars.flow_button_border_radius,
	],
};

/**
 * Component Settings setion.
 */

// Expand first payment.
let expand_first_payment_method =
	cko_flow_customization_vars.flow_component_expand_first_payment_method;
if (expand_first_payment_method === "yes") {
	expand_first_payment_method = true;
} else {
	expand_first_payment_method = false;
}

window.componentOptions = {
	flow: {
		expandFirstPaymentMethod: expand_first_payment_method,
	},
	card: {
		displayCardholderName:
			cko_flow_customization_vars.flow_component_cardholder_name_position,
	},
};

// Show card holder name.
let show_card_holder_name =
	cko_flow_customization_vars.flow_show_card_holder_name;

// Saved Payment setting.
window.saved_payment = cko_flow_customization_vars.flow_saved_payment;

document.addEventListener("DOMContentLoaded", function () {
	if (show_card_holder_name === "yes") {
		// Function to set cardholder name
		function setCardholderName() {
			let family_name = "";
			let given_name = "";
			
			// Check if we're on order-pay page
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			
			if (isOrderPayPage) {
				// For order-pay pages, get billing info from order data instead of form fields
				// The order data is available in the cart-info or order-pay-info data attribute
				let orderData = null;
				
				// Try to get order data from cart-info or order-pay-info
				const cartInfoElement = document.getElementById("cart-info");
				const orderPayInfoElement = document.getElementById("order-pay-info");
				
				if (orderPayInfoElement) {
					orderData = orderPayInfoElement.getAttribute("data-order-pay");
				} else if (cartInfoElement) {
					orderData = cartInfoElement.getAttribute("data-cart");
				}
				
				if (orderData) {
					try {
						const parsedData = JSON.parse(orderData);
						
						// Get billing info from order data
						if (parsedData.billing_address) {
							given_name = parsedData.billing_address.given_name || "";
							family_name = parsedData.billing_address.family_name || "";
						}
				} catch (e) {
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.error('Error parsing order data for cardholder name:', e);
					}
				}
				}
				
				// Fallback: try to get from form fields (even if disabled)
				if (!given_name && !family_name) {
					family_name = document.getElementById("billing_last_name")?.value || "";
					given_name = document.getElementById("billing_first_name")?.value || "";
				}
			} else {
				// For regular checkout pages, get from form fields
				family_name = document.getElementById("billing_last_name")?.value || "";
				given_name = document.getElementById("billing_first_name")?.value || "";
			}

			const cardholderName = `${given_name} ${family_name}`.trim();
			
			if (cardholderName) {
				window.componentOptions.card.data = {
					cardholderName: cardholderName,
				};
			}
		}
		
		// Set cardholder name immediately
		setCardholderName();
	}
});

// Component name section.
window.componentName = cko_flow_customization_vars.flow_component_name || 'flow';

// Locale and Translation section.
window.locale = cko_flow_customization_vars.flow_component_locale;

function isValidJson(str) {
    try {
        if (!str || str.trim() === '' || str === 'null' || str === 'undefined') {
            return false;
        }
        JSON.parse(str);
        return true;
    } catch (error) {
	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.warn('Failed to parse translation data:', error);
	}
	return false;
}
}

// Safely parse translation data with fallback
window.translations = {};
if (typeof cko_flow_customization_vars !== 'undefined' && 
    cko_flow_customization_vars.flow_component_translation && 
    isValidJson(cko_flow_customization_vars.flow_component_translation)) {
    window.translations = JSON.parse(cko_flow_customization_vars.flow_component_translation);
} else {
    if (typeof ckoLogger !== 'undefined') {
        ckoLogger.debug('No valid translation data found, using empty translations object');
    }
}
