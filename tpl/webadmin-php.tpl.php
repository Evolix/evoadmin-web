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

<h2>Version PHP</h2>

<p>Version actuelle de PHP : <?= preg_replace("/^(\d)(\d)$/", '\1.\2', $current_PHP_version) ?></p>

<form name="form-add" id="form-add" action="" method="POST">
    <fieldset>
        <legend>Changement de version de PHP</legend>
        <?= $form ?>
        <p><input type="submit" value="Changer"/></p>
    </fieldset>
</form>
