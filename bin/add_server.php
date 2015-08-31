#!/usr/bin/php
<?php
require '../lib/bdd.php';
require_once '../htdocs/common.php';

$bdd = new bdd();

$file = '';
$server = '';
if (is_mcluster_mode()) {
    if ($argc != 3) {
    	echo "Multi-cluster mode is enabled in your config file.\n"; 
        echo "Usage: $argv[0] <cluster> <server>\n";
    	exit(1);
    }
    $file = str_replace('%cluster_name%', $argv[1], $conf['cache']);
    $server = $argv[2];
}
else {
    if ($argc != 2) {
        echo "Usage: $argv[0] <server>\n";
    	exit(1);
    }
    $file = $conf['cache'];
    $server = $argv[1];
}


if (!file_exists($file)) {
	echo "$file doesn't exist\n";
	exit(1);
}

$bdd->open($file);
$bdd->add_server(array("name" => "$server"));
echo "$server added in $file\n";

exit(0);
?>
