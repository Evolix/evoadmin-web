<?php

/**
 * Databases management page template
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
<div class="container">
<h2>Bases de données</h2><hr>

<?php if(count($db_list) > 0) { ?>
    <table id="tab-list" class="table table-striped table-condensed">
      <thead>
        <tr>
          <th>Propriétaire</th>
          <th>Bases de données</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($db_list as $db_info) {
          print '<tr>';
          printf('<td align="left">&nbsp;&nbsp;%s</td>', preg_replace("/'/", "", $db_info['owner']));
          printf('<td align="left">&nbsp;&nbsp;%s</td>', $db_info['database']);
          print '</tr>';
      } ?>
      </tbody>
    </table>
<?php
    } else {
        print '<div class="alert alert-info" role="alert">Aucune base existante !</div>';
    }
?>
</div>