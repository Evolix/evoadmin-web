<?php

/**
 * Header template for all pages 
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

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>EvoAdmin - Powered by Evolix</title>
<link rel="stylesheet" href="/inc/css/main.css" type="text/css" media="screen, projection" />
<script type="text/javascript" src="/inc/js/lib/prototype-1.6.0.3.js"></script>
<script type="text/javascript" src="/inc/js/ftpadmin.js"></script>
<script type="text/javascript" src="/inc/js/webadmin.js"></script>
</head>

<body>

<div id="main">
    <h1 id="top">EvoAdmin
        <?php
            if(!empty($_SESSION['user'])) {
                print ' - '.$_SESSION['user'];
            }
            if(is_superadmin()) {
                print ' (Administrateur)';
            }
        ?>
    </h1>
