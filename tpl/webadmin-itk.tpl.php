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

    if(count($data_list) > 0) { ?>

    <table id="tab-list">
        <thead>
        <tr>
           <th>ServerName</th>
           <th>Utilisateur</th>
           <?php if (is_superadmin()) {
               print '<th>Action</th>';
           } ?>
        </tr>
        </thead>
        <tbody>
        <?php
            for ( $i=0; $i < count($data_list); ++$i ) {
                print '<tr>';
                printf('<td>%s</td>',
                    $data_list[$i]['servername']);
                printf('<td>%s</td>',
                    $data_list[$i]['user']);
                if (is_superadmin()) {

                  if (strpos($data_list[$i]['user'], 'www') !== false) {
                    $action = ['disable', 'Désactiver'];
                  } else {
                    $action = ['enable', 'Activer'];
                  }

                  printf('<td><a href="/webadmin/itk/%s?%s=%s">'.$action[1].'</a></td>',
                          $domain, $action[0], $data_list[$i]['servername']);
                }
                print '</tr>';
        } ?>
        </tbody>
    </table>
<?php
   } else {
       print "<p>La sécurité ITK ne semble pas en place pour le domaine $domain</p>";
   }


?>
