# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

This project does not follow semantic versioning.
The **major** part of the version is the year
The **minor** part changes is the month
The **patch** part changes is incremented if multiple releases happen the same month

## [Unreleased]

### Added

* web-add.sh: Add new command list-php-versions
* web-add.sh: Add new command enable-vhost
* web-add.sh: Add new command disable-vhost

### Changed

* Add some PHPDoc comments for ease of programming
* Better PHP version number handling (6786114c68 )
* Deprecate Stretch

### Fixed

* Fix deletion of SSH permissions
* Fix ssl config (in /etc/apache2/ssl) not deleted on account deletion
* FTP account listing broken when the size file doesn't exist
* Quota system

### Removed

### Security



## [24.04]

### Added

* Prevent op_del to fail and able to remove web account when part of it is already removed

### Changed

* Add sendmail_path and open_basedir in LXC PHP pool configs

### Fixed

* letsencrypt: Add required check when retrieving certificate. (Avoid TypeError.)
* web-add.sh: Fix ssh group membership (#94)

### Removed

### Security


## [23.02] 2023-02-20

### Added

* web : Display web-add.sh version

### Changed

* Readme.md : Translated to english. New contribution guidelines and misc information

### Fixed

* web-add.sh : Deleting mysql user with DROP USER to stay compatible with MariaDB 10.5+ - #78
* web-add.sh : Correcting deletion order to avoid dependency issues - #76

### Removed

### Security




