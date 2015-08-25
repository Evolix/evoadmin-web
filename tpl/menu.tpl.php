<?php

/**
 * Menu principal
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

<ul id="menu">
    <li><a href="/">Accueil</a>
    <?php if(is_superadmin()) {
        print '<li><a href="/accounts">Ajout d\'un compte</a></li>';
    } ?>
    <li><a href="/webadmin">Liste des comptes</a></li>
    <?php if ($conf['ftpadmin']) { ?>
        <li><a href="/ftpadmin/add">Ajout FTP</a></li>
        <li><a href="/ftpadmin">Comptes FTP</a></li>
    <?php } ?>
    <li><a href="/destroy">Déconnexion</a></li>
</ul>
<br/>

