<?php
/**
 * Authentification page
 *
 * Copyright (c) 2009-2022 Evolix - Tous droits reserves
 *
 * @author  Evolix <info@evolix.fr>
 * @author  Gregory Colpart <reg@evolix.fr>
 * @author  Thomas Martin <tmartin@evolix.fr>
 * @author  Sebastien Palma <spalma@evolix.fr>
 * @author  and others.
 * @version 1.0
 */

?>

<h2>Evoadmin : Connexion</h2>

<form method="POST">
<table align="center">
    <tr>
        <td align="right">Utilisateur : &nbsp;</td>
        <td align="left"><input type="text" name="login" /></td>
    </tr>
    <tr>
        <td align="right">Mot de passe : &nbsp;</td>
        <td align="left"><input type="password" name="passw" /></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td align="left"><br/><input type="submit" value="Connexion" /></td>
    </tr>
    <?php
    if (!empty($error)) {
        ?>
    <tr>
        <td colspan="2" class="auth-error">
            Identifiants invalides. 
            Veuillez ré-essayer
        </td>
    </tr>
        <?php
    }
    ?>
</table>
</form>
