Feature: Non-Pci Test Suite

Scenario: I should be able to complete a normal transaction with the js integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I disable three d for non-pci
      Given I set the integration type to js
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I place the order
      Then I complete the js integration with a visa card
      Then I should see the order confirmation page

 
Scenario: I should be able to complete a thee d transaction with the js integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I enable three d for non-pci
      Given I set the integration type to js
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I place the order
      Then I complete the js integration with a visa card
      Then I complete the three d password
      Then I should see the order confirmation page

 
Scenario: I should be able to complete a normal transaction with the frames integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I disable three d for non-pci
      Given I set the integration type to frames
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I complete the frames integration with a visa card
      Then I place the order
      Then I should see the order confirmation page


Scenario: I should be able to complete a three d transaction with the frames integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I enable three d for non-pci
      Given I set the integration type to frames
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I complete the frames integration with a visa card
      Then I place the order
      Then I complete the three d password
      Then I should see the order confirmation page

 
Scenario: I should be able to complete a normal transaction with the hosted integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I disable three d for non-pci
      Given I set the integration type to hosted
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I place the order
      Then I complete the hosted integration with a visa card
      Then I should see the order confirmation page

 
Scenario: I should be able to complete a thee d transaction with the hosted integration
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I enable three d for non-pci
      Given I set the integration type to hosted
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I place the order
      Then I complete the hosted integration with a visa card
      Then I complete the three d password
      Then I should see the order confirmation page


Scenario: I should be able to pay with a saved card 
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Given I disable three d for non-pci
      Then I enable saved cards for non-pci
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the saved card option
      Then I place the order
      Then I should see the order confirmation page


Scenario: I should be able to pay with a saved card 
      Given I go to the backend of Checkout's plugin
      Given I open the non-pci settings
      Then I customise the js and hosted solution
      Given I save the backend settings
      Given I set the integration type to js
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the non-pci payment option
      Then I select the new card option
      Then I check the customisation option
