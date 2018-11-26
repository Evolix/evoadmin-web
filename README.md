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

## Paquet

TODO

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