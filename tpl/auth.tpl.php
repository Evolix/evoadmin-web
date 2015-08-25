<?php

/**
 * Authentification form
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

<br/><br/>
<form method="POST">
<table width="300" align="center">
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
    <td colspan="2" class="auth-error">Identifiants invalides. Veuillez ré-essayer</td>
  </tr>
  <?php
  }
  ?>
</table>
</form>
