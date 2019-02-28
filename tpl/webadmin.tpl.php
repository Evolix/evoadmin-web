<?php

/**
 * Gestion des domaines
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

<h2>Domaines</h2>

<?php if(count($vhost_list) > 0) { ?>
    <table id="tab-list">
      <thead>
      <tr>
        <?php if(is_superadmin()) {
            print '<th>Propriétaire</th>';
        } ?>
        <th>Domaine</th>
        <?php if(is_superadmin()) {
            print '<th>Alias</th>';
        }
        if($conf['quota']) {
            print '<th>&nbsp;Utilisé&nbsp;</th>';
            print '<th>&nbsp;Soft&nbsp;</th>';
            print '<th>&nbsp;Hard&nbsp;</th>';
            print '<th>&nbsp;Occupation&nbsp;</th>';
        }
        if (array_key_exists('php_versions', $conf) && count($conf['php_versions']) > 1) {
            print '<th>&nbsp;PHP&nbsp;</th>';
        }
        ?>
        <th>&nbsp;Actif&nbsp;</th>
        <th>&nbsp;Action&nbsp;</th>
        <!--<th>Opérations</th>-->
        <?php if($conf['cluster']) { ?>
    <th>Bdd</th>
    <th>Mail</th>
    <th>Replication</th>
    <th>Master</th>
    <th>Slave</th>
    <?php } ?>
      </tr>
      </thead>
      <tbody>
      <?php foreach($vhost_list as $vhost_info) {
          print '<tr>';
          if(is_superadmin()) {
            printf('<td>%s</td>', $vhost_info['owner']);
          }
          printf('<td><a href="http://%s">http://%s</a></td>',
                  $vhost_info['server_name'], $vhost_info['server_name']);
    
          if ($conf['cluster']) {
              if (empty($vhost_info['bdd']))
                  printf('<td bgcolor="#696969"/>');
              else
                  printf('<td>%s</td>', $vhost_info['bdd']);

              if (empty($vhost_info['mail']))
                  printf('<td bgcolor="#696969" />');
              else if ($vhost_info['mail'] == 'gmail')
                  printf('<td><img src="/img/gmail.gif" alt="Gmail" /></td>');
              else printf('<td><img src="/img/evolix.gif" alt="Evolix" /></td>');

              if (empty($vhost_info['replication']))
                  printf('<td bgcolor="#696969"/>');
              else
                  printf('<td>%s</td>', $vhost_info['replication']);
              printf('<td>%s</td>', $vhost_info['master']);
              if (empty($vhost_info['slave']))
                  printf('<td bgcolor="#696969"/>');
              else
                  printf('<td>%s</td>', $vhost_info['slave']);
	  }

          printf('<td align="left">%s</td>', preg_replace('/,/','<br />',$vhost_info['server_alias']));

          if ($conf['quota']) {
            printf('<td>%s</td>', $vhost_info['size']);
            printf('<td>%s</td>', $vhost_info['quota_soft']);
            printf('<td>%s</td>', $vhost_info['quota_hard']);
            printf('<td>%s</td>', $vhost_info['occupation']);
          }
          if (array_key_exists('php_versions', $conf) && count($conf['php_versions']) > 1) {
            printf('<td>%s</td>', preg_replace("/^(\d)(\d)$/", '\1.\2', $vhost_info['php_version']));
          }
          printf('<td>%s</td>', $vhost_info['is_enabled']);
          if (is_superadmin()) {
              printf('<td><a href="/webadmin/edit/%s">Lister/Modifier</a></td>',
                      $vhost_info['owner']);
          }

          print '</tr>';

      } ?>
      </tbody>
    </table>
    <br>
    Nombre total de comptes : <?= count($vhost_list); ?>
<?php
    } else {
        print '<p>Aucun domaine existant !</p>';
    }
?>
