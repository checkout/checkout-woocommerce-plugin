import Globals from '../config/values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;

describe('Card plugin features', function() {
    // custom comands defined in commands.js
    it('example functions', function() {
        cy.setPaymentAction('Authorize and Capture');
        cy.setCaptureDelay(0);
        cy.enable3D(true);
        cy.enableAttemptN3D(true);
        cy.enableSavedCards(true);
        cy.enableCvvForSavedCards(true);
        cy.enableDynamicDescriptor(true);
        cy.setDescritorName('Test1');
        cy.setDescritorCity('Test2');
        cy.enableMada(true);
    });
});
