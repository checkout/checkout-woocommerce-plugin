/* eslint-disable no-reserved-keys */
export default {
    value: {
        url: {
            wordpress_base: 'http://localhost/wordpress',
            admin_path: '/wp-admin',
            plugins_path: '/wp-admin/plugins.php',
            order_path_1: '/wp-admin/post.php?post=',
            order_path_2: '&action=edit',
            product_path: '/?product=test',
            core_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards',
            card_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=card_settings',
            order_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=orders_settings',
            google_pay_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_google_pay',
            apple_pay_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_apple_pay',
            apm_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_alternative_payments',
            debug_settings:
                'http://localhost/wordpress/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=debug_settings',
            woocommerce: 'http://localhost/wordpress/wp-admin/index.php?page=wc-setup'
        },
        admin: {
            username: 'checkout',
            password: 'Checkout17',
            three_d_password: 'Checkout1!',
            secret_key: 'sk_test_fe1ea74c-e9be-4191-9781-76ec04523e29',
            public_key: 'pk_test_537c069c-9533-47e3-9a4a-14c55b9781ee',
            private_shared_key: 'fc8b91da-a59d-480e-93d7-3dd590948b04'
        },
        guest: {
            email: 'john@smith.com',
            firstname: 'John',
            lastname: 'Smith',
            street: '42 Ealing Broadway',
            town: 'London',
            postcode: 'w1w 8sy',
            phone: '07123456789'
        },
        customer: {
            email: 'test@checkout.com',
            firstname: 'Test',
            lastname: 'Checkout',
            street: '1 Wall Street',
            town: 'London',
            country: 'GB',
            phone: '07987654321',
            password: 'Checkout17'
        },
        card: {
            visa: {
                card_number: '4242424242424242',
                month: '06',
                year: '29',
                cvv: '100'
            },
            mastercard: {
                card_number: '5436031030606378',
                month: '06',
                year: '29',
                cvv: '257'
            },
            amex: {
                card_number: '345678901234564',
                month: '06',
                year: '29',
                cvv: '1051'
            },
            diners: {
                card_number: '30123456789019',
                month: '06',
                year: '29',
                cvv: '257'
            },
            jcb: {
                card_number: '3530111333300000',
                month: '06',
                year: '29',
                cvv: '100'
            },
            discover: {
                card_number: '6011111111111117',
                month: '06',
                year: '29',
                cvv: '100'
            }
        }
    },
    selector: {
        frontend: {
            order: {
                add: '.single_add_to_cart_button',
                view_cart: 'a.button',
                go_to_checkout: '.checkout-button',
                firstname: '#billing_first_name',
                lastname: '#billing_last_name',
                street: '#billing_address_1',
                town: '#billing_city',
                postcode: '#billing_postcode',
                phone: '#billing_phone',
                email: '#billing_email',
                non_pci_option: 'li.wc_payment_method:nth-child(2)',
                non_pci_save_card: '#save-card-checkbox',
                pci_option: 'li.wc_payment_method:nth-child(3)',
                place_order: '#place_order',
                js_new_card: '.checkout-new-card-radio',
                pci_new_card:
                    '#woocommerce_checkout_pci-cc-form > ul:nth-child(1) > li:nth-child(2) > p:nth-child(1) > input:nth-child(1)',
                pci_saved_card:
                    '#woocommerce_checkout_pci-cc-form > ul:nth-child(1) > li:nth-child(1) > p:nth-child(1) > input:nth-child(1)',
                js_saved_card: '#checkout-saved-card-0',
                order_confirmation: '.woocommerce-notice',
                three_d_password: '#txtPassword',
                three_d_submit: '#txtButton',
                success_order_number: '.woocommerce-order-overview__order',
                order_status: '#select2-order_status-container'
            },
            js: {
                iframe: 'iframe:last-of-type',
                widget: '#cko-widget',
                card_number: "input[data-checkout='card-number']",
                month: "input[data-checkout='expiry-month']",
                year: "input[data-checkout='expiry-year']",
                cvv: "input[data-checkout='cvv']",
                submit: '.form-submit',
                header: '.header',
                title: 'span.title:nth-child(1)',
                logo: 'div.logo:nth-child(2) > img:nth-child(1)'
            },
            hosted: {
                card_number: 'div.input-group:nth-child(2) > input:nth-child(2)',
                month: 'div.split-view:nth-child(2) > div:nth-child(1) > input:nth-child(1)',
                year: 'div.split-view:nth-child(2) > div:nth-child(2) > input:nth-child(1)',
                cvv:
                    'div.split-view:nth-child(3) > div:nth-child(2) > div:nth-child(1) > input:nth-child(2)',
                title: '',
                pay_button: '.form-submit',
                hosted_header: '#modal-wrapper > div.cko-md-content > div > div.header',
                hosted_pay_button:
                    '#modal-wrapper > div.cko-md-content > div > div.container-body > div.content.card > form > input',
                hosted_alt_payments_tab:
                    '#modal-wrapper > div.cko-md-content > div > div.container-body > div.content-control.tabs.split-view > div.tab.lp.fragment-2.enter',
                hosted_card_tab:
                    '#modal-wrapper > div.cko-md-content > div > div.container-body > div.content-control.tabs.split-view > div.tab.active.card',
                hosted_region_selector:
                    '#modal-wrapper > div.cko-md-content > div > div.container-body > div.content-control.region-selector'
            },
            pci: {
                name: '#woocommerce_checkout_pci-card-holder-name',
                card_number: '#woocommerce_checkout_pci-card-number',
                month_year: '#woocommerce_checkout_pci-card-expiry',
                cvv: '#woocommerce_checkout_pci-card-cvc'
            }
        },
        backend: {
            save_cahanges: '.submit > .button-primary',
            admin_username: '#user_login',
            admin_password: '#user_pass',
            admin_sign_in: '#wp-submit',
            plugin: {
                activate:
                    '[data-slug="checkout-com-unified-payments-api"] > .plugin-title > .row-actions > .activate > .edit',
                core: {
                    secret_key: '#woocommerce_wc_checkout_com_cards_ckocom_sk',
                    public_key: '#woocommerce_wc_checkout_com_cards_ckocom_pk',
                    private_shared_key: '#woocommerce_wc_checkout_com_cards_ckocom_psk'
                },
                card: {
                    payment_action: '#ckocom_card_autocap',
                    capture_delay: '#ckocom_card_cap_delay',
                    '3ds': '#ckocom_card_threed',
                    atempt_n3d: '#ckocom_card_notheed',
                    saved_card: '#ckocom_card_saved',
                    require_cvv: '#ckocom_card_require_cvv',
                    dynamic_descriptor: '#ckocom_card_desctiptor',
                    dd_name: '#ckocom_card_desctiptor_name',
                    dd_city: '#ckocom_card_desctiptor_city',
                    mada: '#ckocom_card_mada'
                }
            },
            woo_plugin_state:
                '[data-slug="woocommerce"] > .plugin-title > .row-actions > :nth-child(1) > a',
            woo_deactivate:
                '[data-slug="woocommerce"] > .plugin-title > .row-actions > :nth-child(2) > a',
            woo_adress: '#store_address',
            woo_next: '.button-primary',
            woo_postcode: '#store_postcode',
            wo_pay: '.checked > div:nth-child(3) > span:nth-child(1)',
            woo_shipping_1:
                'li.wc-wizard-service-item:nth-child(2) > div:nth-child(2) > div:nth-child(2) > div:nth-child(1) > input:nth-child(1)',
            woo_shipping_2:
                'li.wc-wizard-service-item:nth-child(3) > div:nth-child(2) > div:nth-child(2) > div:nth-child(1) > input:nth-child(1)',
            woo_theme:
                'ul.wc-wizard-services:nth-child(1) > li:nth-child(1) > div:nth-child(2) > span:nth-child(1)',
            woo_skip: '.wc-return-to-dashboard',
            woo_create_product: 'a.button-primary',
            woo_product_name: '#title',
            woo_normal_price: '#_regular_price',
            woo_promo_price: '#_sale_price',
            woo_publish: '#publish',
            woo_city: '#store_city'
        }
    }
};
