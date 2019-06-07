<?php

require_once EVOADMIN_BASE . '../lib/letsencrypt.php';

use lib\LetsEncrypt as letsencryt;

// store domain and aliases in session
if (!isset($_SESSION['lestencrypt-domains']) || empty($_SESSION['letsencrypt-domains'])) {
    $domain = $params[1];
    $cmd = 'web-add.sh list-vhost';

    if (!is_superadmin()) {
        $cmd = sprintf('%s %s', $cmd, $domain);
    }

    sudoexec($cmd, $data_output, $exec_return);

    $data_split = explode(':', $data_output[0]);
    $aliases = explode(',', $data_split[3]);

    $domains = array();

    // store domain and aliases
    array_push($domains, $data_split[2]);
    foreach ($aliases as $alias) {
        array_push($domains, $alias);
    }

    $_SESSION['letsencrypt-domains'] = $domains;
}

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

if (isset($_POST['submit'])) {
    $letsencrypt = new letsencryt();
    $error_message = '';

    while (true) {
        // check domains list
        if (empty($_SESSION['letsencrypt-domains'])) {
            $error_message = "Erreur : la liste des domaines est vide.";
            break;
        }

        // check if evoacme is installed
        $binaries_installed = $letsencrypt->isEvoacmeInstalled();
        if (!$binaries_installed) {
            $error_message = "Erreur : les binaires Evoacme ne sont pas installés.
                              Veuillez contacter un administrateur.";
            break;
        }

        // check HTTP
        $checked_domains = $letsencrypt->checkRemoteResourceAvailability($_SESSION['letsencrypt-domains']);
        $failed_domains = array_diff($_SESSION['letsencrypt-domains'], $checked_domains);
        if (!empty($failed_domains)) {
            $error_message = "Erreur : Le challenge HTTP a échoué pour le(s) domaine(s) ci-dessous.
                              Merci de vérifier que le dossier <code>/.well-known/</code> est accessible.";
            break;
        }

        // check DNS
        $valid_domains = $letsencrypt->checkDNSValidity($checked_domains);
        $failed_domains = array_diff($checked_domains, $valid_domains);
        if (!empty($failed_domains)) {
            $error_message = "Erreur : La vérification DNS a échoué pour les domaines ci-dessous.
                              Merci de vérifier les enregistrements de type A et AAAA.";
            break;
        }

        break;
    }
}

include_once EVOADMIN_BASE . '../tpl/webadmin-letsencrypt.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
