#!/bin/sh

set -o errexit
set -o nounset
#set -x

cut -d : -f 6 /etc/proftpd/vpasswd | while read -r dir; do
    if [ -d "$dir" ]; then
        du -s "$dir" | cut -f 1 > "$dir"/.size
        chmod 644 "$dir"/.size
    fi
done

