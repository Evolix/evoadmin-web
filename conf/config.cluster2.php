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

$clusterconf['noreplication'] = array('srv10');
$clusterconf['postponedreplication'] = array('srv10', 'srv11');
$clusterconf['immediatereplication'] = array('srv10', 'srv11');
$clusterconf['postponedreplication_mode'] = array('3 fois/jour', '1 fois/jour', '1 fois/heure');
$clusterconf['1 fois/heure'] = array('srv10', 'srv11');
