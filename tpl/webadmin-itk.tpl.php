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

<h2>Sécurité ITK</h2>

<?php

    if(!empty($user_itk)) { ?>

    <table id="tab-list">
        <thead>
        <tr>
           <th>Utilisateur</th>
           <?php if (is_superadmin()) {
               print '<th>Action</th>';
           } ?>
        </tr>
        </thead>
        <tbody>
        <?php

          print '<tr>';
          printf('<td>%s</td>',
              $user_itk);
          if (is_superadmin()) {

            if (strpos($user_itk, 'www') !== false) {
              $action = ['disable', 'Désactiver'];
            } else {
              $action = ['enable', 'Activer'];
            }

            printf('<td><a href="/webadmin/%s/itk/%s/">'.$action[1].'</a></td>',
                    $domain, $action[0]);
          }
          print '</tr>';
        ?>
        </tbody>
    </table>
<?php
   } else {
       print "<p>La sécurité ITK ne semble pas en place pour le domaine $domain</p>";
   }


?>
