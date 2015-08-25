<?php

/**
 * Template Page for the ProFTPD's virtual accounts
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

function html_render_account($line) {
  print "<tr>\n";
  if(is_superadmin()) {
    print "  <td>{$line['owner']}</td>\n";
  }
  print "  <td>{$line['name']}</td>\n";
  print "  <td>{$line['path']}</td>\n";
  print "  <td>{$line['size']}</td>\n";
  //print "  <td>{$line['date']}</td>\n";
  print "  <td><!-- <a href='/ftpadmin/edit/{$line['name']}'>Change password</a> --><a href='/ftpadmin/delete/{$line['name']}' onClick='return confirm('Voulez-vous vraiment supprimer le compte {$line['name']}');'>Supprimer</a></td>\n";
  print "</tr>\n";
}

?>

<h2>Comptes FTP</h2>

<br/><br/>

<?php if(count($table) > 0) { ?>
<table id="tab-list" class="tab-list">
  <thead>
  <tr>
    <?php if(is_superadmin()) { ?>
    <th>Propriétaire</th>
    <?php } ?>
    <th>Compte</th>
    <th>Répertoire</th>
    <th>Espace utilisé</th>
    <!--<th>Dernière modif.</th>-->
    <th>Actions</th>
  <tr>
  </thead>
  <tbody>
  <?php
  foreach ($table as $table_line) {
    echo html_render_account($table_line);
  }
  ?>
  </tbody>
</table>

<p>
Espace total utilisé : <?php print $size_total ?>.
</p>
<br/>
<br/>
<?php } ?>


<?php 

$readonly="";
if (array_key_exists('error', $_SESSION) && $_SESSION['error']==3) $readonly="readonly='readonly'";

?>

<br/>

<a id="ftp-add"></a>
<pre>

