/* eslint-disable func-names, prefer-arrow-callback, no-reserved-keys, no-case-declarations */
import Globals from '../../config/globals';
import chai from 'chai';

const URL = Globals.value.url;
const VAL = Globals.value;
const FRONTEND = Globals.selector.frontend;

export default function () {
  this.Then(/^I complete the order flow until the payment stage$/, { wrapperOptions: { retry: 2 } }, () => {
    browser.url(URL.wordpress_base + URL.product_path);
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.add);
    }, VAL.timeout_out, 'add button should be enabled');
    browser.click(FRONTEND.order.add);
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.view_cart);
    }, VAL.timeout_out, 'view cart button should be enabled');
    browser.click(FRONTEND.order.view_cart);
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.go_to_checkout);
    }, VAL.timeout_out, 'go to checkout button should be enabled');
    browser.click(FRONTEND.order.go_to_checkout);
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.firstname);
    }, VAL.timeout_out, 'firstname field should be visible');
    if (browser.getValue(FRONTEND.order.street) !== VAL.guest.street) {
      browser.setValue(FRONTEND.order.street, VAL.guest.street);
    }
    if (browser.getValue(FRONTEND.order.town) !== VAL.guest.town) {
      browser.setValue(FRONTEND.order.town, VAL.guest.town);
    }
    if (browser.getValue(FRONTEND.order.postcode) !== VAL.guest.postcode) {
      browser.setValue(FRONTEND.order.postcode, VAL.guest.postcode);
    }
    if (browser.getValue(FRONTEND.order.firstname) !== VAL.guest.firstname) {
      browser.setValue(FRONTEND.order.firstname, VAL.guest.firstname);
    }
    if (browser.getValue(FRONTEND.order.lastname) !== VAL.guest.lastname) {
      browser.setValue(FRONTEND.order.lastname, VAL.guest.lastname);
    }
    if (browser.getValue(FRONTEND.order.phone) !== VAL.guest.phone) {
      browser.setValue(FRONTEND.order.phone, VAL.guest.phone);
    }
    if (browser.getValue(FRONTEND.order.email) !== VAL.guest.email) {
      browser.setValue(FRONTEND.order.email, VAL.guest.email);
    }
  });
  this.Then(/^I select the (.*) payment option$/, (option) => {
    switch (option) {
      case 'pci':
        browser.waitUntil(function () {
          return browser.isVisible(FRONTEND.order.pci_option);
        }, VAL.timeout_out, 'pci option should be visible');
        browser.pause(2000); // allow pci option to load
        try {
          browser.click(FRONTEND.order.pci_option);
        } catch (err) {
          browser.pause(10000); // avoid slow animation errors
          browser.click(FRONTEND.order.pci_option);
        }
        break;
      case 'non-pci':
        browser.pause(3000); // allow js to load
        browser.waitUntil(function () {
          return browser.isVisible(FRONTEND.order.non_pci_option);
        }, VAL.timeout_out, 'non-pci option should be visible');
        try {
          browser.click(FRONTEND.order.non_pci_option);
        } catch (err) {
          browser.pause(10000); // avoid slow animation errors
          browser.click(FRONTEND.order.non_pci_option);
        }
        break;
      default:
        break;
    }
  });
  this.Then(/^I select the (.*) card option$/, (option) => {
    switch (option) {
      case 'new':
        browser.waitForEnabled(FRONTEND.order.js_new_card, VAL.timeout_out);
        browser.click(FRONTEND.order.js_new_card);
        break;
      case 'saved':
        browser.waitForEnabled(FRONTEND.order.js_saved_card, VAL.timeout_out);
        browser.click(FRONTEND.order.js_saved_card);
        break;
      default:
        break;
    }
  });
  this.Then(/^I place the order$/, () => {
    browser.waitUntil(function () {
      return browser.isEnabled(FRONTEND.order.place_order);
    }, VAL.timeout_out, 'place_order button should be visible');
    browser.pause(2000); // allow module initialisation
    browser.click(FRONTEND.order.place_order);
  });
  this.Then(/^I should see the order confirmation page$/, () => {
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.order_confirmation);
    }, VAL.timeout_out, 'order confirmation should be visible');
  });
  this.Then(/^I enable the option to save the card$/, () => {
    browser.pause(5000);
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.non_pci_save_card);
    }, VAL.timeout_out, 'save card option should be visible');
    browser.click(FRONTEND.order.non_pci_save_card);
  });
  this.Then(/^I should see the new order status as hold$/, () => {
    browser.pause(3000);
    const orderNumber = browser.getText(FRONTEND.order.success_order_number).match(/\d+/)[0];
    browser.url(URL.wordpress_base + URL.order_path_1 + orderNumber + URL.order_path_2);
    browser.waitUntil(function () {
      return browser.getText(FRONTEND.order.order_status) === 'On hold';
    }, VAL.timeout_out, 'order status should be set to hold');
  });
  this.Then(/^I check the customisation option$/, () => {
    browser.waitForVisible(FRONTEND.js.widget, VAL.timeout_out);
    chai.expect(browser.getCssProperty(FRONTEND.js.widget, 'background-color').parsed.hex.toUpperCase()).to.equal(VAL.customisation.widget_color);
    browser.waitUntil(function () {
      return browser.isEnabled(FRONTEND.order.place_order);
    }, VAL.timeout_out, 'place_order button should be visible');
    browser.pause(2000); // allow module initialisation
    browser.click(FRONTEND.order.place_order);
    try {
      browser.waitForVisible(FRONTEND.js.iframe, VAL.timeout_out);
    } catch (err) {
      browser.refresh();
      browser.waitUntil(function () {
        return browser.isVisible(FRONTEND.order.firstname);
      }, VAL.timeout_out, 'firstname field should be visible');
      if (browser.getValue(FRONTEND.order.street) !== VAL.guest.street) {
        browser.setValue(FRONTEND.order.street, VAL.guest.street);
      }
      if (browser.getValue(FRONTEND.order.town) !== VAL.guest.town) {
        browser.setValue(FRONTEND.order.town, VAL.guest.town);
      }
      if (browser.getValue(FRONTEND.order.postcode) !== VAL.guest.postcode) {
        browser.setValue(FRONTEND.order.postcode, VAL.guest.postcode);
      }
      if (browser.getValue(FRONTEND.order.firstname) !== VAL.guest.firstname) {
        browser.setValue(FRONTEND.order.firstname, VAL.guest.firstname);
      }
      if (browser.getValue(FRONTEND.order.lastname) !== VAL.guest.lastname) {
        browser.setValue(FRONTEND.order.lastname, VAL.guest.lastname);
      }
      if (browser.getValue(FRONTEND.order.phone) !== VAL.guest.phone) {
        browser.setValue(FRONTEND.order.phone, VAL.guest.phone);
      }
      if (browser.getValue(FRONTEND.order.email) !== VAL.guest.email) {
        browser.setValue(FRONTEND.order.email, VAL.guest.email);
      }
      if (browser.getValue(FRONTEND.order.postcode) !== VAL.guest.postcode) {
        browser.setValue(FRONTEND.order.postcode, VAL.guest.postcode);
      }
      try {
        browser.click(FRONTEND.order.non_pci_option);
      } catch (er) {
        browser.pause(5000); // avoid slow animation errors
        browser.click(FRONTEND.order.non_pci_option);
      }
      browser.waitForEnabled(FRONTEND.order.js_new_card, VAL.timeout_out);
      browser.click(FRONTEND.order.js_new_card);
      browser.waitForVisible(FRONTEND.js.iframe, VAL.timeout_out);
    }
    const js = browser.element(FRONTEND.js.iframe);
    browser.frame(js.value);
    browser.waitForVisible(FRONTEND.js.card_number, VAL.timeout_out);
    chai.expect(browser.getCssProperty(FRONTEND.js.header, 'background-color').parsed.hex.toUpperCase()).to.equal(VAL.customisation.theme);
    chai.expect(browser.getText(FRONTEND.js.title)).to.equal(VAL.customisation.js_title);
    chai.expect(browser.getAttribute(FRONTEND.js.logo, 'src')).to.equal(VAL.customisation.lightbox_url);
    browser.frameParent();
  });
}
