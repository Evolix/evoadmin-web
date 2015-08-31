<?php

function domain_add($name, $IP, $with_mxs, $gmail=false) {

    $exec_cmd = 'bind-add-ng.sh';

    if ( $with_mxs == true ) {
        /* Quai13 specific: use Gmail MXs if wanted */
        if ( $gmail ) {
            $exec_cmd .= ' -m ASPMX.L.GOOGLE.com.,10';
            $exec_cmd .= ' -m ALT1.ASPMX.L.GOOGLE.com.,20';
            $exec_cmd .= ' -m ALT2.ASPMX.L.GOOGLE.com.,20';
            $exec_cmd .= ' -m ASPMX2.GOOGLEMAIL.com.,30';
            $exec_cmd .= ' -m ASPMX3.GOOGLEMAIL.com.,30';
        }
        else {
            $exec_cmd .= ' -m mail,10';
            $exec_cmd .= ' -m backup.quai13.net.,20';
        }
#        mail('tech@evolix.fr', '[TAF] Ajouter '.$name.' sur quai13-backup', wordwrap('Ajouter le domaine '.$name.' à la directive relay_domains dans le fichier /etc/postfix/main.cf sur quai13-backup, pour mettre en place le MX secondaire du domaine.', 70));
    }

    $exec_cmd .= " -a $IP $name";

    //echo $exec_cmd."\n";
    sudoexec($exec_cmd, $exec_output, $exec_return);
    return array($exec_cmd, $exec_return, $exec_output);
}

