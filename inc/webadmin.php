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
		$data_split = split(':', $data_line);
		array_push($vhost_list, array(
					'owner' => $data_split[0],
					'configid' => $data_split[1],
					'server_name' => $data_split[2],
					'server_alias' => $data_split[3])
			  );
	}

}
else {

	$cache=$conf['cache'];

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
