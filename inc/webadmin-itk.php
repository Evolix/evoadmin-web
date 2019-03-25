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

var_dump($_SESSION);
if (isset($_GET['enable']) ) {
    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';


    # TODO: sanitize $_GET
    $servername = array (
        'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
        'servername'  => $_GET['enable']
    );

    $enable_cmd = 'web-add.sh enable-user-itk ' . $servername['servername'] . ' ' . $servername['domain'];

    sudoexec($enable_cmd, $enable_cmd_output, $enable_cmd_return);

    if ($enable_cmd_return == 0) {
      print 'Sécurité ITK activée.';
      printf ('<p><a href="%s">Retour à la gestion ITK</a></p>', $_SERVER['REDIRECT_URL']);
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';


}
elseif (isset($_GET['disable']) ) {
    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';


    # TODO: sanitize $_GET
    $servername = array (
        'domain' => htmlspecialchars(basename($_SERVER['REDIRECT_URL'])),
        'servername'  => $_GET['disable']
    );

    $disable_cmd = 'web-add.sh disable-user-itk ' . $servername['servername'] . ' ' . $servername['domain'];

    sudoexec($disable_cmd, $disable_cmd_output, $disable_cmd_return);

    if ($disable_cmd_return == 0) {
      print 'Sécurité ITK désactivée';
      printf ('<p><a href="%s">Retour à la gestion ITK</a></p>', $_SERVER['REDIRECT_URL']);
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';


}
else {

    $domain = $params[1];
    $data_list = array();

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

      # à revérifier (notamment gestion erreurs)
      if ($exec_return == 0) {
        foreach($data_output as $data_line) {
          $cmd_itk = 'web-add.sh list-user-itk ' . $data_line . ' ' . $domain;

          sudoexec($cmd_itk, $data_output_itk, $exec_return_itk);

          # on prend le premier résultat du tableau, ne fonctionne pas s'il y a plusieurs la même ligne ou des commentaires etc.
          array_push($data_list, ['servername' => $data_line, 'user' => $data_output_itk[0]]);
          unset($data_output_itk);  # reset variable pour éviter conflits
  	    }
      }
    }

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-itk.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

}

?>
