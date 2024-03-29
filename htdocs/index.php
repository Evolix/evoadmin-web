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

error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

/**
 * Requires
 */
require_once EVOADMIN_BASE . '../inc/common.php';


$uri = $_SERVER['REQUEST_URI'];
$params = array();

if (!array_key_exists('auth', $_SESSION) || $_SESSION['auth']!=1) {

    include_once EVOADMIN_BASE . '../inc/auth.php';

} elseif (preg_match('#^/ftpadmin/?(edit|add|delete)?/?(.*)?/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/ftpadmin.php';

} elseif (preg_match('#^/webadmin/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/webadmin.php';

} elseif (preg_match('#^/webadmin?#', $uri)) {

  // Redirect to /webadmin in order to set $_SESSION['non_stanard']
  if (!isset($_SESSION['non_standard']))
    http_redirect('/webadmin');

  // block the non-standard vhost modification
  if (in_array(htmlspecialchars(basename($_SERVER['REDIRECT_URL'])), $_SESSION['non_standard']))
    http_redirect('/webadmin');

  if (preg_match('#^/webadmin/(.*)/domain/?(edit)?/?(.*)?/$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-servername.php';

  } elseif (preg_match('#^/webadmin/(.*)/itk/?(enable|disable)?/?(.*)?/$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-itk.php';

  } elseif (preg_match('#^/webadmin/(.*)/php/$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-php.php';

  } elseif (preg_match('#^/webadmin/(.*)/alias/?(add|delete)?/?(.*)?/$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-edit.php';

  } elseif (preg_match('#^/webadmin/delete/(.*)/?$#', $uri, $params)) {
      //TODO: fix according to route naming convention
      include_once EVOADMIN_BASE . '../inc/webadmin-delete.php';

  } elseif (preg_match('#^/webadmin/suppr/(.*)/?$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-suppr.php';

  } elseif (preg_match('#^/webadmin/(.*)/letsencrypt/?$#', $uri, $params)) {

      include_once EVOADMIN_BASE . '../inc/webadmin-letsencrypt.php';

  } else {
      http_redirect('/webadmin');
  }
} elseif (is_superadmin() && preg_match('#^/accounts/?#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/accounts.php';

} elseif (preg_match('#^//?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/home.php';

} elseif (preg_match('#^/destroy/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/destroy.php';

} elseif (preg_match('#^/dbadmin/?$#', $uri, $params)) {

    include_once EVOADMIN_BASE . '../inc/dbadmin.php';

} else {
    die ("Cette page n'existe pas !!!");
}
