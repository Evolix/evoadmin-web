<?php

/**
 * Gestion des comptes utilisateurs
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

require_once EVOADMIN_BASE . '../evolibs/Form.php';
require_once EVOADMIN_BASE . '../lib/bdd.php';

global $conf;

$servers=$conf['servers'];
$servers_slave=$conf['servers'];

$servers_slave[]='Aucun';
$cache=$conf['cache'];


function domain_add($name, $IP, $with_mxs, $gmail=false) {

    $exec_cmd = 'bind-add-ng.sh';

    if ( $with_mxs == true ) {
        /* Quai13 specific: use Gmail MXs if wanted */
        if ( $gmail ) {
            $exec_cmd .= ' -m ASPMX.L.GOOGLE.com.,10';
            $exec_cmd .= ' -m ALT1.ASPMX.L.GOOGLE.com.,20';
            $exec_cmd .= ' -m ALT2.ASPMX.L.GOOGLE.com.,20';
            $exec_cmd .= ' -m ASPMX2.GOOGLEMAIL.com.,30';
            $exec_cmd .= ' -m ASPMX3.GOOGLEMAIL.com.,30';
        }
        else {
            $exec_cmd .= ' -m mail,10';
            $exec_cmd .= ' -m backup.quai13.net.,20';
        }
        mail($conf['techmail'], '[TAF] Ajouter '.$name.' sur le serveur de mail', wordwrap('Ajouter le domaine '.$name.' à la directive relay_domains dans le fichier /etc/postfix/main.cf sur le serveur mail, pour mettre en place le MX secondaire du domaine.', 70));
    }

    $exec_cmd .= " -a $IP $name";

    //echo $exec_cmd."\n";
    sudoexec($exec_cmd, $exec_output, $exec_return);
    return array($exec_cmd, $exec_return, $exec_output);
}

function web_add($form, $admin_mail) {

    $exec_cmd = 'web-add.sh add -y';


    if(!$form->getField('password_random')->getValue()) {
        $exec_cmd .= sprintf(' -p %s',
                             $form->getField('password')->getValue());
    }

    /* Ajout des options spécifiques à MySQL si nécessaire */
    if($form->getField('mysql_db')->getValue()) {
        $exec_cmd .= sprintf(' -m %s',
                             $form->getField('mysql_dbname')->getValue());

        if(!$form->getField('mysql_password_random')->getValue()) {
            $exec_cmd .= sprintf(' -P %s',
                                $form->getField('mysql_password')->getValue());
        }
    }

    $exec_cmd .= sprintf(' -l %s %s %s 2>&1', $admin_mail,
        $form->getField('username')->getValue(), 
        $form->getField('domain')->getValue());

    sudoexec($exec_cmd, $exec_output, $exec_return);

    return array($exec_cmd, $exec_return, $exec_output);
}

function web_add_cluster($form, $admin_mail) {
    global $cache;
    global $conf;

    $exec_cmd = 'web-add-cluster.sh add';

    $realtime=0;
	
    /* array account with infos for sqlite cache */
    $account = array();

    if(!$form->getField('password_random')->getValue()) {
        $exec_cmd .= sprintf(' -p %s',
                             $form->getField('password')->getValue());
    }

    /* Ajout des options spécifiques à MySQL si nécessaire */
    if($form->getField('mysql_db')->getValue()) {
        $exec_cmd .= sprintf(' -m %s',
                             $form->getField('mysql_dbname')->getValue());

        if(!$form->getField('mysql_password_random')->getValue()) {
            $exec_cmd .= sprintf(' -P %s',
                                $form->getField('mysql_password')->getValue());
        }

        $account['bdd'] = $form->getField('mysql_dbname')->getValue();

    }
    else $account['bdd'] = '' ;

    /* Replication */
    $value_cluster_mode = $form->getField('cluster_mode')->getValue();

    /* decode $value */
    // noreplication :        1-x
    // postponedreplication : 2-x-y-m
    // immediate :            3-x-y

    list($crepl, $cmaster, $cslave, $cmode) = explode('-', $value_cluster_mode);

    switch ($crepl) {
        case 1:  // noreplication, single server
            $account['replication'] = '';
            $realtime = 1;// hack
            $master = $conf['noreplication'][$cmaster];
            $slave="null";

            break;
        case 2:  // postponed
            $mode = $conf['postponedreplication_mode'][$cmode];
            if (isset($conf[$mode])) {
                $master = $conf[$mode][$cmaster];
                $slave = $conf[$mode][$cslave];
            }
            
            $master = $conf['postponedreplication'][$cmaster];
            $slave = $conf['postponedreplication'][$cslave];

            // en dur pour le moment à améliorer plus tard ...
            switch ($cmode) {
                case 0: // 3/day
                    $cron_freq_opt=8;
                    $cron_unit_opt='hour';
                    break;
                case 1: // 1/day
                    $cron_freq_opt=24;
                    $cron_unit_opt='hour';
                    break;
                case 2: // 1/hour
                    $cron_freq_opt=1;       /* 1/hour */
                    $cron_unit_opt='hour';
                    break;
            }
            
            $account['replication'] = $conf['postponedreplication_mode'][$cmode];
            
            $exec_cmd .= sprintf(' -f %d -c %s', $cron_freq_opt, $cron_unit_opt);
            
            break;
        case 3: // realtime
            $realtime = 1;
            $account['replication'] = 'realtime';

            $master = $conf['immediatereplication'][$cmaster];
            $slave = $conf['immediatereplication'][$cslave];
            break;
    }

    $exec_cmd .= sprintf(' -l %s %s %s %s %s %s 2>&1', 
        $admin_mail,
        $form->getField('username')->getValue(), 
        $form->getField('domain')->getValue(),
        $master,
        $slave,
        ($realtime ? 'realtime': 'deferred'));

    domain_add($form->getField('domain')->getValue(), gethostbyname($master), true, $form->getField('use_gmail_mxs')->getValue());
    sudoexec($exec_cmd, $exec_output, $exec_return);

    /* Gestion des noms de domaines supplementaires */
    if ( $form->getField('domain_alias')->getValue() ) {
        $domain_alias = preg_split('/,/', $form->getField('domain_alias')->getValue());
        foreach ( $domain_alias as $alias ) {
            $exec_cmd = 'web-add-cluster.sh add-alias '.$form->getField('username')->getValue().' ';
            $alias = trim($alias);
            $exec_cmd .= $alias.' '.$master.' '.$slave;
            sudoexec($exec_cmd, $exec_output2, $exec_return2);
            //print $exec_cmd."\n";
            domain_add($alias, gethostbyname($master), true);
        }
        $exec_return |= $exec_return2; 
        array_push($exec_output, $exec_output2);
    }

    /* insertion des infos dans le cache sqlite */
    if ($exec_return == 0) {

	    $bdd=new bdd();
	    $bdd->open($cache);

	    $account['name'] = $form->getField('username')->getValue();
	    $account['domain'] = $form->getField('domain')->getValue();

	    $bdd->add_account($account);

	    $bdd->add_role($account['name'], $master, 'master');
	    if ($slave != "null");
		$bdd->add_role($account['name'], $slave, 'slave');

        if (substr_compare($account['domain'], 'www.', 0, strlen('www.')) == 0) {
            $wwwalias = ltrim($account['domain'], 'www.');
            $serveralias = array (
                    'domain' => $account['name'],
                    'alias' => $wwwalias,
                    );
            $bdd->add_serveralias($serveralias);
        }

        /* Ajout des serveralias dans la base de donnée */
        if ( $form->getField('domain_alias')->getValue() ) {

            $domain = $form->getField('username')->getValue();
            $domain_alias = preg_split('/,/', $form->getField('domain_alias')->getValue());
            foreach ( $domain_alias as $alias ) {
                $alias = trim($alias);
                $serveralias = array (
                        'domain' => "$domain",
                        'alias' => "$alias"
                        );
                $bdd->add_serveralias($serveralias);
            }
        }
    }

    return array($exec_cmd, $exec_return, $exec_output);
}

/* Construction du formulaire d'ajout */
$form = new FormPage("Ajout d'un compte web", FALSE);
$form->addField('username', new TextInputFormField("Nom d'utilisateur", TRUE));
$form->addField('domain', new TextInputFormField("Nom de domaine", TRUE));
$form->addField('domain_alias', new TextInputFormField("Alias (séparés par une virgule)", FALSE));
$form->addField('password_random', 
                new CheckboxInputFormField("Mot de passe aléatoire ?", FALSE));
$form->getField('password_random')->setValue(TRUE);
$form->addField('password', new PasswordInputFormField('Mot de passe', FALSE));
$form->getField('password')->setDisabled();
$form->addField('mysql_db',
                new CheckboxInputFormField("Créer une base de données MySQL ?",
                                           FALSE));
$form->getField('mysql_db')->setValue(TRUE);
$form->addField('mysql_dbname',
                new TextInputFormField("Nom de la base MySQL", FALSE));
//$form->getField('mysql_dbname')->setDisabled();
$form->addField('mysql_password_random', 
                new CheckboxInputFormField("Mot de passe MySQL aléatoire ?",
                                           FALSE));
$form->getField('mysql_password_random')->setValue(TRUE);
//$form->getField('mysql_password_random')->setDisabled();
$form->addField('mysql_password',
                new PasswordInputFormField('Mot de passe MySQL', FALSE));
$form->getField('mysql_password')->setDisabled();

if ($conf['cluster']) {

    /* construction of $choices */
    $choices = array();
    $elmt = "";

    /* 1-x noreplication */
    $key_server = 0;
    foreach($conf['noreplication'] as $server) {
        $key="1-". $key_server;   
        $choices[$key] = "Aucun ($server)";
        $key_server ++;
    }

    /* 2-x-y-m postponedreplication */
    $size_tab = count($conf['postponedreplication']);

    if ($size_tab >= 2) { // compute all possibilities ...
        $key_mode = 0;
        foreach($conf['postponedreplication_mode'] as $mode) {

            // ... unless a restrictive list is given for a $mode in config.local.php
            if (isset($conf[$mode])) {
                $size_sub_tab = count($conf["$mode"]);

                // pair x -> y
                if (($size_sub_tab >= 2) && (($size_sub_tab % 2) == 0))
                    for ( $k = 0; $k < $size_sub_tab; $k = $k + 2 ) {
                        $elmt =  "$mode (" . $conf["$mode"][$k] . " => " . $conf["$mode"][$k+1] . ")";
                        $key= "2-" . substr($conf[$mode][$k], -1) . "-" . substr($conf[$mode][$k+1], -1) . "-" . $key_mode;
                        $choices[$key] = $elmt;
                    }
                $key_mode++;
                continue;       // done for $conf[$mode], next !
            }

            // for the others, give every combination
            $key_server = 0; // pivot 
            foreach($conf['postponedreplication'] as $server) {
                for ( $i = 0; $i < $size_tab; $i++) {

                    if ( $key_server <> $i ) {
                        $elmt = "$mode ($server =>" . $conf['postponedreplication'][$i] . ")";
                        $key = "2-" . $key_server . "-" . $i . "-" . $key_mode;
                        $choices[$key] = $elmt;
                    }
                }
                $key_server++;
            }
            $key_mode++;
        }
    }

    /* 3-x-y immediatereplication */
    $size_tab = count($conf['immediatereplication']);

    // pair x -> y
    if (($size_tab >= 2) && (($size_tab % 2) == 0))
        for ( $i = 0; $i < $size_tab; $i = $i + 2 ) {
            $elmt =  "Immediat (" . $conf['immediatereplication'][$i] . " => " . $conf['immediatereplication'][$i+1] . ")";
            $key = "3-".$i."-".($i+1);
            $choices[$key] = $elmt;
        }

    /* final form */
    $form->addField('cluster_mode', new SelectFormField('Mode de réplication', FALSE, $choices));
}

/* Quai13 specific: allow to switch between Gmail MX/Quai13 MX */
$form->addField('use_gmail_mxs', new CheckboxInputFormField("Utilisation des serveurs Gmail en MX&nbsp;?", FALSE));

/* Traitement du formulaire */
if(!empty($_POST)) {
	$form->isCurrentPage(TRUE);
	$form->initFields();

	/* Le champ password devient obligatoire si le champ password_random est
	 * décoché */
	if(!$form->getField('password_random')->getValue()) {
		$form->getField('password')->setMandatory(TRUE);
		$form->getField('password')->setDisabled(FALSE);
	}

	/* Erreur si mysql_db est coché */
	if($form->getField('mysql_db')->getValue()) {
		$form->getField('mysql_dbname')->setMandatory(TRUE);
		$form->getField('mysql_dbname')->setDisabled(FALSE);
		$form->getField('mysql_password_random')->setDisabled(FALSE);

		/* Le champ mysql_passwd devient obligatoire si le champ
		 * mysql_password_random est coché */
		if(!$form->getField('mysql_password_random')->getValue()) {
			$form->getField('mysql_password')->setMandatory(TRUE);
			$form->getField('mysql_password')->setDisabled(FALSE);
		}
	}

	/* Test de validation du formulaire */
	if($form->verify(TRUE)) {
		if ($conf['cluster'])
			$exec_info = web_add_cluster($form, $conf['admin']['mail']);
		else
			$exec_info = web_add($form, $conf['admin']['mail']);
	}   
}

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
include_once EVOADMIN_BASE . '../tpl/accounts.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

?>
