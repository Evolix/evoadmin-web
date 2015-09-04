<?php

/**
 * file included in every PHP file
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


/**
 * Functions
 */
function test_exist($file) {
    if(!file_exists($file)) {
        die("Erreur, vous devez mettre en place le fichier $file !\n");
    }   
    if(!is_readable($file)) {
        die("Erreur, le fichier $file n'est pas accessible en lecture !\n");
    }   
}

function http_redirect($path) {
    header('Location: http://'.$_SERVER['HTTP_HOST'].$path);
    exit(0);
}

function findexts ($filename)
{
    $filename = strtolower($filename) ;
    $exts = explode("[/\\.]", $filename) ;
    $n = count($exts)-1;
    $exts = $exts[$n];
    return $exts;
} 

function is_superadmin() {
    global $conf;
    if(!empty($_SESSION['user']) && in_array($_SESSION['user'], $conf['superadmin'])) {
        return 1;
    } else {
        return 0;
    }
}

function sudoexec($cmd, &$output, &$return_var) {
    global $conf;

    /* -H  The -H (HOME) option sets the HOME environment variable to the
     * homedir of the target user */
    /* => Nécessaire pour l'utilisation du .my.cnf de root */
    $cmd = sprintf('sudo -H %s/%s', $conf['script_path'], $cmd);

    exec($cmd, $output, $return_var);
}

/**
 * Return TRUE is Evoadmin is installed in cluster mode.
 */
function is_cluster_mode() {
    global $conf;
    return $conf['cluster'];
}

/**
 * Return TRUE is Evoadmin is installed in multi-cluster mode.
 */
function is_mcluster_mode() {
    global $conf;
    return is_cluster_mode() && array_key_exists('clusters', $conf) && is_array($conf['clusters']);
}

/**
 * Load config file for the specified cluster.
 */
function load_config_cluster($cluster) {
    global $conf;
    $configfile = '../conf/config.'.$cluster.'.php';
    test_exist($configfile);
    require_once($configfile);
    $conf = array_merge($conf, $clusterconf);
}

/**
 * Includes
 */

// PEAR libs
if (!(ini_set('include_path', ini_get('include_path')))) {
    die('bibliotheques PEAR non presentes');
} else {

    require_once 'PEAR.php';
    require_once 'Log.php';

    // config files
    // (here because need Log PEAR lib)
    test_exist('../conf/connect.php');
    require_once('../conf/connect.php');
    test_exist('../conf/config.php');
    require_once('../conf/config.php');
    test_exist('../conf/config.local.php');
    require_once('../conf/config.local.php');
    # il faut un acces en lecture au fichier shadow pour pam_auth()
    test_exist('/etc/shadow');
    $conf = array_merge($oriconf, $localconf);
}
