From: Equipe Evolix <equipe@evolix.fr>
To: RCPTTO
Bcc: alert3@evolix.fr
Subject: Parametres hebergement web : LOGIN

Bonjour,

Votre compte d'hebergement web a ete cree.

**********************************
* CONNEXION SFTP/SSH
**********************************

NOM DU SERVEUR : %SERVER_NAME%
USER : LOGIN
PASSWORD : PASSE1

*****************************************
* Details sur l'environnement NginX/PHP
*****************************************

URL du site :
http://SERVERNAME

Repertoire de connexion : HOME_DIR/LOGIN/
Repertoire pour site web : HOME_DIR/LOGIN/www/

PHP tourne en www-data:www-data c'est-a-dire qu'il a acces
uniquement *en lecture* aux differents fichiers/repertoires (a condition
d'avoir 'g=rx' sur les repertoires et 'g=r' sur les fichiers ce qui est le
comportement par defaut).

Lorsqu'on a besoin d'autoriser *l'ecriture* pour certains fichiers/repertoires,
il suffit d'ajouter le droit 'g+w'.

***********************************
* MySQL
***********************************

SERVEUR : 127.0.0.1
PORT DU SERVEUR : 3306
USER : LOGIN
PASSWORD : PASSE2
NOM BASE : DBNAME
URL interface d'admin :
%PMA_URL%

***********************************
* Rappels divers
***********************************

Votre nom de domaine doit etre configure pour pointer sur l'adresse IP
(enregistrement DNS A) ou etre un alias de  (enregistrement DNS CNAME).

Si vous avez besoin de faire des tests, vous devez ajouter la ligne suivante au
fichier "/etc/hosts" sous Linux/Unix ou au fichier "system32\drivers\etc\hosts"
sous Windows :
%SERVER_ADDR% SERVERNAME

Attention, par defaut, toutes les connexions vers l'exterieur sont bloquees. Si
vous avez besoin de recuperer des donnees a l'exterieur (flux RSS, BDD externe,
etc.), contactez nous afin de mettre en oeuvre les autorisations necessaires.

Si vous desirez mettre en place des parametres particuliers pour votre site
(PHP, etc.) ou pour tout autre demande (scripts en crontab, etc.), n'hesitez
pas a nous contacter a l'adresse %MAIL_STANDARD% (ou %MAIL_URGENT% si
votre demande est urgente).

Cordialement,
-- 
Equipe Evolix <equipe@evolix.fr>
Evolix http://www.evolix.fr/
