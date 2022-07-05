<?php

/**
 * Configuration page
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 * 
 * $Id: config.php 273 2009-05-12 13:54:50Z tmartin $
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2
 *
 * @author  Gregory Colpart <reg@evolix.fr>
 * @author  Thomas Martin <tmartin@evolix.fr>
 * @author  Sebastien Palma <spalma@evolix.fr>
 * @version 1.0
 */


$oriconf['admin']['mail'] = 'admin@example.com';
$oriconf['techmail'] = 'jdoe@example.com';
$oriconf['debug'] = false;
$oriconf['superadmin'] = array('superadmin');
$oriconf['script_path'] = '/usr/share/scripts/evoadmin';
$oriconf['cluster'] = false;
$oriconf['servers'] = array('servers');
$oriconf['cache'] = '/home/evoadmin/www/cache.sqlite';
$oriconf['known_host'] = '/home/evoadmin/www/known_host';
$oriconf['ftpadmin'] = false;
$oriconf['bindadmin'] = false;
// Warning: Don't forget to add available PHP versions into : /etc/evolinux/web-add.conf
// $oriconf['php_versions'] = array();
$oriconf['quota'] = false;
$oriconf['dbadmin'] = false;

$oriconf['noreplication'] = array('srv00.example.com', 'srv01.example.com', 'srv02.example.com');
$oriconf['postponedreplication'] = array('srv00.example.com', 'srv01.example.com', 'srv02.example.com');
$oriconf['immediatereplication'] = array('srv00.example.com', 'srv01.example.com');
$oriconf['postponedreplication_mode'] = array('1 fois/jour', '3 fois/jour', '1 fois/jour');

// Generate password hashes : mkpasswd --method=sha-512 (cli) or with PHP's password_hash()
$oriconf['logins'] = array();
//$oriconf['logins']['foo'] = '$6$X0jqa/ausLSBkj4m$dLMMcPGVxak.aDPo4V/GJLm2d8vU8/QA5LbGTuqXCdxSNYU0kRKBgDl16GAyp0GqXXZ5wwDEJKQ1npgFwiuV81';
//$oriconf['logins']['bar'] = '$6$Q6233S6mlWAF6p.j$LtzwG02YucozwqjAgSpeldh24Mnz7lBuVSbOQYbKKh9FiUx3tMVl6kJZkmrNdPqeadFXKAYXrqn.gy8KposF5.';
