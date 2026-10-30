@local @local_evidencehub @javascript
Feature: Validity policies
  In order to say how long the evidence of each competency stays valid
  As an administrator
  I need to set a framework's validity policy and override it for one competency

  Background:
    Given the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Safety    | SAFETY   |
    And the following "core_competency > competencies" exist:
      | shortname | competencyframework | idnumber |
      | Heights   | SAFETY              | NR35     |
      | Fire      | SAFETY              | NR23     |
    And I log in as "admin"
    And I am on the "SAFETY" "local_evidencehub > Policies" page

  Scenario: An admin sets the framework's policy and overrides one competency
    When I click on "Edit policy" "button"
    And I set the following fields in the "Edit policy" "dialogue" to these values:
      | Kind of policy                   | Regulatory |
      | First alert (days before expiry) | 90         |
    And I click on "Save changes" "button" in the "Edit policy" "dialogue"
    Then I should see "Regulatory" in the "Fire" "table_row"
    And I should see "The framework" in the "Fire" "table_row"
    When I click on "Override" "button" in the "Heights" "table_row"
    And I set the following fields in the "Edit policy" "dialogue" to these values:
      | Kind of policy | Technical |
    And I click on "Save changes" "button" in the "Edit policy" "dialogue"
    Then I should see "This competency" in the "Heights" "table_row"
    And I should see "Technical" in the "Heights" "table_row"
    And I should see "The framework" in the "Fire" "table_row"
