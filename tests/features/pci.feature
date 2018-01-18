Feature: Pci Test Suite


Scenario: I should be able to complete a normal transaction with the pci integration
      Given I go to the backend of Checkout's plugin
      Given I open the pci settings
      Given I disable three d for pci
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the pci payment option
      Then I select the new card option for pci
      Then I complete the pci integration with a visa card
      Then I place the order
      Then I should see the order confirmation page


Scenario: I should be able to complete a three d transaction with the pci integration
      Given I go to the backend of Checkout's plugin
      Given I open the pci settings
      Given I enable three d for pci
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the pci payment option
      Then I select the new card option for pci
      Then I complete the pci integration with a visa card
      Then I place the order
      Then I complete the three d password
      Then I should see the order confirmation page



Scenario: I should be able set the new order status
      Given I go to the backend of Checkout's plugin
      Given I open the pci settings
      Given I disable three d for pci
      Given I set the pci new order status to hold
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the pci payment option
      Then I select the new card option for pci
      Then I complete the pci integration with a visa card
      Then I place the order
      Then I should see the order confirmation page
      Then I should see the new order status as hold


Scenario: I should be able to pay with a saved card 
      Given I go to the backend of Checkout's plugin
      Given I open the pci settings
      Given I disable three d for pci
      Then I enable saved cards for pci
      Given I save the backend settings
      Then I complete the order flow until the payment stage
      Then I select the pci payment option
      Then I select the saved card option for pci
      Then I place the order
      Then I should see the order confirmation page
