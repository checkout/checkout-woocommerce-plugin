[![N|Solid](https://cdn.checkout.com/img/checkout-logo-online-payments.jpg)](https://checkout.com/)

# Woocommerce Extension
[Checkout.com](https://www.checkout.com "Checkout.com") is a software platform that has integrated 100% of the value chain to create payment infrastructures that truly make a difference.

This extension allows shop owners to process online payments (card / alternative payments) using:
  - **Frames.js** - Customisable payment form, embedded within your website
  - **Apple Pay & Google Pay** - Shoppers can pay using mobile wallets
  - **Alternative payments** - Shoppers can pay using local payment options (Sofort, iDEAL, Boleto ... etc.)

# Installation
You can find a full installation guide [here](https://github.com/checkout/checkout-woocommerce-plugin/wiki/Installation)

# Initial Setup
If you do not have an account yet, simply go to [checkout.com](https://checkout.com/) and hit the "Get Test Account" button.

# Keys
There are 3 keys to configure in the module:
- **Secret Key**
- **Public Key**
- **Private Shared Key** (not required if using v4.2.0+ of our WooCommerce plugin)

> The Private Shared Key is generated when you [configure the Webhook URL](https://docs.checkout.com/the-hub/manage-webhooks) in the Checkout HUB.

# Webhook
In order to keep WooCommerce order statuses in sync you need to configure the following webhook URL in your Checkout HUB (where _example.com_ is your store URL):

> The following URL format is for plugins versions 4.X or newer; click [here](https://github.com/checkout/checkout-woocommerce-plugin/wiki/URLs--2.x) to get the URLs for older plugin versions


| URL Example | API Version | Events |
| ------ | ------ | ------ |
| _example.com_**/?wc-api=wc_checkoutcom_webhook** &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | 2.0 | All |

> You can see a guide on how to manage webhooks in the HUB [here](https://docs.checkout.com/the-hub/manage-webhooks) ; You can find test card details [here](https://docs.checkout.com/testing)

# Going LIVE

Upon receiving your live credentials from your account manager, here are the required steps to start processing live transactions:

- In the plugin settings, input your **Live** keys
- Switch the _Endpoint URL mode_ to **Live**.

# Development environment Setup

- Clone the repository to in `wp-content/plugins` folder with the name `woocommerce-gateway-checkout-com`
  `git clone git@github.com:checkout/checkout-woocommerce-plugin.git woocommerce-gateway-checkout-com`
  or
  `git clone https://github.com/checkout/checkout-woocommerce-plugin.git woocommerce-gateway-checkout-com`
- Use required `npm` version by executing `nvm use`.
- Install the `npm` packages using `npm install`.
- Install the `composer` packages using `composer install`.
- During pushing the committing the code the PHPCS check will run at pre-commit hook. **So, till the PHPCS errors are not fixed for during commit we have to use `--no-verify` option in the git commit command.**


# Reference

You can find our complete Documentation [here](http://docs.checkout.com/).
If you would like to be assigned an account manager, please contact us at sales@checkout.com
For help during the integration process you can contact us at integration@checkout.com
For support, you can contact us at support@checkout.com

_Checkout.com is authorised and regulated as a Payment institution by the UK Financial Conduct Authority._
