#!/usr/bin/php
<?php
require '../lib/bdd.php';
require_once '../htdocs/common.php';

$file = '';
$server = '';
if (is_mcluster_mode()) {
    if ($argc != 2) {
    	echo "Multi-cluster mode is enabled in your config file.\n"; 
        echo "Usage: $argv[0] <cluster>\n";
    	exit(1);
    }
    $file = str_replace('%cluster_name%', $argv[1], $conf['cache']);
}
else {
    if ($argc != 1) {
        echo "Usage: $argv[0]\n";
    	exit(1);
    }
    $file = $conf['cache'];
}

if (!file_exists($file)) {
	echo "$file is not created\n";
	exit(1);
}

$bdd = new bdd();
$bdd->open($file);
$domains = $bdd->list_domains();
print_r($domains);

exit(0);

?>
