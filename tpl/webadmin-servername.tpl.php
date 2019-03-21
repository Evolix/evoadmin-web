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

<h2>Server Name</h2>

<?php

    if(count($servername_list) > 0) { ?>

    <table id="tab-list">
        <thead>
        <tr>
           <th>Server Name</th>
           <?php if (is_superadmin()) {
               print '<th>Action</th>';
           } ?>
        </tr>
        </thead>
        <tbody>
        <?php
            for ( $i=0; $i < count($servername_list); ++$i ) {
                print '<tr>';
                printf('<td>%s</td>',
                    $servername_list[$i]);
                if (is_superadmin())
                    printf('<td><a href="/webadmin/servername/%s?edit=%s">Modifier</a></td>',
                            $domain, $servername_list[$i]);
                print '</tr>';
        } ?>
        </tbody>
    </table>
<?php
   } else {
       print "<p>Aucun alias existant pour le domaine $domain !</p>";
        if (is_superadmin()) {
            print "<center>";
            printf('<p><a href="/webadmin/edit/%s?add">Ajouter un alias</a></p>', $domain);
            print "</center>";
        }
   }


?>
