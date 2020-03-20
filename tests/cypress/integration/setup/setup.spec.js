import Globals from '../config/values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;

describe('Setup Wocommerce', function() {
    it('should login in the admin', function() {
        cy.loginAdmin();
    });

    it('should configure woocommerce', function() {
        cy.loginAdmin();
        cy.visit(URL.wordpress_base + URL.plugins_path);
        cy.get(BACKEND.woo_plugin_state).then($button => {
            let wocoommerceIsActivated = false;
            this.wocoommerceIsActivated = $button.text() === 'Settings';
            // only run setup if wocommerce is not activated yet
            console.log(wocoommerceIsActivated);
            if (!($button.text() === 'Settings')) {
                console.log('Activieazal');
                cy.get(BACKEND.woo_plugin_state).click();
                cy.visit(URL.woocommerce);
                cy.get(BACKEND.woo_adress).type('London');
                cy.get(BACKEND.woo_city).type('London');
                cy.get(BACKEND.woo_postcode).type('w1w w1w');
                cy.get(BACKEND.woo_next).click();
                cy.get(BACKEND.wo_pay).click();
                cy.get(BACKEND.woo_next).click();
                cy.get(BACKEND.woo_shipping_1).type('0');
                cy.get(BACKEND.woo_shipping_2).type('0');
                cy.get(BACKEND.woo_next).click();
                cy.get(BACKEND.woo_theme).click();
                cy.get(BACKEND.woo_next).click();
                cy.get(BACKEND.woo_skip).click();
                cy.get(BACKEND.woo_create_product).click();
                cy.get(BACKEND.woo_product_name).type('test');
                cy.get(BACKEND.woo_normal_price).type('1234');
                cy.get(BACKEND.woo_promo_price).type('123');
                cy.get(BACKEND.woo_publish).click();
                cy.pause(4000);
            }
        });
    });

    it('should activate and configure the checkout plugin', function() {
        cy.loginAdmin();
        cy.visit(URL.wordpress_base + URL.plugins_path);
        cy.get(BACKEND.plugin.activate).click();
        cy.visit(URL.core_settings);
        cy.get(BACKEND.plugin.core.secret_key)
            .clear()
            .click()
            .type(VAL.admin.secret_key);
        cy.get(BACKEND.plugin.core.public_key)
            .clear()
            .click()
            .type(VAL.admin.public_key);
        cy.get(BACKEND.plugin.core.private_shared_key)
            .clear()
            .type(VAL.admin.private_shared_key);
        cy.get(BACKEND.save_cahanges).click();
        // cy.get('#message').then($el => {
        //     expect($el.text()).to.equal('Your settings have been saved.');
        // });
    });
});
