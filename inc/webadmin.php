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

/* Appel du script pour récupérer les vhost appartenant à l'utilisateur */

require_once EVOADMIN_BASE . '../lib/bdd.php';

global $conf;

if (!$conf['cluster']) {

    $cmd = 'web-add.sh list-vhost';

    if(!is_superadmin()) {
        $cmd = sprintf('%s %s', $cmd, $_SESSION['user']);
    }
    sudoexec($cmd, $data_output, $exec_return);

    /* Récupération de cette liste dans le tableau $vhost_list */
    $vhost_list = array();
    foreach($data_output as $data_line) {
        $data_split = explode(':', $data_line);

		if (strstr($data_split[4],'K')) {
			$taille_utilise  	= number_format(($data_split[4]/1024), 2, '.', '').'M';
			$taille_utilise_mo	= $taille_utilise;
			if ($taille_utilise >= 1024) {
				$taille_utilise = number_format(($taille_utilise/1024), 2, '.', '').'G';	
			}
		} else if ($data_split[4] >= 1024) {
			$taille_utilise_mo	= $data_split[4];
			$taille_utilise 	= number_format(($data_split[4]/1024), 2, '.', '').'G';	
		} else {
			$taille_utilise_mo	= $data_split[4];
			$taille_utilise 	= $data_split[4];
		}	
		
		$quota_bas_mo	= $data_split[5];		
		$quota_bas	= number_format(($data_split[5]/1024), 2, '.', '').'G';
		$quota_haut	= number_format(($data_split[6]/1024), 2, '.', '').'G';		
		$occupation	= number_format((($taille_utilise_mo/$quota_bas_mo)*100), 2, '.', '');
		if ($occupation >= 90) {
			$occupation = '<span style="color:red;font-weight:bold;">'.$occupation.'%</span>';
		} else if ($occupation >= 80) {
			$occupation = '<span style="color:MediumVioletRed;font-weight:bold;">'.$occupation.'%</span>';
		} else if ($occupation >= 70) {
			$occupation = '<span style="color:Fuchsia;font-weight:bold;">'.$occupation.'%</span>';
		} else {
			$occupation = $occupation.'%';
		}
		array_push($vhost_list, array(
					'owner' 	    => $data_split[0],
					'configid' 	    => $data_split[1],
					'server_name' 	=> $data_split[2],
					'server_alias' 	=> $data_split[3],
					'size' 		    => $taille_utilise,
					'quota_soft' 	=> $quota_bas,
					'quota_hard' 	=> $quota_haut,
					'occupation' 	=> $occupation,
					'php_version' 	=> $data_split[7],
					'is_enabled' 	=> $data_split[8])
			  );
    }

}
else {

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

    $vhost_list = array();

    /* parcours de la table account */
    $bdd=new bdd();
    $bdd->open($cache);

    $accounts = $bdd->list_accounts();

    if (!empty($accounts)) {
        foreach($accounts as $account) {
            
            $master = $bdd->get_server_from_roleid($account['id_master']);
            
            $slave = '';
            if  (!empty($account['id_slave']))
                $slave = $bdd->get_server_from_roleid($account['id_slave']);            
            
            array_push($vhost_list, array(
                'owner' => $account['name'],
                'server_name'=> $account['domain'],
                'bdd'   => $account['bdd'],
                'mail'  => $account['mail'],
                'replication'  => $account['replication'],
                'master' => $master,
                'slave'  => $slave)
            );  

        }
    }

}


include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
include_once EVOADMIN_BASE . '../tpl/webadmin.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

?>
