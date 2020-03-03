import Globals from '../integration/config/values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;

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
