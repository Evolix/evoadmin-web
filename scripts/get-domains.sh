#!/bin/sh
#
# Get main domain and aliases for a specified account from the Apache
# virtualhost.
# It's part of Evocluster project.

set -e

if [ $# -ne 1 ]; then
    echo >&2 "Usage: $0 <account>"
    exit 1
fi

vhost="/etc/apache2/sites-available/$1"

# Extract ServerName and ServerAlias, remove third level part of domains,
# remove duplicate domains, and print the list to stdout, one domain per line.
perl -ne 'print $3 if (/^\s*Server(Name|Alias)\s+(.*\.)?([^\.]+\.[^\.]+)$/)' $vhost |sort -u
