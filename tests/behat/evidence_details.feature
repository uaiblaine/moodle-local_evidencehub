@local @local_evidencehub @javascript
Feature: Evidence details
  In order to show that a worker's evidence is valid
  As a worker and as a manager
  I need to enter the validity details of an evidence and verify them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | worker   | Wanda     | Worker   |
      | manager  | Mara      | Manager  |
    And the following "role assigns" exist:
      | user    | role    | contextlevel | reference |
      | manager | manager | System       |           |
    And I log in as "worker"
    And I am on the "worker" "local_evidencehub > Evidence" page
    And I click on "Add evidence" "button"
    And I set the following fields in the "Add evidence" "dialogue" to these values:
      | Name             | First aid certificate                |
      | Type of evidence | Regulatory training certificate (NR) |
      | Issuer           | Training centre                      |
    And I click on "Save changes" "button" in the "Add evidence" "dialogue"
    And I should see "First aid certificate"
    And I should see "Awaiting verification"
    And I log out

  Scenario: A manager verifies the worker's evidence
    Given I log in as "manager"
    And I am on the "worker" "local_evidencehub > Evidence" page
    When I click on "Verify or reject" "button"
    And I click on "Save changes" "button" in the "Verify or reject" "dialogue"
    Then I should see "Verified"
    And I should not see "Awaiting verification"

  Scenario: A rejection without a note shows the field error
    Given I log in as "manager"
    And I am on the "worker" "local_evidencehub > Evidence" page
    When I click on "Verify or reject" "button"
    And I click on "Reject" "radio" in the "Verify or reject" "dialogue"
    And I click on "Save changes" "button" in the "Verify or reject" "dialogue"
    Then I should see "Say why the details are rejected." in the "Verify or reject" "dialogue"
