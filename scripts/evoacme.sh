#!/bin/bash

in_login="$1"

domain=$(bash /usr/share/scripts/evoadmin/web-add.sh list-vhost $in_login | cut -d ':' -f3)
alias=$(bash /usr/share/scripts/evoadmin/web-add.sh list-vhost $in_login | cut -d ':' -f4 | tr ',' ' ')
echo $domain $alias | /usr/local/sbin/make-csr "$in_login"
if [[ ${PIPESTATUS[1]} != 0 ]]; then
  echo "Erreur avec echo $domain $alias | /usr/local/sbin/make-csr $in_login"
  return 1
fi
return 0

