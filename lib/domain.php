<?php

/**
 * Add a domain in BIND
 * @param string $name Domain to add
 * @param string $IP IP of web server for this domain
 * @param bool $with_mxs Set to `true` if you wants to add MX records. Otherwise set to `false`.
 * @param bool $gmail Set to `true` if the Google MXs are to be used by this domain as its MXs.
 * @return array
 */
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
    }

    $exec_cmd .= " -a $IP $name";

    sudoexec($exec_cmd, $exec_output, $exec_return);
    return array($exec_cmd, $exec_return, $exec_output);
}

/**
 * Ensure that the domain (or list of domains) do no exists in any other
 * apache config file. Either as a ServerName or ServerAlias
 *
 * @param string $name Domain (or list of domains separated by commas)
 *
 * @return boolean True if one occurence is found. Else otherwise
 */
function check_occurence_name($name) {

    // If no domain are given, that should be okay
    if(strlen($name) === 0){
        return false;
    }

    $exploded_names = explode(',', $name);

    foreach ($exploded_names as $current_name) {
        $check_occurence_cmd = 'web-add.sh check-occurence ' . escapeshellarg($current_name);

        sudoexec($check_occurence_cmd, $check_occurence_output, $check_occurence_return);
        if ($check_occurence_return == 0) return true;
    }

    return false;
}
