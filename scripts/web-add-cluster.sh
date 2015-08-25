#!/bin/bash

#
# Gestion des comptes web et des hôtes virtuels Apache sur un EvoCluster
# 
# Copyright (c) 2009 Evolix - Tous droits reserves
# $Id: web-add.sh 221 2012-02-22 15:52:18Z reg $
#

# TODO
# - Gestion des quota
# - Possibilité de créer un compte FTP-only
# - Pouvoir spécifier le CONTACT_MAIL dans un fichier de conf
# - Traduire usage() en francais, ou l'inverse ??

set -e

HOME="/root"
CONTACT_MAIL=""
WWWBOUNCE_MAIL=""
LOCAL_SCRIPT="/usr/share/scripts/web-add.local.sh"
PRE_LOCAL_SCRIPT="/usr/share/scripts/web-add.pre-local.sh"
TPL_VHOST="/usr/share/scripts/vhost"
TPL_AWSTATS="/usr/share/scripts/awstats.XXX.conf"
TPL_MAIL="/usr/share/scripts/web-mail.tpl"
VHOST_PATH="/etc/apache2/sites-enabled/"
MAX_LOGIN_CHAR=16
HOME_DIR="/home"
MYSQL_CREATE_DB_OPTS=""
MIN_UID=5000
NOBODY_UID=65534
SSH="/usr/bin/ssh"
WEBADD="/usr/share/scripts/web-add.sh"
SSH_USER="root"
LAST_UID="cut -d: -f3 /etc/passwd | grep -v $NOBODY_UID | sort -n | tail -1"
VMAIL_USER="vmail"

# Utiliser ce fichier pour redefinir la valeur des variables ci-dessus
config_file="/etc/evolix/web-add.conf"
[ -r $config_file ] && . $config_file

usage() {
	cat <<EOT >&2

Usage: $0 COMMAND [ARG]

add [[OPTIONS] LOGIN WWWDOMAIN IP_MASTER IP_SLAVE REPLICATION_MODE]

    Create web account LOGIN on Master and Slave servers, state file in 
    /home/LOGIN and push ssh public key of Master in Slave .ssh/authorized.

   -p PASSWD
      FTP and SFTP password (default : random)

   -m DBNAME
      Name of MySQL database (default : same as account)

   -P DBPASSWD
      MySQL password (default : random)

   -l MAIL
      Send summary email to MAIL

  If REPLICATION_MODE is deferred:

   -f interval
      interval between each replication

   -c min|hour
      Unit for -f argument
      Eg.: -f 4 -c hour meens do replication each 4 hours.

   Example : web-add-cluster.sh add -m testdb -f 4 -c hour testlogin testdomain.com 192.168.0.1 192.168.0.2 deferred

del LOGIN IP_MASTER IP_SLAVE [DBNAME]

   Delete account and all files related (Apache, Awstats, etc) in Master and
   Slave server.
   Archive home directory.
   Remove MySQL database if DBNAME is specified.

add-alias WWWDOMAIN ALIAS IP_MASTER IP_SLAVE 

    Add a ServerAlias to an Apache vhost on Master and Slave

del-alias WWWDOMAIN ALIAS IP_MASTER IP_SLAVE

    Del a ServerAlias from an Apache vhost on Master and Slave

NOTE: to create an account or add/del an alias on only one server (without
replication to a slave), we must pass "null" keyword instead of IP_SLAVE.

EOT
}

#
# Affiche un message d'erreur de validation
#
in_error() {
	msg=$1
	cat >&2 <<EOT
***
Erreur : $msg
***
EOT
}

gen_random_passwd() {
	apg -c /dev/urandom -n1 -E oOlL10\&\\\/\"\'
}

validate_login() {
	login=$1
	
	length=${#login}
	
	if [ $length -lt 3 ]; then
		in_error "Le login doit contenir plus de 2 caracteres"
		return 1
	fi
	
	if [ $length -gt $MAX_LOGIN_CHAR ]; then
		in_error "Le login ne doit pas contenir plus de $MAX_LOGIN_CHAR caracteres"
		return 1
	fi
}

validate_passwd() {
	passwd=$1
	length=${#passwd}

	if [ $length -lt 6 ] && [ $length -gt 0 ]; then
		in_error "Le mot de passe doit avoir au moins 6 caracteres"
		return 1
	fi
}

validate_dbname() {
	dbname=$1
# aandre 18/06
#	if mysql -ss -e "show databases" | grep "^$dbname$" >/dev/null; then
#		in_error "Base de données déjà existante"
#		return 1
#	fi
}

validate_wwwdomain() {
	wwwdomain=$1
	if [ -z "$wwwdomain" ]; then
		in_error "Le nom de domaine est obligatoire"
		return 1
	fi
	return 0
}

validate_mail() {
	return 0
}

validate_replmode() {
    if [ $in_replmode != "realtime" ] && [ $in_replmode != "deferred" ]; then
        in_error "Le mode de replication doit etre realtime ou deferred"
        return 1
    fi
    return 0
}

validate_replinterval() {
    if [ -z "$in_replunit" ]; then
        in_error "L'unite de l'intervalle de replication doit etre specifiee"
        return 1
    elif [ $in_replunit != "min" ] && [ $in_replunit != "hour" ]; then
        in_error "L'unite de l'intervalle de replication doit etre min (minute), hour (heure)"
        return 1
    fi
    if [ -z "$in_replinterval" ]; then
        in_error "L'intervalle de replication doit etre specifiee"
        return 1
    elif [ $in_replunit = "min" ]; then
        if [ $in_replinterval -lt 1 ] || [ $in_replinterval -gt 60 ]; then
            in_error "Valeur incorecte pour l'intervalle en minute"
            return 1
        fi
    elif [ $in_replunit = "hour" ]; then
        if [ $in_replinterval -lt 1 ] || [ $in_replinterval -gt 24 ]; then
            in_error "Valeur incorecte pour l'intervalle en heure"
            return 1
        fi
    fi
    return 0
}

step_ok() {
	msg=$1
	echo "[OK] $msg"
}

create_www_accounts() {

	CMD_MASTER="$SSH -T $SSH_USER@$in_master"
	CMD_SLAVE="$SSH -T $SSH_USER@$in_slave"



	# On verifie que le compte n'existe pas sur master et slave

	if [ -n "$($CMD_MASTER cut -d: -f1 /etc/passwd| grep ^$in_login$)" ]; then
		in_error "Le compte $in_login existe sur $in_master";
		exit 1;
	fi

    if [ $in_slave != "null" ]; then
        if [ -n "$($CMD_SLAVE cut -d: -f1 /etc/passwd| grep ^$in_login$)" ]; then
            in_error "Le compte $in_login existe sur $in_slave";
            exit 1;
        fi
    fi


	# Trouver un UID valide et commun pour le compte cree sur Master et Slave

	last_uid_master=$($CMD_MASTER $LAST_UID)
	if [ -z "$last_uid_master" ]; then
		echo "error while fetching uid in master";
		return 1
	fi

    if [ $in_slave != "null" ]; then
        last_uid_slave=$($CMD_SLAVE $LAST_UID)
        if [ -z "$last_uid_slave" ]; then
            echo "error while fetching uid in slave";
            return 1
        fi

        if [ $last_uid_master -ge $last_uid_slave ]; then
            max_uid=$(($last_uid_master + 1))
        else
            max_uid=$(($last_uid_slave + 1))
        fi

        if [ $max_uid -lt $MIN_UID ]; then
            uid=$MIN_UID
        else
            uid=$max_uid
        fi
    else
        uid=$(($last_uid_master + 1))
    fi

	echo "UID libre: $uid"

	# options mysql
	opts_mysql='';
	[ -n "$in_dbname" ] && opts_mysql="-m $in_dbname -P '$in_dbpasswd'"

	# Creation web account on Master

	echo "MASTER: $CMD_MASTER $WEBADD add -p '$in_passwd' $opts_mysql -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain"

	$CMD_MASTER $WEBADD add -p \'$in_passwd\' $opts_mysql -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain || (in_error "creation du compte master sur $in_master: $?" && exit 1)

	# Creation web account on Slave

    if [ $in_slave != "null" ]; then
 
        if [ "$in_replmode" != "realtime" ]; then

            echo "SLAVE: $CMD_SLAVE $WEBADD add -p '$in_passwd' $opts_mysql -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain"
            $CMD_SLAVE $WEBADD add -p \'$in_passwd\' $opts_mysql -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain || (in_error "creation du compte slave sur $in_slave: $?" && exit 1)

       else

           echo "SLAVE: $CMD_SLAVE $WEBADD add -p '$in_passwd' -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain"
           $CMD_SLAVE $WEBADD add -p \'$in_passwd\' -l $in_mail -y -u $uid -g $uid -U $(($uid + 1)) $in_login $in_wwwdomain || (in_error "creation du compte slave sur $in_slave: $?" && exit 1)

        fi
    fi

    # Les operation suivantes sont faites uniquement si une replication doit
    # etre mise en place.

    if [ $in_slave != "null" ]; then

        # Creation des fichiers state

        $CMD_MASTER "echo -e \"STATE=master\nIP=$in_slave\" >> ~$in_login/state"
        $CMD_SLAVE "echo -e \"STATE=slave\nIP=$in_master\" >> ~$in_login/state"

        # Create id_dsa.pub on master

        $CMD_MASTER << ENDSSH
mkdir -p ~$in_login/.ssh
chown $in_login:$in_login ~$in_login/.ssh
chmod 700 ~$in_login/.ssh
su $in_login -c "ssh-keygen -t dsa -f ~$in_login/.ssh/id_dsa <<<\"\" "
ENDSSH

        # push id_dsa.pub to authorized_keys of slave

        master_key=$($CMD_MASTER "cat /home/$in_login/.ssh/id_dsa.pub")

        $CMD_SLAVE << ENDSSH
mkdir -p ~$in_login/.ssh
chown $in_login:$in_login ~$in_login/.ssh
chmod 700 ~$in_login/.ssh
su  $in_login -c "echo $master_key >> ~$in_login/.ssh/authorized_keys"
ENDSSH

        # create known_hosts

        $CMD_MASTER << ENDSSH
/usr/bin/ssh-keyscan $in_slave > /home/$in_login/.ssh/known_hosts
chown $in_login:$in_login /home/$in_login/.ssh/known_hosts
ENDSSH

        $CMD_SLAVE << ENDSSH
/usr/bin/ssh-keyscan $in_master > /home/$in_login/.ssh/known_hosts
chown $in_login:$in_login /home/$in_login/.ssh/known_hosts
ENDSSH

        echo "--------------------------------------------------------------------"

        # Mise en place de la methode de replication choisie
        if [ $in_replmode = "realtime" ]; then

            # demon lsyncd
            $CMD_MASTER <<ENDSSH
(crontab -lu $in_login 2>/dev/null; echo "@reboot  lsyncd ~$in_login/www $in_slave:www") |crontab -u $in_login -
sudo -u $in_login lsyncd ~$in_login/www $in_slave:www
ENDSSH
            # Pour les mails
	    # La réplication se fait au niveau du domaine (réplication de tout
	    # /home/vmail/example.com/).
        domain=$(remove_subdomain $wwwdomain)
	    $CMD_MASTER <<ENDSSH
if [ ! -d ~$VMAIL_USER/$domain ]; then
    mkdir ~$VMAIL_USER/$domain
    chown $VMAIL_USER:$VMAIL_USER ~$VMAIL_USER/$domain
    (crontab -lu $VMAIL_USER 2>/dev/null; echo '@reboot  /opt/evocluster/get-domains.sh $in_login |while read domain; do lsyncd ~$VMAIL_USER/\$domain $VMAIL_USER@$in_slave:\$domain; done'; echo '* * * * * /opt/evocluster/get-domains.sh $in_login |while read domain; do ldapsearch >~/\$domain/dump.sql; done') |crontab -u $VMAIL_USER -
    sudo -u $VMAIL_USER /opt/evocluster/get-domains.sh $in_login |while read domain; do lsyncd ~$VMAIL_USER/\$domain $VMAIL_USER@$in_slave:\$domain; done
fi
ENDSSH
            $CMD_SLAVE <<ENDSSH
[ -d ~$VMAIL_USER/$domain ] || mkdir ~$VMAIL_USER/$domain && chown $VMAIL_USER:$VMAIL_USER ~$VMAIL_USER/$domain
ENDSSH

            # replication MySQL
            # On eteint le slave en attendant qu'un evoman ajoute la db dans le
            # replicate-to-db et redemarre MySQL sur le slave. Comme ceci, il n'y a
            # pas besoin de faire un dump de la nouvelle base, lors du start slave
            # les donnees seront copiees.
            $CMD_SLAVE mysqladmin stop-slave
            mail -s "[TAF][Quai13] Replication MySQL pour $in_login" $CONTACT_MAIL <<ENDMAIL
Le compte $in_login a été crée sur $in_master (master) et $in_slave
(slave).
Pour terminer sa mise en place, il faut :
  - sur le slave, ajouter la base de données $in_dbname à la directive
  replicate-do-db dans la conf de MySQL ;
  - redémarrer MySQL sur le slave, et s'assurer que la réplication est
  ok (SHOW SLAVE STATUS et test d'écriture sur le master).

--
Evocluster
ENDMAIL
            # crontab
            $CMD_MASTER <<ENDSSH
(crontab -lu $in_login; echo " */5 * * * *  crontab -l |ssh $in_slave \"cat >crontab\"") |crontab -u $in_login -
ENDSSH

        elif [ $in_replmode = "deferred" ]; then

            domain=$(remove_subdomain $wwwdomain)

            if [ $in_replunit = "min" ]; then
                cron_line="*/$in_replinterval * * * *  /opt/evocluster/sync-master-to-slave.sh"
                cron_line2="*/$in_replinterval * * * *  /opt/evocluster/get-domains.sh $in_login |while read domain; do /opt/evocluster/sync-master-to-slave_mail.sh \$domain $in_login; done"
            elif [ $in_replunit = "hour" ]; then
                minute=$(($RANDOM % 60))
                offset=5
                cron_line="$minute */$in_replinterval * * *  /opt/evocluster/sync-master-to-slave.sh"
                cron_line2="$(($minute+$offset)) */$in_replinterval * * *  /opt/evocluster/get-domains.sh $in_login |while read domain; do /opt/evocluster/sync-master-to-slave_mail.sh \$domain $in_login; done"
            fi
            $CMD_MASTER "(crontab -lu $in_login; echo \"$cron_line\") |crontab -u $in_login -"
            $CMD_MASTER "(crontab -lu $VMAIL_USER; echo '$cron_line2') |crontab -u $VMAIL_USER -"
        fi

        # log

        DATE=$(date +"%Y-%m-%d")
        echo "$DATE [web-add-cluster.sh] $in_login added on $in_master and $in_slave" >> /var/log/evolix.log
    else
        DATE=$(date +"%Y-%m-%d")
        echo "$DATE [web-add-cluster.sh] $in_login added on $in_master" >> /var/log/evolix.log
    fi
}

op_del() {
	if [ $# -lt 3 ]; then
		usage
		exit 1
	else
		login=$1
		master=$2
		slave=$3
		if [ $# -eq 4 ]; then
			dbname=$4
		fi

	fi

    if [ $slave != "null" ]; then
        echo "Deleting account $login on $master and $slave. Continue ?"
    else
        echo "Deleting account $login on $master. Continue ?"
    fi
    read

	CMD_MASTER="$SSH $SSH_USER@$master"
	CMD_SLAVE="$SSH $SSH_USER@$slave"

	# check account exist on master and slave
	if [ -z "$($CMD_MASTER cut -d: -f1 /etc/passwd| grep ^$login$)" ]; then
		error "Account $login doesn't exist on $master";
		exit 1;
	fi
 
    if [ $slave != "null" ]; then
        if [ -z "$($CMD_SLAVE cut -d: -f1 /etc/passwd| grep ^$login$)" ]; then
            error "Account $login doesn't exist on $slave";
            exit 1;
        fi
    fi

	yes | $CMD_MASTER $WEBADD del $login $dbname
    if [ $slave != "null" ]; then
        yes | $CMD_SLAVE $WEBADD del $login $dbname
    fi

	DATE=$(date +"%Y-%m-%d")
    if [ $slave != "null" ]; then
        echo "$DATE [web-add-cluster.sh] $login deleted from $master and $slave" >> /var/log/evolix.log
    else
        echo "$DATE [web-add-cluster.sh] $login deleted from $master" >> /var/log/evolix.log
    fi
}

op_aliasadd() {
    if [ $# -lt 3 ]; then
        usage
        exit 1
    fi

    vhost=$1
    alias=$2
    master=$3
    slave=$4

	CMD_MASTER="$SSH $SSH_USER@$master"
	CMD_SLAVE="$SSH $SSH_USER@$slave"
    
    $CMD_MASTER $WEBADD add-alias $vhost $alias

    if [ $slave != "null" ]; then
        $CMD_SLAVE $WEBADD add-alias $vhost $alias
    fi

	DATE=$(date +"%Y-%m-%d")
    if [ $slave != "null" ]; then
        echo "$DATE [web-add-cluster.sh] $alias added to $vhost on $master and $slave" >> /var/log/evolix.log
    else
        echo "$DATE [web-add-cluster.sh] $login added to $vhost on $master" >> /var/log/evolix.log
    fi
}

op_aliasdel() {
    if [ $# -lt 3 ]; then
        usage
        exit 1
    fi

    vhost=$1
    alias=$2
    master=$3
    slave=$4

	CMD_MASTER="$SSH $SSH_USER@$master"
	CMD_SLAVE="$SSH $SSH_USER@$slave"
    
    $CMD_MASTER $WEBADD del-alias $vhost $alias

    if [ $slave != "null" ]; then
        $CMD_SLAVE $WEBADD del-alias $vhost $alias
    fi

	DATE=$(date +"%Y-%m-%d")
    if [ $slave != "null" ]; then
        echo "$DATE [web-add-cluster.sh] $alias deleted from $vhost on $master and $slave" >> /var/log/evolix.log
    else
        echo "$DATE [web-add-cluster.sh] $login deleted from $vhost on $master" >> /var/log/evolix.log
    fi
}



arg_processing() {

	# Détermination de la commande
	
	if [ $# -lt 1 ]; then
		usage
	else
		commandname=$1
		shift
		
		case "$commandname" in
		add)
			op_add $*
			;;
		del)
			op_del $*
			;;
		list-vhost)
			op_listvhost $*
			;;
        add-alias)
            op_aliasadd $*
            ;;
        del-alias)
            op_aliasdel $*
            ;;
		*)
			usage
			;;
		esac
	fi
}

op_listvhost() {
	if [ $# -eq 1 ]; then
		configlist="$VHOST_PATH/$1";
	else
		configlist="$VHOST_PATH/*";
	fi


	for configfile in $configlist; do
		if [ -r "$configfile" ]; then
			servername=`awk '/^[[:space:]]*ServerName (.*)/ { print $2 }' $configfile | head -n 1`
			serveraliases=`perl -ne 'print $1 if /^[[:space:]]*ServerAlias (.*)/' $configfile | head -n 1`
			serveraliases=`echo $serveraliases | sed 's/ \+/, /g'`
			userid=`awk '/^[[:space:]]*AssignUserID.*/ { print $3 }' $configfile | head -n 1`
			if [ "$servername" ] && [ "$userid" ]; then
				configid=`basename $configfile`
				echo "$userid:$configid:$servername:$serveraliases"
			fi
		fi
	done
}

op_add() {
	while getopts hyp:m:P:s:l:f:c: opt; do
		case "$opt" in
			p)
				in_passwd=$OPTARG
				;;
			m)
				in_dbname=$OPTARG
				;;
			P)
				in_dbpasswd=$OPTARG
				;;
			l)
				in_mail=$OPTARG
				;;
			f)
				in_replinterval=$OPTARG
				;;
			c)
				in_replunit=$OPTARG
				;;
			h)
				usage
				exit 1
				;;
			?)
				usage
				exit 1
				;;
		esac
	done

	shift $(($OPTIND - 1))
	if [ $# -ne 5 ]; then
		usage
		exit 1
	fi

	in_login=$1
	in_wwwdomain=$2
	in_master=$3
	in_slave=$4
	in_replmode=$5

	# in_master doit etre different d'in_slave
	[ "$in_master" = "$in_slave" ] && in_slave="null";

	validate_login $in_login || exit 1
	[ -z "$in_passwd" ] && in_passwd=`gen_random_passwd`
	validate_passwd $in_passwd || exit 1

	if [ -n "$in_dbname" ]; then
		validate_dbname $in_dbname || exit 1
		if [ -z "$in_dbpasswd" ]; then
			in_dbpasswd=`gen_random_passwd`
			validate_passwd $in_dbpasswd || exit 1
			echo "validate mysql passwd $in_dbpasswd";
		fi
		echo " ? validate mysql passwd $in_dbpasswd";
	fi

	validate_wwwdomain $in_wwwdomain || exit 1
	[ -z "$in_mail" ] && in_mail=$CONTACT_MAIL
	validate_mail $in_mail || exit 1
    validate_replmode $in_replmode || exit 1
    if [ $in_replmode = "deferred" ]; then
        validate_replinterval $in_replinterval $in_replunit || exit 1
    fi

	echo
	echo "----------------------------------------------"
	echo "Nom du compte : $in_login"
	echo "Mot de passe : $in_passwd"
	if [ -n "$in_dbname" ]; then
		echo "Base de données MySQL : $in_dbname"
		echo "Mot de passe MySQL : $in_dbpasswd"
	fi
	echo "Nom de domaine : $in_wwwdomain"
	echo "IP compte master : $in_master"
	echo "IP compte slave : $in_slave"
	echo "Mode de replication : $in_replmode"
	echo "Envoi du mail récapitulatif à : $in_mail"
	echo "----------------------------------------------"
	echo
	
	create_www_accounts
	echo
	echo " => Compte $in_login créé avec succès"
	echo
}

remove_subdomain() {
    #return $(echo $1 |sed 's/\([^\.]\+\.[^\.]\+\)$/\1/')
    echo $1 |perl -ne 'print $1 if ( /([^\.]+\.[^\.]+)$/ )'
}

# Point d'entrée
arg_processing $*

