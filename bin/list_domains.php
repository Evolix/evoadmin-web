<?php

require '../lib/bdd.php';
require_once '../conf/config.php';
require_once '../conf/config.local.php';

$conf = array_merge($oriconf, $localconf);


$bdd = new bdd();

$file=$conf['cache'];

if (!file_exists($file)) {
	echo "$file is not created\n";
	exit(1);
}

$bdd->open($file);

$domains = $bdd->list_domains();
print_r($domains);

exit(0);

?>
