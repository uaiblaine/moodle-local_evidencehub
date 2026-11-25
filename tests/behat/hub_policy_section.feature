@local @local_evidencehub @local_dimensions @javascript
Feature: The validity policy in the Competency hub's panel
  In order to see how long a competency's evidence stays valid where the competency is managed
  As a manager
  I need the Competency hub's panel to show the evidence hub's policy of the selected competency

  # Needs local_dimensions with the competency_panel_sections hook installed in the test site (CI checks it out
  # under plugin-dependencies); without it the hub is not there to open.
  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber |
      | Safety    | SAFETY   |
    And the following "core_competency > competencies" exist:
      | shortname | competencyframework | idnumber |
      | Heights   | SAFETY              | NR35     |
      | Fire      | SAFETY              | NR23     |
    And I log in as "admin"
    And I am on the "SAFETY" "local_evidencehub > Policies" page
    And I click on "Override" "button" in the "Heights" "table_row"
    And I set the following fields in the "Edit policy" "dialogue" to these values:
      | Kind of policy | Technical |
      | Grace days     | 15        |
    And I click on "Save changes" "button" in the "Edit policy" "dialogue"
    And I should see "This competency" in the "Heights" "table_row"

  Scenario: A manager sees a competency's own policy, and the default of a competency without one
    When I visit "/local/dimensions/central.php"
    And I click on "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Heights']]" "xpath_element"
    Then I should see "Validity policy" in the "aside.local-dimensions-hubv2-panel" "css_element"
    And I should see "Technical" in the "[data-key='local_evidencehub-policy']" "css_element"
    And I should see "Grace days" in the "[data-key='local_evidencehub-policy']" "css_element"
    And I should see "15" in the "[data-key='local_evidencehub-policy']" "css_element"
    And I should see "This competency" in the "[data-key='local_evidencehub-policy'] .local-dimensions-hubv2-status" "css_element"
    And "Edit policy" "link" should exist in the "[data-key='local_evidencehub-policy']" "css_element"
    When I click on "//li[@role='treeitem'][.//span[contains(concat(' ', @class, ' '), ' local-dimensions-hubv2-oname ')][.='Fire']]" "xpath_element"
    Then I should see "Fire" in the "aside.local-dimensions-hubv2-panel h3" "css_element"
    And I should see "Default" in the "[data-key='local_evidencehub-policy'] .local-dimensions-hubv2-status" "css_element"
    And I should not see "This competency" in the "aside.local-dimensions-hubv2-panel" "css_element"
