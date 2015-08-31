#!/usr/bin/php
<?php
require '../lib/bdd.php';
require_once '../htdocs/common.php';

$files = array();
if (is_mcluster_mode()) {
    foreach ($conf['clusters'] as $cluster) {
        array_push($files, str_replace('%cluster_name%', $cluster, $conf['cache']));
    }
}
else {
    array_push($files, $conf['cache']);
}

foreach ($files as $file) {

    $bdd = new bdd();

    if (!file_exists($file)) {
    	$bdd->create($file);
        echo "$file created.\n";
    }
    else {
    	echo "$file is already created.\n";
    	continue;
    }
}

exit(0);
?>
