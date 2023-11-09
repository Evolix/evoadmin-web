# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

This project does not follow semantic versioning.
The **major** part of the version is the year
The **minor** part changes is the month
The **patch** part changes is incremented if multiple releases happen the same month

## [Unreleased]

### Added

* Prevent op_del to fail and able to remove web account when part of it is already removed

### Changed

* Add sendmail_path and open_basedir in LXC PHP pool configs

### Fixed

* Fix sendmail_path hostname (missing domain / FQDN)
* Fix missing ITK admin link for multi PHP

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




