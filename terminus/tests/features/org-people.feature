Feature: Organizational users
  In order to coordinate users within organizations
  As an organizational user
  I need to be able to list organizational user memberships.

  Background: I am authenticated
    Given I am authenticated

  @vcr org-people-list.yml
  Scenario: List an organization's members
    When I run "[[executable]] org:people:list '[[organization_name]]'"
    Then I should get: "-------------------------------------- ----------------------- -----------"
    And I should get: "ID                                     Email                   Role"
    And I should get: "-------------------------------------- ----------------------- -----------"
    And I should get: "a7926bb1-9490-46eb-b580-2e80cdf9fd11   [[other_user]]   developer"
    And I should get: "11111111-1111-1111-1111-111111111111   [[username]]     admin"
    And I should get: "-------------------------------------- ----------------------- -----------"

  @vcr org-people-site-list-empty.yml
  Scenario: List an organization's members
    When I run "[[executable]] org:people:list '[[organization_name]]'"
    Then I should get the warning: "[[organization_name]] has no members."
    And I should get: "---- ------- ------"
    And I should get: "ID   Email   Role"
    And I should get: "---- ------- ------"

  @vcr org-people-add.yml
  Scenario: Add a new member to an organization
    When I run "[[executable]] org:people:add '[[organization_name]]' [[other_user]] team_member"
    Then I should get: "[[other_user]] has been added to the [[organization_name]] organization as a(n) team_member."

  @vcr org-people-add.yml
  Scenario: Failing to add an org member role because the given role is invalid
    When I run "[[executable]] org:people:add '[[organization_name]]' [[other_user]] invalid"
    Then I should get: "invalid is not a valid role selection. Please enter admin, developer, team_member, or unprivileged."

  @vcr org-people-remove.yml
  Scenario: Removing a member from an organization
    When I run "[[executable]] org:people:remove '[[organization_name]]' [[other_user]]"
    Then I should get: "Dev User has been removed from the [[organization_name]] organization."

  @vcr org-people-role.yml
  Scenario: Changing a org member's role
    When I run "[[executable]] org:people:role '[[organization_name]]' [[other_user]] developer"
    Then I should get: "Dev User's role has been changed to developer in the [[organization_name]] organization."

  @vcr org-people-role.yml
  Scenario: Failing to change a org member's role because the given role is invalid
    When I run "[[executable]] org:people:role '[[organization_name]]' [[other_user]] invalid"
    Then I should get: "invalid is not a valid role selection. Please enter admin, developer, team_member, or unprivileged."
