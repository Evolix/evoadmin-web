# Contributing

Each Debian stable version has it's own nominal branch (Wheezy,
Jessie, …) These branches should not be touched, unless you're
trying to backport a security fix, which should be committed  as
"Hotfix #IssueId Description of bug"

The project leader is in charge of merging future versions (wheezy-dev,
jessie-dev, …) into the stable branches et maintaining the changelog.

Versions are managed using git(1) tags. With stable releases being
backported to the various Debian branches.

Use  feature branches named "Implement #IssueID. Feature Description."
or "Fix #issueID. Bug Description." and then create a pull request
to merge them into the master branch.
