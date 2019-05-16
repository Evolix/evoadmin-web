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

<h2>Servername</h2>

<?php

    if(!empty($servername)) { ?>

    <table id="tab-list">
        <thead>
        <tr>
           <th>Servername</th>
           <?php if (is_superadmin()) {
               print '<th>Action</th>';
           } ?>
        </tr>
        </thead>
        <tbody>
        <?php
          print '<tr>';
          printf('<td>%s</td>',
              $servername);
          if (is_superadmin())
              printf('<td><a href="/webadmin/servername/%s?edit=%s">Modifier</a></td>',
                      $domain, $servername);
          print '</tr>';
        ?>
        </tbody>
    </table>
<?php
   } else {
       print "<p>Aucun Servername existant pour le domaine $domain !</p>";
   }


?>
