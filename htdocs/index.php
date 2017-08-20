<?php

/**
 * common DirectoryIndex page
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
 * Path
 */
define('EVOADMIN_BASE','./');

/**
 * PHP cookies session
 */
session_name('EVOADMINWEB_SESS');
session_start();

error_reporting(E_ALL | E_NOTICE);
header('Content-Type: text/html; charset=utf-8');

/**
 * Requires
 */
require_once EVOADMIN_BASE . 'common.php';


$uri = $_SERVER['REQUEST_URI'];
$params = array();

if (!array_key_exists('auth', $_SESSION) || $_SESSION['auth']!=1) {

    include_once EVOADMIN_BASE . '../inc/auth.php';

} elseif (preg_match('#^/ftpadmin/?(edit|add|delete)?/?(.*)?/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/ftpadmin.php';

} elseif (preg_match('#^/webadmin/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/webadmin.php';

} elseif (preg_match('#^/webadmin/edit/(.*)/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/webadmin-edit.php';

} elseif (preg_match('#^/webadmin/suppr/(.*)/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/webadmin-suppr.php';

} elseif (is_superadmin() && preg_match('#^/accounts/?#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/accounts.php';

} elseif (preg_match('#^//?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/home.php';

} elseif (preg_match('#^/destroy/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/destroy.php';

} else {
    die ("Cette page n'existe pas !!!");
}

