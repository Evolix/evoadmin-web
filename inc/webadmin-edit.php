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

global $conf;

if (isset($_GET['del']) ) {

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

    $alias = htmlspecialchars($_GET['del']);

    if (isset($_GET['modif']) && ($_GET['modif'] == 'yes')) {

        print "<center>";
        print "<p>Suppression de $alias...</p>";


        $serveralias = array (
            'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_SCRIPT_URL'])),
            'alias'  => $alias
        );

        if ($conf['cluster']) {

            $cache = $conf['cache'];
            $bdd = new bdd();
            $bdd->open($cache);

            if (! $bdd->del_serveralias($serveralias)) 
                print "<p>La suppression a échouée. Veuillez contacter votre administrateur.</p>";
        }
        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_SCRIPT_URL']);
        print "</center>";

    } else if ( ! isset($_GET['modif']) ) {

        print "<center>";
        print "<p>Confirmez vous la suppression de $alias ?</p>";
        printf ('<p><a href="%s?del=%s&modif=yes">Confirmer la suppression</a></p>', $_SERVER['REDIRECT_SCRIPT_URL'], $alias);
        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_SCRIPT_URL']);
        print "</center>";
    }


    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} else if (isset($_GET['add']) ) {

    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

        $form = new FormPage("Ajout d'un alias", FALSE);
        $form->addField('domain_alias', new TextInputFormField("Alias", FALSE));

        if (!empty($_POST)) {
            $form->isCurrentPage(TRUE);
            $form->initFields();

            if ($form->verify(TRUE)) {
                if ($conf['cluster']) {
                    $cache = $conf['cache'];
                    $bdd = new bdd();
                    $bdd->open($cache);

                    $serveralias = array (
                        'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_SCRIPT_URL'])),
                        'alias'  => $form->getField('domain_alias')->getValue(),
                    );
                    
                    $account_name=$serveralias['domain'];
                    $account = $bdd->get_account($account_name);

                    if (sizeof($account) == 0)
                        die("Anomalie... Contactez votre administrateur.");

                    $master = $bdd->get_server_from_roleid($account['id_master']);
                    $slave = $bdd->get_server_from_roleid($account['id_slave']);

                    /* web-add-cluster addalias */
                    $exec_cmd = 'web-add-cluster.sh add-alias '.$serveralias['domain'].' '.$serveralias['alias'].' '.$master.' '.$slave;
                    sudoexec($exec_cmd, $exec_output, $exec_return);

                    /* bind add */
                    $exec_cmd = 'bind-add-ng.sh';
                    $exec_cmd .= ' -a '. gethostbyname($master) .' '.$serveralias['alias'];
                    sudoexec($exec_cmd, $exec_output, $exec_return2);
                 
                    $exec_return |= $exec_return2;

                    if ($exec_return == 0) {
                        /* Ajout BDD */
                        $bdd->add_serveralias($serveralias);

                        domain_add($serveralias['alias'], gethostbyname($master) , false);

                        # Si le compte en question est en replication temps
                        # reel, il faut faire un restart manuel de lsyncd
                        # pour prendre en compte le nouveau domaine.
                        if ($account['replication'] == "realtime") {
                            mail('tech@evolix.fr', "[TAF] Redemarrer lsyncd sur $master", wordwrap('killer tous les processus lsyncd lancé par vmail pour le compte '.$account['name'].' et les relancer (cf. la ligne correspondante à ce compte dans la crontab de vmail).\n', 70));
                        }

                        print "<center>";
                        printf ('<p>L\'alias %s du domaine %s a bien été créé</p>', $serveralias['alias'], $serveralias['domain']);
                        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_SCRIPT_URL']);
                        print "</center>";
                    } else {
                        print "<center>";
                        printf ('<p>Echec dans la creation de l\'alias %s du domaine %s</p>', $serveralias['alias'], $serveralias['domain']);
                        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_SCRIPT_URL']);
                        print "</center>";
                    }
                }
            }
        } else {

            print "<form name=\"form-add\" id=\"form-add\" action=\"\" method=\"POST\">";
            print "   <fieldset>";
            print "        <legend>Ajout d'un serveralias</legend>";
            print $form;
            print "        <p><input type=\"submit\" value=\"Créer\"/></p>";
            print "     </fieldset>";
            print "</form>";

        }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';


} else {

    $domain = $params[1];
    $alias_list = array();

    if ($conf['cluster']) {

        $cache = $conf['cache'];

        $alias_list = array();

        /* parcours de la table Serveralias */
        $bdd = new bdd();
        $bdd->open($cache);

        $alias_list = $bdd->list_serveralias($domain);
    }

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-edit.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

}

?>
