# evoadmin-web

Panel d'administration de serveur web et scripts shell.

Project leader : ?

## Versions

 * Stable Wheezy → git checkout wheezy
 * Stable Jessie → git checkout jessie
 * Stretch → master

## Installation

Via ansible avec le role packweb-apache. Le role webapps/evoadmin-web en dépendance se charge de l'installation de l'interface et de ses scripts.

### Manuelle

TODO


### Activation du mode Multi PHP avec des conteneurs LXC

Installer les paquets nécessaires :

~~~
# apt install lxc debootstrap
~~~

Modifier la configuration de LXC :

~~~
# cat /etc/lxc/default.conf

# Set the default network virtualization method.
lxc.network.type = none

# Mount /home into containers.
lxc.mount.entry = /home home none bind 0 0

# Only one tty is enough.
# This require that you disabled others tty ([2-6]) in systemd.
lxc.tty = 1

# Run 64bits containers
lxc.arch = x86_64

# Start containers on boot by default
lxc.start.auto = 1
~~~

Dans cette configuration, les containers LXC n'ont pas leur interface réseau virtualisée. Et /home de l'hôte est partagé dans les containers.

#### PHP 5.6

On installe un conteneur Debian Jessie :

~~~
# lxc-create --name php56 --template debian --bdev dir --logfile /var/log/lxc/lxc-php56.log --logpriority INFO -- --arch amd64 --release jessie
~~~

Puis on installe les paquets PHP 5.6 dans ce conteneur :

~~~
# lxc-start -n php56
# lxc-attach -n php56 apt install php5-fpm php5-cli php5-gd php5-imap php5-ldap php5-mcrypt php5-mysql php5-pgsql php-gettext php5-intl php5-curl php5-ssh2 libphp-phpmailer
~~~

#### PHP 7.0

On installe un conteneur Debian Stretch :

~~~
# lxc-create --name php70 --template debian --bdev dir --logfile /var/log/lxc/lxc-php70.log --logpriority INFO -- --arch amd64 --release stretch
~~~

Puis on installe les paquets PHP 7.0 dans ce conteneur :

~~~
# lxc-start -n php70
# lxc-attach -n php70 apt install php-fpm php-cli php-gd php-intl php-imap php-ldap php-mcrypt php-mysql php-pgsql php-gettext php-curl php-ssh2 composer libphp-phpmailer
~~~

#### PHP 7.3

On installe un conteneur Debian Stretch :

~~~
# lxc-create --name php73 --template debian --bdev dir --logfile /var/log/lxc/lxc-php73.log --logpriority INFO -- --arch amd64 --release stretch
~~~

Puis on installe les paquets PHP 7.3 dans ce conteneur :

~~~
# lxc-start -n php73
# lxc-attach -n php73

# apt-get update && apt-get install -y --no-install-recommends wget apt-transport-https ca-certificates gnupg
# curl https://packages.sury.org/php/apt.gpg | apt-key add
# echo "deb https://packages.sury.org/php/ stretch main" > /etc/apt/sources.list.d/sury.list
# apt-get update && apt-get install -y --no-install-recommends php7.3 php7.3-fpm php7.3-cli php7.3-curl php7.3-mysql php7.3-pgsql php7.3-ldap php7.3-imap php7.3-gd php-ssh2 php-gettext composer libphp-phpmailer
~~~

#### Pour toutes les versions de PHP

Dans les containers, il faut ajouter le fichier **z-evolinux-defaults.ini** dans le dossier **conf.d** des réglages de PHP FPM et CLI

> Pour PHP5 **/etc/php5/fpm/conf.d/z-evolinux-defaults.ini** et  **/etc/php5/cli/conf.d/z-evolinux-defaults.ini**
>
> Pour PHP7.0  **/etc/php/7.0/fpm/conf.d/z-evolinux-defaults.ini** et  **/etc/php/7.0/cli/conf.d/z-evolinux-defaults.ini**
>
> Pour PHP7.3  **/etc/php/7.3/fpm/conf.d/z-evolinux-defaults.ini** et **/etc/php/7.3/cli/conf.d/z-evolinux-defaults.ini**

~~~
[PHP]
short_open_tag = Off
expose_php = Off
display_errors = Off
log_errors = On
html_errors = Off
allow_url_fopen = Off
disable_functions = exec,shell-exec,system,passthru,putenv,popen
~~~

Après cela, il faut redémarrer FPM

~~~
# lxc-attach -n php56 /etc/init.d/php5-fpm restart
# lxc-attach -n php70 /etc/init.d/php7.0-fpm restart
# lxc-attach -n php73 /etc/init.d/php7.3-fpm restart
~~~

Une fois les conteneurs installés, il faut configurer evoadmin-web pour lui indiquer les versions disponibles de PHP dans **/etc/evolinux/web-add.conf** (pour *web-add.sh*) et dans  **/home/evoadmin/www/conf/config.local.php** pour l'interface web

~~~
# cat /etc/evolinux/web-add.conf
#(...)
PHP_VERSIONS=(56 70 73)
#(...)
~~~

~~~
# cat /home/evoadmin/www/conf/config.local.php
// (...)
$localconf['php_versions'] = array(70, 73);
// (...)
~~~

#### Apache

Il est nécessaire d'activer le mod proxy pour apache2 si ce n'a pas déjà été fait :

~~~
# a2enmod proxy_fcgi
# systemctl restart apache2.service
~~~

Si vous rencontrez l'erreur "File not found" avec les fichiers php, bien vérifier que le rootfs des conteneurs est en 755 :

~~~
# chmod 755 /var/lib/lxc/php56/rootfs
# chmod 755 /var/lib/lxc/php70/rootfs
# chmod 755 /var/lib/lxc/php73/rootfs
~~~

#### Email

Pour envoyer des mails, on peut installer **ssmtp** qui va forwarder les mails du conteneur vers l'hôte (à faire par conteneur via lxc-attach) :

~~~
# apt install ssmtp
~~~

Editer **/etc/ssmtp/ssmtp.conf** (remplacer example.com par le hostname complet de votre machine) :

~~~
root=postmaster
mailhub=127.0.0.1
hostname=example.com
FromLineOverride=YES
~~~

#### PHP-CLI


~~~
$ cat /usr/local/bin/exec73
#!/bin/bash

php_cmd=$(printf "/usr/bin/php %q" "$@" )
lxc-attach -n php73 -- su - "$SUDO_USER" -c "$php_cmd"
~~~

Il faut ensuite s'assurer que ce script peut être exécuté via sudo.



## Méthodes de collaboration

Lire le fichier GUIDELINES.

Chaque version stable a le nom de la version Debian dans une branche. (Wheezy, Jessie, …)
On ne touche pas à ces branches, sauf pour corriger un bug critique,
qu'on appellera dans le commit "Hotfix #IDBugRedmine. Description du commit/bug".
Le project leader se charge de merger les futures version (wheezy-dev, jessie-dev, …)
dans la branche stable et de faire un changelog.

Chaque version de redmine est prévu à l'avance dans la roadmap de Redmine.
Quand une version de développement est terminé (tout les bugs fermés),
on utilisera les tags pour se repérer dans l'historique GIT.
Puis une fois que la version est décrété stable, elle sera mergé dans la branche stable.

Pour travailler sur une version spécifique, il faut travailler dans la branche "$release".
Voir les branches sur le remote :

```
$ git branch -r
  origin/jessie-dev
  origin/wheezy-dev
  origin/wheezy
```

Il suffira de « checkout » dessus, et commencer à commiter. Les commits doivent
être nommés "Implement #IDBugRedmine. Description feature." ou
"Fix #IDBugRedmine. Description correction du bug.".

```
$ git checkout wheezy-dev
[…] hack hack […]
$ git commit
$ git push
```

## Licence

Evoadmin-web est un projet [Evolix](https://evolix.com) et est distribué sour licence AGPLv3, voir le fichier [LICENSE](LICENSE) pour les détails.
