<?php

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

# Package dependencies : php-net-dns2
try {
    require_once EVOADMIN_BASE . '../lib/letsencrypt.php';
    $letsencrypt = new LetsEncrypt();
} catch (Throwable $e) {
    $errorMessage = 'Erreur de chargement du module Let\'s Encrypt : vérifier que la librairie NetDNS2 (paquet php-net-dns2) est bien installée.</br>';
}

$debug_level = 0; # 0 = disabled; 1 = actions; 2 = actions + vars + warnings PHP

if ($debug_level >= 1) {
    ini_set('display_errors', 'On');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ERROR);
}
if ($debug_level >= 2) {
    error_reporting(E_ALL);
}

# Get web account infos
$web_account = $params[1];
$cmd = 'web-add.sh list-vhost ' . $web_account;
sudoexec($cmd, $data_output, $exec_return);
$data_split = explode(':', $data_output[0]);
$aliases = array_filter(explode(',', $data_split[3]), 'strlen'); // array_filter($array, 'strlen') removes empty elements
sort($aliases);
$server_name = $data_split[2];
$vhost_domains = [$server_name];
$vhost_domains = array_merge($vhost_domains, $aliases);
$is_enabled = $data_split[8];

try {
    if (isset($errorMessage) && ! empty($errorMessage)) {
        throw new Exception($errorMessage);
    }

    if (empty($vhost_domains)) {
        $errorMessage = 'Erreur : la liste des domaines est vide.</br>';
    }

    # Reset webaccount info in session if needed
    if (isset($_SESSION[$web_account]) && ! isset($_POST['submit-generate-cert'])) {
        unset($_SESSION[$web_account]);
    }

    if ($debug_level >= 2) {
        print_r($_SESSION[$web_account]);
        print('<br/>');
    }

    # Test domains eligibility for a new certificate
    if (isset($_POST['submit-test-domains'])) {
        # Make CSR
        if ($debug_level >= 1) {
            print('Making CSR with all domains.</br>');
        }
        $is_csr_generated = $letsencrypt->makeCsr($web_account, $vhost_domains, True);
        if (! $is_csr_generated) {
            throw new Exception('Erreur : La génération de la demande de certificat a échoué.<br/>
                Merci de contacter un administrateur pour continuer.');
        }

        # Run evoacme in dry-run (test included)
        # Debug
        if ($debug_level >= 1) {
            print('Sending dry-run certificate request to Let\'s Encrypt.</br>');
        }
        $dry_run_failed_domains = $letsencrypt->generateSSLCertificate($web_account, True);
        if ($debug_level >= 2) {
            print_r($dry_run_failed_domains);
            print('<br/>');
        }
    }

    # Generate a new certificate
    if (isset($_POST['submit-generate-cert'])) {
        # List eligible domains from session infos
        $csr_domains = [];
        foreach ($_SESSION[$web_account] as $domain => $domain_details) {
            if ($domain_details['is_eligible']) {
                $csr_domains[] = $domain;
            }
        }

        # Make CSR
        if ($debug_level >= 1) {
            print('Making CSR with eligible domains.</br>');
        }
        $is_csr_generated = $letsencrypt->makeCsr($web_account, $csr_domains, False);
        if (!$is_csr_generated) {
            throw new Exception('Erreur : La génération de la demande de certificat a échoué.<br/>
                Merci de contacter un administrateur.');
        }

        # Run evoacme
        if ($debug_level >= 1) {
            print('Sending certificate request to Let\'s Encrypt.</br>');
        }
        $failed_domains = $letsencrypt->generateSSLCertificate($web_account, False);
        if (! empty($failed_domains)) {
            $errorMessage = 'Erreur : La génération de certificat a échoué.<br/>
                Merci de contacter un administrateur.<br/>';
            foreach ($failed_domains as $domain => $error) {
                $errorMessage .= $domain . ' : erreur de type ' . $error['type'] . ', ' . $error['detail'] . '<br/>';
            }
            throw new Exception($errorMessage);
        }

        $cert_gen_succeded = True;

        unset($_SESSION[$web_account]);
    }

    # This array will contain vhost domains AND certs domains
    # (they may have been removed from vhost and still in cert)
    $domains = $vhost_domains;

    # Get the certificate served by the localhost for this ServerName
    if ($debug_level >= 1) {
        print('Retrieving certificate from localhost.</br>');
    }
    $vhost_cert = $letsencrypt->getCertificate($server_name);
    # Parse the cert to get relevant infos
    $cert_domains = [];
    if (! is_bool($vhost_cert)) {
        $parsed_cert = $letsencrypt->parseCertificate($vhost_cert);

        # Get domains
        $cert_domains = $letsencrypt->getCertificateDomains($parsed_cert);
        foreach ($cert_domains as $domain) {
            if (! in_array($domain, $domains)) {
                $domains[] = $domain;
            }
        }

        # Get expiration infos
        $cert_valid_until = date('d/m/Y', $parsed_cert['validUntil']);
        $cert_is_valid = $letsencrypt->isCertValid($parsed_cert['validUntil']); # /!\ checks only the date, not the chain
        # TODO vérifier la CA et la chaine ? Voir l'exemple https://www.php.net/manual/en/function.openssl-x509-verify.php#refsect1-function.openssl-x509-verify-examples
        # TODO: expiresSoon (2 weeks) + yellow color

        # Get issuer
        $cert_is_letsencrypt = $parsed_cert['isLetsEncrypt'];
        $cert_is_self_signed = $parsed_cert['isSelfSigned'];
        if ($cert_is_self_signed) {
            $cert_issuer = 'Auto-signé';
        } else {
            $cert_issuer = $parsed_cert['issuer'];
        }
    }

    # Get host IPs
    if ($debug_level >= 1) {
        print('Getting host IPs.</br>');
    }
    $host_ips = $letsencrypt->getHostIPs();

    # Gather infos domain by domain in $domains_details
    $domains_details = [];
    $are_not_covered_domains_eligible = False;
    $are_covered_domains_not_eligible = False;
    $are_covered_domains_not_in_vhost = False;
    foreach ($domains as $domain) {
        $domain_details = ['dns_msg' => '', 'domain_ips' => []];

        # Check if the domain is in the vhost
        $domain_is_in_vhost = in_array($domain, $vhost_domains);
        $domain_details['is_in_vhost'] = $domain_is_in_vhost;

        # Check if the domain is in the certificate
        $domain_is_in_cert = in_array($domain, $cert_domains);
        $domain_details['is_in_cert'] = $domain_is_in_cert;

        # Get the domain A records
        if ($debug_level >= 1) {
            print('Retrieving ' . $domain . ' DNS records from authoritative DNS.</br>');
        }
        $record_ips = $letsencrypt->getIPsFromAuthoritativeDNS($domain);

        # Check if the A records are in the server's IPs list
        if (is_bool($record_ips)) {
            $domain_details['is_dns_ok'] = False;
            $domain_details['dns_msg'] = 'Enregistrement DNS manquant';
        } else {
            $records_not_in_host_IPs = [];
            foreach ($record_ips as $record_ip) {
                $domain_details['domain_ips'][] = $record_ip;
                if (! $letsencrypt->isIPInArray($record_ip, $host_ips)) {
                    $records_not_in_host_IPs[] = $record_ip;
                }
            }
            $n_records_not_ok = count($records_not_in_host_IPs);
            $domain_details['is_dns_ok'] = $n_records_not_ok == 0;
            if ($n_records_not_ok == 1) {
                $domain_details['dns_msg'] = 'L\'IP ' . $records_not_in_host_IPs[0] . ' n\'appartient pas au serveur';
            } else if ($n_records_not_ok >= 1) {
                $domain_details['dns_msg'] = 'Les IPs n\'appartiennent pas au serveur :<br/>' . implode(', ', $records_not_in_host_IPs);
            }
        }

        if (isset($_POST['submit-test-domains'])) {
            # Setup session
            if (! isset($_SESSION[$web_account])) {
                $_SESSION[$web_account] = [];
            }

            # Set relevant domain infos in session
            if (in_array($domain, $vhost_domains)) {
                if (array_key_exists($domain, $dry_run_failed_domains)) {
                    $certbot_fail_msg = 'Type d\'erreur : ' . $dry_run_failed_domains[$domain]['type'] . '. Détail : ' . $dry_run_failed_domains[$domain]['detail'];
                    $domain_details['certbot_fail_msg'] = $certbot_fail_msg;
                    $_SESSION[$web_account][$domain]['is_eligible'] = False;
                } else {
                    $_SESSION[$web_account][$domain]['is_eligible'] = True;
                }
            } else {
                $_SESSION[$web_account][$domain]['is_eligible'] = False;
            }

            # Get relevant infos from session
            if (isset($_SESSION[$web_account][$domain]['is_eligible'])) {
                $domain_details['is_eligible'] = $_SESSION[$web_account][$domain]['is_eligible'];
            }
            if (! $domain_is_in_cert && $domain_details['is_eligible']) {
                $are_not_covered_domains_eligible = True;
            }
            if ($domain_is_in_cert && ! $domain_details['is_eligible']) {
                $are_covered_domains_not_eligible = True;
            }
            if ($domain_is_in_cert && ! $domain_is_in_vhost) {
                $are_covered_domains_not_in_vhost = True;
            }

        }

        $domains_details[$domain] = $domain_details;
    }

    # List eligible domains from session data
    if (isset($_SESSION[$web_account])) {
        $eligible_domains = [];
        foreach ($_SESSION[$web_account] as $domain => $domain_details) {
            if ($domain_details['is_eligible']) {
                $eligible_domains[] = $domain;
            }
        }
        if ($debug_level >= 2) {
            print_r($eligible_domains);
            print('<br/>');
        }
    }

    # Determine if generate cert button should be disabled
    $allow_test_domains = True;
    $allow_new_cert = True;
    if (! isset($eligible_domains)) {
        # Step 'Tester les domaines' not followed yet, or certificate was generated
        $allow_new_cert = False;
        $disallow_new_cert_msg = '';
    } else if (empty($eligible_domains)) {
        $allow_new_cert = False;
        $disallow_new_cert_msg = 'Aucun domaine n\'est éligible pour Let\'s Encrypt.';
    } else if ($vhost_cert) {
        if (! $cert_is_self_signed) { # allays allow if self-signed
            if (! $cert_is_letsencrypt) {
                $allow_new_cert = False;
                $disallow_new_cert_msg = 'Le certificat n\'est ni auto-signé, ni Let\'s Encrypt.';
            } else if ($cert_is_valid && (! $are_not_covered_domains_eligible && ! $are_covered_domains_not_in_vhost)) {
                $allow_new_cert = False;
                $disallow_new_cert_msg = 'Les domaines éligibles sont tous dans le certificat.';
            }
        }
    }

    # Check if evoacme is installed
    $evoacme_installed = $letsencrypt->isEvoacmeInstalled();
    if (! $evoacme_installed) {
        $allow_test_domains = False;
        $allow_new_cert = False;
        $disallow_new_cert_msg = 'Evoacme n\'est pas installé.';
    }

    # Debug: always enable new cert button
    # $allow_new_cert = True;

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

include_once EVOADMIN_BASE . '../tpl/webadmin-letsencrypt.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
