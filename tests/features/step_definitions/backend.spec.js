/* eslint-disable func-names, prefer-arrow-callback, no-reserved-keys */
import Globals from '../../config/globals';

const BACKEND = Globals.selector.backend;
const VAL = Globals.value;

export default function () {
  this.Given(/^I (.*) void charge on cancelled order$/, (option) => {
    switch (option) {
      case 'enable':
        if (!browser.isSelected(BACKEND.plugin.non_pci.cancel_status_on_void)) {
          browser.click(BACKEND.plugin.non_pci.cancel_status_on_void);
        }
        break;
      case 'disable':
        if (browser.isSelected(BACKEND.plugin.non_pci.cancel_status_on_void)) {
          browser.click(BACKEND.plugin.non_pci.cancel_status_on_void);
        }
        break;
      default:
        break;
    }
  });
  this.Given(/^I set the (.*) payment action to (.*)$/, (type, mode) => {
    switch (type) {
      case 'pci':
        switch (mode) {
          case 'authorize and capture':
            browser.click(BACKEND.plugin.pci.payment_action);
            browser.selectByValue(BACKEND.plugin.pci.payment_action_selector, 'authorize_capture');
            break;
          case 'authorize only':
            browser.click(BACKEND.plugin.pci.payment_action);
            browser.selectByValue(BACKEND.plugin.pci.payment_action_selector, 'authorize');
            break;
          default:
            break;
        }
        break;
      case 'non-pci':
        switch (mode) {
          case 'authorize and capture':
            browser.click(BACKEND.plugin.non_pci.payment_action);
            browser.selectByValue(BACKEND.plugin.non_pci.payment_action_selector, 'authorize_capture');
            break;
          case 'authorize only':
            browser.click(BACKEND.plugin.non_pci.payment_action);
            browser.selectByValue(BACKEND.plugin.non_pci.payment_action_selector, 'authorize');
            break;
          default:
            break;
        }
        break;
      default:
        break;
    }
  });
  this.Given(/^I set the (.*) autocapture time to (.*)$/, (type, time) => {
    switch (type) {
      case 'pci':
        browser.setValue(BACKEND.plugin.pci.autocapture_time, time);
        break;
      case 'non-pci':
        browser.setValue(BACKEND.plugin.non_pci.autocapture_time, time);
        break;
      default:
        break;
    }
  });
  this.Given(/^I set the (.*) new order status to (.*)$/, (type, mode) => {
    switch (type) {
      case 'pci':
        switch (mode) {
          case 'hold':
            browser.click(BACKEND.plugin.pci.new_order_status);
            browser.selectByValue(BACKEND.plugin.pci.new_order_status_selector, 'on-hold');
            break;
          case 'processing':
            browser.click(BACKEND.plugin.pci.payment_action);
            browser.selectByValue(BACKEND.plugin.pci.new_order_status_selector, 'processing');
            break;
          default:
            break;
        }
        break;
      case 'non-pci':
        switch (mode) {
          case 'hold':
            browser.click(BACKEND.plugin.non_pci.new_order_status);
            browser.selectByValue(BACKEND.plugin.non_pci.new_order_status_selector, 'on-hold');
            break;
          case 'processing':
            browser.click(BACKEND.plugin.non_pci.payment_action);
            browser.selectByValue(BACKEND.plugin.non_pci.new_order_status_selector, 'processing');
            break;
          default:
            break;
        }
        break;
      default:
        break;
    }
  });
  this.Given(/^I (.*) three d for (.*)$/, (option, type) => {
    switch (type) {
      case 'non-pci':
        switch (option) {
          case 'enable':
            browser.click(BACKEND.plugin.non_pci.three_d);
            browser.selectByValue(BACKEND.plugin.non_pci.three_d_selector, '2');
            break;
          case 'disable':
            browser.click(BACKEND.plugin.non_pci.three_d);
            browser.selectByValue(BACKEND.plugin.non_pci.three_d_selector, '1');
            break;
          default:
            break;
        }
        break;
      case 'pci':
        switch (option) {
          case 'enable':
            browser.click(BACKEND.plugin.pci.three_d);
            browser.selectByValue(BACKEND.plugin.pci.three_d_selector, '2');
            break;
          case 'disable':
            browser.click(BACKEND.plugin.pci.three_d);
            browser.selectByValue(BACKEND.plugin.pci.three_d_selector, '1');
            break;
          default:
            break;
        }
        break;
      default:
        break;
    }
  });
  this.Given(/^I (.*) saved cards for (.*)$/, (option, type) => {
    switch (type) {
      case 'non-pci':
        switch (option) {
          case 'enable':
            if (!browser.isSelected(BACKEND.plugin.non_pci.save_cards)) {
              browser.click(BACKEND.plugin.non_pci.save_cards);
            }
            break;
          case 'disable':
            if (browser.isSelected(BACKEND.plugin.non_pci.save_cards)) {
              browser.click(BACKEND.plugin.non_pci.save_cards);
            }
            break;
          default:
            break;
        }
        break;
      case 'pci':
        switch (option) {
          case 'enable':
            if (!browser.isSelected(BACKEND.plugin.pci.save_cards)) {
              browser.click(BACKEND.plugin.pci.save_cards);
            }
            break;
          case 'disable':
            if (browser.isSelected(BACKEND.plugin.pci.save_cards)) {
              browser.click(BACKEND.plugin.pci.save_cards);
            }
            break;
          default:
            break;
        }
        break;
      default:
        break;
    }
  });
  this.Given(/^I set the integration type to (.*)$/, (option) => {
    switch (option) {
      case 'js':
        browser.click(BACKEND.plugin.non_pci.integration);
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'checkoutjs');
        browser.click(BACKEND.plugin.non_pci.title); // contex switch to the tile field to avoid dropdown missing the focus 
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'checkoutjs');
        browser.click(BACKEND.plugin.non_pci.title);
        break;
      case 'hosted':
        browser.click(BACKEND.plugin.non_pci.integration);
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'hosted');
        browser.click(BACKEND.plugin.non_pci.title);
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'hosted');
        browser.click(BACKEND.plugin.non_pci.title);
        break;
      case 'frames':
        browser.click(BACKEND.plugin.non_pci.integration);
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'frames');
        browser.click(BACKEND.plugin.non_pci.title);
        browser.selectByValue(BACKEND.plugin.non_pci.integration_selector, 'frames');
        browser.click(BACKEND.plugin.non_pci.title);
        break;
      default:
        break;
    }
  });
  this.Given(/^I customise the js and hosted solution$/, () => {
    browser.setValue(BACKEND.plugin.non_pci.lightbox_url, VAL.customisation.lightbox_url);
    browser.setValue(BACKEND.plugin.non_pci.theme, VAL.customisation.theme);
    browser.setValue(BACKEND.plugin.non_pci.js_title, VAL.customisation.js_title);
    browser.setValue(BACKEND.plugin.non_pci.widget_color, VAL.customisation.widget_color);
    browser.setValue(BACKEND.plugin.non_pci.form_button_color, VAL.customisation.form_button_color);
    browser.setValue(BACKEND.plugin.non_pci.form_label_color, VAL.customisation.form_label_color);
    browser.setValue(BACKEND.plugin.non_pci.opacity, VAL.customisation.opacity);
    if (!browser.isSelected(BACKEND.plugin.non_pci.currency_code)) {
      browser.click(BACKEND.plugin.non_pci.currency_code);
    }
    browser.click(BACKEND.plugin.non_pci.overlay_shade);
    browser.selectByValue(BACKEND.plugin.non_pci.overlay_shade_selector, 'light');
  });
}
