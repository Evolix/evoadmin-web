<?php

/*
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

$clusterconf['noreplication'] = array('srv00');
$clusterconf['postponedreplication'] = array('srv00', 'srv01', 'srv04');
$clusterconf['immediatereplication'] = array('srv00', 'srv01');
$clusterconf['postponedreplication_mode'] = array('3 fois/jour', '1 fois/jour', '1 fois/heure');
// On specifie des serveurs pour certains modes de replication différés
//$clusterconf['1 fois/jour'] = array('srv03', 'srv01');
$clusterconf['1 fois/heure'] = array('srv01', 'srv00');

/* opcodes
 *                     type      indice array   mode  

noreplication           1             x
postponedrepl           2             x  y      m
immediaterepl           3             x  y 
*/
