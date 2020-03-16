import Globals from '../integration/config/values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;
const FRONTEND = Globals.selector.frontend;

Cypress.Commands.add('loginAdmin', () => {
    cy.visit(URL.wordpress_base + URL.admin_path);
    // only login whne you have to
    cy.get('body').then($body => {
        if ($body.find(BACKEND.admin_username).length > 0) {
            cy.get(BACKEND.admin_username)
                .clear()
                .click()
                .type(VAL.admin.username);
            cy.get(BACKEND.admin_password)
                .clear()
                .click()
                .type(VAL.admin.password);
            cy.get(BACKEND.admin_sign_in).click();
        }
    });
});

// pass "Authorize only" or "Authorize and Capture"
Cypress.Commands.add('setPaymentAction', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.payment_action).select(value);
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass integer value
Cypress.Commands.add('setCaptureDelay', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.capture_delay)
        .clear()
        .click()
        .type(value);
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enable3D', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card['3ds']).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enableAttemptN3D', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.atempt_n3d).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enableSavedCards', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.saved_card).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enableCvvForSavedCards', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.require_cvv).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enableDynamicDescriptor', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.dynamic_descriptor).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass text value
Cypress.Commands.add('setDescritorName', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.dd_name)
        .clear()
        .click()
        .type(value);
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass text value
Cypress.Commands.add('setDescritorCity', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.dd_city)
        .clear()
        .click()
        .type(value);
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

// pass boolean
Cypress.Commands.add('enableMada', value => {
    cy.loginAdmin();
    cy.visit(URL.card_settings);
    cy.get(BACKEND.plugin.card.mada).select(value === true ? 'Yes' : 'No');
    cy.get(BACKEND.save_cahanges).click();
    cy.get('#message').then($el => {
        expect($el.text()).to.equal('Your settings have been saved.');
    });
});

Cypress.Commands.add('completeGuessDetails', value => {
    cy.get(FRONTEND.order.firstname)
        .clear()
        .click()
        .type(VAL.guest.firstname);
    cy.get(FRONTEND.order.lastname)
        .clear()
        .click()
        .type(VAL.customer.lastname);
    cy.get(FRONTEND.order.street)
        .clear()
        .click()
        .type(VAL.guest.street);
    cy.get(FRONTEND.order.town)
        .clear()
        .click()
        .type(VAL.guest.town);
    cy.get(FRONTEND.order.postcode)
        .clear()
        .click()
        .type(VAL.guest.postcode);
    cy.get(FRONTEND.order.phone)
        .clear()
        .click()
        .type(VAL.guest.phone);
    cy.get(FRONTEND.order.email)
        .clear()
        .click()
        .type(VAL.guest.email);
});

Cypress.Commands.add('completeFlowUtillPayment', () => {
    cy.visit(URL.wordpress_base + URL.product_path);
    cy.get(FRONTEND.order.add).click();
    cy.get(FRONTEND.order.view_cart).click();
    cy.get(FRONTEND.order.go_to_checkout).click();
    cy.completeGuessDetails();
});

// pass the card number, expiry date (as 0629) and the cvv
Cypress.Commands.add('fillFramesSingelFrame', (number, date, cvv) => {
    cy.get(FRONTEND.singleFrames.selector).then($iframe => {
        const $body = $iframe.contents().find('body');
        cy.wrap($body.find(FRONTEND.singleFrames.cardNumber))
            .click()
            .type(number, { force: true, delay: 50 });
        cy.wrap($body.find(FRONTEND.singleFrames.date))
            .click()
            .type(date, { force: true, delay: 50 });
        cy.wrap($body.find(FRONTEND.singleFrames.cvv))
            .click()
            .type(cvv, { force: true, delay: 50 });
    });
});
