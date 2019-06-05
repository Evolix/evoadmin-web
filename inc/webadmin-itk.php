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

if (isset($params[2]) && $params[2] != "") {

    $redirect_url = "/webadmin/" . $params[1] . "/itk/";

    require_once EVOADMIN_BASE . '../evolibs/Form.php';

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

    $servername = array (
        'domain' => $params[1],
    );

    if ($params[2] == "enable") {
      $enable_cmd = 'web-add.sh enable-user-itk ' . $servername['domain'];

      sudoexec($enable_cmd, $enable_cmd_output, $enable_cmd_return);

      if ($enable_cmd_return == 0) {
        print 'Sécurité ITK activée.';
        printf ('<p><a href="%s">Retour à la gestion ITK</a></p>', $redirect_url);
      }
    }
    elseif ($params[2] == "disable") {
      $disable_cmd = 'web-add.sh disable-user-itk ' . $servername['domain'];

      sudoexec($disable_cmd, $disable_cmd_output, $disable_cmd_return);

      if ($disable_cmd_return == 0) {
        print 'Sécurité ITK désactivée';
        printf ('<p><a href="%s">Retour à la gestion ITK</a></p>', $redirect_url);
      }
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
} else {
    $domain = $params[1];

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
          $cmd_itk = 'web-add.sh list-user-itk ' . $domain;

          sudoexec($cmd_itk, $data_output_itk, $exec_return_itk);

          $user_itk = $data_output_itk[0];
    }

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-itk.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
}

?>
