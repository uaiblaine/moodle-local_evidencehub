@local @local_evidencehub @local_dimensions
Feature: The validity pills in the plan overview
  In order to see how valid a worker's evidence is where the plan is read
  As a worker or a manager
  I need the Competency hub's plan overview to show the evidence hub's validity beside each competency

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "users" exist:
      | username | firstname | lastname |
      | worker1  | Ana       | Souza    |
      | manager1 | Mia       | Manager  |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Safety    | SAFETY   |
    And the following "core_competency > competencies" exist:
      | shortname | competencyframework | idnumber |
      | Heights   | SAFETY              | NR35     |
      | Health    | SAFETY              | ASO      |
    And the following "core_competency > plans" exist:
      | name        | user    | status | competencies |
      | Safety plan | worker1 | active | NR35, ASO    |
    And the following "local_evidencehub > statuses" exist:
      | user    | competency | status | effectiveexpiry | issensitive |
      | worker1 | NR35       | 2      | 20990101        | 0           |
      | worker1 | ASO        | 2      | 20990101        | 1           |

  Scenario: The worker sees the validity of every competency, sensitive or not
    When I am on the "Safety plan" "local_dimensions > View plan" page logged in as "worker1"
    Then I should see "Validity: Compliant, 2099-01-01" in the "Heights" "button"
    And I should see "Validity: Compliant, 2099-01-01" in the "Health" "button"

  Scenario: A manager without viewsensitive sees a sensitive pair as restricted
    When I am on the "Safety plan" "local_dimensions > View plan" page logged in as "manager1"
    Then I should see "Validity: Compliant, 2099-01-01" in the "Heights" "button"
    And I should see "Validity: Restricted" in the "Health" "button"
    And I should not see "Validity: Compliant, 2099-01-01" in the "Health" "button"
