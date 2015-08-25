#!/bin/bash

# TODO:
#   - check if database and/or role already exist

# To integrate it to web-add.sh, add this into web-add.local.sh:
#
#     read -p "Créer un compte PostgreSQL ? [Y|n] : " r
#     if [ "$r" != "n" ] || [ "$r" != "N" ]; then
#             /usr/share/scripts/pgsql-add.sh
#     fi


set -e

read -p "Nom de la base de données : " base
read -p "Utilisateur propriétaire de la base : " user
read -s -p "Mot de passe (vide pour aléatoire) : " password
echo

if [ ! -n "$password" ]; then
    password=$(apg -n1 -E I0O)
    echo "> Mot de passe généré : $password"
fi

su postgres -c "psql -qc \"CREATE ROLE $user WITH PASSWORD '$password'\""
su postgres -c "psql -qc \"CREATE DATABASE $base WITH OWNER $user\""

echo "Creation de la base OK."
