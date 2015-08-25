<?php

require '../lib/bdd.php';
require_once '../conf/config.php';
require_once '../conf/config.local.php';

$conf = array_merge($oriconf, $localconf);


$bdd = new bdd();

$file=$conf['cache'];

if (!file_exists($file))
	$bdd->create($file);
else {
	echo "$file is already created";
	exit(1);
}

foreach ($conf['servers'] as $server) {
	echo "$server added in cache\n";
	$bdd->add_server(array("name" => "$server"));
}

echo "Cache initialisé\n";
exit(0);
?>
