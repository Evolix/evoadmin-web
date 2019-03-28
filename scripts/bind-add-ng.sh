#!/bin/bash

BINDROOT="/etc/bind"
DBTEMPLATE="$BINDROOT/db.example.com"
SLAVE="ns4.evolix.net"
LOGFILE="/var/log/bind-add.log"
ADD_DOMAIN_SLAVE="/usr/share/scripts/add_domain_slave_ns4.sh"
ADD_RELAY_DOMAIN="/usr/share/scripts/add_relay_domains.sh"

# Redirect stderr to $LOGFILE since Evoadmin don't catch stderr correctly.
#exec 2>>$LOGFILE
# Duplicate stderr on $LOGFILE (bashism)
exec 2> >(tee -a $LOGFILE >&2)

# Create zone file for a new domain.
create_domain () {
    domain="$1"
    ip="$2"

    zonefile="$BINDROOT/db.$domain"

    # Create new zone file
    cp -p $DBTEMPLATE $zonefile
    sed -i "s/_IP_/$ip/" $zonefile

    # Add domain to bind conf
    sed "s/__DATE__/`date "+%d.%m.%Y"`/; s/example.com/$domain/" \
      $BINDROOT/named.conf.single >> $BINDROOT/named.conf.evolix
}

# Add a MX record to an existing domain.
add_mx () {
    domain="$1"
    mx="$2"
    mx_priority="$3"
    mx_subdomain="$4"

    zonefile="$BINDROOT/db.$domain"

    # Quai13 specific. See #8053.
    if [ "$mx_subdomain" != "@" ]; then
        log INFO "Not adding MX record for subdomain $mx_subdomain."
        return 0
    fi

    if ! grep -q -P "^$mx_subdomain[ \t]+IN[ \t]+MX[ \t]+[[:digit:]]+[ \t]+$mx$" $zonefile; then
        echo "$mx_subdomain           IN MX      $mx_priority $mx" >>$zonefile
    else
        log ERR "MX Record $mx already exists."
        return 1
    fi
}

# Add a subdomain (CNAME) to an existing domain.
add_subdomain () {
    domain="$1"
    subdomain="$2"

    zonefile="$BINDROOT/db.$domain"

    if ! grep -q -P "^$subdomain[ \t]+IN[ \t]+(CNAME|A)[ \t]+" $zonefile; then
        echo "$subdomain   IN CNAME   @" >>$zonefile
    else
        log ERR "CNAME or A record for $subdomain already exists."
        return 1
    fi
}

# Incremente serial number for a domain, check zone, and reload rndc daemon.
inc_and_reload () {
    domain="$1"

    zonefile="$BINDROOT/db.$domain"

    # Set the date for serial (only if greater than actual serial)
    serial=$(grep -P '^[ \t]*[0-9]{10}[ \t]*; serial' $zonefile | sed -r "s/^[ \t]*([0-9]{10})[ \t]*; serial/\1/")
    if [ `date "+%Y%m%d%H"` -gt $serial ]; then serial=$(date "+%Y%m%d%H"); else serial=$(( $serial + 1 )); fi
    sed -ri "s/^([ \t]*)[0-9]{10}([ \t]*; serial)/\1$serial\2/" \
      $zonefile
    if stderr=$(named-checkzone $domain $zonefile 2>&1); then
        rndc reload
    else
        log ERR "named-checkzone returns non zero exit code: $stderr"
        return 1
    fi
}

# Send mail to staff to create domain on the slave server.
send_mail () {
    #addr_master=$(ifconfig eth0 |perl -ne 'print "$1" if /addr:([\d\.]+)/')
    echo "IP du serveur maitre : 46.105.42.13" |
         mail -s "[TAF] Ajouter le domaine $domain sur $SLAVE" tech@evolix.fr
}

usage () {
    cat >&2 <<EOT
Usage: $0 -a <A record> [-m <MX record>,<priority>] [-s subdomain] domain
E.g.: $0 -a 192.0.2.12 -m mail,10 -s foo example.net

Notes:
  - -m and -s options can be specified multiple times to add multiple MX
  records and/or subdomains
  - you can also create a subdomain "foo" with this syntax (even if example.net
  is not yet created):
    $0 foo.example.net
EOT
}

log () {
    level="$1"
    message="$2"

    if [ "$level" = "ERR" ]; then
        echo -n "$(date +"%b %d %T") " >>$LOGFILE
        echo "ERROR: $message" |tee -a $LOGFILE >&2
    elif [ "$level" = "INFO" ]; then
        echo -n "$(date +"%b %d %T") " >>$LOGFILE
        echo "INFO: $message" |tee -a $LOGFILE
    fi
}


log INFO "$0 $*"

# Options parsing.

while getopts 'a:s:m:' opt; do
    case $opt in
      a)
        ip=$OPTARG
        ;;
      s)
        subdomains="$subdomains $OPTARG"
        ;;
      m)
        mxs="$mxs $OPTARG"
        ;;
      \?)
        log ERR "Invalid option -$OPTARG."
        usage
        exit 1
        ;;
    esac
done

shift $((OPTIND-1))

if [ $# -eq 1 ]; then
    fqdn=$(echo $1 |tr '[:upper:]' '[:lower:]')
else
    usage
    exit 1
fi

# If the domain contains a subdomain, extracts it.
if [ $(echo "$fqdn" |grep -oF '.' |wc -l) -gt 1 ]; then
    domain=$(echo $fqdn |grep -o '[^\.]\+\.[^\.]\+$')
    if [ ${fqdn%.$domain} != "www" ]; then          # www is already present in the zone template, so skip it.
        subdomains="$subdomains ${fqdn%.$domain}"
    fi
else
    domain="$fqdn"
fi

if [ ! -f $BINDROOT/db.$domain ]; then
    log INFO "Creating domain $domain."
    if [ -n "$ip" ]; then
        create_domain $domain $ip
        #send_mail $domain
        $ADD_DOMAIN_SLAVE $domain
        $ADD_RELAY_DOMAIN $domain
    else
        log ERR "Domain $domain does not exist and -a option is not set."
        log "Could not create domain."
        exit 1
    fi
    log INFO "domain $domain created successfully."
fi

if [ -n "$mxs" ]; then
    for mx in $mxs; do
        log INFO "Adding MX record $mx to domain $domain."
        mx_domain=$(echo $mx |cut -d ',' -f '1')
        mx_priority=$(echo $mx |cut -d ',' -f '2')
        mx_subdomain="${fqdn%.$domain}"
        if [ "$mx_subdomain" = "$domain" ] || [ "$mx_subdomain" = "www" ]; then
            mx_subdomain="@"
        fi
        if ! add_mx $domain $mx_domain $mx_priority "$mx_subdomain"; then
            log ERR "Error: adding MX record failed."
            exit 1
        fi
        log INFO "MX record $mx added successfully to domain $domain."
    done
fi

if [ -n "$subdomains" ]; then
    for subdomain in $subdomains; do
        log INFO "Adding CNAME record $subdomain to domain $domain."
        if ! add_subdomain $domain $subdomain; then
            log ERR "Error: adding CNAME record failed."
            exit 1
        fi
        log INFO "CNAME record $subdomain added successfully to domain $domain."
    done
fi

log INFO "Reloading rndc."
if ! inc_and_reload $domain; then
    log ERR "Error: zone not loaded due to errors."
    exit 1
fi
log INFO "rndc reloaded successfully."
