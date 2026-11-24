@local @local_evidencehub @javascript
Feature: Readiness dossiers
  In order to prove to a contractor that my workers are ready
  As a manager
  I need to export their readiness dossiers and let anyone check one by its code

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
    And the following "local_evidencehub > plan templates" exist:
      | shortname    | competencies |
      | Electricians | NR35         |
    And the following "local_evidencehub > template plans" exist:
      | user    | template     |
      | worker1 | Electricians |
      | worker2 | Electricians |
    And the following "local_evidencehub > statuses" exist:
      | user    | competency | status | effectiveexpiry |
      | worker1 | NR35       | 2      | 20990101        |
      | worker2 | NR35       | 5      | 20250101        |
    And the following config values are set as admin:
      | syncmaxworkers | 1 | local_evidencehub |

  Scenario: Two workers are prepared in the background and the dossier checks out by its code
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Matrix" page
    When I click on "[data-region='worker']" "css_element" in the "Ana Souza" "table_row"
    And I click on "[data-region='worker']" "css_element" in the "Bia Lima" "table_row"
    And I press "Export dossiers"
    Then I should see "The dossiers of 2 workers are being prepared"
    # The export returns to the matrix it was posted from.
    And I should see "Readiness matrix"
    And I should see "Ana Souza" in the ".local-evidencehub-matrix" "css_element"
    And I run all adhoc tasks
    And I reload the page
    And I open the notification popover
    And I should see "Your readiness dossiers are ready" in the "#nav-notification-popover-container" "css_element"
    And I log out
    When I am on the "worker1" "local_evidencehub > Dossier verification" page
    Then I should see "A*** S***"
    And I should see "Authentic dossier"
    And I should not see "Ana Souza"
