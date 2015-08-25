<?php

require '../lib/bdd.php';
require_once '../conf/config.php';
require_once '../conf/config.local.php';


if ($argc==1) {
	echo "Specify a server name"; 
	exit(1);
}

$conf = array_merge($oriconf, $localconf);

$bdd = new bdd();

$file=$conf['cache'];

if (!file_exists($file)) {
	echo "$file doesn't exist\n";
	exit(1);
}

$bdd->open($file);

$server = array("name" => $argv[1]);

$bdd->add_server(array("name" => "$server"));
exec('ssh -o "UserKnownHostsFile '.$conf['known_host'].'" '.$argv[1].' /bin/true');

echo "$server added in cache\n";
exit(0);
?>

