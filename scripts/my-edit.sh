#!/bin/bash

if [ $# -ne 2 ]; then
    echo "Usage: $0 passwd <login>"
    echo "Usage: $0 adddb <login>"
    echo "Usage: $0 dropdb <login>"
    exit 1
fi

if [ "$1" = "passwd" ]; then
    login="$2"
    read -s -p "New password for ${login}: " password1
    echo
    read -s -p "New password for $login (confirm): " password2

    if [ -z "$password1" ] || [ "$password1" != "$password2" ]; then
        echo "Incorrect password."
        exit 1
    fi

    mysql -e "SET PASSWORD FOR '${login}'@'%' = PASSWORD('${password1}');"
fi

if [ "$1" = "adddb" ]; then
    login="$2"
    read -p "New database name for ${login}: " database

    if [ -z "$database" ]; then
        echo "Database name cannot be empty."
        exit 1
    fi

    mysql -e "CREATE DATABASE ${database};"
    mysql -e "GRANT ALL ON ${database}.* TO '${login}'@'%';"
fi

if [ "$1" = "dropdb" ]; then
    login="$2"
    read -p "Drop database name for ${login}: " database

    if [ -z "$database" ]; then
        echo "Database name cannot be empty."
        exit 1
    fi

    mysql -e "DROP DATABASE ${database};"
    mysql -e "REVOKE ALL PRIVILEGES ON ${database}.* FROM '${login}'@'%';"
fi
