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

 /**
  * Check if a file exists and is readable and `die()` if it doesnt.
  * @param string $file
  * @return void
  */
function test_exist($file) {
    if(!file_exists($file)) {
        die("Erreur, vous devez mettre en place le fichier $file !\n");
    }   
    if(!is_readable($file)) {
        die("Erreur, le fichier $file n'est pas accessible en lecture !\n");
    }   
}

/**
 * Redirect the page to $path on the same HOST
 * @param string $path
 * @return never
 */
function http_redirect($path) {
    header('Location: '.$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$path);
    exit(0);
}

/**
 * @param string $filename
 * @return string
 */
function findexts ($filename)
{
    $filename = strtolower($filename) ;
    $exts = explode("[/\\.]", $filename) ;
    $n = count($exts)-1;
    $exts = $exts[$n];
    return $exts;
} 

/**
 * Check if the user is a superadmin
 * @return bool
 */
function is_superadmin() {
    global $conf;
    if(!empty($_SESSION['user']) && in_array($_SESSION['user'], $conf['superadmin'])) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute a command with sudo
 * @param string $cmd The command that will be executed.
 * @param array $output output of the command.
 * @param int $return_var return status of the command.
 * @return void
 */
function sudoexec($cmd, &$output, &$return_var) {
    global $conf;

    /* -H  The -H (HOME) option sets the HOME environment variable to the
     * homedir of the target user */
    /* => Nécessaire pour l'utilisation du .my.cnf de root */
    $cmd = sprintf('sudo -H %s/%s', $conf['script_path'], $cmd);

    exec($cmd, $output, $return_var);
}

/**
 * Check if Evoadmin is installed in cluster mode
 * @return boolean
 */
function is_cluster_mode() {
    global $conf;
    return $conf['cluster'];
}

/**
 * Check if Evoadmin is installed in multi-cluster mode.
 * @return boolean
 */
function is_mcluster_mode() {
    global $conf;
    return is_cluster_mode() && array_key_exists('clusters', $conf) && is_array($conf['clusters']);
}

/**
 * Load config file for the specified cluster.
 * @param string $cluster
 * @return void
 */
function load_config_cluster($cluster) {
    global $conf;
    $configfile = '../conf/config.'.$cluster.'.php';
    test_exist($configfile);
    require_once($configfile);
    $conf = array_merge($conf, $clusterconf);
}

/**
 * Check if evoadmin install is a multi PHP install
 * @return boolean - True when it's a multi PHP system
 */
function is_multiphp() {
    global $conf;
    return array_key_exists('php_versions', $conf) && count($conf['php_versions']) > 1;
}

/**
 * Webadd
 * @param string $command webadd command to run
 * @return array output from the command
 */
function run_webadd_cmd($command) {
    global $conf;

    $cmd = 'web-add.sh '. $command;

    $data_output = null;
    $exec_return = null;
    sudoexec($cmd, $data_output, $exec_return);


    return $data_output;
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

    /**
     * (simply there to silence a warning)
     * @var array $oriconf
     * @var array $localconf
     */
    // config files
    // (here because need Log PEAR lib)
    test_exist('../conf/connect.php');
    require_once('../conf/connect.php');
    test_exist('../conf/config.php');
    require_once('../conf/config.php');
    test_exist('../conf/config.local.php');
    require_once('../conf/config.local.php');
    $conf = array_merge($oriconf, $localconf);
}
