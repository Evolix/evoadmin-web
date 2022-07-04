<?php

/**
 * Configuration page
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 * 
 * $Id: config.php 273 2009-05-12 13:54:50Z tmartin $
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2
 *
 * @author Gregory Colpart <reg@evolix.fr>
 * @author Thomas Martin <tmartin@evolix.fr>
 * @author Sebastien Palma <spalma@evolix.fr>
 * @version 1.0
 */


// Email pour les notifications
$oriconf['admin']['mail'] = 'admin@example.com';
$oriconf['techmail'] = 'jdoe@example.com';
$oriconf['debug'] = FALSE;
$oriconf['superadmin'] = array('superadmin');
$oriconf['script_path'] = '/usr/share/scripts/evoadmin';
$oriconf['cluster'] = FALSE;
$oriconf['servers'] = array('servers');
$oriconf['cache'] = '/home/evoadmin/www/cache.sqlite';
$oriconf['known_host'] = '/home/evoadmin/www/known_host';
$oriconf['ftpadmin'] = FALSE;
$oriconf['bindadmin'] = FALSE;
// Penser à rajouter également les versions de PHP disponibles dans /etc/evolinux/web-add.conf
// $oriconf['php_versions'] = array();
$oriconf['quota'] = FALSE;
$oriconf['dbadmin'] = FALSE;

$oriconf['noreplication'] = array('srv00.example.com', 'srv01.example.com', 'srv02.example.com');
$oriconf['postponedreplication'] = array('srv00.example.com', 'srv01.example.com', 'srv02.example.com');
$oriconf['immediatereplication'] = array('srv00.example.com', 'srv01.example.com');
$oriconf['postponedreplication_mode'] = array('1 fois/jour', '3 fois/jour', '1 fois/jour');

// auth (sha256 hashs)
$oriconf['logins'] = array();
//$oriconf['logins']['foo'] = 'd5d3c723fb82cb0078f399888af78204234535ec2ef3da56710fdd51f90d2477';
//$oriconf['logins']['bar'] = '7938c84d6e43d1659612a7ea7c1101ed02e52751bb64597a8c20ebaba8ba4303';
