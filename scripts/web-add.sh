#!/bin/bash

#
# Gestion des comptes web et des hôtes virtuels pour Apache et Nginx
#
# Copyright (c) 2009-2017 Evolix - Tous droits reserves
#

# TODO
# - Gestion des quota
# - Possibilité de créer un compte FTP-only
# - Pouvoir spécifier le CONTACT_MAIL dans un fichier de conf
# - Traduire usage() en francais, ou l'inverse ??

set -e

VERSION="24.04"
HOME="/root"
CONTACT_MAIL="jdoe@example.org"
WWWBOUNCE_MAIL="jdoe@example.org"
SCRIPTS_PATH="/usr/share/scripts/evoadmin"
LOCAL_SCRIPT="$SCRIPTS_PATH/web-add.local.sh"
PRE_LOCAL_SCRIPT="$SCRIPTS_PATH/web-add.pre-local.sh"
TPL_AWSTATS="$SCRIPTS_PATH/awstats.XXX.conf"
SSH_GROUP="evolinux-ssh"
HOST="$(hostname -f)"

# Set to nginx if you use nginx and not apache
WEB_SERVER="apache"
if [ "$WEB_SERVER" == "apache" ]; then
    VHOST_PATH="/etc/apache2/sites-available"
    TPL_VHOST="$SCRIPTS_PATH/vhost"
    TPL_MAIL="$SCRIPTS_PATH/web-mail.tpl"

elif [ "$WEB_SERVER" == "nginx" ]; then
    VHOST_PATH="/etc/nginx/sites-available"
    TPL_VHOST="$SCRIPTS_PATH/vhost-nginx.tpl"
    TPL_MAIL="$SCRIPTS_PATH/web-mail-nginx.tpl"
else
    echo "$WEB_SERVER is not apache nor nginx, exiting..."
    exit 1
fi

# FPM
FPM_PATH="/etc/php/7.0/fpm/pool.d"
FPM_SERVICE_NAME="php7.0-fpm"
TPL_FPM="$SCRIPTS_PATH/fpm.conf.tpl"

MAX_LOGIN_CHAR=16
HOME_DIR="/home"
MYSQL_CREATE_DB_OPTS=""
MYSQL_OPTS=""
PHP_VERSIONS=()

# Utiliser ce fichier pour redefinir la valeur des variables ci-dessus
config_file="/etc/evolinux/web-add.conf"
# shellcheck source=/etc/evolinux/web-add.conf
[ -r $config_file ] && . $config_file

usage() {
    cat <<EOT >&2

Evoadmin web tooling - Version $VERSION
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

   -r
      PHP version (without dot)

   -q
      Filesystem quota in GiB, in the form <quota soft>:<quota hard>

   Example : web-add.sh add -m testdb -r 56 testlogin testdomain.com

del [ [OPTIONS] LOGIN [DBNAME] ]

   Delete account and all files related (Apache, Awstats, etc)
   Archive home directory.
   Remove MySQL database only if DBNAME is specified.

   -y
      Don't ask for confirmation

   Example : web-add.sh del -y testlogin testdatabase


list-vhost LOGIN

   List Apache vhost for user LOGIN

check-vhosts -f

	List suggested changes to vhosts, apply fixes with -f

add-alias VHOST ALIAS

    Add a ServerAlias to an Apache vhost

del-alias VHOST ALIAS

    Del a ServerAlias from an Apache vhost

update-servername VHOST SERVERNAME OLD_SERVERNAME

    Replace the OLD_SERVERNAME with the SERVERNAME for an Apache vhost
    Also apply to rewrite rules

check-occurence NAME

    List all occurences of NAME in vhosts

list-user-itk LOGIN

    List the assigned ITK user for the LOGIN specified

enable-user-itk LOGIN

    Enable the assigned ITK user for the LOGIN specified

disable-user-itk LOGIN

    Disable the assigned ITK user for the LOGIN specified

setphpversion LOGIN VERSION

    Change PHP version for LOGIN

setquota LOGIN QUOTA_SOFT:QUOTA_HARD

    Change quotas for LOGIN

manage-http-challenge-file [CREATE | DELETE]

    Create or delete a dummy file for the Let's Encrypt HTTP challenge
    The default directory is /var/lib/letsencrypt/.well-known/

generate-csr LOGIN DOMAINS

    Generate the request for the Let's Encrypt certificate

generate-ssl-certificate LOGIN [false]

    Generate the Let's Encrypt certificate
    Run in TEST mode unless "false" is used

version 

    Obtain the script version

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
    apg -c /dev/urandom -MNCL -n1 -m18  -E oOlL10
}

validate_login() {
    login=$1

    length=${#login}

    if [ "$length" -lt 3 ]; then
        in_error "Le login doit contenir plus de 2 caracteres"
        return 1
    fi

    if [ "$length" -gt $MAX_LOGIN_CHAR ]; then
        in_error "Le login ne doit pas contenir plus de $MAX_LOGIN_CHAR caracteres"
        return 1
    fi
}

validate_passwd() {
    passwd=$1
    length=${#passwd}

    if [ "$length" -lt 6 ] && [ "$length" -gt 0 ]; then
        in_error "Le mot de passe doit avoir au moins 6 caracteres"
        return 1
    fi
}

validate_dbname() {
    dbname=$1
    if mysql $MYSQL_OPTS -ss -e "show databases" | grep "^$dbname$" >/dev/null; then
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
    case "$wwwdomain" in
        *'/'*)
            in_error "Le caractère / n'est pas autorisé. Avez-vous confondu nom de domaine (example.com) et URL (https://example.com) ?"
            return 1;;
        *':'*)
            in_error "Le caractère : n'est pas autorisé. Avez-vous confondu nom de domaine (example.com) et URL (https://example.com) ?"
            return 1;;
    esac

    return 0
}

validate_mail() {
    return 0
}

validate_phpversion() {
    php_version="$1"
    if [[ ! " ${PHP_VERSIONS[*]} " =~ ${php_version} ]]; then
        in_error "Version de PHP incorrecte."
        return 1
    fi
}

validate_quota() {
    quota_soft=$(echo "$1" |cut -f 1 -d:)
    quota_hard=$(echo "$1" |cut -f 2 -d:)
    if [ -z "$quota_soft" ] || [ -z "$quota_hard" ]; then
        in_error "Le quota soft et le quota hard doivent être spécifiés sous la forme <quota soft>:<quota hard>."
        return 1
    elif [ "$quota_soft" -gt "$quota_hard" ]; then
        in_error "Le quota hard doit être plus grand que le quota soft."
        return 1
    fi
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
    	# shellcheck source=/usr/share/scripts/evoadmin/web-add.pre-local.sh
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

	# Create user and force UID / GID if specified
    /usr/sbin/adduser \
    	--gecos "User $in_login" \
    	--disabled-password \
    	"$in_login" \
    	--shell /bin/bash \
    	${in_uid:+'--uid' "$in_uid"} \
    	${in_gid:+'--gid' "$in_gid"} \
    	--force-badname \
    	--home "$HOME_DIR_USER" >/dev/null

    [ -z "$in_sshkey" ] \
    && echo "$in_login:$in_passwd" | chpasswd

    [ -z "$in_sshkey" ] \
    || [ -n "$HOME_DIR_USER" ] \
    && mkdir "$HOME_DIR_USER/.ssh" \
    && echo "$in_sshkey" > "$HOME_DIR_USER/.ssh/authorized_keys" \
	&& chmod -R u=rwX,g=,o= "$HOME_DIR_USER/.ssh/authorized_keys" \
    && chown -R "$in_login":"$in_login" "$HOME_DIR_USER/.ssh"

    if [ "$WEB_SERVER" == "apache" ]; then
        # Create www user and force UID if specified
        /usr/sbin/adduser \
        	--gecos "WWW $in_login" \
        	--disabled-password \
        	www-"$in_login" \
            --shell /bin/false \
            ${in_wwwuid:+'--uid' "$in_wwwuid"} \
            --ingroup "$in_login" \
            --force-badname \
            --home "$HOME_DIR_USER"/www \
            --no-create-home > /dev/null
    elif [ "$WEB_SERVER" == "nginx" ]; then
        # Adding user www-data to group $in_login.
        # And primary group www-data for $in_login.
        adduser www-data "$in_login"
        usermod -g www-data "$in_login"
    fi

    # Get uid/gid for newly created accounts
    uid=$(id -u "$in_login")
    gid=$(id -g "$in_login")
    www_uid=$(id -u www-"$in_login")

    # Create users inside all containers
    for php_version in "${PHP_VERSIONS[@]}"; do
        lxc-attach -n php"${php_version}" -- /usr/sbin/addgroup "$in_login" --gid "$gid" --force-badname >/dev/null
        lxc-attach -n php"${php_version}" -- /usr/sbin/adduser --gecos "User $in_login" --disabled-password "$in_login" --shell /bin/bash --uid "$uid" --gid "$gid" --force-badname --home "$HOME_DIR_USER" >/dev/null
        lxc-attach -n php"${php_version}" -- [ -z "$in_sshkey" ] && echo "$in_login:$in_passwd" | chpasswd
        lxc-attach -n php"${php_version}" -- /usr/sbin/adduser --disabled-password --home "$HOME_DIR_USER"/www --no-create-home --shell /bin/false --gecos "WWW $in_login" www-"$in_login" --uid "$www_uid" --ingroup "$in_login" --force-badname >/dev/null
    done

    if grep --quiet --extended-regexp --ignore-case '^AllowUsers' /etc/ssh/sshd_config; then
        sed -i "s/^AllowUsers .*/& $in_login/" /etc/ssh/sshd_config
    elif getent group "${SSH_GROUP}" 1>/dev/null 2>&1; then
        usermod --append --groups "${SSH_GROUP}" "$in_login"
    fi
    /etc/init.d/ssh reload

    step_ok "Création des utilisateurs"

    ############################################################################

    if [ "$WEB_SERVER" == "apache" ]; then
        echo "www-$login: $login" >> /etc/aliases
        echo "$login: $WWWBOUNCE_MAIL" >> /etc/aliases
    elif [ "$WEB_SERVER" == "nginx" ]; then
        echo "$login: $WWWBOUNCE_MAIL" >> /etc/aliases
    fi
    newaliases

    step_ok "Alias mail"

    ############################################################################

    chmod 750 "$HOME_DIR_USER"/

    # Répertoires par défaut
    mkdir -p "$HOME_DIR_USER"/{log,www,awstats}
    chown "$in_login":"$in_login" "$HOME_DIR_USER"/www
    chgrp "$in_login" "$HOME_DIR_USER"/{log,awstats}
    chmod 750 "$HOME_DIR_USER"/{log,www,awstats}

    # Ajout des logs par defaut
    touch "$HOME_DIR_USER"/log/access.log
    touch "$HOME_DIR_USER"/log/error.log
    touch "$HOME_DIR_USER"/log/php.log
    chgrp "$in_login" "$HOME_DIR_USER"/log/access.log
    chgrp "$in_login" "$HOME_DIR_USER"/log/error.log
    if [ "$WEB_SERVER" == "apache" ]; then
        chown www-"$in_login":"$in_login" "$HOME_DIR_USER"/log/php.log
    fi
    # There is no php.log for nginx ATM, it will go in error.log.
    chmod 640 "$HOME_DIR_USER"/log/access.log
    chmod 640 "$HOME_DIR_USER"/log/error.log
    chmod 640 "$HOME_DIR_USER"/log/php.log

    step_ok "Création du répertoire personnel"

    ############################################################################

    if [ -n "$in_quota" ]; then
        quota_soft=$(($(echo "$in_quota" |cut -f 1 -d:) * 1024 * 1024))
        quota_hard=$(($(echo "$in_quota" |cut -f 2 -d:) * 1024 * 1024))
        setquota --remote --user "$in_login" $quota_soft $quota_hard 0 0 /home
    fi

    ############################################################################

    # Create FPM pool on all containers.
    for php_version in "${PHP_VERSIONS[@]}"; do
        if [ "$php_version" = "70" ]; then
            pool_path="/etc/php/7.0/fpm/pool.d/"
        elif [ "$php_version" = "73" ]; then
            pool_path="/etc/php/7.3/fpm/pool.d/"
        elif [ "$php_version" = "74" ]; then
            pool_path="/etc/php/7.4/fpm/pool.d/"
        elif [ "$php_version" = "80" ]; then
            pool_path="/etc/php/8.0/fpm/pool.d/"
        elif [ "$php_version" = "81" ]; then
            pool_path="/etc/php/8.1/fpm/pool.d/"
        elif [ "$php_version" = "82" ]; then
            pool_path="/etc/php/8.2/fpm/pool.d/"
        elif [ "$php_version" = "83" ]; then
            pool_path="/etc/php/8.3/fpm/pool.d/"
        elif [ "$php_version" = "84" ]; then
            pool_path="/etc/php/8.4/fpm/pool.d/"
        else
            pool_path="/etc/php5/fpm/pool.d/"
        fi
        phpfpm_socket_path="/home/${in_login}/php-fpm${php_version}.sock"
        cat <<EOT >/var/lib/lxc/php"${php_version}"/rootfs/${pool_path}/"${in_login}".conf
[${in_login}]
user = www-${in_login}
group = ${in_login}

listen = ${phpfpm_socket_path}
listen.owner = ${in_login}
listen.group = ${in_login}

pm = ondemand
pm.status_path = /evolinux_fpm_status-$(apg -Mncl -n1 -m32)
pm.max_children = 10
pm.process_idle_timeout = 10s

php_admin_value[error_log] = /home/${in_login}/log/php.log
php_admin_value[sendmail_path] = "/usr/sbin/sendmail -t -i -f www-${in_login}@${HOST}"
php_admin_value[open_basedir] = "/usr/share/php:/home/${in_login}:/tmp"
EOT
        step_ok "Création du pool FPM ${php_version}"
    done

    ############################################################################

    random=$RANDOM
    if [ "$WEB_SERVER" == "apache" ]; then
        # On s'assure que /etc/apache2/ssl pour le IncludeOptional de la conf
        mkdir -p /etc/apache2/ssl

        vhostfile="/etc/apache2/sites-available/${in_login}.conf"
        sed -e "s/XXX/$in_login/g ; s/SERVERNAME/$in_wwwdomain/ ; s/RANDOM/$random/ ; s#HOME_DIR#$HOME_DIR#" < $TPL_VHOST > "$vhostfile"

        if [ ${#PHP_VERSIONS[@]} -gt 0 ]; then
            phpfpm_socket_path="/home/${in_login}/php-fpm${in_phpversion}.sock"
            cat <<EOT >>"$vhostfile"
    <Proxy "unix:${phpfpm_socket_path}|fcgi://localhost/" timeout=300>
    </Proxy>
    <FilesMatch "\\.php$">
        SetHandler proxy:unix:${phpfpm_socket_path}|fcgi://localhost/
    </FilesMatch>
</VirtualHost>
EOT
        else
            cat <<EOT >>"$vhostfile"
</VirtualHost>
EOT
        fi

        # On active aussi example.com si domaine commence par "www." comme www.example
        if echo "$in_wwwdomain" | grep '^www.' > /dev/null; then
            subweb="${in_wwwdomain#www.}"
            sed -i -e "s/^\\(.*\\)#\\(ServerAlias\\).*$/\\1\\2 $subweb/" "$vhostfile"
        fi

        a2ensite "${in_login}.conf" >/dev/null

        step_ok "Configuration d'Apache"

    elif [ "$WEB_SERVER" == "nginx" ]; then
        sed -e \
        "s/DOMAIN/${in_wwwdomain}/g; s/LOGIN/${in_login}/g;" \
        < "$TPL_VHOST" \
        > ${VHOST_PATH}/"$in_login"
        ln -s /etc/nginx/sites-available/"$in_login" \
            /etc/nginx/sites-enabled/"$in_login"

        /etc/init.d/nginx restart

        step_ok "Configuration de Nginx + restart"

        ############################################################################

        sed -e "s/SED_LOGIN/${in_login}/g;" \
        < $TPL_FPM > ${FPM_PATH}/"${in_login}".conf
        step_ok "Creation du pool PHP-FPM"

        ############################################################################
    fi

    sed -e "s/XXX/$in_login/ ; s/SERVERNAME/$in_wwwdomain/ ; s#HOME_DIR#$HOME_DIR#" \
        < $TPL_AWSTATS > /etc/awstats/awstats."$in_login".conf
    chmod 644 /etc/awstats/awstats."$in_login".conf

       VAR=$(grep -v "^#" /etc/cron.d/awstats |tail -1 | cut -d " " -f1)
    if [ "$VAR" = "" ] || [ "$VAR" -ge 59 ]; then
        VAR=1
    else
        VAR=$((VAR +1))
    fi

    echo "$VAR * * * * root umask 033; [ -x /usr/lib/cgi-bin/awstats.pl -a -f /etc/awstats/awstats.$in_login.conf -a -r $HOME_DIR_USER/log/access.log ] && /usr/lib/cgi-bin/awstats.pl -config=$in_login -update >/dev/null" >> /etc/cron.d/awstats

    step_ok "Activation d'Awstats"

    ############################################################################

    if [ "$in_dbname" ]; then
        echo "CREATE DATABASE \`$in_dbname\` $MYSQL_CREATE_DB_OPTS;" | mysql $MYSQL_OPTS
        echo "GRANT ALL PRIVILEGES ON \`$in_dbname\`.* TO \`$in_login\`@localhost IDENTIFIED BY '$in_dbpasswd';" | mysql $MYSQL_OPTS
        echo "FLUSH PRIVILEGES;" | mysql $MYSQL_OPTS

        my_cnf_file="$HOME_DIR_USER/.my.cnf"
        cat > "$my_cnf_file" <<-EOT
[client]
user = $in_login
password = "$in_dbpasswd"

[mysql]
database = $in_dbname
EOT
        chown "$in_login" "$my_cnf_file"
        chmod 600 "$my_cnf_file"

        step_ok "Création base de données et compte MySQL"
    fi

    ############################################################################

    if [ "$in_dbname" ]; then
        sed -e "
        s/LOGIN/$in_login/g ;
        s/SERVERNAME/$in_wwwdomain/ ;
        s/PASSE1/$in_passwd/ ;
        s/PASSE2/$in_dbpasswd/ ;
        s/RANDOM/$random/ ;
        s/QUOTA/$quota/ ;
        s/RCPTTO/$in_mail/ ;
        s/DBNAME/$in_dbname/ ;
        s#HOME_DIR#$HOME_DIR#" \
        < $TPL_MAIL | /usr/lib/sendmail -oi -t -f "$CONTACT_MAIL"
    else
        sed -e "
            s/LOGIN/$in_login/g ;
            s/SERVERNAME/$in_wwwdomain/ ;
            s/PASSE1/$in_passwd/ ;
            s/RANDOM/$random/ ;
            s/QUOTA/$quota/ ;
            s/RCPTTO/$in_mail/ ;
            s#HOME_DIR#$HOME_DIR# ;
            39,58d" \
            < $TPL_MAIL | /usr/lib/sendmail -oi -t -f "$CONTACT_MAIL"
    fi

    step_ok "Envoi du mail récapitulatif"

    ############################################################################

    if [ -f $LOCAL_SCRIPT ]; then
    	# shellcheck source=/usr/share/scripts/evoadmin/web-add.local.sh
        source $LOCAL_SCRIPT
    fi

    step_ok "Exécution du script spécifique"

    ############################################################################

    if [ "$WEB_SERVER" == "apache" ]; then
        apache2ctl configtest 2>/dev/null
        /etc/init.d/apache2 force-reload >/dev/null
        for php_version in "${PHP_VERSIONS[@]}"; do
            if [ "$php_version" = "70" ]; then
                initscript_path="/etc/init.d/php7.0-fpm"
                binary="php-fpm7.0"
            elif [ "$php_version" = "73" ]; then
                initscript_path="/etc/init.d/php7.3-fpm"
                binary="php-fpm7.3"
            elif [ "$php_version" = "74" ]; then
                initscript_path="/etc/init.d/php7.4-fpm"
                binary="php-fpm7.4"
            elif [ "$php_version" = "80" ]; then
                initscript_path="/etc/init.d/php8.0-fpm"
                binary="php-fpm8.0"
            elif [ "$php_version" = "81" ]; then
                initscript_path="/etc/init.d/php8.1-fpm"
                binary="php-fpm8.1"
            elif [ "$php_version" = "82" ]; then
                initscript_path="/etc/init.d/php8.2-fpm"
                binary="php-fpm8.2"
            elif [ "$php_version" = "83" ]; then
                initscript_path="/etc/init.d/php8.3-fpm"
                binary="php-fpm8.3"
            elif [ "$php_version" = "84" ]; then
                initscript_path="/etc/init.d/php8.4-fpm"
                binary="php-fpm8.4"
            else
                initscript_path="/etc/init.d/php5-fpm"
                binary="php5-fpm"
            fi
            lxc-attach -n php"${php_version}" -- $binary --test >/dev/null
            lxc-attach -n php"${php_version}" -- $initscript_path restart >/dev/null
            step_ok "Rechargement de php-fpm dans php${php_version}"
        done

        step_ok "Rechargement d'Apache"
    fi

############################################################################

    if [ "$WEB_SERVER" == "nginx" ]; then
        fpm_status=$(echo -n "$in_login" | md5sum | cut -d' ' -f1)
        cat <<EOT> /etc/munin/plugin-conf.d/phpfpm_"${in_login}"_

[phpfpm_${in_login}_*]
env.url http://munin:%d/fpm_status_$fpm_status
env.ports 80
env.phpbin php-fpm
env.phppool $in_login
EOT
        for name in average connections memory processes status; do
        ln -s /usr/local/share/munin/plugins/phpfpm_${name} \
            /etc/munin/plugins/phpfpm_"${in_login}"_${name}
        done
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
            ${FPM_PATH}/"${in_login}".conf
        /etc/init.d/nginx reload
        /etc/init.d/${FPM_SERVICE_NAME} reload
        /etc/init.d/munin-node restart

        step_ok "Configuration plugin php-fpm pour munin"
    fi
    ############################################################################

    DATE=$(date +"%Y-%m-%d")
    echo "$DATE [web-add.sh] Ajout $in_login" >> /var/log/evolix.log
}

op_del() {

    #
    # Mode interactif
    #

    if [ $# -eq 0 ]; then
        echo
        echo "Suppression d'un compte WEB"
        echo

        until [ "$login" ]; do
            echo -n "Entrez le login du compte à supprimer : "
            read -r tmp
            login="$tmp"
        done

        echo -n "Voulez-vous aussi supprimer un compte/base MySQL ? [y|N]"
        read -r confirm

        if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
            echo -n "Entrez le nom de la base de donnees ($login par defaut) : "
            read -r tmp

            if [ -z "$tmp" ]; then
                dbname=$login
            else
                dbname="$tmp"
            fi
        fi

    #
    # Mode non interactif
    #

    else
        while getopts hy opt; do
            case "$opt" in
            y)
                force_confirm=1
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

        shift $((OPTIND - 1))
        if [ $# -gt 0 ] && [ $# -le 2 ]; then
            login=$1
            if [ $# -eq 2 ]; then
                dbname=$2
            fi
        else
            usage
            exit 1
        fi
    fi

    echo
    echo "----------------------------------------------"
    echo "Nom du compte : $login"
    if [ "$dbname" ]; then
        echo "Base de données MySQL : $dbname"
    fi
    echo "----------------------------------------------"
    echo

    if [ -z "$force_confirm" ]; then
        echo -n "Confirmer la suppression ? [y/N] : "
        read -r tmp
        echo
        if [ "$tmp" != "y" ] && [ "$tmp" != "Y" ]; then
            echo "Annulation..."
            echo
            exit 1
        fi
    fi


    set -x
    # Crontab dump needs to be done **before** user deletion
    if crontab -l -u "$login"; then
        crontab -l -u "$login" &> /home/$login/crontab-$(date '+%Y%m%d-%H%M%S').bak
        crontab -r -u "$login"
    fi

    # Deactivate web vhost (apache or nginx)
    if [ "$WEB_SERVER" == "apache" ]; then
        if a2query -s "${login}" >/dev/null 2&>1; then
            a2dissite "${login}.conf"
        fi
        rm -f /etc/apache2/sites-available/"$login.conf"

        apache2ctl configtest

        for php_version in "${PHP_VERSIONS[@]}"; do
            if [ "$php_version" = "70" ]; then
                phpfpm_dir="/etc/php/7.0/fpm/pool.d/"
                initscript_path="/etc/init.d/php7.0-fpm"
            elif [ "$php_version" = "73" ]; then
                phpfpm_dir="/etc/php/7.3/fpm/pool.d/"
                initscript_path="/etc/init.d/php7.3-fpm"
            elif [ "$php_version" = "74" ]; then
                phpfpm_dir="/etc/php/7.4/fpm/pool.d/"
                initscript_path="/etc/init.d/php7.4-fpm"
            elif [ "$php_version" = "80" ]; then
                phpfpm_dir="/etc/php/8.0/fpm/pool.d/"
                initscript_path="/etc/init.d/php8.0-fpm"
            elif [ "$php_version" = "81" ]; then
                phpfpm_dir="/etc/php/8.1/fpm/pool.d/"
                initscript_path="/etc/init.d/php8.1-fpm"
            elif [ "$php_version" = "82" ]; then
                phpfpm_dir="/etc/php/8.2/fpm/pool.d/"
                initscript_path="/etc/init.d/php8.2-fpm"
            elif [ "$php_version" = "83" ]; then
                phpfpm_dir="/etc/php/8.3/fpm/pool.d/"
                initscript_path="/etc/init.d/php8.3-fpm"
            elif [ "$php_version" = "84" ]; then
                phpfpm_dir="/etc/php/8.4/fpm/pool.d/"
                initscript_path="/etc/init.d/php8.4-fpm"
            else
                phpfpm_dir="/etc/php5/fpm/pool.d/"
                initscript_path="/etc/init.d/php5-fpm"
            fi
            rm -f /var/lib/lxc/php"${php_version}"/rootfs/${phpfpm_dir}/"${login}".conf
            lxc-attach -n php"${php_version}" -- $initscript_path restart >/dev/null
        done

    elif [ "$WEB_SERVER" == "nginx" ]; then
        rm -f /etc/nginx/sites-{available,enabled}/"$login"
        rm -f /etc/munin/plugins/phpfpm_"${in_login}"*
        nginx -t
    fi

    rm -f /etc/awstats/awstats."$login.conf"
    sed -i.bak "/-config=$login /d" /etc/cron.d/awstats

    if [ "$WEB_SERVER" == "apache" ]; then
        if id www-"$login" &> /dev/null; then
            userdel -f www-"$login"
        fi

        for php_version in "${PHP_VERSIONS[@]}"; do
            if lxc-attach -n php"${php_version}" -- getent passwd www-"$login" &> /dev/null; then
                lxc-attach -n php"${php_version}" -- userdel -f www-"$login"
            fi
            if lxc-attach -n php"${php_version}" -- getent passwd "$login" &> /dev/null; then
                lxc-attach -n php"${php_version}" -- userdel -f "$login"
            fi
        done
    fi

    if getent passwd "$login" &> /dev/null; then
        userdel -f "$login"
    fi

    sed -i.bak "/^$login:/d" /etc/aliases
    if [ "$WEB_SERVER" == "apache" ]; then
        sed -i.bak "/^www-$login:/d" /etc/aliases
    fi

    if grep --quiet --extended-regexp --ignore-case '^AllowUsers' /etc/ssh/sshd_config; then
        sed -i "s/^AllowUsers .*/& $in_login/" /etc/ssh/sshd_config
    elif getent group "${SSH_GROUP}" 1>/dev/null 2>&1; then
        usermod --append --groups "${SSH_GROUP}" "$in_login"
    fi

    if [ -d "$HOME_DIR/$login" ]; then
        mv -i $HOME_DIR/"$login" $HOME_DIR/"$login"."$(date '+%Y%m%d-%H%M%S')".bak
    else
        echo "warning : $HOME_DIR/$login does not exist"
    fi

    if [ -d /etc/letsencrypt/"$login" ]; then
        rm -r /etc/letsencrypt/"$login"
    fi

    set +x

    if [ -n "$dbname" ]; then
        set -x
        echo "DROP DATABASE \`$dbname\`; DROP USER \`$login\`@localhost; FLUSH PRIVILEGES;" | mysql $MYSQL_OPTS
        set +x
    fi
}

op_setphpversion() {
    if [ $# -ne 2 ]; then
        usage
        exit 1
    fi
    login="$1"
    phpversion="$2"

    validate_phpversion "$phpversion"

    sed -i "s#^\\(\s*SetHandler proxy:unix:/home/.*/php-fpm\\)..\\(\\.sock\\)#\\1${phpversion}\\2#" /etc/apache2/sites-available/"${login}".conf
    sed -i "s#^\\(\s*<Proxy .*unix:/home/.*/php-fpm\\)..\\(\\.sock\\)#\\1${phpversion}\\2#" /etc/apache2/sites-available/"${login}".conf
    /etc/init.d/apache2 force-reload >/dev/null

    DATE=$(date +"%Y-%m-%d")
    echo "$DATE [web-add.sh] PHP version set to $phpversion for $login" >> /var/log/evolix.log
}

op_setquota() {
    if [ $# -ne 2 ]; then
        usage
        exit 1
    fi
    login="$1"
    quota="$2"

    validate_quota "$quota"

    quota_soft=$(($(echo "$quota" |cut -f 1 -d:) * 1024 * 1024))
    quota_hard=$(($(echo "$quota" |cut -f 2 -d:) * 1024 * 1024))
    setquota --remote --user "$login" $quota_soft $quota_hard 0 0 /home

    DATE=$(date +"%Y-%m-%d")
    echo "$DATE [web-add.sh] quota set to $quota for $login" >> /var/log/evolix.log
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
            op_add "$@"
            ;;
        del)
            op_del "$@"
            ;;
        list-vhost)
            op_listvhost "$@"
            ;;
        check-vhosts)
            op_checkvhosts "$@"
            ;;
        add-alias)
            op_aliasadd "$@"
            ;;
        del-alias)
            op_aliasdel "$@"
            ;;
        update-servername)
            op_servernameupdate "$@"
            ;;
        check-occurence)
            op_checkoccurencename "$@"
            ;;
        list-user-itk)
            op_listuseritk "$@"
            ;;
        enable-user-itk)
            op_enableuseritk "$@"
            ;;
        disable-user-itk)
            op_disableuseritk "$@"
            ;;
        setphpversion)
            op_setphpversion "$@"
            ;;
        setquota)
            op_setquota "$@"
            ;;
        manage-http-challenge-file)
            op_managehttpchallengefile "$@"
            ;;
        generate-csr)
            op_makecsr "$@"
            ;;
        generate-ssl-certificate)
            op_generatesslcertificate "$@"
            ;;
        version)
            op_version "$@"
            ;;
        *)
            usage
            ;;
        esac
    fi
}

op_makecsr() {
    if [ $# -gt 1 ]; then
        vhost="$1"
        domains=""

        # remove the first argument to keep only the domains
        shift 1

        for domain in "$@"; do
            domains="${domains:+${domains} }${domain}"
        done

        # pipe the domains to make-csr because we don't have STDIN
        echo "$domains" | make-csr "$vhost"
    else usage
    fi
}

op_generatesslcertificate() {
    if [ $# -gt 1 ]; then
        vhost="$1"
        test_mode="$2"

        if [ "$test_mode" = "false" ]; then
            if [ -L /etc/letsencrypt/$vhost/live ]; then
                rm /etc/letsencrypt/$vhost/live
            fi
            evoacme "$vhost"
        else
            DRY_RUN=1 evoacme "$vhost"
        fi
    else usage
    fi
}

op_managehttpchallengefile() {
    if [ $# -eq 1 ]; then
        folder="/var/lib/letsencrypt/.well-known"
        file="testfile"

        action=${1};

        if [ "$action" = "create" ]; then
            if [ ! -d "$folder" ]; then
                mkdir -p "$folder/acme-challenge"
            fi
            if [ ! -f "$folder/acme-challenge/$file" ]; then
                touch "$folder/acme-challenge/$file"
            fi
            chmod -R 755 "$folder"
        elif [ "$action" = "delete" ]; then
            rm -r "$folder"
        else usage
        fi
    else usage
    fi
}

op_listvhost() {
    if [ $# -eq 1 ]; then
        configlist="$VHOST_PATH/${1}.conf";
    else
        configlist="$VHOST_PATH/*";
    fi

    for configfile in $configlist; do
        if [ -r "$configfile" ] && echo "$configfile" |grep -qvE "/(000-default|default-ssl|evoadmin)\\.conf$"; then
            servername="$(awk '/^[[:space:]]*ServerName (.*)/ { print $2 }' "$configfile" | head -n 1)"
            serveraliases="$(perl -ne 'print "$1 " if /^[[:space:]]*ServerAlias (.*)/' "$configfile" | head -n 1)"
            serveraliases="$(echo $serveraliases | sed 's/ \+/,/g')"
            userid="$(awk '/^[[:space:]]*AssignUserID.*/ { print $3 }' "$configfile" | head -n 1)"
            if [ -x /usr/bin/quota ]; then
                size=$(quota --no-wrap --human-readable "$userid" |grep /home |awk '{print $2}')
                quota_soft=$(quota --no-wrap --human-readable "$userid" |grep /home |awk '{print $3}')
                quota_hard=$(quota --no-wrap --human-readable "$userid" |grep /home |awk '{print $4}')
            fi
            phpversion=$(perl -lne 'print $1 if (m!^\s+SetHandler proxy:unix:/home/.*/php-fpm(\d{2})\.sock!)' "$configfile" | head -n 1)
            if [ -e /etc/apache2/sites-enabled/"${userid}".conf ]; then
                is_enabled=1
            else
                is_enabled=0
            fi

            count_virtualhosts="$(grep "<VirtualHost" "$configfile" | wc -l)"
            if [ "$count_virtualhosts" -eq 1 ]; then
                is_standard=1
            else
                is_standard=0
            fi

            if [ "$servername" ] && [ "$userid" ]; then
                configid=$(basename "$configfile")
                echo "$userid:$configid:$servername:$serveraliases:$size:$quota_soft:$quota_hard:$phpversion:$is_enabled:$is_standard"
            fi
        fi
    done
}

op_aliasadd() {
    if [ $# -eq 2 ]; then
        vhost="${1}.conf"
        alias=$2
        vhost_file="${VHOST_PATH}/${vhost}"

        if [ -f "${vhost_file}" ]; then
            sed -i "/ServerName .*/a \\\tServerAlias $alias" "${vhost_file}" --follow-symlinks
        else
            echo "VHost file \`${vhost_file}' not found'" >&2
            return 1
        fi

        configtest_out=$(apache2ctl configtest)
        configtest_rc=$?

        if [ "$configtest_rc" = "0" ]; then
            /etc/init.d/apache2 force-reload >/dev/null
        else
            echo $configtest_out >&2
        fi
    else usage
    fi
}

op_aliasdel() {
    if [ $# -eq 2 ]; then
        vhost="${1}.conf"
        alias=$2
        vhost_file="${VHOST_PATH}/${vhost}"

        if [ -f "${vhost_file}" ]; then
            sed -i -e "/ServerAlias $alias/d" "${vhost_file}" --follow-symlinks
        else
            echo "VHost file \`${vhost_file}' not found'" >&2
            return 1
        fi

        configtest_out=$(apache2ctl configtest)
        configtest_rc=$?

        if [ "$configtest_rc" = "0" ]; then
            /etc/init.d/apache2 force-reload >/dev/null
        else
            echo $configtest_out >&2
        fi
    else
        usage
    fi
}

op_servernameupdate() {
    if [ $# -eq 3 ]; then
      vhost="${1}.conf"
      servername=$2
      old_servername=$3
      vhost_file="${VHOST_PATH}/${vhost}"

      if [ -f "${vhost_file}" ]; then
          sed -i "/^ *ServerName/ s/$old_servername/$servername/g" "${vhost_file}" --follow-symlinks
          sed -i "/^ *RewriteCond/ s/$old_servername/$servername/g" "${vhost_file}" --follow-symlinks
      fi

      configtest_out=$(apache2ctl configtest)
      configtest_rc=$?

      if [ "$configtest_rc" = "0" ]; then
          /etc/init.d/apache2 force-reload >/dev/null
      else
          echo $configtest_out >&2
      fi
    else
        usage
    fi
}

op_checkoccurencename() {
    if [ $# -eq 1 ]; then
        name=${1}
        configlist="$VHOST_PATH/*";
        servernames=''
        aliases=''

        for configfile in $configlist; do
            if [ -r "$configfile" ]; then
                alias=$(perl -ne 'print "$1 " if /^[[:space:]]*ServerAlias (.*)/' "$configfile" | head -n 1)
                aliases="$aliases $alias"

                servername=$(awk '/^[[:space:]]*ServerName (.*)/ { print $2 }' "$configfile" | uniq)
                servernames="$servernames $servername"
            fi
        done

        echo "$servernames" "$aliases" | grep -E "(^|\s)$name(\s|$)"
    else
        usage
    fi
}

op_listuseritk() {
    if [ $# -eq 1 ]; then
        configfile="$VHOST_PATH/${1}.conf"

        awk '/AssignUserID/ {print $2}' "$configfile" | uniq
    else
        usage
    fi
}

op_enableuseritk() {
    if [ $# -eq 1 ]; then
        configfile="$VHOST_PATH/${1}.conf"
        group=$(awk '/AssignUserID/ {print $3}' "$configfile" | uniq)

        sed -i "s/^ *AssignUserID $group/    AssignUserID www-$group/" "$configfile" --follow-symlinks

        configtest_out=$(apache2ctl configtest)
        configtest_rc=$?

        if [ "$configtest_rc" = "0" ]; then
            /etc/init.d/apache2 force-reload >/dev/null
        else
            echo $configtest_out >&2
        fi
    else
        usage
    fi
}

op_disableuseritk() {
    if [ $# -eq 1 ]; then
        configfile="$VHOST_PATH"/"${1}".conf
        group=$(awk '/AssignUserID/ {print $3}' "$configfile" | uniq)

        sed -i "s/^ *AssignUserID www-$group/    AssignUserID ${group}/" "$configfile" --follow-symlinks

        configtest_out=$(apache2ctl configtest)
        configtest_rc=$?

        if [ "$configtest_rc" = "0" ]; then
            /etc/init.d/apache2 force-reload >/dev/null
        else
            echo $configtest_out >&2
        fi
    else
        usage
    fi
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
            read -r tmp
            if validate_login "$tmp"; then
                in_login="$tmp"
            fi
        done

        until [ "$in_passwd" ]; do
            echo -n "Entrez le mot de passe FTP/SFTP/SSH (ou vide pour aleatoire) : "
            read -rs tmp
            echo

            if [ -z "$tmp" ]; then
                tmp=$(gen_random_passwd)
            fi

            if validate_passwd "$tmp"; then
                in_passwd="$tmp"
            fi
        done

        echo -n "Voulez-vous aussi un compte/base MySQL ? [Y|n] "
        read -r confirm

        if [ "$confirm" != "n" ] && [ "$confirm" != "N" ]; then
            until [ "$in_dbname" ]; do
                echo -n "Entrez le nom de la base de donnees ($in_login par defaut) : "
                read -r tmp

                if [ -z "$tmp" ]; then
                    tmp=$in_login
                fi

                if validate_dbname "$tmp"; then
                    in_dbname="$tmp"
                fi
            done

            until [ "$in_dbpasswd" ]; do
                echo -n "Entrez le mot de passe MySQL (ou vide pour aleatoire) : "
                read -rs tmp
                echo

                if [ -z "$tmp" ]; then
                    tmp=$(gen_random_passwd)
                fi

                if validate_passwd "$tmp"; then
                    in_dbpasswd="$tmp"
                fi
            done
        fi

        until [ "$in_wwwdomain" ]; do
            echo -n "Entrez le nom de domaine web (ex: foo.example.com) : "
            read -r tmp
            if validate_wwwdomain "$tmp"; then
                in_wwwdomain="$tmp"
            fi
        done

        if [ ${#PHP_VERSIONS[@]} -gt 0 ]; then
            until [ "$in_phpversion" ]; do
                echo -n "Entrez la version de PHP désirée parmis ${PHP_VERSIONS[*]} : "
                read -r tmp
                if validate_phpversion "$tmp"; then
                    in_phpversion="$tmp"
                fi
            done
        fi

        until [ "$in_mail" ]; do
            echo -n "Entrez votre adresse mail pour recevoir le mail de creation ($CONTACT_MAIL par défaut) : "
            read -r tmp
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
        while getopts hyp:m:P:w:l:k:u:g:U:r:q: opt; do
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
            r)
                in_phpversion=$OPTARG
                ;;
            q)
                in_quota=$OPTARG
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

        shift $((OPTIND - 1))
        if [ $# -ne 2 ]; then
            usage
            exit 1
        else
            in_login=$1
            in_wwwdomain=$2
            validate_login "$in_login" || exit 1
            [ -z "$in_passwd" ] && [ -z "$in_sshkey" ] && in_passwd=$(gen_random_passwd)
            [ -z "$in_sshkey" ] && ( validate_passwd "$in_passwd" || exit 1 )
            [ -n "$in_dbname" ] && ( validate_dbname "$in_dbname" || exit 1 )
            [ -z "$in_dbpasswd" ] && [ -n "$in_dbname" ] && in_dbpasswd=$(gen_random_passwd)
            [ -n "$in_dbname" ] && ( validate_passwd "$in_dbpasswd" || exit 1 )
            validate_wwwdomain "$in_wwwdomain" || exit 1
            [ -z "$in_mail" ] && in_mail=$CONTACT_MAIL
            validate_mail $in_mail || exit 1
            [ -n "$in_phpversion" ] && (validate_phpversion "$in_phpversion" || exit 1)
            [ -n "$in_quota" ] && (validate_quota "$in_quota" || exit 1)
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
    if [ ${#PHP_VERSIONS[@]} -gt 0 ]; then
        echo "version de PHP : $in_phpversion"
    fi
    echo "Quota : $in_quota"
    echo "Envoi du mail récapitulatif à : $in_mail"
    echo "----------------------------------------------"
    echo

    if [ -z "$force_confirm" ]; then
        echo -n "Confirmer la création ? [y/N] : "
        read -r tmp
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

# Some people forget to use the --follow-symlinks flag with sed(1),
# thus not carrying changes over to /etc/sites-available.
op_checkvhosts() {
    ln_vhosts_dir="$(sed 's/available/enabled/' <<< "$VHOST_PATH")"
    non_ln_vhosts="$(find "$ln_vhosts_dir"/* ! -type l)"

	while getopts f opt; do
		case "$opt" in
		f)
			apply=1
			;;
		?)
			usage
			exit 1
			;;
		esac
	done

	for ln_path in $non_ln_vhosts
    do
		vhost_name=$(basename "$ln_path")
		fix_conf="mv $ln_path $VHOST_PATH/$vhost_name"
		fix_ln="a2ensite ${vhost_name}.conf"

		if [[ -z "$apply" ]]; then
			echo "Suggested fixes for $vhost_name:"
			echo "diff $ln_path $VHOST_PATH/$vhost_name"
			echo "$fix_conf"
			echo "$fix_ln"
		else
			$fix_conf
			$fix_ln
		fi
	done
}

# Return web-add.sh version
op_version(){
    echo "$VERSION"
}

# Point d'entrée
arg_processing "$@"
