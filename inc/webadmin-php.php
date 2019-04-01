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
require_once EVOADMIN_BASE . '../evolibs/Form.php';

global $conf;

$form = new FormPage("Modification de la version de PHP", FALSE);
$form->addField('php_version', new SelectFormField("Nouvelle version de PHP", True, $conf['php_versions']));

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

$domain = $params[1];

// TODO: adapt for cluster mode
if ($conf['cluster']) {
    print "<center>";
    printf ('<h2>ERREUR</h2>');
    printf ('<p>Cette action n\'est pas encore supportée en mode cluster</p>');
    printf ('<p><a href="/webadmin">Retour à liste des comptes</a></p>');
    print "</center>";
}
else {
    $cmd = 'web-add.sh list-vhost ' . escapeshellarg($domain);
    sudoexec($cmd, $data_output, $exec_return);

    $data_split = explode(':', $data_output[0]);
    $current_PHP_version = $data_split[7];

    if (!empty($_POST)) {
        $form->isCurrentPage(TRUE);
        $form->initFields();

        if ($form->verify(TRUE)) {
            if (isset($conf['php_versions'][$form->getField('php_version')->getValue()]))
            {
                $selected_php_version = $conf['php_versions'][$form->getField('php_version')->getValue()];
                $exec_cmd = 'web-add.sh setphpversion '.escapeshellarg($domain).' '.escapeshellarg($selected_php_version);
                sudoexec($exec_cmd, $exec_output, $exec_return);

                if ($exec_return == 0) {

                    print "<center>";
                    printf ('<p>La version de PHP a bien été modifiée</p>');
                    printf ('<p><a href="/webadmin">Retour à liste des comptes</a></p>');
                    print "</center>";

                }
                else {
                    print "<center>";
                    printf ('<h2>ERREUR</h2>');
                    printf ('<p>Une erreur inattendue s\'est produite </p>');

                        if ($conf['debug'] == TRUE) {
                            print '<pre>';
                            foreach($exec_output as $exec_line) {
                                printf("%s\n", $exec_line);
                            }
                            print '</pre>';
                        }

                    printf ('<p><a href="/webadmin">Retour à liste des comptes</a></p>');
                    print "</center>";
                }
            }
            else {
                include_once EVOADMIN_BASE . '../tpl/webadmin-php.tpl.php';
            }
        }
        else {
            include_once EVOADMIN_BASE . '../tpl/webadmin-php.tpl.php';
        }
    }
    else {
        include_once EVOADMIN_BASE . '../tpl/webadmin-php.tpl.php';
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
}
