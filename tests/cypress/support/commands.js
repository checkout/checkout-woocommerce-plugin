import Globals from '../integration/_values';
const URL = Globals.value.url;
const VAL = Globals.value;
const BACKEND = Globals.selector.backend;

// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
Cypress.Commands.add('loginAdmin', () => {
    cy.visit(URL.wordpress_base + URL.admin_path);
    cy.get(BACKEND.admin_username).type(VAL.admin.username);
    cy.get(BACKEND.admin_password).type(VAL.admin.password);
    cy.get(BACKEND.admin_sign_in).click();
});

//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
