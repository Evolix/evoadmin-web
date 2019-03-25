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

if (isset($_GET['del']) ) {

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

    $alias = htmlspecialchars($_GET['del']);

    if (isset($_GET['modif']) && ($_GET['modif'] == 'yes')) {

        print "<center>";
        print "<p>Suppression de $alias...</p>";


        $serveralias = array (
            'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
            'alias'  => $alias
        );

        if ($conf['cluster']) {

            if (is_mcluster_mode()) {
                // If the user has not yet selected a cluster, redirect-it to home page.
                if (empty($_SESSION['cluster'])) {
                    http_redirect('/');
                }
                $cache = str_replace('%cluster_name%', $_SESSION['cluster'], $conf['cache']);
            }
            else {
                $cache = $conf['cache'];
            }
            $bdd = new bdd();
            $bdd->open($cache);

            $account_name=$serveralias['domain'];
            $account = $bdd->get_account($account_name);

            if (sizeof($account) == 0)
                  die("Anomalie... Contactez votre administrateur.");

            $master = $bdd->get_server_from_roleid($account['id_master']);
            $slave = $bdd->get_server_from_roleid($account['id_slave']);

            /* web-add-cluster addalias */
            $exec_cmd = 'web-add-cluster.sh del-alias '.$serveralias['domain'].' '.$serveralias['alias'].' '.$master.' '.$slave;
            sudoexec($exec_cmd, $exec_output, $exec_return);

            if ($exec_return == 0) {
                if (! $bdd->del_serveralias($serveralias)) 
                    print "<p>La suppression a échouée. Veuillez contacter votre administrateur.</p>";
                printf ('<p>Alias %s est supprimé.</p>', $serveralias['alias']);
            } else 
                print "<p>La suppression a échouée. Veuillez contacter votre administrateur.</p>";

        }
        else {
            $exec_cmd = 'web-add.sh del-alias ' . $serveralias['domain'] . ' ' . $serveralias['alias'];
            sudoexec($exec_cmd, $exec_output, $exec_return);
            if ($exec_return == 0) {
                printf ('<p>Alias %s est supprimé.</p>', $serveralias['alias']);
            } else 
                print "<p>La suppression a échouée. Veuillez contacter votre administrateur.</p>";

        }
        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
        print "</center>";

    } else if ( ! isset($_GET['modif']) ) {

        print "<center>";
        print "<p>Confirmez vous la suppression de $alias ?</p>";
        printf ('<p><a href="%s?del=%s&modif=yes">Confirmer la suppression</a></p>', $_SERVER['REDIRECT_URL'], $alias);
        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
        print "</center>";
    }


    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} else if (isset($_GET['add']) ) {

    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

        $form = new FormPage("Ajout d'un alias", FALSE);
        $form->addField('domain_alias', new DomainInputFormField("Alias", TRUE));

        if (!empty($_POST)) {
            $form->isCurrentPage(TRUE);
            $form->initFields();

            if ($form->verify(TRUE)) {
                if ($conf['cluster']) {
                    if (is_mcluster_mode()) {
                        // If the user has not yet selected a cluster, redirect-it to home page.
                        if (empty($_SESSION['cluster'])) {
                            http_redirect('/');
                        }
                        $cache = str_replace('%cluster_name%', $_SESSION['cluster'], $conf['cache']);
                    }
                    else {
                        $cache = $conf['cache'];
                    }
                    $bdd = new bdd();
                    $bdd->open($cache);

                    $serveralias = array (
                        'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
                        'alias'  => $form->getField('domain_alias')->getValue(),
                    );
                    
                    $account_name=$serveralias['domain'];
                    $account = $bdd->get_account($account_name);


                    if (sizeof($account) == 0)
                        die("Anomalie... Contactez votre administrateur.");

                    $master = $bdd->get_server_from_roleid($account['id_master']);
                    $slave = $bdd->get_server_from_roleid($account['id_slave']);


                    if ( $bdd->is_serveralias( $account_name, $serveralias['alias'] ) == 0  ) {

                        /* web-add-cluster addalias */
                        $exec_cmd = 'web-add-cluster.sh add-alias '.$serveralias['domain'].' '.$serveralias['alias'].' '.$master.' '.$slave;
                        sudoexec($exec_cmd, $exec_output, $exec_return);



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
                            printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                            print "</center>";
                        } else {
                            print "<center>";
                            printf ('<p>Echec dans la creation de l\'alias %s du domaine %s</p>', $serveralias['alias'], $serveralias['domain']);
                            printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                            print "</center>";
                        }
                    } else {
                        print "<center>";
                        printf ('<p>Alias %s du domaine %s deja existant !</p>', $serveralias['alias'], $serveralias['domain']);
                        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                        print "</center>";

                    }
                }
                else {
                    $serveralias = array (
                        'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
                        'alias'  => $form->getField('domain_alias')->getValue(),
                    );

                    $account_name=$serveralias['domain'];

                    $check_occurence_cmd = 'web-add.sh check-occurence ' . $serveralias['alias'];
                    sudoexec($check_occurence_cmd, $check_occurence_output, $check_occurence_return);

                    // Check if the name is present in vhosts already, returns 1 if no
                    if ($check_occurence_return == 1) {
                      $exec_cmd = 'web-add.sh add-alias ' . $serveralias['domain'] . ' ' . $serveralias['alias'];
                      sudoexec($exec_cmd, $exec_output, $exec_return);
                      if ($exec_return == 0) {
                          //domain_add($serveralias['alias'], gethostbyname($master) , false); TODO avec l'IP du load balancer
                          print "<center>";
                          printf ('<p>L\'alias %s du domaine %s a bien été créé</p>', $serveralias['alias'], $serveralias['domain']);
                          printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                          print "</center>";
                      }
                      else {
                          print "<center>";
                          printf ('<p>Echec dans la creation de l\'alias %s du domaine %s</p>', $serveralias['alias'], $serveralias['domain']);
                          printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                          print "</center>";
                      }
                    }
                    else {
                      print "<center>";
                      printf ('<p>Echec dans la creation de l\'alias %s du domaine %s</p>', $serveralias['alias'], $serveralias['domain']);
                      print  ('<p>L\'alias existe dans d\'autres vhosts.');
                      printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $_SERVER['REDIRECT_URL']);
                      print "</center>";
                    }
                }
            }
            else {
              print "<h2>Ajout d'un serveralias</h2><hr>";
                    print "<form name=\"form-add\" id=\"form-add\" action=\"\" method=\"POST\">";
                    print "   <fieldset>";
                    print "        <legend>Ajout d'un serveralias</legend>";
                    print $form;
                    print "        <p><input type=\"submit\" value=\"Créer\"/></p>";
                    print "     </fieldset>";
                    print "</form>";

            }
        } else {
			print "<h2>Ajout d'un serveralias</h2><hr>";
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
        if (is_mcluster_mode()) {
            // If the user has not yet selected a cluster, redirect-it to home page.
            if (empty($_SESSION['cluster'])) {
                http_redirect('/');
            }
            $cache = str_replace('%cluster_name%', $_SESSION['cluster'], $conf['cache']);
        }
        else {
            $cache = $conf['cache'];
        }

        $alias_list = array();

        /* parcours de la table Serveralias */
        $bdd = new bdd();
        $bdd->open($cache);

        $alias_list = $bdd->list_serveralias($domain);
    }
    else {
        $cmd = 'web-add.sh list-vhost';
	    if(!is_superadmin()) {
	    	$cmd = sprintf('%s %s', $cmd, $_SESSION['user']);
	    }
	    sudoexec($cmd, $data_output, $exec_return);

	    /* Récupération de cette liste dans le tableau $vhost_list */
	    $vhost_list = array();
	    foreach($data_output as $data_line) {
	    	$data_split = explode(':', $data_line);
            if ($data_split[0] == $domain && $data_split[3] != '') {
                $alias_split = explode(',', $data_split[3]);
                foreach($alias_split as $alias) {
                    $alias_array['alias'] = $alias;
                    array_push($alias_list, $alias_array);
                }
            }
	    }
    }

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-edit.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

}

?>
