@block @block_forumfeed

Feature: Ensure discussion's display period settings are respected

  Scenario: Ensure the student does not see discussions with "Display start" in
    the future or "Display end" in the past

    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name            | course | idnumber | type    |
      | forum    | Test forum name | C1     | forump1  | general |
    And the following "mod_forum > discussions" exist:
      | user  | forum   | name                         | message                              | timeend       | timestart    |
      | admin | forump1 | Discussion no restriction    | Discussion contents 1, first message |               |              |
      | admin | forump1 | Discussion not yet visible   | Discussion contents 2, first message |               | ##tomorrow## |
      | admin | forump1 | Discussion no longer visible | Discussion contents 3, first message | ##yesterday## |              |
    And the following config values are set as admin:
      | forum_enabletimedposts | 1 |
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | forumfeed | System       | 1         | my-index        | side-post     |
    And I log in as "student1"
    Then I should see "Discussion no restriction"
    But I should not see "Discussion not yet visible"
    And I should not see "Discussion no longer visible"
