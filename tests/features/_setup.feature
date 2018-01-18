Feature: Prepare Wordpress/Woocomerce for Tests
      Disable Set Checkout Keys, Complete an initial transaction

Scenario: I should be able to setup Wordpress/Woocomerce
      Given I set the viewport and timeout
      Given I go to the backend of Checkout's plugin
      Given I create a product
      Given I enable the 2 checkout plugins
      Given I open the pci settings
      Given I enable saved cards for pci
      Given I set the pci sandbox keys
      Given I save the backend settings
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I set the non-pci sandbox keys
      Given I enable saved cards for non-pci
      Given I set the integration type to frames
      Given I disable three d for non-pci
      Given I save the backend settings
      Given I complete the order flow until the payment stage
      Given I select the non-pci payment option
      Then I enable the option to save the card
      Then I complete the frames integration with a visa card
      Then I place the order
      Then I should see the order confirmation page
