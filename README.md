# Evoadmin-web

Web interface and management scripts for web hosting

Project leader : ?

## Versions & Branches

The `master` branch is ready for production. 
It's compatible with the current Debian version (and few previous ones)

Code for older Debian releases (Wheezy, Jessie) is archived on separate branches.

The `unstable` branch contains not sufficiently tested changes that we don't consider ready for production yet.

### Versions 

* Debian Stretch, Buster, Bullseye → use branch `master` (current stable version)
* Debian Jessie → use branch `jessie` (old, archive branch)
* Debian Wheezy → use branch `wheezy` (old, archive branch)

## Installation

Installation should be done with Ansible. 
The role `packweb-apache` will handle all dependencies (Apache, PHP, MariaDB...) installation and configuration.
It will also use the role `webapps/evoadmin-web` to setup the web-interface and management sw

### Manually

TODO

## Contributing

Contributions are welcome, especially bug fixes. They will be merged in if they are consistent with our conventions and use cases. They might be rejected if they introduce complexity, cover features we don't need or don't fit "style".

Before starting anything of importance, we suggest opening an issue to discuss what you'd like to add or change.

All modifications should be documented in the CHANGELOG file, to help review releases. We encourage atomic commits and with the CHANGELOG in the same commit.

# Workflow

The ideal and most typical workflow is to create a branch, based on the `unstable` branch. The branch should have a descriptive name (a ticket/issue number is great). The branch can be treated as a pull-request or merge-request. It should be propery tested and reviewed before merging into `unstable`.

Changes that don't introduce significant changes — or that must go faster that the typical workflow — can be commited directly into `unstable`.

Hotfixes, can be prepared on a new branch, based on `master` or `unstable` (to be decided by the author). When ready, it can be merged back to `master` for immediate deployment and to `unstable` for proper backporting.

Other workflow are not forbidden, but should be discussed in advance.
