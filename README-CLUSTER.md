INSTALLATION & CONFIGURATION EVOADMIN EN MODE CLUSTER
=====================================================


1. Infra type à mettre en place
-------------------------------

                    .---.
                    |   | adm
                    '-.-'
         .------------+------------.
         v            v            v
       .---.        .---.        .---.
       |   | www00  |   | www01  |   | wwwnn
       '---'        '---'        '---'

  * adm : machine hébergeant evoadmin-cluster. Doit pouvoir se connecter en
    root sur tous les frontaux ;

  * wwwXX : frontaux configurés en tant que pack-web/pack-mail.


2. Configuration sur la machine d'admin
---------------------------------------

  * Récupérer le code ;
  * Créer le virtual host pointant dans htdocs/ ;
  * Initialiser la base de données SQLite :
  * copier le contenu de scripts/ du dépôt dans /usr/share/scripts/evoadmin/.

3. Configuration des frontaux
-----------------------------

  * Configurer un pack-web et pack-mail+evoadmin-mail classique ;
  * ouvrir l'accès SSH entre tous les frontaux (nécessaire pour les
    réplications) ;
  * Ouvrir l'accès SSH en root depuis la machine d'admin vers chaque frontal ;
  * Copier les scripts scripts/sync-master-to-slave* du dépôt dans
    /opt/evocluster/, puis `chmod -R 755 /opt/evocluster/`.

Cas de la réplication MySQL :
  * Configurer la réplication MySQL de manière standard (comme décrit ici [1]) ;
  * Rajouter sur la machine slave dans le my.cnf la directive replicate-do-db,
    qui contiendra la liste des bases des comptes à répliquer.

[1] http://trac.evolix.net/infogerance/wiki/HowtoMySQL#R%C3%A9plicationMySQL

4. Configuration d'Evoadmin
---------------------------

Afin d'activer Evoadmin en mode Cluster, remplir les variables suivantes dans 
/home/evoadmin/www/conf/config.local.php :

$localconf['cluster'] = TRUE;
$localconf['cache'] = '/home/evoadmin/www/cache.sqlite'; // cache sqlite
$localconf['servers'] = array('www00', 'www01', ..., 'wwwnn');

Créer le cache après avoir renseigner le fichier de conf :
# cd /home/evoadmin/www/bin && php init_cache.php
www00 added in cache
[...]
Cache initialisé
# chown www-evoadmin:evoadmin /home/evoadmin/www/cache.sqlite
# chmod ug+rx /home/evoadmin/www/cache.sqlite

S'assurer que /home/evoadmin/www appartient bien à www-evoadmin:evoadmin.

Il est possible de dumper le cache grâce à au script list_domain.php et de 
rajouter des serveurs dans le cache grâce au script add_server.php :
# php add_server.php www42


