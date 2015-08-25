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
$localconf['known_host'] = '/home/evoadmin/www/known_host';
$oriconf['ftpadmin'] = TRUE;

/* cluster mode
 * $oriconf['noreplication'] = array('www00');
 * $oriconf['postponedreplication'] = array('www00', 'www01');
 * $oriconf['immediatereplication'] = array('www00', 'www01');
 * $oriconf['postponedreplication_mode'] = array('3 fois/jour', '1 fois/jour', '1 fois/heure');
 */

/* Il est possible de définir pour chaque mode de
 * postponedreplication_mode une liste de serveurs,
 * qui seront utilisés à la place des serveurs du 
 * tableau postponedreplication.
 *
 * $localconf['1 fois/jour'] = array('www00', 'www01');
 * $localconf['1 fois/heure'] = array('www01', 'www00'); 
 */

