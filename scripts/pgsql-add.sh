#!/bin/bash

# TODO:
#   - check if database and/or role already exist

# To integrate it to web-add.sh, add this into web-add.local.sh:
#
#     read -p "Créer un compte PostgreSQL ? [Y|n] : " r
#     if [ "$r" != "n" ] || [ "$r" != "N" ]; then
#             /usr/share/scripts/pgsql-add.sh
#     fi

usage() {
    echo "usage: $0 [-h]" >&2
    echo "       This script will interactively ask for:" >&2
    echo "       - a database name" >&2
    echo "       - a user name" >&2
    echo "       - a password" >&2
    exit 1
}

if [ "$1" = -h ]; then
    usage
fi

set -e

read -p "Nom de la base de données : " base
read -p "Utilisateur propriétaire de la base : " user
read -s -p "Mot de passe (vide pour aléatoire) : " password
echo

if [ ! -n "$password" ]; then
    password=$(apg -n1 -m23 -Mncl)
    echo "> Mot de passe généré : $password"
fi

(
    if cd ~postgres; then
        su postgres -c "createuser \"${user}\""
        su postgres -c "createdb -O \"${user}\" \"${base}\""
        su postgres -c "psql -c \"ALTER USER \\\"${user}\\\" WITH PASSWORD '${password}'\"" > /dev/null
    fi
)

echo "Création de la base OK."
