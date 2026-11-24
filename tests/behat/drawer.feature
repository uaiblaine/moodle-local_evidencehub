@local @local_evidencehub @javascript
Feature: The inspection drawer
  In order to check one worker's evidence of one competency
  As a manager
  I need to open the pair's drawer from the hub, read its timeline and verify the evidence

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Mia       | Manager  |
      | worker1  | Ana       | Souza    |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Safety    | SAFETY   |
    And the following "core_competency > competencies" exist:
      | shortname | competencyframework | idnumber |
      | Heights   | SAFETY              | NR35     |
    And the following "local_evidencehub > plan templates" exist:
      | shortname    | competencies |
      | Electricians | NR35         |
    And the following "local_evidencehub > template plans" exist:
      | user    | template     |
      | worker1 | Electricians |
    And the following "local_evidencehub > evidence records" exist:
      | user    | competency | name             | type          | issuedate | expirydate | issuer   | verifystatus |
      | worker1 | NR35       | Initial training | nrcertificate | 20200914  | 20220914   | Treinar  | 1            |
      | worker1 | NR35       | Recycling 2024   | nrcertificate | 20241120  | 20991116   | Training | 0            |

  Scenario: A manager reads the timeline and verifies the pending evidence
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    # The pair is compliant (the later evidence covers today, verified or not), so it is outside the list's
    # default scope of pairs needing attention: "All" lists it.
    And I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    When I click on "Inspect" "button" in the "Ana Souza" "table_row"
    Then I should see "Recycling 2024" in the "Ana Souza: Heights" "dialogue"
    And "2024-11-20 → 2099-11-16" "text" should appear before "2020-09-14 → 2022-09-14" "text"
    And I should see "Awaiting verification" in the "Recycling 2024" "list_item"
    When I click on "Verify or reject" "button" in the "Recycling 2024" "list_item"
    And I click on "Save changes" "button" in the "Verify or reject" "dialogue"
    # Saving reloads the hub, which opens on the pairs needing attention again: "All" lists the pair.
    And I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    And I click on "Inspect" "button" in the "Ana Souza" "table_row"
    Then I should see "Verified" in the "Recycling 2024" "list_item"
    And I should not see "Awaiting verification" in the "Recycling 2024" "list_item"

  Scenario: The drawer closes with Escape and gives the focus back to its button
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    # The pair is compliant (the later evidence covers today, verified or not), so it is outside the list's
    # default scope of pairs needing attention: "All" lists it.
    And I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    When I click on "Inspect" "button" in the "Ana Souza" "table_row"
    And I should see "Recycling 2024" in the "Ana Souza: Heights" "dialogue"
    And I press the escape key
    Then "Ana Souza: Heights" "dialogue" should not exist
    And the focused element is "Inspect" "button" in the "Ana Souza" "table_row"
