/* eslint-disable func-names, prefer-arrow-callback, no-reserved-keys, no-case-declarations */
import Globals from '../../config/globals';

const VAL = Globals.value;
const FRONTEND = Globals.selector.frontend;

export default function () {
  this.Then(/^I complete the (.*) integration with a (.*) card$/, (integration, option) => {
    // const iframe = browser.element(FRONTEND.js.iframe);
    let card;
    let month;
    let year;
    let cvv;
    switch (integration) {
      case 'js':
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
        switch (option) {
          // 300 ms added to avoid context from switching before the key up event is done
          case 'visa':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.visa.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.visa.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.visa.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.visa.cvv);
            browser.pause(300);
            break;
          case 'mastercard':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.mastercard.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.mastercard.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.mastercard.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.mastercard.cvv);
            browser.pause(300);
            break;
          case 'amex':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.amex.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.card.month);
            month.setValue(VAL.card.amex.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.card.year);
            year.setValue(VAL.card.amex.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.card.cvv);
            cvv.setValue(VAL.card.amex.cvv);
            browser.pause(300);
            break;
          case 'diners':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.diners.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.diners.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.diners.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.diners.cvv);
            browser.pause(300);
            break;
          case 'jcb':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.jcb.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.jcb.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.jcb.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.jcb.cvv);
            browser.pause(300);
            break;
          case 'discover':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.discover.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.discover.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.discover.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.discover.cvv);
            browser.pause(300);
            break;
          default:
            break;
        }
        browser.click(FRONTEND.js.submit);
        browser.frameParent();
        break;
      case 'frames':
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
        browser.pause(5000);
        const frames = browser.element(FRONTEND.js.iframe);
        browser.frame(frames.value);
        browser.waitForVisible(FRONTEND.js.card_number, VAL.timeout_out);
        switch (option) {
          // 300 ms added to avoid context from switching before the key up event is done
          case 'visa':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.visa.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.visa.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.visa.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.visa.cvv);
            browser.pause(300);
            break;
          case 'mastercard':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.mastercard.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.mastercard.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.mastercard.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.mastercard.cvv);
            browser.pause(300);
            break;
          case 'amex':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.amex.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.card.month);
            month.setValue(VAL.card.amex.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.card.year);
            year.setValue(VAL.card.amex.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.card.cvv);
            cvv.setValue(VAL.card.amex.cvv);
            browser.pause(300);
            break;
          case 'diners':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.diners.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.diners.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.diners.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.diners.cvv);
            browser.pause(300);
            break;
          case 'jcb':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.jcb.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.jcb.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.jcb.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.jcb.cvv);
            browser.pause(300);
            break;
          case 'discover':
            card = browser.element(FRONTEND.js.card_number);
            card.setValue(VAL.card.discover.card_number);
            browser.pause(300);
            month = browser.element(FRONTEND.js.month);
            month.setValue(VAL.card.discover.month);
            browser.pause(300);
            year = browser.element(FRONTEND.js.year);
            year.setValue(VAL.card.discover.year);
            browser.pause(300);
            cvv = browser.element(FRONTEND.js.cvv);
            cvv.setValue(VAL.card.discover.cvv);
            browser.pause(300);
            break;
          default:
            break;
        }
        browser.frameParent();
        break;
      case 'hosted':
        browser.waitUntil(function () {
          return browser.isVisible(FRONTEND.hosted.hosted_header);
        }, VAL.timeout_out, 'hosted page should be visible');
        switch (option) {
          case 'visa':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.visa.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.visa.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.visa.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.visa.cvv);
            break;
          case 'mastercard':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.mastercard.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.mastercard.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.mastercard.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.mastercard.cvv);
            break;
          case 'amex':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.amex.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.amex.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.amex.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.amex.cvv);
            break;
          case 'diners':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.diners.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.diners.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.diners.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.diners.cvv);
            break;
          case 'jcb':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.jcb.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.jcb.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.jcb.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.jcb.cvv);
            break;
          case 'discover':
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.discover.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.discover.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.discover.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.discover.cvv);
            break;
          default:
            card = browser.element(FRONTEND.hosted.card_number);
            card.setValue(VAL.card.visa.card_number);
            month = browser.element(FRONTEND.hosted.month);
            month.setValue(VAL.card.visa.month);
            year = browser.element(FRONTEND.hosted.year);
            year.setValue(VAL.card.visa.year);
            cvv = browser.element(FRONTEND.hosted.cvv);
            cvv.setValue(VAL.card.visa.cvv);
            break;
        }
        browser.click(FRONTEND.hosted.pay_button);
        break;
      case 'pci':
        browser.waitForVisible(FRONTEND.pci.card_number, VAL.timeout_out);
        switch (option) {
          case 'visa':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.visa.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.visa.month + '/' + VAL.card.visa.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.visa.cvv);
            break;
          case 'mastercard':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.mastercard.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.mastercard.month + '/' + VAL.card.mastercard.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.mastercard.cvv);
            break;
          case 'amex':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.amex.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.amex.month + '/' + VAL.card.amex.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.amex.cvv);
            break;
          case 'diners':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.diners.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.diners.month + '/' + VAL.card.diners.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.mastercard.cvv);
            break;
          case 'jcb':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.jcb.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.jcb.month + '/' + VAL.card.jcb.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.jcb.cvv);
            break;
          case 'discover':
            browser.setValue(FRONTEND.pci.name, VAL.customer.firstname);
            browser.setValue(FRONTEND.pci.card_number, VAL.card.discover.card_number);
            browser.setValue(FRONTEND.pci.month_year, VAL.card.discover.month + '/' + VAL.card.discover.year);
            browser.setValue(FRONTEND.pci.cvv, VAL.card.discover.cvv);
            break;
          default:
            break;
        }
        browser.pause(5000);
        break;
      default:
        break;
    }
  });
  this.Then(/^I complete the three d password$/, () => {
    browser.waitUntil(function () {
      return browser.isVisible(FRONTEND.order.three_d_password);
    }, VAL.timeout_out, 'three d password field should be visible');
    browser.setValue(FRONTEND.order.three_d_password, VAL.admin.three_d_password);
    browser.click(FRONTEND.order.three_d_submit);
  });
  this.Then(/^I select the (.*) card option for pci$/, (option) => {
    switch (option) {
      case 'new':
        browser.waitForVisible(FRONTEND.order.pci_new_card, VAL.timeout_out);
        browser.click(FRONTEND.order.pci_new_card);
        break;
      case 'saved':
        browser.waitForVisible(FRONTEND.order.pci_saved_card, VAL.timeout_out);
        browser.click(FRONTEND.order.pci_saved_card);
        break;
      default:
        break;
    }
  });
}
