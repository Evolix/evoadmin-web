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

if (isset($params[2]) && $params[2] == "check") {
    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

    $letsencrypt = new letsencryt();

    // check HTTP
    $checked_domains = $letsencrypt->checkRemoteResourceAvailability($_SESSION['letsencrypt-domains']);
    $failed_domains_http = array_diff($_SESSION['letsencrypt-domains'], $checked_domains);

    # debug à améliorer
    echo '<h2>The following domain(s) failed the HTTP challenge</h2>';
    foreach ($failed_domains_http as $failed_domain) {
        echo $failed_domain . '<br>';
    }

    // check DNS
    if (!empty($checked_domains)) {
        $valid_domains = $letsencrypt->checkDNSValidity($checked_domains);
        $failed_domains_dns = array_diff($checked_domains, $valid_domains);

        # debug à améliorer
        echo '<h2>The following domain(s) failed the DNS check</h2>';
        foreach ($failed_domains_dns as $failed_domain) {
            echo $failed_domain . '<br>';
        }
    }

    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
} else {
    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/webadmin-letsencrypt.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
}
