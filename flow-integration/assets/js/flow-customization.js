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
		let family_name = document.getElementById("billing_last_name")?.value || "";
		let given_name = document.getElementById("billing_first_name")?.value || "";

		window.componentOptions.card.data = {
			cardholderName: `${given_name} ${family_name}`.trim(),
		};
	}
});

// Component name section.
window.componentName = cko_flow_customization_vars.flow_component_name;

// Locale and Translation section.
window.locale = cko_flow_customization_vars.flow_component_locale;

function isValidJson(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (error) {
		console.warn('Failed to parse translation data:', error);
		return false;
	}
}

window.translations = isValidJson(cko_flow_customization_vars.flow_component_translation)
    ? JSON.parse(cko_flow_customization_vars.flow_component_translation)
    : {};
