#!/bin/sh
#
# Script to do a per account synchronization of files, database and user's
# crontab.
# It's part of Evocluster project.


set -e

LOGFILE=~/log/evocluster.log
PIDFILE=/tmp/evocluster-$USER.pid

sync() {
    set +e
    mysqldump >~/dump.sql
    crontab -l >~/crontab
    rsync -a --delete ~/www ~/dump.sql ~/crontab $1:
    set -e
}

error() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "ERROR: $1" |tee -a $LOGFILE >&2
}

info() {
    echo -n "$(date +"%b %d %T") " >>$LOGFILE
    echo "INFO: $1" |tee -a $LOGFILE
}


# Read the state file
if [ -e ~/state ]; then
    . ~/state
else
    error 'state file does not exist, I do not know who am I!'
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

# Check if there is not another instance of the script running
if [ -e $PIDFILE ]; then
    error "$0 already running (PID $(cat $PIDFILE))!"
    exit 2
fi
echo "$$" > $PIDFILE
trap "rm -f $PIDFILE" EXIT

if [ $LOCAL_STATE = 'master' ]; then

    # Try to connect to the remote server and check if it is a slave
    REMOTE_STATE=$(mktemp)
    if ssh $REMOTE_IP [ -e ~/state ]; then
    ssh $REMOTE_IP cat ~/state >$REMOTE_STATE
    elif [ $? -eq 255 ]; then
    error "failed to connect to slave ($REMOTE_IP)!"
    exit 2
    else
        error "state file does not exist on slave!"
        exit 2
    fi

    . $REMOTE_STATE
    rm -f $REMOTE_STATE
    if [ -n "$STATE" ]; then
        REMOTE_STATE=$STATE
    else
        error '$STATE not set in remote state file!'
        exit 2
    fi

    if [ $REMOTE_STATE = 'slave' ]; then
        info 'local server is master and remote server is slave.'
        info "starting replication at $(/bin/date +"%Y-%m-%d %H:%M")."
        sync $REMOTE_IP
        info "replication ended at $(/bin/date +"%Y-%m-%d %H:%M")."
    else
        info 'remote server is not slave. Doing nothing.'
    fi
else
    info 'local server is not master. Doing nothing.'
fi
