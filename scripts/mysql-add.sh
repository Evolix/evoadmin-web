#!/bin/sh

echo "Ajout d'un compte/base MySQL"
echo "Entrez le nom de la nouvelle base MySQL"
read base

echo "Entrez le login qui aura tous les droits sur cette base"
echo "(Vous pouvez entrer un login existant)"
read login

echo -n "Cet utilisateur existe-t-il déjà ? [y|N] "
read confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Attention, si l'utilisateur existe, il sera ecrase !"
    echo "Entrez le mot de passe MySQL (ou vide pour aléatoire) :"
    read -s passe2
    echo ""

    if [ ! -n "$passe2" ]; then
        echo "Génération du mot de passe aléatoire. "
        passe2=$(apg -n1 -E I0O)
    fi

    mysql -uroot << END_SCRIPT
CREATE DATABASE \`$base\`;
GRANT ALL PRIVILEGES ON \`$base\`.* TO \`$login\`@localhost IDENTIFIED BY "$passe2";
FLUSH PRIVILEGES;
END_SCRIPT

else

    mysql -uroot << END_SCRIPT
GRANT ALL PRIVILEGES ON \`$base\`.* TO \`$login\`@localhost;
CREATE DATABASE \`$base\`;
FLUSH PRIVILEGES;
END_SCRIPT

fi

echo "Si aucune erreur, création de la base MySQL $base OK"

