@local @local_evidencehub @javascript
Feature: The hub's list of pairs as a data report
  In order to work through whose evidence needs attention
  As a manager
  I need the hub's list to load, filter, sort, choose its columns and view, and act on the rows I select

  # Thin smoke tests on the data report mould (data-report.md, "Behat"); the logic is PHPUnit's. Every step
  # waits for what the React view draws, never for its skeleton: the view holds Behat's pending counter while
  # a region loads (js/esm/src/datareport/pending.ts).

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
      | worker2 | NR35       | 3      | 20990101        |
      | worker2 | NR23       | 2      | 20990101        |

  Scenario: The pairs needing attention arrive, with the band, and "All" lists every pair
    Given I log in as "manager1"
    When I am on the "Electricians" "local_evidencehub > Hub" page
    Then I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should see "2 pairs" in the "[data-region='local-evidencehub-total']" "css_element"
    And I should see "Pairs needing attention" in the "[data-region='local-evidencehub-band']" "css_element"
    And I should not see "Fire" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    Then I should see "4 pairs" in the "[data-region='local-evidencehub-total']" "css_element"
    And I should see "Fire" in the "[data-region='local-evidencehub-rows']" "css_element"

  Scenario: A status from the filter panel narrows the pairs and shows a chip that removes it
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I press "Filters"
    And I click on "Expired 1" "button" in the "[data-region='local-evidencehub-bar'] [role='dialog']" "css_element"
    And I press "Show 1"
    Then I should see "Status: Expired" in the "[data-region='local-evidencehub-chips']" "css_element"
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should not see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I press "Remove filter Status: Expired"
    Then I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"

  Scenario: A sort by the worker reverses with a second press
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I click on "Worker" "button" in the "[data-region='local-evidencehub-rows'] thead" "css_element"
    Then "Ana Souza" "text" should appear before "Bia Lima" "text"
    When I click on "Worker" "button" in the "[data-region='local-evidencehub-rows'] thead" "css_element"
    Then "Bia Lima" "text" should appear before "Ana Souza" "text"

  Scenario: The columns menu adds a column and removes it again
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should not see "Issue date" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I press "Columns"
    And I click on "Issue date" "button" in the "[data-region='local-evidencehub-bar'] [role='menu'][aria-label='Columns']" "css_element"
    Then I should see "Issue date" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I click on "Issue date" "button" in the "[data-region='local-evidencehub-bar'] [role='menu'][aria-label='Columns']" "css_element"
    Then I should not see "Issue date" in the "[data-region='local-evidencehub-rows']" "css_element"

  Scenario: View switches the rows to cards, and "Table view" shows core's table
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I press "View"
    # By its label: the "Automatic" choice's hint says "Cards" too, and a button is matched by a part of its text.
    And I click on "//div[@role='menu'][@aria-label='View']//button[.//span[normalize-space(.)='Cards']]" "xpath_element"
    Then I should see "Ana Souza" in the "[data-region='local-evidencehub-rows'] [role='list']" "css_element"
    And "[data-region='local-evidencehub-rows'] table" "css_element" should not exist
    When I click on "Table view" "link" in the "[data-region='local-evidencehub-reportview']" "css_element"
    Then I should see "Ana Souza" in the "[data-region='core_reportbuilder/report']" "css_element"

  Scenario: Asking for another set of rows empties the selection, so the dock goes
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    When I click on "Select Ana Souza: Heights" "checkbox"
    And I click on "Select Bia Lima: Heights" "checkbox"
    Then I should see "2 selected on this page" in the "[data-region='local-evidencehub-dock']" "css_element"
    When I click on "Worker" "button" in the "[data-region='local-evidencehub-rows'] thead" "css_element"
    Then "[data-region='local-evidencehub-dock']" "css_element" should not exist
    And I should see "Ana Souza" in the "[data-region='local-evidencehub-rows']" "css_element"

  Scenario: Selected rows show the dock with their count, the page box selects the page, and the dossiers export
    Given the following config values are set as admin:
      | syncmaxworkers | 1 | local_evidencehub |
    And I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    And "[data-region='local-evidencehub-dock']" "css_element" should not exist
    When I click on "Select Ana Souza: Heights" "checkbox"
    Then I should see "1 selected on this page" in the "[data-region='local-evidencehub-dock']" "css_element"
    And I should see "1 worker" in the "[data-region='local-evidencehub-dock']" "css_element"
    # The dock is sticky at the window's bottom while the rows scroll by: the page box is scrolled clear of it.
    When I scroll "Select every row of this page" "checkbox" to the middle of the window
    And I click on "Select every row of this page" "checkbox"
    Then I should see "2 selected on this page" in the "[data-region='local-evidencehub-dock']" "css_element"
    And I should see "2 workers" in the "[data-region='local-evidencehub-dock']" "css_element"
    When I click on "Export dossiers" "button" in the "[data-region='local-evidencehub-dock']" "css_element"
    Then I should see "The dossiers of 2 workers are being prepared"

  Scenario: Download offers the site's formats, each a link to the list's rows under the criteria in force
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    And I should see "Bia Lima" in the "[data-region='local-evidencehub-rows']" "css_element"
    And I click on "All" "button" in the "[data-region='local-evidencehub-bar']" "css_element"
    And I should see "4 pairs" in the "[data-region='local-evidencehub-total']" "css_element"
    When I press "Download"
    Then "Comma separated values (.csv)" "link" should exist in the "[data-region='local-evidencehub-bar'] [role='menu'][aria-label='Download']" "css_element"
    And the "href" attribute of "Comma separated values (.csv)" "link" should contain "download=csv"
    And the "href" attribute of "Comma separated values (.csv)" "link" should contain "scope=all"
    And the "href" attribute of "Comma separated values (.csv)" "link" should contain "sesskey="

  Scenario: Inspect opens the pair's drawer
    Given I log in as "manager1"
    And I am on the "Electricians" "local_evidencehub > Hub" page
    When I click on "Inspect" "button" in the "Ana Souza" "table_row"
    Then "Ana Souza: Heights" "dialogue" should be visible
