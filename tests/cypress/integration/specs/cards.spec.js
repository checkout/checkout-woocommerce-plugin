import Globals from '../config/values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;
const FRONTEND = Globals.selector.frontend;

describe('Card plugin features', function() {
    it('should complete a full payment with frames', function() {
        cy.completeFlowUtillPayment();
        cy.get(FRONTEND.order.checkout_card_option).click();
        cy.fillFramesSingelFrame('4242424242424242', '0629', '100');
        cy.get(FRONTEND.order.place_order).click();
        cy.get(FRONTEND.order.order_confirmation).should('be.visible');
    });
});
