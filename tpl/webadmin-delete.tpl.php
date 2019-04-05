<?php

/**
 * Edition d'un domaine
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 *
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2
 *
 * @author Thomas Martin <tmartin@evolix.fr>
 * @version 1.0
 */

?>

<h2>Suppression du compte web</h2>

<form name="form-delete-vhost" id="form-add" action="" method="POST">
    <fieldset>
        <p>
            <label for="vhost-name">Nom du compte :</label>
            <input type="text" name="vhost-name">
        </p>
        <p>
            <label for="vhost-delete-db">Supprimer la base de données ? :</label>
            <input id="vhost-delete-db" name="vhost-delete-db" checked="checked" value="1" type="checkbox">
        </p>
        <p>
            <label for="vhost-dbname">Nom de la base de données :</label>
            <input type="text" name="vhost-dbname" id="vhost-dbname">
        </p>
        <p>
            <input type="submit" name="delete-vhost" value="Supprimer">
        </p>
    </fieldset>
</form>
