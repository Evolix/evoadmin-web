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

if (isset($params[2]) && $params[2] == "edit") {
    $redirect_url = "/webadmin/" . $params[1] . "/domain/";

    if (isset($params[3]) && $params[3] == "") http_redirect($redirect_url);

    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

    $form = new FormPage("Modification du ServerName", FALSE);
    $form->addField('domain_servername', new DomainInputFormField("ServerName", TRUE), $params[3]);
    $form->addField('previous_servername', new DomainInputFormField("", TRUE, TRUE), $params[3]);

    if (!empty($_POST)) {
        $form->isCurrentPage(TRUE);
        $form->initFields();

        if ($form->verify(TRUE)) {
            // TODO: Adapt the script for cluster mode
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

                $servername = array (
                    'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
                    'servername' => $form->getField('domain_servername')->getValue(),
                    'previous_servername' => $form->getField('previous_servername')->getValue(),
                );

                $account_name=$servername['domain'];
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
                        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $redirect_url);
                        print "</center>";
                    } else {
                        print "<center>";
                        printf ('<p>Echec dans la creation de l\'alias %s du domaine %s</p>', $serveralias['alias'], $serveralias['domain']);
                        printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $redirect_url);
                        print "</center>";
                    }
                } else {
                    print "<center>";
                    printf ('<p>Alias %s du domaine %s deja existant !</p>', $serveralias['alias'], $serveralias['domain']);
                    printf ('<p><a href="%s">Retour à la liste des alias</a></p>', $redirect_url);
                    print "</center>";

                }
            }
            else {
                $servername = array (
                    'domain' => $params[1],
                    'servername'  => $form->getField('domain_servername')->getValue(),
                    'previous_servername' => $form->getField('previous_servername')->getValue(),
                );

                $account_name=$servername['domain'];

                $is_servername_present = check_occurence_name($servername['servername']);

                if (!$is_servername_present) {
                  $exec_cmd = 'web-add.sh update-servername ' . $servername['domain'] . ' ' . $servername['servername'] . ' ' . $servername['previous_servername'];
                  sudoexec($exec_cmd, $exec_output, $exec_return);

                  if ($exec_return == 0) {
                      //domain_add($serveralias['alias'], gethostbyname($master) , false); TODO avec l'IP du load balancer
                      print "<center>";
                      printf ('<p>Le ServerName %s a bien été modifié</p>', $servername['servername']);
                      printf ('<p><a href="%s">Retour à la liste des ServerNames</a></p>', $redirect_url);
                      print "</center>";
                  }
                  else {
                      print "<center>";
                      printf ('<p>Echec dans la modification du ServerName %s</p>', $servername['servername']);
                      printf ('<p><a href="%s">Retour à la liste des ServerNames</a></p>', $redirect_url);
                      print "</center>";
                  }
                }
                else {
                  print "<center>";
                  printf ('<p>Echec dans la modification du ServerName %s</p>', $servername['servername']);
                  print  ('<p>Le domaine existe déjà dans d\'autres vhosts.');
                  printf ('<p><a href="%s">Retour à la liste des ServerNames</a></p>', $redirect_url);
                  print "</center>";
                }
            }
        } else {
          print "<h2>Modification du ServerName</h2><hr>";
          print "<form name=\"form-add\" id=\"form-add\" action=\"\" method=\"POST\">";
          print "   <fieldset>";
          print "        <legend>Modification du ServerName</legend>";
          print $form;
          print "        <p><input type=\"submit\" value=\"Modifier\"/></p>";
          print "     </fieldset>";
          print "</form>";
        }
    } else {
      print "<h2>Modification du ServerName</h2><hr>";
      print "<form name=\"form-add\" id=\"form-add\" action=\"\" method=\"POST\">";
      print "   <fieldset>";
      print "        <legend>Modification du ServerName</legend>";
      print $form;
      print "        <p><input type=\"submit\" value=\"Modifier\"/></p>";
      print "     </fieldset>";
      print "</form>";

    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';


} else {

    $domain = $params[1];
    $servername_list = array();

    // TODO: adapt for cluster mode
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

      $cmd = 'web-add.sh list-servername ' . $domain;

	    if(!is_superadmin()) {
	    	$cmd = sprintf('%s %s', $cmd, $_SESSION['user']);
	    }
	    sudoexec($cmd, $data_output, $exec_return);

	    foreach($data_output as $data_line) {
        array_push($servername_list, $data_line);
	    }
    }

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-servername.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

}

?>
