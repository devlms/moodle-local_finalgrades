@local @local_finalgrades @javascript
Feature: Submit final grades and export them for the student records system
  In order to send final grades to the records office without a spreadsheet
  As a teacher and a registrar
  I need to submit course totals, withdraw one with a reason, and export them

  Background:
    Given the following "courses" exist:
      | fullname                | shortname | idnumber         |
      | Modern European History | HIST210   | HIST210-2026-AUT |
    And the following "users" exist:
      | username | firstname | lastname | idnumber |
      | lee      | Hana      | Lee      |          |
      | omar     | Omar      | Haddad   | S240117  |
      | ines     | Ines      | Moreau   | S240122  |
      | maya     | Maya      | Rossi    | S240150  |
    And the following "course enrolments" exist:
      | user | course  | role           |
      | lee  | HIST210 | editingteacher |
      | omar | HIST210 | student        |
      | ines | HIST210 | student        |
      | maya | HIST210 | student        |
    And the following "grade items" exist:
      | itemname   | course  | grademax |
      | Essays     | HIST210 | 40       |
      | Final exam | HIST210 | 60       |
    And the following "grade grades" exist:
      | gradeitem  | user | grade |
      | Essays     | omar | 31    |
      | Final exam | omar | 46    |
      | Essays     | ines | 28    |
      | Final exam | ines | 41.5  |
      | Essays     | maya | 30    |

  @accessibility
  Scenario: A teacher submits final grades and withdraws one with a reason
    Given I am on the "HIST210" "Course" page logged in as "lee"
    When I navigate to "Final grades" in current page administration
    Then I should see "Final grade submission"
    And "Maya Rossi" row "Status" column of "local-finalgrades-table" table should contain "Items not graded: 1"
    And the page should meet accessibility standards
    And I click on "Omar Haddad" "checkbox"
    And I click on "Ines Moreau" "checkbox"
    And I press "Submit selected grades"
    And I should see "Submitted grades: 2."
    And "Omar Haddad" row "Status" column of "local-finalgrades-table" table should contain "Submitted"
    And I click on "Withdraw Ines Moreau" "link"
    And the page should meet accessibility standards
    And I set the field "Reason" to "The exam was re-marked after an appeal."
    And I press "Withdraw"
    And I should see "Grade withdrawn: Ines Moreau."
    And "Ines Moreau" row "Status" column of "local-finalgrades-table" table should contain "Withdrawn: The exam was re-marked after an appeal."
    And the page should meet accessibility standards

  @accessibility
  Scenario: The registrar exports submitted grades and downloads the file
    Given I am on the "HIST210" "Course" page logged in as "lee"
    And I navigate to "Final grades" in current page administration
    And I click on "Select all" "checkbox"
    And I press "Submit selected grades"
    And I log out
    When I log in as "admin"
    And I navigate to "Grades > Final grade exports" in site administration
    Then I should see "Grades waiting to be sent: 3"
    And the page should meet accessibility standards
    And I press "Export now"
    And I should see "Export made with 3 rows"
    And I should see "Grades waiting to be sent: 0"
    And the "Export now" "button" should be disabled
    And I should see "Admin User" in the "[data-region='exports'] tbody" "css_element"
