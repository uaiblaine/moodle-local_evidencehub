@local @local_evidencehub @javascript
Feature: The evidence hub
  In order to see whose evidence needs attention
  As a manager
  I need to open the hub from a category, pick a learning plan template and filter by status

  Background:
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Operations | 0        | OPS      |
    And the following "users" exist:
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
      | shortname    | category | competencies |
      | Electricians | OPS      | NR35         |
    And the following "local_evidencehub > template plans" exist:
      | user    | template     |
      | worker1 | Electricians |
      | worker2 | Electricians |
    And the following "local_evidencehub > statuses" exist:
      | user    | competency | status | effectiveexpiry |
      | worker1 | NR35       | 5      | 20250101        |
      | worker2 | NR35       | 2      | 20300101        |

  Scenario: A manager opens the hub from a category's More menu and widens it to every status
    Given I log in as "manager1"
    And I am on the "OPS" "category" page
    When I navigate to "Evidence hub" in current page administration
    Then I should see "Opened from Operations"
    And "//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Evidence hub']" "xpath_element" should exist
    And "(//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Evidence hub'])[2]" "xpath_element" should not exist
    When I set the field "Learning plan template" to "Electricians (Operations)"
    And I press "Apply"
    Then I should see "Expired" in the "[data-region='local-evidencehub-band']" "css_element"
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should not see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    Then I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"

  Scenario: The hub and the policies list name themselves once in the breadcrumb
    Given I log in as "admin"
    When I visit "/local/evidencehub/index.php"
    Then "//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Evidence hub']" "xpath_element" should exist
    And "(//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Evidence hub'])[2]" "xpath_element" should not exist
    When I visit "/local/evidencehub/policies.php"
    Then "//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Validity policies']" "xpath_element" should exist
    And "(//ol[contains(concat(' ', normalize-space(@class), ' '), ' breadcrumb ')]/li[normalize-space()='Validity policies'])[2]" "xpath_element" should not exist
