@mod @mod_progcontest
Feature: Attempt a progcontest
  As a student
  In order to demonstrate what I know
  I need to be able to attempt progcontestzes

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | progcontest       | Programmingcontest 1 | Programmingcontest 1 description | C1     | progcontest1    |

  @javascript
  Scenario: Attempt a progcontest with a single unnamed section, review and re-attempt
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And progcontest "Programmingcontest 1" contains the following questions:
      | question | page | maxmark |
      | TF1      | 1    |         |
      | TF2      | 1    | 3.0     |
    And user "student" has attempted "Programmingcontest 1" with responses:
      | slot | response |
      |   1  | True     |
      |   2  | False    |
    When I am on the "Programmingcontest 1" "mod_progcontest > View" page logged in as "student"
    And I follow "Review"
    Then I should see "Started on"
    And I should see "State"
    And I should see "Completed on"
    And I should see "Time taken"
    And I should see "Marks"
    And I should see "Grade"
    And I should see "25.00 out of 100.00"
    And I follow "Finish review"
    And I press "Re-attempt progcontest"

  @javascript
  Scenario: Attempt a progcontest with multiple sections
    Given the following "activities" exist:
      | activity   | name   | course | idnumber | grade |
      | progcontest       | Programmingcontest 2 | C1     | progcontest2    | 6     |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
      | Test questions   | truefalse   | TF3   | Third question  |
      | Test questions   | truefalse   | TF4   | Fourth question |
      | Test questions   | truefalse   | TF5   | Fifth question  |
      | Test questions   | truefalse   | TF6   | Sixth question  |
    And progcontest "Programmingcontest 2" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 1    |
      | TF3      | 2    |
      | TF4      | 3    |
      | TF5      | 4    |
      | TF6      | 4    |
    And progcontest "Programmingcontest 2" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 0       |
      | Section 2 | 3         | 0       |
      |           | 4         | 1       |
      | Section 3 | 5         | 1       |

    When I am on the "Programmingcontest 2" "mod_progcontest > View" page logged in as "student"
    And I press "Attempt progcontest now"

    Then I should see "Section 1" in the "Programmingcontest navigation" "block"
    And I should see question "1" in section "Section 1" in the progcontest navigation
    And I should see question "2" in section "Section 1" in the progcontest navigation
    And I should see question "3" in section "Section 2" in the progcontest navigation
    And I should see question "4" in section "Untitled section" in the progcontest navigation
    And I should see question "5" in section "Section 3" in the progcontest navigation
    And I should see question "6" in section "Section 3" in the progcontest navigation
    And I click on "True" "radio" in the "First question" "question"

    And I follow "Finish attempt ..."
    And I should see question "1" in section "Section 1" in the progcontest navigation
    And I should see question "2" in section "Section 1" in the progcontest navigation
    And I should see question "3" in section "Section 2" in the progcontest navigation
    And I should see question "4" in section "Untitled section" in the progcontest navigation
    And I should see question "5" in section "Section 3" in the progcontest navigation
    And I should see question "6" in section "Section 3" in the progcontest navigation
    And I should see "Section 1" in the "progcontestsummaryofattempt" "table"
    And I should see "Section 2" in the "progcontestsummaryofattempt" "table"
    And I should see "Untitled section" in the "progcontestsummaryofattempt" "table"
    And I should see "Section 3" in the "progcontestsummaryofattempt" "table"

    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I should see "1.00 out of 6.00 (16.67%)" in the "Grade" "table_row"
    And I should see question "1" in section "Section 1" in the progcontest navigation
    And I should see question "2" in section "Section 1" in the progcontest navigation
    And I should see question "3" in section "Section 2" in the progcontest navigation
    And I should see question "4" in section "Untitled section" in the progcontest navigation
    And I should see question "5" in section "Section 3" in the progcontest navigation
    And I should see question "6" in section "Section 3" in the progcontest navigation

    And I follow "Show one page at a time"
    And I should see "First question"
    And I should not see "Third question"
    And I should see "Next page"

    And I follow "Show all questions on one page"
    And I should see "Fourth question"
    And I should see "Sixth question"
    And I should not see "Next page"

  @javascript
  Scenario: Next and previous navigation
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext                |
      | Test questions   | truefalse   | TF1   | Text of the first question  |
      | Test questions   | truefalse   | TF2   | Text of the second question |
    And progcontest "Programmingcontest 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    When I am on the "Programmingcontest 1" "mod_progcontest > View" page logged in as "student"
    And I press "Attempt progcontest now"
    Then I should see "Text of the first question"
    And I should not see "Text of the second question"
    And I press "Next page"
    And I should see "Text of the second question"
    And I should not see "Text of the first question"
    And I click on "Finish attempt ..." "button" in the "region-main" "region"
    And I should see "Summary of attempt"
    And I press "Return to attempt"
    And I should see "Text of the second question"
    And I should not see "Text of the first question"
    And I press "Previous page"
    And I should see "Text of the first question"
    And I should not see "Text of the second question"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I should see "Text of the first question"
    And I should see "Text of the second question"
    And I follow "Show one page at a time"
    And I should see "Text of the first question"
    And I should not see "Text of the second question"
    And I follow "Next page"
    And I should see "Text of the second question"
    And I should not see "Text of the first question"
    And I follow "Previous page"
    And I should see "Text of the first question"
    And I should not see "Text of the second question"

  @javascript
  Scenario: Take a progcontest with number of attempts set
    Given the following "activities" exist:
      | activity | name   | course | grade | navmethod  | attempts |
      | progcontest     | Programmingcontest 5 | C1     | 100   | free       | 2        |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF7   | First question  |
    And progcontest "Programmingcontest 5" contains the following questions:
      | question | page |
      | TF7      | 1    |
    And user "student" has attempted "Programmingcontest 5" with responses:
      | slot | response |
      |   1  | True     |
    When I am on the "Programmingcontest 5" "mod_progcontest > View" page logged in as "student"
    Then I should see "Attempts allowed: 2"
    And I should not see "No more attempts are allowed"
    And I press "Re-attempt progcontest"
    And I should see "First question"
    And I click on "Finish attempt ..." "button" in the "region-main" "region"
    And I press "Submit all and finish"
    And I should see "Once you submit, you will no longer be able to change your answers for this attempt." in the "Confirmation" "dialogue"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I follow "Finish review"
    And I should not see "Re-attempt progcontest"
    And I should see "No more attempts are allowed"
