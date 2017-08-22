<?php

/**
 * Databases Management Page 
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

global $conf;

if (!$conf['dbadmin'])
    http_redirect('/');

$cmd = 'dbadmin.sh list';

if(!is_superadmin()) {
    $cmd .= ' ' . $_SESSION['user'];
}
sudoexec($cmd, $data_output, $exec_return);

/*
 * Put command output to db_list array.
 */

$db_list = array();
foreach ($data_output as $data_line) {
    $data_split = explode(':', $data_line);
    array_push($db_list, array(
                'owner' => $data_split[0],
                'database' => $data_split[1])
            );
}

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
include_once EVOADMIN_BASE . '../tpl/dbadmin.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
?>
