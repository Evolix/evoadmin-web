<?php

/**
 * Apache VirtualHost Management Page
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 *
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2
 *
 * @author Gregory Colpart <reg@evolix.fr>
 * @author Thomas Martin <tmartin@evolix.fr>
 * @author Sebastien Palma <spalma@evolix.fr>
 * @version 1.0
 */

require_once EVOADMIN_BASE . '../lib/bdd.php';
require_once EVOADMIN_BASE . '../lib/domain.php';

global $conf;

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

if (isset($_POST['delete-vhost'])) {
    $domain = $params[1];

    while(true) {
        // Errors handling
        if (empty($_POST['vhost-name'])) {
            print "<p>Veuillez entrer le nom du compte web à supprimer.</p>";
            printf ('<p><a href="%s">Retour</a></p>', $_SERVER['REDIRECT_URL']);
            break;
        }

        if ($_POST['vhost-name'] !== $domain) {
            print "Le nom de compte ne correspond pas.";
            printf ('<p><a href="%s">Retour</a></p>', $_SERVER['REDIRECT_URL']);
            break;
        }

        if (isset($_POST['vhost-delete-db']) && empty($_POST['vhost-dbname'])) {
            print "Veuillez spécifier un nom de base de données.";
            printf ('<p><a href="%s">Retour</a></p>', $_SERVER['REDIRECT_URL']);
            break;
        }

        // Shell arguments
        if (!empty($_POST['vhost-dbname']))
            $exec_cmd = "web-add.sh del -y " . $domain . " " . $_POST['vhost-dbname'];
        else
            $exec_cmd = "web-add.sh del -y " . $domain;

        // Execute script
        sudoexec($exec_cmd, $exec_output, $exec_return);

        // Deal with response code
        if ($exec_return == 0)
            print "<p>Compte supprimé.</p>";
        else
            print "<p>La suppression a échouée. Veuillez contacter votre administrateur.</p>";

        break;
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} else {
    include_once EVOADMIN_BASE . '../tpl/webadmin-delete.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
}

?>
