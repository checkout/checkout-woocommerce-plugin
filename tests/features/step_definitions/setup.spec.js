/* eslint-disable func-names, prefer-arrow-callback, no-reserved-keys */
import Globals from '../../config/globals';

const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;

export default function () {
  this.Given(/^I set the viewport and timeout$/, () => {
    this.setDefaultTimeout(120 * 1000);
    browser.setViewportSize({
      width: VAL.resolution_w,
      height: VAL.resolution_h,
    }, true);
  });
  this.Given(/^I go to the backend of Checkout's plugin$/, () => {
    browser.url(URL.wordpress_base + URL.payments_path);
    try {
      browser.waitForVisible(BACKEND.dashboard, 6000);
    } catch (err) {
      browser.setValue(BACKEND.admin_username, VAL.admin.username);
      browser.setValue(BACKEND.admin_password, VAL.admin.password);
      browser.click(BACKEND.admin_sign_in);
      browser.pause(2000);
    }
    if (browser.isVisible(BACKEND.plugin.pci.activate_pci)) {
      browser.click(BACKEND.plugin.pci.activate_pci);
    }
    if (browser.isVisible(BACKEND.plugin.non_pci.activate_non_pci)) {
      browser.click(BACKEND.plugin.non_pci.activate_non_pci);
    }
  });
  this.Given(/^I open the (.*) settings$/, (option) => {
    switch (option) {
      case 'non-pci':
        browser.click(BACKEND.plugin.non_pci.settings_non_pci);
        browser.waitUntil(function () {
          return browser.isVisible(BACKEND.plugin.non_pci.public_key);
        }, VAL.timeout_out, 'settings should be loaded');
        break;
      case 'pci':
        browser.click(BACKEND.plugin.pci.settings_pci);
        browser.waitUntil(function () {
          return browser.isVisible(BACKEND.plugin.pci.secret_key);
        }, VAL.timeout_out, 'settings should be loaded');
        break;
      default:
        break;
    }
  });
  this.Given(/^I set the (.*) sandbox keys$/, (option) => {
    switch (option) {
      case 'non-pci':
        browser.setValue(BACKEND.plugin.non_pci.public_key, VAL.admin.non_pci_public_key);
        browser.setValue(BACKEND.plugin.non_pci.secret_key, VAL.admin.non_pci_secret_key);
        browser.setValue(BACKEND.plugin.non_pci.private_shared_key, VAL.admin.non_pci_private_shared_key);
        break;
      case 'pci':
        browser.setValue(BACKEND.plugin.pci.secret_key, VAL.admin.pci_secret_key);
        browser.setValue(BACKEND.plugin.pci.private_shared_key, VAL.admin.pci_private_shared_key);
        break;
      default:
        break;
    }
  });
  this.Given(/^I save the backend settings$/, () => {
    browser.click(BACKEND.plugin.save);
  });
  this.Given(/^I create an account$/, () => {
    browser.url(URL.wordpress_base);
  });
  this.Given(/^I enable the 2 checkout plugins$/, () => {
    browser.click(BACKEND.plugin.non_pci.settings_non_pci);
    browser.waitUntil(function () {
      return browser.isVisible(BACKEND.plugin.non_pci.public_key);
    }, VAL.timeout_out, 'settings should be loaded');
    if (!browser.isSelected(BACKEND.plugin.non_pci.enable_plugin)) {
      browser.click(BACKEND.plugin.non_pci.enable_plugin);
      browser.click(BACKEND.plugin.save);
    }
    browser.url(URL.wordpress_base + URL.payments_path);
    browser.click(BACKEND.plugin.pci.settings_pci);
    browser.waitUntil(function () {
      return browser.isVisible(BACKEND.plugin.pci.secret_key);
    }, VAL.timeout_out, 'settings should be loaded');
    if (!browser.isSelected(BACKEND.plugin.pci.enable_plugin)) {
      browser.click(BACKEND.plugin.pci.enable_plugin);
      browser.click(BACKEND.plugin.save);
    }
    browser.url(URL.wordpress_base + URL.payments_path);
  });
  this.Given(/^I create a product$/, () => {
    browser.click(BACKEND.activate_woocomerce);
    browser.pause(2000);
    if (browser.isVisible(BACKEND.admin_username)) {
      browser.setValue(BACKEND.admin_username, VAL.admin.username);
      browser.setValue(BACKEND.admin_password, VAL.admin.password);
      browser.click(BACKEND.admin_sign_in);
    }
    browser.waitForVisible(BACKEND.woo_adress, VAL.timeout_out);
    browser.pause(2000);
    browser.setValue(BACKEND.woo_adress, 'London');
    browser.setValue(BACKEND.woo_city, 'London');
    browser.setValue(BACKEND.woo_postcode, 'w1w w1w');
    browser.click(BACKEND.woo_next);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.wo_pay, VAL.timeout_out);
    browser.click(BACKEND.wo_pay);
    browser.click(BACKEND.woo_next);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.woo_shipping_1, VAL.timeout_out);
    browser.setValue(BACKEND.woo_shipping_1, '0');
    browser.setValue(BACKEND.woo_shipping_2, '0');
    browser.click(BACKEND.woo_next);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.woo_theme, VAL.timeout_out);
    browser.click(BACKEND.woo_theme);
    browser.click(BACKEND.woo_next);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.woo_skip, VAL.timeout_out);
    browser.click(BACKEND.woo_skip);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.woo_create_product, VAL.timeout_out);
    browser.click(BACKEND.woo_create_product);
    browser.pause(2000);
    browser.waitForVisible(BACKEND.woo_product_name, VAL.timeout_out);
    browser.setValue(BACKEND.woo_product_name, 'test');
    browser.setValue(BACKEND.woo_normal_price, '1234');
    browser.setValue(BACKEND.woo_promo_price, '123');
    browser.pause(2000); // allow time for wordpress to update
    browser.click(BACKEND.woo_publish);
    browser.pause(2000);
    browser.url(URL.wordpress_base + URL.payments_path);
  });
}
