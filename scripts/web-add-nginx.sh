#!/bin/bash

#
# Gestion des comptes web et des hôtes virtuels Nginx
#
# Copyright (c) 2013 Evolix - Tous droits reserves
#

# TODO
# - Gestion des quota
# - Possibilité de créer un compte FTP-only
# - Pouvoir spécifier le CONTACT_MAIL dans un fichier de conf
# - Traduire usage() en francais, ou l'inverse ??

set -e

HOME="/root"
CONTACT_MAIL="equipe@evolix.fr"
WWWBOUNCE_MAIL="equipe@evolix.fr"
LOCAL_SCRIPT="/usr/share/scripts/evoadmin/web-add.local.sh"
PRE_LOCAL_SCRIPT="/usr/share/scripts/evoadmin/web-add.pre-local.sh"
TPL_VHOST="/usr/share/scripts/evoadmin/vhost-nginx.tpl"
TPL_AWSTATS="/usr/share/scripts/evoadmin/awstats.XXX.conf"
TPL_MAIL="/usr/share/scripts/evoadmin/web-mail-nginx.tpl"
TPL_FPM="/usr/share/scripts/evoadmin/fpm.conf.tpl"
VHOST_PATH="/etc/nginx/sites-available"
FPM_PATH="/etc/php/7.0/fpm/pool.d"
FPM_SERVICE_NAME="php7.0-fpm"
MAX_LOGIN_CHAR=16
HOME_DIR="/home"
MYSQL_CREATE_DB_OPTS=""

# Utiliser ce fichier pour redefinir la valeur des variables ci-dessus
config_file="/etc/evolinux/web-add.conf"
[ -r $config_file ] && . $config_file

usage() {
        cat <<EOT >&2

Usage: $0 COMMAND [ARG]

add [ [OPTIONS] LOGIN WWWDOMAIN ]

    Create web account LOGIN.
    No arguments starts interactive mode.

   -p PASSWD
      FTP and SFTP password (default : random)

   -m DBNAME
      Name of MySQL database (default : same as account)

   -P DBPASSWD
      MySQL password (default : random)

   -l MAIL
      Send summary email to MAIL

   -k SSHKEY
      Use this SSH key

   -u UID
      Force account UID (only in command line)

   -g GID
      Force account GID (only in command line)

   -U UID
      Force www-account UID (only in command line)

   -y
      Don't ask for confirmation

   Example : web-add.sh add -m testdb testlogin testdomain.com

del LOGIN [DBNAME]

   Delete account and all files related (Apache, Awstats, etc)
   Archive home directory.
   Remove MySQL database only if DBNAME is specified.

list-vhost LOGIN

   List Apache vhost for user LOGIN

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
        if mysql -ss -e "show databases" | grep "^$dbname$" >/dev/null; then
                in_error "Base de données déjà existante"
                return 1
        fi
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

step_ok() {
        msg=$1
        echo "[OK] $msg"
}

create_www_account() {

        # Vérifications
        for filetocheck in $TPL_VHOST $TPL_AWSTATS $TPL_MAIL; do
                if [ ! -f $filetocheck ]; then
                        in_error "Fichier inexistant : $filetocheck"
                        exit 1
                fi
        done

        ############################################################################

        if [ -f $PRE_LOCAL_SCRIPT ]; then
                source $PRE_LOCAL_SCRIPT
        fi

        step_ok "Exécution du pre-script spécifique"

        ############################################################################

        if [ -z "$HOME_DIR_USER" ]; then
                HOME_DIR_USER="$HOME_DIR/$in_login"
        fi

        ############################################################################

        if  [ -d "$HOME_DIR_USER" ]; then
                in_error "Ce compte existe deja (ou il a mal été effacé)"
                return 1
        fi

        ############################################################################

        # Force UID GID if specified

        [ -n "$in_uid" ] && OPT_UID="--uid" && OPT_UID_ARG="$in_uid"
        [ -n "$in_gid" ] && OPT_GID="--gid" && OPT_GID_ARG="$in_gid"
        [ -n "$in_wwwuid" ] && OPT_WWWUID="--uid" && OPT_WWWUID_ARG="$in_wwwuid"

        ############################################################################


        /usr/sbin/adduser --gecos "User $in_login" --disabled-password "$in_login" \
        --shell /bin/bash $OPT_UID $OPT_UID_ARG --force-badname \
        --home "$HOME_DIR_USER" >/dev/null
        [ -z "$in_sshkey" ] && echo "$in_login:$in_passwd" | chpasswd
        [ -z "$in_sshkey" ] || [ -n "$HOME_DIR_USER" ] \
        && mkdir "$HOME_DIR_USER/.ssh" \
        && echo "$in_sshkey" > "$HOME_DIR_USER/.ssh/authorized_keys" \
            && chmod -R u=rwX,g=,o= "$HOME_DIR_USER/.ssh/authorized_keys" \
            && chown -R "$in_login":"$in_login" "$HOME_DIR_USER/.ssh"

        # Adding user www-data to group $in_login.
        # And primary group www-data for $in_login.
        adduser www-data $in_login
        usermod -g www-data $in_login
        # Authorize user to connect by SSH.
        sed -i "s/^AllowUsers .*/& $in_login/" /etc/ssh/sshd_config
        /etc/init.d/ssh reload

        step_ok "Création des utilisateurs"

        ############################################################################

        echo "$login: $WWWBOUNCE_MAIL" >> /etc/aliases
        newaliases

        step_ok "Alias mail"

        ############################################################################

        chmod 750 $HOME_DIR_USER/

        # Répertoires par défaut
        mkdir -p $HOME_DIR_USER/{log,www,awstats}
        chown $in_login:$in_login $HOME_DIR_USER/www
        chgrp $in_login $HOME_DIR_USER/{log,awstats}
        chmod 750 $HOME_DIR_USER/{log,www,awstats}

        # Ajout des logs par defaut
        touch $HOME_DIR_USER/log/access.log
        touch $HOME_DIR_USER/log/error.log
        touch $HOME_DIR_USER/log/php.log
        chgrp $in_login $HOME_DIR_USER/log/access.log
        chgrp $in_login $HOME_DIR_USER/log/error.log
        chown $in_login:$in_login $HOME_DIR_USER/log/php.log
        chmod 640 $HOME_DIR_USER/log/access.log
        chmod 640 $HOME_DIR_USER/log/error.log
        chmod 640 $HOME_DIR_USER/log/php.log

        step_ok "Création du répertoire personnel"

        ############################################################################

        random=$RANDOM

        cat $TPL_VHOST | \
            sed -e "
            s/DOMAIN/${in_wwwdomain}/g;
            s/LOGIN/${in_login}/g;" > ${VHOST_PATH}/$in_login
        # On active aussi example.com si le domaine commence par "www." comme
        # www.example
#       if echo $in_wwwdomain | grep '^www.' > /dev/null; then
#               subweb=$(echo $in_wwwdomain | sed -e "s/www.//")
#               sed -i -e "s/^\(.*\)#\(ServerAlias\).*$/\1\2 $subweb/" $vhostfile
#       fi

        ln -s /etc/nginx/sites-available/$in_login \
        /etc/nginx/sites-enabled/$in_login

        /etc/init.d/nginx restart

        step_ok "Configuration de Nginx + restart"

        ############################################################################

        cat $TPL_FPM | \
        sed -e "s/SED_LOGIN/${in_login}/g;" > ${FPM_PATH}/${in_login}.conf
        step_ok "Creation du pool PHP-FPM"

        ############################################################################

        cat $TPL_AWSTATS | \
                sed -e "s/XXX/$in_login/ ; s/SERVERNAME/$in_wwwdomain/ ; s#HOME_DIR#$HOME_DIR#" \
                > /etc/awstats/awstats.$in_login.conf
        chmod 644 /etc/awstats/awstats.$in_login.conf

       VAR=`grep -v "^#" /etc/cron.d/awstats |tail -1 | cut -d " " -f1`
        if [ "$VAR" = "" ] || [ $VAR -ge 59 ]; then
                VAR=1
        else
                VAR=$(($VAR +1))
        fi

        echo "$VAR * * * * root umask 033; [ -x /usr/lib/cgi-bin/awstats.pl -a -f /etc/awstats/awstats.$in_login.conf -a -r $HOME_DIR_USER/log/access.log ] && /usr/lib/cgi-bin/awstats.pl -config=$in_login -update >/dev/null" >> /etc/cron.d/awstats

        step_ok "Activation d'Awstats"

        ############################################################################

        if [ "$in_dbname" ]; then
                echo "CREATE DATABASE \`$in_dbname\` $MYSQL_CREATE_DB_OPTS;" | mysql
                #echo "CREATE DATABASE \`staging_${in_dbname}\` $MYSQL_CREATE_DB_OPTS;" | mysql
                echo "GRANT ALL PRIVILEGES ON \`$in_dbname\`.* TO \`$in_login\`@localhost IDENTIFIED BY '$in_dbpasswd';" | mysql
                #echo "GRANT ALL PRIVILEGES ON \`staging_${in_dbname}\`.* TO \`$in_login\`@localhost IDENTIFIED BY '$in_dbpasswd';" | mysql
                echo "FLUSH PRIVILEGES;" | mysql

                my_cnf_file="$HOME_DIR_USER/.my.cnf"
                cat >$my_cnf_file <<-EOT
                        [client]
                        user = $in_login
                        password = "$in_dbpasswd"

                        [mysql]
                        database = $in_dbname
EOT
                chown $in_login $my_cnf_file
                chmod 600 $my_cnf_file

                step_ok "Création base de données et compte MySQL"
        fi

        ############################################################################

        cat $TPL_MAIL | \
            sed -e "s/LOGIN/$in_login/g ; s/SERVERNAME/$in_wwwdomain/ ; s/PASSE1/$in_passwd/ ; s/PASSE2/$in_dbpasswd/ ; s/RANDOM/$random/ ; s/QUOTA/$quota/ ; s/RCPTTO/$in_mail/ ; s/DBNAME/$in_dbname/ ; s#HOME_DIR#$HOME_DIR#"| \
           /usr/lib/sendmail -oi -t -f "$CONTACT_MAIL"

        step_ok "Envoi du mail récapitulatif"

        ############################################################################

        fpm_status=$(echo -n $in_login | md5sum | cut -d' ' -f1)
        cat <<EOT> /etc/munin/plugin-conf.d/phpfpm_${in_login}_

[phpfpm_${in_login}_*]
env.url http://munin:%d/fpm_status_$fpm_status
env.ports 80
env.phpbin php-fpm
env.phppool $in_login
EOT
        for name in average connections memory processes status; do
        ln -s /usr/local/share/munin/plugins/phpfpm_${name} \
            /etc/munin/plugins/phpfpm_${in_login}_${name}
        done
        mkdir -p /etc/nginx/evolinux.d/
        cat <<EOT>> /etc/nginx/evolinux.d/munin-plugins.conf

# $in_login FPM Status page. Secret part is md5 of pool name.
location ~ ^/fpm_status_${fpm_status}$ {
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php-fpm-${in_login}.sock;
    fastcgi_param SCRIPT_FILENAME \$fastcgi_script_name;
    allow 127.0.0.1;
    deny all;
}
EOT
        sed -i "s#SED_STATUS#/fpm_status_${fpm_status}#" \
            ${FPM_PATH}/${in_login}.conf
        /etc/init.d/nginx reload
        /etc/init.d/${FPM_SERVICE_NAME} reload
        /etc/init.d/munin-node restart

        step_ok "Configuration plugin php-fpm pour munin"
        ############################################################################

        if [ -f $LOCAL_SCRIPT ]; then
                source $LOCAL_SCRIPT
        fi

        step_ok "Exécution du script spécifique"

        ############################################################################

        DATE=$(date +"%Y-%m-%d")
        echo "$DATE [web-add.sh] Ajout $in_login" >> /var/log/evolix.log
}

op_del() {
        if [ $# -lt 1 ]; then
                usage
                exit 1
        else
                login=$1
                if [ $# -eq 2 ]; then
                        dbname=$2
                fi
        fi

        echo "Deleting account $login. Continue ?"
        read

        set -x
        deluser www-data $login
        userdel $login
        groupdel $login
        sed -i.bak "/^$login:/d" /etc/aliases

        sed -i "s/^\(AllowUsers .*\)$login/\1/" /etc/ssh/sshd_config
        /etc/init.d/ssh reload

        if [ -d "$HOME_DIR/$login" ]; then
                mv -i $HOME_DIR/$login $HOME_DIR/$login.`date '+%Y%m%d-%H%M%S'`.bak
        else
                echo "warning : $HOME_DIR/$login does not exist"
        fi

        rm ${FPM_PATH}/${login}.conf
        rm /etc/nginx/sites-{available,enabled}/$login
        rm /etc/awstats/awstats.$login.conf
        rm /etc/munin/plugins/phpfpm_${login}*
        sed -i.bak "/-config=$login/d" /etc/cron.d/awstats
        nginx -t
        set +x

        if [ -n "$dbname" ]; then
                echo "Deleting mysql DATABASE $dbname and mysql user $login. Continue ?"
                read

                set -x
                echo "DROP DATABASE \`${dbname}\`; delete from mysql.user where User=\'${login}\' ; FLUSH PRIVILEGES;" | mysql
                set +x
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

        #
        # Mode interactif
        #
        if [ $# -eq 0 ]; then
                echo
                echo "Ajout d'un compte WEB"
                echo

                until [ "$in_login" ]; do
                        echo -n "Entrez le login du nouveau compte : "
                        read tmp
                        if validate_login "$tmp"; then
                                in_login="$tmp"
                        fi
                done

                until [ "$in_passwd" ]; do
                        echo -n "Entrez le mot de passe FTP/SFTP/SSH (ou vide pour aleatoire) : "
                        read -s tmp
                        echo

                        if [ -z "$tmp" ]; then
                                tmp=`gen_random_passwd`
                        fi

                        if validate_passwd "$tmp"; then
                                in_passwd="$tmp"
                        fi
                done

                echo -n "Voulez-vous aussi un compte/base MySQL ? [Y|n] "
                read confirm

                if [ "$confirm" != "n" ] && [ "$confirm" != "N" ]; then
                        until [ "$in_dbname" ]; do
                                echo -n "Entrez le nom de la base de donnees ($in_login par defaut) : "
                                read tmp

                                if [ -z "$tmp" ]; then
                                        tmp=$in_login
                                fi

                                if validate_dbname "$tmp"; then
                                        in_dbname="$tmp"
                                fi
                        done

                        until [ "$in_dbpasswd" ]; do
                                echo -n "Entrez le mot de passe MySQL (ou vide pour aleatoire) : "
                                read -s tmp
                                echo

                                if [ -z "$tmp" ]; then
                                        tmp=`gen_random_passwd`
                                fi

                                if validate_passwd "$tmp"; then
                                        in_dbpasswd="$tmp"
                                fi
                        done
                fi

                until [ "$in_wwwdomain" ]; do
                        echo -n "Entrez le nom de domaine web (ex: foo.example.com) : "
                        read tmp
                        if validate_wwwdomain "$tmp"; then
                                in_wwwdomain="$tmp"
                        fi
                done

                until [ "$in_mail" ]; do
                        echo -n "Entrez votre adresse mail pour recevoir le mail de creation ($CONTACT_MAIL par défaut) : "
                        read tmp
                        if [ -z "$tmp" ]; then
                                tmp="$CONTACT_MAIL"
                        fi
                        if validate_mail "$tmp"; then
                                in_mail="$tmp"
                        fi
                done

        #
        # Mode non interactif
        #
        else
                while getopts hyp:m:P:w:l:k:u:g:U: opt; do
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
                        k)
                                in_sshkey=$OPTARG
                                ;;
                        y)
                                force_confirm=1
                                ;;
                        u)
                                in_uid=$OPTARG
                                ;;
                        g)
                                in_gid=$OPTARG
                                ;;
                        U)
                                in_wwwuid=$OPTARG
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
                if [ $# -ne 2 ]; then
                        usage
                        exit 1
                else
                        in_login=$1
                        in_wwwdomain=$2
                        validate_login $in_login || exit 1
                        [ -z "$in_passwd" ] && [ -z "$in_sshkey" ] && in_passwd=`gen_random_passwd`
                        [ -z "$in_sshkey" ] && ( validate_passwd $in_passwd || exit 1 )
                        [ -n "$in_dbname" ] && ( validate_dbname $in_dbname || exit 1 )
                        [ -z "$in_dbpasswd" ] && [ -n "$in_dbname" ] && in_dbpasswd=`gen_random_passwd`
                        [ -n "$in_dbname" ] && ( validate_passwd $in_dbpasswd || exit 1 )
                        validate_wwwdomain $in_wwwdomain || exit 1
                        [ -z "$in_mail" ] && in_mail=$CONTACT_MAIL
                        validate_mail $in_mail || exit 1
                fi
        fi

        echo
        echo "----------------------------------------------"
        echo "Nom du compte : $in_login"
        echo "Mot de passe : $in_passwd"
        if [ "$in_dbname" ]; then
                echo "Base de données MySQL : $in_dbname"
                echo "Mot de passe MySQL : $in_dbpasswd"
        fi
        echo "Nom de domaine : $in_wwwdomain"
        echo "Envoi du mail récapitulatif à : $in_mail"
        echo "----------------------------------------------"
        echo

        if [ -z "$force_confirm" ]; then
                echo -n "Confirmer la création ? [y/N] : "
                read tmp
                echo
                if [ "$tmp" != "y" ] && [ "$tmp" != "Y" ]; then
                        echo "Annulation..."
                        echo
                        exit 1
                fi
        fi

        create_www_account
        echo
        echo " => Compte $in_login créé avec succès"
        echo
}

# Point d'entrée
arg_processing $*

