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

<h2>Server Alias</h2>

<?php 

    if(count($alias_list) > 0) { 
        
        if (is_superadmin()) {
            print "<center>";
            printf('<p><a href="/webadmin/edit/%s?add">Ajouter un alias</a></p>', $domain);
            print "</center>";
        }?>

    <table id="tab-list">
        <thead>
        <tr>
           <th>Alias</th>
           <?php if (is_superadmin()) {
               print '<th>Action</th>';
           } ?>
        </tr>
        </thead>
        <tbody>
        <?php 
            for ( $i=0; $i < count($alias_list); ++$i ) { 
                print '<tr>';
                printf('<td><a href="http://%s">http://%s</a></td>',
                    $alias_list[$i]['alias'], $alias_list[$i]['alias']);
                if (is_superadmin()) 
                    printf('<td><a href="/webadmin/edit/%s?del=%s">Supprimer</a></td>', 
                            $domain, $alias_list[$i]['alias']);
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

