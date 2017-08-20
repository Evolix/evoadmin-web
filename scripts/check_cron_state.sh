#!/bin/sh
# Script called 


set -e

LOGFILE=~/log/evocluster.log

MAIL="jdoe@example.com"

mail_error() {
    echo "From: John Doe <jdoe@example.com>
To: $MAIL
Subject: [evocluster] check_cron_state.sh

$1 

"|/usr/lib/sendmail -oi -t -f "$MAIL"
}

error() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "ERROR: $1" |tee -a $LOGFILE >&2
    mail_error "$1"
}

info() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "INFO: $1" |tee -a $LOGFILE
}


# Read the state file
if [ -e ~/state ]; then
    . ~/state
else
    error '~/state file does not exist, I dont know who am I!'
    exit 2
fi


# Check if all required variables are set
if [ -n "$STATE" ] && [ -n "$IP" ]; then
    LOCAL_STATE=$STATE
    REMOTE_IP=$IP
else
    error '$STATE or $IP not set in state file!'
    exit 2
fi

[ -n "$FORCE_CRON" ] && info 'FORCE_CRON=yes setted. Master is mandated to execute cron.'


if [ "$STATE" = "slave" ]; then
    info 'Account is in state slave, execution of cron is disabled'
    exit 1
else
    # env var $FORCE_CRON is set to yes, execute the cron
    [ "$FORCE_CRON" = "yes" ] && exit 0;
    
    ###  rajouter une règle sudo pour pouvoir exécuter ping ?
    # test if the remote server is alive
    #if ! ping -c1 -w1 $REMOTE_IP; then 
    #   error "Remote server $IP doesn't seem to be up, I don't know what to do...Add 'FORCE_CRON=yes' in ~/state file to execute user cron"
    #   exit 1
    #fi
    ###

    # try to connect to the remote server and check if it isn't a master
    REMOTE_STATE=$(mktemp)
    if ssh $REMOTE_IP [ -e ~/state ]; then
        ssh $REMOTE_IP cat ~/state >$REMOTE_STATE
        elif [ $? -eq 255 ]; then
        error "Failed to connect to slave ($REMOTE_IP)! I don't know what to do.."
        exit 1
        else
            error "State file does not exist on slave !"
            exit 1
        fi

    . $REMOTE_STATE
    rm -rf $REMOTE_STATE
    if [ -n "$STATE" ]; then
        REMOTE_STATE=$STATE
    else
        error '$STATE not set in remote state file!'
        exit 1
    fi

    if [ "$REMOTE_STATE" = "master" ]; then
        error "Remote server $IP is master too. (incoherent state)..."
        exit 1
    fi
fi

# Remote server is slave
# State is coherent, execute the cron
info 'Execute user cron'

exit 0
