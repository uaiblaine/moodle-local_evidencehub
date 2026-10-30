@local @local_evidencehub @javascript
Feature: The readiness matrix
  In order to see who is ready for every competency of a contract at a glance
  As a manager
  I need to switch the hub to its matrix of workers by competencies and open a pair from a cell

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Mia       | Manager  |
      | worker1  | Ana       | Souza    |
      | worker2  | Bia       | Lima     |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Safety    | SAFETY   |
    And the following "core_competency > competencies" exist:
      | shortname | competencyframework | idnumber |
      | Heights   | SAFETY              | NR35     |
      | Fire      | SAFETY              | NR23     |
    And the following "local_evidencehub > plan templates" exist:
      | shortname    | competencies |
      | Electricians | NR35, NR23   |
    And the following "local_evidencehub > template plans" exist:
      | user    | template     |
      | worker1 | Electricians |
      | worker2 | Electricians |
    And the following "local_evidencehub > statuses" exist:
      | user    | competency | status | effectiveexpiry |
      | worker1 | NR35       | 5      | 20250101        |
      | worker1 | NR23       | 2      | 20990101        |
      | worker2 | NR35       | 2      | 20990101        |
      | worker2 | NR23       | 2      | 20990101        |

  Scenario: A manager switches to the matrix and opens a pair from its cell
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    When I click on "Matrix" "link" in the ".local-evidencehub-tabs" "css_element"
    Then I should see "Pending, no evidence" in the ".local-evidencehub-legend" "css_element"
    And I should see "Ana Souza" in the ".local-evidencehub-matrix" "css_element"
    And I should see "Bia Lima" in the ".local-evidencehub-matrix" "css_element"
    And ".local-evidencehub-cell.bg-dark" "css_element" should exist in the "Ana Souza" "table_row"
    And ".local-evidencehub-cell.bg-success" "css_element" should exist in the "Ana Souza" "table_row"
    When I click on "Expired" "button" in the "Ana Souza" "table_row"
    Then "Ana Souza: Heights" "dialogue" should be visible

  Scenario: The status chips keep the workers with a pair in the chosen statuses
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Matrix" page
    When I click on "Expired" "link" in the ".local-evidencehub-chips" "css_element"
    Then I should see "Ana Souza" in the ".local-evidencehub-matrix" "css_element"
    And I should not see "Bia Lima" in the ".local-evidencehub-matrix" "css_element"

  Scenario: Two hundred workers by twenty-five competencies render without layout shift
    Given the following "local_evidencehub > matrix seeds" exist:
      | template | workers | competencies |
      | Line 4   | 200     | 25           |
    And I log in as "admin"
    When I am on the "Line 4" "local_evidencehub > Matrix" page
    Then I should see "200 workers" in the ".local-evidencehub-total" "css_element"
    And I should see "M25" in the ".local-evidencehub-matrix" "css_element"
    And the layout shift of the page should be at most "0.01"
