#!/bin/bash

vhost=$1
dryrun=${2:-no}

echo "$0 $@ invocated at $(date -R)" >> /var/log/evoacme-wrapper.log

if [[ -f /etc/letsencrypt/${vhost}/live/fullchain.pem ]]; then
    echo "Le certificat est déjà en place ! Ouvrir un ticket si il faut ajouter un domaine au certificat."
    openssl x509 -text -in /etc/letsencrypt/${vhost}/live/fullchain.pem | grep -e etc -e CN= -e DNS: -e After;
    exit 1
fi

if [[ ! -f /etc/ssl/requests/${vhost}.csr ]]; then
    source /usr/share/scripts/evoadmin/evoacme.sh $1
fi

if [[ "$dryrun" == "dry-run" ]]; then
    export VERBOSE=1
    export DRY_RUN=1
    echo "Lancement en dry-run"
    /usr/local/sbin/evoacme $vhost
else
    export VERBOSE=1
    /usr/local/sbin/evoacme $vhost
fi

grep -q "*:80>" /etc/apache2/sites-enabled/${vhost}.conf
if [ $? -eq 0 ] ; then
    sed -i 's@<VirtualHost \*:80>@<VirtualHost \*:80 \*:443>@' /etc/apache2/sites-enabled/${vhost}.conf
    sed -i "s@</VirtualHost>@Include /etc/apache2/ssl/$vhost.conf\n</VirtualHost>@" /etc/apache2/sites-enabled/${vhost}.conf
fi
