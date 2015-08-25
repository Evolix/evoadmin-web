#!/bin/sh

# Script to install MySQL and lsync replication on master and slave host.
# It's part of Evocluster project.


set -e

LOGFILE=~/log/evocluster.log

error() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "ERROR: $1" |tee -a $LOGFILE >&2
}

info() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "INFO: $1" |tee -a $LOGFILE
}

if [ $# -ne 1 ] && ([ $1 != 'master' ] || [ $1 != 'slave']); then
    error 'You must specify one argument: master or slave'
    exit 2
fi

if [ $1 = 'master' ]; then

    # **
    # Warning: script supposes the database to configure is empty and stay
    # empty during all the configuration process.
    # **

    # Change MySQL configuration
    sed -i 's/^bind-address\t.*$/#&/' /etc/mysql/my.cnf
    sed -i 's/^#\(server-id\t.*\)$/\1/' /etc/mysql/my.cnf
    sed -i 's/^#\(log_bin\t.*\)$/\1/' /etc/mysql/my.cnf
    /etc/init.d/mysql restart # a voir...

    # Create repl user if it doesn't exist
    pass=$(apg -c /dev/urandom -n1 -E \&\\\/\"\')
    if [ -z "$(mysql -e "SELECT user FROM mysql.user WHERE user='repl'")" ]
    then
        mysql -e "GRANT REPLICATION SLAVE ON *.* to repl@'%' IDENTIFIED BY
          '$pass'"
    fi

    # lsyncd daemon
    (crontab -l; echo "@reboot  lsyncd ~/www slave:www") |crontab -

    # TODO: Monitoring lsyncd a mettre en place

    MASTER_LOG_FILE=$(mysql -Ne "SHOW MASTER STATUS" |cut -f 1)
    MASTER_LOG_POS=$(mysql -Ne "SHOW MASTER STATUS" |cut -f 2)
    if [ -z $MASTER_LOG_FILE ] || [ -z $MASTER_LOG_POS ]; then
        error 'MASTER_LOG_FILE or MASTER_LOG_POS are not set, bin-log seem not
          working correctly.'
    fi

    # Return some MySQL information
    # (required to configure slave)
    echo "MASTER_PASSWORD=$pass"
    echo "MASTER_LOG_FILE=$MASTER_LOG_FILE"
    echo "MASTER_LOG_POS=$MASTER_LOG_POS"

else

    # Source stding
    # doesn't POSIX compatible :'(
    #. <(grep -E '^MASTER_(HOST|DATABASE|PASSWORD|LOG_FILE|LOG_POS)=') 
    tmpfile=$(mktemp)
    grep -E '^MASTER_(HOST|DATABASE|PASSWORD|LOG_FILE|LOG_POS)=' >$tmpfile
    . $tmpfile
    rm $tmpfile

    # Change MySQL configuration
    sed -i 's/^#(server-id\t.*)\d$/\12/' /etc/mysql/my.cnf
    if grep -q '^replicate-do-db' /etc/mysql/my.cnf; then
        sed -i "/^replicate-do-db\t.*/&, $MASTER_DATABASE"
    else
        sed -i "/^#binlog_ignore_db/a replicate-do-db  = $MASTER_DATABASE"
    fi
    /etc/init.d/mysql restart # a voir...

    # Start replication
    mysql -e "CHANGE MASTER TO
      MASTER_HOST='$MASTER_HOST',
      MASTER_USER='repl',
      MASTER_PASSWORD='$MASTER_PASSWORD',
      MASTER_LOG_FILE='$MASTER_LOG_FILE',
      MASTER_LOG_POS=$MASTER_LOG_FILE"
    mysql -e "START SLAVE"
    sleep 2
    mysql -Ne "SHOW SLAVE STATUS"

    # TODO: Add nagios check

    # info a retourner :
    # - show slave status pour etre sur que la replication a demarre

fi
