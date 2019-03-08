# evoadmin-web

Panel d'administration de serveur web.


Project leader : ?

## Cloner le repo

```
git clone ssh://git@git.evolix.org/evoadmin-web.git
```

## Versions

 * Stable Squeeze → Déprécié. Dispo sur projet privé.
 * Stable Wheezy → git checkout wheezy
 * Stable Jessie → N’existe pas encore.
 * Dev Jessie → git checkout jessie-dev


## Installation

Automatiquement via Evolinux.

### Manuelle

TODO

### Paquet

TODO

### Conteneurs LXC (Multi PHP)

Installer le paquet nécessaire :

~~~
apt install lxc debootstrap
~~~

Modifier la configuration de LXC :

~~~
# cat /etc/lxc/default.conf

# Set the default network virtualization method.
lxc.network.type = none

# Mount /home into containers.
# lxc.mount.entry = /home home none bind 0 0

# Only one tty is enough.
# This require that you disabled others tty ([2-6]) in systemd.
lxc.tty = 1

# Run 64bits containers
lxc.arch = x86_64

# Start containers on boot by default
lxc.start.auto = 1
~~~

#### PHP 5.6

On installe un conteneur Debian Jessie :

~~~
/usr/bin/lxc-create --name php56 --template debian --bdev dir --logfile /var/log/lxc/lxc-php56.log --logpriority INFO -- --arch amd64 --release jessie
~~~

Puis on installe les paquets PHP 5.6 dans ce conteneur :

~~~
lxc-attach -n php56 apt install php5-fpm php5-cli php5-gd php5-imap php5-ldap php5-mcrypt php5-mysql php5-pgsql php-gettext php5-intl php5-curl php5-ssh2 libphp-phpmailer
~~~

On configure ensuite PHP via les fichiers **/etc/php5/fpm/conf.d/z-evolinux-defaults.ini** et **/etc/php5/cli/conf.d/z-evolinux-defaults.ini** (dans le conteneur) :

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

Il ne reste plus qu'a redémarrer FPM :

~~~
lxc-attach -n php56 /etc/init.d/php5-fpm restart
~~~

#### PHP 7.0

On installe un conteneur Debian Stretch :

~~~
/usr/bin/lxc-create --name php70 --template debian --bdev dir --logfile /var/log/lxc/lxc-php70.log --logpriority INFO -- --arch amd64 --release stretch
~~~

Puis on installe les paquets PHP 7.0 dans ce conteneur :

~~~
lxc-attach -n php70 apt install php-fpm php-cli php-gd php-intl php-imap php-ldap php-mcrypt php-mysql php-pgsql php-gettext php-curl php-ssh2 composer libphp-phpmailer
~~~

On configure ensuite PHP via les fichiers **/etc/php/7.0/fpm/conf.d/z-evolinux-defaults.ini** et **/etc/php/7.0/cli/conf.d/z-evolinux-defaults.ini** :

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

Il ne reste plus qu'a redémarrer FPM :

~~~
lxc-attach -n php70 /etc/init.d/php7.0-fpm restart
~~~

#### Toutes versions

Pour envoyer des mails, on peut installer **ssmtp** qui va forwarder les mails du conteneur vers l'hôte (à faire par conteneur via lxc-attach) :

~~~
apt install ssmtp
~~~

Editer **/etc/ssmtp/ssmtp.conf** (remplacer example.com par le hostname complet de votre machine) :

~~~
root=postmaster
mailhub=127.0.0.1
hostname=example.com
FromLineOverride=YES
~~~

####

Une fois les conteneurs installés, il faut configurer evoadmin-web pour lui diniquer les versions disponibles de PHP dans **/etc/evolinux/web-add.conf** :

~~~
PHP_VERSIONS=(56 70)
~~~

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
