[![N|Solid](https://cdn.checkout.com/img/checkout-logo-online-payments.jpg)](https://checkout.com/)

# Woocommerce Extension&nbsp; ![N|Solid](https://circleci.com/gh/checkout/checkout-woocommerce-plugin.svg?style=shield&circle-token=4f03ec3447eff0c5348eccf22649290978066a41)

[Checkout.com](https://www.checkout.com "Checkout.com") is a software platform that has integrated 100% of the value chain to create payment infrastructures that truly make a difference.

This extension allows shop owners to process online payments (card / alternative payments) using:
  - **Frames.js** - Customisable payment form, embedded within your website
  - **Checkout.js** - Customisable payment widget 
  - **Checkout.js Hosted** - Redirection to a customisable page on Checkout.com's servers
  - **Apple Pay & Google Pay** - Shoppers can pay using mobile wallets
  - **Alternative payments** - Shoppers can pay using local payment options (Sofort, iDEAL, Boleto ... etc.)

# Installation
You can find a full installation guide [here](https://github.com/checkout/checkout-woocommerce-plugin/wiki/Installation)

# Initial Setup
If you do not have an account yet, simply go to [checkout.com](https://checkout.com/ "checkout.com") and hit the "Get Test Account" button.

# Keys
There are 3 keys that you need to configure in the NON-PCI extension, while for the PCI version you just need 2:
- **Secret Key**
- **Public Key** (not needed for PCI extension)
- **Private Shared Key**

> The Private Shared Key is generated when you [configure the Webhook URL](https://docs.checkout.com/docs/business-level-administration#section-manage-webhook-url "configure the Webhook URL") in the Checkout HUB.

# URLs
In order to successfully complete 3D Secure transactions, and to keep Woocommerce order statuses in sync you need to configure the following URLs in your Checkout HUB as follows:

> The following URL formats are for plugins versions 3.X or newer; click [here](https://docs.checkout.com) to get the URLs for older plugin versions

For the **NON-PCI** extension:

| Type | URL Example | Description |
| ------ | ------ | ------ |
| Redirections (success/fail)| _example.com_**/?wc-api=WC_Checkout_Non_Pci_Callback** | Redirect after 3D Secure |
| Webhook | _example.com_**/?wc-api=WC_Checkout_Non_Pci_Webhook** | Sync Woocommerce |

For the **PCI** extension:

| Type | URL Example | Description |
| ------ | ------ | ------ |
| Redirections (success/fail)| _example.com_**/?wc-api=WC_Checkout_Pci_Callback** | Redirect after 3D Secure |
| Webhook | _example.com_**/?wc-api=WC_Checkout_Pci_Webhook** &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| Sync Woocommerce |

> You can see a guide on how to set the URLs in the HUB [here](https://docs.checkout.com/docs/business-level-administration#section-manage-channel-urls "here") ; You can find test card details [here](https://docs.checkout.com/docs/testing#section-credit-cards "here")

# Going LIVE

Upon receiving your live credentials from your account manager, here are the required steps to start processing live transactions:

- In the plugin settings, place your **live** keys
- Switch the _Endpoint URL mode_ to **live**.
- Make sure you have configured the Redirection and Webhook URLs correctly in your **live** Checkout.com HUB


# Reference 

You can find our complete Documentation [here](http://docs.checkout.com/ "here").  
If you would like to get an account manager, please contact us at sales@checkout.com  
For help during the integration process you can contact us at integration@checkout.com  
For support, you can contact us at support@checkout.com

_Checkout.com is authorised and regulated as a Payment institution by the UK Financial Conduct Authority._
